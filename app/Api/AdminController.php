<?php
/**
 * Admin API Controller.
 *
 * Handles administrative REST API requests for analytics and lead management.
 *
 * @package    NotifyBay
 * @subpackage Api
 * @since      1.0.0
 * @author     WPAnchorBay <sankarsan@wpanchorbay.com>
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

		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $as_table ) ) ) ) { // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom {$wpdb->prefix}notifybay_leads table; values are bound via prepare(). Direct, uncached queries are intentional for this real-time data-access layer.
			$groups_table = $wpdb->prefix . 'actionscheduler_groups';
			$results      = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Action Scheduler tables; a direct, uncached read is intentional for this admin status widget.
				$wpdb->prepare(
					"SELECT a.status, COUNT(*) as count
					 FROM %i a
					 LEFT JOIN %i g ON a.group_id = g.group_id
					 WHERE g.slug = 'notifybay_alerts'
					 GROUP BY a.status",
					$as_table,
					$groups_table
				)
			);
			foreach ( $results as $res ) {
				$jobs[ $res->status ] = (int) $res->count;
			}
		}

		// Failed Leads
		$lead_table   = $wpdb->prefix . 'notifybay_leads';
		$failed_leads = (int) $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom {$wpdb->prefix}notifybay_leads table; a direct, uncached read is intentional for this admin status widget.
			$wpdb->prepare( "SELECT COUNT(*) FROM %i WHERE status = 'failed'", $lead_table )
		);

		// Processing Leads (potentially stuck)
		$processing_leads = (int) $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom {$wpdb->prefix}notifybay_leads table; a direct, uncached read is intentional for this admin status widget.
			$wpdb->prepare( "SELECT COUNT(*) FROM %i WHERE status = 'processing'", $lead_table )
		);

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

			/*
			 * Same rule as the bulk action in bulk_actions(): restoring a lead
			 * to `active` MUST also clear expires_at. Without it the lead keeps
			 * the stale, already-past date that expired it, and
			 * Engine\CronTasks::cleanup_expired_leads() flips it back to
			 * `expired` on the next daily run -- the edit reports success and
			 * silently undoes itself within a day.
			 *
			 * Only the bulk path was fixed at first, which left the per-row
			 * edit on the Leads screen still doing it. Cleared rather than
			 * recomputed, for the reason given at length there: the merchant is
			 * making a deliberate exception for this lead, and imposing a fresh
			 * countdown they did not ask for would re-create the same surprise
			 * on a longer timer.
			 */
			if ( 'active' === $lead->status ) {
				$lead->expires_at = null;
			}
		}

		// There was a `target_price` branch here. `target_price` is not a column
		// in {$wpdb->prefix}notifybay_leads and never has been, so the UPDATE was
		// rejected outright with "Unknown column 'target_price' in 'field list'"
		// -- taking the user_email and status changes in the same request down
		// with it, because Model::save() writes every attribute at once. The
		// caller saw a bare 500 "Could not update lead." naming nothing.
		//
		// Not replaced with price_at_subscription (the real column). Nothing
		// reads a customer-set target: Pro's price-drop trigger compares the new
		// price against price_at_subscription itself
		// (ProDispatcher::get_price_drop_leads), and no code in Free or Pro ever
		// wrote target_price. Making the field editable would be a new feature,
		// not a fix.
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

		/*
		 * Refuse an action this method does not implement, rather than falling
		 * past both branches below to the success response at the end. That
		 * fall-through answered "success: true" having touched nothing, so a
		 * misspelled action -- or a caller sending `action` instead of
		 * `bulk_action`, which is easy to do -- reported that a hundred leads
		 * had been updated when none had. A silent no-op reported as success is
		 * worse than an error, because nobody goes looking.
		 */
		$allowed = array( 'delete', 'active', 'expired', 'unsubscribed' );

		if ( ! in_array( $action, $allowed, true ) ) {
			return new \WP_Error(
				'invalid_bulk_action',
				sprintf(
					/* translators: %s: comma-separated list of the actions this endpoint accepts. */
					__( 'Unknown bulk action. Expected one of: %s.', 'notifybay-waitlist-and-stock-alert-woo' ),
					implode( ', ', $allowed )
				),
				array( 'status' => 400 )
			);
		}

		global $wpdb;
		$lead_model      = new Lead();
		$table           = $lead_model->get_table();
		$ids_placeholder = implode( ',', array_fill( 0, count( $ids ), '%d' ) );

		if ( 'delete' === $action ) {
			$wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom {$wpdb->prefix}notifybay_leads table; a direct, uncached bulk write is intentional for this admin action.
				$wpdb->prepare( // phpcs:ignore WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- The IN() list is a runtime-sized %d placeholder set (one per id); the arg count always matches.
					"DELETE FROM %i WHERE id IN ({$ids_placeholder})", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $ids_placeholder is a literal list of %d placeholders; the table and every id are bound.
					array_merge( array( $table ), $ids )
				)
			);
		} elseif ( in_array( $action, array( 'active', 'expired', 'unsubscribed' ), true ) ) {
			/*
			 * Restoring a lead to `active` MUST also clear expires_at. This wrote
			 * only status and updated_at, so a lead brought back from `expired`
			 * kept the stale, already-past expires_at that had expired it in the
			 * first place -- and Engine\CronTasks::cleanup_expired_leads() flipped
			 * it straight back to `expired` on the next daily run. The admin
			 * action reported success and then silently undid itself within a day,
			 * which is worse than refusing outright.
			 *
			 * Clearing rather than recomputing: the merchant is making a
			 * deliberate exception for these specific leads, so re-imposing a
			 * fresh countdown they did not ask for would re-create the same
			 * surprise on a longer timer. It also matches what
			 * Database\Migrations\FixLeadExpiry does for the historical rows.
			 */
			$set_clause = 'active' === $action
				? 'SET status = %s, expires_at = NULL, updated_at = %s'
				: 'SET status = %s, updated_at = %s';

			$wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom {$wpdb->prefix}notifybay_leads table; a direct, uncached bulk write is intentional for this admin action.
				$wpdb->prepare( // phpcs:ignore WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- The IN() list is a runtime-sized %d placeholder set; the arg count always matches.
					"UPDATE %i {$set_clause} WHERE id IN ({$ids_placeholder})", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $set_clause is one of two literal strings chosen above and $ids_placeholder is a literal list of %d placeholders; the table and every value are bound.
					array_merge( array( $table, $action, current_time( 'mysql' ) ), $ids )
				)
			);
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

		/**
		 * Neutralize spreadsheet formula injection.
		 *
		 * A cell beginning with =, +, -, @ (or a leading tab/carriage return) is
		 * interpreted as a formula by Excel/Sheets. Prefixing such values with a
		 * single quote forces them to be treated as plain text.
		 *
		 * @param mixed $value The raw cell value.
		 * @return string The safe cell value.
		 */
		$notifybay_csv_safe = function ( $value ) {
			$value = (string) $value;
			if ( '' !== $value && preg_match( '/^[=+\-@\t\r]/', $value ) ) {
				return "'" . $value;
			}
			return $value;
		};

		$output = fopen( 'php://output', 'w' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
		fputcsv( $output, array( 'ID', 'Email', 'User ID', 'Product ID', 'Variation ID', 'Type', 'Status', 'Price at Subscription', 'Created At' ) );

		global $wpdb;
		$lead_model = new Lead();
		$table      = $lead_model->get_table();
		$leads      = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom {$wpdb->prefix}notifybay_leads table; a direct, uncached read is intentional for this admin CSV export.
			$wpdb->prepare( 'SELECT * FROM %i ORDER BY created_at DESC', $table ),
			ARRAY_A
		);

		foreach ( $leads as $lead ) {
			fputcsv(
				$output,
				array_map(
					$notifybay_csv_safe,
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
				)
			);
		}

		fclose( $output ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
		exit;
	}
}
