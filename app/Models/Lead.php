<?php
/**
 * Lead Model.
 *
 * @package    NotifyBay
 * @subpackage Models
 * @since      1.0.0
 * @author     WPAnchorBay <sankarsan@gmail.com>
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

		// Build field names and placeholders
		$fields       = array();
		$placeholders = array();
		$values       = array();

		foreach ( $attributes as $field => $value ) {
			$fields[] = "`$field`";

			if ( is_null( $value ) ) {
				$placeholders[] = 'NULL';
			} else {
				if ( is_int( $value ) ) {
					$placeholders[] = '%d';
				} elseif ( is_float( $value ) ) {
					$placeholders[] = '%f';
				} else {
					$placeholders[] = '%s';
				}
				$values[] = $value;
			}
		}

		$fields_sql       = implode( ', ', $fields );
		$placeholders_sql = implode( ', ', $placeholders );

		// Prepare UPDATE clauses (using VALUES() to refer to the inserted values)
		$update_clauses = array();
		foreach ( $attributes as $field => $value ) {
			if ( 'created_at' === $field ) {
				continue; // Don't overwrite created_at if the lead already existed
			}
			$update_clauses[] = "`$field` = VALUES(`$field`)";
		}
		$update_sql = implode( ', ', $update_clauses );

		$sql = "INSERT INTO {$table} ({$fields_sql}) VALUES ({$placeholders_sql}) ON DUPLICATE KEY UPDATE {$update_sql}";

		$prepared_sql = $wpdb->prepare( $sql, $values ); // phpcs:ignore

		$result = $wpdb->query( $prepared_sql ); // phpcs:ignore

		return false !== $result;
	}

	/**
	 * Check if a user is already subscribed to a product.
	 *
	 * @param string $email        User email.
	 * @param int    $product_id   Product ID.
	 * @param int    $variation_id Variation ID (optional).
	 * @param string $type         Subscription type (waitlist/wishlist).
	 * @return bool
	 */
	public static function is_subscribed( $email, $product_id, $variation_id = 0, $type = 'waitlist' ) {
		global $wpdb;
		$instance = new static();
		$table    = $instance->get_table();

		$status_check = ( 'waitlist' === $type ) ? "IN ('active', 'pending_verification')" : "= 'active'";

		$result = $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Custom {$wpdb->prefix}notifybay_leads table; values are bound via prepare(). Direct, uncached queries are intentional for this real-time data-access layer.
			$wpdb->prepare(
				"SELECT id FROM {$table} WHERE user_email = %s AND product_id = %d AND variation_id = %d AND type = %s AND status {$status_check}", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$email,
				(int) $product_id,
				(int) $variation_id,
				$type
			)
		);

		return (bool) $result;
	}

	/**
	 * Get the count of active leads for a product (for FOMO).
	 *
	 * @param int $product_id   Product ID.
	 * @param int $variation_id Variation ID.
	 * @return int
	 */
	public static function get_lead_count( $product_id, $variation_id = 0 ) {
		global $wpdb;
		$instance = new static();
		$table    = $instance->get_table();

		return (int) $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Custom {$wpdb->prefix}notifybay_leads table; values are bound via prepare(). Direct, uncached queries are intentional for this real-time data-access layer.
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$table} WHERE product_id = %d AND variation_id = %d AND status = 'active' AND type = 'waitlist'", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				(int) $product_id,
				(int) $variation_id
			)
		);
	}

	/**
	 * Get total active waitlist count for a product (all variants combined).
	 * Used for variable product FOMO before a variant is selected.
	 *
	 * @param int $product_id Product ID.
	 * @return int
	 */
	public static function get_total_lead_count( $product_id ) {
		global $wpdb;
		$instance = new static();
		$table    = $instance->get_table();

		return (int) $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Custom {$wpdb->prefix}notifybay_leads table; values are bound via prepare(). Direct, uncached queries are intentional for this real-time data-access layer.
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$table} WHERE product_id = %d AND status = 'active' AND type = 'waitlist'", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				(int) $product_id
			)
		);
	}

	/**
	 * Get waitlist counts for all variations of a product.
	 *
	 * @param int $product_id Product ID.
	 * @return array Map of [variation_id => count]
	 */
	public static function get_all_variation_fomo_counts( $product_id ) {
		global $wpdb;
		$instance = new static();
		$table    = $instance->get_table();

		$results = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Custom {$wpdb->prefix}notifybay_leads table; values are bound via prepare(). Direct, uncached queries are intentional for this real-time data-access layer.
			$wpdb->prepare(
				"SELECT variation_id, COUNT(*) as count FROM {$table} WHERE product_id = %d AND status = 'active' AND type = 'waitlist' GROUP BY variation_id", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
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
	 * @param string[] $statuses     Lead statuses to include (e.g. ['notified', 'notified_hurry']).
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
		$args         = array_merge( array( (int) $product_id, (int) $variation_id ), $statuses, array( $cutoff_date ) );

		return (int) $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Custom {$wpdb->prefix}notifybay_leads table; values are bound via prepare(). Direct, uncached queries are intentional for this real-time data-access layer.
			// phpcs:ignore WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- $placeholders expands to a variable number of %s at runtime (one per $statuses element), which the sniff can't evaluate statically; args count always matches.
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$table} WHERE product_id = %d AND variation_id = %d AND type = 'waitlist' AND status IN ({$placeholders}) AND notified_at >= %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				...$args
			)
		);
	}

	/**
	 * Get all active/pending subscriptions for a user for a specific product (including variations).
	 *
	 * @param string $email      User email.
	 * @param int    $product_id Product ID.
	 * @return array Map of [variation_id => [type => true]]
	 */
	public static function get_user_subscriptions_for_product( $email, $product_id ) {
		global $wpdb;
		$instance = new static();
		$table    = $instance->get_table();

		$results = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Custom {$wpdb->prefix}notifybay_leads table; values are bound via prepare(). Direct, uncached queries are intentional for this real-time data-access layer.
			$wpdb->prepare(
				"SELECT variation_id, type FROM {$table} WHERE user_email = %s AND product_id = %d AND status IN ('active', 'pending_verification')", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$email,
				(int) $product_id
			),
			ARRAY_A
		);

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
	 * @return array Map of [product_id => [variation_id => [type => true]]]
	 */
	public static function get_user_subscriptions_for_products( $email, array $product_ids ) {
		if ( empty( $product_ids ) ) {
			return array();
		}

		global $wpdb;
		$instance = new static();
		$table    = $instance->get_table();

		$placeholders = implode( ',', array_fill( 0, count( $product_ids ), '%d' ) );
		$args         = array_merge( array( $email ), array_map( 'intval', $product_ids ) );

		$results = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Custom {$wpdb->prefix}notifybay_leads table; values are bound via prepare(). Direct, uncached queries are intentional for this real-time data-access layer.
			$wpdb->prepare(
				"SELECT product_id, variation_id, type FROM {$table} WHERE user_email = %s AND product_id IN ($placeholders) AND status IN ('active', 'pending_verification')", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				...$args
			),
			ARRAY_A
		);

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
	 * @param string $type  'waitlist' or 'wishlist'.
	 * @return array List of lead objects.
	 */
	public static function get_user_leads_by_type( $email, $type ) {
		global $wpdb;
		$instance = new static();
		$table    = $instance->get_table();

		return $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Custom {$wpdb->prefix}notifybay_leads table; values are bound via prepare(). Direct, uncached queries are intentional for this real-time data-access layer.
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE user_email = %s AND type = %s AND status IN ('active', 'pending_verification') ORDER BY created_at DESC", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$email,
				$type
			),
			ARRAY_A
		);
	}

	/**
	 * Transition a wishlist lead to waitlist.
	 *
	 * @return bool
	 */
	public function transition_to_waitlist() {
		if ( 'wishlist' !== $this->type ) {
			return false;
		}

		$this->type       = 'waitlist';
		$this->updated_at = current_time( 'mysql' );
		return $this->save();
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
