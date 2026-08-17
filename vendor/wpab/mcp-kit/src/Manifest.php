<?php
/**
 * D1 §4.3 `Manifest` -- loads, validates and normalises the plugin's
 * definition array (D1 §5), and D1 §7.5's union-type ban at registration
 * time (core's schema validation covers everything else at call time).
 *
 * @package WPAB\Mcp
 */

declare(strict_types=1);

namespace WPAB\Mcp;

/**
 * Loads, validates and normalises the plugin's manifest.
 */
final class Manifest {

	/**
	 * Loaded, validated manifests, memoised by path.
	 *
	 * @var array<string, array|null>
	 */
	private static array $cache = [];

	/**
	 * The manifest, fully loaded, Pro-filtered, and validated. Memoized per
	 * path so N callers (Registrar, ServerFactory, AdminApi) within one
	 * request only ever `require` the file once.
	 *
	 * MUST NOT be called from Bootstrap step 1 -- see Bootstrap::run()'s
	 * docblock on peek_product_key() for why.
	 *
	 * @param string $path Absolute path to the manifest file.
	 * @return array
	 */
	public static function load( string $path ): array {

		if ( array_key_exists( $path, self::$cache ) && null !== self::$cache[ $path ] ) {
			return self::$cache[ $path ];
		}

		$raw = self::require_manifest( $path );

		if ( null === $raw ) {
			self::$cache[ $path ] = [
				'product_key' => '',
				'label'       => '',
				'category'    => null,
				'abilities'   => [],
			];
			return self::$cache[ $path ];
		}

		$product_key = (string) ( $raw['product_key'] ?? '' );

		// D1 §6: Pro add-ons contribute abilities through this filter,
		// entirely inside one plugin's family -- it never crosses the hub
		// boundary.
		$abilities = apply_filters(
			"wpab_mcp_manifest_{$product_key}",
			$raw['abilities'] ?? []
		);

		$validated = [];

		foreach ( $abilities as $key => $ability ) {
			$error = self::validate_ability( (string) $key, $ability );

			if ( null !== $error ) {
				Observability::log(
					'warning',
					$error,
					[
						'ability'     => $key,
						'product_key' => $product_key,
					]
				);
				continue; // MUST reject and log, without aborting the rest.
			}

			$validated[ $key ] = $ability;
		}

		self::$cache[ $path ] = [
			'product_key' => $product_key,
			'label'       => (string) ( $raw['label'] ?? '' ),
			'category'    => $raw['category'] ?? null,
			'abilities'   => $validated,
		];

		return self::$cache[ $path ];
	}

	/**
	 * D1 §4.1 step 1 needs product_key to construct
	 * `wpab_mcp_toggle_<product_key>` before it is otherwise safe to touch
	 * the manifest (see Bootstrap::run()). This reads the raw file *text*
	 * with no `require`, so no __() call in a label/category/ability and no
	 * array construction happens -- only a regex against the source.
	 *
	 * Deliberately narrow: it only ever has to find one literal string.
	 *
	 * @param string $path Absolute path to the manifest file.
	 * @return string|null
	 */
	public static function peek_product_key( string $path ): ?string {

		if ( ! is_readable( $path ) ) {
			return null;
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- A local manifest file inside the consuming plugin, never a URL; wp_remote_get() cannot read it. Read as text on purpose, so the file is not executed this early (see the docblock above).
		$contents = file_get_contents( $path );

		if ( false === $contents ) {
			return null;
		}

		if ( preg_match( '/[\'"]product_key[\'"]\s*=>\s*[\'"]([a-z0-9]+)[\'"]/', $contents, $matches ) ) {
			return $matches[1];
		}

		return null;
	}

	/**
	 * Load the manifest file, or null if it is unusable.
	 *
	 * @param string $path Absolute path to the manifest file.
	 * @return array|null
	 */
	private static function require_manifest( string $path ): ?array {

		if ( ! is_readable( $path ) ) {
			Observability::log( 'error', 'Manifest file not readable.', [ 'path' => $path ] );
			return null;
		}

		$data = require $path;

		if ( ! is_array( $data ) ) {
			Observability::log( 'error', 'Manifest did not return an array.', [ 'path' => $path ] );
			return null;
		}

		return $data;
	}

	/**
	 * D1 §5's field rules, plus D1 §7.5's union-type ban -- the one piece
	 * of validation core's own schema pass does not cover, because it has
	 * to be enforced at registration time rather than call time.
	 *
	 * @param string $key The ability key being validated.
	 * @param array  $ability One manifest ability entry.
	 * @return string|null
	 */
	private static function validate_ability( string $key, $ability ): ?string {

		if ( ! is_array( $ability ) ) {
			return "Ability \"{$key}\" is not an array.";
		}

		// Every check below mirrors a throw in core's
		// WP_Ability::prepare_properties(). Core catches that throw, calls
		// _doing_it_wrong() and returns null -- which is invisible in
		// production -- so anything core rejects and the kit accepts becomes an
		// ability that silently never registers. The kit validates it here
		// instead, where it produces a log line naming the manifest entry.
		// Registrar::register() checks the return value as a backstop for the
		// cases this list does not anticipate.
		if ( empty( $ability['label'] ) || ! is_string( $ability['label'] ) ) {
			return "Ability \"{$key}\" is missing a label, or its label is not a string.";
		}

		// Required, and NOT defaulted the way a category's description is.
		// A category description is invisible plumbing, so falling back to the
		// label there is harmless (see Registrar::register_category()). An
		// ability's description is the prompt text the model reads to decide
		// whether to call this tool, so a label-derived stand-in ("Delete
		// Things") is actively worse than refusing to register: it produces a
		// tool the model will misuse rather than one it cannot see. Reject and
		// log; do not "fix" this into a fallback later.
		if ( empty( $ability['description'] ) || ! is_string( $ability['description'] ) ) {
			return "Ability \"{$key}\" is missing a description. Core requires a non-empty description string and silently drops the ability without one; it is also the prompt text the model selects on, so the kit will not substitute the label.";
		}

		if ( isset( $ability['input_schema'] ) && ! is_array( $ability['input_schema'] ) ) {
			return "Ability \"{$key}\" has an input_schema that is not an array.";
		}

		if ( empty( $ability['risk'] ) || ! in_array( $ability['risk'], [ 'readonly', 'idempotent', 'destructive' ], true ) ) {
			return "Ability \"{$key}\" has a missing or invalid risk tier.";
		}

		// Tombstones (D1 §5): a deprecated entry MUST declare both since and
		// replacement, and MUST be risk => readonly whatever the tier of the
		// tool it replaces.
		$is_tombstone = isset( $ability['deprecated'] );

		if ( $is_tombstone ) {
			if ( empty( $ability['deprecated']['since'] ) || empty( $ability['deprecated']['replacement'] ) ) {
				return "Tombstone \"{$key}\" is missing since/replacement.";
			}

			if ( 'readonly' !== $ability['risk'] ) {
				return "Tombstone \"{$key}\" must be risk => readonly, whatever the tier of the tool it replaces.";
			}
		}

		// A tombstone needs no handler: D1 §10 describes its whole behaviour
		// as "returning a structured error naming its replacement", which the
		// kit generates itself -- see Registrar::build_args(). Every other
		// ability MUST supply one.
		if ( ! $is_tombstone && ( empty( $ability['handler'] ) || ! is_callable( $ability['handler'] ) ) ) {
			return "Ability \"{$key}\" has no callable handler.";
		}

		if ( isset( $ability['input_schema'] ) && self::schema_has_union_type( $ability['input_schema'] ) ) {
			return "Ability \"{$key}\" declares a union type, which is invalid in WPAB manifests.";
		}

		return null;
	}

	/**
	 * A union type shows up either as `'type' => ['string', 'null']` or as
	 * a `oneOf` / `anyOf` at the schema root or inside any property.
	 *
	 * @param mixed $schema A JSON Schema fragment, or whatever the manifest supplied.
	 * @return bool
	 */
	private static function schema_has_union_type( $schema ): bool {

		if ( ! is_array( $schema ) ) {
			return false;
		}

		if ( isset( $schema['type'] ) && is_array( $schema['type'] ) ) {
			return true;
		}

		if ( isset( $schema['oneOf'] ) || isset( $schema['anyOf'] ) ) {
			return true;
		}

		if ( isset( $schema['properties'] ) && is_array( $schema['properties'] ) ) {
			foreach ( $schema['properties'] as $property ) {
				if ( self::schema_has_union_type( $property ) ) {
					return true;
				}
			}
		}

		return false;
	}
}
