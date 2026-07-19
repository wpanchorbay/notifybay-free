<?php
/**
 * Frontend Shortcodes.
 *
 * Provides [notifybay_waitlist] and [notifybay_wishlist] shortcodes.
 *
 * @package    NotifyBay
 * @subpackage Frontend
 * @since      1.0.0
 * @author     WPAnchorBay <sankarsan@gmail.com>
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
 * Class Shortcodes
 */
class Shortcodes {

	/**
	 * The single instance of the class.
	 *
	 * @var Shortcodes
	 */
	private static $instance = null;

	/**
	 * Get the instance.
	 *
	 * @return Shortcodes
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
		$loader = $plugin->get_loader();
		$loader->add_action( 'init', $this, 'register_shortcodes' );
	}

	/**
	 * Register shortcodes.
	 *
	 * Note: [notifybay_wishlist] is a premium (NotifyBay Pro) shortcode and is
	 * registered by Pro itself, not here, so Free never ships wishlist rendering.
	 */
	public function register_shortcodes() {
		add_shortcode( 'notifybay_waitlist', array( $this, 'render_waitlist_shortcode' ) );
	}

	/**
	 * Render waitlist shortcode.
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string
	 */
	public function render_waitlist_shortcode( $atts ) {
		$atts = shortcode_atts(
			array(
				'id' => 0,
			),
			$atts,
			'notifybay_waitlist'
		);

		$product_id = ! empty( $atts['id'] ) ? (int) $atts['id'] : get_the_ID();
		$product    = wc_get_product( $product_id );

		if ( ! $product ) {
			return '';
		}

		ob_start();
		$this->render_ui_standalone( $product, 'waitlist' );
		return ob_get_clean();
	}

	/**
	 * Render the UI standalone (wrapper for the waitlist shortcode).
	 *
	 * @param \WC_Product $product The product.
	 * @param string      $type    'waitlist' (only type Free renders standalone).
	 */
	private function render_ui_standalone( $product, $type ) {
		wp_enqueue_style( 'dashicons' );
		wp_enqueue_script( 'notifybay-frontend' );

		$product_id             = $product->get_id();
		$is_subscribed_waitlist = false;
		$is_subscribed_wishlist = false;
		$subscription_map       = array();

		if ( is_user_logged_in() ) {
			$user_email             = wp_get_current_user()->user_email;
			$subscription_map       = Lead::get_user_subscriptions_for_product( $user_email, $product_id );
			$is_subscribed_waitlist = ! empty( $subscription_map[0]['waitlist'] );
			$is_subscribed_wishlist = ! empty( $subscription_map[0]['wishlist'] );
		}

		$settings = Settings::get_instance();

		echo '<div class="notifybay-frontend-root"
				   data-product-id="' . esc_attr( $product_id ) . '"
				   data-product-type="' . esc_attr( $product->get_type() ) . '"
				   data-subscribed-waitlist="' . esc_attr( $is_subscribed_waitlist ? '1' : '0' ) . '"
				   data-subscribed-wishlist="' . esc_attr( $is_subscribed_wishlist ? '1' : '0' ) . '"
				   data-variation-subscriptions=\'' . esc_attr( wp_json_encode( $subscription_map ) ) . '\'
				   style="margin:0; display:inline-block;">';

		TemplateRenderer::render(
			'frontend/waitlist-form',
			array(
				'product'       => $product,
				'btn_text'      => $settings->get_settings( 'appearance_waitlistButtonText' ),
				'css_class'     => $settings->get_settings( 'appearance_waitlistButtonClass' ),
				'settings'      => $settings,
				'is_subscribed' => $is_subscribed_waitlist,
			)
		);
		echo '</div>';
	}
}
