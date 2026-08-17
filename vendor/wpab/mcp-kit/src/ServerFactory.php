<?php
/**
 * D1 §4.2 `ServerFactory` -- hooks mcp_adapter_init and calls
 * create_server() with everything the manifest and the host plugin's own
 * header supply.
 *
 * @package WPAB\Mcp
 */

declare(strict_types=1);

namespace WPAB\Mcp;

/**
 * Calls the adapter's create_server() with what the manifest and plugin header supply.
 */
final class ServerFactory {

	/**
	 * Create this product's MCP server on mcp_adapter_init.
	 *
	 * @param string $file The consuming plugin's `__FILE__`.
	 * @param string $product_key The consuming plugin's `product_key` (D1 §5).
	 * @param string $manifest_path Absolute path to the plugin's `config/mcp.php`.
	 */
	public static function create( string $file, string $product_key, string $manifest_path ): void {

		$manifest = Manifest::load( $manifest_path );

		if ( empty( $manifest['product_key'] ) ) {
			Observability::log( 'error', 'ServerFactory: manifest failed to load; no server created.', [ 'product_key' => $product_key ] );
			return;
		}

		$plugin_data = self::plugin_data( $file );
		$level       = Settings::get_access_level( $product_key );
		$tool_names  = array_keys( Gate::filter_tools_for_level( $manifest['abilities'], $level ) );

		// `create_server()` requires three non-nullable strings the
		// manifest does not carry -- name, description, version. Do not
		// invent defaults: a server advertising version 0.0.0 to every
		// client is worse than a build-time failure (D1 §4.2).
		if ( '' === $plugin_data['Version'] ) {
			Observability::log( 'error', 'ServerFactory: host plugin version unavailable via get_plugin_data(); no server created.', [ 'product_key' => $product_key ] );
			return;
		}

		try {
			$result = \WP\MCP\Core\McpAdapter::instance()->create_server(
				"wpab-{$product_key}",
				'wpab',
				"{$product_key}/mcp",
				$manifest['label'],
				$plugin_data['Description'],
				$plugin_data['Version'],
				[ \WP\MCP\Transport\HttpTransport::class ],
				\WP\MCP\Infrastructure\ErrorHandling\ErrorLogMcpErrorHandler::class,
				// The bridge, not Observability itself -- see
				// ObservabilityBridge's docblock. This line is the only place
				// in the kit that names it, which is what keeps the adapter
				// interface off the degraded path.
				ObservabilityBridge::class,
				$tool_names,
				[],
				[],
				static function ( \WP_REST_Request $request ) use ( $product_key ) {
					return Gate::transport( $request, $product_key );
				}
			);
		} catch ( \Throwable $e ) {
			Observability::log(
				'error',
				'create_server() threw.',
				[
					'product_key' => $product_key,
					'message'     => $e->getMessage(),
				]
			);
			return;
		}

		// Checking the return is mandatory and is two checks, not one --
		// create_server() returns WP_Error on several conditions, and can
		// additionally throw, which the try/catch above already covers.
		if ( is_wp_error( $result ) ) {
			Observability::log(
				'error',
				'create_server() returned WP_Error.',
				[
					'product_key' => $product_key,
					'message'     => $result->get_error_message(),
				]
			);
			return;
		}

		self::hide_from_rest_index( $product_key );
	}

	/**
	 * D1 §4.2: the MCP route MUST be registered with 'show_in_index' =>
	 * false. The installed wordpress/mcp-adapter's own HttpTransport::
	 * register_routes() does not expose that option -- its
	 * register_rest_route() call has no show_in_index key at all, so the
	 * route defaults to WordPress core's own default of `true` (verified
	 * against the vendored package, not assumed). This is the workaround:
	 * WP_REST_Server itself filters every compiled route through
	 * `rest_endpoints` before building the index, so the kit forces the
	 * flag here rather than at the point of registration it does not own.
	 *
	 * @param string $product_key The consuming plugin's `product_key` (D1 §5).
	 */
	private static function hide_from_rest_index( string $product_key ): void {

		add_filter(
			'rest_endpoints',
			static function ( array $endpoints ) use ( $product_key ) {

				$route = "/wpab/{$product_key}/mcp";

				if ( ! isset( $endpoints[ $route ] ) ) {
					return $endpoints;
				}

				foreach ( $endpoints[ $route ] as &$registration ) {
					if ( is_array( $registration ) ) {
						$registration['show_in_index'] = false;
					}
				}
				unset( $registration );

				return $endpoints;
			}
		);
	}

	/**
	 * The host plugin's own header fields, which create_server() requires as
	 * non-nullable strings.
	 *
	 * @param string $file The consuming plugin's `__FILE__`.
	 * @return array
	 */
	private static function plugin_data( string $file ): array {

		if ( ! function_exists( '\get_plugin_data' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$data = get_plugin_data( $file, false, false );

		return [
			'Version'     => (string) ( $data['Version'] ?? '' ),
			'Description' => (string) ( $data['Description'] ?? '' ),
		];
	}
}
