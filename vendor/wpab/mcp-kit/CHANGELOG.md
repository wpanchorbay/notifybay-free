# Changelog

All notable changes to `wpab/mcp-kit`.

The version numbering is pre-1.0 on purpose: the kit has not yet been
integrated into a shipping product, and the manifest contract may still move in
response to the first real integration. Treat minor bumps as potentially
breaking until 1.0.

## 0.3.2

Closes the two packaging gaps that would have bitten the first plugin to adopt
the kit. Neither was a code defect; both were things a library needs and this
one did not have.

### Added

- **The kit's own strings are actually translatable.** Eight strings reach a
  human or a model — the missing-dependency notice, the two settings labels,
  the permission refusals, the tombstone — and they carried `__()` and the
  `wpab-mcp-kit` domain from the start. Nothing ever loaded that domain and no
  `.pot` shipped, so they could only ever render in English. Marked-but-
  unloadable is the quiet kind of broken: every linter is satisfied and no
  translator can act.

  `Bootstrap` now loads a `.mo` addressed relative to the kit's own directory,
  which is what survives PHP-Scoper relocating everything around it —
  `load_plugin_textdomain()` cannot work for a library living in somebody
  else's `vendor/`. Deferred to `init`, because loading a translation earlier
  trips WP 6.7+'s just-in-time check and prints a notice into every response on
  the site, exactly as the default-server check did in 0.2.2.

  A `.pot` ships, and `tests/cases/05-i18n.sh` proves a compiled catalogue is
  found and applied through the *scoped* build — the first version of that test
  caught the `languages/` directory failing to ship at all.

- **`.gitattributes`.** Without it, `composer require` handed every consumer the
  whole repository: 156K of test harness against 96K of library, including
  three fixture *plugins* with real `Plugin Name:` headers sitting inside
  another plugin's `vendor/`. The distribution is 250 KB → 120 KB and contains
  only `src/`, `examples/`, `languages/`, `scoper.inc.php` and the docs.

## 0.3.1

### Fixed

- **The missing-dependency notice was never visible to anyone.** The
  availability check sat below Bootstrap's REST/CLI gate, but `admin_notices`
  only fires on an admin page render — the one request that gate returns from.
  The callback was registered only on REST and WP-CLI requests, where the
  action never fires, so a site with no adapter told its owner nothing. D1
  §4.1's "an admin notice, never a fatal" was half met: never a fatal, and
  never a notice either. Only the notice registration moved above the gate; the
  early return stays below step 3, so a site without the adapter still serves
  the settings routes that report the problem.

  This survived three passes of criterion 9 because the test asserted it with
  `do_action('admin_notices')` under WP-CLI, where `WP_CLI` being defined opens
  the gate. It is now asserted through a real cookie-authenticated wp-admin
  page.

### Changed

- `tests/run.sh` verifies the active-plugin list is unchanged at the end of the
  whole run, rather than trusting a per-case cleanup check that cannot see
  effects landing after it.

## 0.3.0

The release that made the evidence repeatable.

### Added

- **A regression suite.** `composer test` runs 109 assertions over all 17 of
  D1 §9's acceptance criteria, all 10 known defects, and `Guard`'s branches,
  against real HTTP and packaged zips, driven at whatever WordPress `WP_PATH`
  and `WP_URL` point to.
- **`Guard`'s `any_of` branch is covered.** It had never executed — every
  fixture declared `all_of`, so one side of the authorization evaluator shipped
  untested, and a mistake there widens access rather than breaking a tool.
  Both directions, the both-present AND semantics, and five malformed shapes
  failing closed.
- **`phpcs.xml.dist`** (D1 §8.4, previously unmet), modelled on the ruleset the
  consuming WPAB plugins use. Clean.
- **PHPCompatibility** at 7.4–8.3, and a `composer lint:74` that parses every
  source on a real PHP 7.4 in Docker. Both clean.
- **CI**: PHPCS and compatibility on every push, plus the suite across a
  PHP 7.4/8.2/8.3 and WP stable/RC matrix.
- **`@param`/`@return` documentation** across the public surface.
- The test fixtures and harness now live in the repository under `tests/`.
  They previously sat in an untracked directory and a temp folder, which meant
  the only way to reproduce the acceptance evidence could have been lost.

### Changed

- **Packaged zips use a Composer-generated scoped autoloader**
  (`composer dump-autoload --classmap-authoritative` over the scoped tree)
  instead of a hand-written `spl_autoload_register`. The old one worked, but it
  left the build path D1 §8.5 documents unproven. The build now fails if the
  generated classmap does not contain the scoped entry point.

### Fixed

- `Bootstrap` reads `$_SERVER['REQUEST_URI']` through `wp_unslash()` and
  `sanitize_text_field()`.

## 0.2.2

- **Deferred the default-server decision into the filter callback.** Reading
  WooCommerce's `mcp_integration` flag on `plugins_loaded` forced its
  `FeaturesController` to load the `woocommerce` textdomain before `init`,
  tripping WP 6.7+'s just-in-time translation check. With `display_errors` on,
  the resulting notice was printed into the body of **every REST response on
  the site** — core's `/wp-json/` and WooCommerce's own routes included — so
  every JSON response on the site was unparseable. It also made every request
  pay for WooCommerce's feature table, against D1 §8.2.

## 0.2.1

- **Split `ObservabilityBridge` out of `Observability`.** A class that
  `implements` an interface cannot be autoloaded when that interface is absent,
  so with no adapter installed *any* call to `Observability::log()` fataled —
  on the path that exists to report the adapter being missing. `AdminApi`
  registers its routes above the dependency check, so an administrator opening
  MCP settings got a white screen instead of an admin notice.
- **`Manifest::validate_ability()` now mirrors core's own requirements.** Core
  reports every registration failure as `_doing_it_wrong()` plus a `null`
  return — silent in production — so a missing `description`, a non-string
  `label` or a non-array `input_schema` produced a product serving fewer tools
  than its manifest declared, with nothing logged. `description` is rejected
  rather than defaulted to the label: it is the prompt text the model selects
  on.
- **`Registrar::register()` checks what `wp_register_ability()` returns**, as a
  backstop for the failures that validation does not anticipate.
- `Envelope::paginate()` computed `has_more` from the raw page while reporting
  the clamped one.

## 0.2.0 / 0.1.3

- **Fully qualified every existence-check string literal.** PHP-Scoper rewrites
  bare strings inside `function_exists()` and `class_exists()`, so
  `'wp_register_ability'` became `'<Prefix>\wp_register_ability'` — permanently
  false. A scoped build concluded the Abilities API was missing and served no
  MCP route, while its settings routes kept answering 200 and made the plugin
  look half-alive rather than broken.
- **A crashing tool reported `isError: false`.** A handler that threw produced a
  *successful* result whose body merely contained an error, so a client checking
  the protocol-level flag treated the crash as a working call. Same defect in
  the tombstone path.
- **Tombstones** name their replacement in wire form (`product-verb-noun`), not
  as the slashed ability key, which is not callable.
- `properties => new stdClass()` fataled on any non-empty input, via core's
  `rest_validate_value_from_schema()`.
- Multibyte-safe truncation in `Envelope` and `Observability`.

## 0.1.0

Initial implementation of every component in D1 §4.

Historical note: tags `v0.1.0`–`v0.1.3` and `v0.2.0`–`v0.2.2` exist partly to
exercise D1 §9's criterion 6, which requires two independently scoped plugins
pinned to *different* kit versions serving side by side. The test suite still
builds one fixture from `v0.1.3`, so those tags must not be deleted.
