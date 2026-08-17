<?php
/**
 * Example manifest — copy to your plugin's `config/mcp.php` and edit.
 *
 * The kit is pointed at this file by path, never by pre-loaded array:
 *
 *     \WPAB\Mcp\Kit::boot( __FILE__, __DIR__ . '/config/mcp.php' );
 *
 * called at **file scope** in the plugin's main file. Passing a `require`d
 * array instead would read the manifest on every front-end page view and
 * fails D1 §9 criterion 12; deferring the Kit::boot() call into your own
 * `plugins_loaded` callback registers the kit's hook after that action has
 * already fired (D1 §4.13).
 *
 * Field rules are D1 §5. The ones that bite:
 *
 * - `product_key` is lowercase `[a-z0-9]` with **no hyphen**, and is
 *   permanent — it appears in the endpoint URL and in every tool name.
 * - The ability key is `<product_key>/<verb>-<noun>` and is **not** the name
 *   a client calls. The adapter rewrites the slash to a hyphen, so
 *   `example/list-things` is called on the wire as `example-list-things`.
 *   Both forms are permanent identifiers under D4 §9.
 * - `label` MUST be translated. `description` MUST NOT be — it is prompt text
 *   for a model, and tool-selection accuracy depends on its exact wording.
 * - `input_schema` MUST NOT use a union type (`['string','null']`, `oneOf`,
 *   `anyOf`); the kit rejects the entry at registration time and logs it.
 * - Bulk array parameters MUST declare `maxItems`; a `per_page` parameter
 *   SHOULD declare `maximum` equal to `Envelope::MAX_PER_PAGE` (100).
 *
 * @package WPAB\Mcp
 */

defined( 'ABSPATH' ) || exit;

return [

	'product_key' => 'example',
	'label'       => __( 'Example Product', 'example-textdomain' ),

	// Required. Registered on wp_abilities_api_categories_init before any
	// ability — WP_Ability throws without one, and a manifest with no
	// category registers zero abilities while the server still advertises them.
	'category'    => [
		'slug'  => 'example',
		'label' => __( 'Example Product', 'example-textdomain' ),
	],

	'abilities'   => [

		// --- readonly: no state change anywhere -------------------------
		'example/list-things'   => [
			'label'        => __( 'List Things', 'example-textdomain' ),
			'description'  => 'List things in the store, optionally filtered by status. Returns id, name and status for each.',
			'input_schema' => [
				'type'       => 'object',
				'properties' => [
					'status'   => [
						'type' => 'string',
						'enum' => [ 'active', 'archived' ],
					],
					'per_page' => [
						'type'    => 'integer',
						'default' => 20,
						'minimum' => 1,
						// Equal to Envelope::MAX_PER_PAGE. JSON Schema cannot
						// reference a PHP constant, so this number is a
						// deliberate duplicate; D4 §7 records the duty to keep
						// the two in sync.
						'maximum' => 100,
					],
				],
			],
			'risk'         => 'readonly',
			// No 'capability': wpab_mcp_access already gates every tool.
			'handler'      => [ \Example\Mcp\Tools\Things::class, 'list_things' ],
		],

		// --- idempotent: running it twice leaves the same state ---------
		'example/set-status'    => [
			'label'        => __( 'Set Thing Status', 'example-textdomain' ),
			'description'  => 'Set a thing\'s status to active or archived. Running this twice with the same input has no additional effect.',
			'input_schema' => [
				'type'       => 'object',
				'properties' => [
					'id'     => [ 'type' => 'integer' ],
					'status' => [
						'type' => 'string',
						'enum' => [ 'active', 'archived' ],
					],
				],
				'required'   => [ 'id', 'status' ],
			],
			'risk'         => 'idempotent',
			'handler'      => [ \Example\Mcp\Tools\Things::class, 'set_status' ],
		],

		// --- destructive: compounds or deletes --------------------------
		'example/delete-things' => [
			'label'        => __( 'Delete Things', 'example-textdomain' ),
			'description'  => 'Permanently delete the things with the given ids. This cannot be undone.',
			'input_schema' => [
				'type'       => 'object',
				'properties' => [
					'ids' => [
						'type'     => 'array',
						'items'    => [ 'type' => 'integer' ],
						// Mandatory on any bulk array parameter (D1 §5). With
						// rate limiting deferred (D1 §10), this is part of what
						// stands in for it.
						'maxItems' => 50,
					],
				],
				'required'   => [ 'ids' ],
			],
			'risk'         => 'destructive',
			// Narrower than wpab_mcp_access, because deletion warrants it.
			// 'all_of' and 'any_of' may both appear; both must then pass.
			'capability'   => [ 'all_of' => [ 'delete_published_products' ] ],
			'handler'      => [ \Example\Mcp\Tools\Things::class, 'delete_things' ],
		],

		// --- tombstone: a renamed or removed tool -----------------------
		// Keeps the old name registered so an old client gets a structured
		// error naming the successor instead of a bare "tool not found".
		// The kit generates that error and forces additionalProperties:true
		// on the schema, so no handler is needed here — see D1 §5, §10.
		'example/list-stuff'    => [
			'label'       => __( 'List Stuff (removed)', 'example-textdomain' ),
			'description' => 'Removed. Use example-list-things instead.',
			// MUST be readonly whatever the tier of the tool it replaces: a
			// tombstone tagged destructive is hidden at the levels most
			// stores run, so the explanation never reaches the client.
			'risk'        => 'readonly',
			'deprecated'  => [
				'since'       => '2.0',
				'replacement' => 'example/list-things',
			],
		],
	],
];
