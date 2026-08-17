<?php
/**
 * D1 §4.8 `Settings` -- per-product `enabled` and `access_level`, one
 * option row per product. A WordPress option is a single serialized row
 * with no per-key write, so a shared array keyed by product_key would make
 * every toggle a read-modify-write race; one row each makes that
 * interleaving structurally impossible rather than merely forbidden.
 *
 * @package WPAB\Mcp
 */

declare(strict_types=1);

namespace WPAB\Mcp;

/**
 * Per-product `enabled` and `access_level`, one option row each.
 */
final class Settings {

	private const DEFAULTS = [
		'enabled'      => false,
		'access_level' => 'read',
	];

	private const VALID_LEVELS = [ 'read', 'read+modify', 'full' ];

	/**
	 * The option row name for one product.
	 *
	 * @param string $product_key The consuming plugin's `product_key` (D1 §5).
	 * @return string
	 */
	public static function option_name( string $product_key ): string {
		return "wpab_mcp_settings_{$product_key}";
	}

	/**
	 * This product's settings, with defaults merged in.
	 *
	 * @param string $product_key The consuming plugin's `product_key` (D1 §5).
	 * @return array
	 */
	public static function get( string $product_key ): array {
		$stored = get_option( self::option_name( $product_key ), [] );
		return array_merge( self::DEFAULTS, is_array( $stored ) ? $stored : [] );
	}

	/**
	 * Whether MCP is switched on for this product.
	 *
	 * @param string $product_key The consuming plugin's `product_key` (D1 §5).
	 * @return bool
	 */
	public static function is_enabled( string $product_key ): bool {
		return (bool) self::get( $product_key )['enabled'];
	}

	/**
	 * The access level in force, normalised to a known value.
	 *
	 * @param string $product_key The consuming plugin's `product_key` (D1 §5).
	 * @return string
	 */
	public static function get_access_level( string $product_key ): string {
		$level = self::get( $product_key )['access_level'];
		return in_array( $level, self::VALID_LEVELS, true ) ? $level : 'read';
	}

	/**
	 * Registered once per request, only when Bootstrap step 1's gate
	 * passes (admin/REST/CLI). Autoload MUST be off -- only REST and
	 * WP-CLI requests ever read this row.
	 *
	 * @param string $product_key The consuming plugin's `product_key` (D1 §5).
	 */
	public static function listen_toggle( string $product_key ): void {
		add_action(
			"wpab_mcp_toggle_{$product_key}",
			static function ( array $payload = [] ) use ( $product_key ) {
				self::handle_toggle( $product_key, $payload );
			}
		);
	}

	/**
	 * D1 §4.8: re-checks manage_options itself rather than trusting the
	 * caller (a hook is callable by anything on the site); rejects an
	 * unrecognised access_level rather than storing it, since a newer hub
	 * can send a level this kit copy has never heard of; writes only its
	 * own row. Returns nothing -- there is deliberately no result hook,
	 * see D1 §4.1 step 1's docblock in Bootstrap.
	 *
	 * @param string $product_key The consuming plugin's `product_key` (D1 §5).
	 * @param array  $payload Partial settings: `enabled` and/or `access_level`.
	 */
	private static function handle_toggle( string $product_key, array $payload ): void {

		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		self::update( $product_key, $payload );
	}

	/**
	 * Shared by the wpab_mcp_toggle_<product_key> hook (D1 §4.8) and
	 * AdminApi's settings route (D1 §4.12) -- both are manage_options-only
	 * writers of the same row, and both reject an unrecognised
	 * access_level rather than storing it. Callers are responsible for
	 * their own capability check; this method does not repeat it.
	 *
	 * @param string $product_key The consuming plugin's `product_key` (D1 §5).
	 * @param array  $payload Partial settings: `enabled` and/or `access_level`.
	 * @return array
	 */
	public static function update( string $product_key, array $payload ): array {

		$current = self::get( $product_key );

		if ( array_key_exists( 'enabled', $payload ) ) {
			$current['enabled'] = (bool) $payload['enabled'];
		}

		if ( array_key_exists( 'access_level', $payload ) ) {
			if ( ! in_array( $payload['access_level'], self::VALID_LEVELS, true ) ) {
				return self::get( $product_key ); // Reject the whole write, return the unchanged state.
			}
			$current['access_level'] = $payload['access_level'];
		}

		update_option( self::option_name( $product_key ), $current, false );

		return $current;
	}

	/**
	 * D1 §4.11: uninstall is deliberately almost empty. Deletes this
	 * product's own row, and nothing else -- never wpab_mcp_access, never
	 * another product's row, never an Application Password.
	 *
	 * @param string $product_key The consuming plugin's `product_key` (D1 §5).
	 */
	public static function uninstall( string $product_key ): void {
		delete_option( self::option_name( $product_key ) );
	}
}
