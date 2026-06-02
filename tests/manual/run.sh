#!/usr/bin/env bash
#
# Woo4Etch runtime proof against a real WooCommerce install via wp-env.
# Usage (from repo root):  bash tests/manual/run.sh
#
set -uo pipefail

WP() { npx --yes @wordpress/env run cli wp "$@"; }
line() { printf '\n========== %s ==========\n' "$1"; }

line "1. Ensure wp-env is up"
npx --yes @wordpress/env start >/dev/null 2>&1 || true

line "2. Activate WooCommerce + Woo4Etch"
WP plugin activate woocommerce woo4etch
WP plugin list --status=active --field=name

line "3. Install WooCommerce pages (cart/checkout/my-account/shop)"
WP wc --user=1 tool run install_pages >/dev/null 2>&1 || true
WP option update woocommerce_calc_taxes no >/dev/null 2>&1 || true

line "4. PROVE the theme-support fix (Carlo's question)"
echo -n "With Woo4Etch active, current_theme_supports('woocommerce') = "
WP eval 'echo current_theme_supports("woocommerce") ? "TRUE" : "FALSE";'
echo
echo -n "With Woo4Etch deactivated (default Etch-style theme)         = "
WP eval 'echo current_theme_supports("woocommerce") ? "TRUE" : "FALSE";' --skip-plugins=woo4etch
echo

line "5. Shortcode harness (every [woo_*] against real data)"
WP eval-file wp-content/woo4etch-tests/manual/shortcode-harness.php

line "6. HTTP render checks (request-context shortcodes)"
BASE="http://localhost:9100"
check() { # url, marker, label
  local body; body="$(curl -s "$1")"
  if echo "$body" | grep -q "$2"; then
    echo "PASS  $3  ($1 contains '$2')"
  else
    echo "FAIL  $3  ($1 missing '$2')"
  fi
}
# Shop archive renders with our theme support (no unsupported-theme shim).
check "$BASE/?post_type=product" "product" "shop archive renders products"
# Single product page renders Woo markup.
PURL="$(WP eval 'echo get_permalink((int) wc_get_products(["limit"=>1,"sku"=>"WOO4ETCH-TEST"])[0]->get_id());' 2>/dev/null | tr -d '\r')"
[ -n "$PURL" ] && check "$PURL" "WOO4ETCH-TEST" "single product page renders SKU"

echo
echo "Done. Open the site:    npx wp-env run cli wp option get siteurl"
echo "Admin shortcode page:   $BASE/wp-admin/ (admin / password)  →  WooCommerce → Woo4Etch"
