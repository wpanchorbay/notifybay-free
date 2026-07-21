<?php
/**
 * Migration: Create NotifyBay Leads Table
 *
 * @package    NotifyBay
 * @subpackage Database
 * @since      1.0.0
 * @author     WPAnchorBay <sankarsan@gmail.com>
 */

namespace NotifyBay\Database\Migrations;

use NotifyBay\Database\MigrationInterface;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class CreateNotifybayLeadsTable
 */
class CreateNotifybayLeadsTable implements MigrationInterface {

	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up() {
		global $wpdb;

		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$wpdb->prefix}notifybay_leads (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			user_email VARCHAR(191) NOT NULL,
			user_id BIGINT UNSIGNED DEFAULT NULL,
			product_id BIGINT UNSIGNED NOT NULL,
			variation_id BIGINT UNSIGNED DEFAULT 0 NOT NULL,
			type ENUM('waitlist', 'wishlist') NOT NULL,
			status ENUM('pending_verification', 'active', 'processing', 'notified', 'notified_hurry', 'failed', 'converted', 'expired', 'unsubscribed') NOT NULL DEFAULT 'active',
			price_at_subscription DECIMAL(10,2) DEFAULT NULL,
			user_currency VARCHAR(3) DEFAULT NULL,
			verification_token VARCHAR(64) DEFAULT NULL,
			user_locale VARCHAR(10) DEFAULT NULL,
			product_name_snapshot VARCHAR(255) DEFAULT NULL,
			retry_count TINYINT UNSIGNED NOT NULL DEFAULT 0,
			last_batch_id VARCHAR(36) DEFAULT NULL,
			expires_at DATETIME DEFAULT NULL,
			notified_at DATETIME DEFAULT NULL,
			created_at DATETIME NOT NULL,
			updated_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			KEY idx_product_variation_status (product_id, variation_id, status),
			KEY idx_user_email_status (user_email, status),
			KEY idx_verification_token (verification_token),
			KEY idx_status_expires_at (status, expires_at),
			KEY idx_status_created_at (status, created_at),
			UNIQUE KEY idx_unique_active_lead (user_email, product_id, variation_id, type)
		) $charset_collate;";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}

	/**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
	public function down() {
		global $wpdb;
		$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}notifybay_leads" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange -- Custom {$wpdb->prefix}notifybay_leads table; values are bound via prepare(). Direct, uncached queries are intentional for this real-time data-access layer.
	}
}
