<?php
/**
 * Base Model class for interacting with custom database tables.
 * Provides a simple Eloquent-like wrapper over $wpdb.
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

			return false === $result ? false : true;
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
			return false;
		}
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

		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE {$pk} = %d", $id ), ARRAY_A ); // phpcs:ignore

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

		$results = $wpdb->get_results( "SELECT * FROM {$table}", ARRAY_A ); // phpcs:ignore

		$models = array();
		foreach ( $results as $row ) {
			$models[] = new static( $row );
		}

		return $models;
	}

	/**
	 * Paginate records.
	 *
	 * @param int   $page     Page number.
	 * @param int   $per_page Items per page.
	 * @param array $where    Optional simple WHERE clauses (e.g., ['status' => 'active']).
	 * @param array $search   Optional search ['column' => 'value'].
	 * @return array An array containing 'data', 'total', 'page', 'per_page'.
	 */
	public static function paginate( $page = 1, $per_page = 20, $where = array(), $search = array() ) {
		global $wpdb;
		$instance = new static();
		$table    = $instance->get_table();

		$page     = max( 1, absint( $page ) );
		$per_page = max( 1, absint( $per_page ) );
		$offset   = ( $page - 1 ) * $per_page;

		$where_sql    = '1=1';
		$where_values = array();

		if ( ! empty( $where ) ) {
			foreach ( $where as $col => $val ) {
				$where_sql     .= " AND {$col} = %s";
				$where_values[] = $val;
			}
		}

		if ( ! empty( $search ) ) {
			foreach ( $search as $col => $val ) {
				$where_sql     .= " AND {$col} LIKE %s";
				$where_values[] = '%' . $wpdb->esc_like( $val ) . '%';
			}
		}

		// Count total
		$count_query = "SELECT COUNT(*) FROM {$table} WHERE {$where_sql}";
		if ( ! empty( $where_values ) ) {
			$count_query = $wpdb->prepare( $count_query, $where_values ); // phpcs:ignore
		}
		$total = (int) $wpdb->get_var( $count_query ); // phpcs:ignore

		// Get data
		$data_query   = "SELECT * FROM {$table} WHERE {$where_sql} ORDER BY {$instance->primary_key} DESC LIMIT %d OFFSET %d";
		$query_values = array_merge( $where_values, array( $per_page, $offset ) );
		$data_query = $wpdb->prepare( $data_query, $query_values ); // phpcs:ignore

		$results = $wpdb->get_results( $data_query, ARRAY_A ); // phpcs:ignore

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
