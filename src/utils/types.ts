/**
 * Type definitions for the NotifyBay plugin.
 *
 * Customize these types for your own plugin's data structures.
 */

export interface PluginData {
	plugin_name: string;
	short_name: string;
	menu_label: string;
	menu_icon: string;
	author_name: string;
	author_uri: string;
	support_uri: string;
	docs_uri: string;
	buy_pro_url: string;
	position: number;
}

export interface WpSettings {
	dateFormat: string;
	timeFormat: string;
}

/**
 * Plugin settings as stored in the WordPress option.
 * Must match the PHP default settings in config/settings.php.
 *
 * A premium add-on (NotifyBay Pro) registers its own additional keys at runtime
 * via the `notifybay_options_properties` / `notifybay_options_defaults` filters;
 * those are typed within the add-on's own bundle, not here.
 */
export interface PluginSettings {
	// --- Free ---
	general_doubleOptIn: boolean;
	general_backorderWaitlist: string;
	appearance_waitlistButtonText: string;
	appearance_waitlistButtonClass: string;
	appearance_waitlistExpiryEnabled: boolean;
	appearance_waitlistExpiryOptions: string;
	appearance_waitlistExpiryDefault: string;
	appearance_waitlistSuccessMessage: string;
	engine_minStockThreshold: number;
	engine_adminAlerts: boolean;
	email_fromName: string;
	email_fromEmail: string;
	email_restockSubject: string;
	email_restockBody: string;
	email_verificationSubject: string;
	email_verificationBody: string;
	advanced_deleteAllOnUninstall: boolean;
	debug_enableMode: boolean;
}

export interface LeadData {
	id: number;
	user_email: string;
	user_id: number | null;
	product_id: number;
	variation_id: number;
	product_name: string;
	type: string;
	status: string;
	price_at_subscription: number | null;
	user_currency: string | null;
	created_at: string;
}

export interface DashboardStats {
	top_restocks: Array< {
		product_id: number;
		variation_id: number;
		count: number;
		name: string;
	} >;
	potential_revenue: number;
	total_active_waitlist: number;
	total_conversions: number;
	historical_trend: Array< {
		label: string;
		count: number;
	} >;
}

/**
 * Where the MCP settings routes live, or `null` when the MCP tab must not
 * render at all -- either `wpab/mcp-kit` is not installed, or the current user
 * lacks the `manage_options` the kit gates those routes on. Set by
 * Admin::get_mcp_localize().
 */
export interface McpLocalize {
	product_key: string;
	/** Base URL of the kit's settings routes, e.g. `.../wp-json/wpab/v1/notifybay`. */
	rest_url: string;
	app_passwords_url: string;
	/** Core's `wp/v2/users/me/application-passwords` route. */
	app_passwords_rest_url: string;
	current_user_login: string;
	/** False when WordPress refuses Application Passwords, e.g. over plain http. */
	app_passwords_available: boolean;
	is_local_dev: boolean;
	/** Only true on an https development host, where a self-signed cert is plausible. */
	offer_tls_bypass: boolean;
}

/**
 * One Application Password, as core's users/me/application-passwords route
 * reports it. `password` is present only in the response that creates one.
 */
export interface McpAppPassword {
	uuid: string;
	name: string;
	created: string;
	last_used: string | null;
	last_ip: string | null;
}

/**
 * One row of the kit's declarative settings list. The kit returns its settings
 * this way so that adding a setting never requires a change here.
 */
export interface McpSettingField {
	key: string;
	label: string;
	type: string;
	value: boolean | string;
	options?: string[];
}

/**
 * The payload of `GET /wpab/v1/<product_key>/status`, which `POST .../settings`
 * also returns so the panel always re-renders from server truth.
 */
export interface McpStatus {
	product_key: string;
	status: string;
	endpoint: string;
	tool_count: number;
	available_access_levels: string[];
	client_config: Record< string, unknown >;
	settings: McpSettingField[];
}

/**
 * The main store type, matching the data passed by PHP's wp_localize_script().
 */
export interface BoilerplateStore {
	version: string;
	root_id: string;
	nonce: string;
	store: string;
	rest_url: string;
	is_pro: boolean;
	pluginData: PluginData;
	wpSettings: WpSettings;
	plugin_settings: PluginSettings;
	currency: {
		code: string;
		symbol: string;
	};
	mcp?: McpLocalize | null;
}
