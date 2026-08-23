<?php
/**
 * D1 §4.4 `Registrar` -- registers the category, then every ability, on
 * WordPress core's own Abilities API hooks. Never hooks at all unless
 * Bootstrap has already decided the product is enabled and the request is
 * REST or WP-CLI (D1 §4.1 steps 2 and 5) -- Registrar trusts that gate
 * rather than re-checking it.
 *
 * @package WPAB\Mcp
 */

declare(strict_types=1);

namespace WPAB\Mcp;

/**
 * Registers the ability category and every ability with core.
 */
final class Registrar {

	/**
	 * Register the manifest's ability category. MUST run before any ability.
	 *
	 * @param string $product_key The consuming plugin's `product_key` (D1 §5).
	 * @param string $manifest_path Absolute path to the plugin's `config/mcp.php`.
	 */
	public static function register_category( string $product_key, string $manifest_path ): void {

		$manifest = Manifest::load( $manifest_path );
		$category = $manifest['category'] ?? null;

		if ( empty( $category['slug'] ) || empty( $category['label'] ) ) {
			// D1 §4.4: a manifest with no category registers zero
			// abilities and the server advertises tools that do not exist.
			Observability::log( 'error', 'Manifest has no category; no abilities will register.', [ 'product_key' => $product_key ] );
			return;
		}

		// Core requires a `description` string as well as a label:
		// WP_Ability_Category::prepare_properties() throws without one, so
		// WP_Ability_Categories_Registry::register() returns null, the
		// category never registers, and then *every* ability is rejected for
		// naming an unregistered category — the exact zero-abilities failure
		// D1 §4.4 warns about. D1 §5 lists only `slug` and `label` as the
		// manifest's category fields, so the kit accepts an optional
		// `description` and falls back to the label rather than making every
		// existing manifest invalid.
		$registered = wp_register_ability_category(
			(string) $category['slug'],
			[
				'label'       => (string) $category['label'],
				'description' => (string) ( $category['description'] ?? $category['label'] ),
			]
		);

		if ( null === $registered ) {
			Observability::log(
				'error',
				'Ability category failed to register; no abilities will register.',
				[
					'product_key' => $product_key,
					'slug'        => $category['slug'],
				]
			);
		}
	}

	/**
	 * Register every validated ability in the manifest.
	 *
	 * @param string $product_key The consuming plugin's `product_key` (D1 §5).
	 * @param string $manifest_path Absolute path to the plugin's `config/mcp.php`.
	 */
	public static function register( string $product_key, string $manifest_path ): void {

		$manifest = Manifest::load( $manifest_path );

		if ( empty( $manifest['category']['slug'] ) ) {
			return; // Logged already in register_category().
		}

		foreach ( $manifest['abilities'] as $ability_key => $ability ) {
			$registered = wp_register_ability(
				(string) $ability_key,
				self::build_args( $product_key, (string) $ability_key, $ability, $manifest['category']['slug'] )
			);

			// Core returns null on *every* registration failure -- a duplicate
			// name, an unregistered category, or any of the ten throws in
			// WP_Ability::prepare_properties() -- and reports it only through
			// _doing_it_wrong(), which is silent in production. Without this
			// check a rejected ability is indistinguishable from a registered
			// one: the product simply serves fewer tools than its manifest
			// lists, with nothing in the log to say which or why.
			//
			// Manifest::validate_ability() already mirrors the prepare_properties()
			// throws the kit knows about; this is the backstop for the ones it
			// does not, including any core adds in a later release.
			if ( null === $registered ) {
				Observability::log(
					'error',
					'Ability was rejected by the Abilities API and will not be served.',
					[
						'product_key' => $product_key,
						'ability'     => $ability_key,
					]
				);
			}
		}
	}

	/**
	 * Build the argument array core's wp_register_ability() expects.
	 *
	 * @param string $product_key The consuming plugin's `product_key` (D1 §5).
	 * @param string $ability_key Full ability key, `<product_key>/<verb>-<noun>`.
	 * @param array  $ability One manifest ability entry.
	 * @param string $category_slug The registered category slug every ability must name.
	 * @return array
	 */
	private static function build_args( string $product_key, string $ability_key, array $ability, string $category_slug ): array {

		$is_tombstone = isset( $ability['deprecated'] );

		return [
			'label'               => $ability['label'],
			'description'         => $ability['description'] ?? '',
			'category'            => $category_slug,
			'input_schema'        => $is_tombstone
				? self::tombstone_schema( $ability )
				: self::input_schema( $ability['input_schema'] ?? [ 'type' => 'object' ] ),
			'execute_callback'    => $is_tombstone
				? self::tombstone_callback( $ability )
				: self::wrap_execute( $product_key, $ability_key, $ability ),
			'permission_callback' => self::build_permission_callback( $product_key, $ability_key, $ability ),
			// D1 §7.4 mitigations 2 and 3: never set 'mcp.public' or
			// 'show_in_rest', even if a manifest author copied them in --
			// meta is rebuilt here rather than passed through.
			'meta'                => [],
		];
	}

	/**
	 * An empty `properties` cannot be spelled the same way on both sides of
	 * this boundary, so it is spelled on neither: it is dropped.
	 *
	 * The array is used twice. Core validates against it, and
	 * rest_validate_object_value_from_schema() does
	 * `isset( $args['properties'][ $property ] )` for every key the caller
	 * sent -- which fatals with "Cannot use object of type stdClass as array"
	 * if `properties` is an empty object. So it may not be stdClass.
	 *
	 * The adapter also serialises the same array straight into the advertised
	 * inputSchema, where an empty PHP array becomes JSON `[]`. JSON Schema
	 * requires `properties` to be an object, and a strict client rejects
	 * `tools/list` *as a whole* over one bad entry -- so a single argument-less
	 * tool takes down every other tool the product publishes. Observed against
	 * Claude Code: "tools.1.inputSchema.properties: expected record, received
	 * array", and no NotifyBay tools available at all.
	 *
	 * Omitting the key satisfies both. `isset()` on a missing key is false, so
	 * core is happy, and `{"type":"object"}` is valid JSON Schema meaning the
	 * same thing as an empty properties map.
	 *
	 * A previous comment here claimed the adapter already omitted an empty
	 * properties list. It does not: McpTool::to_array() substitutes
	 * `['type' => 'object']` only when the *whole* schema is empty, and the
	 * kit's own default supplied a non-empty one, which defeated it.
	 *
	 * @param array $schema One ability's declared input schema.
	 * @return array
	 */
	private static function input_schema( array $schema ): array {

		if ( isset( $schema['properties'] ) && is_array( $schema['properties'] ) ) {
			if ( [] === $schema['properties'] ) {
				unset( $schema['properties'] );
			} else {
				// Nested object properties serialise through the same path and
				// fail the same way, so this is not only a top-level concern.
				foreach ( $schema['properties'] as $name => $sub ) {
					if ( is_array( $sub ) ) {
						$schema['properties'][ $name ] = self::input_schema( $sub );
					}
				}
			}
		}

		if ( isset( $schema['items'] ) && is_array( $schema['items'] ) ) {
			$schema['items'] = self::input_schema( $schema['items'] );
		}

		return $schema;
	}

	/**
	 * D1 §5: a tombstone **MUST** accept the superseded arguments rather than
	 * rejecting them — a client calling the old name sends the *old* input,
	 * and if core's validation refuses it the explanatory error never reaches
	 * the model. `additionalProperties: true` is forced here rather than left
	 * to each manifest author, because a tombstone that omits it fails
	 * silently in exactly the case it exists to handle.
	 *
	 * @param array $ability One manifest ability entry.
	 * @return array
	 */
	private static function tombstone_schema( array $ability ): array {

		$schema = $ability['input_schema'] ?? [];

		if ( ! is_array( $schema ) ) {
			$schema = [];
		}

		$schema['type']                 = 'object';
		$schema['additionalProperties'] = true;

		// A tombstone exists precisely to be called with the *old* arguments,
		// so it is the one schema guaranteed to receive keys it does not
		// declare -- which additionalProperties above already permits. An
		// empty properties map adds nothing and breaks the advertised schema,
		// so it is dropped rather than synthesised. See input_schema().
		if ( isset( $schema['properties'] ) && ! is_array( $schema['properties'] ) ) {
			unset( $schema['properties'] );
		}

		// A tombstone accepts whatever the old tool took, so nothing is
		// required -- a leftover `required` list would reject the old input.
		unset( $schema['required'] );

		return self::input_schema( $schema );
	}

	/**
	 * D1 §10: a tombstone keeps the old name registered and returns "a
	 * structured error naming its replacement". That is its entire behaviour,
	 * so the kit generates it and any handler the manifest supplies is
	 * ignored — Manifest::validate_ability() correspondingly does not require
	 * one for a deprecated entry.
	 *
	 * @param array $ability One manifest ability entry.
	 * @return callable
	 */
	private static function tombstone_callback( array $ability ): callable {

		$since       = (string) $ability['deprecated']['since'];
		$replacement = (string) $ability['deprecated']['replacement'];

		// The manifest declares the replacement as an ability key
		// (`<product_key>/new-name`, per D1 §5), but this message is read by
		// a model that calls **wire** names, and the slashed form is not
		// callable — the adapter keys its registry by the rewritten name and
		// nothing reverses the transform. Naming the ability key here would
		// send the client straight into a second "tool not found", which is
		// the exact failure the tombstone exists to prevent.
		$wire_name = str_replace( '/', '-', $replacement );

		return static function () use ( $since, $replacement, $wire_name ) {
			// A WP_Error rather than an Envelope::error() array, on purpose.
			// The adapter converts a WP_Error returned from execute into the
			// protocol's own isError:true response and surfaces the code as
			// _metadata.failure_reason; an Envelope array comes back as a
			// *successful* result whose body happens to contain an error, so
			// a client checking isError — the signal that actually travels —
			// would treat a call to a removed tool as having worked.
			return new \WP_Error(
				'tool_deprecated',
				sprintf(
					/* translators: 1: version the tool was removed in, 2: replacement tool name as called on the wire */
					__( 'This tool was removed in %1$s. Use %2$s instead.', 'wpab-mcp-kit' ),
					$since,
					$wire_name
				),
				[
					'since'           => $since,
					'replacement'     => $wire_name,
					'replacement_key' => $replacement,
				]
			);
		};
	}

	/**
	 * D1 §4.4 / §7.1: authorization order is (1) wpab_mcp_access,
	 * (2) the access-level check, (3) any capability the manifest declares
	 * -- and every denial is a WP_Error, never a bare `false`.
	 *
	 * @param string $product_key The consuming plugin's `product_key` (D1 §5).
	 * @param string $ability_key Full ability key, `<product_key>/<verb>-<noun>`.
	 * @param array  $ability One manifest ability entry.
	 * @return callable
	 */
	private static function build_permission_callback( string $product_key, string $ability_key, array $ability ): callable {

		return static function () use ( $product_key, $ability_key, $ability ) {

			if ( ! current_user_can( 'wpab_mcp_access' ) ) {
				return new \WP_Error(
					'wpab_mcp_forbidden',
					__( 'This account does not hold wpab_mcp_access.', 'wpab-mcp-kit' ),
					[ 'status' => 403 ]
				);
			}

			$level = Settings::get_access_level( $product_key );
			$risk  = $ability['risk'] ?? 'destructive';

			if ( ! Gate::risk_permitted_at_level( $risk, $level ) ) {
				return new \WP_Error(
					'wpab_mcp_access_level',
					__( 'This tool requires a higher access level than the product is currently set to.', 'wpab-mcp-kit' ),
					[ 'status' => 403 ]
				);
			}

			if ( isset( $ability['capability'] ) ) {
				$guard_result = Guard::authorize( $ability_key, $ability['capability'] );

				if ( is_wp_error( $guard_result ) ) {
					return $guard_result;
				}
			}

			return true;
		};
	}

	/**
	 * D1 §4.4: catches every Throwable, logs it, and returns a structured
	 * internal_error -- authorization is not done here, it already ran in
	 * permission_callback before core reached execute().
	 *
	 * @param string $product_key The consuming plugin's `product_key` (D1 §5).
	 * @param string $ability_key Full ability key, `<product_key>/<verb>-<noun>`.
	 * @param array  $ability One manifest ability entry.
	 * @return callable
	 */
	private static function wrap_execute( string $product_key, string $ability_key, array $ability ): callable {

		return static function ( $input ) use ( $product_key, $ability_key, $ability ) {

			$risk         = $ability['risk'] ?? 'destructive';
			$access_level = Settings::get_access_level( $product_key );

			try {
				$result = call_user_func( $ability['handler'], $input );

				if ( 'readonly' !== $risk ) {
					$affected = ( is_array( $result ) && isset( $result['affected'] ) ) ? $result['affected'] : null;
					Observability::log_tool_call( $ability_key, $access_level, $input, is_wp_error( $result ) ? 'error' : 'success', $affected );

					// `affected` is this kit's contract with the handler, not
					// part of the tool's answer, so it is removed once the log
					// has it. Left in, it reached the client as an extra
					// undocumented key beside the real ones -- and a model
					// cannot tell an internal audit field from a result it is
					// supposed to interpret, so it either invents a meaning or
					// reports it as data.
					if ( is_array( $result ) ) {
						unset( $result['affected'] );
					}
				}

				// Returned raw, not through Envelope::error() -- verified
				// against the adapter's ToolsHandler, which explicitly
				// detects a WP_Error returned here and converts it to the
				// MCP protocol's own isError:true response. Routing it
				// through Envelope::error() instead would bury it inside a
				// *successful* tool result shaped like {"error": {...}},
				// which is worse: a client checking `isError` -- the correct
				// MCP-level signal -- would never see it.
				if ( is_wp_error( $result ) ) {
					return $result;
				}

				return Envelope::success( $result );

			} catch ( \Throwable $e ) {

				if ( 'readonly' !== $risk ) {
					Observability::log_tool_call( $ability_key, $access_level, $input, 'exception' );
				}

				Observability::log(
					'error',
					'Tool execution threw.',
					[
						'product_key' => $product_key,
						'ability'     => $ability_key,
						'message'     => $e->getMessage(),
					]
				);

				// A WP_Error, not Envelope::error(), for the same reason the
				// tombstone returns one: an Envelope array is delivered as a
				// *successful* tool result whose body merely contains an
				// error, so the adapter reports isError:false and a client
				// checking the protocol-level flag treats a crashed tool as
				// having worked. Verified against a live call before changing
				// it. The structured parts survive -- the adapter surfaces a
				// WP_Error's code as _metadata.failure_reason, and `retryable`
				// rides along in the data array.
				//
				// D1 §4.4 says this path is "shaped through Envelope (§4.6)".
				// That instruction, followed literally, is what produced the
				// isError:false behaviour, so it is a spec defect rather than
				// a liberty taken here.
				return new \WP_Error(
					'internal_error',
					__( 'Something went wrong running this tool.', 'wpab-mcp-kit' ),
					[
						'retryable' => true,
						'status'    => 500,
					]
				);
			}
		};
	}
}
