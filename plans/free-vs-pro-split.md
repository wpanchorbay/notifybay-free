# NotifyBay — Free vs. Pro Feature Split

_Decision: split the current all-free plugin into two tiers only — **Free** (distributed on wordpress.org, self-updating) and **Pro** (licensed). The license server and update checker move to Pro; Free updates are handled by wordpress.org. Goal for this split: keep Free genuinely complete and rankable, while Pro is defined by a few clear high-value pillars._

## Current state (important)

- **Nothing is gated today.** Every feature ships free. The license only controls **automatic updates** — `LicenseManager::is_valid()` is never called to lock a feature, and the React store exposes no pro/license flag.
- **The gating UI exists but is unused for real features.** `BuyProTooltip` and the `variant: 'buy_pro' | 'coming_soon'` option flag are implemented, but they appear **only in `src/pages/ClassicShowcase.tsx`** (a component demo). No settings tab tags anything as Pro. → This is a **greenfield split**, not a formalization of existing intent.
- **`notifybaypro/` exists but is empty.** Two viable delivery models:
  - **A) Separate add-on plugin** — extract feature code into `notifybaypro`, Free exposes hooks/filters. Clean for self-contained features. **The license server + update checker live here.**
  - **B) In-place license unlock** — keep code in Free, wrap with `is_valid()` (PHP) + an `isPro` store flag (React) that disables `buy_pro` options. Faster; no code extraction.
  - Recommendation: **B for engine/settings toggles, A for self-contained surfaces** (blocks, analytics dashboard).
- **Excluded from consideration:** `src/store/AddonContext.tsx` (custom form-field builder). It is dead scaffolding — not wired to any route/page, and it's product-addons code (swatches, `price_type`, `formula`) unrelated to waitlist/wishlist.

## The split

Free is a complete, standalone **back-in-stock waitlist** product: capture, verified opt-in, editable emails, expiry, leads export, account management. Pro is defined by four pillars — the **Wishlist / price-drop mode**, **scarcity/FOMO conversion tools**, the **Fair-Play engine**, and **revenue analytics** — plus Gutenberg blocks and the licensed update channel.

| Feature | Free | Pro | Where it lives |
|---|:---:|:---:|---|
| Back-in-stock Waitlist ("Notify Me" + lead capture) | ✅ | ✅ | `Frontend/ProductPage.php`, `Models/Lead.php` |
| Restock email — editable subject + body | ✅ | ✅ | `email_restockSubject/Body`, `Engine/Worker.php` |
| Email from-name / from-email | ✅ | ✅ | `email_fromName/fromEmail` |
| Double opt-in + verification email | ✅ | ✅ | `general_doubleOptIn`, `email_verification*` |
| Leads admin list **+ CSV export** | ✅ | ✅ | `pages/Leads.tsx`, `Api/AdminController.php` |
| Waitlist button text / CSS class | ✅ | ✅ | `appearance_waitlistButtonText/Class` |
| Waitlist expiry (auto-cleanup windows) | ✅ | ✅ | `appearance_waitlistExpiry*` |
| Backorder waitlist + min-stock threshold | ✅ | ✅ | `general_backorderWaitlist`, `engine_minStockThreshold` |
| Admin restock alerts | ✅ | ✅ | `engine_adminAlerts` |
| My Account integration (manage own subscriptions) | ✅ | ✅ | `Frontend/MyAccount.php` |
| Custom CSS | ✅ | ✅ | `appearance_customCss`, `Plugin::enqueue_public_styles` |
| Shortcodes | ✅ | ✅ | `Frontend/Shortcodes.php` |
| Debug mode / delete-on-uninstall | ✅ | ✅ | `debug_enableMode`, `advanced_deleteAllOnUninstall` |
| Require-login / disable-guest toggle _(to build — see gap below)_ | ✅ | ✅ | new setting over `FrontendController` |
| **Wishlist mode + "Add to Wishlist"** | — | ✅ | `general_wishlistEnabled`, `ProductPage.php` |
| **Price-drop alerts** (+ price-drop email template) | — | ✅ | `email_priceDrop*`, wishlist flow |
| Wishlist/Waitlist on shop & category archives | — | ✅ | `appearance_showWishlistOnArchives` |
| **FOMO banner** ("{count} people waiting") | — | ✅ | `appearance_fomo*`, `ProductPage.php` |
| **Hurry / scarcity emails** | — | ✅ | `engine_hurry*`, `email_hurry*`, `Engine/Worker.php` |
| **Fair-Play restock engine** (reservation window, ratio, anti-oversell) | — | ✅ | `engine_fairPlay*`, `Engine/Dispatcher.php` |
| **Revenue / conversion analytics dashboard** | — | ✅ | `pages/Dashboard.tsx` (recharts), `Core/WooCommerceHooks.php`, `engine_conversionWindow` |
| Gutenberg blocks (Waitlist/Wishlist) | — | ✅ | `Frontend/Blocks.php`, `src/blocks.tsx` |
| License activation + auto-updates (server lives in Pro) | ❌ wp.org | ✅ | `Core/LicenseManager.php`, `Api/LicenseController.php` |

## Open gap: guest-lead toggle

There is **no setting to disable guest (non-logged-in) subscriptions today** — guests are always accepted (`FrontendController` takes a guest `email`, double opt-in targets guests, `WooCommerceHooks::merge_guest_leads()` merges them on registration). A "Only allow logged-in users to subscribe" toggle is a small config switch over existing code and belongs in **Free**. Listed above as a to-build item.

## What "moving to Pro" requires (no gate exists yet)

1. **PHP:** expose a pro check (`LicenseManager::is_valid()`) and short-circuit gated engine/frontend paths.
2. **React:** pass `is_pro` / license status through `wp_localize_script` into `wpabStore`, then set `variant: 'buy_pro'` on the relevant settings options so the existing `BuyProTooltip` locks them.
3. **Model A only:** move the feature's classes out of `config/core.php` into the `notifybaypro` plugin, wiring via Free-side hooks/filters. The license server + update checker move here regardless.
