<?php
/**
 * D1 §4.9 `Observability` -- writes everything through wc_get_logger() with
 * source `wpab-mcp`. The kit MUST NOT create a table.
 *
 * Pure static, and deliberately names no adapter symbol at all: this class is
 * reachable from the degraded path (Manifest::load() logs invalid abilities,
 * and AdminApi's routes register above Bootstrap's dependency check), so it
 * MUST stay loadable on a site with no adapter installed. The half that does
 * implement the adapter's McpObservabilityHandlerInterface lives in
 * ObservabilityBridge -- see that file for the fatal this split fixes.
 *
 * @package WPAB\Mcp
 */

declare(strict_types=1);

namespace WPAB\Mcp;

/**
 * Logging. Names no adapter symbol, so it survives the adapter being absent.
 */
final class Observability {

	/**
	 * D1 §4.9: "Every non-readonly call MUST log: user ID, tool name,
	 * access level in force, a digest of arguments, and the outcome."
	 * Called from Registrar::wrap_execute() for every tool whose risk tier
	 * is not `readonly`.
	 *
	 * "Identifiers of the affected records SHOULD be logged too... A
	 * handler that mutates records SHOULD therefore return them under an
	 * `affected` key, which the wrapper logs if present and omits if not."
	 *
	 * @param string     $ability_key Full ability key, `<product_key>/<verb>-<noun>`.
	 * @param string     $access_level Access level in force when the call ran.
	 * @param mixed      $input Validated input, as core passed it.
	 * @param string     $outcome `success`, `error` or `exception`.
	 * @param array|null $affected Identifiers the handler reported changing.
	 */
	public static function log_tool_call( string $ability_key, string $access_level, $input, string $outcome, ?array $affected = null ): void {

		$context = [
			'user_id'      => get_current_user_id(),
			'tool'         => $ability_key,
			'access_level' => $access_level,
			'arguments'    => self::digest( $input ),
			'outcome'      => $outcome,
		];

		if ( null !== $affected ) {
			$context['affected'] = $affected;
		}

		self::write( 'info', 'wpab_mcp.tool_call', $context );
	}

	/** Kit-internal diagnostics -- manifest validation, bootstrap failures. */
	/**
	 * Log.
	 *
	 * @param string $level Access level in force: `read`, `read+modify` or `full`.
	 * @param string $message Client-safe message. Never an exception message.
	 * @param array  $context Structured context for the log line.
	 */
	public static function log( string $level, string $message, array $context = [] ): void {
		self::write( $level, $message, $context );
	}

	/**
	 * The single write point into wc_get_logger().
	 *
	 * @param string $level Access level in force: `read`, `read+modify` or `full`.
	 * @param string $message Client-safe message. Never an exception message.
	 * @param array  $context Structured context for the log line.
	 */
	private static function write( string $level, string $message, array $context ): void {

		if ( ! function_exists( '\wc_get_logger' ) ) {
			return; // D1 §4.1 step 4 already gated on the adapter/Abilities API, not WooCommerce's logger specifically -- fail quiet rather than fatal.
		}

		wc_get_logger()->log( $level, $message, array_merge( $context, [ 'source' => 'wpab-mcp' ] ) );
	}

	/**
	 * "A digest of arguments", not the arguments themselves -- this is a
	 * log line, not a second copy of whatever the caller sent.
	 *
	 * @param mixed $input Validated input, as core passed it.
	 * @return string
	 */
	private static function digest( $input ): string {

		if ( empty( $input ) ) {
			return '{}';
		}

		$encoded = wp_json_encode( $input );

		if ( false === $encoded ) {
			return '[unencodable]';
		}

		// Character-based for the same reason as Envelope::truncate() -- a
		// byte-offset cut can split a multi-byte sequence and leave invalid
		// UTF-8 in the log line.
		return mb_strlen( $encoded ) > 500 ? mb_substr( $encoded, 0, 500 ) . '…' : $encoded;
	}
}
