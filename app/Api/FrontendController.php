<?php
/**
 * Frontend API Controller.
 *
 * Handles public-facing REST API requests for real-time stock checks and signups.
 *
 * @package    NotifyBay
 * @subpackage Api
 * @since      1.0.0
 * @author     WPAnchorBay <sankarsan@wpanchorbay.com>
 */

namespace NotifyBay\Api;

use WP_REST_Request;
use NotifyBay\Models\Lead;
use NotifyBay\Core\Settings;
use NotifyBay\Helper\TemplateRenderer;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class FrontendController
 */
class FrontendController extends ApiController {

	/**
	 * The single instance of the class.
	 *
	 * @var FrontendController
	 */
	private static $instance = null;

	/**
	 * Gets an instance of this object.
	 *
	 * @static
	 * @access public
	 * @return FrontendController
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Register routes.
	 */
	public function register_routes() {
		register_rest_route(
			$this->namespace . $this->version,
			'/subscribe',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'subscribe' ),
				'permission_callback' => '__return_true',
			)
		);

		register_rest_route(
			$this->namespace . $this->version,
			'/product-status',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_product_status' ),
				'permission_callback' => '__return_true',
			)
		);

		register_rest_route(
			$this->namespace . $this->version,
			'/unsubscribe-ajax',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'unsubscribe_ajax' ),
				'permission_callback' => '__return_true',
			)
		);

		register_rest_route(
			$this->namespace . $this->version,
			'/batch-product-status',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_batch_product_status' ),
				'permission_callback' => '__return_true',
			)
		);
	}

	/**
	 * Get real-time product status for multiple products (used for guest hydration on archives).
	 *
	 * @param WP_REST_Request $request The request object.
	 * @return array|\WP_Error
	 */
	public function get_batch_product_status( WP_REST_Request $request ) {
		$product_ids = $request->get_param( 'product_ids' );
		if ( empty( $product_ids ) || ! is_array( $product_ids ) ) {
			return new \WP_Error( 'invalid_ids', __( 'Invalid product IDs.', 'notifybay-waitlist-and-stock-alert-woo' ), array( 'status' => 400 ) );
		}

		$results = array();
		$email   = sanitize_email( $request->get_param( 'email' ) );

		$all_subscriptions = array();
		if ( $email ) {
			$all_subscriptions = Lead::get_user_subscriptions_for_products( $email, $product_ids );
		}

		foreach ( $product_ids as $product_id ) {
			$product_id = (int) $product_id;
			$product    = wc_get_product( $product_id );
			if ( ! $product ) {
				continue;
			}

			// Force variation_subscriptions to be an object so JS receives a dictionary keyed by variation_id
			$var_subs = isset( $all_subscriptions[ $product_id ] ) ? $all_subscriptions[ $product_id ] : new \stdClass();
			if ( is_array( $var_subs ) ) {
				$var_subs = (object) $var_subs;
			}

			$results[ $product_id ] = array(
				'variation_subscriptions' => $var_subs,
			);
		}

		return rest_ensure_response( array( 'results' => $results ) );
	}

	/**
	 * Get real-time product status and demand data.
	 *
	 * @param WP_REST_Request $request The request object.
	 * @return array|\WP_Error
	 */
	public function get_product_status( WP_REST_Request $request ) {
		$product_id = (int) $request->get_param( 'product_id' );
		if ( ! $product_id ) {
			return new \WP_Error( 'invalid_id', __( 'Invalid product ID.', 'notifybay-waitlist-and-stock-alert-woo' ), array( 'status' => 400 ) );
		}

		$product = wc_get_product( $product_id );
		if ( ! $product ) {
			return new \WP_Error( 'not_found', __( 'Product not found.', 'notifybay-waitlist-and-stock-alert-woo' ), array( 'status' => 404 ) );
		}

		global $wpdb;

		$settings  = Settings::get_instance();
		$overrides = get_post_meta( $product_id, '_notifybay_overrides', true );
		if ( ! is_array( $overrides ) ) {
			$overrides = array();
		}

		$backorder_mode = ( ( $overrides['backorder_mode'] ?? 'default' ) !== 'default' )
			? $overrides['backorder_mode']
			: $settings->get_settings( 'general_backorderWaitlist', '0' );

		$variation_id       = (int) $request->get_param( 'variation_id' );
		$is_out_of_stock    = ( $variation_id ) ? ( ! wc_get_product( $variation_id )->is_in_stock() ) : ( ! $product->is_in_stock() );
		$backorders_allowed = ( $variation_id ) ? ( wc_get_product( $variation_id )->backorders_allowed() ) : ( $product->backorders_allowed() );

		$show_waitlist = false;
		if ( empty( $overrides['disable_waitlist'] ) ) {
			$show_waitlist = \NotifyBay\Frontend\ProductPage::should_show_waitlist( $backorder_mode, $is_out_of_stock, $backorders_allowed );
		}

		$is_subscribed_waitlist  = false;
		$variation_subscriptions = array();
		$user_id                 = get_current_user_id();
		$skip_subscription       = (bool) $request->get_param( 'skip_subscription' );

		if ( $user_id && ! $skip_subscription ) {
			$user_email      = wp_get_current_user()->user_email;
			$check_variation = $variation_id ? $variation_id : 0;

			$is_subscribed_waitlist = (bool) $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom {$wpdb->prefix}notifybay_leads table; values are bound via prepare(). Direct, uncached queries are intentional for this real-time data-access layer.
				$wpdb->prepare(
					"SELECT id FROM {$wpdb->prefix}notifybay_leads WHERE product_id = %d AND variation_id = %d AND user_email = %s AND type = 'waitlist' AND status IN ('active', 'pending_verification')", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					$product_id,
					$check_variation,
					$user_email
				)
			);
		} elseif ( ! $user_id && ! $skip_subscription && $request->get_param( 'email' ) ) {
			$guest_email             = sanitize_email( $request->get_param( 'email' ) );
			$variation_subscriptions = Lead::get_user_subscriptions_for_product( $guest_email, $product_id );

			$check_variation        = $variation_id ? $variation_id : 0;
			$is_subscribed_waitlist = ! empty( $variation_subscriptions[ $check_variation ]['waitlist'] );
		}

		$response = array(
			'is_in_stock'             => $product->is_in_stock(),
			'stock_qty'               => (int) $product->get_stock_quantity(),
			'show_waitlist'           => $show_waitlist,
			'is_subscribed_waitlist'  => $is_subscribed_waitlist,
			'variation_subscriptions' => empty( $variation_subscriptions ) ? new \stdClass() : (object) $variation_subscriptions,
			'skip_subscription'       => $skip_subscription,
			// Wishlist and FOMO ('X people are waiting') are premium features and are not
			// computed by Free. These keys are defaulted here so the response shape is
			// stable whether or not NotifyBay Pro is active; Pro overwrites them via the
			// notifybay_product_status_response filter below.
			'show_wishlist'           => false,
			'is_subscribed_wishlist'  => false,
			'wishlist_count'          => 0,
			'fomo_count'              => 0,
		);

		/**
		 * Filters the product-status REST response.
		 *
		 * Used by NotifyBay Pro to add Wishlist and FOMO fields (`show_wishlist`,
		 * `is_subscribed_wishlist`, `wishlist_count`, `fomo_count`, and, for variable
		 * products, `variation_fomo_map`) which Free does not compute.
		 *
		 * @since 1.0.0
		 * @hook notifybay_product_status_response
		 * @param array           $response     The response payload built so far.
		 * @param \WC_Product     $product      The product (or parent product, if $variation_id is set).
		 * @param int             $product_id   The product ID.
		 * @param int             $variation_id The variation ID (0 if none).
		 * @param WP_REST_Request $request      The original request.
		 * @param array           $overrides    Per-product `_notifybay_overrides` meta.
		 * @return array
		 */
		return apply_filters( 'notifybay_product_status_response', $response, $product, $product_id, $variation_id, $request, $overrides );
	}

	/**
	 * Subscribe a user to a waitlist or wishlist.
	 *
	 * @param WP_REST_Request $request The request object.
	 * @return array|\WP_Error
	 */
	public function subscribe( WP_REST_Request $request ) {
		// Nonce check is crucial for security
		$nonce = $request->get_header( 'X-WP-Nonce' );
		if ( ! wp_verify_nonce( $nonce, 'wp_rest' ) ) {
			return new \WP_Error( 'rest_cookie_invalid', __( 'Security check failed.', 'notifybay-waitlist-and-stock-alert-woo' ), array( 'status' => 403 ) );
		}

		/**
		 * Filters which lead `type` values the subscribe endpoint accepts.
		 *
		 * Free only accepts `waitlist`. NotifyBay Pro adds `wishlist` here (rather than
		 * Free hardcoding both), so Free never processes wishlist signups on its own.
		 *
		 * @since 1.0.0
		 * @hook notifybay_allowed_lead_types
		 * @param string[] $allowed_types Allowed `type` values. Default `array( 'waitlist' )`.
		 * @return string[]
		 */
		$allowed_types = apply_filters( 'notifybay_allowed_lead_types', array( 'waitlist' ) );

		$rules = array(
			'email'      => 'required|email',
			'product_id' => 'required|integer',
			'type'       => 'required|in:' . implode( ',', $allowed_types ),
		);

		$validated = $this->validate( $request, $rules );
		if ( is_wp_error( $validated ) ) {
			return $validated;
		}

		$settings = Settings::get_instance();
		if ( 'wishlist' === $validated['type'] && ! $settings->get_settings( 'general_wishlistEnabled', false ) ) {
			return new \WP_Error( 'feature_disabled', __( 'Wishlist functionality is currently disabled.', 'notifybay-waitlist-and-stock-alert-woo' ), array( 'status' => 403 ) );
		}

		// Rate limiting to prevent spam
		if ( ! $this->check_rate_limit( $validated['email'] ) ) {
			return new \WP_Error( 'rate_limit', __( 'Too many requests. Please try again later.', 'notifybay-waitlist-and-stock-alert-woo' ), array( 'status' => 429 ) );
		}

		$user_id = get_current_user_id();

		$settings = Settings::get_instance();
		$status   = ( $settings->get_settings( 'general_doubleOptIn', false ) ) ? 'pending_verification' : 'active';

		$expires_at  = null;
		$expiry_days = (int) $request->get_param( 'notifybay_expiry' );

		// Fallback to default setting if not provided from frontend
		if ( $expiry_days <= 0 ) {
			$settings    = Settings::get_instance();
			$expiry_days = (int) $settings->get_settings( 'appearance_waitlistExpiryDefault' );
		}

		if ( $expiry_days > 0 ) {
			$expires_at = gmdate( 'Y-m-d H:i:s', time() + ( $expiry_days * DAY_IN_SECONDS ) );
		}

		$product   = wc_get_product( $validated['product_id'] );
		$lead_data = array(
			'user_email'            => $validated['email'],
			'user_id'               => $user_id ? $user_id : null,
			'product_id'            => (int) $validated['product_id'],
			'variation_id'          => (int) $request->get_param( 'variation_id' ),
			'type'                  => $validated['type'],
			'status'                => $status,
			'verification_token'    => wp_generate_password( 32, false ),
			'user_locale'           => get_user_locale(),
			'product_name_snapshot' => get_the_title( $validated['product_id'] ),
			'expires_at'            => $expires_at,
			'price_at_subscription' => (float) $product->get_price(),
			'user_currency'         => get_woocommerce_currency(),
		);

		global $wpdb;
		$success = Lead::upsert_lead( $lead_data );
		$lead_id = $wpdb->insert_id; // upsert_lead uses insert_id internally if it was a new lead or updated

		if ( $success ) {
			if ( 'pending_verification' === $status ) {
				do_action( 'notifybay_run_verification', $lead_id );
			}

			// Admin Email Alert
			if ( $settings->get_settings( 'engine_adminAlerts', false ) ) {
				$admin_email = get_option( 'admin_email' );
				/* translators: %s: lead type */
				$subject = sprintf( __( '[NotifyBay] New %s signup', 'notifybay-waitlist-and-stock-alert-woo' ), ucfirst( $validated['type'] ) );
				$body    = TemplateRenderer::render(
					'emails/admin-alert',
					array(
						'user_email'    => $validated['email'],
						'lead_type'     => $validated['type'],
						'product_title' => get_the_title( $validated['product_id'] ),
					),
					true
				);
				wp_mail( $admin_email, $subject, $body );
			}

			$message = ( 'pending_verification' === $status )
				? __( 'Please check your email to verify your subscription.', 'notifybay-waitlist-and-stock-alert-woo' )
				: ( ( 'waitlist' === $validated['type'] )
					? $settings->get_settings( 'appearance_waitlistSuccessMessage' )
					: $settings->get_settings( 'appearance_wishlistSuccessMessage' ) );

			// Fallback if somehow settings are missing
			if ( ! $message ) {
				$message = __( 'Successfully subscribed! You will be notified as soon as possible.', 'notifybay-waitlist-and-stock-alert-woo' );
			}

			// Calculate fresh wishlist count for the response
			$wishlist_count = (int) $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom {$wpdb->prefix}notifybay_leads table; values are bound via prepare(). Direct, uncached queries are intentional for this real-time data-access layer.
				$wpdb->prepare(
					"SELECT COUNT(*) FROM {$wpdb->prefix}notifybay_leads WHERE user_email = %s AND type = 'wishlist' AND status = 'active'", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					$validated['email']
				)
			);

			return array(
				'success'        => true,
				'message'        => $message,
				'wishlist_count' => $wishlist_count,
			);
		}

		notifybay_log( sprintf( 'Database error during subscription for email %s, product_id %d', $validated['email'], $validated['product_id'] ), 'ERROR' );
		return new \WP_Error( 'db_error', __( 'Could not process subscription. Please try again.', 'notifybay-waitlist-and-stock-alert-woo' ), array( 'status' => 500 ) );
	}

	/**
	 * Unsubscribe a user via AJAX (used in My Account).
	 *
	 * @param \WP_REST_Request $request The request object.
	 * @return array|\WP_Error
	 */
	public function unsubscribe_ajax( \WP_REST_Request $request ) {
		$nonce = $request->get_header( 'X-WP-Nonce' );
		if ( ! wp_verify_nonce( $nonce, 'wp_rest' ) ) {
			return new \WP_Error( 'rest_cookie_invalid', __( 'Security check failed.', 'notifybay-waitlist-and-stock-alert-woo' ), array( 'status' => 403 ) );
		}

		$lead_id = (int) $request->get_param( 'lead_id' );
		if ( ! $lead_id ) {
			return new \WP_Error( 'invalid_id', __( 'Invalid lead ID.', 'notifybay-waitlist-and-stock-alert-woo' ), array( 'status' => 400 ) );
		}

		$user_email = is_user_logged_in() ? wp_get_current_user()->user_email : '';
		if ( ! $user_email ) {
			return new \WP_Error( 'forbidden', __( 'You must be logged in to manage subscriptions.', 'notifybay-waitlist-and-stock-alert-woo' ), array( 'status' => 403 ) );
		}

		global $wpdb;
		$lead_model = new Lead();
		$table      = $lead_model->get_table();

		// Ensure the lead belongs to the user before unsubscribing
		$result = $wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom {$wpdb->prefix}notifybay_leads table; values are bound via prepare(). Direct, uncached queries are intentional for this real-time data-access layer.
			$table,
			array(
				'status'     => 'unsubscribed',
				'updated_at' => current_time( 'mysql' ),
			),
			array(
				'id'         => $lead_id,
				'user_email' => $user_email,
			)
		);

		if ( $result ) {
			// Calculate fresh wishlist count for the response
			$wishlist_count = (int) $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom {$wpdb->prefix}notifybay_leads table; values are bound via prepare(). Direct, uncached queries are intentional for this real-time data-access layer.
				$wpdb->prepare(
					"SELECT COUNT(*) FROM {$wpdb->prefix}notifybay_leads WHERE user_email = %s AND type = 'wishlist' AND status = 'active'", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					$user_email
				)
			);

			return array(
				'success'        => true,
				'message'        => __( 'Unsubscribed successfully.', 'notifybay-waitlist-and-stock-alert-woo' ),
				'wishlist_count' => $wishlist_count,
			);
		}

		return new \WP_Error( 'not_found', __( 'Subscription not found or already removed.', 'notifybay-waitlist-and-stock-alert-woo' ), array( 'status' => 404 ) );
	}

	/**
	 * Simple rate limiting using transients.
	 *
	 * Caps repeated signups from the same email address to curb spam and
	 * email-bombing of the public subscribe endpoint. Returns false once the
	 * threshold is exceeded within the window, so the caller can respond 429.
	 *
	 * @param string $email The user email.
	 * @return bool True if the request is within the allowed rate, false otherwise.
	 */
	private function check_rate_limit( $email ) {
		$key   = 'notifybay_rl_' . md5( strtolower( (string) $email ) );
		$count = (int) get_transient( $key );

		/**
		 * Filters the maximum number of signups allowed per email within the window.
		 *
		 * @since 1.0.0
		 * @param int $max_attempts Default 5.
		 */
		$max_attempts = (int) apply_filters( 'notifybay_rate_limit_max_attempts', 5 );

		if ( $count >= $max_attempts ) {
			return false;
		}

		set_transient( $key, $count + 1, 10 * MINUTE_IN_SECONDS );
		return true;
	}
}
