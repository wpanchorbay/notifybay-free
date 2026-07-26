<?php
/**
 * Frontend Product Page UI.
 *
 * Handles the injection of Waitlist ("Notify Me") forms on single product
 * pages and archive loops.
 *
 * @package    NotifyBay
 * @subpackage Frontend
 * @since      1.0.0
 * @author     WPAnchorBay <sankarsan@wpanchorbay.com>
 */

namespace NotifyBay\Frontend;

use NotifyBay\Core\Plugin;
use NotifyBay\Core\Settings;
use NotifyBay\Helper\TemplateRenderer;
use NotifyBay\Models\Lead;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class ProductPage
 */
class ProductPage {


	/**
	 * The single instance of the class.
	 *
	 * @var ProductPage
	 */
	private static $instance = null;

	/**
	 * Cached settings instance.
	 *
	 * @var Settings
	 */
	private $settings = null;

	/**
	 * Get the instance.
	 *
	 * @return ProductPage
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
		$this->settings = Settings::get_instance();
		$loader         = $plugin->get_loader();

		// === Single Product Page Hooks ===
		$loader->add_action( 'woocommerce_after_add_to_cart_button', $this, 'render_single_product_ui', 31 );
		$loader->add_action( 'woocommerce_single_product_summary', $this, 'render_single_product_ui', 32 );

		// === Archive / Shop Loop Hooks ===
		$loader->add_action( 'woocommerce_after_shop_loop_item', $this, 'render_archive_loop_ui', 11 );

		// Hide native "Read More" on archives for out-of-stock non-variable products
		$loader->add_filter( 'woocommerce_loop_add_to_cart_link', $this, 'maybe_hide_archive_add_to_cart', 10, 3 );

		// === Global Hooks ===
		$loader->add_action( 'wp_enqueue_scripts', $this, 'enqueue_assets' );
	}

	// =========================================================================
	// Archive / Shop Loop
	// =========================================================================

	/**
	 * Hide the native WooCommerce add to cart link on archives for out-of-stock non-variable products.
	 * Variable products keep their "Select Options" link.
	 *
	 * @param string      $html    The button HTML.
	 * @param \WC_Product $product The product object.
	 * @param array       $args    Additional arguments.
	 * @return string
	 */
	public function maybe_hide_archive_add_to_cart( $html, $product, $args ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- $args is required by the woocommerce_loop_add_to_cart_link filter signature.
		if ( ! $product->is_in_stock() && ! $product->is_type( 'variable' ) ) {
			return '';
		}
		return $html;
	}

	/**
	 * Render UI for archive/shop loop items.
	 *
	 * - Non-variable out of stock: "Notify Me" waitlist form (PHP)
	 * - Non-variable in stock: nothing
	 * - Variable products: Do nothing (WooCommerce shows "Select Options")
	 */
	/**
	 * Pre-fetched archive subscription cache.
	 * Populated once per request, used by render_archive_loop_ui().
	 *
	 * @var array|null
	 */
	private $archive_subs_cache = null;

	/**
	 * Get the batch subscription cache for all products in the current archive query.
	 * Returns an empty array for guests (guest state is handled by JS).
	 *
	 * @return array Map of [product_id => [variation_id => [type => true]]]
	 */
	private function get_archive_subs_cache() {
		if ( $this->archive_subs_cache !== null ) {
			return $this->archive_subs_cache;
		}

		$this->archive_subs_cache = array();

		if ( ! is_user_logged_in() ) {
			return $this->archive_subs_cache;
		}

		global $wp_query;
		if ( empty( $wp_query->posts ) ) {
			return $this->archive_subs_cache;
		}

		$product_ids              = wp_list_pluck( $wp_query->posts, 'ID' );
		$user_email               = wp_get_current_user()->user_email;
		$this->archive_subs_cache = Lead::get_user_subscriptions_for_products( $user_email, $product_ids );

		return $this->archive_subs_cache;
	}

	/**
	 * Determine whether the waitlist should be shown based on backorder mode.
	 *
	 * @param string $backorder_mode     The backorder mode setting ('0', '1', or '2').
	 * @param bool   $is_out_of_stock    Whether the product is out of stock.
	 * @param bool   $backorders_allowed Whether backorders are allowed.
	 * @return bool
	 */
	public static function should_show_waitlist( $backorder_mode, $is_out_of_stock, $backorders_allowed ) {
		if ( ! $is_out_of_stock ) {
			return false;
		}
		// Mode '1': Always show waitlist when out of stock
		if ( '1' === $backorder_mode ) {
			return true;
		}
		// Modes '0' and '2': Show waitlist only when backorders are NOT allowed
		return ! $backorders_allowed;
	}

	/**
	 * Render the UI elements on the WooCommerce shop archive loop.
	 *
	 * @return void
	 */
	public function render_archive_loop_ui() {
		global $product;
		if ( ! $product ) {
			return;
		}

		// Variable products: WooCommerce handles "Select Options" natively.
		if ( $product->is_type( 'variable' ) ) {
			return;
		}

		$product_id = $product->get_id();
		$settings   = Settings::get_instance();

		// Check per-product overrides
		$overrides = get_post_meta( $product_id, '_notifybay_overrides', true );
		if ( ! is_array( $overrides ) ) {
			$overrides = array();
		}

		if ( ! empty( $overrides['disable_waitlist'] ) ) {
			return;
		}

		$is_out_of_stock = ! $product->is_in_stock();
		if ( ! $is_out_of_stock ) {
			return;
		}

		// Use batch cache instead of per-product queries
		$subs_cache   = $this->get_archive_subs_cache();
		$product_subs = $subs_cache[ $product_id ] ?? array();

		// Out of stock: Show "Notify Me" waitlist form
		$backorder_mode = ( ( $overrides['backorder_mode'] ?? 'default' ) !== 'default' )
			? $overrides['backorder_mode']
			: $settings->get_settings( 'general_backorderWaitlist', '0' );

		if ( self::should_show_waitlist( $backorder_mode, $is_out_of_stock, $product->backorders_allowed() ) ) {
			$is_subscribed = ! empty( $product_subs[0]['waitlist'] );

			echo '<div class="notifybay-frontend-root" data-context="archive" data-product-id="' . esc_attr( $product_id ) . '" data-product-type="' . esc_attr( $product->get_type() ) . '" style="margin:0; ">';
			$this->render_waitlist_form( $product, $is_subscribed );
			echo '</div>';
		}
	}

	// =========================================================================
	// Single Product Page
	// =========================================================================

	/**
	 * Render UI for single product pages.
	 *
	 * Hook strategy:
	 * - woocommerce_after_add_to_cart_button (priority 31): Fires for variable products and in-stock non-variable.
	 * - woocommerce_single_product_summary (priority 32): FALLBACK for out-of-stock non-variable products
	 *   (WooCommerce hides the add-to-cart form entirely, so hook #1 never fires).
	 */
	public function render_single_product_ui() {
		global $product;
		if ( ! $product || ! is_product() ) {
			return;
		}

		$current_hook = current_action();

		// The summary hook is a fallback ONLY for out-of-stock non-variable products.
		if ( 'woocommerce_single_product_summary' === $current_hook ) {
			if ( $product->is_type( 'variable' ) || $product->is_in_stock() ) {
				return;
			}
		}

		// Prevent double rendering
		static $rendered = array();
		$product_id      = $product->get_id();
		if ( isset( $rendered[ $product_id ] ) ) {
			return;
		}
		$rendered[ $product_id ] = true;

		$settings = Settings::get_instance();

		// Check per-product overrides
		$overrides = get_post_meta( $product_id, '_notifybay_overrides', true );
		if ( ! is_array( $overrides ) ) {
			$overrides = array();
		}

		if ( ! empty( $overrides['disable_waitlist'] ) ) {
			return;
		}

		// === Variable Products ===
		// Render a lightweight container carrying the neutral subscription map.
		// Free's waitlist JS injects the "Notify Me" form for out-of-stock
		// variations on selection; an add-on's script reads the same map.
		if ( $product->is_type( 'variable' ) ) {
			$subscription_map = array();

			if ( is_user_logged_in() ) {
				$user_email       = wp_get_current_user()->user_email;
				$subscription_map = Lead::get_user_subscriptions_for_product( $user_email, $product_id );
			}

			echo '<div class="notifybay-frontend-root"
					   data-context="single"
					   data-product-id="' . esc_attr( $product_id ) . '"
					   data-product-type="variable"
					   data-variation-subscriptions=\'' . esc_attr( wp_json_encode( $subscription_map ) ) . '\'
					   style="margin:0; ">';
			echo '</div>';
			return;
		}

		// === Non-Variable Products ===
		// Render everything in PHP — no JS needed for UI.
		$backorder_mode = ( ( $overrides['backorder_mode'] ?? 'default' ) !== 'default' )
			? $overrides['backorder_mode']
			: $settings->get_settings( 'general_backorderWaitlist', '0' );

		$is_out_of_stock = ! $product->is_in_stock();
		$show_waitlist   = self::should_show_waitlist( $backorder_mode, $is_out_of_stock, $product->backorders_allowed() );

		$is_subscribed_waitlist = false;

		if ( is_user_logged_in() ) {
			$user_email             = wp_get_current_user()->user_email;
			$subs                   = Lead::get_user_subscriptions_for_product( $user_email, $product_id );
			$is_subscribed_waitlist = ! empty( $subs[0]['waitlist'] );
		}

		echo '<div class="notifybay-frontend-root"
				   data-context="single"
				   data-product-id="' . esc_attr( $product_id ) . '"
				   data-product-type="' . esc_attr( $product->get_type() ) . '"
				   style="margin:0; ">';

		if ( $show_waitlist && empty( $overrides['disable_waitlist'] ) ) {
			$this->render_waitlist_form( $product, $is_subscribed_waitlist );
		}

		echo '</div>';
	}

	// =========================================================================
	// Shared Template Renderers
	// =========================================================================

	/**
	 * Render the Waitlist Form.
	 *
	 * @param \WC_Product $product       The product object.
	 * @param bool        $is_subscribed Whether the user is already subscribed.
	 */
	private function render_waitlist_form( $product, $is_subscribed = false ) {
		$settings  = Settings::get_instance();
		$btn_text  = $settings->get_settings( 'appearance_waitlistButtonText', 'Notify Me' );
		$css_class = $settings->get_settings( 'appearance_waitlistButtonClass', '' );

		TemplateRenderer::render(
			'frontend/waitlist-form',
			array(
				'product'       => $product,
				'btn_text'      => $btn_text,
				'css_class'     => $css_class,
				'settings'      => $settings,
				'is_subscribed' => $is_subscribed,
			)
		);
	}

	// =========================================================================
	// Assets
	// =========================================================================

	/**
	 * Enqueue assets.
	 */
	public function enqueue_assets() {
		wp_register_script( 'notifybay-frontend', \NOTIFYBAY_URL . 'assets/js/frontend.js', array( 'jquery' ), \NOTIFYBAY_VERSION, true );

		$settings = Settings::get_instance();

		wp_localize_script(
			'notifybay-frontend',
			'notifybay_vars',
			array(
				'ajax_url' => admin_url( 'admin-ajax.php' ),
				'rest_url' => get_rest_url( null, 'notifybay/v1' ),
				'nonce'    => wp_create_nonce( 'wp_rest' ),
				'user'     => array(
					'is_logged_in' => is_user_logged_in(),
					'email'        => is_user_logged_in() ? wp_get_current_user()->user_email : '',
				),
				'settings' => array(
					'backorder_mode' => $settings->get_settings( 'general_backorderWaitlist', '0' ),
					'waitlist_btn'   => $settings->get_settings( 'appearance_waitlistButtonText', 'Notify Me' ),
					'waitlist_class' => $settings->get_settings( 'appearance_waitlistButtonClass', '' ),
					'expiry_enabled' => $settings->get_settings( 'appearance_waitlistExpiryEnabled', false ),
				),
			)
		);

		if ( is_product() || is_shop() || is_product_category() || is_product_tag() || is_account_page() ) {
			wp_enqueue_style( 'dashicons' );
			wp_enqueue_script( 'notifybay-frontend' );
		}
	}
}
