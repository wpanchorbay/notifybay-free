<?php
/**
 * Custom Endpoints & Compliance Handlers.
 *
 * Handles verification, unsubscribes, and GDPR requests.
 *
 * @package    NotifyBay
 * @subpackage Frontend
 * @since      1.0.0
 * @author     WPAnchorBay <sankarsan@gmail.com>
 */

namespace NotifyBay\Frontend;

use NotifyBay\Core\Plugin;
use NotifyBay\Models\Lead;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Endpoints
 */
class Endpoints {

	/**
	 * The single instance of the class.
	 *
	 * @var Endpoints
	 */
	private static $instance = null;

	/**
	 * Get the instance.
	 *
	 * @return Endpoints
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

		$loader->add_action( 'init', $this, 'handle_custom_endpoints' );

		// GDPR Hooks
		$loader->add_filter( 'wp_privacy_personal_data_exporters', $this, 'register_personal_data_exporter' );
		$loader->add_filter( 'wp_privacy_personal_data_erasers', $this, 'register_personal_data_eraser' );
	}

	/**
	 * Handle custom URL endpoints (verify/unsubscribe).
	 */
	public function handle_custom_endpoints() {
		if ( ! isset( $_GET['notifybay_action'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}

		$action = sanitize_text_field( wp_unslash( $_GET['notifybay_action'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$token  = isset( $_GET['token'] ) ? sanitize_text_field( wp_unslash( $_GET['token'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		if ( 'verify' === $action ) {
			$this->verify_lead( $token );
		} elseif ( 'unsubscribe' === $action ) {
			$this->unsubscribe_lead( $token );
		}
	}

	/**
	 * Verify a lead's email via token.
	 *
	 * @param string $token The verification token.
	 */
	private function verify_lead( $token ) {
		if ( ! $token ) {
			wp_die( esc_html__( 'Invalid verification token.', 'notifybay-waitlist-and-stock-alert-woo' ) );
		}

		global $wpdb;
		$lead_model = new Lead();
		$table      = $lead_model->get_table();

		$lead = $wpdb->get_row( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom {$wpdb->prefix}notifybay_leads table; values are bound via prepare(). Direct, uncached queries are intentional for this real-time data-access layer.
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}notifybay_leads WHERE verification_token = %s AND status = 'pending_verification'", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$token
			)
		);

		if ( ! $lead ) {
			wp_die( esc_html__( 'Invalid or expired verification link.', 'notifybay-waitlist-and-stock-alert-woo' ) );
		}

		$wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom {$wpdb->prefix}notifybay_leads table; values are bound via prepare(). Direct, uncached queries are intentional for this real-time data-access layer.
			$table,
			array(
				'status'     => 'active',
				'updated_at' => current_time( 'mysql' ),
			),
			array( 'id' => $lead->id )
		);

		wp_die(
			esc_html__( 'Email verified successfully! You will now receive notifications.', 'notifybay-waitlist-and-stock-alert-woo' ),
			esc_html__( 'Verification Successful', 'notifybay-waitlist-and-stock-alert-woo' ),
			array( 'response' => 200 )
		);
	}

	/**
	 * Unsubscribe a lead via token.
	 *
	 * @param string $token The token.
	 */
	private function unsubscribe_lead( $token ) {
		if ( ! $token ) {
			wp_die( esc_html__( 'Invalid unsubscribe token.', 'notifybay-waitlist-and-stock-alert-woo' ) );
		}

		global $wpdb;
		$lead_model = new Lead();
		$table      = $lead_model->get_table();

		$lead = $wpdb->get_row( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom {$wpdb->prefix}notifybay_leads table; values are bound via prepare(). Direct, uncached queries are intentional for this real-time data-access layer.
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}notifybay_leads WHERE verification_token = %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$token
			)
		);

		if ( ! $lead ) {
			wp_die( esc_html__( 'Unsubscribe failed. Lead not found.', 'notifybay-waitlist-and-stock-alert-woo' ) );
		}

		$wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom {$wpdb->prefix}notifybay_leads table; values are bound via prepare(). Direct, uncached queries are intentional for this real-time data-access layer.
			$table,
			array(
				'status'     => 'unsubscribed',
				'updated_at' => current_time( 'mysql' ),
			),
			array( 'id' => $lead->id )
		);

		wp_die(
			esc_html__( 'You have been successfully unsubscribed from this alert.', 'notifybay-waitlist-and-stock-alert-woo' ),
			esc_html__( 'Unsubscribed', 'notifybay-waitlist-and-stock-alert-woo' ),
			array( 'response' => 200 )
		);
	}

	/**
	 * Register GDPR data exporter.
	 *
	 * @param array $exporters Exporters.
	 * @return array
	 */
	public function register_personal_data_exporter( $exporters ) {
		$exporters['notifybay-leads'] = array(
			'exporter_friendly_name' => __( 'NotifyBay Leads', 'notifybay-waitlist-and-stock-alert-woo' ),
			'callback'               => array( $this, 'personal_data_exporter' ),
		);
		return $exporters;
	}

	/**
	 * Personal data exporter callback.
	 *
	 * @param string $email_address Email address.
	 * @param int    $page          Page number.
	 */
	public function personal_data_exporter( $email_address, $page = 1 ) {
		$lead_model = new Lead();
		$where      = array( 'user_email' => $email_address );
		$leads      = $lead_model::paginate( $page, 50, $where )['data'];

		$data_to_export = array();
		foreach ( $leads as $lead ) {
			$data_to_export[] = array(
				'group_id'    => 'notifybay-leads',
				'group_label' => __( 'NotifyBay Leads', 'notifybay-waitlist-and-stock-alert-woo' ),
				'item_id'     => 'lead-' . $lead->id,
				'data'        => array(
					array(
						'name'  => __( 'Email', 'notifybay-waitlist-and-stock-alert-woo' ),
						'value' => $lead->user_email,
					),
					array(
						'name'  => __( 'Product ID', 'notifybay-waitlist-and-stock-alert-woo' ),
						'value' => $lead->product_id,
					),
					array(
						'name'  => __( 'Status', 'notifybay-waitlist-and-stock-alert-woo' ),
						'value' => $lead->status,
					),
					array(
						'name'  => __( 'Created', 'notifybay-waitlist-and-stock-alert-woo' ),
						'value' => $lead->created_at,
					),
				),
			);
		}

		return array(
			'data' => $data_to_export,
			'done' => count( $leads ) < 50,
		);
	}

	/**
	 * Register GDPR data eraser.
	 *
	 * @param array $erasers Erasers.
	 * @return array
	 */
	public function register_personal_data_eraser( $erasers ) {
		$erasers['notifybay-leads'] = array(
			'eraser_friendly_name' => __( 'NotifyBay Leads', 'notifybay-waitlist-and-stock-alert-woo' ),
			'callback'             => array( $this, 'personal_data_eraser' ),
		);
		return $erasers;
	}

	/**
	 * Personal data eraser callback.
	 *
	 * @param string $email_address Email address.
	 */
	public function personal_data_eraser( $email_address ) {
		global $wpdb;
		$lead_model = new Lead();
		$table      = $lead_model->get_table();

		$count = $wpdb->delete( $table, array( 'user_email' => $email_address ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom {$wpdb->prefix}notifybay_leads table; values are bound via prepare(). Direct, uncached queries are intentional for this real-time data-access layer.

		return array(
			'items_removed'  => $count > 0,
			'items_retained' => false,
			'messages'       => array(),
			'done'           => true,
		);
	}
}
