<?php
/**
 * Core Configuration
 *
 * Use this file to register your Core classes that need to be initialized.
 * Each class should implement a run($loader) method or similar logic
 * to register its hooks with the Loader.
 *
 * @package    NotifyBay
 * @subpackage Config
 * @since      1.0.0
 * @author     WPAnchorBay <sankarsan@wpanchorbay.com>
 */

// Prevent direct access.
defined( 'ABSPATH' ) || exit;

// Note: Gutenberg blocks (Waitlist/Wishlist) are a premium packaging feature and
// are registered by NotifyBay Pro (its own Blocks class), not here.
return array(
	\NotifyBay\Admin\Admin::class,
	\NotifyBay\Admin\WooCommerce::class,
	\NotifyBay\Core\Settings::class,
	\NotifyBay\Core\Cron::class,
	\NotifyBay\Engine\Dispatcher::class,
	\NotifyBay\Engine\Worker::class,
	\NotifyBay\Emails\EmailManager::class,
	\NotifyBay\Engine\CronTasks::class,
	\NotifyBay\Engine\ActionSchedulerFallback::class,
	\NotifyBay\Core\WooCommerceHooks::class,
	\NotifyBay\Frontend\ProductPage::class,
	\NotifyBay\Frontend\Endpoints::class,
	\NotifyBay\Frontend\MyAccount::class,
	\NotifyBay\Frontend\Shortcodes::class,
);
