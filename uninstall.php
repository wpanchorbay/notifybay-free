<?php
/**
 * NotifyBay Uninstall
 *
 * Fired when the plugin is deleted.
 *
 * @package    NotifyBay
 * @since      1.0.0
 * @author     WPAnchorBay <sankarsan@wpanchorbay.com>
 */

// If uninstall not called from WordPress, die.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// Check if Deep Uninstall is enabled.
// NOTE: the option is 'notifybay' (NOTIFYBAY_OPTION_NAME), not 'notifybay_settings' —
// this previously read the wrong key, so Deep Uninstall never actually fired.
$notifybay_settings       = get_option( 'notifybay' );
$notifybay_deep_uninstall = isset( $notifybay_settings['advanced_deleteAllOnUninstall'] ) ? (bool) $notifybay_settings['advanced_deleteAllOnUninstall'] : false;

if ( $notifybay_deep_uninstall ) {
	global $wpdb;

	// Drop the shared leads table. This also removes any rows a premium add-on
	// (NotifyBay Pro) wrote — the add-on owns no tables of its own.
	$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}notifybay_leads" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared

	// Delete options. This also removes Pro's license_key/license_status,
	// which live inside this same shared option — Pro's own uninstall.php
	// only needs to handle the case where Pro is deleted while Free stays.
	delete_option( 'notifybay' );
	delete_option( 'notifybay_version' );

	// Delete transients (covers both notifybay_* and notifybaypro_* prefixes).
	$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_notifybay%'" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_notifybay%'" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared

	/*
	 * The MCP endpoint's settings row. Inside the deep-uninstall branch to
	 * match this file's existing convention that only a deep uninstall removes
	 * options.
	 *
	 * Kit::uninstall() deletes this product's row and nothing else: never the
	 * shared wpab_mcp_access capability (another WPAB plugin on the same site
	 * may still need it), never another product's row, and never an
	 * Application Password — those are the store owner's WordPress
	 * credentials, not this plugin's data.
	 */
	if ( file_exists( __DIR__ . '/vendor/autoload.php' ) ) {
		require_once __DIR__ . '/vendor/autoload.php';

		if ( class_exists( '\WPAB\Mcp\Kit' ) ) {
			\WPAB\Mcp\Kit::uninstall( 'notifybay' );
		}
	}
}
