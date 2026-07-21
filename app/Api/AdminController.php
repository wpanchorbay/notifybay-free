<?php
/**
 * Admin API Controller.
 *
 * Handles administrative REST API requests for analytics and lead management.
 *
 * @package    NotifyBay
 * @subpackage Api
 * @since      1.0.0
 * @author     WPAnchorBay <sankarsan@gmail.com>
 */

namespace NotifyBay\Api;

use WP_REST_Request;
use WP_REST_Response;
use NotifyBay\Models\Lead;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class AdminController
 */
class AdminController extends ApiController {


	/**
	 * The single instance of the class.
	 *
	 * @var AdminController
	 */
	private static $instance = null;

	/**
	 * Gets an instance of this object.
	 *
	 * @static
	 * @access public
	 * @return AdminController
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
		// Note: analytics/revenue stats (/admin/stats -> get_stats) are premium
		// (revenue analytics dashboard) and are registered by NotifyBay Pro on its
		// own REST namespace, not here.

		register_rest_route(
			$this->namespace . $this->version,
			'/admin/system-status',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_system_status' ),
				'permission_callback' => array( $this, 'get_item_permissions_check' ),
			)
		);

		register_rest_route(
			$this->namespace . $this->version,
			'/admin/leads',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_leads' ),
				'permission_callback' => array( $this, 'get_item_permissions_check' ),
			)
		);

		register_rest_route(
			$this->namespace . $this->version,
			'/admin/leads/bulk',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'bulk_actions' ),
				'permission_callback' => array( $this, 'update_item_permissions_check' ),
			)
		);

		register_rest_route(
			$this->namespace . $this->version,
			'/admin/leads/export',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'export_leads' ),
				'permission_callback' => array( $this, 'get_item_permissions_check' ),
			)
		);

		register_rest_route(
			$this->namespace . $this->version,
			'/admin/wizard/complete',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'complete_wizard' ),
				'permission_callback' => array( $this, 'update_item_permissions_check' ),
			)
		);

		register_rest_route(
			$this->namespace . $this->version,
			'/admin/leads/(?P<id>\d+)',
			array(
				array(
					'methods'             => 'PUT',
					'callback'            => array( $this, 'update_lead' ),
					'permission_callback' => array( $this, 'update_item_permissions_check' ),
				),
				array(
					'methods'             => 'DELETE',
					'callback'            => array( $this, 'delete_lead' ),
					'permission_callback' => array( $this, 'update_item_permissions_check' ),
				),
			)
		);
	}

	/**
	 * Mark the first-run setup wizard as completed.
	 *
	 * @param WP_REST_Request $request The request object.
	 * @return WP_REST_Response
	 */
	public function complete_wizard( WP_REST_Request $request ) {
		update_option( 'notifybay_wizard_completed', 'yes' );
		return rest_ensure_response( array( 'success' => true ) );
	}

	/**
	 * Get system status data (Action Scheduler, etc.)
	 *
	 * @param WP_REST_Request $request The request object.
	 * @return WP_REST_Response
	 */
	public function get_system_status( WP_REST_Request $request ) {
		global $wpdb;

		// Action Scheduler Jobs in our group
		$as_table = $wpdb->prefix . 'actionscheduler_actions';
		$jobs     = array();

		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $as_table ) ) ) ) {
			$groups_table = $wpdb->prefix . 'actionscheduler_groups';
			$query        = "SELECT a.status, COUNT(*) as count 
				 FROM {$as_table} a 
				 LEFT JOIN {$groups_table} g ON a.group_id = g.group_id 
				 WHERE g.slug = 'notifybay_alerts' 
				 GROUP BY a.status";
			$results      = $wpdb->get_results( $query ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			foreach ( $results as $res ) {
				$jobs[ $res->status ] = (int) $res->count;
			}
		}

		// Failed Leads
		$lead_table   = $wpdb->prefix . 'notifybay_leads';
		$failed_leads = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$lead_table} WHERE status = 'failed'" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		// Processing Leads (potentially stuck)
		$processing_leads = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$lead_table} WHERE status = 'processing'" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		return rest_ensure_response(
			array(
				'jobs'             => $jobs,
				'failed_leads'     => $failed_leads,
				'processing_leads' => $processing_leads,
				'php_version'      => PHP_VERSION,
				'wp_version'       => get_bloginfo( 'version' ),
				'wc_version'       => class_exists( 'WooCommerce' ) ? WC()->version : 'Not installed',
			)
		);
	}

	/**
	 * Get paginated leads for management.
	 *
	 * @param WP_REST_Request $request The request object.
	 * @return WP_REST_Response
	 */
	public function get_leads( WP_REST_Request $request ) {
		$page     = $request->get_param( 'page' ) ? (int) $request->get_param( 'page' ) : 1;
		$per_page = $request->get_param( 'per_page' ) ? (int) $request->get_param( 'per_page' ) : 20;
		$search   = $request->get_param( 'search' );
		$type     = $request->get_param( 'type' );

		$where = array();
		if ( $type ) {
			$where['type'] = $type;
		}

		$search_query = array();
		if ( $search ) {
			$search_query['user_email'] = $search;
		}

		$pagination = Lead::paginate( $page, $per_page, $where, $search_query );

		$data = array();
		foreach ( $pagination['data'] as $lead ) {
			$product             = wc_get_product( $lead->variation_id ? $lead->variation_id : $lead->product_id );
			$row                 = $lead->to_array();
			$row['product_name'] = $product ? $product->get_name() : $lead->product_name_snapshot;
			$data[]              = $row;
		}

		$pagination['data'] = $data;

		return rest_ensure_response( $pagination );
	}

	/**
	 * Update a lead.
	 *
	 * @param WP_REST_Request $request The request object.
	 * @return WP_REST_Response|\WP_Error
	 */
	public function update_lead( WP_REST_Request $request ) {
		$id   = (int) $request->get_param( 'id' );
		$lead = Lead::find( $id );

		if ( ! $lead ) {
			return new \WP_Error( 'not_found', __( 'Lead not found.', 'notifybay-waitlist-and-stock-alert-woo' ), array( 'status' => 404 ) );
		}

		$params = $request->get_params();
		if ( isset( $params['user_email'] ) ) {
			$lead->user_email = sanitize_email( $params['user_email'] );
		}
		if ( isset( $params['status'] ) ) {
			$lead->status = sanitize_text_field( $params['status'] );
		}
		if ( isset( $params['target_price'] ) ) {
			$lead->target_price = (float) $params['target_price'];
		}

		if ( $lead->save() ) {
			return rest_ensure_response( array( 'success' => true ) );
		}

		return new \WP_Error( 'db_error', __( 'Could not update lead.', 'notifybay-waitlist-and-stock-alert-woo' ), array( 'status' => 500 ) );
	}

	/**
	 * Delete a lead.
	 *
	 * @param WP_REST_Request $request The request object.
	 * @return WP_REST_Response|\WP_Error
	 */
	public function delete_lead( WP_REST_Request $request ) {
		$id   = (int) $request->get_param( 'id' );
		$lead = Lead::find( $id );

		if ( ! $lead ) {
			return new \WP_Error( 'not_found', __( 'Lead not found.', 'notifybay-waitlist-and-stock-alert-woo' ), array( 'status' => 404 ) );
		}

		if ( $lead->delete() ) {
			return rest_ensure_response( array( 'success' => true ) );
		}

		return new \WP_Error( 'db_error', __( 'Could not delete lead.', 'notifybay-waitlist-and-stock-alert-woo' ), array( 'status' => 500 ) );
	}

	/**
	 * Bulk actions for leads.
	 *
	 * @param WP_REST_Request $request The request object.
	 * @return WP_REST_Response|\WP_Error
	 */
	public function bulk_actions( WP_REST_Request $request ) {
		$ids    = $request->get_param( 'ids' );
		$action = $request->get_param( 'bulk_action' );

		if ( empty( $ids ) || ! is_array( $ids ) ) {
			return new \WP_Error( 'invalid_ids', __( 'No IDs provided.', 'notifybay-waitlist-and-stock-alert-woo' ), array( 'status' => 400 ) );
		}

		global $wpdb;
		$lead_model      = new Lead();
		$table           = $lead_model->get_table();
		$ids_placeholder = implode( ',', array_fill( 0, count( $ids ), '%d' ) );

		if ( 'delete' === $action ) {
			$wpdb->query($wpdb->prepare("DELETE FROM {$table} WHERE id IN ({$ids_placeholder})", $ids)); // phpcs:ignore
		} elseif ( in_array( $action, array( 'active', 'expired', 'unsubscribed' ), true ) ) {
			$wpdb->query($wpdb->prepare("UPDATE {$table} SET status = %s, updated_at = %s WHERE id IN ({$ids_placeholder})", array_merge(array($action, current_time('mysql')), $ids))); // phpcs:ignore
		}

		return rest_ensure_response( array( 'success' => true ) );
	}

	/**
	 * Export leads to CSV.
	 */
	public function export_leads() {
		if ( ! current_user_can( 'manage_notifybay' ) ) {
			wp_die( esc_html__( 'Forbidden', 'notifybay-waitlist-and-stock-alert-woo' ) );
		}

		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=notifybay-leads-' . gmdate( 'Y-m-d' ) . '.csv' );

		$output = fopen( 'php://output', 'w' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
		fputcsv( $output, array( 'ID', 'Email', 'User ID', 'Product ID', 'Variation ID', 'Type', 'Status', 'Price at Subscription', 'Created At' ) );

		global $wpdb;
		$lead_model = new Lead();
		$table      = $lead_model->get_table();
		$leads = $wpdb->get_results("SELECT * FROM {$table} ORDER BY created_at DESC", ARRAY_A); // phpcs:ignore

		foreach ( $leads as $lead ) {
			fputcsv(
				$output,
				array(
					$lead['id'],
					$lead['user_email'],
					$lead['user_id'],
					$lead['product_id'],
					$lead['variation_id'],
					$lead['type'],
					$lead['status'],
					$lead['price_at_subscription'],
					$lead['created_at'],
				)
			);
		}

		fclose( $output ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
		exit;
	}
}
