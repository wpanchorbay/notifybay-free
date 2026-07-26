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

use NotifyBay\Core\Plugin;

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
	 * Register hooks.
	 *
	 * Runs a schema check early on every load. Activation hooks do not fire on an
	 * in-place plugin UPDATE, so without this a structural change (e.g. a new
	 * column) would be missing until the plugin is manually re-activated, causing
	 * insert failures. The check is a single cached option read that early-returns
	 * once the schema is current.
	 *
	 * @since 1.0.0
	 * @param Plugin $plugin The plugin instance.
	 * @return void
	 */
	public function run( Plugin $plugin ) {
		$plugin->get_loader()->add_action( 'init', $this, 'maybe_upgrade', 1 );
	}

	/**
	 * Re-apply the schema when the stored DB version is behind the code's.
	 *
	 * The dbDelta call is idempotent, so this safely adds any new
	 * columns/indexes on update without touching existing data.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function maybe_upgrade() {
		if ( (string) get_option( 'notifybay_db_version' ) === (string) \NOTIFYBAY_DB_VERSION ) {
			return;
		}
		$this->create_tables();
	}

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

		// Stamp the applied schema version so maybe_upgrade() stops re-running
		// dbDelta on subsequent loads (and so a later bump re-applies it).
		update_option( 'notifybay_db_version', \NOTIFYBAY_DB_VERSION );
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
