<?php
/**
 * Api Controller — Base class for all REST API endpoints.
 *
 * @since      1.0.0
 * @package    NotifyBay
 * @subpackage NotifyBay/Api
 */

namespace NotifyBay\Api;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use WP_Error;
use WP_REST_Controller;
use WP_REST_Request;
use WP_REST_Response;
use Rakit\Validation\Validator;

/**
 * The parent class of all API controllers for this plugin.
 *
 * @since      1.0.0
 * @package    NotifyBay
 * @subpackage NotifyBay/Api
 */
class ApiController extends WP_REST_Controller {

	/**
	 * The single instance of the class.
	 *
	 * @since 1.0.0
	 * @var   ApiController
	 * @access private
	 */
	private static $instance = null;

	/**
	 * Rest route namespace.
	 *
	 * @var string
	 */
	public $namespace = \NOTIFYBAY_TEXT_DOMAIN . '/';

	/**
	 * Rest route version.
	 *
	 * @var string
	 */
	public $version = 'v1';

	/**
	 * Whether the controller supports batching.
	 *
	 * @since 1.0.0
	 * @var array
	 */
	protected $allow_batch = array( 'v1' => true );

	/**
	 * Constructor
	 *
	 * @since    1.0.0
	 */
	public function __construct() {}

	/**
	 * Initialize the class — registers REST routes.
	 */
	public function run() {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/**
	 * Gets an instance of this object.
	 *
	 * @static
	 * @access public
	 * @return object
	 * @since 1.0.0
	 */
	public static function get_instance() {
		static $instance = null;
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Throw error on object clone
	 *
	 * @access public
	 * @return void
	 * @since 1.0.0
	 */
	public function __clone() {
		_doing_it_wrong( __FUNCTION__, esc_html__( 'Cloning is not allowed.', 'notifybay-waitlist-and-stock-alert-woo' ), '1.0.0' );
	}

	/**
	 * Disable unserializing of the class
	 *
	 * @access public
	 * @return void
	 * @since 1.0.0
	 */
	public function __wakeup() {
		_doing_it_wrong( __FUNCTION__, esc_html__( 'Unserializing is not allowed.', 'notifybay-waitlist-and-stock-alert-woo' ), '1.0.0' );
	}

	/**
	 * Checks if a given request has access to read items.
	 *
	 * @since 1.0.0
	 * @param WP_REST_Request $request Full details about the request.
	 * @return bool|WP_Error True if the request has read access, WP_Error otherwise.
	 */
	public function get_item_permissions_check( $request ) {
		if ( ! current_user_can( 'manage_notifybay' ) ) {
			return new WP_Error(
				'rest_forbidden',
				__( 'Sorry, you are not allowed to access this resource.', 'notifybay-waitlist-and-stock-alert-woo' ),
				array( 'status' => rest_authorization_required_code() )
			);
		}

		$nonce = $request->get_header( 'X-WP-Nonce' );
		if ( ! $nonce || ! wp_verify_nonce( $nonce, 'wp_rest' ) ) {
			return new WP_Error( 'rest_nonce_invalid', __( 'The security token is invalid.', 'notifybay-waitlist-and-stock-alert-woo' ), array( 'status' => 403 ) );
		}

		return true;
	}

	/**
	 * Checks if a given request has access to update items.
	 *
	 * @since 1.0.0
	 * @param WP_REST_Request $request Full details about the request.
	 * @return bool|WP_Error True if the request has update access, WP_Error otherwise.
	 */
	public function update_item_permissions_check( $request ) {
		if ( ! current_user_can( 'manage_notifybay' ) ) {
			return new WP_Error(
				'rest_forbidden',
				__( 'Sorry, you are not allowed to access this resource.', 'notifybay-waitlist-and-stock-alert-woo' ),
				array( 'status' => rest_authorization_required_code() )
			);
		}

		$nonce = $request->get_header( 'X-WP-Nonce' );

		if ( ! $nonce ) {
			$nonce = isset( $_REQUEST['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['_wpnonce'] ) ) : '';
		}

		if ( ! wp_verify_nonce( $nonce, 'wp_rest' ) ) {
			return new WP_Error(
				'rest_invalid_nonce',
				__( 'Invalid or missing nonce.', 'notifybay-waitlist-and-stock-alert-woo' ),
				array( 'status' => 403 )
			);
		}

		return true;
	}

	/**
	 * Laravel-style request validation.
	 *
	 * @since 1.0.0
	 * @param WP_REST_Request $request  The incoming WP API request.
	 * @param array           $rules    The validation rules.
	 * @param array           $messages Optional custom error messages.
	 * @param array           $aliases  Optional custom attribute aliases.
	 * @return array|WP_Error Returns the validated data array, or a WP_Error if validation fails.
	 */
	protected function validate( WP_REST_Request $request, array $rules, array $messages = array(), array $aliases = array() ) {
		$validator = new Validator();

		// Get all parameters (GET, POST, JSON body)
		$inputs = $request->get_params();

		// Run the validation
		$validation = $validator->make( $inputs, $rules, $messages );

		if ( ! empty( $aliases ) ) {
			$validation->setAliases( $aliases );
		}

		$validation->validate();

		if ( $validation->fails() ) {
			// Get the first error message for each field
			$errors = $validation->errors()->firstOfAll();

			// Return a standard WordPress REST error with a 422 Unprocessable Entity status
			return new WP_Error(
				'validation_failed',
				__( 'Invalid data provided.', 'notifybay-waitlist-and-stock-alert-woo' ),
				array(
					'status' => 422,
					'errors' => $errors,
				)
			);
		}

		// Return only the safely validated data!
		return $validation->getValidData();
	}
}
