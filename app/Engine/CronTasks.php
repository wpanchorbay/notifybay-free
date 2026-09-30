<?php
/**
 * Cron Tasks for Engine.
 *
 * Handles crash recovery and stale data cleanup.
 *
 * @package    NotifyBay
 * @subpackage Engine
 * @since      1.0.0
 * @author     WPAnchorBay <sankarsan@wpanchorbay.com>
 */

namespace NotifyBay\Engine;

use NotifyBay\Core\Plugin;
use NotifyBay\Models\Lead;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class CronTasks
 */
class CronTasks {

	/**
	 * The single instance of the class.
	 *
	 * @var CronTasks
	 */
	private static $instance = null;

	/**
	 * Hours a queued send may be overdue before the queue counts as dead.
	 *
	 * See leads_with_a_queued_send() for why this is far above the retry
	 * backoff rather than near this job's own 15-minute cutoff.
	 *
	 * @since 1.0.3
	 * @var int
	 */
	private const DEAD_QUEUE_HOURS = 24;

	/**
	 * How many queued-send lead ids this job will carry into its NOT IN clause.
	 *
	 * Engine\Dispatcher enqueues one action per lead, so a restock on a product
	 * with a large waitlist puts that many rows in the protected set, and every
	 * one of them becomes a bound %d in a single prepare(). Past some size that
	 * statement stops being a query and starts being a way for this job to fail.
	 *
	 * When the set is bigger than this, the reclaim is SKIPPED rather than
	 * truncated. Truncating would silently drop protection from the leads past
	 * the cut and reclaim them -- which is the duplicate-email bug this
	 * protection exists to prevent, re-created on precisely the large waitlists
	 * where it does the most damage. A skipped pass costs nothing: the leads are
	 * queued, so they are not stuck, and DEAD_QUEUE_HOURS ages a genuinely dead
	 * queue out of the protected set until the reclaim can run again.
	 *
	 * @var int
	 */
	private const MAX_PROTECTED_IDS = 2000;

	/**
	 * Get the instance.
	 *
	 * @return CronTasks
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Register hooks.
	 *
	 * @param Plugin $plugin The plugin instance.
	 */
	public function run( Plugin $plugin ) {
		$loader = $plugin->get_loader();

		// Register Action Scheduler jobs if they aren't scheduled
		$loader->add_action( 'init', $this, 'schedule_jobs' );

		// The actual worker functions.
		// Note: additional premium dispatch jobs are scheduled and handled directly
		// by a premium add-on (NotifyBay Pro), not here.
		$loader->add_action( 'notifybay_stale_processing_recovery', $this, 'recover_stale_processing_leads' );
		$loader->add_action( 'notifybay_verification_cleanup', $this, 'cleanup_expired_verifications' );
		$loader->add_action( 'notifybay_expiry_cleanup', $this, 'cleanup_expired_leads' );
	}

	/**
	 * Schedule the recurring Action Scheduler jobs.
	 */
	public function schedule_jobs() {
		if ( ! function_exists( 'as_next_scheduled_action' ) ) {
			return; // Action Scheduler not ready
		}

		// Run every 30 minutes
		if ( false === as_next_scheduled_action( 'notifybay_stale_processing_recovery' ) ) {
			as_schedule_recurring_action( time(), 1800, 'notifybay_stale_processing_recovery', array(), 'notifybay_alerts' );
		}

		// Run daily
		if ( false === as_next_scheduled_action( 'notifybay_verification_cleanup' ) ) {
			as_schedule_recurring_action( time(), DAY_IN_SECONDS, 'notifybay_verification_cleanup', array(), 'notifybay_alerts' );
		}

		// Run daily expiry cleanup
		if ( false === as_next_scheduled_action( 'notifybay_expiry_cleanup' ) ) {
			as_schedule_recurring_action( time(), DAY_IN_SECONDS, 'notifybay_expiry_cleanup', array(), 'notifybay_alerts' );
		}
	}

	/**
	 * Audit job: find leads stuck in `processing` for >15 minutes and reset them to `active`.
	 */
	public function recover_stale_processing_leads() {
		global $wpdb;
		$table = $wpdb->prefix . 'notifybay_leads';

		/*
		 * The cutoff MUST be built in the same clock the column is written in.
		 * updated_at comes from current_time( 'mysql' ), which is site-local,
		 * and this compared it against a gmdate() cutoff in UTC. On a store
		 * behind UTC -- most of the US -- the stored value is already older
		 * than the UTC cutoff, so a lead the dispatcher had only just set to
		 * `processing` was reclaimed within seconds, while its worker was still
		 * in flight, and then dispatched a second time. Ahead of UTC the
		 * opposite happened: nothing was reclaimed for hours, which strands the
		 * recovery path a disabled restock email now depends on.
		 * cleanup_expired_verifications() below already builds its bound
		 * correctly. (An earlier version of this comment named
		 * expire_reservations(), which does not exist in either plugin.)
		 */
		$cutoff_mysql = current_datetime()->modify( '-15 minutes' )->format( 'Y-m-d H:i:s' );

		/*
		 * `active` is only provably the right home for an abandoned claim when
		 * the claim came from Engine\Dispatcher, whose UPDATE carries
		 * `AND status = 'active'`. Two other claimers take leads from
		 * elsewhere -- Mcp\Tools\Leads::resend_notifications(), and NotifyBay
		 * Pro's hurry alert -- and each records what it took in
		 * `last_batch_id`. Before this CASE, an abandoned claim from either
		 * handed a finished lead back to the live waitlist and the next
		 * restock emailed that customer a second time.
		 *
		 * See Lead::CLAIM_RESTORE_TOKENS for why that column, why the token is
		 * left in place here rather than cleared, and why the Dispatcher needs
		 * no token of its own. Anything the map does not recognise -- a real
		 * uuid4 batch id, NULL, an older or newer sibling plugin's value --
		 * falls through to `active`, exactly as this job always behaved.
		 */
		$restore_case = '';
		$query_args   = array( $table );

		foreach ( Lead::CLAIM_RESTORE_TOKENS as $claim_token => $restore_to ) {
			$restore_case .= ' WHEN last_batch_id = %s THEN %s';
			$query_args[]  = $claim_token;
			$query_args[]  = $restore_to;
		}

		$query_args[] = current_time( 'mysql' );
		$query_args[] = $cutoff_mysql;

		/*
		 * A lead with a send still queued is not abandoned, and reclaiming it
		 * is how the plugin's own retry budget was being spent without ever
		 * being used. Engine\Worker::handle_failure() parks the lead in
		 * `processing` and schedules the next attempt 5 then 30 minutes out;
		 * this job's cutoff is 15 minutes and it runs every 30, so the second
		 * retry was a coin flip -- when this job won, the retry fired,
		 * Worker::process_job() found a status other than `processing` and
		 * returned without sending or logging anything.
		 *
		 * Only `pending` actions are protected, and that is the whole reason
		 * this costs nothing. The failure this job exists for -- a worker that
		 * died mid-send -- leaves its action `in-progress` or `failed`, never
		 * `pending`, so a genuinely crashed lead is still reclaimed at the
		 * 15-minute cutoff exactly as before.
		 *
		 * This overlaps the CASE above rather than replacing it. Both claimers
		 * that write a token enqueue immediately afterwards, so in the ordinary
		 * case their leads are protected here and the CASE never sees them. It
		 * still has three ways to fire: a queue dead past DEAD_QUEUE_HOURS, an
		 * Action Scheduler storing its actions somewhere this cannot read, and
		 * an action consumed without rescheduling. Losing the CASE would put a
		 * finished lead back on the live waitlist in all three.
		 */
		$protected_ids = self::leads_with_a_queued_send();

		/*
		 * Over the cap, do nothing at all this pass. See MAX_PROTECTED_IDS: the
		 * alternative is a NOT IN list long enough to break the statement, and
		 * the alternative to THAT is dropping protection from the overflow and
		 * mailing those customers twice.
		 */
		if ( null === $protected_ids ) {
			if ( function_exists( 'notifybay_log' ) ) {
				notifybay_log(
					sprintf(
						'Stale-lead recovery skipped: more than %d sends are still queued, above what the reclaim can exclude safely. It will run again once the queue drains.',
						self::MAX_PROTECTED_IDS
					),
					'warning'
				);
			}

			return;
		}

		$exclude_sql = '';

		if ( ! empty( $protected_ids ) ) {
			$exclude_sql = ' AND id NOT IN (' . implode( ',', array_fill( 0, count( $protected_ids ), '%d' ) ) . ')';
			$query_args  = array_merge( $query_args, $protected_ids );
		}

		// The placeholder-count sniff cannot see through prepare()'s array form
		// and reads $query_args as a single replacement, so it is disabled for
		// this one statement. Every value in $query_args, the table name
		// included, is still bound.
		// phpcs:disable WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber
		$wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom {$wpdb->prefix}notifybay_leads table; a direct, uncached write is intentional for this scheduled maintenance job.
			$wpdb->prepare(
				"UPDATE %i SET status = CASE{$restore_case} ELSE 'active' END, updated_at = %s WHERE status = 'processing' AND updated_at <= %s{$exclude_sql}", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $restore_case and $exclude_sql are built directly above from literal SQL and placeholders only.
				$query_args
			)
		);
		// phpcs:enable WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber
	}

	/**
	 * Lead ids that still have a send queued and not yet started.
	 *
	 * Overdue actions are included, because a queue running late is the normal
	 * state on a store without a real cron -- that lateness is the entire
	 * reason recover_stale_processing_leads() exists -- and reclaiming a lead
	 * whose retry is merely late rebuilds the bug this protection fixes.
	 *
	 * Not indefinitely, though. Past DEAD_QUEUE_HOURS the queue is not slow,
	 * it is not running, and every lead in it would otherwise stay
	 * `processing` for good with nothing visibly wrong. The bound is deliberately
	 * far above the 30-minute retry backoff rather than near this job's own
	 * 15-minute cutoff: 15 minutes of lateness is the ordinary case, so a
	 * threshold anywhere near it would protect nothing.
	 *
	 * Returns an empty array when Action Scheduler stores its actions
	 * somewhere other than the default tables, which degrades this job to the
	 * behaviour it had before the check existed rather than to protecting
	 * everything or erroring.
	 *
	 * @since 1.0.3
	 * @return int[]|null Lead ids; empty when nothing is queued or Action
	 *                    Scheduler is unreadable, null when there are too many
	 *                    to exclude safely and the caller must not reclaim.
	 */
	private static function leads_with_a_queued_send() {
		global $wpdb;

		$actions = $wpdb->prefix . 'actionscheduler_actions';

		if ( ! $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $actions ) ) ) ) { // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Action Scheduler tables; a direct, uncached read is intentional for this scheduled maintenance job.
			return array();
		}

		$rows = $wpdb->get_col( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Action Scheduler tables; a direct, uncached read is intentional for this scheduled maintenance job.
			$wpdb->prepare(
				'SELECT args FROM %i WHERE hook = %s AND status = %s AND scheduled_date_gmt >= %s LIMIT %d',
				$actions,
				'notifybay_send_email_worker',
				'pending',
				gmdate( 'Y-m-d H:i:s', time() - ( self::DEAD_QUEUE_HOURS * HOUR_IN_SECONDS ) ),
				self::MAX_PROTECTED_IDS + 1
			)
		);

		/*
		 * Decided on ROWS FETCHED, before anything below reduces them.
		 *
		 * The obvious version of this check -- count the returned lead ids and
		 * compare -- is wrong, and wrong in the unsafe direction. $ids is keyed
		 * by lead id, so duplicates collapse, and Action Scheduler really can
		 * hold more than one pending send for a lead (a retry queued while the
		 * original is still pending). Rows that fail to decode vanish too. So
		 * 2001 rows can yield 1500 ids, slip under the cap, and let the reclaim
		 * run against a set that is missing every lead past the LIMIT -- which
		 * is precisely the silent truncation this is supposed to prevent.
		 *
		 * null means "the protected set is unknowable", not "empty". The caller
		 * must skip, not proceed.
		 */
		if ( count( (array) $rows ) > self::MAX_PROTECTED_IDS ) {
			return null;
		}

		$ids = array();

		foreach ( (array) $rows as $args ) {
			$decoded = json_decode( (string) $args, true );

			// The lead id is the first argument. Action Scheduler stores it as
			// a number from one call path and a string from another, so this
			// casts rather than matching a spelling -- the same reason
			// Mcp\Tools\Leads::last_action_error() checks both forms.
			if ( is_array( $decoded ) && isset( $decoded[0] ) && is_scalar( $decoded[0] ) ) {
				$id = (int) $decoded[0];

				if ( $id > 0 ) {
					$ids[ $id ] = $id;
				}
			}
		}

		return array_values( $ids );
	}

	/**
	 * Daily cleanup job: flip `pending_verification` leads to `expired` if token timed out (24h).
	 */
	public function cleanup_expired_verifications() {
		global $wpdb;
		$table = $wpdb->prefix . 'notifybay_leads';

		// Same local-vs-UTC mismatch as recover_stale_processing_leads():
		// created_at is written with current_time( 'mysql' ), so the cutoff has
		// to be local too or tokens expire early or never.
		$cutoff_mysql = current_datetime()->modify( '-1 day' )->format( 'Y-m-d H:i:s' );

		$wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom {$wpdb->prefix}notifybay_leads table; a direct, uncached write is intentional for this scheduled maintenance job.
			$wpdb->prepare(
				"UPDATE %i SET status = 'expired', updated_at = %s WHERE status = 'pending_verification' AND created_at <= %s",
				$table,
				current_time( 'mysql' ),
				$cutoff_mysql
			)
		);
	}

	/**
	 * Daily cleanup job: flip leads with `expires_at` in the past to `expired`.
	 *
	 * The zero-date exclusion keeps this in step with the other two readers of
	 * this column — Engine\Dispatcher and Models\Lead::has_active_waitlist() both
	 * treat '0000-00-00 00:00:00' as "no expiry". Without it a zero-date row is
	 * non-NULL and sorts before now, so this job would expire a lead the
	 * dispatcher still considers live.
	 */
	public function cleanup_expired_leads() {
		global $wpdb;
		$table     = $wpdb->prefix . 'notifybay_leads';
		$now_mysql = current_time( 'mysql' );

		$wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom {$wpdb->prefix}notifybay_leads table; a direct, uncached write is intentional for this scheduled maintenance job.
			$wpdb->prepare(
				"UPDATE %i SET status = 'expired', updated_at = %s WHERE status = 'active' AND expires_at IS NOT NULL AND expires_at != '0000-00-00 00:00:00' AND expires_at <= %s",
				$table,
				$now_mysql,
				$now_mysql
			)
		);
	}
}
