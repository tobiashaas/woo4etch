<?php
/**
 * Layer 4 — the state/province claim, verified against real WooCommerce.
 *
 * The checkout layout shipped without a `billing_state` field on the theory
 * that a minimal European field set is enough. It is not: WooCommerce's
 * per-country locale overrides only RENAME `state` for countries like
 * Australia — they never set `required => false` — so a form without the
 * field is rejected server-side on both the classic and the Store API path,
 * in those countries only, with nothing in the layout to suggest why.
 *
 * That claim is the load-bearing one behind the fix, and the service-free
 * fast-checks cannot test it: they run without WordPress, so Woo's locale
 * table isn't there. This asks WooCommerce directly, then asserts the bridge
 * payload the layout loops over matches.
 *
 * Non-destructive: the billing country, the store base country and the cart
 * are all restored in `finally`.
 *
 * @package Woo4Etch\Tests\Integration
 */

require __DIR__ . '/_lib.php';

echo "09 checkout address locale (state / address_2)\n";

w4e_it(class_exists('WooCommerce'), 'WooCommerce is active');
w4e_it(class_exists('Woo4Etch'), 'Woo4Etch class loaded');
if (!class_exists('WooCommerce') || !class_exists('Woo4Etch') || !WC()->countries) {
    w4e_it_done();
}

/* ---------------------------------------------------------------
   1. WooCommerce's own answer — the claim, not our restatement.
   --------------------------------------------------------------- */

$au = WC()->countries->get_address_fields('AU', 'billing_');
w4e_it(isset($au['billing_state']), 'AU: billing_state exists in the merged address fields');
w4e_it(
    !empty($au['billing_state']['required']),
    'AU: billing_state is REQUIRED — the locale override renames it, it does not drop the requirement'
);
w4e_it(empty($au['billing_state']['hidden']), 'AU: billing_state is not hidden');

$au_states = WC()->countries->get_states('AU');
w4e_it(is_array($au_states) && !empty($au_states), 'AU: WooCommerce has a state list (so the field renders as a select)');

// The opposite case, and the reason the layout cannot simply always render
// the field: some countries hide it entirely. Germany is the canonical one,
// but assert the CLASS of case rather than one country — Woo's locale table
// is theirs to change, and a rename there is not a Woo4Etch regression.
$hidden_in = [];
foreach (['DE', 'AT', 'BE', 'DK', 'NO', 'PL', 'SE', 'CH', 'CZ', 'FI'] as $cc) {
    $fields = WC()->countries->get_address_fields($cc, 'billing_');
    if (!empty($fields['billing_state']['hidden'])) {
        $hidden_in[] = $cc;
    }
}
w4e_it(
    !empty($hidden_in),
    'some countries hide billing_state, so state_hidden is load-bearing (hidden in: ' . implode(', ', $hidden_in) . ')'
);

// A country with no state list at all → the layout's free-text branch.
$stateless = 0;
foreach (array_slice(array_keys((array) WC()->countries->get_countries()), 0, 80) as $cc) {
    if (empty(WC()->countries->get_states($cc))) {
        $stateless++;
    }
}
w4e_it($stateless > 0, "some countries have no state list, so the free-text branch has real cases ({$stateless} of the first 80)");

/* ---------------------------------------------------------------
   2. The bridge payload the layout loops over.
   --------------------------------------------------------------- */

// expose_checkout_data() memoizes per request, so the payload can only be
// inspected for ONE country per process — Australia, the case that was broken.
$restore_base    = null;
$restore_country = null;
$restore_state   = null;
$cart_key        = '';

// Outside the try/finally: exit() does not run finally blocks, and there is
// nothing to restore yet.
if (function_exists('wc_load_cart') && (!WC()->cart || !WC()->session)) {
    wc_load_cart();
}
if (!WC()->customer || !WC()->cart) {
    w4e_it_skip('bridge payload (no cart/customer object in this context)');
    w4e_it_done();
}

try {
    $restore_base = get_option('woocommerce_default_country');
    update_option('woocommerce_default_country', 'AU:NSW');

    $restore_country = WC()->customer->get_billing_country();
    $restore_state   = WC()->customer->get_billing_state();
    WC()->customer->set_billing_country('AU');
    WC()->customer->set_billing_state('VIC');

    // The bridge returns the empty payload for an empty cart, so put
    // something in it. Any purchasable product will do.
    $product_id = 0;
    foreach ((array) wc_get_products(['limit' => 5, 'status' => 'publish', 'return' => 'ids']) as $id) {
        $product = wc_get_product($id);
        if ($product instanceof WC_Product && $product->is_purchasable() && $product->is_in_stock()) {
            $product_id = (int) $id;
            break;
        }
    }

    if ($product_id && WC()->cart) {
        $cart_key = (string) WC()->cart->add_to_cart($product_id, 1);
    }

    if ('' === $cart_key) {
        w4e_it_skip('bridge payload (no purchasable product on this install)');
    } else {
        $data     = Woo4Etch::expose_checkout_data([]);
        $checkout = isset($data['checkout']) && is_array($data['checkout']) ? $data['checkout'] : [];

        foreach (['states', 'has_states', 'state', 'state_label', 'state_required', 'state_hidden', 'address_2_label', 'address_2_hidden', 'countries'] as $key) {
            w4e_it(array_key_exists($key, $checkout), "{options.checkout.{$key}} exists");
        }

        w4e_it_equals(true, (bool) ($checkout['has_states'] ?? false), 'AU: has_states is true');
        w4e_it_equals(true, (bool) ($checkout['state_required'] ?? false), 'AU: state_required is true');
        w4e_it_equals(false, (bool) ($checkout['state_hidden'] ?? true), 'AU: state_hidden is false');
        w4e_it(
            is_string($checkout['state_label'] ?? null) && '' !== $checkout['state_label'],
            'AU: state_label carries Woo\'s locale label (got ' . var_export($checkout['state_label'] ?? null, true) . ')'
        );

        $states = isset($checkout['states']) && is_array($checkout['states']) ? $checkout['states'] : [];
        w4e_it(!empty($states), 'AU: states is a non-empty array');

        // Shape the layout's loop relies on: {st.code} / {st.name} and the
        // per-option selected flag that renders the checked state server-side.
        $shaped = true;
        foreach ($states as $entry) {
            if (!is_array($entry) || !isset($entry['code'], $entry['name']) || !array_key_exists('selected', $entry)) {
                $shaped = false;
                break;
            }
        }
        w4e_it($shaped, 'AU: every state entry has code, name and selected');

        $codes = array_column($states, 'code');
        w4e_it(in_array('NSW', $codes, true), 'AU: the state list contains NSW');

        // Exactly one option is marked selected, and it is the customer's.
        $selected = array_values(array_filter($states, static function ($s) {
            return !empty($s['selected']);
        }));
        w4e_it_equals(1, count($selected), 'AU: exactly one state is marked selected');
        w4e_it_equals('VIC', (string) ($selected[0]['code'] ?? ''), 'AU: the selected state is the customer\'s');

        // The docblock claimed these were exposed; one of them never was.
        w4e_it(
            isset($checkout['countries']) && is_array($checkout['countries']) && !empty($checkout['countries']),
            'countries is a non-empty array (the key the docblock used to omit)'
        );

        /* ---- Cart bridge: the shipping keys the summary renders ---- */
        $cart_data = Woo4Etch::expose_cart_data([]);
        foreach (['cart_needs_shipping', 'cart_show_shipping', 'cart_shipping_total', 'cart_shipping_notice'] as $key) {
            w4e_it(array_key_exists($key, $cart_data), "{options.{$key}} exists");
        }
        w4e_it(
            is_bool($cart_data['cart_show_shipping'] ?? null),
            'cart_show_shipping is a bool'
        );
        // The two are mutually exclusive by construction: either Woo will
        // disclose the cost here, or it tells the customer where it will.
        w4e_it(
            !(('' !== (string) ($cart_data['cart_shipping_notice'] ?? '')) && !empty($cart_data['cart_show_shipping'])),
            'cart_shipping_notice and cart_show_shipping are never both set'
        );
    }
} finally {
    if ('' !== $cart_key && WC()->cart) {
        WC()->cart->remove_cart_item($cart_key);
    }
    if (WC()->customer) {
        if (null !== $restore_country) {
            WC()->customer->set_billing_country($restore_country);
        }
        if (null !== $restore_state) {
            WC()->customer->set_billing_state($restore_state);
        }
    }
    if (null !== $restore_base) {
        update_option('woocommerce_default_country', $restore_base);
    }
}

w4e_it_done();
