<?php
/**
 * Database Migration Interface.
 *
 * @package    NotifyBay
 * @subpackage Database
 * @since      1.0.0
 * @author     WPAnchorBay <sankarsan@wpanchorbay.com>
 */

namespace NotifyBay\Database;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

interface MigrationInterface {

	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up();

	/**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
	public function down();
}
