<?php
/**
 * Base singleton class.
 *
 * @package    NotifyBay
 * @subpackage Core
 * @since      1.0.0
 * @author     WPAnchorBay <sankarsan@gmail.com>
 */

namespace NotifyBay\Core;

/**
 * Base singleton class.
 */
abstract class Base {

	/**
	 * The single instance of the class.
	 *
	 * @since 1.0.0
	 * @access protected
	 * @var object $instance
	 */
	protected static $instance = null;

	/**
	 * Get the instance of the class.
	 *
	 * @since 1.0.0
	 * @access public
	 * @return object
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new static();
		}

		return self::$instance;
	}

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 * @access protected
	 */
	protected function __construct() {
		$this->init();
	}

	/**
	 * Initialize the class.
	 *
	 * @since 1.0.0
	 * @access protected
	 */
	protected function init() {}

	/**
	 * Add an action.
	 *
	 * @since 1.0.0
	 * @access protected
	 * @param string   $hook          The hook name.
	 * @param callable $callback      The callback function.
	 * @param int      $priority      The priority.
	 * @param int      $accepted_args The number of accepted arguments.
	 */
	protected function add_action( $hook, $callback, $priority = 10, $accepted_args = 1 ) {
		add_action( $hook, array( $this, $callback ), $priority, $accepted_args );
	}

	/**
	 * Add a filter.
	 *
	 * @since 1.0.0
	 * @access protected
	 * @param string   $hook          The hook name.
	 * @param callable $callback      The callback function.
	 * @param int      $priority      The priority.
	 * @param int      $accepted_args The number of accepted arguments.
	 */
	protected function add_filter( $hook, $callback, $priority = 10, $accepted_args = 1 ) {
		add_filter( $hook, array( $this, $callback ), $priority, $accepted_args );
	}

	/**
	 * Log a message.
	 *
	 * @since 1.0.0
	 * @access protected
	 * @param string $type    The log type.
	 * @param string $hook    The hook name.
	 * @param mixed  $callback The callback function.
	 * @param int    $priority The priority.
	 * @param int    $accepted_args The number of accepted arguments.
	 */
	protected function log( $type, $hook, $callback, $priority = 10, $accepted_args = 1 ) {
		// Log logic.
	}

	/**
	 * Prevents the instance from being cloned.
	 *
	 * @since 1.0.0
	 */
	private function __clone() {}

	/**
	 * Prevents the instance from being unserialized.
	 *
	 * @since 1.0.0
	 */
	public function __wakeup() {}
}
