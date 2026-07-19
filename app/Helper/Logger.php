<?php
/**
 * Handles all logging for the plugin.
 *
 * @package    NotifyBay
 * @subpackage Helper
 * @since      1.0.0
 * @author     WPAnchorBay <sankarsan@gmail.com>
 */

namespace NotifyBay\Helper;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use NotifyBay\Core\Cron;

/**
 * Handles all logging for the plugin.
 *
 * Provides a central point for logging events to daily log files.
 * Works with the Cron class for cache management and purging.
 *
 * @since      1.0.0
 * @package    NotifyBay
 * @subpackage NotifyBay/Helper
 * @author     WPAnchorBay <sankarsan@gmail.com>
 */
class Logger {

	/**
	 * The instance of the Logger class.
	 *
	 * @since 1.0.0
	 * @access private
	 * @var Logger
	 */
	private static $instance = null;

	/**
	 * Gets an instance of the Logger class.
	 *
	 * @since 1.0.0
	 * @access public
	 * @return Logger
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Private constructor for singleton.
	 *
	 * @since 1.0.0
	 * @access private
	 */
	private function __construct() {}

	/**
	 * Central logging function for the entire plugin.
	 *
	 * Records events to daily log files.
	 *
	 * @since 1.0.0
	 * @access public
	 * @param string $log_type The category of the log entry (e.g., 'info', 'error', 'activity').
	 * @param string $message  A short, human-readable message describing the event.
	 * @param array  $context  Optional. An associative array of contextual data to store.
	 * @return void
	 */
	public function log( $log_type, $message, $context = array() ) {
		$level = $log_type;
		if ( ! empty( $context ) ) {
			$message .= ' ' . wp_json_encode( $context );
		}
		notifybay_log( $message, $level );
	}

	/**
	 * Get the log directory path.
	 *
	 * @since 1.0.0
	 * @access public
	 * @return string
	 */
	public static function get_log_dir() {
		$upload_dir = wp_upload_dir();
		return $upload_dir['basedir'] . '/' . \NOTIFYBAY_TEXT_DOMAIN . '-logs/';
	}

	/**
	 * Get the log file path for a specific date.
	 *
	 * @since 1.0.0
	 * @access public
	 * @param string $date Date in Y-m-d format. Defaults to today.
	 * @return string
	 */
	public static function get_log_file_for_date( $date = '' ) {
		if ( empty( $date ) ) {
			$date = gmdate( 'Y-m-d' );
		}
		return self::get_log_dir() . 'plugin-log-' . $date . '.log';
	}

	/**
	 * Get the merged logs for the last 30 days.
	 *
	 * Delegates to Cron::get_merged_logs() which reads the transient
	 * cache (last 29 days) and appends today's live file.
	 *
	 * @since 1.0.0
	 * @access public
	 * @return string
	 */
	public static function get_merged_logs() {
		return Cron::get_instance()->get_merged_logs();
	}

	/**
	 * Purge log files older than the retention period.
	 *
	 * Delegates to Cron::purge_old_logs().
	 *
	 * @since 1.0.0
	 * @access public
	 * @return void
	 */
	public static function purge_old_logs() {
		Cron::get_instance()->purge_old_logs();
	}

	/**
	 * Rebuild the log cache transient.
	 *
	 * Delegates to Cron::rebuild_log_cache().
	 *
	 * @since 1.0.0
	 * @access public
	 * @return void
	 */
	public static function rebuild_cache() {
		Cron::get_instance()->rebuild_log_cache();
	}
}
