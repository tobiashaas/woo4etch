<?php
/**
 * Layer 4 — Woo4Etch_Woo_Root::build_data() shape with WooCommerce active
 * (issue #12). Read-only: asserts the documented keys exist and are shaped as
 * the templates rely on ({woo.cart.items}, {woo.checkout.url}, …). The same
 * builders feed {options.*}, so this covers both spellings.
 *
 * @package Woo4Etch\Tests\Integration
 */

require __DIR__ . '/_lib.php';

echo "02 woo-root data shape\n";

w4e_it(class_exists('WooCommerce'), 'WooCommerce is active');
w4e_it(class_exists('Woo4Etch_Woo_Root'), 'Woo4Etch_Woo_Root class loaded');
if (!class_exists('WooCommerce') || !class_exists('Woo4Etch_Woo_Root')) {
    w4e_it_done();
}

// build_data() calls the cart builders — make sure the session/cart exist in
// CLI context like they would on a frontend request.
if (function_exists('wc_load_cart') && (!WC()->cart || !WC()->session)) {
    wc_load_cart();
}

$data = Woo4Etch_Woo_Root::build_data();

w4e_it(is_array($data), 'build_data() returns an array');

foreach (['cart', 'checkout', 'shop', 'account'] as $key) {
    w4e_it(isset($data[$key]) && is_array($data[$key]), "'{$key}' key exists and is an array");
}
w4e_it(array_key_exists('order', $data), "'order' key exists (null outside thank-you/view-order)");

$cart = isset($data['cart']) ? $data['cart'] : [];
w4e_it(isset($cart['items']) && is_array($cart['items']), 'cart.items is an array');
w4e_it(isset($cart['count']) && is_numeric($cart['count']), 'cart.count is numeric');
w4e_it(array_key_exists('is_empty', $cart) && is_bool($cart['is_empty']), 'cart.is_empty is a bool');
w4e_it(isset($cart['url']) && is_string($cart['url']) && $cart['url'] !== '', 'cart.url is a non-empty string');
w4e_it(isset($cart['nonce']) && is_string($cart['nonce']), 'cart.nonce is a string');
w4e_it(isset($cart['cross_sells']) && is_array($cart['cross_sells']), 'cart.cross_sells is an array');
w4e_it_equals((int) $cart['count'] === 0, (bool) $cart['is_empty'], 'cart.is_empty agrees with cart.count');

w4e_it(isset($data['checkout']['url']) && is_string($data['checkout']['url']) && $data['checkout']['url'] !== '', 'checkout.url is a non-empty string');
w4e_it(isset($data['shop']['url']) && is_string($data['shop']['url']) && $data['shop']['url'] !== '', 'shop.url is a non-empty string');

$account = isset($data['account']) ? $data['account'] : [];
w4e_it(isset($account['menu']) && is_array($account['menu']), 'account.menu is an array');
w4e_it(array_key_exists('endpoint', $account) && is_string($account['endpoint']), 'account.endpoint is a string');
w4e_it(isset($account['orders']) && is_array($account['orders']), 'account.orders is an array');

// Each account-menu entry must carry label + url (the account layout loops these).
$menu_ok = true;
foreach ((array) $account['menu'] as $entry) {
    if (!is_array($entry) || !isset($entry['label'], $entry['url'])) {
        $menu_ok = false;
        break;
    }
}
w4e_it($menu_ok, 'every account.menu entry has label + url');

// Each cart item must carry the keys the cart layout renders.
$item_ok = true;
foreach ((array) $cart['items'] as $item) {
    foreach (['key', 'name', 'quantity'] as $k) {
        if (!is_array($item) || !array_key_exists($k, $item)) {
            $item_ok = false;
            break 2;
        }
    }
}
count($cart['items']) > 0
    ? w4e_it($item_ok, 'every cart.items entry has key/name/quantity')
    : w4e_it_skip('cart.items entry shape (cart is empty in CLI context)');

w4e_it_done();
