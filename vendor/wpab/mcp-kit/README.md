# wpab/mcp-kit

Implementation of the spec in `wpab-mcp-docs/D1-PRD-mcp-kit.md`. Lets any WPAB
WooCommerce plugin expose its own MCP endpoint on top of WordPress's
Abilities API (core since 6.9.0, also vendored by WooCommerce) and
`wordpress/mcp-adapter` (vendored by WooCommerce).

## What's here

Every component D1 §4 lists, one file each:

| File | D1 section | Does |
| --- | --- | --- |
| `Kit.php` | §4.13 | The one public entry point: `Kit::boot( $file, $manifest_path )` |
| `Bootstrap.php` | §4.1 | The seven-step `plugins_loaded` gate |
| `ServerFactory.php` | §4.2 | `create_server()` call on `mcp_adapter_init` |
| `Manifest.php` | §4.3, §7.5 | Loads/validates/normalises the manifest; union-type ban |
| `Registrar.php` | §4.4 | Registers category + abilities; builds `permission_callback` |
| `Guard.php` | §4.5 | `capability` any_of/all_of, derived from the manifest only |
| `Envelope.php` | §4.6 | Response shaping, `MAX_PER_PAGE = 100` |
| `Gate.php` | §4.7 | Transport permission callback, access ladder, activation capability |
| `Settings.php` | §4.8, §4.11 | One `wp_options` row per product; the toggle listener; uninstall |
| `Observability.php` | §4.9 | Static logging entry points → `wc_get_logger()`. Names no adapter symbol, so it stays loadable when the adapter is absent |
| `ObservabilityBridge.php` | §4.9 | The `McpObservabilityHandlerInterface` half, named only in `ServerFactory` |
| `Discovery.php` | §4.10 | The `wpab_mcp_products` filter callback |
| `AdminApi.php` | §4.12 | `wpab/v1` settings REST routes |

Every class/function name called against the Abilities API and
`wordpress/mcp-adapter` (`wp_register_ability_category`, `create_server()`'s
13-argument signature, `McpObservabilityHandlerInterface::record_event()`,
the transport permission callback's `WP_Error`-fails-open behavior) was
checked against the actual packages vendored at
`wp-content/plugins/woocommerce/vendor/wordpress/{abilities-api,mcp-adapter}`
in this environment, not assumed from the PRD's prose alone.

## Three places this deviates from a literal reading of D1, on purpose

**1. Bootstrap step 1 does read the manifest, in a narrow sense.**
D1 §4.1 step 1 says registering `Discovery` and the
`wpab_mcp_toggle_<product_key>` listener happens "and no manifest is read."
Read literally that's unsatisfiable: the toggle hook's *name* needs
`product_key`, which only lives inside the manifest, and `Kit::boot()`'s
signature (§4.13) never receives `product_key` as its own argument.
`Manifest::peek_product_key()` resolves this by reading the file's raw
*text* with a regex, never `require`-ing it — so no `__()` call, no ability
array, no side effect the "too early for translation loading" concern is
actually about ever runs at step 1. `Manifest::load()` (the full,
validated, `wpab_mcp_manifest_<product_key>`-filtered load) only ever runs
later, inside the `wp_abilities_api_init` / `mcp_adapter_init` callbacks.

**2. `show_in_rest_index => false` on the MCP route needed a workaround.**
D1 §4.2 requires the MCP route to register with `'show_in_index' => false`.
The installed `wordpress/mcp-adapter`'s own
`HttpTransport::register_routes()` calls `register_rest_route()` with no
`show_in_index` key at all (verified directly in the vendored source) — the
adapter gives `create_server()` callers no lever for this. `ServerFactory`
works around it by hooking WordPress core's own `rest_endpoints` filter
(confirmed against `wp-includes/rest-api/class-wp-rest-server.php`) and
forcing the flag onto the kit's specific route after the adapter registers
it. This is a real gap between the spec and the current adapter version, not
an interpretation call — worth flagging upstream if `wordpress/mcp-adapter`
ever adds a native option for it, at which point this workaround should be
deleted in favor of passing it directly.

**3. `Kit::VERSION` is invented.** D1 doesn't specify what the
kit's own version string should start at; picked as a reasonable default,
not a spec requirement.

## What's deliberately not done yet

The two largest items that used to head this list — "no consuming plugin" and
"`scoper.inc.php` is syntax-checked, not run" — are **done**. See
`ACCEPTANCE.md`: all 17 of D1 §9's criteria pass against a live WordPress 7.0.4
/ WooCommerce 10.8.1 install, driven over real HTTP with Application Passwords,
against PHP-Scoper-packaged zips rather than a dev checkout. Eleven defects
came out of actually running it; none were visible to `php -l`, `composer validate`
or source review.

That evidence is now **executable**: `composer test` runs 119 assertions over
all 17 criteria, all 11 defects, `Guard`'s branches, and the kit's own i18n. `composer cs` and
`composer compat` are clean, and a CI workflow runs both plus a WP/PHP matrix
(written, not yet executed). Three more
items that used to head this list — the hand-written scoped autoloader, the
untested `any_of` branch, and the missing WPCS ruleset — are done.

What genuinely remains:

- **One WordPress install, and CI is unverified.** Every recorded result comes
  from a single WordPress 7.0.4 / WooCommerce 10.8.1 / PHP 8.3.6 site. The CI
  workflow builds a throwaway WordPress and runs the same suite against it, but
  has never executed — there is no runner here.
- **PHP 7.4 is proven statically, not at runtime.** Sources parse on a real
  7.4.33 and PHPCompatibility is clean at 7.4–8.3, but the kit has never
  executed on 7.4 with WordPress and WooCommerce loaded.
- **The kit has never been integrated into a real product** — only the three
  throwaway fixtures. That is the obvious next step.
- **It is not published anywhere.** No git remote, not on Packagist, so a
  consuming plugin can only reach it via a local path repo. The remaining hard
  blocker on a first integration, and a hosting decision rather than a code
  change.
- **No PHP version floor is specified anywhere in D1/D4.** `composer.json`
  sets `>=7.4` as a reasonable floor, not a documented requirement.

`ACCEPTANCE.md`'s "Still not verified" section is the authoritative list.

## Verified so far

- `php -l` clean on all 13 source files plus `scoper.inc.php` (PHP 8.3.6).
- `composer validate` passes.
- Every cross-class static method call (`Settings::`, `Gate::`, `Manifest::`,
  etc.) checked against the method it actually calls — one dead wire caught
  and fixed in the process: `Registrar::register_category()` existed but was
  never hooked to `wp_abilities_api_categories_init` until this pass, which
  would have meant zero abilities ever registering (categories must exist
  first).

### Second pass: checked against the adapter/Abilities API source, not just internal wiring

- **`Observability`'s interface conformance.** `McpObservabilityHandlerInterface::record_event()`
  is an instance method (`public function record_event(string $event, array
  $tags = [], ?float $duration_ms = null): void`), and `McpServer::setup_handlers()`
  does `new $observability_handler()` on the class-string `create_server()`
  is given. Confirmed by reading both files directly — the signature and the
  instantiation style match what `Observability.php` already does.
- **Hook ordering between `wp_abilities_api_init` and `mcp_adapter_init`.**
  `McpComponentRegistry::register_tools()` calls `wp_get_ability()`
  *eagerly*, inside `create_server()` — i.e. inside our own `mcp_adapter_init`
  callback. That is the first Abilities API call of the request. Both
  `WP_Abilities_Registry::get_instance()` and
  `WP_Ability_Categories_Registry::get_instance()` use a lazy singleton that
  sets its instance *before* firing its own init action, so our
  `Registrar::register_category()` / `register()` callbacks fire reentrantly,
  nested inside that same `wp_get_ability()` call, and the ability exists by
  the time `wp_get_ability()` returns. No infinite loop (the instance is set
  before the action fires), no missing abilities. Confirmed by reading both
  registry classes directly. Looks fragile; is WordPress's documented
  lazy-init contract for these two hooks.
- **`Registrar::wrap_execute()` returning a handler's `WP_Error` raw, not
  through `Envelope::error()`.** This looked like it broke D1 §4.6's
  "uniform response shape" requirement. Checked the adapter's
  `ToolsHandler` directly: it explicitly detects a `WP_Error` returned from
  the execute callback and converts it to the MCP protocol's own
  `isError: true` response. Wrapping it in `Envelope::error()` first would
  instead hand the adapter a *successful* result shaped like
  `{"error": {...}}` — worse, since a client checking the protocol-level
  `isError` flag would never see it. Left as-is; comment added at the call
  site recording why.

Two real fixes came out of this pass:

- **`AdminApi`'s settings route read `$request->get_json_params()` instead of
  `$request->get_param()`,** bypassing the route's own declared `args`
  schema entirely — no type coercion, and `null` (silent no-op, HTTP 200) for
  a form-encoded POST. Switched to `get_param()` for both `enabled` and
  `access_level`.
- **`Settings::ensure_row_exists()` was dead code** — defined, never called
  anywhere. `Settings::update()` already creates the row with `autoload`
  off on its first write, so nothing needed it. Deleted.
