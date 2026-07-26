<?php
/**
 * Plugin Name:       NotifyBay - Waitlist and Stock Alert for WooCommerce
 * Plugin URI:        https://wpanchorbay.com/plugins/notifybay-waitlist-and-stock-alert-for-woocommerce
 * Description:       Adds Waitlist (back-in-stock) alerts to WooCommerce so store owners can capture leads on out-of-stock products and automatically notify waitlisted customers when items are restocked.
 * Requires at least: 7.0
 * Requires PHP:      7.0
 * Requires Plugins:  woocommerce
 * Version:           1.0.0
 * Stable tag:        1.0.0
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
define( 'NOTIFYBAY_VERSION', '1.0.0' );
// Database schema version. Bump whenever the custom table structure changes so
// the schema is re-applied on plugin UPDATE (activation hooks do not fire on an
// in-place update). '2' adds the leads.guest_token column.
define( 'NOTIFYBAY_DB_VERSION', '2' );
define( 'NOTIFYBAY_PLUGIN_NAME', 'notifybay' );
define( 'NOTIFYBAY_TEXT_DOMAIN', 'notifybay' );
define( 'NOTIFYBAY_OPTION_NAME', 'notifybay' );
define( 'NOTIFYBAY_SLUG', 'notifybay-waitlist-and-stock-alert-woo' );

// Composer autoloader.
if ( file_exists( NOTIFYBAY_PATH . 'vendor/autoload.php' ) ) {
	require_once NOTIFYBAY_PATH . 'vendor/autoload.php';
}




require_once NOTIFYBAY_PATH . 'app/functions.php';

/**
 * Declare compatibility with WooCommerce High-Performance Order Storage (HPOS).
 *
 * NotifyBay stores its leads in its own table and never reads or writes the
 * WooCommerce orders table directly, so it is fully compatible with HPOS.
 *
 * @since 1.0.0
 */
function notifybay_declare_wc_compatibility() {
	if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
			'custom_order_tables',
			__FILE__,
			true
		);
	}
}
add_action( 'before_woocommerce_init', 'notifybay_declare_wc_compatibility' );

/**
 * Admin notice: WooCommerce missing.
 */
function notifybay_missing_woocommerce_notice() {
	?>
	<div class="notice notice-error">
		<p>
			<strong><?php esc_html_e( 'NotifyBay', 'notifybay-waitlist-and-stock-alert-woo' ); ?></strong>
			<?php esc_html_e( 'requires WooCommerce to be installed and active.', 'notifybay-waitlist-and-stock-alert-woo' ); ?>
		</p>
	</div>
	<?php
}

/**
 * Begins execution of the plugin, once WooCommerce is confirmed present.
 * Runs on `plugins_loaded` priority 20 — a deliberately late priority so
 * WooCommerce's own classes are guaranteed to be loaded before the
 * `class_exists( 'WooCommerce' )` check below runs, regardless of plugin
 * load order.
 *
 * @since 1.0.0
 */
function notifybay_run() {
	if ( ! class_exists( 'WooCommerce' ) ) {
		add_action( 'admin_notices', 'notifybay_missing_woocommerce_notice' );
		return;
	}

	\NotifyBay\Core\Plugin::get_instance();
}
add_action( 'plugins_loaded', 'notifybay_run', 20 );

/**
 * Activate the plugin.
 *
 * @since 1.0.0
 */
function notifybay_activate() {
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
