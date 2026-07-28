<?php
/**
 * Dispatcher Engine.
 *
 * Handles the logic for querying leads and batching them into individual worker jobs.
 *
 * @package    NotifyBay
 * @subpackage Engine
 * @since      1.0.0
 * @author     WPAnchorBay <sankarsan@wpanchorbay.com>
 */

namespace NotifyBay\Engine;

use NotifyBay\Core\Plugin;
use NotifyBay\Core\Settings;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Dispatcher
 */
class Dispatcher {


	/**
	 * The single instance of the class.
	 *
	 * @var Dispatcher
	 */
	private static $instance = null;

	/**
	 * Get the instance.
	 *
	 * @return Dispatcher
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Register hooks with the plugin loader.
	 *
	 * @param Plugin $plugin The plugin instance.
	 */
	public function run( Plugin $plugin ) {
		$loader = $plugin->get_loader();
		// Hook into the async actions scheduled by Action Scheduler. Free only
		// handles the base back-in-stock waitlist dispatch and the verification
		// email. A premium add-on (NotifyBay Pro) may register its own Action
		// Scheduler handlers separately under its own hook names.
		$loader->add_action( 'notifybay_run_dispatcher', $this, 'dispatch_waitlist', 10, 2 );
		$loader->add_action( 'notifybay_run_verification', $this, 'dispatch_verification', 10, 1 );
	}

	/**
	 * Dispatch waitlist notifications (Restock).
	 *
	 * @param int $product_id   The product ID.
	 * @param int $variation_id The variation ID (0 if simple).
	 */
	public function dispatch_waitlist( $product_id, $variation_id = 0 ) {

		global $wpdb;

		$settings = Settings::get_instance();

		// 1. Determine available stock
		$product = wc_get_product( $variation_id ? $variation_id : $product_id );

		if ( ! $product || ! $product->is_in_stock() ) {

			return; // Product is not in stock anymore, abort
		}

		$stock_quantity = $product->get_stock_quantity();

		// Minimum stock threshold check
		$min_threshold = (int) $settings->get_settings( 'engine_minStockThreshold', 0 );
		if ( $min_threshold > 0 && $stock_quantity !== null && $stock_quantity < $min_threshold ) {

			return; // Restock is too small, skip for now
		}

		/**
		 * Filters the maximum number of waitlist leads to notify for this restock.
		 *
		 * Used by NotifyBay Pro to implement the Fair-Play reservation-window engine.
		 * Contract: return `null` for "unlimited" (the Free default — notify everyone
		 * on the waitlist); return an integer `>= 0` for an exact cap, where `0`
		 * legitimately means "notify no one this run" (e.g. all stock is currently
		 * reserved for previously-notified leads). Do not use `0` to mean unlimited.
		 *
		 * @since 1.0.0
		 * @hook notifybay_waitlist_notify_limit
		 * @param int|null   $notify_limit   Default `null` (unlimited).
		 * @param \WC_Product $product        The product (or variation) being restocked.
		 * @param int|null   $stock_quantity Current stock quantity, or `null` if stock isn't managed.
		 * @param int        $product_id     The product ID.
		 * @param int        $variation_id   The variation ID (0 if simple).
		 * @return int|null
		 */
		$notify_limit = apply_filters( 'notifybay_waitlist_notify_limit', null, $product, $stock_quantity, $product_id, $variation_id );

		if ( 0 === $notify_limit ) {

			return; // All stock is currently reserved; nobody to notify this run.
		}

		// 2. Query active leads for this product/variation. The two branches differ
		// only by the optional LIMIT, kept in fully-literal queries so nothing is
		// concatenated into the SQL. Table (%i) and all values are bound.
		$table     = $wpdb->prefix . 'notifybay_leads';
		$now_mysql = current_time( 'mysql' );

		if ( null !== $notify_limit && $notify_limit > 0 ) {
			$lead_ids = $wpdb->get_col( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom {$wpdb->prefix}notifybay_leads table; a direct, uncached read is intentional for this real-time dispatch loop.
				$wpdb->prepare(
					"SELECT id FROM %i WHERE product_id = %d AND variation_id = %d AND type = 'waitlist' AND status = 'active' AND (expires_at IS NULL OR expires_at = '0000-00-00 00:00:00' OR expires_at > %s) ORDER BY created_at ASC LIMIT %d",
					$table,
					$product_id,
					$variation_id,
					$now_mysql,
					$notify_limit
				)
			);
		} else {
			$lead_ids = $wpdb->get_col( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom {$wpdb->prefix}notifybay_leads table; a direct, uncached read is intentional for this real-time dispatch loop.
				$wpdb->prepare(
					"SELECT id FROM %i WHERE product_id = %d AND variation_id = %d AND type = 'waitlist' AND status = 'active' AND (expires_at IS NULL OR expires_at = '0000-00-00 00:00:00' OR expires_at > %s) ORDER BY created_at ASC",
					$table,
					$product_id,
					$variation_id,
					$now_mysql
				)
			);
		}

		if ( empty( $lead_ids ) ) {

			return; // No one to notify
		}

		// 4. Update status to 'processing' to lock them
		$batch_id        = wp_generate_uuid4();
		$ids_placeholder = implode( ',', array_fill( 0, count( $lead_ids ), '%d' ) );
		$wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom {$wpdb->prefix}notifybay_leads table; a direct, uncached bulk write is intentional for this real-time dispatch loop.
			$wpdb->prepare( // phpcs:ignore WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- The IN() list is a runtime-sized %d placeholder set; the arg count always matches.
				"UPDATE %i SET status = 'processing', updated_at = %s, last_batch_id = %s WHERE id IN ({$ids_placeholder}) AND status = 'active'", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $ids_placeholder is a literal list of %d placeholders; the table and every value are bound.
				array_merge( array( $table, current_time( 'mysql' ), $batch_id ), $lead_ids )
			)
		);

		// 5. Enqueue worker jobs
		foreach ( $lead_ids as $lead_id ) {

			if ( function_exists( 'as_enqueue_async_action' ) ) {

				as_enqueue_async_action( 'notifybay_send_email_worker', array( $lead_id, 'waitlist_restock' ), 'notifybay_alerts' );
			}
		}

		// Note: For massive lists (e.g. >500), we should ideally batch this in the DB query
		// and re-enqueue the dispatcher if $notify_limit was 0 (unlimited) and we hit a batch size.
		// For now, this implementation supports the standard Fair-Play limits perfectly.
	}

	/**
	 * Dispatch verification email.
	 *
	 * @param int $lead_id The lead ID.
	 */
	public function dispatch_verification( $lead_id ) {
		// Just proxy to worker
		if ( function_exists( 'as_enqueue_async_action' ) ) {
			as_enqueue_async_action( 'notifybay_send_email_worker', array( (int) $lead_id, 'verification' ), 'notifybay_alerts' );
		}
	}
}
