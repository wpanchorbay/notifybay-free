# NotifyBay Pro — Free ↔ Pro Architecture Plan

## Context

NotifyBay currently ships every feature in one free plugin. The goal is a **freemium split**: a free plugin distributed on wordpress.org (self-updating) plus a separate licensed **`notifybaypro`** add-on. This plan defines *how the two plugins communicate and work together*. It is modelled directly on the author's two existing, working freemium pairs — **campaignbay↔campaignbaypro** and **optionbay↔optionbaypro** — which share NotifyBay's boilerplate lineage (`Loader`, `Base`, single-option `Settings`, `{prefix}_admin_script` asset filters, wpanchorbay.com license server). The feature-tier split itself is in [free-vs-pro-split.md](free-vs-pro-split.md); this doc is the plumbing.

## Decisions (locked)

| # | Decision | Choice |
|---|---|---|
| 1 | Delivery model | Separate Pro add-on plugin (not single-plugin unlock) |
| 2 | Premium code location | Extracted **out of Free into Pro** (wordpress.org compliance) |
| 3 | Settings storage | Shared `notifybay` option; Pro registers keys via filters |
| 4 | UI injection | **Registry (optionbay-style)** — `@wordpress/hooks` slots + `window.notifybay` component exports; Pro ships a thin extension bundle |
| 5 | Data/tables | **Free owns all tables/migrations**; Pro owns none |

## Coupling model (one-directional: Pro → Free)

Free is completely **Pro-agnostic** — it references Pro nowhere. It just exposes generic seams. Pro is a thin add-on that boots after Free and plugs into those seams. This mirrors both reference pairs exactly.

### 1. Bootstrap & load coordination (Pro side)

`notifybaypro/notifybaypro.php`:
- Header: `Requires Plugins: woocommerce, notifybay`; define `NOTIFYBAY_PRO_REQUIRED_FREE_VERSION`.
- Boot on `add_action('plugins_loaded', 'notifybay_pro_run', 20)`. NOTE: Free calls `notifybay_run()` at **file scope** (`notifybay.php:54`), not on a `plugins_loaded` hook — file-scope execution during plugin load precedes any `plugins_loaded` callback regardless of alphabetical order, so Pro on `plugins_loaded` pri 20 is safely after Free. (Do **not** "align" Free to a `plugins_loaded` pri-10 callback based on an earlier draft of this doc that wrongly claimed it already was one.)
- `notifybay_pro_run()` gates: (a) `defined('NOTIFYBAY_VERSION')` else admin-notice + bail; (b) `version_compare(NOTIFYBAY_VERSION, NOTIFYBAY_PRO_REQUIRED_FREE_VERSION, '<')` else notice + bail; (c) WooCommerce active. Only then `\NotifyBayPro\Core\Plugin::get_instance()->run()`.
- Define `NOTIFYBAY_PRO_*` constants (PATH/URL/VERSION/REMOTE_URL = `https://wpanchorbay.com/wp-json/`).

### 2. Namespace & autoloading

- Pro namespace `NotifyBayPro\` → `app/` via its **own** Composer PSR-4 autoloader (`autoloader-suffix` set to avoid collision), loaded from Pro's `vendor/autoload.php`.
- Pro **reuses Free's runtime classes across the namespace boundary**: Pro's REST controllers `extends \NotifyBay\Api\ApiController`; Pro reads settings via `\NotifyBay\Core\Settings::get_instance()`. Guaranteed safe by the priority-20 ordering.
- **Pro does NOT literally share Free's `Loader` collector instance.** Free's `Loader::run()` fires synchronously at file-scope (`notifybay_run()` calls `Plugin::__construct()`, which calls `$this->loader->run()` immediately) — this completes *before* `plugins_loaded` even begins, i.e. before Pro's pri-20 callback runs. Adding hooks to the shared `Loader::get_instance()` after that point would sit unregistered unless `run()` were invoked a second time. Simpler and equally safe: Pro's own components call WordPress's native `add_action()`/`add_filter()` directly in their `run()` methods (implemented this way in `notifybaypro/app/Core/Plugin.php`). `plugins_loaded` still fires well before `init`, so there's no timing disadvantage.
- Pro carries its own parallel `Core\Plugin` + `config/core.php` + `config/api.php` internal manifests (same pattern as Free). **Free's manifests stay hardcoded — no cross-plugin class registry is needed** (matches both references).

### 3. Settings sharing (extend Free)

Pro stores its keys inside Free's single `notifybay` option. **Free changes needed** — add two filters mirroring optionbay:
- `notifybay_options_properties` — *already exists* (`Core/Settings.php:198`). Pro appends its schema properties (`general_wishlistEnabled`, `email_priceDrop*`, `engine_fairPlay*`, `appearance_fomo*`, `engine_hurry*`, `license_key`, `license_status`).
- `notifybay_options_defaults` — **NEW filter to add** in `Settings::get_default_settings()` (`Settings.php:117-119`), so Pro-registered keys get defaults and survive `sanitize_settings_object()` (which already respects the filtered schema).

Result: one option, one REST save pipeline (`SettingsController`), one localize payload, one React state blob — Pro keys ride along automatically.

### 4. License & self-update (move OUT of Free, INTO Pro)

Free currently wrongly contains license code. **Remove from Free**, relocate to Pro:
- Delete `Core/LicenseManager.php`, `Api/LicenseController.php`, the license instantiation in `Core/Plugin.php:108-110`, and the `license_key`/`license_status` keys + `AdvancedTab` license UI from Free.
- Pro adds: `NotifyBayPro\Core\LicenseManager` (activate/check against `.../license-server/v1/activate|check`, 12h transient cache), `NotifyBayPro\Api\LicenseController` (REST `notifybaypro/v1/license/activate|deactivate`), and PUC self-update via `PucFactory::buildUpdateChecker('.../update-check/notifybaypro/{key}', ...)` gated on `license_status === 'active'`. Pro re-injects `license_key`/`license_status` into the shared option via the settings filters above.
- Free updates via wordpress.org (readme.txt, `License: GPLv2`). Pro header `License: Proprietary`.
- **Pro needs its own REST root in JS.** Free's localize hardcodes `rest_url` to `notifybay/v1` (`Admin.php:302`); Pro's controllers live at `notifybaypro/v1`. Either Free's localize adds a generic `rest_root`, or Pro's bundle computes its own from `wpApiSettings`/`window.notifyBay_Localize.rest_url`. Trivial but required for the license UI's first call.
- **Pro's `Deactivator` is NOT empty** (the reference plugins' are, but NotifyBay Pro schedules Action Scheduler work). On deactivation Pro must `as_unschedule_all_actions()` for any recurring actions it scheduled (e.g. its own hurry/price check), or AS keeps firing no-op hooks forever. On **uninstall**, Pro must scrub `license_key`/`license_status` from the shared `notifybay` option (Free's uninstall can't — it doesn't know those keys).

### 5. Engine extraction & PHP seams (the biggest Free-side work)

Free's premium logic is baked into `Engine/Dispatcher` (`dispatch_price_drop`, `dispatch_hurry_alerts`, fair-play inside `dispatch_waitlist`), `Engine/Worker` (email send), **and `Api/FrontendController`** (wishlist subscribe + FOMO/wishlist product-state payload). Extract via a **small number of surgical seams**, not filters at every decision point — every Free seam is a permanent public-API commitment. Verified against source, the seams are:

**Seam 1 — Fair-Play limit** (`Dispatcher::dispatch_waitlist`): **Free KEEPS lines 69-92** (product resolution + `is_in_stock` guard + the **min-stock-threshold** check `engine_minStockThreshold`, which is a FREE feature). The filter replaces **only lines 94-123** (the fair-play reserved-count block). Signature: `apply_filters('notifybay_waitlist_notify_limit', null, $product, $stock_quantity, $product_id, $variation_id)`.
- **Cap semantics matter — `0` is ambiguous in the current code and must not be the "unlimited" sentinel.** Today `$notify_limit = 0` initializes to "unlimited" but the fair-play branch also `return`s when `$notify_limit <= 0` meaning "all stock reserved, notify NOBODY" — two opposite meanings. Define the filter contract as: **`null` = unlimited (free, no `LIMIT`)**; a returned **integer `>= 0` = exact cap**, where `0` legitimately means send-none this run. Dispatcher then: `null` → no LIMIT clause; `0` → abort; `>0` → `LIMIT n`. Getting this wrong silently oversells during a partial restock — the exact failure fair-play exists to prevent.
- Pro owns the reserved-count computation. Free's `Models\Lead` gets a **parameterized data-accessor** `count_reserved($product_id, $variation_id, $cutoff_date, $statuses)` — the reservation-window/ratio *policy* stays in Pro (Pro computes the cutoff + ratio and passes them in), so Free's model embeds only a query, not a pro concept.

**Seam 2 — Email-event registry** (`Worker`): premium-ness is keyed by **event type**, and it is smeared across four spots that must stay consistent — the subject map (`Worker.php:211-216`), body-key derivation (`:269-270`), template map (`:280-284`), and the post-send status transition (`:153`). Do **not** wrap each in its own filter. Replace with ONE registry:
```php
$events = apply_filters( 'notifybay_email_events', array(
  'waitlist_restock' => array( 'subject_default'=>..., 'body_key'=>..., 'template'=>'emails/restock-notification', 'status_after'=>'notified' ),
  'verification'     => array( ... ),
) );
```
Pro registers `wishlist_price_drop` and `hurry_low_stock` entries. Keep `verification` (double opt-in, a FREE event) in Free's default registry — the Worker's `verification`-bypasses-`processing`-status pre-check (`Worker.php:73`) must survive the refactor; don't let it get "cleaned up." **Critical — hard-bail on unknown event types.** Today `get_message()` falls back to `'emails/restock-notification'` for any unknown event (`Worker.php:286`). After extraction, an unknown event (e.g. a `wishlist_price_drop` AS job still pending when Free is updated but Pro isn't active yet) would send a **wrong "back in stock" email to a wishlist customer** — silent and irreversible. The extracted Worker must, on an event type absent from the registry, **leave the lead in `processing` and return** (no send); the existing reaper `CronTasks::recover_stale_processing_leads` (`CronTasks.php:59,74-75`, every 30 min, resets >15-min `processing` → `active`) recovers it once Pro is active. This upgrade-window bail is the single most important correctness requirement in the whole split — see the Upgrade-window section below.

**Seam 3 — Lead-type whitelist** (`Api/FrontendController`): the subscribe endpoint validates `type: required|in:waitlist,wishlist`, gates on `general_wishlistEnabled`, snapshots `price_at_subscription`, and the product-state payload computes `fomo_count`/`wishlist_count`/`show_wishlist`/wishlist messages (`FrontendController.php:158,202-252`). Without a seam here the "free" plugin **still ships a working wishlist ingestion pipeline** — the exact wp.org-compliance violation the split exists to prevent. Add `apply_filters('notifybay_allowed_lead_types', array('waitlist'))` driving the validation `in:` rule, and a filter on the product-state response array so Pro adds its FOMO/wishlist fields. Free renders only waitlist; Pro's frontend classes add wishlist + FOMO.

**Seam 4 — split `Core/WooCommerceHooks`** (registered in Free's `config/core.php`, but half-premium). Free KEEPS: `handle_stock_change` (restock → `notifybay_run_dispatcher`), `merge_guest_leads`, and the deletion/visibility/trash integrity handlers. **MOVE to a Pro class** (hooking WooCommerce directly, not via a Free seam):
- `track_conversions` (`WooCommerceHooks.php:229-269`, `engine_conversionWindow`) — this is the **revenue-analytics** feature; a paid feature currently sitting in the free zip. Pro hooks `woocommerce_order_status_completed` itself.
- The **price-drop enqueue** inside `handle_product_update` (`:130`, `as_enqueue_async_action('notifybay_run_price_dispatcher', ...)`). **Remove it from Free** — otherwise Free (Pro absent) keeps enqueuing price-dispatcher jobs with no handler (dead background churn + muddies the compliance claim). Pro hooks `woocommerce_update_product` itself.

**Seam 4b — split `Engine/CronTasks`** (same mixed-profile hazard as WooCommerceHooks; registered in Free's `config/core.php`). Free KEEPS the load-bearing free jobs: `recover_stale_processing_leads` (`:59,97` — **this reaper is what the upgrade-window hard-bail relies on**), `cleanup_expired_verifications` (`:60`), `cleanup_expired_leads` (`:62`). **MOVE to Pro:** `trigger_hurry_dispatcher` (`:61,137`) **and** its `notifybay_daily_hurry_check` scheduling block in `schedule_jobs()` (`:84-86`). If the hurry schedule stayed in Free, Free (Pro absent) would schedule a daily job enqueuing `notifybay_run_hurry_dispatcher` with no handler — dead churn + a paid trigger shipped in the free zip. Pro schedules its own recurring hurry action (unscheduled by Pro's Deactivator, surface 4).

**Seam 4c — split `Api/AdminController`** (mixed-profile REST controller, `config/api.php`). Free KEEPS lead CRUD + `export_leads` (leads list + CSV export are Free) + `get_system_status` (diagnostic). **MOVE to a Pro REST controller** (`notifybaypro/v1`, Pro's own `config/api.php`): `get_stats` (`:129`, computes `potential_revenue` `:170-182` + `total_conversions` + `get_historical_trend` `:199`) and the `notifybay_dashboard_stats` transient — this **is the revenue-analytics backend**. It's incoherent (and a compliance violation) to serve the analytics dashboard *frontend* from Pro while its *backend* sits in Free. Pro's dashboard widget fetches from the pro route (this is exactly why Pro needs its own REST root, surface 4).

Two lower-severity same-pattern surfaces: **`Admin/WooCommerce`** adds a **wishlist** product-list column (`:78,91,95,124`) alongside the waitlist one — gate the wishlist column on `general_wishlistEnabled` or Pro-inject it (Pro data in Free's admin UI). **`Frontend/Shortcodes`** registers `[notifybay_wishlist]` (`:64,100`) alongside the Free `[notifybay_waitlist]` — move that one shortcode's registration/render to Pro (Pro calls its own `add_shortcode`) or gate it. (`Frontend/Endpoints` verify/unsubscribe is generic across lead types — Free, no action.)

**Also remove the dangling AS registrations from `Dispatcher::run()`** — it currently registers `notifybay_run_price_dispatcher`→`dispatch_price_drop` (`:58`) and `notifybay_run_hurry_dispatcher`→`dispatch_hurry_alerts` (`:59`). Deleting those method bodies without removing these `add_action`s **fatals the moment either AS action fires.** Free keeps only `notifybay_run_dispatcher`→`dispatch_waitlist` (`:57`) and `notifybay_run_verification`→`dispatch_verification` (`:60`); Pro re-registers the price/hurry handlers on the same hook names.

**No Free seams needed for triggers** — Pro hooks WooCommerce/Action-Scheduler directly: price-change via `woocommerce_update_product`, and hurry via **its own** recurring AS action (Pro schedules `notifybay_daily_hurry_check` or its own name). Pro registers its dispatchers on the existing AS hook *names* (`notifybay_run_price_dispatcher`, `notifybay_run_hurry_dispatcher`). Pro's fair-play callback (Seam 1) must replicate the existing `managing_stock() && $stock_quantity !== null` guard and return `null` (unlimited) for non-managed-stock products — `$stock_quantity` is `null` there.

**Move the dormant pro assets out of Free too:** `templates/emails/price-drop.php`, `hurry-low-stock.php`, `admin-alert.php` are pro-only files currently in the free zip. Move them to Pro; Pro serves them via the existing `notifybay_locate_template` filter (`TemplateRenderer.php`).

**Precondition — audit 2-arg `get_settings()` calls.** `Settings::get_settings($key='')` takes ONE param (`Settings.php:100`); the codebase calls it as `get_settings('general_wishlistEnabled', true)` in many places (`FrontendController`, `MyAccount`, `ProductPage`, `Dispatcher`) where the 2nd "default" arg is **silently ignored**. It works today only because the key exists in Free's config defaults. Once wishlist/fair-play/hurry keys move to Pro's defaults filter, any such call with Pro inactive returns `false` (correct: feature off) — but the dead 2nd arg must not be relied on. Audit all of them before extracting.

**Fix the pre-existing status-enum bug before splitting** (it becomes a cross-plugin bug after): fair-play counts only `status='notified'` (`Dispatcher.php:109`) and conversion tracking matches only `notified`, but hurry writes `notified_hurry` (`Worker.php:153`) — so hurried leads are invisible to reservation + conversion. Change those matches to `status IN ('notified','notified_hurry')`. **The lead `status` enum is the cross-plugin contract** (`active/processing/notified/notified_hurry/converted/failed/expired/...`) — document it as versioned.

Free's other additive seams already usable by Pro: `notifybay_cron_jobs` (`Cron.php:135`), `notifybay_locate_template`, `notifybay_admin_localize` / `notifybay_admin_script_{context}` (`Admin.php`).

### 6b. Registry version-drift & the JS contract

Free auto-updates from wordpress.org *underneath* a pinned licensed Pro. The PHP version gate only protects against Free-too-**old**; Free-too-**new** renaming a slot, changing a slot payload shape, or dropping a `window.notifybay` export breaks Pro silently. Treat the slot names + `window.notifybay` keys as a **semver'd, add-only public API**:
- Export `window.notifybay.apiVersion`; Pro checks it and, on mismatch, degrades to `buy_pro` upsell placeholders + an admin notice ("update NotifyBay Pro") rather than a broken screen.
- **Never rename or repurpose a slot; only add.** Keep a one-page JS-contract doc in the Free repo.
- Keep `window.notifybay` a **curated, frozen** surface (store hook, `apiFetch` wrapper, a handful of form primitives) — not "whatever Pro needs this week." Every exported component's props become public API.
- **Same-hooks-registry check:** both bundles must resolve `@wordpress/hooks` to the one `wp.hooks` global (wp-scripts' `DependencyExtractionWebpackPlugin` handles this, but Free currently imports `@wordpress/hooks` nowhere and `webpack.config.js:67` overrides `externals` to only react/react-dom — verify hooks stays external, or Pro's `addFilter` lands in a registry Free never reads and every slot silently shows the Free fallback).
- **Legacy build:** NotifyBay ships a `BUILD_TARGET=legacy` variant. Decide explicitly — instrument slots in both bundles, or declare Pro unsupported on the legacy target. Don't leave it implicit.

### 6. UI injection — Registry pattern (Free instrumentation + thin Pro bundle)

**Free changes** (instrument the existing React app — optionbay pattern):
- Add `@wordpress/hooks` `applyFilters()` slots at extension points in `src/`: a settings-sections/tabs slot in `src/pages/Settings.tsx` (the hardcoded tab list at `:191-198` + render switch at `:232-257`), field slots inside `EngineTab`/`EmailTab`/`AppearanceTab`, and a dashboard-widgets slot in `src/pages/Dashboard.tsx`.
- Expose Free's shared internals on a `window.notifybay` registry from `src/admin.tsx`/`src/settings.tsx` (e.g. `useWpabStore`, common form components, `ClassicSettingsTable`) so Pro reuses them without bundling React (webpack already externals React/ReactDOM).
- Add `license_status` handling to the store type (`src/utils/types.ts` `BoilerplateStore`) so Pro can read Pro-ness; **Pro-ness = `plugin_settings.license_status === 'active'`** (no separate `is_pro` boolean — matches both references).

**Pro side:**
- Ship a thin `build/pro-extensions.js` (own webpack/@wordpress/scripts project, React externals) that `addFilter()`s its fields/sections/dashboard widgets/license tab into Free's slots and reads shared components off `window.notifybay`, degrading gracefully to `null` if Free's globals are absent.
- Enqueue it on `admin_enqueue_scripts` priority 15 and **push `notifybaypro-extensions` onto the `deps` array of Free's `notifybay-admin`/`notifybay-settings` script handles** so `addFilter` runs before Free renders. No separate React root — Pro mounts into Free's `#notifybay` root.
- Free keeps rendering `variant: 'buy_pro'` upsell placeholders when Pro is absent (already supported by `Select`/`CardRadioGroup`/`BuyProTooltip`); Pro's bundle simply replaces those with real controls.

### 7. Data / tables

- **Free owns everything, and the schema is ALREADY both-tiers.** No new migration is needed: `CreateNotifybayLeadsTable` already defines `type ENUM('waitlist','wishlist')` (`:41`), `price_at_subscription DECIMAL` (`:43`), and a `status` enum including `notified_hurry` (`:42`). Nothing was ever gated, so Free's schema already accommodates all pro rows. (An earlier draft wrongly listed a "new migration" step — deleted.)
- **Pro must not run raw `$wpdb` against Free's table.** Its only write path is Free's `Models\Lead` + REST controllers. The extracted fair-play reserved-count query (Seam 1) is raw SQL today — give it a method on Free's `Models\Lead` so Pro calls that, otherwise every Free schema change must be diffed against Pro's SQL strings (dual-writer problem through the back door).
- Pro owns **no** tables, **no** activation-hook DB work, **no** migrations. Matches both references.

## Free-side change checklist (what actually gets edited in the free plugin)

1. `Core/Settings.php` — add `notifybay_options_defaults` filter to `get_default_settings()`.
2. `Engine/Dispatcher.php` — Seam 1 (`notifybay_waitlist_notify_limit`, replacing only lines 94-123). Fix status match to `IN ('notified','notified_hurry')`. Delete `dispatch_price_drop`/`dispatch_hurry_alerts` bodies **and their two `add_action` registrations in `run()` (`:58-59`)** (move to Pro) — leaving the registrations pointing at deleted methods fatals when the AS action fires.
3. `Engine/Worker.php` — Seam 2 (`notifybay_email_events` registry); **hard-bail (leave `processing`, no send) on unknown event types** — do not fall back to the restock template.
4. `Api/FrontendController.php` — Seam 3 (`notifybay_allowed_lead_types` + product-state payload filter); Free serves waitlist only.
4b. `Core/WooCommerceHooks.php` — Seam 4: Free keeps stock/merge/integrity handlers; **move `track_conversions` + the price-drop enqueue out to a Pro class**. Free must stop enqueuing `notifybay_run_price_dispatcher`.
4c. `Engine/CronTasks.php` — Seam 4b: Free keeps the `recover_stale_processing_leads` reaper (load-bearing for the hard-bail) + the two cleanups; **move `trigger_hurry_dispatcher` + the `notifybay_daily_hurry_check` schedule to Pro**.
4d. `Api/AdminController.php` — Seam 4c: Free keeps lead CRUD + `export_leads` + `get_system_status`; **move `get_stats` + `get_historical_trend` + the `notifybay_dashboard_stats` transient to a Pro REST controller** on `notifybaypro/v1`.
4e. Minor surfaces: gate/Pro-inject the **wishlist product column** in `Admin/WooCommerce.php`; move the `[notifybay_wishlist]` shortcode in `Frontend/Shortcodes.php` to Pro.
5. Remove `Core/LicenseManager.php`, `Api/LicenseController.php`, license wiring in `Core/Plugin.php`, license keys in `config/settings.php`, license UI in the Advanced settings tab.
6. Move `templates/emails/price-drop.php`, `hurry-low-stock.php`, `admin-alert.php` out of Free into Pro.
7. Audit every 2-arg `get_settings(...)` call (dead default arg) before extracting the keys.
8. Add a `Models\Lead` method for the reserved-count query (so Pro doesn't raw-SQL Free's table).
9. React (`src/`) — add `applyFilters()` slots (settings tabs/sections/fields, dashboard widgets), expose the curated `window.notifybay` registry + `apiVersion`, add `license_status` to store type; verify `@wordpress/hooks` stays a shared external; decide legacy-build support.
10. Remove the premium React tab/field bodies (wishlist, price-drop email, fair-play, FOMO, hurry, analytics dashboard) that now arrive from Pro; leave `buy_pro` upsell placeholders.
11. `Frontend/MyAccount.php` / `Frontend/ProductPage.php` / `Frontend/Blocks.php` — gate/extract the wishlist + FOMO surfaces (they read `general_wishlistEnabled` etc.); blocks move to Pro.
12. i18n: keep Free's `notifybay` `.pot`; Pro gets its own `notifybaypro` text domain (see below).
13. *(Optional, cheapest now)* add the guest-lead / require-login toggle while `FrontendController` is already being reseamed — or explicitly defer it.

## New Pro plugin skeleton (`notifybaypro/`)

- `notifybaypro.php` (bootstrap + version/Free gates), `composer.json` (PSR-4 `NotifyBayPro\`), `vendor/` (own autoloader + plugin-update-checker).
- `app/Core/Plugin.php`, `config/core.php`, `config/api.php` (internal manifests).
- `app/Core/LicenseManager.php`, `app/Api/LicenseController.php` (extends Free's `ApiController`).
- `app/Engine/*` pro dispatchers/FOMO/fair-play hooking Free's seams; `app/Core/SettingsExtension.php` (settings filters); `app/Frontend/*` (wishlist, blocks).
- `src/pro-extensions.tsx` → `build/pro-extensions.js` (registry `addFilter` bundle).

## The upgrade window (highest-risk scenario — design for it explicitly)

Action Scheduler serializes jobs to the DB; they survive plugin updates/deactivations and retry with backoff (up to ~2h out). The dangerous sequence: an existing site auto-updates **Free** (engine extracted) *before* Pro is installed/active, while `notifybay_send_email_worker(lead_id, 'wishlist_price_drop'|'hurry_low_stock')` jobs are pending or in retry. Free's Worker still owns that hook. If it falls back to the restock template (current behavior), a wishlist customer gets a wrong "back in stock" email — **silent, delayed, irreversible.** Requirements:
- Worker **hard-bails** on unknown event types (Seam 2) — leave lead `processing`, no send; the stale-recovery reaper (`CronTasks`, 30-min) resets it to `active` so Pro re-processes once active.
- `ActionSchedulerFallback.php` hardcodes a weight for `notifybay_run_hurry_dispatcher` — after extraction that's a dead entry; filter the weight map (`notifybay_fallback_weights`) or accept documented dead entries.
- Pro's Deactivator unschedules its own recurring AS actions (surface 4).

## Existing-user data policy (the plan must carry this)

Everything ships free today, so live installs already have `type='wishlist'` leads, guests awaiting price-drop alerts, FOMO enabled, tuned fair-play settings, and **posts containing the Waitlist/Wishlist Gutenberg blocks**. Updating Free to the gated build silently stops those pro behaviors, and the blocks render "block unavailable" in the editor for free-only users. This is a **product decision that constrains the migration** — pick and document one:
- *Dormant-and-grandfather:* keep wishlist rows/settings untouched; behaviors resume when Pro is activated; show an admin upsell notice explaining wishlist/price-drop now needs Pro.
- *Expire-with-notice:* mark existing wishlist leads expired + upsell.
- For blocks: register a lightweight free-side block stub (renders nothing / an upsell) so existing posts don't break, or accept the "unavailable" state. Decide before shipping.

## i18n across two plugins

Pro strings use their own `notifybaypro` text domain (own `.pot`, `load_plugin_textdomain`), and Pro's React bundle needs its own `wp_set_script_translations('notifybaypro-extensions', 'notifybaypro', ...)`. Pro strings injected into Free's UI **cannot** ride Free's `notifybay` domain — wordpress.org language packs only cover Free's strings. `npm run makepot` stays Free-only; Pro gets its own.

## Capability / nonce (considered, no action)

Shared `wp_rest` nonce works for both; Pro's controllers `extends \NotifyBay\Api\ApiController` and inherit its `manage_notifybay` permission callbacks. No cross-plugin cap/nonce work needed.

## Verification & safety net

This is the riskiest change this codebase will undergo and the failure surface is **background email to real customers** (silent, delayed, irreversible). Pure manual QA is **not** sufficient. Minimum net (all lightweight):
1. **WP-CLI smoke script** (no framework): a small mu-plugin hooks `pre_wp_mail` to record-not-send; seed leads of each `type`/`status`, flip stock/price, `wp action-scheduler run`, assert lead statuses + captured emails. Directly exercises the seams and the upgrade-window bail. ~1 day, reusable forever.
2. **QIT managed tests** (activation, PHPStan, security) on both zips — tooling available in this environment; catches "Pro fatals because Free renamed a method" cheaply.
3. **PHPStan across the pair** — Pro `extends`/calls Free across the namespace boundary; only static analysis over both trees catches Free-side refactors that break Pro at runtime.
4. **Scripted upgrade-path rehearsal** — old all-free build (with wishlist rows + pending AS jobs) → new Free alone → then + Pro. This is what every existing user actually experiences.

Manual end-to-end passes on top:
1. **Free alone:** standalone waitlist + restock email fire; no license UI; no PHP notices; `buy_pro` placeholders show; a stray `wishlist_price_drop` AS job leaves the lead `processing` and sends nothing.
2. **Missing/old Free:** Pro bails with admin notice; bump `NOTIFYBAY_PRO_REQUIRED_FREE_VERSION` above Free's → version notice.
3. **Both active:** license activation works; pro settings appear in shared tabs and persist in the `notifybay` option across reload; analytics dashboard renders; `window.wp.hooks.didFilter('notifybay_settings_sections') > 0`.
4. **Engine:** restock → waitlist email (free path). Price drop on a wishlisted product → price-drop email (Pro seam). Fair-Play reservation only engages with Pro active. Hurry lead (`notified_hurry`) is counted by fair-play + conversion tracking (enum fix).
5. **Update channel:** Free updates from wordpress.org; Pro PUC checks wpanchorbay.com only when licensed.
6. **Deactivation/uninstall:** Pro deactivation unschedules its AS actions; Pro uninstall scrubs `license_*` from the shared option.
