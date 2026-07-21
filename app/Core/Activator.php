<?php
/**
 * Fired during plugin activation.
 *
 * @package    NotifyBay
 * @subpackage Core
 * @since      1.0.0
 * @author     WPAnchorBay <sankarsan@gmail.com>
 */

namespace NotifyBay\Core;

use NotifyBay\Data\DbManager;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Fired during plugin activation.
 *
 * This class defines all code necessary to run during the plugin's activation.
 *
 * @since      1.0.0
 * @package    NotifyBay
 * @subpackage Core
 * @author     WPAnchorBay <sankarsan@gmail.com>
 */
class Activator {

	/**
	 * The main activation method.
	 *
	 * @since 1.0.0
	 * @access public
	 * @return void
	 */
	public static function activate() {
		// Set up the default options if they don't exist.
		/* Default Settings */
		Settings::get_instance()->update_settings( Settings::get_instance()->get_default_settings() );

		// Create custom database tables.
		self::create_custom_tables();

		// Register WooCommerce endpoints manually so they are included in the flush
		add_rewrite_endpoint( 'notifybay-waitlist', EP_PAGES );
		add_rewrite_endpoint( 'notifybay-wishlist', EP_PAGES );

		// Flush rewrite rules.
		flush_rewrite_rules();

		// Add custom capabilities.
		self::add_plugin_roles_and_capabilities();

		// Schedule cron events.
		Cron::get_instance()->schedule_events();

		notifybay_log( 'NotifyBay plugin activated successfully.', 'INFO' );
	}

	/**
	 * Instantiates the DB Manager and creates the custom tables.
	 *
	 * @since 1.0.0
	 * @access private
	 * @return void
	 */
	private static function create_custom_tables() {
		DbManager::get_instance()->create_tables();
	}


	/**
	 * Adds custom roles and capabilities required by the plugin.
	 *
	 * @since 1.0.0
	 * @access private
	 * @static
	 */
	private static function add_plugin_roles_and_capabilities() {
		$custom_capability = 'manage_notifybay';

		// Grant to administrators and WooCommerce shop managers so store staff can
		// manage waitlists/leads without full administrator access. Removal on
		// deactivation is handled across all roles by Deactivator.
		foreach ( array( 'administrator', 'shop_manager' ) as $role_name ) {
			$role = get_role( $role_name );
			if ( $role && ! $role->has_cap( $custom_capability ) ) {
				$role->add_cap( $custom_capability );
			}
		}
	}
}
