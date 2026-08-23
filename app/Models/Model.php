<?php
/**
 * Base Model class for interacting with custom database tables.
 * Provides a simple Eloquent-like wrapper over $wpdb.
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
 * Base Model class.
 */
abstract class Model {
	/**
	 * The table associated with the model (without prefix).
	 *
	 * @var string
	 */
	protected $table;

	/**
	 * The primary key for the model.
	 *
	 * @var string
	 */
	protected $primary_key = 'id';

	/**
	 * Column names that callers may filter/search on via paginate().
	 *
	 * Column names cannot be bound as prepared-statement parameters, so any
	 * key passed in paginate()'s $where/$search arrays is checked against this
	 * allow-list before it is interpolated into SQL. Subclasses MUST declare
	 * their queryable columns; an empty list (the default) disallows all
	 * filtering, which fails closed rather than trusting caller-supplied keys.
	 *
	 * @var string[]
	 */
	protected $queryable_columns = array();

	/**
	 * Model attributes.
	 *
	 * @var array
	 */
	protected $attributes = array();

	/**
	 * Create a new model instance.
	 *
	 * @param array $attributes Attributes to fill.
	 */
	public function __construct( array $attributes = array() ) {
		$this->fill( $attributes );
	}

	/**
	 * Get the full table name including the WordPress prefix.
	 *
	 * @return string
	 */
	public function get_table() {
		global $wpdb;
		return $wpdb->prefix . $this->table;
	}

	/**
	 * Fill the model with an array of attributes.
	 *
	 * @param array $attributes The attributes.
	 * @return $this
	 */
	public function fill( array $attributes ) {
		foreach ( $attributes as $key => $value ) {
			$this->attributes[ $key ] = $value;
		}
		return $this;
	}

	/**
	 * Magic getter for attributes.
	 *
	 * @param string $key Attribute name.
	 * @return mixed|null
	 */
	public function __get( $key ) {
		return isset( $this->attributes[ $key ] ) ? $this->attributes[ $key ] : null;
	}

	/**
	 * Magic setter for attributes.
	 *
	 * @param string $key   Attribute name.
	 * @param mixed  $value Value.
	 */
	public function __set( $key, $value ) {
		$this->attributes[ $key ] = $value;
	}

	/**
	 * Get all attributes.
	 *
	 * @return array
	 */
	public function to_array() {
		return $this->attributes;
	}

	/**
	 * Save the model to the database.
	 *
	 * @return bool|int True/ID on success, false on failure.
	 */
	public function save() {
		global $wpdb;
		$table = $this->get_table();
		$pk    = $this->primary_key;

		// Set timestamps if they exist
		$now = current_time( 'mysql' );
		if ( ! isset( $this->attributes['created_at'] ) && ! isset( $this->attributes[ $pk ] ) ) {
			$this->attributes['created_at'] = $now;
		}
		$this->attributes['updated_at'] = $now;

		if ( isset( $this->attributes[ $pk ] ) && ! empty( $this->attributes[ $pk ] ) ) {
			// Update
			$id   = $this->attributes[ $pk ];
			$data = $this->attributes;
			unset( $data[ $pk ] ); // Don't update primary key

			$result = $wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom {$wpdb->prefix}notifybay_leads table; values are bound via prepare(). Direct, uncached queries are intentional for this real-time data-access layer.
				$table,
				$data,
				array( $pk => $id )
			);

			if ( false === $result ) {
				self::log_db_failure( 'update', $table, $data );
				return false;
			}

			return true;
		} else {
			// Insert
			$result = $wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Custom {$wpdb->prefix}notifybay_leads table; values are bound via prepare(). Direct, uncached queries are intentional for this real-time data-access layer.
				$table,
				$this->attributes
			);

			if ( $result ) {
				$this->attributes[ $pk ] = $wpdb->insert_id;
				return $this->attributes[ $pk ];
			}

			self::log_db_failure( 'insert', $table, $this->attributes );
			return false;
		}
	}

	/**
	 * Record why a write failed.
	 *
	 * Previously save() returned a bare `false` and discarded $wpdb->last_error,
	 * so a caller could only report something like "Could not update lead." with
	 * no way to find out why. A real instance of that: an `Unknown column
	 * 'target_price' in 'field list'` went unnoticed because the message never
	 * reached anybody, and since save() writes every attribute in one statement,
	 * the rejected column also discarded the legitimate changes beside it.
	 *
	 * The column list is logged, never the values -- a lead row holds an email
	 * address and two tokens that let the bearer act as that customer.
	 *
	 * @since 1.0.3
	 * @access private
	 * @param string $operation Either `insert` or `update`.
	 * @param string $table     The table written to.
	 * @param array  $data      The data passed to $wpdb; only its keys are logged.
	 * @return void
	 */
	private static function log_db_failure( $operation, $table, array $data ) {
		global $wpdb;

		if ( ! function_exists( 'notifybay_log' ) || '' === (string) $wpdb->last_error ) {
			return;
		}

		notifybay_log(
			sprintf(
				'%s on %s failed: %s (columns: %s)',
				$operation,
				$table,
				$wpdb->last_error,
				implode( ', ', array_keys( $data ) )
			),
			'error'
		);
	}

	/**
	 * Delete the model from the database.
	 *
	 * @return bool
	 */
	public function delete() {
		global $wpdb;
		$pk = $this->primary_key;

		if ( ! isset( $this->attributes[ $pk ] ) ) {
			return false;
		}

		$result = $wpdb->delete( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom {$wpdb->prefix}notifybay_leads table; values are bound via prepare(). Direct, uncached queries are intentional for this real-time data-access layer.
			$this->get_table(),
			array( $pk => $this->attributes[ $pk ] )
		);

		return false !== $result;
	}

	/**
	 * Find a model by its primary key.
	 *
	 * @param int $id The ID.
	 * @return static|null
	 */
	public static function find( $id ) {
		global $wpdb;
		$instance = new static();
		$table    = $instance->get_table();
		$pk       = $instance->primary_key;

		// Table and primary-key column are bound as %i identifiers so nothing is
		// interpolated into the SQL string.
		$row = $wpdb->get_row( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom {$wpdb->prefix}notifybay_leads table; a direct, uncached read is intentional for this real-time data-access layer.
			$wpdb->prepare( 'SELECT * FROM %i WHERE %i = %d', $table, $pk, $id ),
			ARRAY_A
		);

		if ( $row ) {
			return new static( $row );
		}

		return null;
	}

	/**
	 * Get all records.
	 *
	 * @return static[]
	 */
	public static function all() {
		global $wpdb;
		$instance = new static();
		$table    = $instance->get_table();

		// Table is bound as a %i identifier so nothing is interpolated into SQL.
		$results = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom {$wpdb->prefix}notifybay_leads table; a direct, uncached read is intentional for this real-time data-access layer.
			$wpdb->prepare( 'SELECT * FROM %i', $table ),
			ARRAY_A
		);

		$models = array();
		foreach ( $results as $row ) {
			$models[] = new static( $row );
		}

		return $models;
	}

	/**
	 * Columns `paginate()` will order by, beyond the primary key.
	 *
	 * Deliberately separate from $queryable_columns. That list controls what
	 * may appear in a WHERE clause, and widening it to make a column sortable
	 * would also make it filterable -- a different decision with a different
	 * blast radius. A model wants to sort by created_at without created_at
	 * becoming an equality filter.
	 *
	 * @var string[]
	 */
	protected $sortable_columns = array();

	/**
	 * Paginate records.
	 *
	 * @param int    $page     Page number.
	 * @param int    $per_page Items per page.
	 * @param array  $where    Optional simple WHERE clauses (e.g., ['status' => 'active']).
	 * @param array  $search   Optional search ['column' => 'value'].
	 * @param string $orderby  Optional column to order by, descending. Must be
	 *                         listed in $sortable_columns; anything else falls
	 *                         back to the primary key. The primary key is always
	 *                         appended as a tiebreaker.
	 * @return array An array containing 'data', 'total', 'page', 'per_page'.
	 */
	public static function paginate( $page = 1, $per_page = 20, $where = array(), $search = array(), $orderby = '' ) {
		global $wpdb;
		$instance = new static();
		$table    = $instance->get_table();

		$page     = max( 1, absint( $page ) );
		$per_page = max( 1, absint( $per_page ) );
		$offset   = ( $page - 1 ) * $per_page;

		$allowed_cols = $instance->queryable_columns;

		// Build the WHERE fragment from allow-listed columns only. Both the column
		// names (%i) and their values (%s) are emitted as prepare() placeholders,
		// so every caller-supplied token is bound — nothing is interpolated into
		// the SQL. The allow-list additionally drops keys that are not real
		// columns, avoiding query errors on unknown fields.
		$where_sql  = '';
		$where_args = array(); // Identifier + value args, in placeholder order.

		if ( ! empty( $where ) ) {
			foreach ( $where as $col => $val ) {
				if ( ! in_array( $col, $allowed_cols, true ) ) {
					continue;
				}
				$where_sql   .= ' AND %i = %s';
				$where_args[] = $col;
				$where_args[] = $val;
			}
		}

		if ( ! empty( $search ) ) {
			foreach ( $search as $col => $val ) {
				if ( ! in_array( $col, $allowed_cols, true ) ) {
					continue;
				}
				$where_sql   .= ' AND %i LIKE %s';
				$where_args[] = $col;
				$where_args[] = '%' . $wpdb->esc_like( $val ) . '%';
			}
		}

		// Count total. `$where_sql` holds only literal placeholder fragments
		// (` AND %i = %s`), so interpolating it into the prepared string is the
		// same bound-placeholder pattern used for `IN (...)` lists.
		$total = (int) $wpdb->get_var( // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom {$wpdb->prefix}notifybay_leads table; a direct, uncached read is intentional, and $where_sql holds only prepared %i/%s fragments so every identifier and value is bound.
			$wpdb->prepare(
				"SELECT COUNT(*) FROM %i WHERE 1=1{$where_sql}", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $where_sql is a literal placeholder fragment; all values are bound below.
				array_merge( array( $table ), $where_args )
			)
		);

		/*
		 * Order by an allow-listed column, defaulting to the primary key so
		 * every existing caller keeps the behaviour it had. Both identifiers
		 * are bound as %i, and $orderby is checked against $sortable_columns,
		 * so a caller cannot reach an arbitrary column or inject one.
		 *
		 * The primary key is always appended as a tiebreaker. Sorting on a
		 * column with duplicate values alone leaves row order undefined
		 * between queries, and since pagination is LIMIT/OFFSET over repeated
		 * queries, that shows up as rows appearing on two pages while others
		 * are never returned at all -- silently, and worst on exactly the
		 * columns worth sorting by, since timestamps tie constantly.
		 */
		$order_col = $instance->primary_key;

		if ( $orderby && in_array( $orderby, $instance->sortable_columns, true ) ) {
			$order_col = $orderby;
		}

		$data_args = array_merge( array( $table ), $where_args, array( $order_col, $instance->primary_key, $per_page, $offset ) );
		$results   = $wpdb->get_results( // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom {$wpdb->prefix}notifybay_leads table; a direct, uncached read is intentional, and $where_sql holds only prepared %i/%s fragments so every identifier and value is bound.
			$wpdb->prepare( // phpcs:ignore WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- $data_args is built at runtime (table + optional filters + order column + primary key + limit/offset); its count always matches the placeholders.
				"SELECT * FROM %i WHERE 1=1{$where_sql} ORDER BY %i DESC, %i DESC LIMIT %d OFFSET %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $where_sql is a literal placeholder fragment; all values are bound.
				$data_args
			),
			ARRAY_A
		);

		$models = array();
		if ( $results ) {
			foreach ( $results as $row ) {
				$models[] = new static( $row );
			}
		}

		return array(
			'data'        => $models,
			'total'       => $total,
			'total_pages' => ceil( $total / $per_page ),
			'page'        => $page,
			'per_page'    => $per_page,
		);
	}
}
