<?php
/**
 * Plugin Name:       NotifyBay - Waitlist and Stock Alert for WooCommerce
 * Plugin URI:        https://wpanchorbay.com/plugins/notifybay-waitlist-and-stock-alert-for-woocommerce/
 * Description:       Adds Waitlist (back-in-stock) alerts to WooCommerce so store owners can capture leads on out-of-stock products and automatically notify waitlisted customers when items are restocked.
 * Requires at least: 6.8
 * Requires PHP:      7.4
 * Requires Plugins:  woocommerce
 * Version:           1.0.2
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
define( 'NOTIFYBAY_VERSION', '1.0.2' );
// Database schema version. Bump whenever the custom table structure changes so
// the schema is re-applied on plugin UPDATE (activation hooks do not fire on an
// in-place update). '2' adds the leads.guest_token column.
define( 'NOTIFYBAY_DB_VERSION', '2' );
define( 'NOTIFYBAY_PLUGIN_NAME', 'notifybay' );
define( 'NOTIFYBAY_TEXT_DOMAIN', 'notifybay' );
define( 'NOTIFYBAY_OPTION_NAME', 'notifybay' );
define( 'NOTIFYBAY_SLUG', 'notifybay-waitlist-and-stock-alert-woo' );
define( 'NOTIFYBAY_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

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
 * Register the deactivation-feedback modal and the review notice.
 *
 * Deliberately registered at file scope rather than inside `notifybay_run()`:
 * that function returns early when WooCommerce is absent, and the feedback
 * modal must still work on exactly those sites — a missing WooCommerce is one
 * of the likelier reasons somebody is deactivating in the first place.
 *
 * Hooked to `init` (not file scope directly) so the translated strings below
 * are not built before translations are available.
 *
 * @since 1.0.0
 */
function notifybay_register_deactivation_feedback() {
	if ( ! is_admin() || ! class_exists( '\WPAB\DeactivationFeedback\DeactivationFeedback' ) ) {
		return;
	}

	new \WPAB\DeactivationFeedback\DeactivationFeedback(
		array(
			'plugin_file'           => NOTIFYBAY_PLUGIN_BASENAME,
			'plugin_slug'           => NOTIFYBAY_SLUG,
			'plugin_name'           => __( 'NotifyBay', 'notifybay-waitlist-and-stock-alert-woo' ),
			'remote_endpoint'       => 'https://dfs.wpanchorbay.com/wp-json/wpab/v1/feedback',

			'modal_title'           => __( 'Quick question before you go', 'notifybay-waitlist-and-stock-alert-woo' ),
			'modal_subtitle'        => __( "If you have a moment, let us know why you're deactivating so we can improve NotifyBay.", 'notifybay-waitlist-and-stock-alert-woo' ),
			'cancel_label'          => __( 'Cancel', 'notifybay-waitlist-and-stock-alert-woo' ),
			'skip_label'            => __( 'Skip & Deactivate', 'notifybay-waitlist-and-stock-alert-woo' ),
			'submit_label'          => __( 'Submit & Deactivate', 'notifybay-waitlist-and-stock-alert-woo' ),

			// Must stay truthful to the payload built in Ajax::handle().
			'disclosure_label'      => __( 'What information is sent?', 'notifybay-waitlist-and-stock-alert-woo' ),
			'disclosure_text'       => __( 'Pressing "Submit & Deactivate" sends the reason you select and any note you type, along with your site address, the email address of your account, and your WordPress version, to wpanchorbay.com. Pressing "Skip & Deactivate" sends nothing.', 'notifybay-waitlist-and-stock-alert-woo' ),

			'reasons'               => array(
				array(
					'id'          => 'couldnt_get_working',
					'label'       => __( "I couldn't get it working", 'notifybay-waitlist-and-stock-alert-woo' ),
					'has_input'   => true,
					'placeholder' => __( 'What were you trying to configure?', 'notifybay-waitlist-and-stock-alert-woo' ),
				),
				array(
					'id'          => 'missing_feature',
					'label'       => __( "It's missing a feature I need", 'notifybay-waitlist-and-stock-alert-woo' ),
					'has_input'   => true,
					'placeholder' => __( 'What feature?', 'notifybay-waitlist-and-stock-alert-woo' ),
				),
				array(
					'id'          => 'conflict',
					'label'       => __( 'It conflicted with my theme or another plugin', 'notifybay-waitlist-and-stock-alert-woo' ),
					'has_input'   => true,
					'placeholder' => __( 'Which theme/plugin?', 'notifybay-waitlist-and-stock-alert-woo' ),
				),
				array(
					'id'          => 'slowed_site',
					'label'       => __( 'It slowed down my site', 'notifybay-waitlist-and-stock-alert-woo' ),
					'has_input'   => true,
					'placeholder' => __( 'Please describe what felt slow', 'notifybay-waitlist-and-stock-alert-woo' ),
				),
				array(
					'id'          => 'emails_not_delivered',
					'label'       => __( "Restock emails weren't reaching my customers", 'notifybay-waitlist-and-stock-alert-woo' ),
					'has_input'   => true,
					'placeholder' => __( 'What did you see happen?', 'notifybay-waitlist-and-stock-alert-woo' ),
				),
				array(
					'id'          => 'better_alternative',
					'label'       => __( 'I found a better alternative', 'notifybay-waitlist-and-stock-alert-woo' ),
					'has_input'   => true,
					'placeholder' => __( 'Which plugin?', 'notifybay-waitlist-and-stock-alert-woo' ),
				),
				array(
					'id'          => 'too_complicated',
					'label'       => __( "It's too complicated to set up", 'notifybay-waitlist-and-stock-alert-woo' ),
					'has_input'   => true,
					'placeholder' => __( 'What part was confusing?', 'notifybay-waitlist-and-stock-alert-woo' ),
				),
				array(
					'id'        => 'no_longer_needed',
					'label'     => __( 'I no longer need waitlists on my store', 'notifybay-waitlist-and-stock-alert-woo' ),
					'has_input' => false,
				),
				array(
					'id'        => 'temporary',
					'label'     => __( "It's temporary — I'm troubleshooting/debugging", 'notifybay-waitlist-and-stock-alert-woo' ),
					'has_input' => false,
				),
				array(
					'id'          => 'other',
					'label'       => __( 'Other', 'notifybay-waitlist-and-stock-alert-woo' ),
					'has_input'   => true,
					'placeholder' => __( 'Please share the reason', 'notifybay-waitlist-and-stock-alert-woo' ),
				),
			),

			'review_notice_enabled' => true,
			'review_delay_days'     => 3,
			'review_snooze_days'    => 7,
			'review_url'            => 'https://wordpress.org/support/plugin/notifybay-waitlist-and-stock-alert-woo/reviews/#new-post',
			'support_url'           => 'https://wordpress.org/support/plugin/notifybay-waitlist-and-stock-alert-woo/',
			'review_notice_title'   => __( 'Enjoying NotifyBay?', 'notifybay-waitlist-and-stock-alert-woo' ),
			'review_notice_message' => __( "You've been using NotifyBay for a few days now. If it's been helpful, would you mind leaving a quick review? It really helps!", 'notifybay-waitlist-and-stock-alert-woo' ),
		)
	);
}
add_action( 'init', 'notifybay_register_deactivation_feedback' );

/**
 * Activate the plugin.
 *
 * @since 1.0.0
 */
function notifybay_activate() {
	\NotifyBay\Core\Activator::activate();

	// Timestamp the first activation so the review notice knows when to appear.
	// Only set once — re-activating must not restart the countdown. The option
	// key must match the plugin_slug passed to DeactivationFeedback exactly.
	if ( ! get_option( 'wpab_activated_at_' . NOTIFYBAY_SLUG ) ) {
		update_option( 'wpab_activated_at_' . NOTIFYBAY_SLUG, time(), false );
	}
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
