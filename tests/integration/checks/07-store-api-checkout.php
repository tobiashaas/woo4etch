<?php
/**
 * Layer 5 — Store API checkout / option A+ (issue #27). Verifies the checkout
 * bridge, the routes the frontend module writes to, the Germanized guard
 * pieces, and the gateway allowlist wiring. Non-destructive.
 *
 * @package Woo4Etch\Tests\Integration
 */

require __DIR__ . '/_lib.php';

echo "07 store api checkout (A+)\n";

w4e_it(class_exists('Woo4Etch'), 'Woo4Etch class loaded');
w4e_it(method_exists('Woo4Etch', 'expose_checkout_data'), 'checkout bridge exists');

if (function_exists('wc_load_cart')) {
    wc_load_cart();
}

/* ---- Bridge shape ---- */

$bridge   = Woo4Etch::expose_checkout_data([]);
$checkout = $bridge['checkout'] ?? null;
w4e_it(is_array($checkout), 'bridge exposes {options.checkout}');
foreach (['payment_methods', 'shipping_rates', 'checkboxes', 'needs_shipping', 'nonce'] as $key) {
    w4e_it(is_array($checkout) && array_key_exists($key, $checkout), "checkout bridge has {$key}");
}

/* ---- Routes the module writes to ---- */

foreach (['cart/update-customer', 'cart/select-shipping-rate', 'checkout'] as $route) {
    $probe = rest_do_request(new WP_REST_Request('OPTIONS', '/wc/store/v1/' . $route));
    w4e_it(404 !== $probe->get_status(), "route /{$route} registered");
}

/* ---- Frontend module wiring ---- */

$js = file_get_contents(WP_PLUGIN_DIR . '/woo4etch/assets/store-api.js');
w4e_it(strpos($js, 'data-w4e-checkout') !== false, 'module binds the data-w4e-checkout opt-in marker');
w4e_it(strpos($js, '/cart/update-customer') !== false, 'module writes update-customer');
w4e_it(strpos($js, '/cart/select-shipping-rate') !== false, 'module writes select-shipping-rate');
w4e_it(strpos($js, "'/checkout'") !== false, 'module places the order via /checkout');
w4e_it(strpos($js, 'woocommerce-germanized') !== false, 'module always sends the Germanized extensions key');
w4e_it(strpos($js, 'data-w4e-checkout-region') !== false, 'module swaps checkout regions');

/* ---- Ready-made checkout layout ---- */

if (class_exists('Woo4Etch_Layouts')) {
    $layout = Woo4Etch_Layouts::get('checkout');
    $json   = $layout ? wp_json_encode($layout['block']) : '';
    w4e_it(is_array($layout), 'ready-made checkout layout resolvable');
    w4e_it(strpos($json, 'data-w4e-checkout') !== false, 'layout form carries the A+ opt-in marker');
    w4e_it(strpos($json, 'options.checkout.payment_methods') !== false, 'layout loops the payment methods bridge');
    w4e_it(strpos($json, 'data-w4e-checkout-region') !== false, 'layout marks live-update regions');
    w4e_it(strpos($json, 'woocommerce-process-checkout-nonce') !== false, 'layout keeps the classic no-JS fallback nonce');
}
w4e_it(array_key_exists('countries', $checkout), 'checkout bridge has countries');

/* ---- Gateway allowlist ---- */

$list = apply_filters('woo4etch/store_api_checkout_gateways', ['bacs', 'cheque', 'cod', 'invoice', 'mollie_wc_gateway_*']);
w4e_it(is_array($list) && in_array('cod', $list, true), 'gateway allowlist filterable, offline gateways included');

/* ---- Germanized parity (skips cleanly without Germanized) ---- */

if (class_exists('WC_GZD_Legal_Checkbox_Manager')) {
    $boxes = $checkout['checkboxes'];
    w4e_it(is_array($boxes), 'Germanized active: checkboxes enumerated');
    $ok = true;
    foreach ($boxes as $box) {
        if (!isset($box['id'], $box['label'], $box['error'], $box['required'])) {
            $ok = false;
        }
    }
    w4e_it($ok, 'each checkbox carries id/label/error/required');
} else {
    w4e_it_skip('Germanized not active — checkbox parity not applicable');
}

w4e_it_done();
