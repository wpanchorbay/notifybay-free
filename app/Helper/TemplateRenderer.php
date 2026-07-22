<?php
/**
 * Template Renderer.
 *
 * Loads template files from the templates/ directory with theme override support.
 * Themes can override any template by placing it in: theme/notifybay/{template_name}.php
 *
 * @package    NotifyBay
 * @subpackage Helper
 * @since      1.0.0
 * @author     WPAnchorBay <sankarsan@wpanchorbay.com>
 */

namespace NotifyBay\Helper;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class TemplateRenderer
 */
class TemplateRenderer {

	/**
	 * Render a template file.
	 *
	 * Looks for the template first in the active theme (child theme takes priority),
	 * then falls back to the plugin's templates/ directory.
	 *
	 * @param string $template_name Relative path inside templates/ without .php extension
	 *                              (e.g. 'admin/meta-box-overrides').
	 * @param array  $data          Associative array of variables to extract into the template scope.
	 * @param bool   $as_string     If true, return the output as a string instead of echoing.
	 * @return string|void
	 */
	public static function render( $template_name, $data = array(), $as_string = false ) {
		$template_path = self::locate( $template_name );

		if ( ! $template_path ) {
			return $as_string ? '' : null;
		}

		if ( $as_string ) {
			ob_start();
		}

		// Extract variables into the template scope.
		// EXTR_SKIP ensures existing variables are never overwritten.
		if ( ! empty( $data ) ) {
			extract( $data, EXTR_SKIP ); // phpcs:ignore WordPress.PHP.DontExtract.extract_extract
		}

		include $template_path;

		if ( $as_string ) {
			return ob_get_clean();
		}
	}

	/**
	 * Locate a template file.
	 *
	 * Checks the following locations in order:
	 * 1. Child theme:  wp-content/themes/child-theme/notifybay/{template_name}.php
	 * 2. Parent theme: wp-content/themes/parent-theme/notifybay/{template_name}.php
	 * 3. Plugin:       wp-content/plugins/notifybay-waitlist-and-stock-alert-woo/templates/{template_name}.php
	 *
	 * Developers can filter the located path via 'notifybay_locate_template'.
	 *
	 * @param string $template_name Relative template path without .php extension.
	 * @return string|false Absolute path to the template file, or false if not found.
	 */
	public static function locate( $template_name ) {
		$template_name = ltrim( $template_name, '/' );
		$template_file = $template_name . '.php';

		// 1. Look in the theme/child-theme directory.
		$theme_path = locate_template(
			array(
				'notifybay/' . $template_file,
			)
		);

		if ( $theme_path ) {
			/**
			 * Filter the located template path.
			 *
			 * @param string $theme_path     Full path to the located template.
			 * @param string $template_name  The original template name requested.
			 */
			return apply_filters( 'notifybay_locate_template', $theme_path, $template_name );
		}

		// 2. Fall back to the plugin's templates/ directory.
		$plugin_path = NOTIFYBAY_PATH . 'templates/' . $template_file;

		if ( ! file_exists( $plugin_path ) ) {
			$plugin_path = false;
		}

		/**
		 * Filter the located template path.
		 *
		 * Fires even when Free has no matching template (path is `false`), so
		 * NotifyBay Pro can supply premium-only templates (e.g. price-drop,
		 * hurry-low-stock) that don't ship in Free at all.
		 *
		 * @param string|false $plugin_path   Full path to the located template, or false if not found.
		 * @param string       $template_name The original template name requested.
		 */
		return apply_filters( 'notifybay_locate_template', $plugin_path, $template_name );
	}
}
