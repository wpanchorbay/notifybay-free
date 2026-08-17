<?php
/**
 * MCP tool handlers for waitlist leads.
 *
 * The logic here is lifted from `NotifyBay\Api\AdminController`, not
 * re-derived, with two deliberate differences:
 *
 * 1. Parameters arrive as a plain array the kit has already validated against
 *    the manifest's `input_schema`, not as a `WP_REST_Request`.
 * 2. There is no `current_user_can( 'manage_notifybay' )` check. Authorization
 *    belongs to the kit's `Gate` (the wpab_mcp_access capability and the
 *    read / read+modify / full access ladder) and `Guard` (the manifest's own
 *    `capability` block), and both run in the ability's permission_callback
 *    before execution is ever reached. Repeating the check here would turn a
 *    refusal into a handler error, which reports through a different channel
 *    and would be debugged in the wrong layer.
 *
 * Return values are plain arrays; `Registrar::wrap_execute()` passes them
 * through `Envelope::success()`. Do not wrap them here — that would produce a
 * doubly-nested `data` key. A failure returns a `WP_Error`, which the MCP
 * adapter converts into the protocol's own `isError: true`; wrapping it in
 * `Envelope::error()` instead would hand back a *successful* result that
 * merely looked like an error, and a client checking `isError` would never
 * see it.
 *
 * @package    NotifyBay
 * @subpackage Mcp
 * @since      1.0.3
 * @author     WPAnchorBay <sankarsan@wpanchorbay.com>
 */

namespace NotifyBay\Mcp\Tools;

use NotifyBay\Models\Lead;
use WPAB\Mcp\Envelope;
use WP_Error;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handlers for the four abilities declared in `config/mcp.php`.
 *
 * @since 1.0.3
 */
class Leads {

	/**
	 * The lead statuses the schema accepts, and the only ones writable here.
	 *
	 * Kept beside the handlers rather than read from the manifest so a schema
	 * change that is not mirrored here fails loudly on the write path instead
	 * of persisting a status nothing else in the plugin recognises.
	 *
	 * @since 1.0.3
	 * @var string[]
	 */
	private const STATUSES = array(
		'active',
		'pending_verification',
		'processing',
		'notified',
		'expired',
		'failed',
		'unsubscribed',
	);

	/**
	 * List waitlist leads, newest first.
	 *
	 * @since 1.0.3
	 * @param array $input Validated tool input.
	 * @return array
	 */
	public static function list_leads( array $input ): array {

		$page     = isset( $input['page'] ) ? absint( $input['page'] ) : 1;
		$per_page = isset( $input['per_page'] ) ? absint( $input['per_page'] ) : 20;

		/*
		 * Model::paginate() allow-lists both arrays against
		 * Lead::$queryable_columns and silently drops anything else, so every
		 * key below has to be a real column. An invented filter name would
		 * return unfiltered results rather than an error.
		 */
		$where = array();

		if ( ! empty( $input['status'] ) ) {
			$where['status'] = sanitize_text_field( $input['status'] );
		}

		if ( ! empty( $input['type'] ) ) {
			$where['type'] = sanitize_text_field( $input['type'] );
		}

		if ( ! empty( $input['product_id'] ) ) {
			$where['product_id'] = absint( $input['product_id'] );
		}

		// `search` is a LIKE against the email column, matching what the admin
		// Leads screen does.
		$search = array();

		if ( ! empty( $input['search'] ) ) {
			$search['user_email'] = sanitize_text_field( $input['search'] );
		}

		$pagination = Lead::paginate( $page, $per_page, $where, $search );

		$items = array();

		foreach ( $pagination['data'] as $lead ) {
			$items[] = self::shape( $lead );
		}

		return Envelope::paginate(
			$items,
			(int) $pagination['page'],
			(int) $pagination['per_page'],
			(int) $pagination['total']
		);
	}

	/**
	 * Report queue health: Action Scheduler job counts and stuck leads.
	 *
	 * @since 1.0.3
	 * @param array $input Validated tool input. Unused; the tool takes none.
	 * @return array
	 */
	public static function system_status( array $input ): array {

		unset( $input );

		global $wpdb;

		$as_table = $wpdb->prefix . 'actionscheduler_actions';
		$jobs     = array();

		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $as_table ) ) ) ) { // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Action Scheduler tables; a direct, uncached read is intentional for this real-time status report.
			$groups_table = $wpdb->prefix . 'actionscheduler_groups';
			$results      = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Action Scheduler tables; a direct, uncached read is intentional for this real-time status report.
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

		$lead_table = $wpdb->prefix . 'notifybay_leads';

		$failed_leads = (int) $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom {$wpdb->prefix}notifybay_leads table; a direct, uncached read is intentional for this real-time status report.
			$wpdb->prepare( "SELECT COUNT(*) FROM %i WHERE status = 'failed'", $lead_table )
		);

		$processing_leads = (int) $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom {$wpdb->prefix}notifybay_leads table; a direct, uncached read is intentional for this real-time status report.
			$wpdb->prepare( "SELECT COUNT(*) FROM %i WHERE status = 'processing'", $lead_table )
		);

		return array(
			'jobs'             => $jobs,
			'failed_leads'     => $failed_leads,
			'processing_leads' => $processing_leads,
			'wc_version'       => class_exists( 'WooCommerce' ) ? WC()->version : null,
			'plugin_version'   => defined( 'NOTIFYBAY_VERSION' ) ? NOTIFYBAY_VERSION : null,
		);
	}

	/**
	 * Change a single lead's status or email address.
	 *
	 * @since 1.0.3
	 * @param array $input Validated tool input.
	 * @return array|WP_Error
	 */
	public static function update_lead( array $input ) {

		$id   = isset( $input['id'] ) ? absint( $input['id'] ) : 0;
		$lead = $id > 0 ? Lead::find( $id ) : null;

		if ( null === $lead ) {
			return new WP_Error(
				'notifybay_lead_not_found',
				sprintf(
					/* translators: %d: lead id. */
					__( 'No waitlist lead with id %d.', 'notifybay-waitlist-and-stock-alert-woo' ),
					$id
				),
				array( 'status' => 404 )
			);
		}

		$changed = array();

		if ( isset( $input['status'] ) ) {
			$status = sanitize_text_field( $input['status'] );

			// The manifest's enum is enforced before execution, so reaching
			// this branch with an unknown status means the two have drifted.
			if ( ! in_array( $status, self::STATUSES, true ) ) {
				return new WP_Error(
					'notifybay_invalid_status',
					__( 'That is not a recognised lead status.', 'notifybay-waitlist-and-stock-alert-woo' ),
					array( 'status' => 400 )
				);
			}

			$lead->status = $status;
			$changed[]    = 'status';
		}

		if ( isset( $input['user_email'] ) ) {
			$email = sanitize_email( $input['user_email'] );

			if ( ! is_email( $email ) ) {
				return new WP_Error(
					'notifybay_invalid_email',
					__( 'That is not a valid email address.', 'notifybay-waitlist-and-stock-alert-woo' ),
					array( 'status' => 400 )
				);
			}

			$lead->user_email = $email;
			$changed[]        = 'user_email';
		}

		if ( empty( $changed ) ) {
			return new WP_Error(
				'notifybay_nothing_to_update',
				__( 'Supply at least one of status or user_email.', 'notifybay-waitlist-and-stock-alert-woo' ),
				array( 'status' => 400 )
			);
		}

		if ( ! $lead->save() ) {
			return new WP_Error(
				'notifybay_update_failed',
				__( 'The lead could not be saved.', 'notifybay-waitlist-and-stock-alert-woo' ),
				array( 'status' => 500 )
			);
		}

		return array(
			'lead'     => self::shape( $lead ),
			'changed'  => $changed,
			// Read by Registrar::wrap_execute() and written to the audit log
			// for every non-readonly call.
			'affected' => array( $id ),
		);
	}

	/**
	 * Permanently delete the leads with the given ids.
	 *
	 * Reports per-id outcomes rather than failing the whole call on the first
	 * missing id: a partial success that looked like a total failure would
	 * invite a retry that deletes nothing and reports the same error.
	 *
	 * @since 1.0.3
	 * @param array $input Validated tool input.
	 * @return array|WP_Error
	 */
	public static function delete_leads( array $input ) {

		$ids = isset( $input['ids'] ) && is_array( $input['ids'] ) ? $input['ids'] : array();
		$ids = array_values( array_unique( array_filter( array_map( 'absint', $ids ) ) ) );

		if ( empty( $ids ) ) {
			return new WP_Error(
				'notifybay_no_ids',
				__( 'No lead ids were supplied.', 'notifybay-waitlist-and-stock-alert-woo' ),
				array( 'status' => 400 )
			);
		}

		$deleted   = array();
		$not_found = array();
		$failed    = array();

		foreach ( $ids as $id ) {
			$lead = Lead::find( $id );

			if ( null === $lead ) {
				$not_found[] = $id;
				continue;
			}

			if ( $lead->delete() ) {
				$deleted[] = $id;
			} else {
				$failed[] = $id;
			}
		}

		return array(
			'deleted'   => $deleted,
			'not_found' => $not_found,
			'failed'    => $failed,
			'affected'  => $deleted,
		);
	}

	/**
	 * Reduce a Lead to the fields a tool caller should see.
	 *
	 * An allow-list, not `to_array()` minus a few keys: the row carries
	 * `verification_token` and `guest_token`, which are credentials that let
	 * the bearer act as that customer, and a new sensitive column added later
	 * must not leak by default.
	 *
	 * @since 1.0.3
	 * @param Lead $lead The lead to shape.
	 * @return array
	 */
	private static function shape( Lead $lead ): array {

		$product_id = $lead->variation_id ? (int) $lead->variation_id : (int) $lead->product_id;
		$product    = function_exists( 'wc_get_product' ) ? wc_get_product( $product_id ) : null;

		return array(
			'id'           => (int) $lead->id,
			'user_email'   => (string) $lead->user_email,
			'product_id'   => (int) $lead->product_id,
			'variation_id' => (int) $lead->variation_id,
			'product_name' => $product ? $product->get_name() : (string) $lead->product_name_snapshot,
			'type'         => (string) $lead->type,
			'status'       => (string) $lead->status,
			'created_at'   => (string) $lead->created_at,
			'notified_at'  => $lead->notified_at ? (string) $lead->notified_at : null,
			'expires_at'   => $lead->expires_at ? (string) $lead->expires_at : null,
		);
	}
}
