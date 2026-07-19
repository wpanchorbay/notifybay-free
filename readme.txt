=== NotifyBay ===
Contributors: wpanchorbay, sankarsan
Tags: boilerplate, react, typescript, admin, rest-api
Requires at least: 5.6
Tested up to: 6.9
Requires PHP: 7.0
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

NotifyBay is a modern, production‑ready WordPress plugin boilerplate that bridges classic WordPress development with a full‑featured React/TypeScript admin UI.

== Description ==

NotifyBay is a modern, production‑ready WordPress plugin boilerplate that bridges classic WordPress development with a full‑featured React/TypeScript admin UI. It provides:

* A robust PHP backend built with OOP, PSR‑4 autoloading, and a singleton base class.
* REST API infrastructure with permission‑checked base controllers.
* Dynamic cron job manager for background tasks.
* A resource generator CLI to scaffold models, migrations, and API controllers.
* Full Settings API abstraction.
* Database manager for custom tables plus a logger.
* White‑labeling capabilities (plugin name, slug, icons, URLs).
* Dual React component libraries:
  * `common` – Modern Tailwind‑styled UI components.
  * `classics` – Components that mimic native WordPress/WooCommerce styling.
* Tailwind CSS integration with a custom prefix (`notifybay-`) and a pre‑flight guard to preserve native styles.
* Production build system powered by `@wordpress/scripts` (Webpack) supporting modern and legacy builds.

== Key Features ==

=== Backend (PHP) ===

* Namespace‑based autoloader – PSR‑4 compliant.
* Base Singleton (`Base.php`) – Automatic hook registration.
* REST API (`ApiController.php`) – Secure, extendable base controller.
* Cron Manager (`Cron.php`) – Schedule, manage, and run WP‑Cron jobs.
* Resource Generator (`bin/make-resource`) – Scaffold models, migrations, controllers.
* Settings API (`Settings.php`) – Easy settings handling with schema validation.
* Database Manager (`DbManager.php`) – Custom table creation via `dbDelta()`.
* Logger (`Logger.php`) – DB‑based logging with JSON context and levels.
* White‑label filters – Override plugin name, slug, icon, URLs.

=== Frontend (React/TS) ===

* SPA architecture – React 18+ with `react-router-dom`.
* Dual component libraries:
  * `common` – 30+ Tailwind components (modals, toasts, multi‑select, steppers, etc.).
  * `classics` – 10+ components matching native WP/WC UI (inputs, tables, tooltips).
* Tailwind CSS – Configured with `notifybay-` prefix.
* Pre‑flight guard – `notifybay-ignore-preflight` class prevents Tailwind resets from breaking native styles.
* State management – React Context integrated with `wp_localize_script`.
* Production build – Webpack via `@wordpress/scripts`.

== Directory Structure ==

`
notifybay/
├─ app/                     # PHP backend
│  ├─ Admin/
│  ├─ Api/
│  ├─ Core/
│  ├─ Data/
│  ├─ Database/
│  ├─ Helper/
│  ├─ Models/
│  └─ functions.php
├─ bin/                     # CLI utilities
├─ config/                  # Registration files (api, core, migrations)
├─ src/                     # React/TS SPA
│  ├─ components/
│  │   ├─ classics/        # Native‑style components
│  │   └─ common/           # Modern Tailwind components
│  ├─ pages/                # Dashboard, logs, etc.
│  ├─ store/                # Context API store
│  ├─ styles/               # SCSS & Tailwind guard
│  ├─ utils/                # API helpers, types, hooks
│  ├─ App.tsx               # Router & layout
│  └─ index.tsx             # Entry point
├─ assets/                  # Images, icons
├─ languages/               # i18n files
├─ vendor/                  # Composer dependencies
├─ notifybay.php            # Main plugin bootstrap
├─ uninstall.php            # Cleanup on delete
├─ rename.sh                # Global find‑replace script
├─ package.json
├─ composer.json
├─ tsconfig.json
├─ tailwind.config.js
├─ postcss.config.js
└─ webpack.config.js
`

== Installation ==

1. Clone & Rename:
   `
   git clone <repo_url> your-plugin-name
   cd your-plugin-name
   `
2. White‑label: Run the provided `rename.sh` or manually replace the following strings throughout the codebase:
   * `NotifyBay` -> `YourNamespace`
   * `WP_BOILERPLATE_` -> `YOUR_PLUGIN_`
   * `notifybay` (slug) -> `your-plugin-slug`
   * `notifybay` (snake) -> `your_plugin_slug`
   * `notifyBay` (JS) -> `yourPlugin`
   * `NotifyBay` (UI text) -> `Your Plugin Title`
   * `notifybay-` (Tailwind prefix) -> `yourprefix-`
3. Install Dependencies:
   `
   npm install
   composer install
   `
4. Development: Start hot‑reloading dev server:
   `
   npm run start
   `
   Ensure `SCRIPT_DEBUG` is `true` in your WordPress installation to load dev assets.
5. Production Build:
   `
   npm run build
   `
   Assets are compiled into the `/build` directory.

== Resource Generator ==

Scaffold a new resource (Model + Migration + API Controller) with:
`
npm run make:resource -- <ResourceName>
`

This creates:

* `app/Models/<ResourceName>.php`
* `app/Database/Migrations/Create<Resources>Table.php`
* `app/Api/<ResourceName>Controller.php`
* Registers everything in `config/migrations.php` and `config/api.php`.

== API Testing ==

The `src/pages/Settings.tsx` includes a demo CRUD flow against the `demo-items` endpoint. Modify it to test any newly generated resource and monitor network requests in the browser dev tools.

== Layout & Component Systems ==

* Layout Switcher (`src/App.tsx`):
  * `ClassicLayout` – Native‑style settings experience.
  * `AppLayout` – Modern dashboard layout.
* Component Libraries:
  * `classics/` – Native WordPress/WooCommerce look (inputs, tables, tooltips).
  * `common/` – Modern Tailwind UI (modals, toasts, multi‑select, etc.).

== Styling Architecture ==

Tailwind’s preflight reset can break native WP styles. Adding the `notifybay-ignore-preflight` class to any element (or its parent) disables the reset for that subtree, preserving native typography, input borders, and other WP/WC styling.

== Development Tools ==

* ESLint & Prettier – Pre‑configured for modern JavaScript/TypeScript.
* GitHub Actions – Optional CI workflow included.
* Dual Build System – Generates both modern ES modules and legacy builds for older browsers.

== Requirements ==

* WordPress 5.6+
* PHP 7.0+
* Node.js 18+

== License ==

GPLv2 or later.