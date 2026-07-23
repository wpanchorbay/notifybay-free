/**
 * Type definitions for the NotifyBay plugin.
 *
 * Customize these types for your own plugin's data structures.
 */

export interface PluginData {
	plugin_name: string;
	short_name: string;
	menu_label: string;
	custom_icon: string;
	menu_icon: string;
	author_name: string;
	author_uri: string;
	support_uri: string;
	docs_uri: string;
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
 * The main store type, matching the data passed by PHP's wp_localize_script().
 */
export interface BoilerplateStore {
	version: string;
	root_id: string;
	nonce: string;
	store: string;
	rest_url: string;
	pluginData: PluginData;
	wpSettings: WpSettings;
	plugin_settings: PluginSettings;
	currency: {
		code: string;
		symbol: string;
	};
}
