<?php
/**
 * The admin-specific functionality of the plugin.
 *
 * @package    NotifyBay
 * @subpackage Admin
 * @since      1.0.0
 * @author     WPAnchorBay <sankarsan@gmail.com>
 */

namespace NotifyBay\Admin;

use NotifyBay\Core\Settings;
use NotifyBay\Helper\TemplateRenderer;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The admin-specific functionality of the plugin.
 *
 * @package    NotifyBay
 * @subpackage NotifyBay/Admin
 * @author     WPAnchorBay <sankarsan@gmail.com>
 */
class Admin {


	/**
	 * The single instance of the class.
	 *
	 * @since 1.0.0
	 * @var   Admin
	 * @access private
	 */
	private static $instance = null;

	/**
	 * Menu info.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      array
	 */
	private $menu_info;

	/**
	 * Admin configuration.
	 *
	 * @since 1.0.0
	 * @access private
	 * @var array
	 */
	private $config = null;

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 */
	private function __construct() {
		// Configuration is lazy-loaded via get_config() to avoid early translation calls.
	}

	/**
	 * Get Admin configuration (Lazy Loaded).
	 *
	 * @since 1.0.0
	 * @return array
	 */
	private function get_config() {
		if ( null === $this->config ) {
			$this->config = include \NOTIFYBAY_PATH . 'config/admin.php';
		}
		return $this->config;
	}

	/**
	 * Gets an instance of this object.
	 *
	 * @access public
	 * @return Admin
	 * @since 1.0.0
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Get plugin data (formerly white label options).
	 *
	 * @since 1.0.0
	 * @access private
	 * @return array
	 */
	private function get_plugin_data() {
		return $this->get_config()['plugin_data'] ?? array();
	}

	/**
	 * Add Admin Page Menu page.
	 *
	 * @since 1.0.0
	 * @access public
	 * @return void
	 */
	public function add_admin_menu() {
		notifybay_log( 'Admin: Registering Boilerplate menus', 'INFO' );

		$config      = $this->get_config();
		$menu_config = $config['menu'] ?? array();
		$show_main   = $config['show_main_menu'] ?? true;

		// 1. Add top-level menu
		if ( $show_main && ! empty( $menu_config['top_level'] ) ) {
			$top = $menu_config['top_level'];
			add_menu_page(
				$top['page_title'],
				$top['menu_title'],
				$top['capability'],
				$top['menu_slug'],
				array( $this, 'add_setting_root_div' ),
				$top['icon_url'],
				$top['position']
			);
		}

		// 2. Add sub-menus
		if ( ! empty( $menu_config['sub_menus'] ) ) {
			foreach ( $menu_config['sub_menus'] as $sub ) {
				add_submenu_page(
					$sub['parent_slug'],
					$sub['page_title'],
					$sub['menu_title'],
					$sub['capability'],
					$sub['menu_slug'],
					array( $this, 'add_setting_root_div' )
				);
			}
		}
	}

	/**
	 * Add to WooCommerce settings pages.
	 *
	 * @since 1.0.0
	 * @param array $settings The settings pages.
	 * @return array
	 */
	public function add_wc_settings_tab( $settings ) {
		$settings[] = new WCSettingsTab();
		return $settings;
	}

	/**
	 * Redirect to the main dashboard.
	 *
	 * @return void
	 */
	public function redirect_to_dashboard() {
		$config      = $this->get_config();
		$menu_config = $config['menu'] ?? array();
		$show_main   = $config['show_main_menu'] ?? true;

		$slug = \NOTIFYBAY_PLUGIN_NAME;

		if ( $show_main && ! empty( $menu_config['top_level']['menu_slug'] ) ) {
			$slug = $menu_config['top_level']['menu_slug'];
		} elseif ( ! empty( $menu_config['sub_menus'][0]['menu_slug'] ) ) {
			$slug = $menu_config['sub_menus'][0]['menu_slug'];
		}

		$redirect_url = admin_url( 'admin.php?page=' . $slug );
		wp_safe_redirect( $redirect_url );
		exit;
	}

	/**
	 * Check if current page is our menu page.
	 *
	 * @access public
	 * @since 1.0.0
	 * @return bool
	 */
	public function is_menu_page() {
		$screen = get_current_screen();
		if ( ! $screen ) {
			return false;
		}

		$base        = $screen->base;
		$config      = $this->get_config();
		$menu_config = $config['menu'] ?? array();

		// 1. Check Top-level menu slug
		if ( ! empty( $menu_config['top_level']['menu_slug'] ) ) {
			$top_slug = $menu_config['top_level']['menu_slug'];
			if ( 'toplevel_page_' . $top_slug === $base || $top_slug . '_page_' . $top_slug === $base ) {
				return true;
			}
		}

		// 2. Check all sub-menu slugs dynamically
		if ( ! empty( $menu_config['sub_menus'] ) ) {
			foreach ( $menu_config['sub_menus'] as $sub ) {
				$sub_slug = $sub['menu_slug'];
				// WordPress base for submenus is usually "parent_page_subslug" or just "subslug"
				if ( strpos( $base, '_page_' . $sub_slug ) !== false || $base === $sub_slug ) {
					return true;
				}
			}
		}

		// 3. Handle WooCommerce Settings tab
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( 'woocommerce_page_wc-settings' === $base && isset( $_GET['tab'] ) && \NOTIFYBAY_PLUGIN_NAME === $_GET['tab'] ) {
			return true;
		}

		return false;
	}

	/**
	 * Add has sticky header class.
	 *
	 * @since 1.0.0
	 * @access public
	 * @param string $classes The classes.
	 * @return string
	 */
	public function add_has_sticky_header( $classes ) {
		if ( $this->is_menu_page() ) {
			$classes .= ' at-has-hdr-stky ';
		}
		return $classes;
	}

	/**
	 * Add setting root div.
	 *
	 * @since 1.0.0
	 * @access public
	 * @return void
	 */
	public function add_setting_root_div() {
		TemplateRenderer::render(
			'admin/settings-root',
			array( 'plugin_name' => \NOTIFYBAY_PLUGIN_NAME )
		);
	}

	/**
	 * Enqueue resources.
	 *
	 * @since 1.0.0
	 * @access public
	 * @return void
	 */
	public function enqueue_resources() {
		if ( ! $this->is_menu_page() ) {
			return;
		}

		$screen = get_current_screen();

		$context = ( isset( $screen->base ) && 'woocommerce_page_wc-settings' === $screen->base ) ? 'settings' : 'admin';
		$handle  = \NOTIFYBAY_PLUGIN_NAME . '-' . $context;

		notifybay_log( "Admin: Enqueueing resources for context: {$context}", 'DEBUG' );

		$deps_file  = \NOTIFYBAY_PATH . "build/{$context}.asset.php";
		$dependency = array( 'wp-i18n' );
		$version    = \NOTIFYBAY_VERSION;
		if ( file_exists( $deps_file ) ) {
			$deps_file  = require $deps_file;
			$dependency = $deps_file['dependencies'];
			$version    = $deps_file['version'];
		}

		$admin_script = apply_filters( "notifybay_admin_script_{$context}", \NOTIFYBAY_URL . "build/{$context}.js" );
		wp_enqueue_script( $handle, $admin_script, $dependency, $version, true );
		wp_enqueue_editor();
		wp_enqueue_media();

		$admin_css = apply_filters( "notifybay_admin_css_{$context}", \NOTIFYBAY_URL . "build/{$context}.css" );
		wp_enqueue_style( $handle, $admin_css, array(), $version );
		wp_style_add_data( $handle, 'rtl', 'replace' );

		$config = $this->get_config();
		$localize = apply_filters(
			'notifybay_admin_localize',
			array(
				'version'         => $version,
				'root_id'         => \NOTIFYBAY_PLUGIN_NAME,
				'nonce'           => wp_create_nonce( 'wp_rest' ),
				'store'           => \NOTIFYBAY_PLUGIN_NAME,
				'rest_url'        => get_rest_url( null, \NOTIFYBAY_TEXT_DOMAIN . '/v1' ),
				'pluginData'      => $this->get_plugin_data(),
				'wpSettings'      => array(
					'dateFormat' => get_option( 'date_format' ),
					'timeFormat' => get_option( 'time_format' ),
				),
				'plugin_settings' => Settings::get_instance()->get_settings(),
				'products_url'    => admin_url( 'edit.php?post_type=product' ),
				'settings_url'    => admin_url( 'admin.php?page=wc-settings&tab=' . \NOTIFYBAY_PLUGIN_NAME ),
				'admin_url'       => ( function() use ( $config ) {
					$menu_config = $config['menu'] ?? array();
					$show_main   = $config['show_main_menu'] ?? true;
					$slug = \NOTIFYBAY_PLUGIN_NAME;
					if ( $show_main && ! empty( $menu_config['top_level']['menu_slug'] ) ) {
						$slug = $menu_config['top_level']['menu_slug'];
					} elseif ( ! empty( $menu_config['sub_menus'][0]['menu_slug'] ) ) {
						$slug = $menu_config['sub_menus'][0]['menu_slug'];
					}
					return admin_url( 'admin.php?page=' . $slug );
				} )(),
				'wc_emails_url'   => admin_url( 'admin.php?page=wc-settings&tab=email' ),
				'show_wizard'     => 'yes' !== get_option( 'notifybay_wizard_completed' ),
				'is_pro'          => defined( 'NOTIFYBAY_PRO_VERSION' ),
				'context'         => $context,
				'currency'        => array(
					'code'   => get_woocommerce_currency(),
					'symbol' => get_woocommerce_currency_symbol(),
				),
			)
		);

		wp_localize_script( $handle, 'notifyBay_Localize', $localize );

		$path_to_check = \NOTIFYBAY_PATH . 'languages';
		wp_set_script_translations(
			$handle,
			'notifybay',
			$path_to_check
		);
	}

	/**
	 * Add plugin action links.
	 *
	 * @since 1.0.0
	 * @access public
	 * @param string[] $actions Plugin action links.
	 * @return array
	 */
	public function add_plugin_action_links( $actions ) {
		$config = $this->get_config();
		$links  = $config['plugin_action_links'] ?? array();

		foreach ( $links as $link ) {
			$target = ! empty( $link['target'] ) ? sprintf( ' target="%s" rel="noopener"', esc_attr( $link['target'] ) ) : '';
			$style  = ! empty( $link['style'] ) ? sprintf( ' style="%s"', esc_attr( $link['style'] ) ) : '';
			$html   = sprintf(
				'<a href="%1$s"%2$s%3$s>%4$s</a>',
				esc_url( $link['url'] ),
				$target,
				$style,
				esc_html( $link['text'] )
			);

			// Preserve a stable key when provided so other plugins (Pro) can
			// target the link for removal; fall back to an appended entry.
			if ( ! empty( $link['key'] ) ) {
				$actions[ $link['key'] ] = $html;
			} else {
				$actions[] = $html;
			}
		}

		return $actions;
	}

	/**
	 * Append informational row-meta links under the plugin description.
	 *
	 * @since 1.1.0
	 * @param string[] $meta        Existing row meta links.
	 * @param string   $plugin_file The plugin file being rendered.
	 * @return string[]
	 */
	public function add_plugin_row_meta( $meta, $plugin_file ) {
		if ( plugin_basename( \NOTIFYBAY_PATH . 'notifybay.php' ) !== $plugin_file ) {
			return $meta;
		}

		$config = $this->get_config();
		$links  = $config['plugin_row_meta'] ?? array();

		foreach ( $links as $link ) {
			$meta[] = sprintf(
				'<a href="%s" target="_blank" rel="noopener">%s</a>',
				esc_url( $link['url'] ),
				esc_html( $link['text'] )
			);
		}

		return $meta;
	}

	/**
	 * Register the hooks for the admin area.
	 *
	 * @since    1.0.0
	 * @param    \NotifyBay\Core\Plugin $plugin The Plugin instance.
	 * @return   void
	 */
	public function run( $plugin ) {
		$loader = $plugin->get_loader();
		$loader->add_filter( 'all_plugins', $plugin, 'change_plugin_display_name' );
		$loader->add_action( 'admin_menu', $this, 'add_admin_menu' );
		$loader->add_filter( 'woocommerce_get_settings_pages', $this, 'add_wc_settings_tab' );
		$loader->add_filter( 'admin_body_class', $this, 'add_has_sticky_header' );
		$loader->add_action( 'admin_enqueue_scripts', $this, 'enqueue_resources' );

		$plugin_basename = plugin_basename( \NOTIFYBAY_PATH . 'notifybay.php' );
		$loader->add_filter( 'plugin_action_links_' . $plugin_basename, $this, 'add_plugin_action_links', 10, 1 );
		$loader->add_filter( 'plugin_row_meta', $this, 'add_plugin_row_meta', 10, 2 );
	}
}
