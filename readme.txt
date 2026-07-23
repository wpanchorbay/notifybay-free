=== NotifyBay - Waitlist and Stock Alert for WooCommerce ===
Contributors: wpanchorbay, sankarsan
Tags: woocommerce, waitlist, back-in-stock, stock-alert, inventory
Requires at least: 6.8
Tested up to: 7.0
Requires PHP: 7.0
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Capture high-intent leads on out-of-stock WooCommerce products, then automatically notify customers the moment items are back in stock.

== Description ==

NotifyBay bridges the gap between customer demand and inventory availability. It lets WooCommerce store owners capture high-intent leads when a product is out of stock (Waitlists), then handles the follow-up automatically — recovering sales that would otherwise be lost.

By leveraging background processing and intelligent notification logic, NotifyBay does this without slowing down your store or your customers' checkout experience.

= Key Features =

* **Waitlists & Back-in-Stock Alerts** — Customers can join a waitlist on any out-of-stock product and are automatically emailed the moment it's restocked.
* **Fair-Play Dispatch Engine** — Intelligent dispatch logic calculates how much restocked inventory is actually available to notify waitlisted customers about, respecting a configurable reservation window so alerts are distributed fairly and nobody is notified about stock that's already been claimed.
* **Asynchronous Processing** — Notification emails are queued and sent in the background via Action Scheduler, so high-volume dispatch never impacts your customers' checkout experience or your site's performance.
* **Conversion Tracking** — Deep integration with WooCommerce order data attributes recovered sales back to NotifyBay alerts, so you can see the impact on your bottom line.
* **Guest-to-Account Merging** — Guest waitlist subscriptions are automatically merged into a customer's account the moment they register, so nothing gets lost.
* **My Account Integration** — Customers get a dedicated Waitlist tab in their WooCommerce "My Account" area to review and manage their own subscriptions.

NotifyBay is an independent product and is not officially affiliated with, endorsed by, or sponsored by WooCommerce or Automattic Inc. WooCommerce is a registered trademark of Automattic Inc.

== Installation ==

1. Upload the plugin files to the `/wp-content/plugins/` directory, or install the plugin directly through the WordPress "Plugins" screen (Plugins → Add New → Upload Plugin).
2. Make sure WooCommerce is installed and active — NotifyBay is an add-on for WooCommerce and requires it to function.
3. Activate the plugin through the "Plugins" screen in WordPress.
4. Go to WooCommerce → Settings → NotifyBay to configure your waitlist.

== Frequently Asked Questions ==

= Does this plugin require WooCommerce? =

Yes. NotifyBay adds functionality on top of WooCommerce and will not run without WooCommerce installed and active. You'll see an admin notice if WooCommerce is missing.

= Can a customer join a waitlist without creating an account? =

Yes. Guests can join a waitlist using just their email address. If they later create an account with the same email address, their existing subscriptions are automatically merged into that account.

= How are restock notifications sent? =

Restock notifications are queued and delivered by email in the background using Action Scheduler, so notifying a large number of subscribers at once doesn't slow down your site.

= Will customers be notified about stock that's already been claimed by someone else? =

No. NotifyBay's Fair-Play dispatch engine calculates how much stock is actually available to offer before sending any notifications, so waitlisted customers aren't alerted about inventory that's already been reserved or sold.

== Screenshots ==

1. Screenshots to be added before submission.

== Source Code ==

The compiled JavaScript in the `build/` directory is generated from human-readable source in the `src/` directory using [@wordpress/scripts](https://www.npmjs.com/package/@wordpress/scripts) (webpack). The full, unminified source code and the build tooling are publicly available at:

https://github.com/wpanchorbay/notifybay-free

To rebuild the compiled assets from source: run `npm install`, then `npm run build` (or `bash build.sh`).

== Changelog ==

= 1.0.0 =
* Initial release.

== Upgrade Notice ==

= 1.0.0 =
Initial release.
