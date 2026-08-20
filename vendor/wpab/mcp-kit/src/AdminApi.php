<?php
/**
 * D1 §4.12 `AdminApi` -- REST endpoints so a plugin can render its own MCP
 * settings without the hub. Registered whether or not the product is
 * enabled, because this is the surface that enables it.
 *
 * @package WPAB\Mcp
 */

declare(strict_types=1);

namespace WPAB\Mcp;

/**
 * REST routes so a plugin can render its own MCP settings without the hub.
 */
final class AdminApi {

	private const NAMESPACE = 'wpab/v1';

	/**
	 * Hook the settings routes onto rest_api_init.
	 *
	 * @param string $product_key The consuming plugin's `product_key` (D1 §5).
	 * @param string $manifest_path Absolute path to the plugin's `config/mcp.php`.
	 */
	public static function register( string $product_key, string $manifest_path ): void {
		add_action(
			'rest_api_init',
			static function () use ( $product_key, $manifest_path ) {
				self::register_routes( $product_key, $manifest_path );
			}
		);
	}

	/**
	 * Register this product's status and settings routes.
	 *
	 * @param string $product_key The consuming plugin's `product_key` (D1 §5).
	 * @param string $manifest_path Absolute path to the plugin's `config/mcp.php`.
	 */
	private static function register_routes( string $product_key, string $manifest_path ): void {

		// Gated on manage_options, matching wpab_mcp_toggle_<product_key>
		// (D1 §6) -- NOT wpab_mcp_access, and NOT manage_woocommerce.
		//
		// Reading status escalates nothing, so the capability is the whole
		// gate here.
		$read = static function () {
			return current_user_can( 'manage_options' );
		};

		// D1 §4.12 requires that the surface setting access-level policy be
		// gated on something the AI account does not hold. manage_options
		// alone did not achieve that: the account a store owner connects an
		// assistant as is normally an administrator, so the assistant could
		// POST here with the very credential it was issued and set itself to
		// `full`. The ladder was advisory rather than enforced.
		//
		// A nonce closes it without narrowing the capability. Nonces are
		// bound to a session token that HTTP Basic never establishes, so an
		// Application Password cannot produce a valid one and cannot obtain
		// one -- reading a nonce out of wp-admin requires the cookie session
		// it does not have. The store owner is unaffected: their browser
		// sends X-WP-Nonce on every admin-originated request.
		//
		// Applied to the write only. GET status is read-only, and gating it
		// the same way would break every adopter reading status from a
		// script or CI without changing what an attacker can do.
		$write = static function ( \WP_REST_Request $request ) {

			if ( ! current_user_can( 'manage_options' ) ) {
				return false;
			}

			$nonce = $request->get_header( 'X-WP-Nonce' );

			if ( ! $nonce || ! wp_verify_nonce( $nonce, 'wp_rest' ) ) {
				return new \WP_Error(
					'rest_nonce_invalid',
					__( 'Changing MCP settings requires an authenticated admin session. An application password is not sufficient.', 'wpab-mcp-kit' ),
					[ 'status' => 403 ]
				);
			}

			return true;
		};

		register_rest_route(
			self::NAMESPACE,
			"/{$product_key}/status",
			[
				'methods'             => 'GET',
				'permission_callback' => $read,
				'callback'            => static function () use ( $product_key, $manifest_path ) {
					return rest_ensure_response( self::status( $product_key, $manifest_path ) );
				},
			]
		);

		register_rest_route(
			self::NAMESPACE,
			"/{$product_key}/settings",
			[
				'methods'             => 'POST',
				'permission_callback' => $write,
				'args'                => [
					'enabled'      => [
						'type'     => 'boolean',
						'required' => false,
					],
					'access_level' => [
						'type'     => 'string',
						'required' => false,
					],
				],
				'callback'            => static function ( \WP_REST_Request $request ) use ( $product_key, $manifest_path ) {

					// get_param(), not get_json_params() -- the latter skips
					// the 'args' schema above entirely (no type coercion, no
					// validation, and it is null for a form-encoded POST,
					// which silently no-ops the whole request).
					$payload = [];

					if ( null !== $request->get_param( 'enabled' ) ) {
						$payload['enabled'] = $request->get_param( 'enabled' );
					}

					if ( null !== $request->get_param( 'access_level' ) ) {
						$payload['access_level'] = $request->get_param( 'access_level' );
					}

					Settings::update( $product_key, $payload );

					return rest_ensure_response( self::status( $product_key, $manifest_path ) );
				},
			]
		);
	}

	/**
	 * D1 §4.12: current status, endpoint URL, tool count, available access
	 * levels, a client-config snippet, and declarative settings state --
	 * so adding a kit setting never requires a change to any plugin's own
	 * React panel. Reads its own manifest directly; MUST NOT fire
	 * wpab_mcp_products, which would collect the whole portfolio for what
	 * is meant to be one product's own count.
	 *
	 * @param string $product_key The consuming plugin's `product_key` (D1 §5).
	 * @param string $manifest_path Absolute path to the plugin's `config/mcp.php`.
	 * @return array
	 */
	private static function status( string $product_key, string $manifest_path ): array {

		$manifest = Manifest::load( $manifest_path );
		$state    = Settings::get( $product_key );
		$endpoint = rest_url( "wpab/{$product_key}/mcp" );

		return [
			'product_key'             => $product_key,
			'status'                  => $state['enabled'] ? 'ok' : 'disabled',
			'endpoint'                => $endpoint,
			'tool_count'              => count( $manifest['abilities'] ),
			'available_access_levels' => [ 'read', 'read+modify', 'full' ],
			'client_config'           => self::client_config_snippet( $product_key, $endpoint ),
			'settings'                => [
				[
					'key'   => 'enabled',
					'label' => __( 'Enable MCP', 'wpab-mcp-kit' ),
					'type'  => 'boolean',
					'value' => $state['enabled'],
				],
				[
					'key'     => 'access_level',
					'label'   => __( 'Access level', 'wpab-mcp-kit' ),
					'type'    => 'enum',
					'options' => [ 'read', 'read+modify', 'full' ],
					'value'   => $state['access_level'],
				],
			],
		];
	}

	/**
	 * A ready-to-paste MCP client configuration.
	 *
	 * @param string $product_key The consuming plugin's `product_key` (D1 §5).
	 * @param string $endpoint The product's MCP endpoint URL.
	 * @return array
	 */
	private static function client_config_snippet( string $product_key, string $endpoint ): array {
		return [
			'mcpServers' => [
				$product_key => [
					'command' => 'npx',
					'args'    => [ '-y', '@automattic/mcp-wordpress-remote@latest' ],
					'env'     => [
						'WP_API_URL'      => $endpoint,
						'WP_API_USERNAME' => '',
						'WP_API_PASSWORD' => '',
					],
				],
			],
		];
	}
}
