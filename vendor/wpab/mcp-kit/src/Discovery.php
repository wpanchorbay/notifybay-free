<?php
/**
 * D1 §4.10 `Discovery` -- the kit registers a callback on
 * `wpab_mcp_products`; it never fires that filter. Only the hub does.
 *
 * @package WPAB\Mcp
 */

declare(strict_types=1);

namespace WPAB\Mcp;

/**
 * Announces this product to the hub, and only when the hub asks.
 */
final class Discovery {

	/**
	 * Register the announcement callback. add_filter() is O(1) and touches no manifest -- the expensive work
	 * happens inside the closure below, and therefore only when a
	 * consumer (the hub) actually calls apply_filters('wpab_mcp_products', []).
	 * Nothing on an ordinary admin/REST/CLI request does, so the
	 * announcement costs nothing until the hub renders.
	 *
	 * @param string $product_key The consuming plugin's `product_key` (D1 §5).
	 * @param string $manifest_path Absolute path to the plugin's `config/mcp.php`.
	 */
	public static function register( string $product_key, string $manifest_path ): void {
		add_filter(
			'wpab_mcp_products',
			static function ( array $products ) use ( $product_key, $manifest_path ) {
				$products[] = self::describe( $product_key, $manifest_path );
				return $products;
			}
		);
	}

	/**
	 * Describe this product for the hub.
	 *
	 * @param string $product_key The consuming plugin's `product_key` (D1 §5).
	 * @param string $manifest_path Absolute path to the plugin's `config/mcp.php`.
	 * @return array
	 */
	private static function describe( string $product_key, string $manifest_path ): array {

		if ( ! class_exists( '\WP\MCP\Core\McpAdapter' ) || ! function_exists( '\wp_register_ability' ) ) {
			return self::base( $product_key, 'unavailable', 0, 0 );
		}

		$manifest = Manifest::load( $manifest_path );

		if ( empty( $manifest['product_key'] ) ) {
			return self::base( $product_key, 'error', 0, 0 );
		}

		$level      = Settings::get_access_level( $product_key );
		$tool_count = count( $manifest['abilities'] );
		$available  = count( Gate::filter_tools_for_level( $manifest['abilities'], $level ) );

		$status = Settings::is_enabled( $product_key ) ? 'ok' : 'disabled';

		return array_merge(
			self::base( $product_key, $status, $tool_count, $available ),
			[ 'label' => $manifest['label'] ]
		);
	}

	/**
	 * The fields every discovery payload carries, whatever the state.
	 *
	 * @param string $product_key The consuming plugin's `product_key` (D1 §5).
	 * @param string $status     `ok`, `disabled`, `unavailable` or `error`.
	 * @param int    $tool_count Tools the manifest declares.
	 * @param int    $available  Tools reachable at the current access level.
	 * @return array
	 */
	private static function base( string $product_key, string $status, int $tool_count, int $available ): array {
		return [
			'product_key'     => $product_key,
			'label'           => $product_key,
			'endpoint'        => rest_url( "wpab/{$product_key}/mcp" ),
			'enabled'         => Settings::is_enabled( $product_key ),
			'access_level'    => Settings::get_access_level( $product_key ),
			'tool_count'      => $tool_count,
			'available_count' => $available,
			'status'          => $status,
			'kit_version'     => Kit::VERSION,
		];
	}
}
