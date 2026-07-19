<?php
/**
 * Reusable functions.
 *
 * @package    NotifyBay
 * @since 1.0.0
 * @author     WPAnchorBay <sankarsan@gmail.com>
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}





if ( ! function_exists( 'notifybay_log' ) ) {
	/**
	 * Log messages using WooCommerce Logger.
	 *
	 * @param mixed  $message  The message to log.
	 * @param string $level    The log level (e.g., 'debug', 'info', 'error').
	 */
	function notifybay_log( $message, $level = 'info' ) {
		$enable_logging = NotifyBay\Core\Settings::get_instance()->get_settings( 'debug_enableMode' );
		$level_lower    = is_string( $level ) ? strtolower( $level ) : 'info';

		if ( ! $enable_logging && $level_lower !== 'error' ) {
			return;
		}

		$formatted_message = is_array( $message ) || is_object( $message ) ? wp_json_encode( $message ) : $message;

		if ( function_exists( 'wc_get_logger' ) ) {
			$logger  = wc_get_logger();
			$context = array( 'source' => 'notifybay' );
			$logger->log( $level_lower, $formatted_message, $context );
		} else {
			error_log( "[NotifyBay] [{$level_lower}]: " . print_r( $formatted_message, true ) );
		}
	}
}


if ( ! function_exists( 'notifybay_get_value' ) ) {
	/**
	 * Safely retrieve a value from a nested array or object using dot notation.
	 * Returns default if key is missing OR if value is an empty string.
	 *
	 * @since 1.0.0
	 * @param array|object $target  The array or object to search.
	 * @param string|array $key     The key path (e.g., 'settings.color').
	 * @param mixed        $default_val The default value if key is not found.
	 * @return mixed
	 */
	function notifybay_get_value( $target, $key, $default_val = null ) {
		if ( is_null( $key ) || ( is_string( $key ) && trim( $key ) === '' ) ) {
			return $default_val;
		}

		$keys = is_array( $key ) ? $key : explode( '.', $key );

		foreach ( $keys as $segment ) {
			if ( is_array( $target ) && isset( $target[ $segment ] ) ) {
				$target = $target[ $segment ];
			} elseif ( is_object( $target ) && isset( $target->{$segment} ) ) {
				$target = $target->{$segment};
			} else {
				return $default_val;
			}
		}

		if ( $target === '' ) {
			return $default_val;
		}

		return $target;
	}
}
