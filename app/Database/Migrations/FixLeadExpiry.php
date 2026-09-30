<?php
/**
 * Migration: Repair lead expiry timestamps written by the pre-1.0.3 subscribe path.
 *
 * @package    NotifyBay
 * @subpackage Database
 * @since      1.0.3
 * @author     WPAnchorBay <sankarsan@wpanchorbay.com>
 */

namespace NotifyBay\Database\Migrations;

use NotifyBay\Database\MigrationInterface;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class FixLeadExpiry
 *
 * Api\FrontendController::subscribe() had two defects that together corrupted
 * every `expires_at` value ever written:
 *
 * 1. It never consulted `appearance_waitlistExpiryEnabled`, so it applied
 *    `appearance_waitlistExpiryDefault` (90 by default) on stores that had
 *    never switched expiry on. Engine\CronTasks::cleanup_expired_leads() then
 *    flipped those leads to `expired`, silently dropping the customer.
 * 2. It wrote the value with gmdate() — UTC — while every other datetime column
 *    on the table is written with current_time( 'mysql' ), i.e. site-local. Every
 *    reader compares it against a local bound, so it was wrong by the site's
 *    UTC offset.
 *
 * The customer's own choice never reached the server at all (the frontend posted
 * `expiry` while the route read `notifybay_expiry`), so no stored value has ever
 * represented anything other than the configured default. That is what makes it
 * safe to key the repair off the merchant's current toggle: there are no
 * customer choices to preserve.
 */
class FixLeadExpiry implements MigrationInterface {

	/**
	 * Option flag claiming this repair.
	 *
	 * Data\DbManager::create_tables() re-runs *every* registered migration's
	 * up() on each NOTIFYBAY_DB_VERSION bump, which is safe for the idempotent
	 * dbDelta schema migrations but not for a one-shot data rewrite: shifting
	 * `expires_at` twice would be unrecoverable. The DB version alone cannot
	 * gate this, so the migration carries its own flag — claimed with
	 * add_option() so concurrent requests cannot both win it.
	 *
	 * @since 1.0.3
	 * @var string
	 */
	private const APPLIED_FLAG = 'notifybay_migration_expiry_fixed';

	/**
	 * How far the UTC shift has got, as the highest lead id already shifted.
	 *
	 * Only the shift branch needs this. clear_unwanted_expiry() sets a column to
	 * NULL where it is not already NULL, so re-running it changes nothing and it
	 * needs no memory of where it stopped.
	 *
	 * @since 1.0.3
	 * @var string
	 */
	private const CURSOR_OPTION = 'notifybay_migration_expiry_cursor';

	/**
	 * Rows per chunk.
	 *
	 * @since 1.0.3
	 * @var int
	 */
	private const CHUNK = 1000;

	/**
	 * Seconds this migration will spend in any one request before yielding.
	 *
	 * The whole point of resuming is that no single request carries the entire
	 * rewrite. Well under any plausible max_execution_time, because the request
	 * that triggers this is somebody's page load.
	 *
	 * @since 1.0.3
	 * @var float
	 */
	private const BUDGET_SECONDS = 5.0;

	/**
	 * Run the migration.
	 *
	 * @since 1.0.3
	 * @return void
	 */
	public function up() {
		/*
		 * Completion, not a claim. The exclusive add_option() claim that used to
		 * gate this is gone, and deliberately.
		 *
		 * It was taken BEFORE the work and never released, so a request that
		 * timed out part-way through -- the realistic outcome of an unbatched
		 * rewrite of every row on a large store, run during somebody's page load
		 * -- left the repair marked as taken and permanently unfinished, with
		 * nothing anywhere reporting it. Recovery meant knowing to delete an
		 * option nobody had heard of.
		 *
		 * What that claim was protecting against was two concurrent requests
		 * both shifting expires_at, which would move every value by twice the
		 * UTC offset and is not recoverable from the data. That protection now
		 * lives where it belongs: each chunk takes a row lock on the cursor
		 * (SELECT ... FOR UPDATE) and advances it in the same transaction as the
		 * rows it shifts, so concurrent runners serialise on the cursor and no
		 * row can be shifted twice -- while an interrupted run simply leaves the
		 * cursor where it got to, and the next request carries on.
		 */
		if ( 1 === (int) get_option( self::APPLIED_FLAG, 0 ) ) {
			return;
		}

		global $wpdb;

		$table = $wpdb->prefix . 'notifybay_leads';

		// Read the raw option rather than Core\Settings: this runs on `init` at
		// priority 1 and must not depend on Settings having been bootstrapped.
		$options = get_option( \NOTIFYBAY_OPTION_NAME, array() );
		$enabled = is_array( $options ) && ! empty( $options['appearance_waitlistExpiryEnabled'] );

		$finished = $enabled
			? $this->shift_to_local_time( $table )
			: $this->clear_unwanted_expiry( $table );

		if ( ! $finished ) {
			// Out of budget with rows left. The next request resumes from the
			// cursor; the flag stays unset so this runs again.
			return;
		}

		update_option( self::APPLIED_FLAG, 1 );
		delete_option( self::CURSOR_OPTION );
	}

	/**
	 * Whether this request has spent its share of the work.
	 *
	 * @since 1.0.3
	 * @param float $started microtime(true) when the work began.
	 * @return bool
	 */
	private function out_of_budget( $started ) {
		return ( microtime( true ) - $started ) >= self::BUDGET_SECONDS;
	}

	/**
	 * Expiry is switched off, so no lead should carry an expiry at all.
	 *
	 * This clears `expires_at` on every row, including ones already flipped to
	 * `expired`, and deliberately does NOT change any status. The distinction
	 * matters in both directions:
	 *
	 * - Not reviving is a consent decision. Five code paths set `expired` — a
	 *   timed-out double opt-in that was never confirmed, a trashed product, a
	 *   hidden product, a deliberate admin bulk action, and the bug being fixed
	 *   here — and nothing on the row distinguishes them. Flipping them all back
	 *   to `active` would put never-confirmed subscribers into the send queue.
	 * - But leaving their stale `expires_at` in place would break the one
	 *   recovery route a merchant does have. Api\AdminController's bulk action
	 *   writes only `status` and `updated_at`, so a lead restored to `active`
	 *   keeps its past `expires_at` and Engine\CronTasks::cleanup_expired_leads()
	 *   re-expires it on the next daily run. Clearing the column removes that
	 *   trap without reviving anyone.
	 *
	 * Batched, and it needs no cursor to resume: setting a column to NULL where
	 * it is not already NULL is idempotent, so an interrupted run simply leaves
	 * fewer rows for the next one to find.
	 *
	 * @since 1.0.3
	 * @param string $table Fully-qualified leads table name.
	 * @return bool True when nothing is left to clear.
	 */
	private function clear_unwanted_expiry( $table ) {
		global $wpdb;

		$started = microtime( true );

		do {
			$affected = $wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom {$wpdb->prefix}notifybay_leads table; a direct, uncached one-shot repair is the point of this migration.
				$wpdb->prepare(
					'UPDATE %i SET expires_at = NULL WHERE expires_at IS NOT NULL LIMIT %d',
					$table,
					self::CHUNK
				)
			);

			if ( $this->out_of_budget( $started ) ) {
				// Only unfinished if that chunk was full; a short one means the
				// rows ran out rather than the clock.
				return self::CHUNK > (int) $affected;
			}
		} while ( (int) $affected > 0 );

		return true;
	}

	/**
	 * Expiry is switched on, so convert the stored UTC values to site-local.
	 *
	 * The old write was gmdate( 'Y-m-d H:i:s', time() + N days ), so adding the
	 * site's UTC offset yields the local value the new code writes. Known limit:
	 * this uses the offset in force *now*, while each row captured the offset in
	 * force when it was created. A store that changed timezone since will be off
	 * by the difference, which is not recoverable from the data.
	 *
	 * Resumable, and safe against concurrent runners, because each chunk shifts
	 * a bounded id range and advances the cursor past it inside ONE transaction,
	 * having first taken a row lock on the cursor with SELECT ... FOR UPDATE. A
	 * second request entering at the same moment blocks on that lock, then reads
	 * the cursor the first one committed, so no row falls in two ranges. An
	 * interrupted request commits nothing for the chunk in flight, so the range
	 * is simply retried -- not shifted twice.
	 *
	 * That coupling is the whole design. Advancing the cursor in a separate
	 * statement would reopen the failure this guards: a crash in the gap would
	 * leave rows shifted and the cursor behind them, and the retry would shift
	 * them again -- moving expires_at by twice the UTC offset, which cannot be
	 * told apart from a correct value afterwards.
	 *
	 * @since 1.0.3
	 * @param string $table Fully-qualified leads table name.
	 * @return bool True when every row has been shifted.
	 */
	private function shift_to_local_time( $table ) {
		global $wpdb;

		// gmt_offset is in hours and may be fractional (e.g. 5.5, -9.5), so the
		// shift is expressed in minutes to avoid truncating half-hour zones.
		$offset_minutes = (int) round( (float) get_option( 'gmt_offset', 0 ) * 60 );

		if ( 0 === $offset_minutes ) {
			return true;
		}

		$max_id = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT MAX(id) FROM %i', $table ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom {$wpdb->prefix}notifybay_leads table; a direct, uncached one-shot repair is the point of this migration.

		if ( $max_id < 1 ) {
			return true;
		}

		// The cursor row must exist before it can be locked.
		add_option( self::CURSOR_OPTION, '0', '', 'no' );

		$started = microtime( true );

		while ( true ) {
			$wpdb->query( 'START TRANSACTION' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Transaction control for the chunked repair below.

			$cursor = (int) $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Row lock on the cursor; this is the serialisation point for concurrent runners.
				$wpdb->prepare(
					"SELECT option_value FROM {$wpdb->options} WHERE option_name = %s FOR UPDATE",
					self::CURSOR_OPTION
				)
			);

			if ( $cursor >= $max_id ) {
				$wpdb->query( 'COMMIT' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Transaction control.
				return true;
			}

			$upper = $cursor + self::CHUNK;

			$wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom {$wpdb->prefix}notifybay_leads table; a direct, uncached one-shot repair is the point of this migration.
				$wpdb->prepare(
					'UPDATE %i SET expires_at = DATE_ADD( expires_at, INTERVAL %d MINUTE ) WHERE expires_at IS NOT NULL AND id > %d AND id <= %d',
					$table,
					$offset_minutes,
					$cursor,
					$upper
				)
			);

			$wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Advancing the cursor must commit with the rows above, so it cannot go through update_option().
				$wpdb->prepare(
					"UPDATE {$wpdb->options} SET option_value = %s WHERE option_name = %s",
					(string) $upper,
					self::CURSOR_OPTION
				)
			);

			$wpdb->query( 'COMMIT' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Transaction control.

			// Written behind wpdb's back, so the cached value is now stale.
			wp_cache_delete( self::CURSOR_OPTION, 'options' );

			if ( $upper >= $max_id ) {
				return true;
			}

			if ( $this->out_of_budget( $started ) ) {
				return false;
			}
		}
	}

	/**
	 * Reverse the migration.
	 *
	 * Intentionally a no-op. The pre-migration values were wrong in both
	 * branches — either an expiry the merchant never asked for, or a UTC
	 * timestamp compared against local bounds — and the original per-lead intent
	 * was never recorded, so there is nothing meaningful to restore.
	 *
	 * @since 1.0.3
	 * @return void
	 */
	public function down() {
		delete_option( self::APPLIED_FLAG );
	}
}
