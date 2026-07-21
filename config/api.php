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
 * @author     WPAnchorBay <sankarsan@gmail.com>
 */

// Prevent direct access.
defined( 'ABSPATH' ) || exit;

// Note: license activation (NotifyBay\Api\LicenseController) is registered by
// NotifyBay Pro on its own REST namespace, not here.
return array(
	\NotifyBay\Api\SettingsController::class,
	\NotifyBay\Api\FrontendController::class,
	\NotifyBay\Api\AdminController::class,
);
