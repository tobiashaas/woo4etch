<?php
/**
 * Layer 4 — the {options.*} bridge payloads with WooCommerce active
 * (issue #12). Read-only: asserts the documented keys exist and are shaped as
 * the ready-made layouts rely on.
 *
 * These three builders are what every cart, account and archive layout loops
 * over, so their shape is a contract: a renamed or reshaped key empties a
 * customer's cart page with no error to notice it by. The fast checks cannot
 * see any of it — they run without WordPress, so there is no cart, no session
 * and no order.
 *
 * (This file previously asserted the same builders through the experimental
 * {woo.*} root, which only re-mapped them. The root is gone; the builders it
 * wrapped are the thing worth testing, so the assertions address them directly.)
 *
 * @package Woo4Etch\Tests\Integration
 */

require __DIR__ . '/_lib.php';

echo "02 options bridge shape\n";

w4e_it(class_exists('WooCommerce'), 'WooCommerce is active');
w4e_it(class_exists('Woo4Etch'), 'Woo4Etch class loaded');
if (!class_exists('WooCommerce') || !class_exists('Woo4Etch')) {
    w4e_it_done();
}

// The cart builders need a session/cart, which a CLI request has no reason to
// have booted. Frontend requests always do.
if (function_exists('wc_load_cart') && (!WC()->cart || !WC()->session)) {
    wc_load_cart();
}

$cart    = Woo4Etch::expose_cart_data([]);
$account = Woo4Etch::expose_account_order_data([]);
$shop    = Woo4Etch::expose_shop_data([]);

foreach ([['cart', $cart], ['account/order', $account], ['shop', $shop]] as $pair) {
    w4e_it(is_array($pair[1]), "{$pair[0]} builder returns an array");
}

/* ---- {options.cart_*} ---- */

w4e_it(isset($cart['cart_items']) && is_array($cart['cart_items']), 'cart_items is an array');
w4e_it(isset($cart['cart_count']) && is_numeric($cart['cart_count']), 'cart_count is numeric');
w4e_it(array_key_exists('cart_is_empty', $cart) && is_bool($cart['cart_is_empty']), 'cart_is_empty is a bool');
w4e_it(isset($cart['cart_url']) && is_string($cart['cart_url']) && $cart['cart_url'] !== '', 'cart_url is a non-empty string');
w4e_it(isset($cart['checkout_url']) && is_string($cart['checkout_url']) && $cart['checkout_url'] !== '', 'checkout_url is a non-empty string');
w4e_it(isset($cart['shop_url']) && is_string($cart['shop_url']) && $cart['shop_url'] !== '', 'shop_url is a non-empty string');
w4e_it(isset($cart['cart_nonce']) && is_string($cart['cart_nonce']), 'cart_nonce is a string');
w4e_it(isset($cart['cross_sells']) && is_array($cart['cross_sells']), 'cross_sells is an array');
w4e_it_equals((int) $cart['cart_count'] === 0, (bool) $cart['cart_is_empty'], 'cart_is_empty agrees with cart_count');

// The shipping keys the summary renders (see 09 for the gating rules).
foreach (['cart_needs_shipping', 'cart_show_shipping', 'cart_shipping_total', 'cart_shipping_notice'] as $k) {
    w4e_it(array_key_exists($k, $cart), "{$k} exists");
}

/* ---- {options.account_*} / {options.order} ---- */

w4e_it(isset($account['account_menu']) && is_array($account['account_menu']), 'account_menu is an array');
w4e_it(array_key_exists('account_endpoint', $account) && is_string($account['account_endpoint']), 'account_endpoint is a string');
w4e_it(isset($account['account_orders']) && is_array($account['account_orders']), 'account_orders is an array');
w4e_it(array_key_exists('order', $account), 'order key exists (empty outside thank-you/view-order)');

// Each account-menu entry must carry label + url — the account layout loops these.
$menu_ok = true;
foreach ((array) $account['account_menu'] as $entry) {
    if (!is_array($entry) || !isset($entry['label'], $entry['url'])) {
        $menu_ok = false;
        break;
    }
}
w4e_it($menu_ok, 'every account_menu entry has label + url');

/* ---- {options.shop_*} ---- */

w4e_it(isset($shop['shop_categories']) && is_array($shop['shop_categories']), 'shop_categories is an array');
foreach (['shop_max_price', 'filter_min_price', 'filter_max_price'] as $k) {
    w4e_it(array_key_exists($k, $shop), "{$k} exists");
}

/* ---- cart row shape ---- */

$item_ok = true;
foreach ((array) $cart['cart_items'] as $item) {
    foreach (['key', 'name', 'quantity'] as $k) {
        if (!is_array($item) || !array_key_exists($k, $item)) {
            $item_ok = false;
            break 2;
        }
    }
}
count($cart['cart_items']) > 0
    ? w4e_it($item_ok, 'every cart_items entry has key/name/quantity')
    : w4e_it_skip('cart_items entry shape (cart is empty in CLI context)');

w4e_it_done();
