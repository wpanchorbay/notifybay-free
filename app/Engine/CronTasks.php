<?php
/**
 * Cron Tasks for Engine.
 *
 * Handles crash recovery and stale data cleanup.
 *
 * @package    NotifyBay
 * @subpackage Engine
 * @since      1.0.0
 * @author     WPAnchorBay <sankarsan@gmail.com>
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
		// Note: the hurry-alert dispatcher trigger (notifybay_daily_hurry_check ->
		// trigger_hurry_dispatcher) is premium (Hurry/scarcity alerts) and is scheduled
		// and handled directly by NotifyBay Pro, not here.
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

		// 15 minutes ago
		$cutoff       = time() - ( 15 * MINUTE_IN_SECONDS );
		$cutoff_mysql = gmdate( 'Y-m-d H:i:s', $cutoff );

		$wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
			$wpdb->prepare(
				"UPDATE {$table} SET status = 'active', updated_at = %s WHERE status = 'processing' AND updated_at <= %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				current_time( 'mysql' ),
				$cutoff_mysql
			)
		);
	}

	/**
	 * Daily cleanup job: flip `pending_verification` leads to `expired` if token timed out (24h).
	 */
	public function cleanup_expired_verifications() {
		global $wpdb;
		$table = $wpdb->prefix . 'notifybay_leads';

		// 24 hours ago
		$cutoff       = time() - DAY_IN_SECONDS;
		$cutoff_mysql = gmdate( 'Y-m-d H:i:s', $cutoff );

		$wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
			$wpdb->prepare(
				"UPDATE {$table} SET status = 'expired', updated_at = %s WHERE status = 'pending_verification' AND created_at <= %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				current_time( 'mysql' ),
				$cutoff_mysql
			)
		);
	}

	/**
	 * Daily cleanup job: flip leads with `expires_at` in the past to `expired`.
	 */
	public function cleanup_expired_leads() {
		global $wpdb;
		$table     = $wpdb->prefix . 'notifybay_leads';
		$now_mysql = current_time( 'mysql' );

		$wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
			$wpdb->prepare(
				"UPDATE {$table} SET status = 'expired', updated_at = %s WHERE status = 'active' AND expires_at IS NOT NULL AND expires_at <= %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$now_mysql,
				$now_mysql
			)
		);
	}
}
