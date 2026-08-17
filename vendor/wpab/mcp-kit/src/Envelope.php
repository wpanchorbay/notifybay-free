<?php
/**
 * D1 §4.6 `Envelope` -- uniform response shaping so tools feel like one
 * product across the portfolio.
 *
 * @package WPAB\Mcp
 */

declare(strict_types=1);

namespace WPAB\Mcp;

/**
 * Uniform response shaping, so tools feel like one product across the portfolio.
 */
final class Envelope {

	/**
	 * The pagination ceiling, stated as a constant rather than left for
	 * every example to hard-code from memory (D1 §4.6). A manifest's
	 * `input_schema` may repeat this number in a `maximum` -- JSON Schema
	 * cannot reference a PHP constant -- and D4 §7 records the duty to
	 * keep both in sync if this ever changes.
	 */
	public const MAX_PER_PAGE = 100;

	/**
	 * Wrap a successful result.
	 *
	 * @param mixed $data The payload to wrap.
	 * @return array
	 */
	public static function success( $data ): array {
		return [ 'data' => $data ];
	}

	/**
	 * $code/$message/$retryable are top-level fields, not entries under
	 * $details -- retryable is part of the contract every client reads,
	 * and burying it in a free-form array would make it optional by
	 * accident (D1 §4.6). MUST NOT leak internal exception messages, file
	 * paths, or SQL -- callers pass a client-safe $message; this method
	 * does not accept a Throwable.
	 *
	 * @param string $code Stable, machine-readable error code.
	 * @param string $message Client-safe message. Never an exception message.
	 * @param bool   $retryable Whether retrying the same call could succeed.
	 * @param array  $details Optional structured context.
	 * @return array
	 */
	public static function error( string $code, string $message, bool $retryable = false, array $details = [] ): array {

		$error = [
			'code'      => $code,
			'message'   => $message,
			'retryable' => $retryable,
		];

		if ( ! empty( $details ) ) {
			$error['details'] = $details;
		}

		return [ 'error' => $error ];
	}

	/**
	 * List pagination with a hard ceiling -- $per_page is always bounded
	 * against self::MAX_PER_PAGE, never trusted from the caller directly.
	 *
	 * @param array $items The page of items.
	 * @param int   $page One-based page number.
	 * @param int   $per_page Items per page; clamped to MAX_PER_PAGE.
	 * @param int   $total Total matching items across every page.
	 * @return array
	 */
	public static function paginate( array $items, int $page, int $per_page, int $total ): array {

		$per_page = max( 1, min( $per_page, self::MAX_PER_PAGE ) );

		// Clamp once, then use the clamped value for both the reported page and
		// has_more. Computing has_more from the raw $page made page 0 report
		// itself as page 1 while its has_more was calculated for page 0 -- the
		// two fields described different pages.
		$page = max( 1, $page );

		return [
			'items'    => $items,
			'page'     => $page,
			'per_page' => $per_page,
			'total'    => $total,
			'has_more' => ( $page * $per_page ) < $total,
		];
	}

	/**
	 * Format a timestamp in the portfolio-wide ISO 8601 UTC form.
	 *
	 * @param int $unix_timestamp A Unix timestamp.
	 * @return string
	 */
	public static function timestamp( int $unix_timestamp ): string {
		return gmdate( 'Y-m-d\TH:i:s\Z', $unix_timestamp );
	}

	/**
	 * Character-based, not byte-based. `substr()` cuts at a byte offset and
	 * will split a multi-byte UTF-8 sequence down the middle; the resulting
	 * invalid UTF-8 makes wp_json_encode() return false further down, so a
	 * single accented character in a product title silently empties the
	 * response rather than truncating it. mbstring is loaded here and
	 * WordPress polyfills both functions in wp-includes/compat.php anyway.
	 *
	 * @param string $text The text to shorten.
	 * @param int    $length Maximum length in characters, not bytes.
	 * @return string
	 */
	public static function truncate( string $text, int $length = 280 ): string {

		if ( mb_strlen( $text ) <= $length ) {
			return $text;
		}

		return mb_substr( $text, 0, $length ) . '…';
	}
}
