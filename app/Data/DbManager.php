<?php
/**
 * The Database Manager class.
 *
 * Handles the creation and management of the plugin's custom database tables.
 *
 * @since      1.0.0
 * @package    NotifyBay
 * @subpackage NotifyBay/Data
 * @author     WPAnchorBay <sankarsan@wpanchorbay.com>
 */

namespace NotifyBay\Data;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class DbManager
 *
 * @since 1.0.0
 * @package NotifyBay
 */
class DbManager {

	/**
	 * The single instance of the class.
	 *
	 * @since 1.0.0
	 * @var   DbManager
	 * @access private
	 */
	private static $instance = null;

	/**
	 * Gets an instance of this object.
	 *
	 * @static
	 * @access public
	 * @since 1.0.0
	 * @return DbManager
	 */
	public static function get_instance() {
		static $instance = null;
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Private constructor.
	 *
	 * @since 1.0.0
	 * @access private
	 */
	private function __construct() {}

	/**
	 * Get registered migrations from the configuration.
	 *
	 * @since 1.0.0
	 * @access private
	 * @return array Array of migration instances.
	 */
	private function get_migrations() {
		$migration_classes = include \NOTIFYBAY_PATH . 'config/migrations.php';
		$migrations        = array();

		if ( is_array( $migration_classes ) ) {
			foreach ( $migration_classes as $class ) {
				if ( class_exists( $class ) ) {
					$migrations[] = new $class();
				}
			}
		}

		return $migrations;
	}

	/**
	 * Run all migrations.
	 *
	 * @since 1.0.0
	 */
	public function create_tables() {
		$migrations = $this->get_migrations();

		foreach ( $migrations as $migration ) {
			if ( $migration instanceof \NotifyBay\Database\MigrationInterface ) {
				$migration->up();
			}
		}
	}

	/**
	 * Drop all custom tables.
	 *
	 * @since 1.0.0
	 */
	public function drop_tables() {
		$migrations = $this->get_migrations();

		// Run down() in reverse order
		foreach ( array_reverse( $migrations ) as $migration ) {
			if ( $migration instanceof \NotifyBay\Database\MigrationInterface ) {
				$migration->down();
			}
		}
	}
}
