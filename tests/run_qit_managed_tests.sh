#!/usr/bin/env bash
#
# Managed QIT tests for the FREE plugin zip.
#
# The Pro repo has had this for a while (notifybaypro/tests/run_qit_managed_tests.sh)
# but it only ever packages and submits notifybaypro.zip. Nothing ran the managed
# suites against this plugin -- the one that actually goes to wordpress.org, and
# the one whose review overlaps most with what run:plugin-check and run:security
# report.
#
# Usage:
#   bash tests/run_qit_managed_tests.sh              # every suite below
#   bash tests/run_qit_managed_tests.sh plugin-check security
#
set -euo pipefail

export PATH="$PATH:$HOME/.config/composer/vendor/bin"

cd "$(dirname "$0")/.."

SLUG="notifybay-waitlist-and-stock-alert-woo"

echo "Packaging ${SLUG}..."
bash package.sh > /dev/null

# package.sh writes both a wordpress/ and a woocommerce/ layout; the WooCommerce
# one is what QIT expects.
ZIP_PATH="./dist/woocommerce/${SLUG}.zip"
if [ ! -f "$ZIP_PATH" ]; then
    ZIP_PATH="./dist/${SLUG}.zip"
fi
if [ ! -f "$ZIP_PATH" ]; then
    echo "Error: no zip produced. Looked in ./dist/woocommerce/ and ./dist/." >&2
    exit 1
fi
echo "Using $ZIP_PATH"

# Same set the Pro runner uses, minus woo-e2e: this plugin's e2e suite lives in
# the Pro repo (see qit.json) and runs from there.
ALL_TESTS=(
    activation
    api
    malware
    performance
    phpcompatibility
    phpstan
    plugin-check
    security
    validation
    woo-api
)

if [ "$#" -gt 0 ]; then
    TESTS=("$@")
else
    TESTS=("${ALL_TESTS[@]}")
fi

FAILED=()
for t in "${TESTS[@]}"; do
    echo
    echo "======================================"
    echo "Running run:${t}"
    echo "======================================"
    if ! qit "run:${t}" "$SLUG" --zip="$ZIP_PATH"; then
        # Keep going: these are independent reports, and stopping at the first
        # one hides the rest of the picture.
        FAILED+=("$t")
    fi
done

echo
if [ "${#FAILED[@]}" -gt 0 ]; then
    echo "Suites that did not pass: ${FAILED[*]}"
    exit 1
fi
echo "All requested managed suites passed or were enqueued."
