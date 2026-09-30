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
		// Offered by the admin Leads screen (src/pages/Leads.tsx) with its own
		// badge, and the single most common status in real data. It was
		// missing from both this list and the manifest enum, which made every
		// converted lead unlistable and unsettable through MCP.
		'converted',
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

		/*
		 * Ordered by created_at, not by the primary key.
		 *
		 * For leads created through the site the two agree, because the id is
		 * auto-increment and created_at is stamped at insert. They diverge for
		 * anything imported or migrated, where ids are assigned in load order
		 * and created_at carries the original signup time -- and there the
		 * default id ordering silently answers "who joined most recently" with
		 * whoever happened to be inserted last.
		 */
		$pagination = Lead::paginate( $page, $per_page, $where, $search, 'created_at' );

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

		/*
		 * Seeded out here, not inside the SHOW TABLES guard below. Action
		 * Scheduler can store its actions in the legacy post table instead of
		 * this one, in which case the guard fails and the tool used to return
		 * `jobs: {}` -- destroying the very distinction the manifest promises
		 * these keys for, that a zero is tellable from a measurement that was
		 * never taken, on the one path where it is not zero.
		 *
		 * The running state is spelled `in-progress`
		 * (ActionScheduler_Store::STATUS_RUNNING). Seeding `running` instead
		 * matched no row, so a queue jammed with in-progress actions reported
		 * running 0 next to an unexpected sixth key merged in from the counts.
		 */
		$jobs = array(
			'pending'     => 0,
			'in-progress' => 0,
			'complete'    => 0,
			'failed'      => 0,
			'canceled'    => 0,
		);

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

		/*
		 * The WooCommerce "Enable this email" toggle is the final gate on every
		 * restock send, and it lives in WooCommerce's settings rather than
		 * NotifyBay's. With it off, dispatch runs, jobs complete, and nothing
		 * reaches a customer -- so the queue counts above all look healthy
		 * while the plugin does nothing. Reported here because it is the first
		 * thing worth checking when leads are waiting and no mail arrives.
		 */
		$restock_email = \NotifyBay\Emails\EmailManager::get_email( 'notifybay_restock' );

		return array(
			'jobs'                  => $jobs,
			'failed_leads'          => $failed_leads,
			'processing_leads'      => $processing_leads,
			'restock_email_enabled' => $restock_email ? (bool) $restock_email->is_enabled() : null,
			'wc_version'            => class_exists( 'WooCommerce' ) ? WC()->version : null,
			'plugin_version'        => defined( 'NOTIFYBAY_VERSION' ) ? NOTIFYBAY_VERSION : null,
		);
	}

	/**
	 * Normalise a datetime column to a real timestamp string or null.
	 *
	 * A plain truthiness test is not enough. MySQL happily stores the zero
	 * date "0000-00-00 00:00:00" -- any writer that binds a PHP null with a
	 * %s format produces an empty string, which MySQL coerces to it -- and
	 * that string is truthy in PHP, so it sailed through as if it were a
	 * timestamp. It reached clients on 156 of 258 rows, directly contradicting
	 * the documented rule that null means "not recorded", and no parser can
	 * read it. Treated as absent, which is what it means.
	 *
	 * @param mixed $value Raw column value.
	 * @return string|null
	 */
	private static function date_or_null( $value ) {
		if ( empty( $value ) ) {
			return null;
		}

		$value = (string) $value;

		if ( '' === trim( $value ) || 0 === strpos( $value, '0000-00-00' ) ) {
			return null;
		}

		return $value;
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

			/*
			 * Restoring a lead to `active` MUST also clear expires_at -- the
			 * same rule as Api\AdminController::update_lead() and its
			 * bulk_actions(). Without it the lead keeps the stale, already-past
			 * date that expired it, and Engine\CronTasks::cleanup_expired_leads()
			 * flips it back to `expired` on the next daily run.
			 *
			 * This is the third path that writes a lead status, and the third
			 * to need saying: the bulk action was fixed first, then the per-row
			 * edit, and this one was missed both times. It is the worst place to
			 * miss it, because the tool's own description promises that setting
			 * a lead back to active puts it in front of the restock engine
			 * again -- and the assistant reports success, then the lead quietly
			 * expires again within a day with nobody watching the screen.
			 */
			if ( 'active' === $lead->status ) {
				$lead->expires_at = null;
				$changed[]        = 'expires_at';
			}
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
	 * Why notifications are not arriving: failed leads with their recorded
	 * reason, and failed background jobs grouped by hook.
	 *
	 * Two separate things are reported because they are two separate problems
	 * and the counts in system-status invite conflating them. A failed *lead*
	 * is a customer who did not get their email. A failed *job* is usually a
	 * maintenance task that could not run -- on this store every one of them is
	 * "no callbacks are registered", which happens when an action fires while
	 * the plugin (or NotifyBay Pro) is not loaded, and has nothing to do with
	 * any individual customer.
	 *
	 * @since 1.0.3
	 * @param array $input Validated tool input.
	 * @return array
	 */
	public static function notification_failures( array $input ): array {

		global $wpdb;

		$per_page = isset( $input['per_page'] ) ? absint( $input['per_page'] ) : 20;
		$per_page = max( 1, min( 100, $per_page ) );
		$page     = max( 1, isset( $input['page'] ) ? absint( $input['page'] ) : 1 );

		$pagination = Lead::paginate( $page, $per_page, array( 'status' => 'failed' ), array(), 'updated_at' );

		$leads = array();

		foreach ( $pagination['data'] as $lead ) {
			$row = self::shape( $lead );

			$row['retry_count'] = (int) $lead->retry_count;

			// Not every failure listed here can be acted on -- see
			// resend_refusal_reason(). Without this field the only way to find
			// out is to call resend-notifications and be refused.
			$row['resend_refused_because'] = self::resend_refusal_reason( $lead );

			// The field this list is ordered by. Sorting on a value the caller
			// cannot see leaves them unable to confirm the order is what the
			// description claims, or to merge this list with another.
			$row['last_attempt_at'] = self::date_or_null( $lead->updated_at );
			$row['last_error']      = self::last_action_error( (int) $lead->id );

			$leads[] = $row;
		}

		return array(
			'leads'          => $leads,
			'leads_page'     => (int) $pagination['page'],
			'leads_per_page' => (int) $pagination['per_page'],
			'leads_total'    => (int) $pagination['total'],
			// Prefixed like the rest. A bare has_more beside leads_total reads
			// as if it covered the whole payload, jobs included.
			'leads_has_more' => ( (int) $pagination['page'] * $per_page ) < (int) $pagination['total'],
			'jobs'           => self::failed_jobs(),
		);
	}

	/**
	 * The most recent Action Scheduler log line for a lead's send attempts.
	 *
	 * The plugin does not persist a per-lead failure reason -- handle_failure()
	 * records only a retry count -- so the only account of what went wrong is
	 * Action Scheduler's own log, reached through the action whose args carry
	 * this lead id.
	 *
	 * Restricted to actions Action Scheduler itself marked failed. Without
	 * that clause this returned the newest log line for any matching action
	 * whatever its outcome, so a lead whose last attempt ran fine reported
	 * "action complete via notifybay_fallback" as its error -- a success string
	 * in a field named last_error, on the row a model reads first and then
	 * feeds to an irreversible resend.
	 *
	 * The narrower query is also the honest one. Action Scheduler judges
	 * whether the action RAN, not whether the mail arrived: Worker catches its
	 * own send failure and schedules a backoff, so the action completes and AS
	 * logs nothing wrong. A per-lead delivery reason is therefore usually
	 * absent, and the description says so rather than implying otherwise.
	 *
	 * Both arg spellings are matched. Action Scheduler stores the same
	 * parameter as `[1219,"waitlist_restock"]` from one call path and
	 * `["1219","waitlist_restock"]` from another, so matching one spelling
	 * silently finds nothing for half the actions. The trailing comma anchors
	 * the id so lead 12 does not match lead 123.
	 *
	 * @since 1.0.3
	 * @param int $lead_id Lead id.
	 * @return string|null Null when no attempt was ever logged.
	 */
	private static function last_action_error( int $lead_id ) {

		global $wpdb;

		$actions = $wpdb->prefix . 'actionscheduler_actions';
		$logs    = $wpdb->prefix . 'actionscheduler_logs';

		if ( ! $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $actions ) ) ) ) { // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Action Scheduler tables; a direct, uncached read is intentional for this real-time report.
			return null;
		}

		$message = $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Action Scheduler tables; a direct, uncached read is intentional for this real-time report.
			$wpdb->prepare(
				'SELECT lg.message
				 FROM %i lg
				 INNER JOIN %i a ON a.action_id = lg.action_id
				 WHERE a.hook = %s AND a.status = %s AND ( a.args LIKE %s OR a.args LIKE %s )
				 ORDER BY lg.log_id DESC
				 LIMIT 1',
				$logs,
				$actions,
				'notifybay_send_email_worker',
				'failed',
				'[' . $lead_id . ',%',
				'["' . $lead_id . '",%'
			)
		);

		// Null, never '', so a caller can tell "nothing was ever logged for
		// this lead" from "the log line was empty".
		return ( null === $message || '' === $message ) ? null : (string) $message;
	}

	/**
	 * Failed background jobs in the notifybay_alerts group, grouped by hook.
	 *
	 * @since 1.0.3
	 * @return array
	 */
	private static function failed_jobs(): array {

		global $wpdb;

		$actions = $wpdb->prefix . 'actionscheduler_actions';
		$groups  = $wpdb->prefix . 'actionscheduler_groups';
		$logs    = $wpdb->prefix . 'actionscheduler_logs';

		if ( ! $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $actions ) ) ) ) { // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Action Scheduler tables; a direct, uncached read is intentional for this real-time report.
			return array();
		}

		$rows = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Action Scheduler tables; a direct, uncached read is intentional for this real-time report.
			$wpdb->prepare(
				'SELECT a.hook, COUNT(*) AS failures, MAX(a.action_id) AS latest
				 FROM %i a
				 INNER JOIN %i g ON a.group_id = g.group_id
				 WHERE g.slug = %s AND a.status = %s
				 GROUP BY a.hook
				 ORDER BY failures DESC
				 LIMIT 20',
				$actions,
				$groups,
				'notifybay_alerts',
				'failed'
			)
		);

		$out = array();

		foreach ( (array) $rows as $row ) {
			$message = $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Action Scheduler tables; a direct, uncached read is intentional for this real-time report.
				$wpdb->prepare( 'SELECT message FROM %i WHERE action_id = %d ORDER BY log_id DESC LIMIT 1', $logs, (int) $row->latest )
			);

			$out[] = array(
				'hook'         => (string) $row->hook,
				'failures'     => (int) $row->failures,
				'last_message' => ( null === $message || '' === $message ) ? null : (string) $message,
			);
		}

		return $out;
	}

	/**
	 * Waitlist size per product, with a per-status breakdown.
	 *
	 * @since 1.0.3
	 * @param array $input Validated tool input.
	 * @return array
	 */
	public static function product_summary( array $input ): array {

		global $wpdb;

		$limit = isset( $input['limit'] ) ? absint( $input['limit'] ) : 10;
		$limit = max( 1, min( 50, $limit ) );

		$table = $wpdb->prefix . 'notifybay_leads';

		/*
		 * Grouped by product_id only, so a variable product is reported as one
		 * waitlist rather than one per variation. That is the question people
		 * actually ask ("which product has the longest waitlist"), and the
		 * variation split is still reachable through list-leads.
		 */
		$rows = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom {$wpdb->prefix}notifybay_leads table; a direct, uncached aggregate is intentional for this real-time report.
			$wpdb->prepare(
				'SELECT product_id, status, COUNT(*) AS c, MAX(product_name_snapshot) AS name
				 FROM %i
				 GROUP BY product_id, status',
				$table
			)
		);

		$products = array();

		foreach ( (array) $rows as $row ) {
			$pid = (int) $row->product_id;

			if ( ! isset( $products[ $pid ] ) ) {
				$products[ $pid ] = array(
					'product_id'   => $pid,
					'product_name' => (string) $row->name,
					'total'        => 0,
					'by_status'    => array_fill_keys( self::STATUSES, 0 ),
				);
			}

			$products[ $pid ]['total'] += (int) $row->c;

			// A status not in STATUSES would be data this build does not know
			// about; counted in the total, and surfaced rather than dropped.
			$products[ $pid ]['by_status'][ (string) $row->status ] = (int) $row->c;
		}

		$total_products = count( $products );

		usort(
			$products,
			static function ( array $a, array $b ) {
				return $b['total'] <=> $a['total'];
			}
		);

		$products = array_slice( $products, 0, $limit );

		foreach ( $products as $i => $product ) {
			$live = function_exists( 'wc_get_product' ) ? wc_get_product( $product['product_id'] ) : null;

			// The snapshot is what the row was called when somebody signed up;
			// the live product is authoritative when it still exists.
			if ( $live ) {
				$products[ $i ]['product_name'] = $live->get_name();
				$products[ $i ]['in_stock']     = (bool) $live->is_in_stock();
			} else {
				$products[ $i ]['in_stock'] = null;
			}
		}

		return array(
			'products' => array_values( $products ),
			'returned' => count( $products ),
			// Without this, `returned == limit` is ambiguous: a caller cannot
			// tell a complete list from one truncated by the limit.
			'total'    => $total_products,
		);
	}

	/**
	 * Why this lead cannot be re-sent a back-in-stock notification, if it cannot.
	 *
	 * Shared deliberately with notification_failures(), which lists `failed`
	 * leads and so lists these too. Reporting a lead as a failure to fix and
	 * then refusing to act on it is the same defect as reporting a send as
	 * queued when the email is switched off: the caller is told something is
	 * actionable when it is not. One function decides it for both, so the list
	 * and the tool cannot drift apart.
	 *
	 * Reads only columns Lead::find() and Model::paginate() already select
	 * (both are `SELECT *`), so calling it per row adds no queries.
	 *
	 * Status is not checked here. resend_notifications() tests that first and
	 * reports it separately as wrong_status, and every lead
	 * notification_failures() lists is `failed` by definition.
	 *
	 * @since 1.0.3
	 * @param Lead $lead The lead.
	 * @return string|null Machine-readable reason, or null when it can be sent.
	 */
	private static function resend_refusal_reason( Lead $lead ) {
		/*
		 * This tool only ever enqueues `waitlist_restock`. A wishlist lead
		 * belongs to NotifyBay Pro's price-drop flow, so queuing it here mails
		 * a "back in stock" notice for an item that was never out of stock.
		 * Free validates every subscribe against `notifybay_allowed_lead_types`,
		 * which defaults to `waitlist`, so on a Free-only store this refuses
		 * nothing.
		 */
		if ( 'waitlist' !== (string) $lead->type ) {
			return 'not_a_waitlist_lead';
		}

		/*
		 * Only the notification dispatchers stamp `last_batch_id`, never the
		 * verification send, and Engine\Worker::handle_failure() sets `failed`
		 * without touching it. So `failed` with an empty batch id means the
		 * *verification* email is what failed -- that customer never confirmed
		 * the subscription at all. Mailing them a restock notice is the first
		 * harm. The second is that claiming the lead would stamp a token over
		 * the empty value, and Pro's Api\LeadsController::resend() reads
		 * exactly that emptiness to decide whether to re-send the
		 * verification; nothing clears the column afterwards, so the lead
		 * could never again be recovered from Pro's admin UI.
		 */
		if ( 'failed' === (string) $lead->status && empty( $lead->last_batch_id ) ) {
			return 'verification_never_confirmed';
		}

		return null;
	}

	/**
	 * Re-send the back-in-stock notification for specific leads.
	 *
	 * @since 1.0.3
	 * @param array $input Validated tool input.
	 * @return array|WP_Error
	 */
	public static function resend_notifications( array $input ) {

		$ids = isset( $input['ids'] ) && is_array( $input['ids'] ) ? $input['ids'] : array();
		$ids = array_values( array_unique( array_filter( array_map( 'absint', $ids ) ) ) );

		if ( empty( $ids ) ) {
			return new WP_Error(
				'notifybay_no_ids',
				__( 'No lead ids were supplied.', 'notifybay-waitlist-and-stock-alert-woo' ),
				array( 'status' => 400 )
			);
		}

		if ( ! function_exists( 'as_enqueue_async_action' ) ) {
			return new WP_Error(
				'notifybay_no_scheduler',
				__( 'Action Scheduler is not available, so nothing can be queued.', 'notifybay-waitlist-and-stock-alert-woo' ),
				array( 'status' => 503 )
			);
		}

		/*
		 * The WooCommerce "Enable this email" toggle is the final say at send
		 * time: Engine\Worker returns without sending when it is off, leaving
		 * the lead parked. Queueing anyway reported every id as `queued` --
		 * true in the narrow sense that a job was scheduled, and useless to the
		 * person reading it, since no mail could ever leave. Refuse instead,
		 * and name the screen that fixes it.
		 *
		 * Only when the email object actually exists: get_email() returns null
		 * before WooCommerce's mailer is up, and the Worker's legacy
		 * TemplateRenderer path has no toggle to consult, so a null here is not
		 * evidence of anything being disabled.
		 */
		$restock_email = \NotifyBay\Emails\EmailManager::get_email( 'notifybay_restock' );

		if ( $restock_email && ! $restock_email->is_enabled() ) {
			return new WP_Error(
				'notifybay_email_disabled',
				__( 'The back-in-stock email is switched off in WooCommerce, so nothing would be sent. Enable it under WooCommerce > Settings > Emails, then try again.', 'notifybay-waitlist-and-stock-alert-woo' ),
				array( 'status' => 409 )
			);
		}

		$queued         = array();
		$not_found      = array();
		$wrong_status   = array();
		$not_resendable = array();
		$out_of_stock   = array();
		$failed         = array();

		foreach ( $ids as $id ) {
			$lead = Lead::find( $id );

			if ( null === $lead ) {
				$not_found[] = $id;
				continue;
			}

			/*
			 * Eligibility is the key set of Lead::RESEND_CLAIM_TOKENS, and that
			 * is not a coincidence to be tidied away later: parking a lead in
			 * `processing` is only safe if the stale-lead reaper can be told
			 * where to put it back, so a status with no claim token is a status
			 * this tool must not claim. In practice that is `failed` (the
			 * engine gave up after four attempts) and `notified` (it went out
			 * and somebody wants it sent again). An `active` lead has not been
			 * dispatched to yet and belongs to the stock engine, and re-mailing
			 * an `unsubscribed` or `converted` customer is the kind of thing an
			 * assistant should not be able to do by passing an id.
			 *
			 * Status alone is not sufficient, though -- see the guard directly
			 * below.
			 */
			$prior_status = (string) $lead->status;

			if ( ! isset( Lead::RESEND_CLAIM_TOKENS[ $prior_status ] ) ) {
				$wrong_status[] = array(
					'id'     => $id,
					'status' => $prior_status,
				);
				continue;
			}

			$reason = self::resend_refusal_reason( $lead );

			if ( null !== $reason ) {
				$not_resendable[] = array(
					'id'     => $id,
					'reason' => $reason,
				);
				continue;
			}

			/*
			 * A back-in-stock email is only true if the product is back in
			 * stock. Re-sending one for a product that has since sold out
			 * tells a real customer something false, on an assistant's say-so,
			 * and nothing downstream would catch it -- the engine trusts that
			 * whoever queued the job had a reason.
			 */
			$product_id = $lead->variation_id ? (int) $lead->variation_id : (int) $lead->product_id;
			$product    = function_exists( 'wc_get_product' ) ? wc_get_product( $product_id ) : null;

			if ( ! $product || ! $product->is_in_stock() ) {
				$out_of_stock[] = array(
					'id'           => $id,
					'product_id'   => (int) $lead->product_id,
					'product_name' => $product ? $product->get_name() : (string) $lead->product_name_snapshot,
					'reason'       => $product ? 'out_of_stock' : 'product_missing',
				);
				continue;
			}

			/*
			 * retry_count is reset because handle_failure() increments and
			 * gives up after the fourth failure. A failed lead is already at
			 * the ceiling, so
			 * without this a resend gets one attempt and no backoff before
			 * failing again -- which looks like the resend itself not working.
			 *
			 * The status must become `processing`: Worker::handle() returns
			 * early for any non-verification email whose lead is not in that
			 * state, so queuing without it silently sends nothing.
			 *
			 * `processing` is a claim, though, and Action Scheduler is not
			 * guaranteed to honour it -- on a WP-Cron-only store the queue
			 * routinely takes longer than the fifteen minutes after which
			 * CronTasks::recover_stale_processing_leads() reclaims the lead.
			 * The claim token records where the lead came from so that reaper
			 * can put it back there instead of onto the live waitlist, which is
			 * what used to happen and what earned an already-notified customer
			 * a second copy at the next restock.
			 *
			 * This is not pure upside, and Engine\Worker's comment about the
			 * reaper returning a lead to `active` so the subscription survives
			 * does not hold for leads claimed here. A reclaimed `resend:failed`
			 * now stays `failed` instead of rejoining the live waitlist, so a
			 * customer who is genuinely owed an email no longer gets one by
			 * accident at the next restock. That path was the double-send bug
			 * wearing a friendly face; the lead stays visible to
			 * notification_failures() and to Pro's resend screen, and a human
			 * decides.
			 */
			$lead->retry_count   = 0;
			$lead->status        = 'processing';
			$lead->last_batch_id = Lead::RESEND_CLAIM_TOKENS[ $prior_status ];

			if ( ! $lead->save() ) {
				/*
				 * Reported separately, not as a wrong_status row. A write
				 * failure is an infrastructure error, not a lead state --
				 * putting it in wrong_status made `status` return
				 * 'save_failed', a value that is not a NotifyBay status and
				 * is absent from the documented enum, so a client mapping
				 * that field onto the enum reads a state that cannot exist.
				 * `failed` is also what delete-leads already calls this.
				 */
				$failed[] = $id;
				continue;
			}

			as_enqueue_async_action( 'notifybay_send_email_worker', array( (int) $lead->id, 'waitlist_restock' ), 'notifybay_alerts' );

			$queued[] = $id;
		}

		return array(
			'queued'         => $queued,
			'not_found'      => $not_found,
			'wrong_status'   => $wrong_status,
			'not_resendable' => $not_resendable,
			'out_of_stock'   => $out_of_stock,
			'failed'         => $failed,
			'affected'       => $queued,
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
			'notified_at'  => self::date_or_null( $lead->notified_at ),
			'expires_at'   => self::date_or_null( $lead->expires_at ),
		);
	}
}
