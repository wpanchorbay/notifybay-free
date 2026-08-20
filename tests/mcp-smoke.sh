#!/usr/bin/env bash
# The first real-product integration of wpab/mcp-kit: NotifyBay.
#
# Everything before this ran against the three throwaway fixtures. This drives
# NotifyBay's own endpoint, over real HTTP, with Application Passwords, using
# the kit's own proven handshake helpers rather than a second implementation of
# them.
#
# Leaves the site as it found it: the endpoint is switched back off, the seeded
# lead is deleted, the throwaway account is removed, and the application
# password it issued to the borrowed administrator is revoked by name. That last
# one matters -- without it, every run would leave a live credential holding
# manage_options on a real account.
#
#   KIT=/path/to/mcp-kit WP_PATH=/var/www/html WP_URL=http://localhost \
#     bash tests/mcp-smoke.sh
#
# Needs a WordPress + WooCommerce install with this plugin active, `wp` on PATH,
# and a checkout of the wpab/mcp-kit repository for its tests/lib helpers. The
# Composer package ships src/ only, so KIT must point at the git repo, not at
# vendor/wpab/mcp-kit. Not part of the release zip: package.sh copies an
# explicit allow-list and tests/ is not on it.
#
# It creates and deletes a lead and a throwaway user of its own, and toggles
# this product's MCP setting. It touches no existing lead and no existing
# account -- but point it at a development store regardless.
set -uo pipefail

# No default: the kit repo lives outside this plugin and its path is
# machine-specific. Failing loudly beats sourcing something unexpected.
KIT="${KIT:?set KIT to a checkout of the wpab/mcp-kit repository}"
WP_PATH="${WP_PATH:-/var/www/html}"
export MCP_BASE="${WP_URL:-http://localhost}"
export MCP_WORK; MCP_WORK="$( mktemp -d )"

# The one existing account this script borrows. It needs manage_options (for the
# settings route) and manage_notifybay (for delete-leads), which an
# administrator has. Override if your admin is not called "admin".
#
# Borrowing rather than provisioning is a compromise: the kit's own harness
# creates a dedicated wpab_probe_admin instead, precisely so it never touches a
# real account. That is the better pattern. This script issues a *named*
# application password and revokes it in cleanup() rather than going that far --
# it never changes the account's password, role or capabilities.
ADMIN_LOGIN="${ADMIN_LOGIN:-admin}"
APP_PASS_NAME='notifybay-mcp-pilot'

# shellcheck source=/dev/null
. "$KIT/tests/lib/assert.sh"
# shellcheck source=/dev/null
. "$KIT/tests/lib/mcp.sh"

wp() { command wp --path="$WP_PATH" --skip-themes "$@" 2>/dev/null; }
wp_php() { local r="$MCP_WORK/$( basename "$1" )"; cp "$1" "/tmp/pilot-$( basename "$1" )"; wp eval-file "/tmp/pilot-$( basename "$1" )"; }

echo "→ target: $MCP_BASE  (NotifyBay pilot)"

# ---------------------------------------------------------------------------
# Accounts and a lead to operate on.
#
# nb_ai holds wpab_mcp_access but deliberately NOT manage_notifybay: that is
# the whole point of the capability-narrowing assertion further down.
# ---------------------------------------------------------------------------
cat > "$MCP_WORK/setup.php" <<PHP
<?php
\$admin_login    = '$ADMIN_LOGIN';
\$app_pass_name  = '$APP_PASS_NAME';
PHP
cat >> "$MCP_WORK/setup.php" <<'PHP'

// A null caps entry means "borrow this account, do not create or modify it".
foreach ( [ $admin_login => null, 'nb_ai' => [ 'wpab_mcp_access' ] ] as $login => $caps ) {
	$user = get_user_by( 'login', $login );

	if ( ! $user && null !== $caps ) {
		$id   = wp_insert_user( [
			'user_login' => $login,
			'user_pass'  => wp_generate_password( 24 ),
			'user_email' => $login . '@example.test',
			'role'       => 'subscriber',
		] );
		$user = get_user_by( 'id', $id );
	}

	if ( ! $user ) { printf( "%s FAILED\n", $login ); continue; }

	if ( null !== $caps ) {
		foreach ( $caps as $c ) { $user->add_cap( $c ); }
		// Explicit: a subscriber must not inherit the plugin's own capability.
		$user->remove_cap( 'manage_notifybay' );
	}

	$created = WP_Application_Passwords::create_new_application_password(
		$user->ID, [ 'name' => $app_pass_name ]
	);

	// Spaces stripped: WordPress hands the secret back chunked and strips them
	// again when authenticating, but a space-separated secret is silently
	// truncated by shell word splitting -- which once made a 401 for bad
	// credentials look like proof of a capability boundary.
	printf( "%s %s\n", $login, is_wp_error( $created ) ? 'FAILED' : str_replace( ' ', '', $created[0] ) );
}

// A lead to read, update and delete. Its own product so nothing real is touched.
global $wpdb;
$now = current_time( 'mysql' );
$wpdb->insert( $wpdb->prefix . 'notifybay_leads', [
	'user_email'            => 'pilot-probe@example.test',
	'product_id'            => 999999,
	'variation_id'          => 0,
	'type'                  => 'waitlist',
	'status'                => 'active',
	'product_name_snapshot' => 'Pilot Probe Product',
	'created_at'            => $now,
	'updated_at'            => $now,
] );
printf( "LEAD_ID %d\n", $wpdb->insert_id );
PHP

setup="$( wp_php "$MCP_WORK/setup.php" )"
_cred() { printf '%s:%s' "$1" "$( printf '%s' "$setup" | awk -v u="$1" '$1==u{print $2}' )"; }
MCP_ADMIN="$( _cred "$ADMIN_LOGIN" )"
MCP_AI="$( _cred nb_ai )"
LEAD_ID="$( printf '%s' "$setup" | awk '$1=="LEAD_ID"{print $2}' )"

for v in MCP_ADMIN MCP_AI; do
	case "${!v}" in *:|*:FAILED) echo "no application password for $v" >&2; echo "$setup" >&2; exit 1 ;; esac
done
[ -n "$LEAD_ID" ] && [ "$LEAD_ID" != 0 ] || { echo "could not seed a lead" >&2; exit 1; }
echo "→ probe lead id $LEAD_ID"

# set_level <read|read+modify|full>
set_level() {
	http "$MCP_ADMIN" POST "/wp-json/wpab/v1/notifybay/settings" "{\"enabled\":true,\"access_level\":\"$1\"}"
}

cleanup() {
	http "$MCP_ADMIN" POST "/wp-json/wpab/v1/notifybay/settings" '{"enabled":false,"access_level":"read"}' >/dev/null 2>&1
	cat > "$MCP_WORK/teardown.php" <<PHP
<?php
global \$wpdb;
\$wpdb->delete( \$wpdb->prefix . 'notifybay_leads', [ 'id' => $LEAD_ID ] );

// nb_ai is this script's own account, so deleting it takes its application
// password with it. The administrator is NOT ours -- it existed before the run
// and survives it -- so its password has to be revoked by name, or every run
// leaves a live credential on a real admin account. The whole point of the
// access ladder is that a leaked credential is bounded; leaking one that holds
// manage_options is not bounded at all.
\$u = get_user_by( 'login', 'nb_ai' );
if ( \$u ) { require_once ABSPATH . 'wp-admin/includes/user.php'; wp_delete_user( \$u->ID ); }

\$admin = get_user_by( 'login', '$ADMIN_LOGIN' );
\$revoked = 0;
if ( \$admin ) {
	foreach ( WP_Application_Passwords::get_user_application_passwords( \$admin->ID ) as \$pw ) {
		if ( '$APP_PASS_NAME' === \$pw['name'] ) {
			WP_Application_Passwords::delete_application_password( \$admin->ID, \$pw['uuid'] );
			\$revoked++;
		}
	}
}
printf( "revoked=%d\n", \$revoked );
PHP
	local out; out="$( wp_php "$MCP_WORK/teardown.php" 2>/dev/null )"
	case "$out" in
		*revoked=0*|"") printf '  !! could not revoke the %s application password on "%s" -- revoke it by hand\n' \
			"$APP_PASS_NAME" "$ADMIN_LOGIN" >&2 ;;
	esac
	rm -rf "$MCP_WORK"
}
trap cleanup EXIT

# ---------------------------------------------------------------------------
case_start "Enabling the endpoint through the kit's own settings route"

set_level read
assert_eq 200 "$MCP_STATUS" "the settings route accepts enabled=true for a real product"

http "$MCP_ADMIN" GET "/wp-json/wpab/v1/notifybay/status" ""
assert_eq 200 "$MCP_STATUS" "the status route answers"
assert_json           "...with JSON, not a fatal or a notice-corrupted body"
assert_eq ok    "$( json "d['status']" )"                 "the toggle persisted"
assert_eq true  "$( json "d['settings'][0]['value']" )"   "...and reads back as enabled in the settings payload"
assert_eq 4     "$( json "d['tool_count']" )"             "all 4 manifest abilities validated -- none silently rejected"

# ---------------------------------------------------------------------------
case_start "Registration -- the count, not just the absence of an error"

cat > "$MCP_WORK/count.php" <<'PHP'
<?php
$keys = array_values( array_filter( array_keys( wp_get_abilities() ),
	function ( $n ) { return 0 === strpos( $n, 'notifybay' ); } ) );
sort( $keys );
printf( "count=%d\nkeys=%s\n", count( $keys ), implode( ',', $keys ) );
PHP
reg="$( wp_php "$MCP_WORK/count.php" )"

# Core reports every registration failure as _doing_it_wrong() and a null
# return, so a dropped ability is invisible unless it is counted.
assert_contains "count=4" "$reg" "exactly 4 notifybay abilities register with core"
assert_contains "notifybay/delete-leads,notifybay/list-leads,notifybay/system-status,notifybay/update-lead" \
	"$reg" "...and they are the four the manifest declares"

# ---------------------------------------------------------------------------
case_start "The MCP handshake against a real product's endpoint"

mcp_session "$MCP_ADMIN" notifybay
assert_ne "" "$MCP_SID" "initialize returns an Mcp-Session-Id"

mcp_call "$MCP_ADMIN" notifybay tools/list '{}'
assert_eq 200 "$MCP_STATUS" "tools/list answers over the negotiated session"
names="$( tool_names )"
assert_contains "notifybay-list-leads" "$names" "the hyphenated wire name is what is advertised"
assert_not_contains "notifybay/list-leads" "$names" "...not the slashed ability key"

# ---------------------------------------------------------------------------
case_start "A tool round-trip, with isError checked explicitly"

mcp_tool "$MCP_ADMIN" notifybay notifybay-list-leads '{"per_page":5}'
assert_eq 200   "$MCP_STATUS"      "list-leads answers"
assert_eq false "$( is_error )"    "...and is a success, not a failure reported as one"
assert_contains "pilot-probe@example.test" "$( tool_text )" "the seeded lead comes back through the envelope"
assert_contains '"has_more"' "$( tool_text )" "...in Envelope::paginate's shape"

mcp_tool "$MCP_ADMIN" notifybay notifybay-system-status '{}'
assert_eq false "$( is_error )" "the zero-argument tool works with an empty properties schema"

# A token that is a credential must never appear in a tool response.
assert_not_contains "guest_token"        "$( tool_text )" "system-status leaks no token field"
mcp_tool "$MCP_ADMIN" notifybay notifybay-list-leads '{"per_page":5}'
assert_not_contains "verification_token" "$( tool_text )" "list-leads allow-lists fields -- no verification_token"
assert_not_contains "guest_token"        "$( tool_text )" "...and no guest_token"

# ---------------------------------------------------------------------------
case_start "The access ladder -- hidden AND refused, asserted separately"

# read
set_level read >/dev/null
mcp_call "$MCP_ADMIN" notifybay tools/list '{}'
names="$( tool_names )"
assert_contains     "notifybay-list-leads"    "$names" "read: readonly list-leads is advertised"
assert_contains     "notifybay-system-status" "$names" "read: readonly system-status is advertised"
assert_not_contains "notifybay-update-lead"   "$names" "read: idempotent update-lead is hidden"
assert_not_contains "notifybay-delete-leads"  "$names" "read: destructive delete-leads is hidden"

# Not advertised is not enough. It must also be refused when called directly.
mcp_tool "$MCP_ADMIN" notifybay notifybay-delete-leads "{\"ids\":[$LEAD_ID]}"
assert_eq true "$( refused )" "read: delete-leads called directly is refused, not merely unlisted"
mcp_tool "$MCP_ADMIN" notifybay notifybay-update-lead "{\"id\":$LEAD_ID,\"status\":\"expired\"}"
assert_eq true "$( refused )" "read: update-lead called directly is refused"

# read+modify
set_level read+modify >/dev/null
mcp_call "$MCP_ADMIN" notifybay tools/list '{}'
names="$( tool_names )"
assert_contains     "notifybay-update-lead"  "$names" "read+modify: idempotent update-lead appears"
assert_not_contains "notifybay-delete-leads" "$names" "read+modify: destructive is still hidden"
mcp_tool "$MCP_ADMIN" notifybay notifybay-delete-leads "{\"ids\":[$LEAD_ID]}"
assert_eq true "$( refused )" "read+modify: delete-leads is still refused directly"

mcp_tool "$MCP_ADMIN" notifybay notifybay-update-lead "{\"id\":$LEAD_ID,\"status\":\"unsubscribed\"}"
assert_eq false "$( is_error )" "read+modify: update-lead now succeeds"
assert_contains "unsubscribed" "$( tool_text )" "...and the change is reflected back"

cat > "$MCP_WORK/verify.php" <<PHP
<?php
global \$wpdb;
printf( "status=%s\n", \$wpdb->get_var( \$wpdb->prepare(
	"SELECT status FROM {\$wpdb->prefix}notifybay_leads WHERE id = %d", $LEAD_ID ) ) );
PHP
assert_contains "status=unsubscribed" "$( wp_php "$MCP_WORK/verify.php" )" \
	"...and it actually reached the database, not just the response"

# full
set_level full >/dev/null
mcp_call "$MCP_ADMIN" notifybay tools/list '{}'
assert_contains "notifybay-delete-leads" "$( tool_names )" "full: destructive delete-leads appears"

# ---------------------------------------------------------------------------
case_start "Capability narrowing -- manage_notifybay, above wpab_mcp_access"

# nb_ai holds wpab_mcp_access, so it reaches the endpoint and every readonly
# tool, but the manifest narrows delete-leads to manage_notifybay.
mcp_tool "$MCP_AI" notifybay notifybay-list-leads '{"per_page":1}'
assert_eq false "$( is_error )" "an account with only wpab_mcp_access can read"

mcp_tool "$MCP_AI" notifybay notifybay-delete-leads "{\"ids\":[$LEAD_ID]}"
assert_eq true "$( refused )" "...but is refused delete-leads at access_level=full"

cat > "$MCP_WORK/stillthere.php" <<PHP
<?php
global \$wpdb;
printf( "rows=%d\n", (int) \$wpdb->get_var( \$wpdb->prepare(
	"SELECT COUNT(*) FROM {\$wpdb->prefix}notifybay_leads WHERE id = %d", $LEAD_ID ) ) );
PHP
assert_contains "rows=1" "$( wp_php "$MCP_WORK/stillthere.php" )" \
	"the refusal was fail-closed -- the row is still there"

# ---------------------------------------------------------------------------
case_start "The AI account cannot raise its own ceiling"

# The whole access ladder rests on this. nb_ai reaches the endpoint, so if it
# could also reach the settings route it could simply set itself to full and
# the ladder would be decorative. The kit gates that route on manage_options,
# which nb_ai does not hold.
http "$MCP_AI" POST "/wp-json/wpab/v1/notifybay/settings" '{"access_level":"full"}'
assert_eq 403 "$MCP_STATUS" "the AI account is refused the settings route"

# ...and the admin tab that drives that route is hidden from it too. Asserted
# here rather than left to a one-off manual check, because a tab rendered for
# an account whose every request 403s is exactly the kind of regression a
# later refactor introduces without noticing.
cat > "$MCP_WORK/uigate.php" <<'PHP'
<?php
$rc = new ReflectionClass( 'NotifyBay\Admin\Admin' );
$m  = $rc->getMethod( 'get_mcp_localize' );
$m->setAccessible( true );
$obj = $rc->newInstanceWithoutConstructor();

foreach ( [ 'administrator', 'nb_ai' ] as $who ) {
	$user = 'nb_ai' === $who
		? get_user_by( 'login', 'nb_ai' )
		: get_users( [ 'role' => 'administrator', 'number' => 1 ] )[0];

	wp_set_current_user( $user->ID );
	printf( "%s=%s\n", $who, null === $m->invoke( $obj ) ? 'hidden' : 'shown' );
}
PHP
uigate="$( wp_php "$MCP_WORK/uigate.php" )"
assert_contains "administrator=shown" "$uigate" "the MCP tab renders for an administrator"
assert_contains "nb_ai=hidden"        "$uigate" "...and is hidden from the AI account"

# ---------------------------------------------------------------------------
case_start "Destructive tool, and the audit trail"

mcp_tool "$MCP_ADMIN" notifybay notifybay-delete-leads "{\"ids\":[$LEAD_ID]}"
assert_eq false "$( is_error )" "an account holding manage_notifybay may delete"
assert_contains "$LEAD_ID" "$( tool_text )" "...and the response names the id it deleted"
assert_contains "rows=0" "$( wp_php "$MCP_WORK/stillthere.php" )" "the row is gone"

mcp_tool "$MCP_ADMIN" notifybay notifybay-delete-leads "{\"ids\":[$LEAD_ID]}"
assert_eq false "$( is_error )" "deleting an already-deleted id is not an error"
assert_contains "not_found" "$( tool_text )" "...it is reported as not_found, per-id"

# ---------------------------------------------------------------------------
case_start "No collateral damage (defect 9) -- a real plugin's boot order"

http "$MCP_ADMIN" GET "/wp-json/" ""
assert_eq 200 "$MCP_STATUS" "core's REST index still answers"
assert_json                 "...with parseable JSON and no leading output"

http "$MCP_ADMIN" GET "/wp-json/wc/v3/system_status" ""
assert_eq 200 "$MCP_STATUS" "WooCommerce's own route still answers"
assert_json                 "...with parseable JSON"

http "" GET "/" ""
assert_eq 200 "$MCP_STATUS" "the storefront still renders"
assert_not_contains "Fatal error" "$MCP_BODY" "...with no fatal"

# notifybay/v1 from ApiController::$namespace (NOTIFYBAY_TEXT_DOMAIN) + $version,
# and AdminController registers under /admin.
#
# 403 is the CORRECT answer here, not a regression: NotifyBay's own
# get_item_permissions_check() requires an X-WP-Nonce on top of
# manage_notifybay, and an Application Password request carries no cookie
# session to mint one from. What matters is that the route still exists (not
# 404) and that NotifyBay's own controller is the thing answering (not a fatal,
# and not the kit) -- which is exactly what rest_nonce_invalid proves.
http "$MCP_ADMIN" GET "/wp-json/notifybay/v1/admin/leads?per_page=1" ""
assert_eq 403 "$MCP_STATUS" "NotifyBay's own admin REST route still registers and answers"
assert_json                 "...with JSON, not a fatal"
assert_contains "rest_nonce_invalid" "$MCP_BODY" "...and it is NotifyBay's own permission check that answered"

# ---------------------------------------------------------------------------
case_start "Three kit copies on one site, one of them unscoped"

cat > "$MCP_WORK/products.php" <<'PHP'
<?php
$p = apply_filters( 'wpab_mcp_products', [] );
printf( "count=%d\n", count( $p ) );
foreach ( $p as $row ) {
	printf( "%s|%s|%s\n", $row['product_key'], $row['kit_version'], $row['status'] );
}
PHP
prod="$( wp_php "$MCP_WORK/products.php" )"

# NotifyBay carries an UNSCOPED WPAB\Mcp\Kit; the two fixtures carry scoped
# copies under their own prefixes, one of them a different version. All three
# must report independently.
assert_contains "count=3"                 "$prod" "three products are discovered"
assert_contains "notifybay|0.3.2|ok"      "$prod" "NotifyBay's unscoped copy reports itself"
assert_contains "fixtureone|0.1.3-legacy" "$prod" "...alongside a scoped copy of a DIFFERENT version"
assert_contains "fixturetwo|0.3.2"        "$prod" "...and a second scoped copy"

# ---------------------------------------------------------------------------
case_start "The site is left as it was found"

http "$MCP_ADMIN" POST "/wp-json/wpab/v1/notifybay/settings" '{"enabled":false,"access_level":"read"}'
assert_eq 200 "$MCP_STATUS" "the endpoint switches back off"

http "$MCP_AI" POST "/wp-json/wpab/notifybay/mcp" '{"jsonrpc":"2.0","id":1,"method":"tools/list","params":{}}' 'Accept: application/json'
assert_ne 200 "$MCP_STATUS" "...and the disabled endpoint no longer serves"

summary
