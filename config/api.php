<?php
/**
 * API Configuration
 *
 * Use this file to register your API controllers.
 * Each controller must extend NotifyBay\Api\ApiController
 * and implement get_instance() and run().
 *
 * @package    NotifyBay
 * @subpackage Config
 * @since      1.0.0
 * @author     WPAnchorBay <sankarsan@wpanchorbay.com>
 */

// Prevent direct access.
defined( 'ABSPATH' ) || exit;

// Note: license activation (NotifyBay\Api\LicenseController) is registered by
// NotifyBay Pro on its own REST namespace, not here.
return array(
	\NotifyBay\Api\SettingsController::class,
	\NotifyBay\Api\FrontendController::class,
	\NotifyBay\Api\AdminController::class,

	// Provisions the low-privilege account an AI assistant connects as. Gated on
	// manage_options rather than manage_notifybay, and unreachable with an
	// Application Password -- see McpAccountController::mcp_admin_permissions_check().
	\NotifyBay\Api\McpAccountController::class,
);
