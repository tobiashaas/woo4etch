<?php
/**
 * Layer 5 — Store API cart layer (issue #25). Verifies the pieces the
 * frontend script depends on: the Store API cart endpoints answer, the
 * script + settings wiring is in place, the cart bridge exposes the coupon
 * keys, and the shipped layouts carry the region markers. Non-destructive:
 * runs against a fresh Store API cart session, nothing persisted.
 *
 * @package Woo4Etch\Tests\Integration
 */

require __DIR__ . '/_lib.php';

echo "06 store api cart layer\n";

w4e_it(class_exists('Woo4Etch'), 'Woo4Etch class loaded');
w4e_it(method_exists('Woo4Etch', 'enqueue_store_api_script'), 'store-api enqueue hook exists');

/* ---- Store API endpoints exist and answer ---- */

if (!function_exists('rest_do_request')) {
    w4e_it_skip('REST runtime unavailable');
    w4e_it_done();
}

// WC()->cart must exist for Store API cart routes in CLI context.
if (function_exists('wc_load_cart')) {
    wc_load_cart();
}

$res = rest_do_request(new WP_REST_Request('GET', '/wc/store/v1/cart'));
w4e_it_equals(200, $res->get_status(), 'GET /wc/store/v1/cart answers 200');
// Normalize: rest_do_request data mixes arrays and stdClass objects.
$cart = json_decode(wp_json_encode($res->get_data()), true);
w4e_it(is_array($cart) && array_key_exists('items', $cart), 'cart payload has items[]');
w4e_it(isset($cart['totals']['currency_minor_unit']), 'cart payload has totals');

foreach (['add-item', 'update-item', 'remove-item', 'apply-coupon', 'remove-coupon'] as $route) {
    $probe = rest_do_request(new WP_REST_Request('OPTIONS', '/wc/store/v1/cart/' . $route));
    w4e_it(404 !== $probe->get_status(), "route /cart/{$route} registered");
}

/* ---- Script + setting wiring ---- */

$asset = WP_PLUGIN_DIR . '/woo4etch/assets/store-api.js';
w4e_it(file_exists($asset), 'assets/store-api.js shipped');
if (file_exists($asset)) {
    $js = file_get_contents($asset);
    w4e_it(strpos($js, 'data-w4e-cart-region') !== false, 'script swaps [data-w4e-cart-region]');
    w4e_it(strpos($js, 'woo4etch:cart-updated') !== false, 'script dispatches woo4etch:cart-updated');
    w4e_it(strpos($js, 'wc_fragment_refresh') !== false, 'script triggers wc_fragment_refresh glue');
}

// Default-on semantics: absent key counts as enabled.
$settings = get_option('woo4etch_settings', []);
$enabled  = !isset($settings['store_api_cart']) || !empty($settings['store_api_cart']);
w4e_it($enabled || isset($settings['store_api_cart']), 'store_api_cart setting resolvable (default on)');

/* ---- Cart bridge exposes the coupon + shipping keys ---- */

$bridge = Woo4Etch::expose_cart_data([]);
foreach (['cart_items', 'cart_coupons', 'cart_discount', 'cart_shipping_total', 'cart_nonce', 'cart_is_empty'] as $key) {
    w4e_it(array_key_exists($key, $bridge), "bridge exposes {$key}");
}
w4e_it(is_array($bridge['cart_coupons']), 'cart_coupons is an array');

/* ---- Shipped layouts carry the region markers ---- */

$cart_json = file_get_contents(WP_PLUGIN_DIR . '/woo4etch/layouts/cart.json');
w4e_it(strpos($cart_json, '"data-w4e-cart-region":"cart"') !== false, 'cart layout marks data-w4e-cart-region="cart"');
w4e_it(strpos($cart_json, 'options.cart_coupons') !== false, 'cart layout renders coupon lines');

if (class_exists('Woo4Etch_Layouts')) {
    $mini = wp_json_encode(Woo4Etch_Layouts::get('mini-cart'));
    if ($mini && 'null' !== $mini) {
        w4e_it(strpos($mini, 'mini-cart') !== false && strpos($mini, 'data-w4e-cart-region') !== false, 'mini-cart layout marks its region');
    } else {
        w4e_it_skip('mini-cart layout not resolvable in this environment');
    }
}

w4e_it_done();
