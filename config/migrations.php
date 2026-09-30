<?php
/**
 * Database Migrations Configuration
 *
 * Register your database migration classes here. They will be executed
 * in the order listed on plugin activation, and in reverse order on uninstallation.
 *
 * @package    NotifyBay
 * @subpackage Config
 * @since      1.0.0
 * @author     WPAnchorBay <sankarsan@wpanchorbay.com>
 */

// Prevent direct access.
defined( 'ABSPATH' ) || exit;

return array(
	\NotifyBay\Database\Migrations\CreateNotifybayLeadsTable::class,
	// Order matters: DbManager::create_tables() runs this array in sequence and
	// Activator calls it on a fresh install, so a data migration must come after
	// the schema migration that creates the table it rewrites.
	\NotifyBay\Database\Migrations\FixLeadExpiry::class,
);
