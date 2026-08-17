<?php
/**
 * Reference PHP-Scoper configuration for consumers of wpab/mcp-kit.
 *
 * This file does not run against the kit in isolation -- the kit has no
 * host plugin file of its own (Kit::boot() always takes the *consuming*
 * plugin's __FILE__, per D1 §4.13), so there is nothing here to prefix on
 * the kit's behalf. A consuming plugin copies this into its own build and
 * runs it with its own --prefix, e.g.:
 *
 *   vendor/bin/php-scoper add-prefix --config=scoper.inc.php --prefix=OptionBay\\Vendor
 *
 * D1 §8.3 is the source of truth this file exists to satisfy: PHP-Scoper
 * *prepends* its prefix, so with --prefix=OptionBay\Vendor the kit's own
 * WPAB\Mcp\* classes end up at OptionBay\Vendor\WPAB\Mcp\* -- never drop
 * the WPAB\Mcp segment in a `use` statement, or the class silently does not
 * exist and the failure surfaces as a fatal nowhere near the mistake.
 *
 * The exclusion set below is the substance of this file. Everything in it
 * MUST stay unscoped so the kit keeps calling the *same* adapter and
 * Abilities API instance every other plugin -- and WooCommerce itself --
 * is already using on that site. Scoping any of it would register
 * abilities against a copy nothing else can see.
 *
 * @package WPAB\Mcp
 */

declare(strict_types=1);

use Isolated\Symfony\Component\Finder\Finder;

return [
	'prefix'             => null,

	'finders'            => [
		Finder::create()
			->files()
			->name( '*.php' )
			->in( __DIR__ . '/src' ),
	],

	// D1 §8.3's exclude-namespaces list, verbatim.
	'exclude-namespaces' => [
		'WP\\MCP',
		'WP\\McpSchema',
		'Automattic\\WooCommerce',
	],

	// WordPress core the kit calls directly. Prefer a maintained WordPress
	// scoper preset if the consuming plugin already has one -- this list
	// is the explicit fallback D1 §8.3 allows instead, and is meant as a
	// floor to extend, not a ceiling to trim.
	// NOTE: scoper rewrites bare string literals inside function_exists() and
	// class_exists() too, not just real calls. The kit defends itself by
	// writing those strings fully qualified ('\wp_register_ability'), so a
	// gap in this list degrades performance-neutral behaviour rather than
	// silently disabling the MCP route -- but keep the list complete anyway.
	'exclude-functions'  => [
		'wp_register_ability',
		'wp_register_ability_category',
		'wp_get_ability',
		'wp_get_abilities',
		'register_rest_route',
		'rest_ensure_response',
		'get_option',
		'update_option',
		'add_option',
		'delete_option',
		'get_role',
		'get_current_user_id',
		'is_admin',
		'wp_doing_ajax',
		'wp_json_encode',
		'is_wp_error',
		'register_activation_hook',
		'register_uninstall_hook',
		'add_action',
		'add_filter',
		'apply_filters',
		'do_action',
		'current_user_can',
		'is_user_logged_in',
		'wp_get_current_user',
		'wp_is_serving_rest_request',
		'wp_is_rest_endpoint',
		'rest_get_url_prefix',
		'rest_url',
		'rest_validate_value_from_schema',
		'get_plugin_data',
		'plugin_basename',
		'plugin_dir_url',
		'wc_get_logger',
		'determine_locale',
		'load_textdomain',
		'is_textdomain_loaded',
		'__',
		'_x',
		'esc_html__',
		'esc_html',
		'esc_url',
		'sanitize_text_field',
	],

	'exclude-classes'    => [
		'WP_Error',
		'WP_REST_Request',
		'WP_REST_Response',
		'WP_REST_Server',
		'WP_Ability',
		'WP_CLI',
		'WP_User',
	],

	'exclude-constants'  => [
		'ABSPATH',
		'REST_REQUEST',
	],
];
