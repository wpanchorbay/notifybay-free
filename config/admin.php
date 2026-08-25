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
	 * Set to true to show a dedicated "NotifyBay" menu in the sidebar. This is
	 * only the fallback used before the live `general_showMainMenu` setting
	 * has ever been saved (e.g. immediately after activation) -- it must
	 * match that setting's own default (see config/settings.php) or the menu
	 * silently fails to appear on a fresh install. The user-facing toggle is
	 * on the General settings tab; this flag is not meant to be hand-edited
	 * on a live site.
	 */
	'show_main_menu'      => true,

	/**
	 * Menu registration details.
	 */
	'menu'                => array(

		/**
		 * Top-level menu configuration.
		 * Only active if 'show_main_menu' is true.
		 *
		 * `menu_slug` is deliberately the SAME slug as the Leads page below
		 * (`NOTIFYBAY_PLUGIN_NAME . '-products'`), not a bare `NOTIFYBAY_PLUGIN_NAME`.
		 * add_menu_page() auto-inserts the parent itself as the first submenu
		 * item -- if the top-level slug pointed at a page nothing else
		 * renders, clicking the top-level menu item (or that auto-added first
		 * submenu) would load a screen with no content and no assets enqueued
		 * for it. Reusing the Leads slug means that click lands on a page
		 * that already works, exactly the technique campaignbay's own
		 * Admin.php uses for its top-level "Dashboard" entry.
		 */
		'top_level' => array(
			'page_title' => __( 'NotifyBay', 'notifybay-waitlist-and-stock-alert-woo' ),
			'menu_title' => __( 'NotifyBay', 'notifybay-waitlist-and-stock-alert-woo' ),
			'capability' => 'manage_notifybay',
			'menu_slug'  => \NOTIFYBAY_PLUGIN_NAME . '-products',
			'icon_url'   => 'data:image/svg+xml;base64,PD94bWwgdmVyc2lvbj0iMS4wIiBlbmNvZGluZz0iVVRGLTgiIHN0YW5kYWxvbmU9Im5vIj8+CjxzdmcKICAgd2lkdGg9IjEyMCIKICAgaGVpZ2h0PSIxMjAiCiAgIHZpZXdCb3g9IjAgMCAxMjAgMTIwIgogICBmaWxsPSJub25lIgogICB2ZXJzaW9uPSIxLjEiCiAgIGlkPSJzdmcxMCIKICAgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIgogICB4bWxuczpzdmc9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj4KICA8ZGVmcwogICAgIGlkPSJkZWZzMTQiIC8+CiAgPHBhdGgKICAgICBpZD0icmVjdDIiCiAgICAgc3R5bGU9ImZpbGw6I2ZmZmZmZjtmaWxsLW9wYWNpdHk6MSIKICAgICBkPSJNIDMwIDAgQyAxMy4zODAwNTEgMCAwIDEzLjM4MDA1MSAwIDMwIEwgMCA5MCBDIDAgMTA2LjYxOTk0IDEzLjM4MDA1MSAxMjAgMzAgMTIwIEwgOTAgMTIwIEMgMTA2LjYxOTk0IDEyMCAxMjAgMTA2LjYxOTk0IDEyMCA5MCBMIDEyMCAzMCBDIDEyMCAxMy4zODAwNTEgMTA2LjYxOTk0IDAgOTAgMCBMIDMwIDAgeiBNIDYwLjIzMDQ2OSAyNSBDIDYzLjMyMDU2IDI1IDY2LjIxMDc0NyAyNS4zMzYyMzEgNjguODA2NjQxIDI2LjExMTMyOCBDIDY1LjU1MTk1IDI4LjY3NDcyMiA2My40NjEwMzUgMzIuNjUwNzQ5IDYzLjQ2MDkzOCAzNy4xMTUyMzQgQyA2My40NjA5MzggNDQuODQ3MjEzIDY5LjcyODk1OSA1MS4xMTUyMzQgNzcuNDYwOTM4IDUxLjExNTIzNCBDIDc4Ljk5NTEzMiA1MS4xMTUyMzQgODAuNDcyMjE5IDUwLjg2NjI1NiA4MS44NTM1MTYgNTAuNDEwMTU2IEMgODIuNzk4ODEzIDY0LjM0NzExNCA5MS40NjA5MzcgNjcuMTcwNDYyIDkxLjQ2MDkzOCA3NC44MDY2NDEgQyA5MS40NjA5MzggNzYuNTkxNzM1IDkwLjc1MjUzMSA3OC4zMDYwNjUgODkuNDkwMjM0IDc5LjU2ODM1OSBDIDg4LjIyODA0IDgwLjgzMDU1NiA4Ni41MTU0NjMgODEuNTM5MDYzIDg0LjczMDQ2OSA4MS41MzkwNjIgTCAzNS43MzA0NjkgODEuNTM5MDYyIEMgMzMuOTQ1NTc1IDgxLjUzODk2NSAzMi4yMzQ3NTMgODAuODMwMzU2IDMwLjk3MjY1NiA3OS41NjgzNTkgQyAyOS43MTAzNjIgNzguMzA2MDY1IDI5IDc2LjU5MTczNSAyOSA3NC44MDY2NDEgQyAyOS4wMDAxIDY2LjcyOTg2NSAzOC42OTMyNTkgNjQuMDM4MzE1IDM4LjY5MzM1OSA0Ny44ODQ3NjYgQyAzOC42OTMzNTkgMzAuMzg0OTE3IDQ3Ljg0NjAwNSAyNS4wMDAxIDYwLjIzMDQ2OSAyNSB6IE0gNzcuNDYwOTM4IDI2Ljg4NDc2NiBDIDgzLjExMTIyNiAyNi44ODQ3NjYgODcuNjkxNDA2IDMxLjQ2NDk0NiA4Ny42OTE0MDYgMzcuMTE1MjM0IEMgODcuNjkxNDA2IDQyLjc2NTUyNCA4My4xMTEyMjYgNDcuMzQ1NzAzIDc3LjQ2MDkzOCA0Ny4zNDU3MDMgQyA3MS44MTA2NDggNDcuMzQ1NzAzIDY3LjIzMDQ2OSA0Mi43NjU1MjQgNjcuMjMwNDY5IDM3LjExNTIzNCBDIDY3LjIzMDQ2OSAzMS40NjQ5NDYgNzEuODEwNjQ4IDI2Ljg4NDc2NiA3Ny40NjA5MzggMjYuODg0NzY2IHogTSA0OS41NDg4MjggODUuNTc2MTcyIEwgNzAuOTE0MDYyIDg1LjU3NjE3MiBDIDcwLjI1MTM2MyA5MC44ODgyNjcgNjUuNzIyMDYzIDk1IDYwLjIzMDQ2OSA5NSBDIDU0LjczODk3NCA5NSA1MC4yMTE1MjcgOTAuODg4MjY3IDQ5LjU0ODgyOCA4NS41NzYxNzIgeiAiIC8+Cjwvc3ZnPgo=',
			'position'   => 57,                         // Position in the sidebar
		),

		/**
		 * Sub-menu configuration.
		 * You can register submenus under your own main menu or under external menus.
		 */
		'sub_menus' => array(

			/**
			 * Overrides WordPress's auto-added first submenu item (which
			 * would otherwise just repeat "NotifyBay" as the label) with an
			 * explicit "Leads" label, while pointing at the exact same page
			 * as `top_level` above -- parent_slug and menu_slug are both the
			 * top-level slug on purpose.
			 */
			array(
				'parent_slug' => \NOTIFYBAY_PLUGIN_NAME . '-products',
				'page_title'  => __( 'Leads', 'notifybay-waitlist-and-stock-alert-woo' ),
				'menu_title'  => __( 'Leads', 'notifybay-waitlist-and-stock-alert-woo' ),
				'capability'  => 'manage_notifybay',
				'menu_slug'   => \NOTIFYBAY_PLUGIN_NAME . '-products',
			),

			/**
			 * Leads menu under WooCommerce Products (unchanged -- this is
			 * the original, pre-existing surface and must keep working
			 * exactly as before).
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
