<?php
/**
 * Plugin Name:       NotifyBay
 * Plugin URI:        https://wpanchorbay.com/plugins/notifybay
 * Description:       A modern WordPress plugin boilerplate with React/TypeScript admin UI, REST API, and modular PHP architecture.
 * Requires at least: 5.6
 * Requires PHP:      7.0
 * Version:           1.2.0
 * Stable tag:        1.2.0
 * Author:            WPAnchorBay
 * Author URI:        https://wpanchorbay.com
 * License:           GPLv2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       notifybay-waitlist-and-stock-alert-woo
 * Domain Path:       /languages
 *
 * @package NotifyBay
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

define( 'NOTIFYBAY_PATH', plugin_dir_path( __FILE__ ) );
define( 'NOTIFYBAY_DIR', plugin_dir_path( __FILE__ ) );
define( 'NOTIFYBAY_URL', plugin_dir_url( __FILE__ ) );
define( 'NOTIFYBAY_VERSION', '1.2.0' );
define( 'NOTIFYBAY_PLUGIN_NAME', 'notifybay' );
define( 'NOTIFYBAY_TEXT_DOMAIN', 'notifybay' );
define( 'NOTIFYBAY_OPTION_NAME', 'notifybay' );
// The wordpress.org distribution slug (== plugin folder + text domain). This is
// deliberately distinct from the `notifybay` code prefix used by hooks, options,
// constants, the REST namespace and script handles above.
define( 'NOTIFYBAY_SLUG', 'notifybay-waitlist-and-stock-alert-woo' );
define( 'NOTIFYBAY_REMOTE_URL', 'https://wpanchorbay.com/wp-json/' );

// Composer autoloader.
if ( file_exists( NOTIFYBAY_PATH . 'vendor/autoload.php' ) ) {
	require_once NOTIFYBAY_PATH . 'vendor/autoload.php';
}




require_once NOTIFYBAY_PATH . 'app/functions.php';



/**
 * Begins execution of the plugin.
 *
 * @since 1.0.0
 */
function notifybay_run() {
	\NotifyBay\Core\Plugin::get_instance();
}
notifybay_run();

/**
 * Activate the plugin.
 *
 * @since 1.0.0
 */
function notifybay_activate() {
	require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	\NotifyBay\Core\Activator::activate();
}

/**
 * Deactivate the plugin.
 *
 * @since 1.0.0
 */
function notifybay_deactivate() {
	\NotifyBay\Core\Deactivator::deactivate();
}

register_activation_hook( __FILE__, 'notifybay_activate' );
register_deactivation_hook( __FILE__, 'notifybay_deactivate' );
