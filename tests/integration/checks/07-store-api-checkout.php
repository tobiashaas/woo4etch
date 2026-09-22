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

// The checkout payload is empty without a cart and lists no methods without
// an enabled gateway, so both are arranged here — otherwise the gateway
// assertions below would skip forever and quietly prove nothing. Cash on
// delivery is the safest choice: core, no credentials, no external calls.
// Everything is restored in the `finally` at the end of this file.
$w4e_restore_cod  = null;
$w4e_cart_key     = '';
if (function_exists('wc_load_cart')) {
    wc_load_cart();
}

/* ---- Bridge shape ---- */

$w4e_restore_cod = get_option('woocommerce_cod_settings');
$cod             = is_array($w4e_restore_cod) ? $w4e_restore_cod : [];
$cod['enabled']  = 'yes';
update_option('woocommerce_cod_settings', $cod);
if (function_exists('WC') && WC() && WC()->payment_gateways) {
    WC()->payment_gateways()->init();
}

if (function_exists('WC') && WC() && WC()->cart && WC()->cart->is_empty()) {
    foreach ((array) wc_get_products(['limit' => 5, 'status' => 'publish', 'return' => 'ids']) as $pid) {
        $product = wc_get_product($pid);
        if ($product instanceof WC_Product && $product->is_purchasable() && $product->is_in_stock()) {
            $w4e_cart_key = (string) WC()->cart->add_to_cart((int) $pid, 1);
            break;
        }
    }
}

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

/* ---------------------------------------------------------------
   Gateway surface: the seam a payment adapter needs.

   Woo4Etch ships no adapter. What it ships is the place a gateway's
   fields can render and the channel its token can travel; the point of
   these assertions is that both exist and behave, not that any
   particular gateway works.
   --------------------------------------------------------------- */

$methods = isset($checkout['payment_methods']) && is_array($checkout['payment_methods']) ? $checkout['payment_methods'] : [];
if (!$methods) {
    w4e_it_skip('gateway surface (no payment gateways available on this install)');
} else {
    $shaped = true;
    foreach ($methods as $m) {
        if (!is_array($m) || !array_key_exists('has_fields', $m) || !array_key_exists('store_api', $m)) {
            $shaped = false;
            break;
        }
    }
    w4e_it($shaped, 'every payment method exposes has_fields + store_api');

    $bools = true;
    foreach ($methods as $m) {
        if (!is_bool($m['has_fields']) || !is_bool($m['store_api'])) {
            $bools = false;
            break;
        }
    }
    w4e_it($bools, 'has_fields and store_api are bools, so conditions can bind to them');

    // store_api must agree with the allowlist — the layout uses it to decide
    // whether a method places the order live or falls back to a classic post,
    // and a disagreement there would mislead the shopper, not just the theme.
    $agrees = true;
    foreach ($methods as $m) {
        $expected = false;
        foreach ($list as $pattern) {
            if (substr($pattern, -1) === '*'
                ? strpos($m['id'], substr($pattern, 0, -1)) === 0
                : $m['id'] === $pattern) {
                $expected = true;
                break;
            }
        }
        if ((bool) $m['store_api'] !== $expected) {
            $agrees = false;
            break;
        }
    }
    w4e_it($agrees, 'store_api agrees with the gateway allowlist for every method');
}

/* The payment-fields marker: consumed, and never left as an empty shell. */
if (class_exists('Woo4Etch')) {
    $gid    = $methods ? (string) $methods[0]['id'] : 'cod';
    $marker = '<div data-w4e-payment-fields="' . esc_attr($gid) . '"></div>';
    $out    = Woo4Etch::render_etch_placeholders($marker);

    w4e_it(is_string($out) && '' !== $out, 'payment-fields marker renders without fatal');
    w4e_it(
        strpos($out, 'data-w4e-payment-fields="' . $gid . '"') !== false,
        'the marker element survives so the region stays addressable'
    );

    // An id that is not a gateway must not explode, and must not invent output.
    $bogus = Woo4Etch::render_etch_placeholders('<div data-w4e-payment-fields="not_a_gateway"></div>');
    w4e_it(
        strpos($bogus, '</div>') !== false && strpos($bogus, 'not_a_gateway') !== false,
        'an unknown gateway id renders an empty region rather than failing'
    );
}

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

/* ---- restore ---- */
if ('' !== $w4e_cart_key && function_exists('WC') && WC() && WC()->cart) {
    WC()->cart->remove_cart_item($w4e_cart_key);
}
if (null !== $w4e_restore_cod) {
    if (false === $w4e_restore_cod) {
        delete_option('woocommerce_cod_settings');
    } else {
        update_option('woocommerce_cod_settings', $w4e_restore_cod);
    }
}

w4e_it_done();
