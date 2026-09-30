<?php
/**
 * D1 §4.1 `Bootstrap` -- the seven-step gate that runs on every
 * `plugins_loaded`, and the only place that decides whether anything below
 * it is worth paying for on this request.
 *
 * Steps 1 and 3 register above the disabled check on purpose (D1 §4.1): a
 * disabled product must still answer discovery, still listen for the hub's
 * toggle, and still serve its own settings route, or there is no surface
 * left that can switch it back on.
 *
 * @package WPAB\Mcp
 */

declare(strict_types=1);

namespace WPAB\Mcp;

/**
 * The gate. Decides, once per request, whether any of the rest is worth paying for.
 */
final class Bootstrap {

	/**
	 * The seven-step gate, on every plugins_loaded.
	 *
	 * @param string $file The consuming plugin's `__FILE__`.
	 * @param string $manifest_path Absolute path to the plugin's `config/mcp.php`.
	 */
	public static function run( string $file, string $manifest_path ): void {

		// Step 1 -- Discovery + the toggle listener, admin/REST/CLI only,
		// including when the product is disabled.
		if ( ! self::is_admin_rest_or_cli() ) {
			return;
		}

		// Step 1a -- repair the capability on sites that were UPDATED rather
		// than activated. register_activation_hook() does not fire on an update,
		// and Gate::activate() was the only grant, so those sites answer 403 to
		// every MCP request while reporting themselves healthy. Deliberately
		// above the `enabled` gate at step 5: a product that is switched off
		// today still needs the capability in place for when it is switched on.
		// One shot, guarded by its own option -- see Gate::maybe_backfill().
		Gate::maybe_backfill();

		// Registering the toggle listener needs product_key baked into the
		// hook *name* itself (`wpab_mcp_toggle_<product_key>`), which the
		// full manifest is the only source of. D1 §4.1 says step 1 must not
		// read the manifest at all -- read literally that is unsatisfiable,
		// since Kit::boot() is never given product_key on its own (D1
		// §4.13's signature is only $file and $manifest_path). What this
		// implementation does instead: peek the raw file *text* for the
		// `'product_key' => '...'` literal via Manifest::peek_product_key(),
		// without requiring/executing the file -- so no __() call, no
		// ability array, nothing manifest-shaped actually runs this early.
		// Flagged here rather than silently resolved, per this project's
		// own convention for exactly this kind of spec ambiguity.
		$product_key = Manifest::peek_product_key( $manifest_path );

		if ( null === $product_key ) {
			return; // Malformed manifest -- nothing to register against.
		}

		Discovery::register( $product_key, $manifest_path );
		Settings::listen_toggle( $product_key );
		self::load_textdomain();

		// Step 4a -- availability, evaluated here rather than after step 2's
		// gate, and split from the early return it used to be fused with.
		//
		// D1 §4.1 puts the whole of step 4 below the gate, and that made the
		// notice unreachable: `admin_notices` only fires on an admin page
		// render, which is precisely the request the gate returns from, so the
		// callback was registered only on REST and WP-CLI requests -- where the
		// action never fires. A missing dependency was silent to the store
		// owner it exists to inform. Verified against a real
		// cookie-authenticated wp-admin page, because asserting it by calling
		// do_action('admin_notices') under WP-CLI passes either way: WP_CLI
		// being defined opens the gate, so the CLI check could not see the bug.
		//
		// Only the *notice* moves. The early return stays below step 3, so a
		// site with no adapter still registers its settings routes -- they are
		// the surface that reports the problem and, once fixed, enables the
		// product (D1 §4.12). Returning here instead would 404 them.
		//
		// Cost on an admin request is two existence checks; step 1 already
		// reaches this far and no manifest is read, so §8.2 still holds.
		//
		// Every name in a class_exists()/function_exists() *string* in this
		// kit is written fully qualified, with the leading backslash, and
		// must stay that way. PHP-Scoper rewrites bare string literals in
		// these calls: without the backslash,
		// function_exists( 'wp_register_ability' ) becomes
		// function_exists( '<Prefix>\wp_register_ability' ), which is always
		// false -- so a scoped build concludes the Abilities API is missing
		// and serves no MCP route at all, while its settings routes keep
		// answering and make it look half-alive. D1 §8.3 flags this trap for
		// class_exists; it applies equally to function_exists. The backslash
		// keeps the kit correct even when a consumer's scoper config forgets
		// to list these in exclude-functions.
		$dependencies_available = class_exists( '\WP\MCP\Core\McpAdapter' )
			&& function_exists( '\wp_register_ability' );

		if ( ! $dependencies_available ) {
			add_action(
				'admin_notices',
				static function () use ( $product_key ) {
					self::render_missing_dependency_notice( $product_key );
				}
			);
		}

		// Step 2 -- zero cost on anything that cannot reach a tool. Nothing
		// past this point ever runs on a front-end page view, an admin page
		// render, an AJAX call, or cron (D1 §8.2).
		if ( ! self::is_rest_or_cli() ) {
			return;
		}

		// Step 3 -- settings routes register regardless of enabled, and
		// regardless of the adapter being present, because they are the
		// surface that reports and enables it (D1 §4.12).
		AdminApi::register( $product_key, $manifest_path );

		// Step 4b -- nothing below here can work without the adapter.
		if ( ! $dependencies_available ) {
			return;
		}

		// Step 5 -- disabled products stop here. Steps 1 and 3 already ran.
		if ( ! Settings::is_enabled( $product_key ) ) {
			return;
		}

		// Step 6 -- idempotent; safe with N scoped copies calling it.
		\WP\MCP\Core\McpAdapter::instance();

		// Registrar and ServerFactory are deferred to their own hooks
		// rather than run synchronously here, so the manifest is only ever
		// fully loaded once wp_abilities_api_init / mcp_adapter_init
		// actually fire -- safely past after_setup_theme, unlike this
		// plugins_loaded-timed method.
		// Categories MUST register first. Core does not throw on an
		// unregistered category slug -- WP_Abilities_Registry::register()
		// calls _doing_it_wrong() and returns null, which is silent in
		// production -- so getting this order wrong registers zero
		// abilities with no visible failure at all. Registrar::register()
		// logs that null return, so the ordering now breaks loudly if it
		// ever regresses.
		add_action(
			'wp_abilities_api_categories_init',
			static function () use ( $product_key, $manifest_path ) {
				Registrar::register_category( $product_key, $manifest_path );
			}
		);

		add_action(
			'wp_abilities_api_init',
			static function () use ( $product_key, $manifest_path ) {
				Registrar::register( $product_key, $manifest_path );
			}
		);

		add_action(
			'mcp_adapter_init',
			static function () use ( $file, $product_key, $manifest_path ) {
				ServerFactory::create( $file, $product_key, $manifest_path );
			}
		);

		// Step 7 -- default-server suppression. Three independent
		// mitigations are required in total (D1 §7.4); this is the one
		// that belongs to Bootstrap. The other two (never setting
		// meta.mcp.public / meta.show_in_rest) are Registrar's job, at
		// ability-registration time -- see Registrar::build_args().
		self::suppress_default_server();
	}

	/**
	 * The kit's own strings, in the site's language.
	 *
	 * Eight strings reach a human or a model: the missing-dependency notice, the
	 * two settings labels, the permission refusals, and the tombstone. They were
	 * marked translatable from the start, but nothing ever loaded the domain and
	 * no `.pot` shipped, so they could only ever render in English however the
	 * site was configured.
	 *
	 * A library cannot use `load_plugin_textdomain()` -- that resolves against
	 * the plugins directory, and the kit lives inside somebody else's `vendor/`.
	 * The `.mo` is therefore addressed relative to the kit's own directory,
	 * which keeps working after PHP-Scoper has rewritten and relocated
	 * everything else.
	 *
	 * Deferred to `init` on purpose. Loading a translation before `init` trips
	 * WP 6.7+'s just-in-time check, and that notice is printed into the body of
	 * every response on the site -- the identical failure the default-server
	 * check caused in §7.4. Every one of these strings is used on `admin_notices`
	 * or inside a REST request, so `init` is early enough for all of them.
	 *
	 * `is_textdomain_loaded()` because N scoped copies of the kit each run this;
	 * they ship identical strings, so the first to arrive is as good as any.
	 */
	private static function load_textdomain(): void {

		add_action(
			'init',
			static function () {

				if ( is_textdomain_loaded( 'wpab-mcp-kit' ) ) {
					return;
				}

				$mofile = dirname( __DIR__ ) . '/languages/wpab-mcp-kit-' . determine_locale() . '.mo';

				if ( is_readable( $mofile ) ) {
					load_textdomain( 'wpab-mcp-kit', $mofile );
				}
			}
		);
	}

	/**
	 * D1 §7.4 mitigation 1: filter mcp_adapter_create_default_server to
	 * false, but only when the kit itself is what's starting the adapter.
	 * If WooCommerce's own mcp_integration flag is on, WooCommerce owns
	 * that decision and the kit MUST NOT override it.
	 */
	private static function suppress_default_server(): void {

		// The decision is made *inside* the filter, not here. Bootstrap runs on
		// plugins_loaded, and FeaturesUtil::feature_is_enabled() forces
		// WooCommerce's FeaturesController to build its feature-definition
		// table, which calls __() on the `woocommerce` textdomain. Doing that
		// before `init` trips WordPress 6.7+'s just-in-time translation check,
		// so the kit emitted a _doing_it_wrong notice on every REST, admin and
		// CLI request. On any site with display_errors on, that notice is
		// printed into the response body -- corrupting the JSON of every REST
		// route on the site, including core's /wp-json/ and WooCommerce's own,
		// not merely the kit's. It also made every request pay for
		// WooCommerce's feature table, against §8.2's zero-cost rule.
		//
		// The adapter applies this filter from its own init() (hooked to `init`
		// priority 20 / `rest_api_init` priority 15), so evaluating there is
		// both late enough to be silent and strictly more correct: it reads the
		// flag at the moment the answer is actually needed.
		add_filter(
			'mcp_adapter_create_default_server',
			static function ( $create ) {

				if ( defined( 'WPAB_MCP_ALLOW_DEFAULT_SERVER' ) && WPAB_MCP_ALLOW_DEFAULT_SERVER ) {
					return $create;
				}

				// If WooCommerce's own mcp_integration flag is on, WooCommerce
				// owns that decision and the kit MUST NOT override it.
				$woocommerce_owns_it = class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' )
					&& \Automattic\WooCommerce\Utilities\FeaturesUtil::feature_is_enabled( 'mcp_integration' );

				return $woocommerce_owns_it ? $create : false;
			}
		);
	}

	/**
	 * D1 §4.1: pretty permalinks, plain permalinks, and WP-CLI (which has
	 * no REQUEST_URI at all) all have to be handled -- getting this wrong
	 * fails loud in one direction (a missed REST request 404s) and silent
	 * in the other (a missed WP-CLI detection breaks `wp mcp-adapter serve`).
	 *
	 * @return bool
	 */
	private static function is_rest_or_cli(): bool {

		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			return true;
		}

		if ( function_exists( '\wp_is_serving_rest_request' ) && wp_is_serving_rest_request() ) {
			return true;
		}

		$prefix = function_exists( '\rest_get_url_prefix' ) ? rest_get_url_prefix() : 'wp-json';

		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Not form data. This asks "is this request routed to the REST API", before WordPress has decided anything, so there is no action to nonce and no value that reaches storage. Both reads are presence/substring tests only; neither is used as input.
		if ( isset( $_SERVER['REQUEST_URI'] ) && false !== strpos( sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ), '/' . $prefix . '/' ) ) {
			return true;
		}

		if ( isset( $_GET['rest_route'] ) ) {
			return true;
		}
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		return false;
	}

	/**
	 * Whether this request could reach discovery or the settings routes.
	 *
	 * @return bool
	 */
	private static function is_admin_rest_or_cli(): bool {
		return ( is_admin() && ! wp_doing_ajax() ) || self::is_rest_or_cli();
	}

	/**
	 * Admin notice shown when the adapter or Abilities API is absent.
	 *
	 * @param string $product_key The consuming plugin's `product_key` (D1 §5).
	 */
	private static function render_missing_dependency_notice( string $product_key ): void {
		if ( ! current_user_can( 'activate_plugins' ) ) {
			return;
		}

		$missing = ! class_exists( '\WP\MCP\Core\McpAdapter' ) ? 'wordpress/mcp-adapter' : 'the WordPress Abilities API';

		printf(
			'<div class="notice notice-warning"><p>%s</p></div>',
			esc_html(
				sprintf(
					/* translators: 1: product key, 2: missing dependency name */
					__( 'MCP for %1$s is unavailable: %2$s was not found. It should be vendored by WooCommerce; if WooCommerce is missing or badly out of date, MCP will not be available.', 'wpab-mcp-kit' ),
					$product_key,
					$missing
				)
			)
		);
	}
}
