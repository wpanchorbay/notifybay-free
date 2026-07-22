<?php
/**
 * Frontend Product Page UI.
 *
 * Handles the injection of Waitlist forms, Wishlist buttons, and FOMO banners.
 * Uses separate render methods for single product pages vs archive loops.
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
		$loader->add_action( 'woocommerce_before_add_to_cart_form', $this, 'render_fomo_banner', 10 );
		$loader->add_action( 'woocommerce_before_add_to_cart_button', $this, 'render_fomo_banner', 10 );
		$loader->add_action( 'woocommerce_after_add_to_cart_button', $this, 'render_single_product_ui', 31 );
		$loader->add_action( 'woocommerce_single_product_summary', $this, 'render_single_product_ui', 32 );

		// === Archive / Shop Loop Hooks ===
		$loader->add_action( 'woocommerce_after_shop_loop_item', $this, 'render_archive_loop_ui', 11 );

		// Hide native "Read More" on archives for out-of-stock non-variable products
		$loader->add_filter( 'woocommerce_loop_add_to_cart_link', $this, 'maybe_hide_archive_add_to_cart', 10, 3 );

		// === Global Hooks ===
		$loader->add_action( 'wp_enqueue_scripts', $this, 'enqueue_assets' );
		$loader->add_filter( 'wp_nav_menu_items', $this, 'add_wishlist_to_menu', 10, 2 );
	}

	/**
	 * Add Wishlist link to primary navigation menu.
	 *
	 * @param string $items The menu items.
	 * @param object $args  Menu arguments.
	 * @return string
	 *
	 * @phpcs:disable Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
	 */
	public function add_wishlist_to_menu( $items, $args ) {
		$settings = \NotifyBay\Core\Settings::get_instance();
		if ( ! $settings->get_settings( 'general_wishlistEnabled', false ) ) {
			return $items;
		}

		static $count_cache = null;

		$count = 0;
		if ( is_user_logged_in() ) {
			if ( $count_cache === null ) {
				global $wpdb;
				$user_email  = wp_get_current_user()->user_email;
				$table       = $wpdb->prefix . 'notifybay_leads';
				$count_cache = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$table} WHERE user_email = %s AND type = 'wishlist' AND status = 'active'", $user_email)); // phpcs:ignore
			}
			$count = $count_cache;
		}

		$wishlist_url = wc_get_account_endpoint_url( 'notifybay-wishlist' );
		$icon         = '<span class="dashicons dashicons-heart" style="vertical-align: middle; margin-right: 4px; color: #ff4d4f;"></span>';

		$display = $count > 0 ? 'inline-block' : 'none';
		$badge   = '<span class="notifybay-menu-badge" style="display:' . $display . '; background: #ff4d4f; color: #fff; border-radius: 50%; padding: 2px 6px; font-size: 10px; margin-left: 4px; vertical-align: top;">' . $count . '</span>';

		$wishlist_item  = '<li class="menu-item notifybay-menu-wishlist">';
		$wishlist_item .= '<a href="' . esc_url( $wishlist_url ) . '">' . $icon . __( 'Wishlist', 'notifybay-waitlist-and-stock-alert-woo' ) . $badge . '</a>';
		$wishlist_item .= '</li>';

		return $items . $wishlist_item;
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
	public function maybe_hide_archive_add_to_cart( $html, $product, $args ) {
		if ( ! $product->is_in_stock() && ! $product->is_type( 'variable' ) ) {
			return '';
		}
		return $html;
	}

	/**
	 * Render UI for archive/shop loop items.
	 *
	 * - Non-variable out of stock: "Notify Me" waitlist form (PHP)
	 * - Non-variable in stock: Wishlist button if setting enabled (PHP)
	 * - Variable products: Do nothing (WooCommerce shows "Select Options")
	 * - No FOMO on archives
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

		if ( ! empty( $overrides['disable_waitlist'] ) && ! empty( $overrides['disable_wishlist'] ) ) {
			return;
		}

		$is_out_of_stock = ! $product->is_in_stock();

		// Use batch cache instead of per-product queries
		$subs_cache   = $this->get_archive_subs_cache();
		$product_subs = $subs_cache[ $product_id ] ?? array();

		if ( $is_out_of_stock && empty( $overrides['disable_waitlist'] ) ) {
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
		} elseif ( ! $is_out_of_stock && empty( $overrides['disable_wishlist'] ) ) {
			// In stock: Show wishlist button only for logged-in users and if archive setting enabled
			if ( ! is_user_logged_in() ) {
				return;
			}

			$show_on_archives = $settings->get_settings( 'appearance_showWishlistOnArchives', false );

			if ( $show_on_archives ) {
				$is_subscribed = ! empty( $product_subs[0]['wishlist'] );

				echo '<div class="notifybay-frontend-root" data-context="archive" data-product-id="' . esc_attr( $product_id ) . '" data-product-type="' . esc_attr( $product->get_type() ) . '" style="margin:0; ">';
				$this->render_wishlist_button( $product, $is_subscribed );
				echo '</div>';
			}
		}
	}

	// =========================================================================
	// Single Product Page
	// =========================================================================

	/**
	 * Render FOMO banner for single product pages.
	 * Placed at woocommerce_before_add_to_cart_form.
	 *
	 * - Non-variable: Renders the FOMO count directly in PHP HTML.
	 * - Variable: Renders a container with data-fomo-total for JS to manage per-variant switching.
	 */
	public function render_fomo_banner() {
		global $product;
		if ( ! $product || ! is_product() ) {
			return;
		}

		$product_id = $product->get_id();
		$settings   = Settings::get_instance();

		if ( ! $settings->get_settings( 'appearance_fomoEnabled', false ) ) {
			return;
		}

		$threshold = (int) $settings->get_settings( 'appearance_fomoMinThreshold', 1 );
		$template  = $settings->get_settings( 'appearance_fomoTemplate', '🔥 {count} people are waiting for this' );

		$current_hook = current_action();

		if ( $product->is_type( 'variable' ) ) {
			// Variable product: Should only render at 'woocommerce_before_add_to_cart_button'
			if ( 'woocommerce_before_add_to_cart_button' !== $current_hook ) {
				return;
			}

			// Variable: Get total across all variants. JS will update per-variant on selection.
			$total_count      = Lead::get_total_lead_count( $product_id );
			$variation_counts = Lead::get_all_variation_fomo_counts( $product_id );

			echo '<div class="notifybay-fomo-container" data-product-id="' . esc_attr( $product_id ) . '" data-fomo-total="' . esc_attr( $total_count ) . '" data-fomo-threshold="' . esc_attr( $threshold ) . '" data-variation-fomo=\'' . esc_attr( wp_json_encode( $variation_counts ) ) . '\'>';
			if ( $total_count >= $threshold ) {
				$msg = str_replace( '{count}', $total_count, $template );
				echo '<div class="notifybay-fomo">' . esc_html( $msg ) . '</div>';
			}
			echo '</div>';
		} else {
			// Simple product: Should only render at 'woocommerce_before_add_to_cart_form'
			if ( 'woocommerce_before_add_to_cart_form' !== $current_hook ) {
				return;
			}

			// Non-variable: Render directly in PHP, no JS needed.
			$fomo_count = Lead::get_lead_count( $product_id, 0 );

			echo '<div class="notifybay-fomo-container" data-product-id="' . esc_attr( $product_id ) . '" data-fomo-total="' . esc_attr( $fomo_count ) . '" data-fomo-threshold="' . esc_attr( $threshold ) . '">';
			if ( $fomo_count >= $threshold ) {
				$msg = str_replace( '{count}', $fomo_count, $template );
				echo '<div class="notifybay-fomo">' . esc_html( $msg ) . '</div>';
			}
			echo '</div>';
		}
	}

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

		if ( ! empty( $overrides['disable_waitlist'] ) && ! empty( $overrides['disable_wishlist'] ) ) {
			return;
		}

		// === Variable Products ===
		// Render a lightweight container. JS handles variation switching.
		if ( $product->is_type( 'variable' ) ) {
			$is_subscribed_wishlist = false;
			$subscription_map       = array();

			if ( is_user_logged_in() ) {
				$user_email             = wp_get_current_user()->user_email;
				$subscription_map       = Lead::get_user_subscriptions_for_product( $user_email, $product_id );
				$is_subscribed_wishlist = ! empty( $subscription_map[0]['wishlist'] );
			}

			echo '<div class="notifybay-frontend-root" 
					   data-context="single"
					   data-product-id="' . esc_attr( $product_id ) . '" 
					   data-product-type="variable"
					   data-subscribed-wishlist="' . esc_attr( $is_subscribed_wishlist ? '1' : '0' ) . '"
					   data-variation-subscriptions=\'' . esc_attr( wp_json_encode( $subscription_map ) ) . '\'
					   style="margin:0; ">';

			// Render wishlist button (disabled until variation selected). JS enables it.
			if ( empty( $overrides['disable_wishlist'] ) ) {
				$this->render_wishlist_button( $product, $is_subscribed_wishlist );
			}

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
		$is_subscribed_wishlist = false;

		if ( is_user_logged_in() ) {
			$user_email             = wp_get_current_user()->user_email;
			$subs                   = Lead::get_user_subscriptions_for_product( $user_email, $product_id );
			$is_subscribed_waitlist = ! empty( $subs[0]['waitlist'] );
			$is_subscribed_wishlist = ! empty( $subs[0]['wishlist'] );
		}

		echo '<div class="notifybay-frontend-root" 
				   data-context="single"
				   data-product-id="' . esc_attr( $product_id ) . '" 
				   data-product-type="' . esc_attr( $product->get_type() ) . '"
				   style="margin:0; ">';

		if ( $show_waitlist && empty( $overrides['disable_waitlist'] ) ) {
			$this->render_waitlist_form( $product, $is_subscribed_waitlist );
		} elseif ( empty( $overrides['disable_wishlist'] ) ) {
			$this->render_wishlist_button( $product, $is_subscribed_wishlist );
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

	/**
	 * Render the Wishlist Button.
	 *
	 * @param \WC_Product $product       The product object.
	 * @param bool        $is_subscribed Whether the user is already subscribed.
	 */
	private function render_wishlist_button( $product, $is_subscribed = false ) {
		$settings = Settings::get_instance();
		if ( ! $settings->get_settings( 'general_wishlistEnabled', false ) ) {
			return;
		}

		$btn_text  = $settings->get_settings( 'appearance_wishlistButtonText', 'Add to Wishlist' );
		$css_class = $settings->get_settings( 'appearance_wishlistButtonClass', '' );

		TemplateRenderer::render(
			'frontend/wishlist-button',
			array(
				'btn_text'      => $btn_text,
				'css_class'     => $css_class,
				'is_subscribed' => $is_subscribed,
				'is_variable'   => $product->is_type( 'variable' ),
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
					'fomo_enabled'   => is_product() && $settings->get_settings( 'appearance_fomoEnabled', false ),
					'fomo_template'  => $settings->get_settings( 'appearance_fomoTemplate', '🔥 {count} people are waiting for this' ),
					'fomo_threshold' => $settings->get_settings( 'appearance_fomoMinThreshold', 1 ),
					'backorder_mode' => $settings->get_settings( 'general_backorderWaitlist', '0' ),
					'waitlist_btn'   => $settings->get_settings( 'appearance_waitlistButtonText', 'Notify Me' ),
					'waitlist_class' => $settings->get_settings( 'appearance_waitlistButtonClass', '' ),
					'wishlist_btn'   => $settings->get_settings( 'appearance_wishlistButtonText', 'Add to Wishlist' ),
					'wishlist_class' => $settings->get_settings( 'appearance_wishlistButtonClass', '' ),
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
