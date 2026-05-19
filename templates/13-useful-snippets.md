# 13 — Useful Snippets

Curated recipes for the things that come up in almost every Woo build. Each snippet is self-contained — drop it into **`includes/customizations.php`** in your Woo4Etch install (`plugin/woo4etch/`).

## Add-to-cart URLs

WooCommerce supports adding products to the cart via URL parameters. Useful for marketing campaigns, "Buy Now" links, and email CTAs.

```
# Add a simple product
/?add-to-cart=99

# Add a quantity > 1
/?add-to-cart=99&quantity=3

# Add a variation (parent ID + variation ID + selected attributes)
/?add-to-cart=99&variation_id=123&attribute_pa_size=42&attribute_pa_color=black

# Apply a coupon at the same time
/?add-to-cart=99&coupon-code=SPRING10

# Land directly in the cart after adding
/cart/?add-to-cart=99
```

Behind the scenes, WooCommerce's "URL add-to-cart" handler runs on `wp_loaded`. To redirect after a URL-add, the user has to land on the cart page.

## "Buy Now" button (skip the cart, go straight to checkout)

```php
add_action('woocommerce_after_add_to_cart_button', 'kr_buy_now_button');
function kr_buy_now_button() {
    global $product;
    $checkout_url = wc_get_checkout_url();
    printf(
        '<button type="submit" name="buy_now" value="%d" class="single_add_to_cart_button button buy-now-button">%s</button>',
        $product->get_id(),
        esc_html__('Buy now', 'kr-shop')
    );
}

add_action('wp_loaded', 'kr_buy_now_redirect', 25);
function kr_buy_now_redirect() {
    if (empty($_REQUEST['buy_now'])) return;

    // Clear cart so the order contains only this one item
    WC()->cart->empty_cart();

    $product_id = absint($_REQUEST['buy_now']);
    $quantity   = isset($_REQUEST['quantity']) ? wc_stock_amount($_REQUEST['quantity']) : 1;

    WC()->cart->add_to_cart($product_id, $quantity);

    wp_safe_redirect(wc_get_checkout_url());
    exit;
}
```

## Apply a coupon automatically via URL

`/?coupon-code=SPRING10`

```php
add_action('wp_loaded', function () {
    if (empty($_GET['coupon-code'])) return;
    $code = sanitize_text_field($_GET['coupon-code']);
    if (!WC()->cart->has_discount($code)) {
        WC()->cart->apply_coupon($code);
    }
});
```

## Free-shipping progress bar (cart + mini-cart)

```php
add_action('woocommerce_before_cart',     'kr_free_shipping_bar');
add_action('woocommerce_before_mini_cart','kr_free_shipping_bar');

function kr_free_shipping_bar() {
    $threshold = 50.00;
    $subtotal  = WC()->cart->get_subtotal();
    if ($subtotal <= 0) return;

    $progress = min(100, ($subtotal / $threshold) * 100);
    $diff     = $threshold - $subtotal;

    echo '<div class="shipping-bar">';
    if ($diff > 0) {
        printf(
            '<p>Add <strong>%s</strong> more for free shipping</p>',
            wc_price($diff)
        );
    } else {
        echo '<p>You qualify for free shipping ✓</p>';
    }
    printf(
        '<div class="shipping-bar__track"><div class="shipping-bar__fill" style="width:%s%%"></div></div>',
        number_format($progress, 2)
    );
    echo '</div>';
}
```

## Auto-log-out customer after checkout

For single-use accounts, kiosk setups, or shared computers:

```php
add_action('woocommerce_thankyou', function ($order_id) {
    if (!$order_id || !is_user_logged_in()) return;
    $order = wc_get_order($order_id);
    if (!$order || !$order->has_status(['processing','completed'])) return;

    wp_logout();
    wp_set_current_user(0);
});
```

## Refund-request button on the My Account orders page

```php
add_filter('woocommerce_my_account_my_orders_actions', function ($actions, $order) {
    if (!$order->has_status('completed')) return $actions;

    // Allow refund requests within 14 days
    $days_since = (time() - $order->get_date_created()->getTimestamp()) / DAY_IN_SECONDS;
    if ($days_since > 14) return $actions;

    $actions['request-refund'] = [
        'url'  => wp_nonce_url(
            add_query_arg('refund_request', $order->get_id(), wc_get_account_endpoint_url('orders')),
            'kr_refund_request'
        ),
        'name' => 'Request refund',
    ];
    return $actions;
}, 10, 2);

add_action('wp_loaded', function () {
    if (!isset($_GET['refund_request'])) return;
    if (!check_admin_referer('kr_refund_request')) return;

    $order_id = absint($_GET['refund_request']);
    $order    = wc_get_order($order_id);
    if (!$order || $order->get_customer_id() !== get_current_user_id()) return;

    $order->add_order_note('Customer requested a refund.', 0, true);
    // Optionally: notify admin
    wp_mail(
        get_option('admin_email'),
        'Refund requested for order #' . $order->get_order_number(),
        'A refund was requested for order ' . $order->get_edit_order_url()
    );

    wc_add_notice('Your refund request has been sent.', 'success');
    wp_safe_redirect(wc_get_account_endpoint_url('orders'));
    exit;
});
```

## Price-with-tax / without-tax toggle

```php
// Render a toggle in the shop header
add_action('woocommerce_before_shop_loop', function () {
    $current = WC()->session->get('display_prices_including_tax', wc_prices_include_tax());
    $next    = $current ? 'excl' : 'incl';
    $url     = add_query_arg('tax_display', $next, wc_get_page_permalink('shop'));
    printf(
        '<a class="tax-toggle" href="%s">View prices %s tax</a>',
        esc_url($url),
        $current ? 'excluding' : 'including'
    );
}, 5);

add_action('init', function () {
    if (!isset($_GET['tax_display'])) return;
    $display = $_GET['tax_display'] === 'incl';
    WC()->session->set('display_prices_including_tax', $display);
});

add_filter('woocommerce_get_price_html', function ($html, $product) {
    $display_incl = WC()->session->get('display_prices_including_tax');
    if ($display_incl === null) return $html;

    $price = $display_incl
        ? wc_get_price_including_tax($product)
        : wc_get_price_excluding_tax($product);

    return wc_price($price);
}, 100, 2);
```

## Hide payment methods based on cart total

```php
add_filter('woocommerce_available_payment_gateways', function ($gateways) {
    if (!is_checkout()) return $gateways;
    $total = WC()->cart->get_total('raw');

    // Hide bank transfer (BACS) for small orders
    if ($total < 20 && isset($gateways['bacs'])) {
        unset($gateways['bacs']);
    }

    // Hide cash-on-delivery for orders above a threshold
    if ($total > 500 && isset($gateways['cod'])) {
        unset($gateways['cod']);
    }

    return $gateways;
});
```

## Show/hide bank-account details on the thank-you page

Useful when you offer multiple BACS accounts (e.g. domestic + EU) and want to show only the relevant one:

```php
add_filter('woocommerce_bacs_accounts', function ($accounts, $order_id) {
    $order = wc_get_order($order_id);
    if (!$order) return $accounts;

    $country = $order->get_billing_country();

    // Filter accounts: keep only the one matching country
    return array_filter($accounts, function ($account) use ($country) {
        // Convention: store the country in the bank account's "iban" field comment
        // or use a custom registered account property
        return strpos($account['account_name'] ?? '', $country) !== false
            || empty($country);
    });
}, 10, 2);
```

## Save the currency exchange rate with the order

For multi-currency shops:

```php
add_action('woocommerce_checkout_create_order', function ($order, $data) {
    $base_currency  = get_option('woocommerce_currency');
    $order_currency = $order->get_currency();
    if ($base_currency === $order_currency) return;

    // Replace with your actual rate source
    $rate = (float) get_option("kr_exchange_rate_{$base_currency}_{$order_currency}", 1);
    $order->update_meta_data('_currency_exchange_rate', $rate);
    $order->update_meta_data('_currency_base', $base_currency);
}, 10, 2);
```

## Get all orders that include a specific product

```php
function kr_get_orders_with_product($product_id, $args = []) {
    $args = wp_parse_args($args, [
        'limit'  => -1,
        'status' => ['processing', 'completed'],
        'return' => 'ids',
    ]);

    // HPOS-safe approach: query order items by product_id
    global $wpdb;
    $order_item_ids = $wpdb->get_col($wpdb->prepare(
        "SELECT oi.order_id
         FROM {$wpdb->prefix}woocommerce_order_items oi
         INNER JOIN {$wpdb->prefix}woocommerce_order_itemmeta oim
                 ON oi.order_item_id = oim.order_item_id
         WHERE oim.meta_key = '_product_id'
           AND oim.meta_value = %d",
        $product_id
    ));

    if (!$order_item_ids) return [];

    $args['post__in'] = $order_item_ids;
    return wc_get_orders($args);
}
```

## Sort cart items by name

```php
add_action('woocommerce_cart_loaded_from_session', function () {
    $cart = WC()->cart->get_cart();
    uasort($cart, function ($a, $b) {
        return strcmp($a['data']->get_name(), $b['data']->get_name());
    });
    WC()->cart->cart_contents = $cart;
});
```

## Add a custom column to admin order list

```php
add_filter('manage_edit-shop_order_columns', function ($columns) {
    $new = [];
    foreach ($columns as $key => $label) {
        $new[$key] = $label;
        if ($key === 'order_status') {
            $new['_order_referrer'] = 'Source';
        }
    }
    return $new;
});

add_action('manage_shop_order_posts_custom_column', function ($column, $order_id) {
    if ($column !== '_order_referrer') return;
    echo esc_html( get_post_meta($order_id, '_order_referrer', true) ?: '—' );
}, 10, 2);
```

For HPOS-enabled stores, also add:

```php
add_filter('woocommerce_shop_order_list_table_columns', function ($columns) {
    $columns['_order_referrer'] = 'Source';
    return $columns;
});

add_action('woocommerce_shop_order_list_table_custom_column', function ($column, $order) {
    if ($column !== '_order_referrer') return;
    echo esc_html( $order->get_meta('_order_referrer') ?: '—' );
}, 10, 2);
```

## Send admin email when a coupon is used

```php
add_action('woocommerce_order_status_processing', function ($order_id) {
    $order   = wc_get_order($order_id);
    $coupons = $order->get_coupon_codes();
    if (empty($coupons)) return;

    $admin = get_option('admin_email');
    $body  = sprintf(
        "Order #%s used coupon(s): %s\nOrder total: %s\nView: %s",
        $order->get_order_number(),
        implode(', ', $coupons),
        wp_strip_all_tags(wc_price($order->get_total())),
        admin_url('post.php?post=' . $order_id . '&action=edit')
    );
    wp_mail($admin, 'Coupon used: ' . implode(', ', $coupons), $body);
});
```

## Sources

- Business Bloomer — [Custom Add to Cart URLs](https://www.businessbloomer.com/woocommerce-custom-add-cart-urls-ultimate-guide/)
- Business Bloomer — [Buy Now button](https://www.businessbloomer.com/woocommerce-add-buy-now-button-single-product-page/)
- Business Bloomer — [Auto log-out after checkout](https://www.businessbloomer.com/woocommerce-automatically-log-out-customers-after-checkout/)
- Business Bloomer — [Refund request from My Account](https://www.businessbloomer.com/woocommerce-refund-request-button-my-account/)
- Business Bloomer — [Tax switcher](https://www.businessbloomer.com/woocommerce-simple-price-including-excluding-tax-switcher/)
- Business Bloomer — [Get orders by product](https://www.businessbloomer.com/woocommerce-get-orders-containing-a-specific-product/)
- Business Bloomer — [Save exchange rate](https://www.businessbloomer.com/woocommerce-save-order-currency-exchange-rate/)
- Business Bloomer — [Sort order items](https://www.businessbloomer.com/woocommerce-sort-order-items-by-name-sku-total-quantity/)
