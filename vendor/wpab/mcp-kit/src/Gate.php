<?php
/**
 * D1 §4.7 `Gate` -- the security boundary. Full model in D8.
 *
 * @package WPAB\Mcp
 */

declare(strict_types=1);

namespace WPAB\Mcp;

/**
 * The security boundary: transport permission and the access ladder.
 */
final class Gate {

	/**
	 * Bumped when a release needs to re-run the capability grant.
	 *
	 * @var int
	 */
	private const CAPS_VERSION = 1;

	/**
	 * Option recording the CAPS_VERSION this site has been repaired to.
	 *
	 * @var string
	 */
	private const CAPS_VERSION_OPT = 'wpab_mcp_caps_version';

	private const LADDER = [
		'read'        => [ 'readonly' ],
		'read+modify' => [ 'readonly', 'idempotent' ],
		'full'        => [ 'readonly', 'idempotent', 'destructive' ],
	];

	/**
	 * Grant the shared capability on activation. The register_activation_hook()
	 * callback body, called from Kit::boot().
	 * MUST add wpab_mcp_access to administrator, idempotently, and MUST
	 * NEVER be undone on deactivation or uninstall -- see D1 §4.11.
	 *
	 * Returns whether the administrator role was found and therefore whether the
	 * grant can be relied on. maybe_backfill() needs that answer: this method
	 * silently no-ops when get_role() returns null, and recording a one-shot
	 * repair as done when nothing was granted is unrecoverable.
	 *
	 * @return bool True when the administrator role exists and holds the cap.
	 */
	public static function activate(): bool {
		$role = get_role( 'administrator' );

		if ( ! $role ) {
			return false;
		}

		if ( ! $role->has_cap( 'wpab_mcp_access' ) ) {
			$role->add_cap( 'wpab_mcp_access' );
		}

		return true;
	}

	/**
	 * Grant the capability on *upgrade*, not only on activation.
	 *
	 * WordPress does not fire register_activation_hook() when a plugin is
	 * updated in place, and activate() above was the only thing that ever
	 * granted wpab_mcp_access. So every store that already had a kit-using
	 * plugin active and then received the release that introduced MCP got an
	 * endpoint that answers 403 to everyone, forever, while the settings screen
	 * reported it healthy -- AdminApi gates on manage_options, which the admin
	 * does hold. Only a manual deactivate/reactivate cleared it.
	 *
	 * Keyed on a stored version rather than on whether the role currently holds
	 * the capability. That difference is deliberate: an operator who revokes
	 * wpab_mcp_access from administrators must not have it silently re-added on
	 * the next request. The trade-off is that if the roles are later reset while
	 * this flag stays stamped -- a role-reset plugin, a partial migration that
	 * copies wp_options but rebuilds wp_user_roles -- the site is back to a 403
	 * with no retry. That is narrower than the bug this fixes, but harder to
	 * diagnose, because the running code looks patched. AdminApi's status
	 * payload reports the capability so that state is visible.
	 *
	 * One global option is correct: wpab_mcp_access is a single shared
	 * capability on the administrator role, not a per-product grant. Several
	 * scoped copies of this class may each call this method in one request --
	 * they are distinct classes and do not de-duplicate -- and that is harmless
	 * because the first to run stamps the flag and the rest return here.
	 *
	 * @return void
	 */
	public static function maybe_backfill(): void {
		if ( (int) get_option( self::CAPS_VERSION_OPT, 0 ) >= self::CAPS_VERSION ) {
			return;
		}

		if ( ! self::activate() ) {
			return;
		}

		// A site that defines $wp_user_roles in wp-config.php has
		// WP_Roles::$use_db === false, and WP_Roles::add_cap() then updates only
		// its in-memory arrays -- the update_option() call is inside
		// `if ( $this->use_db )`. The grant looks like it worked (has_cap() is
		// true for the rest of the request) and is gone on the next one.
		// Stamping here would turn a broken site into a permanently broken one
		// that never retries, so leave the flag unset and try again next time.
		if ( ! wp_roles()->use_db ) {
			return;
		}

		// Autoload off: only admin, REST and CLI requests ever reach
		// Bootstrap::run(), so no front-end page view reads this row.
		update_option( self::CAPS_VERSION_OPT, self::CAPS_VERSION, false );
	}

	/**
	 * The transport permission callback -- create_server() argument 13.
	 *
	 * MUST return boolean false to deny, never WP_Error.
	 *
	 * On mcp-adapter 0.1.0 -- the copy WooCommerce vendors --
	 * HttpTransport::check_permission() treats a WP_Error from this callback
	 * as the *callback itself* having failed: it logs the error and falls
	 * through to `current_user_can('read')`, which every logged-in user
	 * holds. A WP_Error there is a silent, total bypass.
	 *
	 * 0.5.0 -- shipped by fluent-toolkit -- returns false instead and fails
	 * closed. Do not read that as the rule being obsolete. Which copy wins on
	 * a site carrying both depends on load order, and this callback cannot
	 * tell which one is calling it. Returning false is correct under either.
	 *
	 * This is the opposite of the rule for an ability *handler*, where
	 * returning WP_Error is correct and the adapter converts it into the
	 * protocol's own isError. Two callbacks, opposite rules.
	 *
	 * @param \WP_REST_Request $request The incoming REST request.
	 * @param string           $product_key The consuming plugin's `product_key` (D1 §5).
	 * @return bool
	 */
	public static function transport( \WP_REST_Request $request, string $product_key ): bool {

		if ( ! is_user_logged_in() ) {
			return false;
		}

		if ( ! current_user_can( 'wpab_mcp_access' ) ) {
			return false;
		}

		// Defence in depth, normally unreachable: a disabled product
		// creates no server and registers no route, so this callback
		// never runs for one. Checked last so a caller in that degraded
		// state gets an authentication failure, not confirmation the
		// product exists but is switched off.
		if ( ! Settings::is_enabled( $product_key ) ) {
			return false;
		}

		return true;
	}

	/** D1 §4.7's access ladder, applied identically at listing and execution time. */
	/**
	 * Risk permitted at level.
	 *
	 * @param string $risk The ability's declared risk tier.
	 * @param string $level Access level in force: `read`, `read+modify` or `full`.
	 * @return bool
	 */
	public static function risk_permitted_at_level( string $risk, string $level ): bool {
		return in_array( $risk, self::LADDER[ $level ] ?? self::LADDER['read'], true );
	}

	/** Ability names permitted at the current level -- the tools/list filtering half of §4.7. */
	/**
	 * Filter tools for level.
	 *
	 * @param array  $abilities Ability entries keyed by ability key.
	 * @param string $level Access level in force: `read`, `read+modify` or `full`.
	 * @return array
	 */
	public static function filter_tools_for_level( array $abilities, string $level ): array {
		return array_filter(
			$abilities,
			static function ( array $ability ) use ( $level ) {
				return self::risk_permitted_at_level( $ability['risk'] ?? 'destructive', $level );
			}
		);
	}
}
