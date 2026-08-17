<?php
/**
 * D1 §4.5 `Guard` -- authorization derived from the manifest, never from a
 * second table. Evaluates the `capability` an ability declares (D1 §5):
 * `any_of` / `all_of`, both present means both must pass.
 *
 * @package WPAB\Mcp
 */

declare(strict_types=1);

namespace WPAB\Mcp;

/**
 * Evaluates the `capability` an ability declares. Fails closed.
 */
final class Guard {

	/**
	 * Evaluate the `capability` an ability declares. Both `any_of` and `all_of`
	 * must pass when both are present, and an unrecognised shape fails closed
	 * rather than being treated as "nothing to check".
	 *
	 * @param string       $ability_key Full ability key, used only for the fail-closed log line.
	 * @param array|string $capability  `[ any_of => [] ]`, `[ all_of => [] ]`, or a bare capability.
	 * @return true|\WP_Error True when permitted, WP_Error when refused.
	 */
	public static function authorize( string $ability_key, $capability ) {

		if ( is_string( $capability ) ) {
			return self::check_all( [ $capability ], $ability_key );
		}

		if ( ! is_array( $capability ) ) {
			// MUST fail closed on an unknown shape, same as an unknown
			// ability name -- this is not a table lookup miss to shrug off.
			Observability::log( 'warning', 'Guard received an unrecognised capability shape; failing closed.', [ 'ability' => $ability_key ] );
			return self::denied( $ability_key );
		}

		if ( isset( $capability['all_of'] ) ) {
			$result = self::check_all( (array) $capability['all_of'], $ability_key );

			if ( is_wp_error( $result ) ) {
				return $result;
			}
		}

		if ( isset( $capability['any_of'] ) ) {
			$result = self::check_any( (array) $capability['any_of'], $ability_key );

			if ( is_wp_error( $result ) ) {
				return $result;
			}
		}

		// Both must pass when both are present (D1 §5) -- if neither key
		// was recognised at all, that is the unknown-shape case above.
		if ( ! isset( $capability['all_of'] ) && ! isset( $capability['any_of'] ) ) {
			return self::denied( $ability_key );
		}

		return true;
	}

	/**
	 * Every capability must be held.
	 *
	 * @param array  $capabilities Capability names to test.
	 * @param string $ability_key Full ability key, `<product_key>/<verb>-<noun>`.
	 */
	private static function check_all( array $capabilities, string $ability_key ) {
		foreach ( $capabilities as $cap ) {
			if ( ! current_user_can( $cap ) ) {
				return self::denied( $ability_key );
			}
		}
		return true;
	}

	/**
	 * At least one capability must be held.
	 *
	 * @param array  $capabilities Capability names to test.
	 * @param string $ability_key Full ability key, `<product_key>/<verb>-<noun>`.
	 */
	private static function check_any( array $capabilities, string $ability_key ) {
		foreach ( $capabilities as $cap ) {
			if ( current_user_can( $cap ) ) {
				return true;
			}
		}
		return self::denied( $ability_key );
	}

	/**
	 * The single fail-closed denial, so every refusal looks identical.
	 *
	 * @param string $ability_key Full ability key, `<product_key>/<verb>-<noun>`.
	 * @return \WP_Error
	 */
	private static function denied( string $ability_key ): \WP_Error {
		return new \WP_Error(
			'wpab_mcp_forbidden',
			__( 'This account lacks a capability this tool requires.', 'wpab-mcp-kit' ),
			[
				'status'  => 403,
				'ability' => $ability_key,
			]
		);
	}
}
