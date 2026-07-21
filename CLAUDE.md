# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## What this is

NotifyBay is a WooCommerce **Waitlist & Wishlist** marketing-automation plugin: a PHP backend (`app/`) plus a React/TypeScript SPA admin UI (`src/`, compiled into `build/`). It captures leads when products are out of stock (back-in-stock alerts) or when customers want price-drop alerts, then dispatches notifications asynchronously via Action Scheduler.

## Commands

```bash
# Install
npm install          # React admin deps
composer install     # PHP deps (Action Scheduler, rakit/validation)

# Develop (watch)
npm run start        # wp-scripts webpack watch → build/

# Build
npm run build        # dev/prod webpack build
npm run build:prod   # runs build.sh: npm build + prepends GPL license headers to build/*.js
npm run zip          # package.sh → distributable zip in dist/

# Lint / format
npm run lint         # PHP (phpcs) + JS (wp-scripts lint-js) together
npm run lint:php     # phpcs --standard=phpcs.xml.dist  (WordPress-Extra + Docs)
npm run lint:php:fix # phpcbf autofix
npm run lint:js:fix  # eslint autofix on src/
npm run format       # prettier via wp-scripts on src/

# i18n
npm run makepot      # regenerate languages/notifybay.pot
```

There is **no automated PHP/JS test suite**. `tests.md` is a manual QA checklist. Testing dispatch logic means adjusting WooCommerce stock/prices and triggering Action Scheduler jobs manually.

## Backend architecture (`app/`, namespace `NotifyBay\`, PSR-4 → `app/`)

The plugin does **not** hardcode its wiring — it is driven by `config/*.php` return-array manifests. To register a new class, add it to the right manifest; the bootstrap iterates them.

- **Bootstrap:** `notifybay-waitlist-and-stock-alert-woo.php` → `NotifyBay\Core\Plugin::get_instance()`. `Plugin::__construct()` runs three phases:
  - `define_core_hooks()` — instantiates every controller in **`config/api.php`** and hooks its `register_routes()` to `rest_api_init`; also boots `LicenseManager`.
  - `define_admin_hooks()` — instantiates every class in **`config/core.php`** and calls its `run($plugin)`. This is where nearly all functional components live (Admin, Settings, Cron, Engine\Dispatcher/Worker, WooCommerceHooks, Frontend\*, Blocks...).
  - `define_public_hooks()` — enqueues front-end CSS + injects custom CSS from settings.
- **Singletons everywhere:** almost every class extends `NotifyBay\Core\Base` (or reimplements `get_instance()`). `Base` provides `add_action`/`add_filter` helpers bound to `$this`. Never construct these classes directly — use `::get_instance()`.
- **Engine (the core value):** `Engine\Dispatcher` implements the "Fair-Play" logic — computes stock *available for notification* (respecting a reservation window) to avoid overselling. `Engine\Worker` + `Engine\CronTasks` + `Engine\ActionSchedulerFallback` run the actual email sends in the background via Action Scheduler so checkout is never blocked.
- **Conversion tracking:** `Core\WooCommerceHooks` ties recovered revenue back to NotifyBay alerts via WC order hooks; also merges guest subscriptions into user accounts on registration.
- **Data layer:** custom tables managed by `Data\DbManager`. Schema changes are **migrations** registered in **`config/migrations.php`** (classes implementing `Database\MigrationInterface`), run in order on activation (`Core\Activator`) and reverse on uninstall. Do not add ad-hoc `CREATE TABLE` calls — add a migration.
- **Settings:** `Core\Settings` is the single source of truth; the schema is declared and consumed by the React frontend. Read values via `Settings::get_instance()->get_settings('group_key')`.
- **Licensing:** `Core\LicenseManager` + `Api\LicenseController` talk to `NOTIFYBAY_REMOTE_URL` (wpanchorbay.com) and drive the plugin-update-checker. The current branch is `license-server` — license/update work is active here.

## Frontend architecture (`src/`)

- **Two independent React apps**, two webpack entries (`webpack.config.js`): `admin` (`src/admin.tsx` → `AdminApp.tsx`, the SPA dashboard with `react-router-dom`) and `settings` (`src/settings.tsx` → `SettingsApp.tsx`). `src/blocks.tsx` builds the Gutenberg Waitlist/Wishlist blocks. All output to `build/`.
- **Legacy target:** `BUILD_TARGET=legacy` (`npm run build:legacy`) produces an alternate build; components under `src/components/classics/` are a "Classic" WP-admin-styled component set alongside the modern Tailwind `common/` set.
- **State:** React Context (`src/store/` — `wpabStore.tsx`, `AddonContext.tsx`, toast). No Redux.
- **API access:** `src/utils/apiFetch.ts` wraps `@wordpress/api-fetch` against the REST controllers in `app/Api/`. Types in `src/utils/types.ts`; validation via `zod` and `src/utils/validation.ts`.
- **Styling:** Tailwind (`tailwind.config.js`) with the mandatory **`notifybay-` prefix** and a preflight guard so Tailwind resets never leak into native WP admin styles. SCSS entry `src/styles/index.scss`.

## Conventions

- PHP must pass `phpcs.xml.dist` (WordPress-Extra + WordPress-Docs): full docblocks, Yoda conditions, escaping/sanitization, text domain `notifybay`. Run `lint:php:fix` before committing PHP.
- All user-facing strings use the `notifybay` text domain; regenerate the `.pot` with `npm run makepot` when adding strings.
- `graphify-out/` is a generated knowledge-graph artifact (gitignored-style output) — ignore it for code work.
