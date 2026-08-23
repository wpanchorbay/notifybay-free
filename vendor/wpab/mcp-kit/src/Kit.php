<?php
/**
 * D1 §4.13 `Kit` — the one public entry point, and the only kit symbol a
 * consuming plugin names.
 *
 * @package WPAB\Mcp
 */

declare(strict_types=1);

namespace WPAB\Mcp;

/**
 * The one public entry point, and the only kit symbol a consuming plugin names.
 */
final class Kit {

	/**
	 * Announced in the wpab_mcp_products payload (D1 §6), so the hub can render
	 * against what an older kit copy actually supports.
	 *
	 * @var string
	 */
	public const VERSION = '0.3.3';

	/**
	 * Wire the kit into the host plugin. Call at file scope, not inside a
	 * `plugins_loaded` callback of your own -- the kit hooks that action
	 * itself, so deferring registers the hook after it has already fired
	 * (D1 §4.13).
	 *
	 * `$file` is needed twice: `register_activation_hook()` derives its action
	 * name from it, and `get_plugin_data()` reads the version and description
	 * that `create_server()` requires as non-nullable strings (D1 §4.2).
	 *
	 * `$manifest_path` is a path and never a pre-loaded array -- passing an
	 * array would load and build the whole manifest on every front-end page
	 * view. See the docblock on Manifest::load().
	 *
	 * @param string $file          The consuming plugin's `__FILE__`.
	 * @param string $manifest_path Absolute path to the plugin's `config/mcp.php`.
	 */
	public static function boot( string $file, string $manifest_path ): void {

		// MUST run on every load, unconditionally -- WordPress only knows
		// what to call on activation if this is registered every time,
		// not only when Bootstrap's gate happens to pass (D1 §7.2).
		register_activation_hook(
			$file,
			static function () {
				Gate::activate();
			}
		);

		// Registered here rather than inside Bootstrap because provisioning an
		// account must work whether or not any product is enabled -- an
		// operator locked out of a disabled endpoint still needs to create the
		// account that will reach it once switched on.
		Cli::register();

		// Bootstrap hooks plugins_loaded itself. Kit::boot() MUST be called
		// at file scope in the plugin's main file, not deferred into the
		// host's own plugins_loaded callback -- doing so would register
		// this hook after plugins_loaded has already fired (D1 §4.13).
		add_action(
			'plugins_loaded',
			static function () use ( $file, $manifest_path ) {
				Bootstrap::run( $file, $manifest_path );
			}
		);
	}

	/**
	 * D1 §4.11: what a consuming plugin's own uninstall.php calls. Deletes
	 * this product's settings row and nothing else -- never
	 * wpab_mcp_access, never another product's row, never an Application
	 * Password. See Settings::uninstall() for why the capability is left
	 * alone deliberately, not by omission.
	 *
	 * @param string $product_key The consuming plugin's `product_key` (D1 §5).
	 */
	public static function uninstall( string $product_key ): void {
		Settings::uninstall( $product_key );
	}
}
