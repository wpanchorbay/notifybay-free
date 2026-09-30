<?php
/**
 * Lead Model.
 *
 * @package    NotifyBay
 * @subpackage Models
 * @since      1.0.0
 * @author     WPAnchorBay <sankarsan@wpanchorbay.com>
 */

namespace NotifyBay\Models;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Lead
 */
class Lead extends Model {

	/**
	 * The table associated with the model (without prefix).
	 *
	 * @var string
	 */
	protected $table = 'notifybay_leads';

	/**
	 * Columns callers may filter/search on via paginate().
	 *
	 * @var string[]
	 */
	protected $queryable_columns = array( 'user_email', 'user_id', 'product_id', 'variation_id', 'type', 'status' );

	/**
	 * Columns paginate() may order by. See Model::$sortable_columns.
	 *
	 * @var string[]
	 */
	protected $sortable_columns = array( 'created_at', 'updated_at', 'notified_at' );

	/**
	 * Claim tokens written to `last_batch_id` by Mcp\Tools\Leads::
	 * resend_notifications(), keyed by the status the lead was taken from.
	 *
	 * The key set is also that tool's eligibility test, and that is not a
	 * coincidence to be tidied away: parking a lead in `processing` is only
	 * safe if Engine\CronTasks::recover_stale_processing_leads() can be told
	 * where to put it back, so a status with no claim token is a status the
	 * tool must not claim.
	 *
	 * INVARIANT: every value here must appear as a key of
	 * CLAIM_RESTORE_TOKENS. A token the reaper cannot resolve falls through to
	 * `active`, which for a `notified` or `failed` lead is the demotion this
	 * whole mechanism exists to prevent.
	 *
	 * @since 1.0.3
	 * @var array<string,string>
	 */
	public const RESEND_CLAIM_TOKENS = array(
		'notified' => 'resend:notified',
		'failed'   => 'resend:failed',
	);

	/**
	 * Every claim token this plugin family understands, mapped to the status
	 * an abandoned claim carrying it must be restored to.
	 *
	 * Engine\Dispatcher needs no token: its UPDATE carries
	 * `AND status = 'active'`, so `active` is provably where its claims came
	 * from, and Engine\CronTasks::recover_stale_processing_leads() falls back
	 * to that for any unrecognised value -- a real uuid4 batch id, NULL, or
	 * something unexpected. A claimer needs an entry here only when it takes
	 * leads from somewhere else:
	 *
	 *   - resend_notifications() claims `notified` and `failed` leads. Without
	 *     a token, a resend whose job never drained handed a finished lead
	 *     back to the live waitlist and the next restock mailed that customer
	 *     a second copy.
	 *   - NotifyBay Pro's Engine\ProDispatcher hurry-alert claims `notified`
	 *     waitlist leads on a daily schedule, with no status guard, and Pro
	 *     ships no reaper of its own. Same demotion, reached far more often
	 *     than the resend path. Its wishlist branch claims from `active` and
	 *     so is deliberately left on a plain uuid4 -- a token whose restore
	 *     target is already the fallback would only make this map bigger than
	 *     the problem.
	 *
	 * Two claimers restoring to the same status (`resend:notified` and
	 * `hurry:notified` both to `notified`) is why this is keyed by token
	 * rather than by status; RESEND_CLAIM_TOKENS keeps the status-keyed view
	 * its own tool needs.
	 *
	 * A uuid4 batch id cannot collide with any of these: uuid4 contains no
	 * colon. The tokens are also deliberately non-empty, because Pro's
	 * Api\LeadsController::resend() reads an *empty* `last_batch_id` on a
	 * `failed` lead as "this lead's last email was a verification, not a
	 * notification", and resets it to `pending_verification` on that basis.
	 * Stamping a token over an empty value would destroy that discriminator
	 * permanently -- nothing clears this column except the next dispatch -- so
	 * resend_notifications() refuses a `failed` lead that has no batch id
	 * rather than claiming it. On the leads it does claim the batch id was
	 * already non-empty and the claim really is a notification attempt, so the
	 * token is the truthful value there; that is why the reaper restores the
	 * status but leaves the token in place rather than clearing it.
	 *
	 * Old and new versions of the two plugins mix safely in both directions.
	 * A token this map does not know falls through to `active`, which is
	 * exactly how the reaper behaved before any of them existed.
	 *
	 * @since 1.0.3
	 * @var array<string,string>
	 */
	public const CLAIM_RESTORE_TOKENS = array(
		'resend:notified' => 'notified',
		'resend:failed'   => 'failed',
		'hurry:notified'  => 'notified',
	);

	/**
	 * Perform an Insert OR Update if duplicate key exists.
	 * This is essential for Smart Transition and preventing duplicate active leads.
	 *
	 * @param array $attributes The attributes to insert or update.
	 * @return bool True on success, false on failure.
	 */
	public static function upsert_lead( array $attributes ) {
		global $wpdb;
		$instance = new static();
		$table    = $instance->get_table();

		// Set timestamps
		$now = current_time( 'mysql' );
		if ( ! isset( $attributes['created_at'] ) ) {
			$attributes['created_at'] = $now;
		}
		$attributes['updated_at'] = $now;

		// Build the column list, value placeholders and bound values. Column names
		// are emitted as %i identifier placeholders and values as %s/%d/%f, so
		// every field name and value is bound by prepare() — none is interpolated
		// into the SQL. A NULL value emits the literal `NULL` and consumes no arg.
		$field_names        = array(); // Column identifiers (bound as %i).
		$value_placeholders = array(); // %d/%f/%s tokens or the literal NULL.
		$value_args         = array(); // Bound values, non-NULL only.

		foreach ( $attributes as $field => $value ) {
			$field_names[] = $field;

			if ( is_null( $value ) ) {
				$value_placeholders[] = 'NULL';
			} elseif ( is_int( $value ) ) {
				$value_placeholders[] = '%d';
				$value_args[]         = $value;
			} elseif ( is_float( $value ) ) {
				$value_placeholders[] = '%f';
				$value_args[]         = $value;
			} else {
				$value_placeholders[] = '%s';
				$value_args[]         = $value;
			}
		}

		// One %i identifier placeholder per column, in the same order.
		$columns_sql = implode( ', ', array_fill( 0, count( $field_names ), '%i' ) );
		$values_sql  = implode( ', ', $value_placeholders );

		// UPDATE clauses reference the inserted values via VALUES(). Each column is
		// emitted twice as a %i identifier. created_at is skipped so an existing
		// lead keeps its original creation time.
		$update_fragments = array();
		$update_args      = array();
		foreach ( $field_names as $field ) {
			if ( 'created_at' === $field ) {
				continue;
			}
			$update_fragments[] = '%i = VALUES(%i)';
			$update_args[]      = $field;
			$update_args[]      = $field;
		}
		$update_sql = implode( ', ', $update_fragments );

		// Arg order matches placeholder order: table, all column names, non-NULL
		// values, then the UPDATE column pairs.
		$sql  = "INSERT INTO %i ({$columns_sql}) VALUES ({$values_sql}) ON DUPLICATE KEY UPDATE {$update_sql}";
		$args = array_merge( array( $table ), $field_names, $value_args, $update_args );

		// $sql is assembled from %i/%s/%d placeholder fragments for a variable column
		// set; every table, column and value is bound in $args, so the query is fully
		// prepared even though the placeholder count is dynamic and PCP's taint
		// heuristic cannot verify it statically.
		$result = $wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Custom {$wpdb->prefix}notifybay_leads table; dynamic column set fully bound via %i/prepare (see note above). Direct, uncached write.
			$wpdb->prepare( $sql, $args ) // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- Fully bound; see note above.
		);

		return false !== $result;
	}

	/**
	 * Check if a user is already subscribed to a product.
	 *
	 * @param string $email        User email.
	 * @param int    $product_id   Product ID.
	 * @param int    $variation_id Variation ID (optional).
	 * @param string $type         Subscription type (e.g. waitlist).
	 * @return bool
	 */
	public static function is_subscribed( $email, $product_id, $variation_id = 0, $type = 'waitlist' ) {
		global $wpdb;
		$instance = new static();
		$table    = $instance->get_table();

		// Waitlist leads can be active or awaiting verification; other types must be
		// active. Each branch passes a fully-literal query to prepare() so the
		// status set is never an interpolated fragment.
		if ( 'waitlist' === $type ) {
			$result = $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom {$wpdb->prefix}notifybay_leads table; a direct, uncached read is intentional for this real-time data-access layer.
				$wpdb->prepare(
					"SELECT id FROM %i WHERE user_email = %s AND product_id = %d AND variation_id = %d AND type = %s AND status IN ('active', 'pending_verification')",
					$table,
					$email,
					(int) $product_id,
					(int) $variation_id,
					$type
				)
			);
		} else {
			$result = $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom {$wpdb->prefix}notifybay_leads table; a direct, uncached read is intentional for this real-time data-access layer.
				$wpdb->prepare(
					"SELECT id FROM %i WHERE user_email = %s AND product_id = %d AND variation_id = %d AND type = %s AND status = 'active'",
					$table,
					$email,
					(int) $product_id,
					(int) $variation_id,
					$type
				)
			);
		}

		return (bool) $result;
	}

	/**
	 * Get the count of active waitlist leads for a product/variation.
	 *
	 * @param int $product_id   Product ID.
	 * @param int $variation_id Variation ID.
	 * @return int
	 */
	public static function get_lead_count( $product_id, $variation_id = 0 ) {
		global $wpdb;
		$instance = new static();
		$table    = $instance->get_table();

		return (int) $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom {$wpdb->prefix}notifybay_leads table; a direct, uncached read is intentional for this real-time data-access layer.
			$wpdb->prepare(
				"SELECT COUNT(*) FROM %i WHERE product_id = %d AND variation_id = %d AND status = 'active' AND type = 'waitlist'",
				$table,
				(int) $product_id,
				(int) $variation_id
			)
		);
	}

	/**
	 * Get total active waitlist count for a product (all variants combined).
	 *
	 * @param int $product_id Product ID.
	 * @return int
	 */
	public static function get_total_lead_count( $product_id ) {
		global $wpdb;
		$instance = new static();
		$table    = $instance->get_table();

		return (int) $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom {$wpdb->prefix}notifybay_leads table; a direct, uncached read is intentional for this real-time data-access layer.
			$wpdb->prepare(
				"SELECT COUNT(*) FROM %i WHERE product_id = %d AND status = 'active' AND type = 'waitlist'",
				$table,
				(int) $product_id
			)
		);
	}

	/**
	 * Get active waitlist counts for all variations of a product.
	 *
	 * @param int $product_id Product ID.
	 * @return array Map of [variation_id => count]
	 */
	public static function get_variation_lead_counts( $product_id ) {
		global $wpdb;
		$instance = new static();
		$table    = $instance->get_table();

		$results = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom {$wpdb->prefix}notifybay_leads table; a direct, uncached read is intentional for this real-time data-access layer.
			$wpdb->prepare(
				"SELECT variation_id, COUNT(*) as count FROM %i WHERE product_id = %d AND status = 'active' AND type = 'waitlist' GROUP BY variation_id",
				$table,
				(int) $product_id
			)
		);

		$map = array();
		if ( $results ) {
			foreach ( $results as $row ) {
				$map[ (int) $row->variation_id ] = (int) $row->count;
			}
		}

		return $map;
	}

	/**
	 * Count waitlist leads for a product/variation matching a set of statuses,
	 * notified at or after a cutoff date. Used by the Fair-Play reservation
	 * calculation (`notifybay_waitlist_notify_limit` filter) to determine how
	 * much stock is already reserved for previously-notified leads, without the
	 * caller needing to run raw SQL against this table directly.
	 *
	 * @param int      $product_id   Product ID.
	 * @param int      $variation_id Variation ID (0 for simple products).
	 * @param string   $cutoff_date  MySQL datetime; only leads notified at/after this are counted.
	 * @param string[] $statuses     Lead statuses to include (e.g. ['notified']).
	 * @return int
	 */
	public static function count_reserved( $product_id, $variation_id, $cutoff_date, array $statuses = array( 'notified' ) ) {
		global $wpdb;
		$instance = new static();
		$table    = $instance->get_table();

		if ( empty( $statuses ) ) {
			return 0;
		}

		$placeholders = implode( ',', array_fill( 0, count( $statuses ), '%s' ) );
		$args         = array_merge( array( $table, (int) $product_id, (int) $variation_id ), $statuses, array( $cutoff_date ) );

		return (int) $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom {$wpdb->prefix}notifybay_leads table; a direct, uncached read is intentional for this real-time data-access layer.
			// phpcs:ignore WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- $placeholders expands to a variable number of %s at runtime (one per $statuses element), which the sniff can't evaluate statically; args count always matches.
			$wpdb->prepare(
				"SELECT COUNT(*) FROM %i WHERE product_id = %d AND variation_id = %d AND type = 'waitlist' AND status IN ({$placeholders}) AND notified_at >= %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $placeholders is a literal list of %s placeholders; the table and all values are bound.
				...$args
			)
		);
	}

	/**
	 * Whether anyone is currently waiting to be notified about a product.
	 *
	 * Deliberately mirrors the selection predicate in Engine\Dispatcher so the
	 * two can never disagree about who counts as waiting: an unexpired, active
	 * waitlist lead. Callers use this to avoid queueing dispatch work for
	 * products nobody has subscribed to — on a large catalogue, a bulk stock
	 * write would otherwise schedule one job per product.
	 *
	 * Backed by idx_product_variation_status.
	 *
	 * @since 1.0.3
	 * @param int $product_id   Product ID.
	 * @param int $variation_id Variation ID (0 for simple products).
	 * @return bool
	 */
	public static function has_active_waitlist( $product_id, $variation_id = 0 ) {
		global $wpdb;
		$instance = new static();
		$table    = $instance->get_table();

		return (bool) $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom {$wpdb->prefix}notifybay_leads table; a direct, uncached read is intentional for this real-time data-access layer.
			$wpdb->prepare(
				"SELECT 1 FROM %i WHERE product_id = %d AND variation_id = %d AND type = 'waitlist' AND status = 'active' AND (expires_at IS NULL OR expires_at = '0000-00-00 00:00:00' OR expires_at > %s) LIMIT 1",
				$table,
				(int) $product_id,
				(int) $variation_id,
				current_time( 'mysql' )
			)
		);
	}

	/**
	 * Get all active/pending subscriptions for a user for a specific product (including variations).
	 *
	 * @param string $email       User email.
	 * @param int    $product_id  Product ID.
	 * @param string $guest_token Optional guest ownership token. When non-empty,
	 *                            results are scoped to leads carrying this token,
	 *                            so a guest only sees subscriptions its own browser
	 *                            created. Empty for logged-in/internal callers.
	 * @return array Map of [variation_id => [type => true]]
	 */
	public static function get_user_subscriptions_for_product( $email, $product_id, $guest_token = '' ) {
		global $wpdb;
		$instance = new static();
		$table    = $instance->get_table();

		if ( '' !== (string) $guest_token ) {
			$results = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom {$wpdb->prefix}notifybay_leads table; a direct, uncached read is intentional for this real-time data-access layer.
				$wpdb->prepare(
					"SELECT variation_id, type FROM %i WHERE user_email = %s AND product_id = %d AND guest_token = %s AND status IN ('active', 'pending_verification')",
					$table,
					$email,
					(int) $product_id,
					(string) $guest_token
				),
				ARRAY_A
			);
		} else {
			$results = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom {$wpdb->prefix}notifybay_leads table; a direct, uncached read is intentional for this real-time data-access layer.
				$wpdb->prepare(
					"SELECT variation_id, type FROM %i WHERE user_email = %s AND product_id = %d AND status IN ('active', 'pending_verification')",
					$table,
					$email,
					(int) $product_id
				),
				ARRAY_A
			);
		}

		$map = array();
		foreach ( $results as $row ) {
			$vid  = (int) $row['variation_id'];
			$type = $row['type'];
			if ( ! isset( $map[ $vid ] ) ) {
				$map[ $vid ] = array();
			}
			$map[ $vid ][ $type ] = true;
		}
		return $map;
	}

	/**
	 * Batch-fetch subscriptions for multiple products at once.
	 * Used on archive/shop pages to reduce N queries to 1.
	 *
	 * @param string $email       User email.
	 * @param int[]  $product_ids Array of product IDs.
	 * @param string $guest_token Optional guest ownership token. When non-empty,
	 *                            results are scoped to leads carrying this token,
	 *                            so a guest only sees subscriptions its own browser
	 *                            created. Empty for logged-in/internal callers.
	 * @return array Map of [product_id => [variation_id => [type => true]]]
	 */
	public static function get_user_subscriptions_for_products( $email, array $product_ids, $guest_token = '' ) {
		if ( empty( $product_ids ) ) {
			return array();
		}

		global $wpdb;
		$instance = new static();
		$table    = $instance->get_table();

		// One %d placeholder per product id for the IN() list. The optional guest
		// token lives in two fully-literal query variants (not a concatenated
		// fragment) so only the array_fill()-built %d list is ever interpolated.
		$placeholders = implode( ',', array_fill( 0, count( $product_ids ), '%d' ) );
		$product_args = array_map( 'intval', $product_ids );

		if ( '' !== (string) $guest_token ) {
			$args    = array_merge( array( $table, $email ), $product_args, array( (string) $guest_token ) );
			$results = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom {$wpdb->prefix}notifybay_leads table; a direct, uncached read is intentional for this real-time data-access layer.
				$wpdb->prepare( // phpcs:ignore WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- The IN() list is a runtime-sized %d set; the arg count always matches.
					"SELECT product_id, variation_id, type FROM %i WHERE user_email = %s AND product_id IN ($placeholders) AND guest_token = %s AND status IN ('active', 'pending_verification')", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $placeholders is an array_fill()-built %d list; the table and all values are bound.
					...$args
				),
				ARRAY_A
			);
		} else {
			$args    = array_merge( array( $table, $email ), $product_args );
			$results = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom {$wpdb->prefix}notifybay_leads table; a direct, uncached read is intentional for this real-time data-access layer.
				$wpdb->prepare( // phpcs:ignore WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- The IN() list is a runtime-sized %d set; the arg count always matches.
					"SELECT product_id, variation_id, type FROM %i WHERE user_email = %s AND product_id IN ($placeholders) AND status IN ('active', 'pending_verification')", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $placeholders is an array_fill()-built %d list; the table and all values are bound.
					...$args
				),
				ARRAY_A
			);
		}

		$map = array();
		foreach ( $results as $row ) {
			$pid  = (int) $row['product_id'];
			$vid  = (int) $row['variation_id'];
			$type = $row['type'];
			if ( ! isset( $map[ $pid ] ) ) {
				$map[ $pid ] = array();
			}
			if ( ! isset( $map[ $pid ][ $vid ] ) ) {
				$map[ $pid ][ $vid ] = array();
			}
			$map[ $pid ][ $vid ][ $type ] = true;
		}
		return $map;
	}

	/**
	 * Get all active/pending leads for a specific user filtered by type.
	 *
	 * @param string $email User email.
	 * @param string $type  The lead type to filter by (e.g. 'waitlist').
	 * @return array List of lead objects.
	 */
	public static function get_user_leads_by_type( $email, $type ) {
		global $wpdb;
		$instance = new static();
		$table    = $instance->get_table();

		return $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom {$wpdb->prefix}notifybay_leads table; a direct, uncached read is intentional for this real-time data-access layer.
			$wpdb->prepare(
				"SELECT * FROM %i WHERE user_email = %s AND type = %s AND status IN ('active', 'pending_verification') ORDER BY created_at DESC",
				$table,
				$email,
				$type
			),
			ARRAY_A
		);
	}

	/**
	 * Mark the lead as notified.
	 *
	 * @return bool
	 */
	public function mark_notified() {
		$this->status      = 'notified';
		$this->notified_at = current_time( 'mysql' );
		$this->updated_at  = current_time( 'mysql' );
		return $this->save();
	}

	/**
	 * Check if the lead is expired.
	 *
	 * Compared in the site's local clock, because that is the clock the column
	 * is written in (Api\FrontendController::subscribe()). This previously used
	 * strtotime()/time(), i.e. UTC -- accidentally correct only while the column
	 * was written with gmdate(), and wrong by gmt_offset once it no longer was.
	 * On a store behind UTC that reported a lead expired hours early and then
	 * *persisted* the mistake through save(). Comparing the two 'Y-m-d H:i:s'
	 * strings directly is both correct and exactly what the SQL readers do.
	 *
	 * The zero-date exclusion keeps this in step with the column's other
	 * readers -- Engine\Dispatcher, Engine\CronTasks::cleanup_expired_leads()
	 * and self::has_active_waitlist() all treat '0000-00-00 00:00:00' as
	 * "no expiry".
	 *
	 * @return bool
	 */
	public function is_expired() {
		if ( 'expired' === $this->status ) {
			return true;
		}

		// Read through __get() into a local before testing. empty() on a magic
		// property dispatches to __isset(), so testing $this->expires_at
		// directly depended on Model::__isset() existing -- which it did not
		// until this cycle, making this whole method dead.
		$expires_at = $this->expires_at;

		if ( null === $expires_at || '' === $expires_at || '0000-00-00 00:00:00' === $expires_at ) {
			return false;
		}

		$now_mysql = current_time( 'mysql' );

		if ( $expires_at <= $now_mysql ) {
			$this->status     = 'expired';
			$this->updated_at = $now_mysql;
			$this->save();
			return true;
		}

		return false;
	}
}
