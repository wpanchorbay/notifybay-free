<?php
/**
 * The admin-specific functionality of the plugin.
 *
 * @package    NotifyBay
 * @subpackage Admin
 * @since      1.0.0
 * @author     WPAnchorBay <sankarsan@wpanchorbay.com>
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
 * @author     WPAnchorBay <sankarsan@wpanchorbay.com>
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
		// Live setting, not just the static config default -- lets a store
		// owner turn the top-level menu off from the General settings tab.
		// Settings::load_settings() merges config/settings.php's defaults, so this
		// key is always present and the second argument is never reached — it is
		// spelled out only to document the intended value.
		$show_main = Settings::get_instance()->get_settings( 'general_showMainMenu', true );
		$top_slug  = $menu_config['top_level']['menu_slug'] ?? null;

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
			$sub_menus = $menu_config['sub_menus'];

			/*
			 * Register the entry parented to our own top-level LAST.
			 *
			 * `notifybay-products` is deliberately registered more than once --
			 * by add_menu_page() above, as the relabelled first child of that
			 * menu, and again under WooCommerce's Products menu, which is the
			 * original surface and has to keep working. But a slug can only have
			 * one parent: add_submenu_page() assigns $_parent_pages[$menu_slug]
			 * unconditionally, so whichever call runs last decides which menu
			 * WordPress opens and highlights for that page.
			 *
			 * In config order the Products entry ran last, so visiting
			 * admin.php?page=notifybay-products -- what get_admin_page_url()
			 * returns, and where the post-activation redirect lands -- opened the
			 * Products menu and left the new NotifyBay menu looking inert on its
			 * own page. Ordering it this way points the highlight at NotifyBay
			 * when that menu exists, and changes nothing when it does not: the
			 * entry is skipped entirely then, leaving Products as the only
			 * claimant.
			 *
			 * Partitioned rather than sorted: usort() is not stable before PHP
			 * 8.0 and this plugin supports 7.4, so a comparator returning 0 for
			 * every remaining pair could quietly reshuffle the visible order of
			 * the other menu items on exactly the versions still supported.
			 */
			$own_last = array();
			$others   = array();

			foreach ( $sub_menus as $sub_menu ) {
				if ( $sub_menu['parent_slug'] === $top_slug ) {
					$own_last[] = $sub_menu;
					continue;
				}

				$others[] = $sub_menu;
			}

			$sub_menus = array_merge( $others, $own_last );

			foreach ( $sub_menus as $sub ) {
				// The entry whose parent_slug is the top-level's own slug only
				// makes sense once that top-level page actually exists.
				if ( $sub['parent_slug'] === $top_slug && ! $show_main ) {
					continue;
				}
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

		// 3. Plain links into pages NotifyBay doesn't own (WooCommerce's own
		// wc-settings page). These are NOT registered via add_submenu_page():
		// wc-settings already has its own registered page hook (WooCommerce's),
		// and a second add_submenu_page() call for the same page risks
		// colliding with it. Appending directly to the $submenu global is the
		// standard WP technique for linking into another plugin's existing
		// page from your own menu.
		if ( $show_main && $top_slug ) {
			global $submenu;
			if ( isset( $submenu[ $top_slug ] ) ) {
				// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- appending an entry to $submenu is the documented way to link into another plugin's registered page; nothing existing is overwritten.
				$submenu[ $top_slug ][] = array(
					__( 'Settings', 'notifybay-waitlist-and-stock-alert-woo' ),
					'manage_woocommerce', // wc-settings' own required capability.
					'admin.php?page=wc-settings&tab=' . \NOTIFYBAY_PLUGIN_NAME,
				);
				// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- see above.
				$submenu[ $top_slug ][] = array(
					__( 'MCP Connection (Beta)', 'notifybay-waitlist-and-stock-alert-woo' ),
					// manage_options, not manage_notifybay: the "mcp" tab only
					// renders for a manage_options user (see get_mcp_localize()
					// below) -- a lower-capability user following this link
					// would otherwise land on a settings page with an empty
					// tab body.
					'manage_options',
					'admin.php?page=wc-settings&tab=' . \NOTIFYBAY_PLUGIN_NAME . '&notifybay_tab=mcp',
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
		wp_safe_redirect( $this->get_admin_page_url() );
		exit;
	}

	/**
	 * Build the URL of the plugin's main admin screen.
	 *
	 * The screen is registered either as a top-level menu or as a submenu of
	 * another screen. Those two cases produce different URLs, and a submenu
	 * cannot be reached through `admin.php` at all — WordPress looks the page
	 * up under its registered parent, so `admin.php?page=notifybay-products`
	 * resolves to a permissions error rather than the Leads screen. Build the
	 * URL from the same `config/admin.php` data that registered the menu so
	 * the two can never drift apart.
	 *
	 * @access public
	 * @since 1.0.0
	 * @return string Absolute admin URL.
	 */
	public function get_admin_page_url() {
		$config      = $this->get_config();
		$menu_config = $config['menu'] ?? array();
		$top_slug    = $menu_config['top_level']['menu_slug'] ?? null;

		// Read the same live setting add_admin_menu() registers against. Reading
		// the static config here instead meant that turning the toggle off left
		// this returning admin.php?page=<top slug> for a page that was never
		// registered — a permissions error from the activation redirect and from
		// the admin_url handed to the React app.
		$show_main = Settings::get_instance()->get_settings( 'general_showMainMenu', true );

		if ( $show_main && ! empty( $top_slug ) ) {
			return admin_url( 'admin.php?page=' . $top_slug );
		}

		/*
		 * Fall back to a sub-menu that exists in BOTH toggle states, which means
		 * skipping any entry parented to the top-level menu — add_admin_menu()
		 * skips those too when the toggle is off, and their parent_slug is not a
		 * real admin file, so building a URL from one yields a dead screen.
		 */
		foreach ( $menu_config['sub_menus'] ?? array() as $sub ) {
			if ( empty( $sub['menu_slug'] ) || empty( $sub['parent_slug'] ) ) {
				continue;
			}

			if ( $sub['parent_slug'] === $top_slug ) {
				continue;
			}

			// A parent such as `edit.php?post_type=product` already carries a
			// query string, so `page` must be appended with `&`, not `?`.
			$separator = ( false === strpos( $sub['parent_slug'], '?' ) ) ? '?' : '&';

			return admin_url( $sub['parent_slug'] . $separator . 'page=' . $sub['menu_slug'] );
		}

		return admin_url( 'admin.php?page=' . \NOTIFYBAY_PLUGIN_NAME );
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
				'plugin_settings' => apply_filters( 'notifybay_settings_response', Settings::get_instance()->get_settings() ),
				'products_url'    => admin_url( 'edit.php?post_type=product' ),
				'settings_url'    => admin_url( 'admin.php?page=wc-settings&tab=' . \NOTIFYBAY_PLUGIN_NAME ),
				'admin_url'       => $this->get_admin_page_url(),
				'wc_emails_url'   => admin_url( 'admin.php?page=wc-settings&tab=email' ),
				'show_wizard'     => 'yes' !== get_option( 'notifybay_wizard_completed' ),
				'is_pro'          => defined( 'NOTIFYBAY_PRO_VERSION' ),
				'context'         => $context,
				'currency'        => array(
					'code'   => get_woocommerce_currency(),
					'symbol' => get_woocommerce_currency_symbol(),
				),
				'mcp'             => $this->get_mcp_localize(),
			)
		);

		wp_localize_script( $handle, 'notifyBay_Localize', $localize );

		$path_to_check = \NOTIFYBAY_PATH . 'languages';
		wp_set_script_translations(
			$handle,
			'notifybay-waitlist-and-stock-alert-woo',
			$path_to_check
		);
	}

	/**
	 * Data the MCP settings tab needs, or null to hide the tab entirely.
	 *
	 * Two conditions, and both matter:
	 *
	 * `class_exists()` mirrors the guard around `Kit::boot()` in the main
	 * plugin file. If someone strips `vendor/`, NotifyBay keeps working and
	 * simply has no MCP tab, rather than rendering a tab whose every request
	 * 404s.
	 *
	 * `manage_options` mirrors the capability the kit gates its own settings
	 * routes on, which is deliberately narrower than the `manage_woocommerce`
	 * that reaches this screen. A shop manager can open WooCommerce settings
	 * but cannot set MCP policy -- by design, since the whole point is that
	 * the access ceiling is set by someone the AI account's own credential
	 * cannot impersonate. Showing them the tab would only produce a panel
	 * where every control 403s.
	 *
	 * @since 1.0.3
	 * @access private
	 * @return array|null Route base and product key, or null if the tab must not render.
	 */
	private function get_mcp_localize() {
		if ( ! class_exists( '\WPAB\Mcp\Kit' ) || ! current_user_can( 'manage_options' ) ) {
			return null;
		}

		$user = wp_get_current_user();

		// `notifybay` is declared permanent in config/mcp.php, which is the
		// source of truth; it is repeated here rather than requiring that file
		// on every admin screen load.
		return array(
			'product_key'             => 'notifybay',
			'rest_url'                => get_rest_url( null, 'wpab/v1/notifybay' ),
			'app_passwords_url'       => admin_url( 'profile.php#application-passwords-section' ),

			/*
			 * Core's own route, used so the MCP Connection tab can issue and revoke
			 * the credential without sending the reader to their profile screen.
			 * `me` is the security boundary: no request the tab makes names a
			 * user, so there is no target to tamper with.
			 */
			'app_passwords_rest_url'  => get_rest_url( null, 'wp/v2/users/me/application-passwords' ),

			/*
			 * A one-click alternative to hand-editing claude_desktop_config.json.
			 * Claude Desktop cannot add a server to its own config from inside a
			 * conversation, which is what people try first, so the screen offers
			 * the file instead. See Mcp\DesktopBundle.
			 */
			'desktop_bundle_url'      => \NotifyBay\Mcp\DesktopBundle::download_url(),

			/*
			 * Repairs the capability split the status route reports through
			 * has_access_capability. Replaces a `wp cap add` line that was
			 * printed as instructions. See Mcp\GrantAccess.
			 */
			'grant_cap_url'           => \NotifyBay\Mcp\GrantAccess::grant_url(),
			'current_user_login'      => $user && $user->exists() ? $user->user_login : '',

			/*
			 * WordPress refuses Application Passwords over plain http, and an
			 * MCP client has no other way in. Without this the adopter sees an
			 * unexplained 401 and no hint that the transport is the problem.
			 */
			'app_passwords_available' => wp_is_application_passwords_available(),
			'is_local_dev'            => self::is_local_dev(),

			/*
			 * Whether to offer the NODE_TLS_REJECT_UNAUTHORIZED=0 escape hatch,
			 * which disables certificate verification and is therefore offered
			 * as narrowly as possible: only on a development host that is
			 * actually served over https, where a self-signed certificate is the
			 * plausible reason a client cannot connect. On plain http there is no
			 * TLS to verify, so including it there would teach the habit without
			 * ever being the fix.
			 *
			 * It is read by Node either way -- by the bundled bridge and by the
			 * npx proxy the hand-written snippet still uses -- so this governs
			 * both.
			 */
			'offer_tls_bypass'        => self::is_local_dev()
				&& 'https' === strtolower( (string) wp_parse_url( home_url(), PHP_URL_SCHEME ) ),
		);
	}

	/**
	 * Whether this looks like a development host.
	 *
	 * Used only to decide whether the connection details should carry
	 * `NODE_TLS_REJECT_UNAUTHORIZED=0`, which Node needs to talk to a
	 * self-signed certificate. It must never be used to relax a security
	 * decision.
	 *
	 * Public because Mcp\DesktopBundle needs the same answer. It previously
	 * carried its own copy, on the reasoning that a duplicated condition was
	 * safer than a shared one -- and the two promptly disagreed: that copy
	 * treated WP_ENVIRONMENT_TYPE=local as sufficient, so a staging site on a
	 * real https hostname with that variable set got a bundle carrying
	 * NODE_TLS_REJECT_UNAUTHORIZED=0 while this screen showed no warning at
	 * all. One answer, or the screen and the file it hands out will keep
	 * drifting apart.
	 *
	 * @since 1.0.3
	 * @return bool
	 */
	public static function is_local_dev() {
		$host = strtolower( (string) wp_parse_url( home_url(), PHP_URL_HOST ) );

		$is_local = in_array( $host, array( 'localhost', '127.0.0.1', '::1' ), true );

		if ( ! $is_local ) {
			foreach ( array( '.test', '.local', '.localhost', '.lab', '.docker' ) as $tld ) {
				if ( substr( $host, -strlen( $tld ) ) === $tld ) {
					$is_local = true;
					break;
				}
			}
		}

		// A private or reserved IP address is a development host too.
		if ( ! $is_local && filter_var( $host, FILTER_VALIDATE_IP ) ) {
			$is_local = ! filter_var( $host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE );
		}

		return (bool) apply_filters( 'notifybay_mcp_is_local_dev', $is_local, $host );
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
		if ( plugin_basename( \NOTIFYBAY_PATH . 'notifybay-waitlist-and-stock-alert-woo.php' ) !== $plugin_file ) {
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

		$plugin_basename = plugin_basename( \NOTIFYBAY_PATH . 'notifybay-waitlist-and-stock-alert-woo.php' );
		$loader->add_filter( 'plugin_action_links_' . $plugin_basename, $this, 'add_plugin_action_links', 10, 1 );
		$loader->add_filter( 'plugin_row_meta', $this, 'add_plugin_row_meta', 10, 2 );
	}
}
