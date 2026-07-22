<?php
/**
 * MyAccount Integration.
 *
 * Registers "Waitlist" and "Wishlist" tabs in WooCommerce My Account.
 *
 * @package    NotifyBay
 * @subpackage Frontend
 * @since      1.0.0
 * @author     WPAnchorBay <sankarsan@wpanchorbay.com>
 */

namespace NotifyBay\Frontend;

use NotifyBay\Models\Lead;
use NotifyBay\Helper\TemplateRenderer;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class MyAccount
 */
class MyAccount {

	/**
	 * The single instance of the class.
	 *
	 * @var MyAccount
	 */
	private static $instance = null;

	/**
	 * Gets an instance of this object.
	 *
	 * @return MyAccount
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Run the component.
	 *
	 * @param \NotifyBay\Core\Plugin $plugin The plugin instance.
	 */
	public function run( $plugin ) {
		$loader = $plugin->get_loader();

		// Register endpoints
		$loader->add_action( 'init', $this, 'add_endpoints' );
		$loader->add_filter( 'query_vars', $this, 'add_query_vars', 0 );
		$loader->add_filter( 'woocommerce_account_menu_items', $this, 'add_menu_items' );

		// Content callbacks. Registration itself is unconditional — the wishlist
		// rewrite endpoint/query var are only added when enabled (see
		// add_endpoints()/add_query_vars(), both deferred to later hooks), so this
		// callback simply won't fire when disabled. Settings is deliberately not
		// read here in run(): that executes at file-load time, before an add-on's
		// plugins_loaded hook can register its notifybay_options_defaults filter,
		// which would permanently cache Settings without the add-on's keys for the
		// rest of the request.
		$loader->add_action( 'woocommerce_account_notifybay-waitlist_endpoint', $this, 'render_waitlist_content' );
		$loader->add_action( 'woocommerce_account_notifybay-wishlist_endpoint', $this, 'render_wishlist_content' );
	}

	/**
	 * Register custom endpoints.
	 */
	public function add_endpoints() {
		add_rewrite_endpoint( 'notifybay-waitlist', EP_PAGES );
		$settings = \NotifyBay\Core\Settings::get_instance();
		if ( $settings->get_settings( 'general_wishlistEnabled', false ) ) {
			add_rewrite_endpoint( 'notifybay-wishlist', EP_PAGES );
		}
	}

	/**
	 * Add query vars.
	 *
	 * @param array $vars The query vars.
	 * @return array
	 */
	public function add_query_vars( $vars ) {
		$vars[]   = 'notifybay-waitlist';
		$settings = \NotifyBay\Core\Settings::get_instance();
		if ( $settings->get_settings( 'general_wishlistEnabled', false ) ) {
			$vars[] = 'notifybay-wishlist';
		}

		return $vars;
	}

	/**
	 * Add menu items to My Account.
	 *
	 * @param array $items The menu items.
	 * @return array
	 */
	public function add_menu_items( $items ) {
		// Insert before Logout if possible
		$logout = null;
		if ( isset( $items['customer-logout'] ) ) {
			$logout = $items['customer-logout'];
			unset( $items['customer-logout'] );
		}

		$items['notifybay-waitlist'] = __( 'Waitlist', 'notifybay-waitlist-and-stock-alert-woo' );
		$settings                    = \NotifyBay\Core\Settings::get_instance();
		if ( $settings->get_settings( 'general_wishlistEnabled', false ) ) {
			$items['notifybay-wishlist'] = __( 'Wishlist', 'notifybay-waitlist-and-stock-alert-woo' );
		}

		if ( $logout ) {
			$items['customer-logout'] = $logout;
		}

		return $items;
	}

	/**
	 * Render the Waitlist tab content.
	 */
	public function render_waitlist_content() {
		$user_email = wp_get_current_user()->user_email;
		$leads      = Lead::get_user_leads_by_type( $user_email, 'waitlist' );

		TemplateRenderer::render(
			'frontend/waitlist',
			array(
				'leads' => $leads,
			)
		);
	}

	/**
	 * Render the Wishlist tab content.
	 */
	public function render_wishlist_content() {
		$user_email = wp_get_current_user()->user_email;
		$leads      = Lead::get_user_leads_by_type( $user_email, 'wishlist' );

		TemplateRenderer::render(
			'frontend/wishlist',
			array(
				'leads' => $leads,
			)
		);
	}
}
