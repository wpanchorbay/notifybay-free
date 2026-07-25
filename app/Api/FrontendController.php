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
				'permission_callback' => array( $this, 'verify_public_nonce' ),
			)
		);

		register_rest_route(
			$this->namespace . $this->version,
			'/product-status',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_product_status' ),
				'permission_callback' => array( $this, 'verify_public_nonce' ),
			)
		);

		register_rest_route(
			$this->namespace . $this->version,
			'/unsubscribe-ajax',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'unsubscribe_ajax' ),
				'permission_callback' => array( $this, 'verify_public_nonce' ),
			)
		);

		register_rest_route(
			$this->namespace . $this->version,
			'/batch-product-status',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_batch_product_status' ),
				'permission_callback' => array( $this, 'verify_public_nonce' ),
			)
		);
	}

	/**
	 * Compute the guest hydration token for an email address.
	 *
	 * The token is an HMAC of the normalized email keyed with a server secret
	 * (wp_salt). It is returned to the subscriber in the subscribe response and
	 * must be presented back to the read-only hydration endpoints, proving the
	 * caller actually subscribed with that email rather than probing arbitrary
	 * addresses. Forging it requires the site secret, which is never exposed.
	 *
	 * @since 1.0.0
	 * @param string $email The subscriber email.
	 * @return string The hydration token.
	 */
	private function hydration_token( $email ) {
		// Normalize the email the same way on both the issuing (subscribe) and
		// verifying (hydration) paths so the HMAC round-trips. sanitize_email() is
		// idempotent, so applying it here makes the token independent of whether
		// the caller passed a raw or already-sanitized address.
		$normalized = strtolower( trim( sanitize_email( (string) $email ) ) );
		return hash_hmac( 'sha256', $normalized, wp_salt() );
	}

	/**
	 * Verify a supplied guest hydration token against an email address.
	 *
	 * Uses a timing-safe comparison. Returns false for an empty token so a
	 * missing token never resolves subscription state.
	 *
	 * @since 1.0.0
	 * @param string $email The email supplied with the request.
	 * @param string $token The token supplied with the request.
	 * @return bool True when the token matches the email.
	 */
	private function verify_hydration_token( $email, $token ) {
		if ( '' === (string) $token ) {
			return false;
		}
		return hash_equals( $this->hydration_token( $email ), (string) $token );
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
		$token   = (string) $request->get_param( 'token' );

		// Only resolve subscription state when the caller proves ownership of the
		// email with the token issued to it at subscribe time. Without a valid
		// token the endpoint returns empty state, so arbitrary emails cannot be
		// probed for their subscription status.
		$all_subscriptions = array();
		if ( $email && $this->verify_hydration_token( $email, $token ) ) {
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
			$guest_email = sanitize_email( $request->get_param( 'email' ) );
			$guest_token = (string) $request->get_param( 'token' );

			// Require the ownership token before revealing a guest's subscription
			// state, so arbitrary emails cannot be probed. Without it the response
			// simply reports "not subscribed".
			if ( $guest_email && $this->verify_hydration_token( $guest_email, $guest_token ) ) {
				$variation_subscriptions = Lead::get_user_subscriptions_for_product( $guest_email, $product_id );

				$check_variation        = $variation_id ? $variation_id : 0;
				$is_subscribed_waitlist = ! empty( $variation_subscriptions[ $check_variation ]['waitlist'] );
			}
		}

		$response = array(
			'is_in_stock'             => $product->is_in_stock(),
			'stock_qty'               => (int) $product->get_stock_quantity(),
			'show_waitlist'           => $show_waitlist,
			'is_subscribed_waitlist'  => $is_subscribed_waitlist,
			'variation_subscriptions' => empty( $variation_subscriptions ) ? new \stdClass() : (object) $variation_subscriptions,
			'skip_subscription'       => $skip_subscription,
		);

		/**
		 * Filters the product-status REST response.
		 *
		 * Used by a premium add-on (NotifyBay Pro) to add its own fields to the
		 * product-status payload which Free does not compute.
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
	 * Subscribe a user to the waitlist (or any lead type an add-on has registered).
	 *
	 * @param WP_REST_Request $request The request object.
	 * @return array|\WP_Error
	 */
	public function subscribe( WP_REST_Request $request ) {
		// The `wp_rest` nonce is verified by the route's permission_callback
		// (ApiController::verify_public_nonce).

		/**
		 * Filters which lead `type` values the subscribe endpoint accepts.
		 *
		 * Free only accepts `waitlist`. A premium add-on (NotifyBay Pro) can append
		 * its own lead types here, so Free's generic subscribe endpoint processes
		 * them without hardcoding any premium feature.
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
				: $settings->get_settings( 'appearance_waitlistSuccessMessage' );

			// Fallback if somehow settings are missing.
			if ( ! $message ) {
				$message = __( 'Successfully subscribed! You will be notified as soon as possible.', 'notifybay-waitlist-and-stock-alert-woo' );
			}

			/**
			 * Filters the success message returned by the subscribe endpoint.
			 *
			 * Lets an add-on (NotifyBay Pro) supply a type-specific message for
			 * the lead types it registers via `notifybay_allowed_lead_types`.
			 *
			 * @since 1.0.0
			 * @param string $message   The success message so far.
			 * @param string $type      The lead type that was subscribed.
			 * @param string $status    The resulting lead status.
			 * @param array  $validated The validated request data.
			 */
			$message = apply_filters( 'notifybay_subscribe_success_message', $message, $validated['type'], $status, $validated );

			$response = array(
				'success'         => true,
				'message'         => $message,
				// Ownership token the guest presents back to the read-only
				// hydration endpoints to prove it subscribed with this email.
				'hydration_token' => $this->hydration_token( $validated['email'] ),
			);

			/**
			 * Filters the subscribe endpoint's success response.
			 *
			 * Lets an add-on (NotifyBay Pro) attach extra fields to the response
			 * for the lead types it owns.
			 *
			 * @since 1.0.0
			 * @param array           $response  The response payload.
			 * @param array           $validated The validated request data.
			 * @param WP_REST_Request $request   The original request.
			 */
			return apply_filters( 'notifybay_subscribe_response', $response, $validated, $request );
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
		// The `wp_rest` nonce is verified by the route's permission_callback
		// (ApiController::verify_public_nonce).
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
			$response = array(
				'success' => true,
				'message' => __( 'Unsubscribed successfully.', 'notifybay-waitlist-and-stock-alert-woo' ),
			);

			/**
			 * Filters the unsubscribe endpoint's success response.
			 *
			 * Lets an add-on (NotifyBay Pro) attach a refreshed set of extra fields
			 * to the response for the lead types it owns.
			 *
			 * @since 1.0.0
			 * @param array  $response   The response payload.
			 * @param int    $lead_id    The lead that was unsubscribed.
			 * @param string $user_email The current user's email.
			 */
			return apply_filters( 'notifybay_unsubscribe_response', $response, $lead_id, $user_email );
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
