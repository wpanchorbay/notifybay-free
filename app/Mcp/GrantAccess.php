<?php
/**
 * One-click repair for the MCP access capability.
 *
 * @package    NotifyBay
 * @subpackage Mcp
 * @since      1.0.3
 * @author     WPAnchorBay <sankarsan@wpanchorbay.com>
 */

namespace NotifyBay\Mcp;

use NotifyBay\Core\Base;
use NotifyBay\Core\Plugin;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Grants `wpab_mcp_access` to the administrator role from the connection screen.
 *
 * The endpoint checks `wpab_mcp_access`; the connection screen is gated on
 * `manage_options`. They are different capabilities, so a site whose roles were
 * reset -- by a role editor, a migration, a security plugin -- reaches a state
 * where the screen reports a healthy endpoint while every assistant request is
 * refused. The screen already detected this and said so. What it then told the
 * merchant to do was run `wp cap add administrator wpab_mcp_access`, printed as
 * prose in a notice, which for most stores means finding somebody with shell
 * access to their server. That is not a repair, it is a referral.
 *
 * This does the same thing in one click.
 *
 * It does not fight the kit's own provisioning. Gate::maybe_backfill() is keyed
 * on a stored version rather than on the current state of the role, deliberately,
 * so that an operator who revokes the capability does not have it silently
 * restored on the next page load. That rules out re-granting automatically --
 * but it says nothing against granting it when an administrator asks for it
 * explicitly, which is what this is.
 *
 * @since 1.0.3
 */
class GrantAccess extends Base {

	/**
	 * Query arg for the admin-post action.
	 *
	 * @var string
	 */
	const ACTION = 'notifybay_grant_mcp_cap';

	/**
	 * Nonce action.
	 *
	 * @var string
	 */
	const NONCE = 'notifybay_grant_mcp_cap';

	/**
	 * The capability the MCP transport checks.
	 *
	 * @var string
	 */
	const CAPABILITY = 'wpab_mcp_access';

	/**
	 * Register hooks.
	 *
	 * @param Plugin $plugin The plugin instance.
	 */
	public function run( Plugin $plugin ) {
		$loader = $plugin->get_loader();
		$loader->add_action( 'admin_post_' . self::ACTION, $this, 'handle_grant' );
	}

	/**
	 * The URL the connection screen posts to, nonce included.
	 *
	 * @since 1.0.3
	 * @return string
	 */
	public static function grant_url() {
		/*
		 * add_query_arg(), not wp_nonce_url(): the latter esc_html()s its result,
		 * turning the separating `&` into `&#038;`, and this value is handed to
		 * JavaScript through wp_localize_script(). See DesktopBundle::download_url().
		 */
		return add_query_arg(
			array(
				'action'   => self::ACTION,
				'_wpnonce' => wp_create_nonce( self::NONCE ),
			),
			admin_url( 'admin-post.php' )
		);
	}

	/**
	 * Grant the capability and return to the connection screen.
	 *
	 * @since 1.0.3
	 * @return void
	 */
	public function handle_grant() {
		/*
		 * Granting a capability to an entire role is an administrator action, and
		 * `manage_options` is the same gate that decides whether the connection
		 * screen renders at all.
		 */
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die(
				esc_html__( 'You are not allowed to change roles on this site.', 'notifybay-waitlist-and-stock-alert-woo' ),
				'',
				array( 'response' => 403 )
			);
		}

		check_admin_referer( self::NONCE );

		$role = get_role( 'administrator' );

		if ( $role ) {
			$role->add_cap( self::CAPABILITY );
		}

		/*
		 * Straight back to the tab the button was pressed on, carrying no result
		 * flag. There was one, and it was removed: the screen re-reads the real
		 * state from /status on load, and has_access_capability is the honest
		 * answer -- the banner is simply gone, or it is not. A `granted=1` in the
		 * URL would be a second, weaker claim that could contradict it, which is
		 * exactly what happens on a site defining $wp_user_roles in wp-config.php,
		 * where add_cap() succeeds in memory and persists nothing.
		 */
		wp_safe_redirect(
			add_query_arg(
				array(
					'page'          => 'wc-settings',
					'tab'           => 'notifybay',
					'notifybay_tab' => 'mcp',
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}
}
