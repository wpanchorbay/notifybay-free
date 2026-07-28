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
	 * @return bool
	 */
	public function is_expired() {
		if ( 'expired' === $this->status ) {
			return true;
		}

		if ( ! empty( $this->expires_at ) ) {
			$expiry_time = strtotime( $this->expires_at );
			if ( $expiry_time && $expiry_time < time() ) {
				$this->status     = 'expired';
				$this->updated_at = current_time( 'mysql' );
				$this->save();
				return true;
			}
		}

		return false;
	}
}
