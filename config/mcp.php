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

/*
 * Every status a lead can hold, declared once and shared by the two tools that
 * accept one. They were written out separately and drifted: `converted` was
 * missing from both, which made the single most common status in real data
 * impossible to filter on and impossible to set -- and a model reading the enum
 * had no way to discover it existed. Keep in sync with Leads::STATUSES and the
 * options in src/pages/Leads.tsx.
 */
$notifybay_statuses = array( 'active', 'pending_verification', 'processing', 'notified', 'converted', 'expired', 'failed', 'unsubscribed' );

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
		'notifybay/list-leads'            => array(
			'label'        => __( 'List Waitlist Leads', 'notifybay-waitlist-and-stock-alert-woo' ),
			'description'  => 'List NotifyBay waitlist leads, most recently signed up first, optionally filtered by status, subscription type, product or email address. Returns for each: id, email, product id and name, variation id (0 when the product has no variations), type, status, and the created_at, notified_at and expires_at timestamps. notified_at is null on any lead that did not go through the notification engine -- imported or seeded records commonly carry status notified with no timestamp -- so a null there means \'not recorded\', not \'never notified\'. expires_at is only set by the reservation window, which is a NotifyBay Pro feature. Results are paginated; the response carries total and has_more. Use this to answer questions about who is waiting for which product.',
			'input_schema' => array(
				'type'                 => 'object',
				// Reject unknown keys. Without this, an invented filter such as
				// {"bogus_filter":"x"} was accepted and returned the FULL
				// unfiltered result set -- indistinguishable from a successful
				// filtered query, because Model::paginate() silently drops any
				// key that is not queryable. A wrong answer that looks right is
				// worse than an error, so this errors now.
				'additionalProperties' => false,
				'properties'           => array(
					// Every filter below is a real column in
					// Lead::$queryable_columns. Model::paginate() silently drops
					// any key that is not, so an invented filter name would
					// return unfiltered results rather than an error.
					'status'     => array(
						'type'        => 'string',
						'description' => 'Return only leads in this state. active: waiting for stock. pending_verification: signed up but has not confirmed their email. processing: currently being dispatched to, a transient lock. notified: a back-in-stock email was sent. converted: went on to buy. expired: the reservation window passed. failed: the notification could not be delivered. unsubscribed: opted out.',
						'enum'        => $notifybay_statuses,
					),
					// Not an enum: the free plugin only writes 'waitlist', but
					// NotifyBay Pro adds its own types, and an enum here would
					// silently make them unfilterable. Named in the description
					// instead, because a wrong guess here does not error -- it
					// returns zero rows, which reads as "nobody is waiting".
					'type'       => array(
						'type'        => 'string',
						'description' => 'Subscription type. The free plugin only ever writes "waitlist"; NotifyBay Pro adds others. An unrecognised value is not an error and returns no leads.',
					),
					'product_id' => array(
						'type'        => 'integer',
						'description' => 'WooCommerce product id. Filters to leads waiting on that product. This is the only way to filter by product -- the search parameter does not match product names.',
						'minimum'     => 1,
					),
					'search'     => array(
						'type'        => 'string',
						'description' => 'Case-insensitive substring match against the customer email address ONLY. It does not search product names; use product_id for that. An unmatched value returns no leads rather than an error.',
					),
					'page'       => array(
						'type'        => 'integer',
						'description' => '1-based page number. Read has_more in the response to decide whether to ask for the next one.',
						'default'     => 1,
						'minimum'     => 1,
					),
					'per_page'   => array(
						'type'        => 'integer',
						'description' => 'How many leads to return per page, 1 to 100.',
						'default'     => 20,
						'minimum'     => 1,
						// Equal to Envelope::MAX_PER_PAGE. JSON Schema cannot
						// reference a PHP constant, so this number is a
						// deliberate duplicate that must be kept in sync.
						'maximum'     => 100,
					),
				),
			),
			'risk'         => 'readonly',
			// No 'capability': wpab_mcp_access already gates every tool, and
			// reading the waitlist is no broader than the admin screen.
			'handler'      => array( Leads::class, 'list_leads' ),
		),

		'notifybay/system-status'         => array(
			'label'        => __( 'NotifyBay System Status', 'notifybay-waitlist-and-stock-alert-woo' ),
			'description'  => 'Report NotifyBay queue health. Returns jobs: Action Scheduler action counts in the notifybay_alerts group, always carrying all five keys (pending, running, complete, failed, canceled) so a zero is distinguishable from a missing measurement. Note that Action Scheduler prunes old completed actions, so complete is a count of what is still retained, not of everything ever run. Also returns failed_leads and processing_leads, which count leads in those states and are unrelated to the job counts -- one failed job can leave many or no failed leads. Also returns restock_email_enabled: the WooCommerce \'Enable this email\' toggle for the back-in-stock notification, which is the final gate on every send and lives in WooCommerce\'s settings rather than NotifyBay\'s. When it is false, dispatch still runs and jobs still complete but no customer is emailed, so the job counts look healthy while nothing arrives -- check this first when leads are waiting and no mail is going out. It is null if the email could not be resolved. Also returns wc_version and plugin_version. Use this to diagnose why restock notifications are not going out.',
			'input_schema' => array(
				'type'       => 'object',
				'properties' => array(),
			),
			'risk'         => 'readonly',
			'handler'      => array( Leads::class, 'system_status' ),
		),

		'notifybay/notification-failures' => array(
			'label'        => __( 'Diagnose Notification Failures', 'notifybay-waitlist-and-stock-alert-woo' ),
			'description'  => 'Explain why restock notifications are not arriving. Returns two separate things. leads: waitlist leads in the failed state, ordered by last_attempt_at (the record\'s last-modified time, returned on every row) descending, each with its retry_count and last_error. last_error is usually null, and that is expected rather than a gap: Action Scheduler judges whether the action RAN, not whether the mail was delivered, so a send that failed inside an action that completed normally leaves no error text anywhere. Only a message from an action Action Scheduler itself marked failed is reported. retry_count is the better signal -- the engine stops retrying at three, though a lead imported or seeded as failed will show 0. jobs: failed background jobs in the notifybay_alerts group, grouped by hook, with the failure count and latest message for each. The two are usually unrelated: a failed job is typically a maintenance task that could not run, often because the plugin or its Pro add-on was not loaded when the action fired, and says nothing about any individual customer. Use this rather than list-leads with status failed when you need the reason, not just the list.',
			'input_schema' => array(
				'type'                 => 'object',
				// Reject unknown keys. Without this, an invented filter such as
				// {"bogus_filter":"x"} was accepted and returned the FULL
				// unfiltered result set -- indistinguishable from a successful
				// filtered query, because Model::paginate() silently drops any
				// key that is not queryable. A wrong answer that looks right is
				// worse than an error, so this errors now.
				'additionalProperties' => false,
				'properties'           => array(
					'page'     => array(
						'type'        => 'integer',
						'description' => '1-based page number for the failed leads list. The leads list is paginated with leads_page, leads_per_page, leads_total and leads_has_more; the jobs breakdown is not. Read leads_has_more to decide whether to ask for the next page.',
						'default'     => 1,
						'minimum'     => 1,
					),
					'per_page' => array(
						'type'        => 'integer',
						'description' => 'How many failed leads to return per page, 1 to 100. The jobs breakdown is always complete and is not paginated.',
						'default'     => 20,
						'minimum'     => 1,
						'maximum'     => 100,
					),
				),
			),
			'risk'         => 'readonly',
			'handler'      => array( Leads::class, 'notification_failures' ),
		),

		'notifybay/product-summary'       => array(
			'label'        => __( 'Waitlist Size By Product', 'notifybay-waitlist-and-stock-alert-woo' ),
			'description'  => 'How many people are waiting for each product, biggest waitlist first, with a per-status breakdown and whether the product is currently in stock (in_stock is null when the product no longer exists in WooCommerce, in which case product_name is the name recorded at signup). Grouped by product, so a variable product is reported as one waitlist rather than one per variation; use list-leads with product_id if you need the variation split. Returns total, the number of products with any leads at all, so a full list is distinguishable from one truncated by limit. Answers "which product has the most demand" in one call instead of paging the whole lead list.',
			'input_schema' => array(
				'type'                 => 'object',
				// Reject unknown keys. Without this, an invented filter such as
				// {"bogus_filter":"x"} was accepted and returned the FULL
				// unfiltered result set -- indistinguishable from a successful
				// filtered query, because Model::paginate() silently drops any
				// key that is not queryable. A wrong answer that looks right is
				// worse than an error, so this errors now.
				'additionalProperties' => false,
				'properties'           => array(
					'limit' => array(
						'type'        => 'integer',
						'description' => 'How many products to return, 1 to 50, ordered by waitlist size descending.',
						'default'     => 10,
						'minimum'     => 1,
						'maximum'     => 50,
					),
				),
			),
			'risk'         => 'readonly',
			'handler'      => array( Leads::class, 'product_summary' ),
		),

		// --- idempotent: running it twice leaves the same state -----------
		'notifybay/update-lead'           => array(
			'label'        => __( 'Update Waitlist Lead', 'notifybay-waitlist-and-stock-alert-woo' ),
			'description'  => "Change a single waitlist lead's status or email address. Running this twice with the same input has no additional effect. Setting status to unsubscribed is how you remove somebody from a waitlist without deleting their record. This only edits the record: it never emails the customer, and setting status to notified does not send a back-in-stock email, it just marks one as sent. Returns the updated lead and a changed list naming the fields that were written.",
			'input_schema' => array(
				'type'                 => 'object',
				// Reject unknown keys. Without this, an invented filter such as
				// {"bogus_filter":"x"} was accepted and returned the FULL
				// unfiltered result set -- indistinguishable from a successful
				// filtered query, because Model::paginate() silently drops any
				// key that is not queryable. A wrong answer that looks right is
				// worse than an error, so this errors now.
				'additionalProperties' => false,
				'properties'           => array(
					'id'         => array(
						'type'        => 'integer',
						'description' => 'The lead id to change, as returned by list-leads.',
						'minimum'     => 1,
					),
					'status'     => array(
						'type'        => 'string',
						'description' => 'New status for the lead. Same values list-leads filters on. Supply this, user_email, or both -- supplying neither is an error.',
						'enum'        => $notifybay_statuses,
					),
					'user_email' => array(
						'type'        => 'string',
						'description' => 'Corrected email address for the lead. Must be a valid address; it is not verified with the customer.',
					),
				),
				'required'             => array( 'id' ),
			),
			'risk'         => 'idempotent',
			'handler'      => array( Leads::class, 'update_lead' ),
		),

		// --- destructive: emails real customers ---------------------------
		'notifybay/resend-notifications'  => array(
			'label'        => __( 'Resend Back-in-Stock Notifications', 'notifybay-waitlist-and-stock-alert-woo' ),
			'description'  => 'Queue the back-in-stock email again for specific leads. THIS EMAILS REAL CUSTOMERS AND CANNOT BE TAKEN BACK, and it is NOT idempotent -- calling it twice with the same ids sends twice. Only leads whose status is failed (the engine gave up after three attempts) or notified (it was sent and you want it sent again) are eligible; anything else is reported back in wrong_status and left alone. A lead whose product is out of stock or no longer exists is reported in out_of_stock and NOT sent, because the email says the item is back. Repeated ids are collapsed, so listing an id twice in one call sends once. Reports each id in exactly one of five lists. queued and not_found are plain id lists. wrong_status rows are {id, status}, where status is the lead\'s current state -- the reason it was not eligible. out_of_stock rows are {id, product_id, product_name, reason}, where reason is out_of_stock or product_missing. failed rows are plain ids whose record could not be written; that is an infrastructure error, not a lead state, so retry rather than treating it as ineligible. Status is checked before stock, so a lead that is both ineligible and out of stock is reported only in wrong_status. Get the ids from notification-failures or list-leads. Sending is asynchronous, so a queued id means the job was scheduled, not that the mail has left.',
			'input_schema' => array(
				'type'                 => 'object',
				// Reject unknown keys. Without this, an invented filter such as
				// {"bogus_filter":"x"} was accepted and returned the FULL
				// unfiltered result set -- indistinguishable from a successful
				// filtered query, because Model::paginate() silently drops any
				// key that is not queryable. A wrong answer that looks right is
				// worse than an error, so this errors now.
				'additionalProperties' => false,
				'properties'           => array(
					'ids' => array(
						'type'        => 'array',
						'description' => 'Lead ids to re-notify, 1 to 50 per call. Each must currently be failed or notified.',
						'items'       => array(
							'type'        => 'integer',
							'description' => 'A lead id.',
							'minimum'     => 1,
						),
						'maxItems'    => 50,
						'minItems'    => 1,
					),
				),
				'required'             => array( 'ids' ),
			),

			/*
			 * `destructive`, though it destroys nothing. The ladder is the only
			 * control a store owner has over what an assistant may do, and
			 * "sends mail to real customers, irreversibly" belongs above "edits
			 * a lead" whatever the tier is nominally named for. Putting it at
			 * `idempotent` would hand every read+modify assistant a button that
			 * mails the waitlist.
			 */
			'risk'         => 'destructive',
			'capability'   => array( 'all_of' => array( 'manage_notifybay' ) ),
			'handler'      => array( Leads::class, 'resend_notifications' ),
		),

		// --- destructive: deletes ----------------------------------------
		'notifybay/delete-leads'          => array(
			'label'        => __( 'Delete Waitlist Leads', 'notifybay-waitlist-and-stock-alert-woo' ),
			'description'  => 'Permanently delete the waitlist leads with the given ids. This cannot be undone and the customers are not notified. Prefer setting status to unsubscribed unless the records genuinely need to be erased. Reports each id separately rather than failing the whole call on the first miss: deleted lists the ids that were erased, not_found the ids that did not exist, and failed the ids that exist but could not be erased. Repeated ids are collapsed.',
			'input_schema' => array(
				'type'                 => 'object',
				// Reject unknown keys. Without this, an invented filter such as
				// {"bogus_filter":"x"} was accepted and returned the FULL
				// unfiltered result set -- indistinguishable from a successful
				// filtered query, because Model::paginate() silently drops any
				// key that is not queryable. A wrong answer that looks right is
				// worse than an error, so this errors now.
				'additionalProperties' => false,
				'properties'           => array(
					'ids' => array(
						'type'        => 'array',
						'description' => 'Lead ids to erase, 1 to 50 per call, as returned by list-leads.',
						'items'       => array(
							'type'        => 'integer',
							'description' => 'A lead id.',
							'minimum'     => 1,
						),
						// Mandatory on a bulk array parameter, and with rate
						// limiting not implemented it is part of what stands
						// in for one.
						'maxItems'    => 50,
						'minItems'    => 1,
					),
				),
				'required'             => array( 'ids' ),
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
