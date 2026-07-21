<?php
/**
 * Settings Configuration
 *
 * This file defines the default settings and REST API validation schema for the
 * FREE plugin only. Premium (NotifyBay Pro) settings keys — Wishlist, price-drop,
 * Fair-Play, Hurry/FOMO, revenue analytics, and license keys — are NOT declared
 * here. NotifyBay Pro registers its own keys at runtime via the
 * `notifybay_options_properties` (schema) and `notifybay_options_defaults`
 * (defaults) filters in NotifyBay\Core\Settings, so they only exist in the
 * shared `notifybay` option when Pro is installed and active.
 *
 * @package    NotifyBay
 * @subpackage Config
 * @since      1.0.0
 * @author     WPAnchorBay <sankarsan@gmail.com>
 */

// Prevent direct access.
defined( 'ABSPATH' ) || exit;

return array(
	'defaults' => array(
		'general_doubleOptIn'               => false,
		'general_backorderWaitlist'         => '0',
		'appearance_waitlistButtonText'     => 'Notify Me',
		'appearance_waitlistButtonClass'    => '',
		'appearance_waitlistExpiryEnabled'  => false,
		'appearance_waitlistExpiryOptions'  => '7,14,30,60,90',
		'appearance_waitlistExpiryDefault'  => '90',
		'appearance_waitlistSuccessMessage' => 'Spot reserved! You\'ll be among the first to know when we restock.',
		'appearance_customCss'              => '',
		'engine_minStockThreshold'          => 0,
		'engine_adminAlerts'                => false,
		'email_fromName'                    => '',
		'email_fromEmail'                   => '',
		// Restock/verification subject & body now live in WooCommerce → Settings
		// → Emails (WC_Email options); see app/Emails/. Migrated once on upgrade.
		'advanced_deleteAllOnUninstall'     => false,
		'debug_enableMode'                  => false,
	),
	'schema'   => array(
		'general_doubleOptIn'               => array( 'type' => 'boolean' ),
		'general_backorderWaitlist'         => array( 'type' => 'string' ),
		'appearance_waitlistButtonText'     => array( 'type' => 'string' ),
		'appearance_waitlistButtonClass'    => array( 'type' => 'string' ),
		'appearance_waitlistExpiryEnabled'  => array( 'type' => 'boolean' ),
		'appearance_waitlistExpiryOptions'  => array( 'type' => 'string' ),
		'appearance_waitlistExpiryDefault'  => array( 'type' => 'string' ),
		'appearance_waitlistSuccessMessage' => array( 'type' => 'string' ),
		'appearance_customCss'              => array( 'type' => 'string' ),
		'engine_minStockThreshold'          => array( 'type' => 'integer' ),
		'engine_adminAlerts'                => array( 'type' => 'boolean' ),
		'email_fromName'                    => array( 'type' => 'string' ),
		'email_fromEmail'                   => array( 'type' => 'string' ),
		'debug_enableMode'                  => array( 'type' => 'boolean' ),
		'advanced_deleteAllOnUninstall'     => array( 'type' => 'boolean' ),
	),
);
