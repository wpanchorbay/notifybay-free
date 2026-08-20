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
	 */
	public static function activate(): void {
		$role = get_role( 'administrator' );

		if ( $role && ! $role->has_cap( 'wpab_mcp_access' ) ) {
			$role->add_cap( 'wpab_mcp_access' );
		}
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
