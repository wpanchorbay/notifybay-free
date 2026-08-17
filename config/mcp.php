<?php
/**
 * MCP Configuration
 *
 * The manifest `wpab/mcp-kit` reads to build NotifyBay's MCP endpoint. The kit
 * is pointed at this file by *path* from the plugin's main file, never handed a
 * pre-loaded array — passing a `require`d array instead would read this file on
 * every front-end page view.
 *
 * Endpoint:        /wp-json/wpab/notifybay/mcp
 * Settings routes: /wp-json/wpab/v1/notifybay/{status,settings}
 *
 * @package    NotifyBay
 * @subpackage Config
 * @since      1.0.3
 * @author     WPAnchorBay <sankarsan@wpanchorbay.com>
 */

// Prevent direct access.
defined( 'ABSPATH' ) || exit;

use NotifyBay\Mcp\Tools\Leads;

return array(

	/*
	 * Lowercase [a-z0-9], no hyphen, and PERMANENT: it appears in the endpoint
	 * URL and in every tool name, so changing it later breaks every configured
	 * client.
	 */
	'product_key' => 'notifybay',
	'label'       => __( 'NotifyBay', 'notifybay-waitlist-and-stock-alert-woo' ),

	/*
	 * Required. Registered before any ability — a manifest with no category
	 * registers zero abilities while the server still advertises them, which
	 * is silent.
	 */
	'category'    => array(
		'slug'  => 'notifybay',
		'label' => __( 'NotifyBay Waitlist', 'notifybay-waitlist-and-stock-alert-woo' ),
	),

	/*
	 * The ability key is NOT the name a client calls. The adapter rewrites the
	 * slash to a hyphen, so `notifybay/list-leads` is invoked on the wire as
	 * `notifybay-list-leads`. Both forms are permanent identifiers.
	 *
	 * `label` is translated; `description` deliberately is not — it is prompt
	 * text a model reads to choose a tool, and tool-selection accuracy depends
	 * on its exact wording.
	 */
	'abilities'   => array(

		// --- readonly ---------------------------------------------------
		'notifybay/list-leads'    => array(
			'label'        => __( 'List Waitlist Leads', 'notifybay-waitlist-and-stock-alert-woo' ),
			'description'  => 'List NotifyBay waitlist leads, newest first, optionally filtered by status, subscription type, product or email. Returns id, email, product id and name, type, status, and creation time for each. Use this to answer questions about who is waiting for which product.',
			'input_schema' => array(
				'type'       => 'object',
				'properties' => array(
					// Every filter below is a real column in
					// Lead::$queryable_columns. Model::paginate() silently drops
					// any key that is not, so an invented filter name would
					// return unfiltered results rather than an error.
					'status'     => array(
						'type' => 'string',
						'enum' => array( 'active', 'pending_verification', 'processing', 'notified', 'expired', 'failed', 'unsubscribed' ),
					),
					// Not an enum: the free plugin only writes 'waitlist', but
					// NotifyBay Pro adds its own types, and an enum here would
					// silently make them unfilterable.
					'type'       => array(
						'type' => 'string',
					),
					'product_id' => array(
						'type'    => 'integer',
						'minimum' => 1,
					),
					'search'     => array(
						'type' => 'string',
					),
					'page'       => array(
						'type'    => 'integer',
						'default' => 1,
						'minimum' => 1,
					),
					'per_page'   => array(
						'type'    => 'integer',
						'default' => 20,
						'minimum' => 1,
						// Equal to Envelope::MAX_PER_PAGE. JSON Schema cannot
						// reference a PHP constant, so this number is a
						// deliberate duplicate that must be kept in sync.
						'maximum' => 100,
					),
				),
			),
			'risk'         => 'readonly',
			// No 'capability': wpab_mcp_access already gates every tool, and
			// reading the waitlist is no broader than the admin screen.
			'handler'      => array( Leads::class, 'list_leads' ),
		),

		'notifybay/system-status' => array(
			'label'        => __( 'NotifyBay System Status', 'notifybay-waitlist-and-stock-alert-woo' ),
			'description'  => 'Report NotifyBay queue health: counts of scheduled, pending, running, complete and failed Action Scheduler jobs in the notifybay_alerts group, plus how many leads are stuck in the failed or processing state. Use this to diagnose why restock notifications are not going out.',
			'input_schema' => array(
				'type'       => 'object',
				'properties' => array(),
			),
			'risk'         => 'readonly',
			'handler'      => array( Leads::class, 'system_status' ),
		),

		// --- idempotent: running it twice leaves the same state -----------
		'notifybay/update-lead'   => array(
			'label'        => __( 'Update Waitlist Lead', 'notifybay-waitlist-and-stock-alert-woo' ),
			'description'  => "Change a single waitlist lead's status or email address. Running this twice with the same input has no additional effect. Setting status to unsubscribed is how you remove somebody from a waitlist without deleting their record.",
			'input_schema' => array(
				'type'       => 'object',
				'properties' => array(
					'id'         => array(
						'type'    => 'integer',
						'minimum' => 1,
					),
					'status'     => array(
						'type' => 'string',
						'enum' => array( 'active', 'pending_verification', 'processing', 'notified', 'expired', 'failed', 'unsubscribed' ),
					),
					'user_email' => array(
						'type' => 'string',
					),
				),
				'required'   => array( 'id' ),
			),
			'risk'         => 'idempotent',
			'handler'      => array( Leads::class, 'update_lead' ),
		),

		// --- destructive: deletes ----------------------------------------
		'notifybay/delete-leads'  => array(
			'label'        => __( 'Delete Waitlist Leads', 'notifybay-waitlist-and-stock-alert-woo' ),
			'description'  => 'Permanently delete the waitlist leads with the given ids. This cannot be undone and the customers are not notified. Prefer setting status to unsubscribed unless the records genuinely need to be erased.',
			'input_schema' => array(
				'type'       => 'object',
				'properties' => array(
					'ids' => array(
						'type'     => 'array',
						'items'    => array(
							'type'    => 'integer',
							'minimum' => 1,
						),
						// Mandatory on a bulk array parameter, and with rate
						// limiting not implemented it is part of what stands
						// in for one.
						'maxItems' => 50,
						'minItems' => 1,
					),
				),
				'required'   => array( 'ids' ),
			),
			'risk'         => 'destructive',
			// Narrower than wpab_mcp_access. `manage_notifybay` is the plugin's
			// own capability, granted to administrator and shop_manager by
			// Core\Activator — so an MCP account that can read the waitlist
			// still cannot erase it unless it also holds the capability the
			// human admin screen requires.
			'capability'   => array( 'all_of' => array( 'manage_notifybay' ) ),
			'handler'      => array( Leads::class, 'delete_leads' ),
		),
	),
);
