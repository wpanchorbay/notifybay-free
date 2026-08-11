<?php
/**
 * Admin Configuration
 *
 * This file defines the menu structure, plugin action links, and other admin-specific
 * metadata. You can customize these values without changing the Admin class logic.
 *
 * @package    NotifyBay
 * @subpackage Config
 * @since      1.0.0
 * @author     WPAnchorBay <sankarsan@wpanchorbay.com>
 */

// Prevent direct access.
defined( 'ABSPATH' ) || exit;

// Defined once and reused below (plugin_action_links + plugin_data) so the
// two never drift apart.
$notifybay_buy_pro_url = 'https://wpanchorbay.com/plugins/notifybay-waitlist-and-stock-alert-for-woocommerce/#pricing';

return array(

	/**
	 * Toggle the main top-level menu.
	 *
	 * Set to true to show a dedicated "NotifyBay" menu in the sidebar.
	 * Set to false if you only want to register submenus under other items (like Products or Tools).
	 */
	'show_main_menu'      => false,

	/**
	 * Menu registration details.
	 */
	'menu'                => array(

		/**
		 * Top-level menu configuration.
		 * Only active if 'show_main_menu' is true.
		 */
		'top_level' => array(
			'page_title' => __( 'NotifyBay', 'notifybay-waitlist-and-stock-alert-woo' ),
			'menu_title' => __( 'NotifyBay', 'notifybay-waitlist-and-stock-alert-woo' ),
			'capability' => 'manage_notifybay',
			'menu_slug'  => \NOTIFYBAY_PLUGIN_NAME,
			'icon_url'   => 'dashicons-admin-plugins', // Dashicon class or full URL to icon
			'position'   => 57,                         // Position in the sidebar
		),

		/**
		 * Sub-menu configuration.
		 * You can register submenus under your own main menu or under external menus.
		 */
		'sub_menus' => array(

			/**
			 * Leads menu under WooCommerce Products
			 */
			array(
				'parent_slug' => 'edit.php?post_type=product',
				'page_title'  => __( 'Leads', 'notifybay-waitlist-and-stock-alert-woo' ),
				'menu_title'  => __( 'Leads', 'notifybay-waitlist-and-stock-alert-woo' ),
				'capability'  => 'manage_notifybay',
				'menu_slug'   => \NOTIFYBAY_PLUGIN_NAME . '-products',
			),
		),
	),

	/**
	 * Plugin Action Links
	 *
	 * These links appear next to "Activate/Deactivate" on the WordPress Plugins page.
	 */
	'plugin_action_links' => array(
		// Keyed 'upgrade' on purpose: NotifyBay Pro's
		// remove_free_upgrade_link() (app/Core/Plugin.php) unsets this exact
		// key once Pro is active, so an already-paying customer never sees an
		// upgrade nag on their own plugin row. Do not rename this key without
		// updating that removal too.
		array(
			'key'    => 'upgrade',
			'text'   => __( 'Upgrade to Pro', 'notifybay-waitlist-and-stock-alert-woo' ),
			'url'    => $notifybay_buy_pro_url,
			'target' => '_blank',
			'style'  => 'color:#f02a74;font-weight:600;',
		),
		array(
			'text' => __( 'Settings', 'notifybay-waitlist-and-stock-alert-woo' ),
			'url'  => admin_url( 'admin.php?page=wc-settings&tab=' . \NOTIFYBAY_PLUGIN_NAME ),
		),
	),

	/**
	 * Plugin Row Meta
	 *
	 * Informational links rendered under the plugin description on the Plugins
	 * screen (alongside "View details"). High-intent actions live in
	 * `plugin_action_links` above; these are references.
	 */
	'plugin_row_meta'     => array(
		array(
			'text' => __( 'Docs', 'notifybay-waitlist-and-stock-alert-woo' ),
			'url'  => 'https://docs.wpanchorbay.com/notifybay/',
		),
		array(
			'text' => __( 'Support', 'notifybay-waitlist-and-stock-alert-woo' ),
			'url'  => 'https://wpanchorbay.com/support/',
		),
		array(
			'text' => __( 'Rate ★★★★★', 'notifybay-waitlist-and-stock-alert-woo' ),
			'url'  => 'https://wordpress.org/support/plugin/' . \NOTIFYBAY_SLUG . '/reviews/#new-post',
		),
	),

	/**
	 * Plugin Metadata
	 *
	 * General data used throughout the admin dashboard and JS localization.
	 */
	'plugin_data'         => array(
		'plugin_name' => esc_html__( 'NotifyBay', 'notifybay-waitlist-and-stock-alert-woo' ),
		'short_name'  => esc_html__( 'NotifyBay', 'notifybay-waitlist-and-stock-alert-woo' ),
		'menu_label'  => esc_html__( 'NotifyBay', 'notifybay-waitlist-and-stock-alert-woo' ),
		'menu_icon'   => 'dashicons-admin-plugins',
		'author_name' => 'WPAnchorBay',
		'author_uri'  => 'https://wpanchorbay.com',
		'support_uri' => 'https://wpanchorbay.com/support/',
		'docs_uri'    => 'https://docs.wpanchorbay.com/notifybay/',
		'buy_pro_url' => $notifybay_buy_pro_url,
		'position'    => 57,
	),
);
