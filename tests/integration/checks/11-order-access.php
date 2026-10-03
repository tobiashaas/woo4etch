<?php
/**
 * Layer 4 — order data access (security).
 *
 * Order ids are sequential, so a request that merely names an order must not
 * be enough to read it. Two paths used to trust the id alone:
 *
 *  - the order-received / view-order endpoint fallback in resolve_order()
 *    (feeds [woo_order_details] and {options.order.*}),
 *  - [do_action] / data-w4e-hook with an order-bound hook such as
 *    woocommerce_thankyou and an author-chosen order id.
 *
 * Both now share one rule: matching order key, the logged-in owner, or a shop
 * manager. This check asserts the refusals as hard as the grants — a false
 * grant leaks a customer's order, a false refusal breaks the thank-you page.
 *
 * Non-destructive: a throwaway product, order and two users, all removed in
 * `finally`. Renders through the same shortcode / placeholder entry points the
 * frontend uses; no real order is read.
 *
 * @package Woo4Etch\Tests\Integration
 */

require __DIR__ . '/_lib.php';

echo "11 order access\n";

w4e_it(class_exists('WooCommerce'), 'WooCommerce is active');
w4e_it(class_exists('Woo4Etch'), 'Woo4Etch class loaded');
if (!class_exists('WooCommerce') || !class_exists('Woo4Etch')) {
    w4e_it_done();
}

$cleanup = [];
$prev_get = $_GET;
$prev_user = get_current_user_id();

try {
    // ---- fixtures --------------------------------------------------------
    $product = new WC_Product_Simple();
    $product->set_name('W4E IT Secret Item');
    $product->set_regular_price('12.00');
    $product->set_status('publish');
    $product->save();
    $cleanup[] = static function () use ($product) {
        $product->delete(true);
    };

    $suffix   = wp_generate_password(6, false, false);
    $owner_id = wp_insert_user(['user_login' => 'w4e_it_owner_' . $suffix, 'user_pass' => wp_generate_password(), 'user_email' => "w4e-it-owner-{$suffix}@example.invalid", 'role' => 'customer']);
    $other_id = wp_insert_user(['user_login' => 'w4e_it_other_' . $suffix, 'user_pass' => wp_generate_password(), 'user_email' => "w4e-it-other-{$suffix}@example.invalid", 'role' => 'customer']);
    $mgr_id   = wp_insert_user(['user_login' => 'w4e_it_mgr_' . $suffix, 'user_pass' => wp_generate_password(), 'user_email' => "w4e-it-mgr-{$suffix}@example.invalid", 'role' => 'shop_manager']);
    foreach ([$owner_id, $other_id, $mgr_id] as $uid) {
        if (is_wp_error($uid)) {
            w4e_it(false, 'fixture user created');
            w4e_it_done();
        }
        $cleanup[] = static function () use ($uid) {
            require_once ABSPATH . 'wp-admin/includes/user.php';
            wp_delete_user($uid);
        };
    }

    $order = wc_create_order(['customer_id' => $owner_id]);
    $order->add_product($product, 1);
    $order->set_billing_first_name('W4E');
    $order->set_billing_last_name('IT-Customer');
    $order->set_billing_email("w4e-it-private-{$suffix}@example.invalid");
    $order->calculate_totals();
    $order->save();
    $cleanup[] = static function () use ($order) {
        $order->delete(true);
    };

    $order_id = $order->get_id();
    $key      = $order->get_order_key();
    $item     = 'W4E IT Secret Item';

    $as = static function ($user_id) {
        wp_set_current_user($user_id);
        $_GET = [];
    };
    $set_endpoint = static function ($var, $id) {
        $GLOBALS['wp_query']->set('order-received', 'order-received' === $var ? $id : '');
        $GLOBALS['wp_query']->set('view-order', 'view-order' === $var ? $id : '');
    };
    $details = static function () {
        return do_shortcode('[woo_order_details]');
    };

    // ---- #38: endpoint fallback -----------------------------------------
    $set_endpoint('order-received', $order_id);

    $as(0);
    w4e_it(false === strpos($details(), $item), 'order-received: anonymous without key sees nothing');

    $_GET = ['key' => 'wc_order_not_the_key'];
    w4e_it(false === strpos($details(), $item), 'order-received: wrong key sees nothing');

    $_GET = ['key' => $key];
    w4e_it(false !== strpos($details(), $item), 'order-received: correct key sees the order');

    $as($other_id);
    w4e_it(false === strpos($details(), $item), 'order-received: another customer without key sees nothing');

    $as($owner_id);
    w4e_it(false !== strpos($details(), $item), 'order-received: owner sees the order');

    $as($mgr_id);
    w4e_it(false !== strpos($details(), $item), 'order-received: shop manager sees the order');

    $set_endpoint('view-order', $order_id);

    $as(0);
    w4e_it(false === strpos($details(), $item), 'view-order: anonymous sees nothing');

    $as($other_id);
    w4e_it(false === strpos($details(), $item), 'view-order: another customer sees nothing');

    $as($owner_id);
    w4e_it(false !== strpos($details(), $item), 'view-order: owner sees the order');

    $set_endpoint('order-received', 0);

    // explicit order_id keeps its existing rule
    $as(0);
    w4e_it(false === strpos(do_shortcode('[woo_order_details order_id="' . $order_id . '"]'), $item), 'order_id attribute: anonymous without key sees nothing');
    w4e_it(false !== strpos(do_shortcode('[woo_order_details order_id="' . $order_id . '" key="' . $key . '"]'), $item), 'order_id attribute: correct key sees the order');

    // options.order bridge goes through the same resolution
    $set_endpoint('order-received', $order_id);
    $as(0);
    $opts = apply_filters('etch/dynamic_data/option', []);
    w4e_it(empty($opts['order']), 'options.order is empty for an anonymous visitor');
    $_GET = ['key' => $key];
    $opts = apply_filters('etch/dynamic_data/option', []);
    w4e_it(!empty($opts['order']) && (int) $opts['order']['id'] === $order_id, 'options.order is filled for the order-key holder');
    $set_endpoint('order-received', 0);

    // ---- #39: order-bound hooks via [do_action] and the marker -----------
    $probe_thankyou = static function ($id) {
        echo 'W4E-IT-THANKYOU-' . (int) $id;
    };
    $probe_plain = static function () {
        echo 'W4E-IT-PLAIN';
    };
    add_action('woocommerce_thankyou_w4eit', $probe_thankyou);
    add_action('w4e_it_plain_hook', $probe_plain);
    $cleanup[] = static function () use ($probe_thankyou, $probe_plain) {
        remove_action('woocommerce_thankyou_w4eit', $probe_thankyou);
        remove_action('w4e_it_plain_hook', $probe_plain);
    };

    $bound  = '[do_action hook="woocommerce_thankyou_w4eit" args="' . $order_id . '"]';
    $marker = '<div data-w4e-hook="woocommerce_thankyou_w4eit" data-w4e-args="' . $order_id . '"></div>';
    $expect = 'W4E-IT-THANKYOU-' . $order_id;

    $as(0);
    w4e_it(false === strpos(do_shortcode($bound), $expect), '[do_action] order-bound hook: anonymous author-chosen id prints nothing');
    w4e_it(false === strpos(Woo4Etch::render_etch_placeholders($marker), $expect), 'data-w4e-hook order-bound: anonymous prints nothing');

    $as($other_id);
    w4e_it(false === strpos(do_shortcode($bound), $expect), '[do_action] order-bound hook: another customer prints nothing');

    $_GET = ['key' => $key];
    w4e_it(false !== strpos(do_shortcode($bound), $expect), '[do_action] order-bound hook: order-key holder still renders (thank-you page)');
    w4e_it(false !== strpos(Woo4Etch::render_etch_placeholders($marker), $expect), 'data-w4e-hook order-bound: order-key holder still renders');

    $as($owner_id);
    w4e_it(false !== strpos(do_shortcode($bound), $expect), '[do_action] order-bound hook: owner renders');

    $as($mgr_id);
    w4e_it(false !== strpos(do_shortcode($bound), $expect), '[do_action] order-bound hook: shop manager renders');

    // Non-order hooks are untouched — the whole point of the hook layer.
    $as(0);
    w4e_it(false !== strpos(do_shortcode('[do_action hook="w4e_it_plain_hook"]'), 'W4E-IT-PLAIN'), '[do_action] non-order hook: still fires for anonymous');
    w4e_it(false !== strpos(do_shortcode('[do_action hook="w4e_it_plain_hook" args="' . $product->get_id() . '"]'), 'W4E-IT-PLAIN'), '[do_action] non-order hook with an id argument: still fires');

    // The gate is extensible for third-party hooks that take an order id.
    $extend = static function ($is_bound, $hook) {
        return $is_bound || 'w4e_it_plain_hook' === $hook;
    };
    add_filter('woo4etch/order_bound_hook', $extend, 10, 2);
    w4e_it(false === strpos(do_shortcode('[do_action hook="w4e_it_plain_hook" args="' . $order_id . '"]'), 'W4E-IT-PLAIN'), 'woo4etch/order_bound_hook extends the gate to a custom hook');
    remove_filter('woo4etch/order_bound_hook', $extend, 10);
} finally {
    $_GET = $prev_get;
    wp_set_current_user($prev_user);
    foreach (array_reverse($cleanup) as $fn) {
        try {
            $fn();
        } catch (Throwable $e) {
            echo '  note cleanup: ' . $e->getMessage() . "\n";
        }
    }
}

w4e_it_done();
