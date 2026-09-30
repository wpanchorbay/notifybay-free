<?php
/**
 * Fired during plugin deactivation.
 *
 * @package    NotifyBay
 * @subpackage Core
 * @since      1.0.0
 * @author     WPAnchorBay <sankarsan@wpanchorbay.com>
 */

namespace NotifyBay\Core;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Fired during plugin deactivation.
 *
 * @since      1.0.0
 * @package    NotifyBay
 * @subpackage NotifyBay/Core
 * @author     WPAnchorBay <sankarsan@wpanchorbay.com>
 */
class Deactivator {

	/**
	 * Fired during plugin deactivation.
	 *
	 * @since 1.0.0
	 * @access public
	 * @return void
	 */
	public static function deactivate() {
		self::remove_custom_capabilities();

		// Unschedule all plugin cron events.
		Cron::get_instance()->unschedule_all();
	}

	/**
	 * Removes the custom plugin capabilities from all roles.
	 *
	 * Uses wp_roles()->get_names() rather than get_editable_roles(). The latter
	 * is defined only in wp-admin/includes/user.php, which the REST plugins
	 * controller never loads -- it pulls in plugin.php, file.php,
	 * class-wp-upgrader.php and plugin-install.php and nothing else. So
	 * deactivating over `PUT /wp/v2/plugins/<plugin>` fatalled with
	 * "Call to undefined function NotifyBay\Core\get_editable_roles()",
	 * and because core fires deactivate_{$plugin} *before* it writes
	 * active_plugins, the fatal aborted that write and the plugin was left
	 * active -- a deactivation that reports a 500 and silently does nothing.
	 * wp_roles() lives in wp-includes and is available in every context.
	 *
	 * Iterating every role (rather than the editable_roles-filtered subset) is
	 * also the more thorough choice here: a role hidden from that filter can
	 * still hold the capability.
	 *
	 * @since 1.0.0
	 * @access private
	 * @return void
	 */
	private static function remove_custom_capabilities() {
		$roles             = wp_roles()->get_names();
		$custom_capability = 'manage_notifybay';

		foreach ( array_keys( $roles ) as $role_name ) {
			$role = get_role( $role_name );
			if ( $role && $role->has_cap( $custom_capability ) ) {
				$role->remove_cap( $custom_capability );
			}
		}
	}
}
