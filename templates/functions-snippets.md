# Woo4Etch — PHP Snippets (Consolidated Reference)

All PHP snippets from the template files in this folder, organized so you can copy what you need into one file (theme `functions.php`, child theme, or the **Woo4Etch** MU-plugin).

> **Recommendation:** copy snippets into **`wp-content/plugins/woo4etch/includes/customizations.php`** (or the same path under `mu-plugins/woo4etch/` if you run Woo4Etch as an MU-plugin). Shortcodes are already provided by the main `woo4etch.php` in that package.

## Where to put this code

After installing [`plugin/woo4etch/`](../plugin/woo4etch/), edit **`includes/customizations.php`** on your site. Do not create a second plugin file.

---

## Setup

### Declare WooCommerce theme support

```php
add_action('after_setup_theme', function () {
    add_theme_support('woocommerce', [
        'thumbnail_image_width' => 600,
        'single_image_width'    => 1200,
        'product_grid'          => [
            'default_rows'    => 3,
            'min_rows'        => 1,
            'default_columns' => 3,
            'min_columns'     => 1,
            'max_columns'     => 6,
        ],
    ]);

    add_theme_support('wc-product-gallery-zoom');
    add_theme_support('wc-product-gallery-lightbox');
    add_theme_support('wc-product-gallery-slider');
});
```

### Disable WooCommerce default styles

```php
add_filter('woocommerce_enqueue_styles', '__return_empty_array');
```

### Replace WooCommerce content wrappers

```php
remove_action('woocommerce_before_main_content', 'woocommerce_output_content_wrapper', 10);
remove_action('woocommerce_after_main_content',  'woocommerce_output_content_wrapper_end', 10);

add_action('woocommerce_before_main_content', function () {
    echo '<main id="main" class="site-main">';
});
add_action('woocommerce_after_main_content', function () {
    echo '</main>';
});
```

---

## Quantity input (used on single product + cart)

### Plus/minus buttons via hooks

```php
add_action('woocommerce_before_quantity_input_field', function () {
    echo '<button type="button" class="qty-minus" aria-label="Decrease quantity">−</button>';
});

add_action('woocommerce_after_quantity_input_field', function () {
    echo '<button type="button" class="qty-plus" aria-label="Increase quantity">+</button>';
});
```

### Companion JS (vanilla)

```js
document.addEventListener('click', (e) => {
  const target = e.target;
  if (!target.matches('.qty-minus, .qty-plus')) return;
  const input = target.closest('.quantity')?.querySelector('input[name="quantity"]');
  if (!input) return;
  const step = Number(input.step) || 1;
  const min  = Number(input.min)  || 1;
  const current = Number(input.value) || min;
  input.value = target.classList.contains('qty-minus')
    ? Math.max(min, current - step)
    : current + step;
  input.dispatchEvent(new Event('change', { bubbles: true }));
});
```

---

## Single Product — Simple

### Trust badges after add-to-cart

```php
add_action('woocommerce_after_add_to_cart_button', function () {
    echo '<ul class="trust-badges" role="list">
            <li>Free shipping over €50</li>
            <li>Secure payment</li>
            <li>14-day return policy</li>
          </ul>';
});
```

### Dynamic button text based on stock

```php
add_filter('woocommerce_product_single_add_to_cart_text', function ($text, $product) {
    if (!$product->is_in_stock()) {
        return 'Notify me when available';
    }
    return $text;
}, 10, 2);
```

### AJAX add-to-cart for simple products

```php
add_filter('woocommerce_add_to_cart_redirect', function ($url) {
    return wp_get_referer() ?: $url;
});
```

---

## Single Product — Variable

### Ensure variation script is loaded

```php
add_action('wp_enqueue_scripts', function () {
    if (is_product()) {
        wp_enqueue_script('wc-add-to-cart-variation');
    }
});
```

### Provide variations JSON to Etch (filter name is illustrative)

```php
add_filter('etch/dynamic_data/item', function ($data, $post) {
    if ($post->post_type !== 'product') return $data;

    $product = wc_get_product($post->ID);
    if (!$product instanceof WC_Product_Variable) return $data;

    $data['variationsJson']               = wc_esc_json(wp_json_encode($product->get_available_variations()));
    $data['meta']['_min_variation_price'] = wc_price($product->get_variation_price('min'));

    return $data;
}, 10, 2);
```

### Disable default add-to-cart-button render in variation slot

```php
add_action('init', function () {
    remove_action('woocommerce_single_variation', 'woocommerce_single_variation_add_to_cart_button', 20);
});
```

### Switch gallery image on variation select

```js
jQuery(document).on('found_variation', 'form.variations_form', function (event, variation) {
  if (variation.image && variation.image.src) {
    document.querySelector('.product__gallery img').src = variation.image.src;
  }
});
```

### Helper text under variants

```php
add_action('woocommerce_after_variations_table', function () {
    echo '<p class="variations-helper">Select all options to see price and availability.</p>';
});
```

### Initialize variations form manually

```js
jQuery(function ($) {
  $('.variations_form').each(function () {
    $(this).wc_variation_form();
  });
});
```

---

## Product Archive / Shop

### Sort options

```php
add_filter('woocommerce_catalog_orderby', function () {
    return [
        'menu_order' => 'Default',
        'price'      => 'Price: low to high',
        'price-desc' => 'Price: high to low',
        'date'       => 'Newest',
    ];
});
```

### Products per page

```php
add_filter('loop_shop_per_page', function () { return 24; });
```

### Columns

```php
add_filter('loop_shop_columns', function () { return 3; });
```

### Remove default loop callbacks (use your own Etch markup)

```php
add_action('init', function () {
    remove_action('woocommerce_before_shop_loop_item_title', 'woocommerce_template_loop_product_thumbnail', 10);
    remove_action('woocommerce_after_shop_loop_item_title', 'woocommerce_template_loop_price', 10);
    remove_action('woocommerce_after_shop_loop_item_title', 'woocommerce_template_loop_rating', 5);
});
```

### Sale badge

```php
add_action('woocommerce_after_shop_loop_item_title', function () {
    global $product;
    if ($product->is_on_sale()) {
        echo '<span class="badge badge--sale">Sale</span>';
    }
}, 15);
```

### Loop button text

```php
add_filter('woocommerce_product_add_to_cart_text', function ($text, $product) {
    if (!$product->is_in_stock()) return 'Unavailable';
    if ($product->is_type('variable')) return 'Select options';
    return 'Add to cart';
}, 10, 2);
```

---

## Cart

### REST endpoint for cart data (for Etch / custom UI)

```php
add_action('rest_api_init', function () {
    register_rest_route('shop/v1', '/cart', [
        'methods'             => 'GET',
        'permission_callback' => '__return_true',
        'callback'            => function () {
            $cart  = WC()->cart;
            $items = [];
            foreach ($cart->get_cart() as $key => $item) {
                $product = $item['data'];
                $items[] = [
                    'key'       => $key,
                    'productId' => $product->get_id(),
                    'title'     => $product->get_name(),
                    'permalink' => $product->get_permalink(),
                    'image'     => ['url' => wp_get_attachment_url($product->get_image_id())],
                    'price'     => wc_price($product->get_price()),
                    'quantity'  => $item['quantity'],
                    'subtotal'  => wc_price($item['line_subtotal']),
                    'sku'       => $product->get_sku(),
                    'removeUrl' => wc_get_cart_remove_url($key),
                    'variation' => $item['variation'] ?? new stdClass(),
                ];
            }
            return [
                'items'         => $items,
                'subtotal'      => wc_price($cart->get_subtotal()),
                'total'         => wc_price($cart->get_total('raw')),
                'shippingTotal' => wc_price($cart->get_shipping_total()),
                'nonce'         => wp_create_nonce('woocommerce-cart'),
                'url'           => wc_get_cart_url(),
            ];
        },
    ]);
});
```

### Disable default cross-sells

```php
add_action('init', function () {
    remove_action('woocommerce_cart_collaterals', 'woocommerce_cross_sell_display');
});
```

### Custom note after order total

```php
add_action('woocommerce_cart_totals_after_order_total', function () {
    echo '<tr><td colspan="2" class="cart-note">All prices include VAT, plus shipping.</td></tr>';
});
```

### Empty cart button

```php
add_action('woocommerce_cart_actions', function () {
    $url = wp_nonce_url(add_query_arg('empty_cart', '1', wc_get_cart_url()), 'woocommerce-cart');
    echo '<a href="' . esc_url($url) . '" class="button">Empty cart</a>';
});

add_action('wp_loaded', function () {
    if (isset($_GET['empty_cart']) && check_admin_referer('woocommerce-cart')) {
        WC()->cart->empty_cart();
        wp_safe_redirect(wc_get_cart_url());
        exit;
    }
});
```

### Live quantity update (no manual update button)

```js
jQuery(function ($) {
  $('form.woocommerce-cart-form').on('change', 'input.qty', function () {
    $('[name="update_cart"]').prop('disabled', false).trigger('click');
  });
});
```

---

## Mini-Cart

### Counter fragment

```php
add_filter('woocommerce_add_to_cart_fragments', function ($fragments) {
    ob_start();
    $count = WC()->cart->get_cart_contents_count();
    ?>
    <span class="mini-cart-count" data-count="<?php echo esc_attr($count); ?>">
      <?php echo esc_html($count); ?>
    </span>
    <?php
    $fragments['span.mini-cart-count'] = ob_get_clean();
    return $fragments;
});
```

### Subtotal fragment

```php
add_filter('woocommerce_add_to_cart_fragments', function ($fragments) {
    ob_start();
    ?>
    <span class="mini-cart-subtotal">
      <?php echo WC()->cart->get_cart_subtotal(); ?>
    </span>
    <?php
    $fragments['span.mini-cart-subtotal'] = ob_get_clean();
    return $fragments;
});
```

### Full dropdown fragment

```php
add_filter('woocommerce_add_to_cart_fragments', function ($fragments) {
    ob_start();
    ?>
    <div id="mini-cart-dropdown"
         class="mini-cart-dropdown widget_shopping_cart_content"
         role="dialog"
         aria-label="Cart contents"
         hidden>
      <?php woocommerce_mini_cart(); ?>
    </div>
    <?php
    $fragments['#mini-cart-dropdown'] = ob_get_clean();
    return $fragments;
});
```

### Free-shipping threshold message

```php
add_action('woocommerce_before_mini_cart', function () {
    $threshold = 50.00;
    $current   = WC()->cart->get_subtotal();
    $diff      = $threshold - $current;

    if ($diff > 0) {
        printf(
            '<p class="mini-cart__threshold">Add <strong>%s</strong> for free shipping</p>',
            wc_price($diff)
        );
    } else {
        echo '<p class="mini-cart__threshold mini-cart__threshold--unlocked">You qualify for free shipping ✓</p>';
    }
});
```

### Auto-open mini-cart on add

```js
jQuery(document.body).on('added_to_cart', function () {
  document.querySelector('.mini-cart-wrapper')?.setAttribute('data-cart-open', 'true');
  document.querySelector('#mini-cart-dropdown')?.removeAttribute('hidden');
  document.querySelector('.mini-cart')?.setAttribute('aria-expanded', 'true');
});
```

---

## Checkout

### Modify checkout fields

```php
add_filter('woocommerce_checkout_fields', function ($fields) {
    $fields['billing']['billing_phone']['required'] = true;
    unset($fields['billing']['billing_company']);

    $fields['order']['order_referrer'] = [
        'type'     => 'text',
        'label'    => 'How did you hear about us?',
        'required' => false,
        'class'    => ['form-row-wide'],
        'priority' => 110,
    ];

    return $fields;
});
```

### Reorder via priority

```php
add_filter('woocommerce_checkout_fields', function ($fields) {
    $fields['billing']['billing_email']['priority'] = 5;
    $fields['billing']['billing_phone']['priority'] = 25;
    return $fields;
});
```

### Validate custom field

```php
add_action('woocommerce_checkout_process', function () {
    if (!empty($_POST['order_referrer']) && strlen($_POST['order_referrer']) > 200) {
        wc_add_notice('Please keep the "How did you hear about us?" answer under 200 characters.', 'error');
    }
});
```

### Save custom field on order

```php
add_action('woocommerce_checkout_update_order_meta', function ($order_id) {
    if (!empty($_POST['order_referrer'])) {
        update_post_meta($order_id, '_order_referrer', sanitize_text_field($_POST['order_referrer']));
    }
});
```

### Remove default login and coupon forms

```php
add_action('init', function () {
    remove_action('woocommerce_before_checkout_form', 'woocommerce_checkout_login_form', 10);
    remove_action('woocommerce_before_checkout_form', 'woocommerce_checkout_coupon_form', 10);
});
```

### Terms checkbox text

```php
add_filter('woocommerce_get_terms_and_conditions_checkbox_text', function () {
    return 'I have read and agree to the <a href="/terms" target="_blank">Terms</a> and <a href="/privacy" target="_blank">Privacy Policy</a>.';
});
```

### Order button text

```php
add_filter('woocommerce_order_button_text', function () {
    return 'Place order';
});
```

### Filter shipping methods

```php
add_filter('woocommerce_package_rates', function ($rates) {
    return array_filter($rates, function ($rate) {
        return $rate->method_id === 'flat_rate' || strpos($rate->method_id, 'dhl') !== false;
    });
});
```

### Update totals on custom-field change

```php
add_filter('woocommerce_checkout_fields', function ($fields) {
    $fields['billing']['billing_postcode']['class'][] = 'update_totals_on_change';
    return $fields;
});
```

---

## My Account

### Modify nav menu

```php
add_filter('woocommerce_account_menu_items', function ($items) {
    $items['favorites'] = 'Favorites';
    unset($items['downloads']);

    $order   = ['dashboard', 'orders', 'favorites', 'edit-address', 'edit-account', 'customer-logout'];
    $ordered = [];
    foreach ($order as $key) {
        if (isset($items[$key])) $ordered[$key] = $items[$key];
    }
    return $ordered;
});
```

### Register custom endpoint

```php
add_action('init', function () {
    add_rewrite_endpoint('favorites', EP_PAGES);
});

add_filter('woocommerce_get_query_vars', function ($vars) {
    $vars['favorites'] = 'favorites';
    return $vars;
});

add_action('woocommerce_account_favorites_endpoint', function () {
    echo '<h2>Your favorites</h2>';
    // …
});
```

### Order actions (re-order button)

```php
add_filter('woocommerce_my_account_my_orders_actions', function ($actions, $order) {
    if ($order->has_status('completed')) {
        $actions['reorder'] = [
            'url'  => wp_nonce_url(add_query_arg('order_again', $order->get_id()), 'woocommerce-order_again'),
            'name' => 'Reorder',
        ];
    }
    return $actions;
}, 10, 2);
```

### Save additional account-detail field

```php
add_action('woocommerce_save_account_details', function ($user_id) {
    if (!empty($_POST['account_phone'])) {
        update_user_meta($user_id, 'billing_phone', sanitize_text_field($_POST['account_phone']));
    }
});
```

### Redirect after login

```php
add_filter('woocommerce_login_redirect', function ($redirect, $user) {
    return user_can($user, 'manage_options')
        ? admin_url()
        : wc_get_account_endpoint_url('orders');
}, 10, 2);
```

### Auto-login after registration

```php
add_filter('woocommerce_registration_auth_new_customer', '__return_true');
```

---

## Thank You / Order Received

### Google Ads conversion (fires once per order)

```php
add_action('woocommerce_thankyou', function ($order_id) {
    if (!$order_id) return;
    $order = wc_get_order($order_id);
    if (!$order) return;
    if (get_post_meta($order_id, '_conversion_tracked', true)) return;
    update_post_meta($order_id, '_conversion_tracked', '1');

    ?>
    <script>
      gtag('event', 'conversion', {
        send_to: 'AW-XXXXXX/YYYYYY',
        value: <?php echo (float) $order->get_total(); ?>,
        currency: '<?php echo esc_js($order->get_currency()); ?>',
        transaction_id: '<?php echo esc_js($order->get_order_number()); ?>'
      });
    </script>
    <?php
});
```

### GA4 purchase event

```php
add_action('woocommerce_thankyou', function ($order_id) {
    $order = wc_get_order($order_id);
    if (!$order) return;
    if (get_post_meta($order_id, '_ga4_tracked', true)) return;
    update_post_meta($order_id, '_ga4_tracked', '1');

    $items = [];
    foreach ($order->get_items() as $item) {
        $product = $item->get_product();
        if (!$product) continue;
        $items[] = [
            'item_id'   => $product->get_sku() ?: $product->get_id(),
            'item_name' => $item->get_name(),
            'price'     => (float) $order->get_item_subtotal($item, false, false),
            'quantity'  => (int) $item->get_quantity(),
        ];
    }

    ?>
    <script>
      gtag('event', 'purchase', {
        transaction_id: '<?php echo esc_js($order->get_order_number()); ?>',
        value: <?php echo (float) $order->get_total(); ?>,
        currency: '<?php echo esc_js($order->get_currency()); ?>',
        items: <?php echo wp_json_encode($items); ?>
      });
    </script>
    <?php
});
```

### Custom row in order-received totals

```php
add_filter('woocommerce_get_order_item_totals', function ($totals, $order) {
    $totals['custom_note'] = [
        'label' => 'Estimated delivery:',
        'value' => '3–5 business days',
    ];
    return $totals;
}, 10, 2);
```

### Redirect paid orders away from default thank-you

```php
add_action('woocommerce_thankyou', function ($order_id) {
    $order = wc_get_order($order_id);
    if ($order && $order->is_paid()) {
        wp_safe_redirect(home_url('/welcome-customer/?order=' . $order_id));
        exit;
    }
}, 1);
```

---

## Emails

### Custom subject and heading

```php
add_filter('woocommerce_email_subject_customer_processing_order', function ($subject, $order) {
    return sprintf('Order #%s received — we\'re on it', $order->get_order_number());
}, 10, 2);

add_filter('woocommerce_email_heading_customer_processing_order', function () {
    return 'Thanks for your order!';
});
```

### Inject custom email CSS

```php
add_filter('woocommerce_email_styles', function ($css) {
    return $css . "
        #body_content h1 { font-family: Georgia, serif; }
        .button { background-color: #1a1a1a; color: #fff; padding: 12px 24px; }
    ";
});
```

### Tracking link in completed-order email

```php
add_action('woocommerce_email_order_meta', function ($order, $sent_to_admin, $plain_text, $email) {
    if ($email->id !== 'customer_completed_order') return;
    $tracking = $order->get_meta('_tracking_number');
    $url      = $order->get_meta('_tracking_url');
    if (!$tracking || !$url) return;

    if ($plain_text) {
        echo "\nTracking number: $tracking\nTrack your shipment: $url\n";
    } else {
        echo '<p><strong>Tracking:</strong> <a href="' . esc_url($url) . '">' . esc_html($tracking) . '</a></p>';
    }
}, 10, 4);
```

### Email preview in browser (admin only)

```php
add_action('admin_init', function () {
    if (!current_user_can('manage_options')) return;
    if (!isset($_GET['preview_email'])) return;

    $order = wc_get_order((int) ($_GET['order_id'] ?? 0));
    if (!$order) wp_die('Order not found');

    $email_id    = sanitize_key($_GET['preview_email']);
    $email_class = 'WC_Email_' . str_replace(' ', '_', ucwords(str_replace('_', ' ', $email_id)));
    $email       = WC()->mailer()->emails[$email_class] ?? null;
    if (!$email) wp_die('Unknown email');

    $email->object = $order;
    echo $email->get_content_html();
    exit;
});
```

Visit `/wp-admin/?preview_email=customer_processing_order&order_id=123`.

---

## Useful smaller helpers

### Stop WooCommerce from loading scripts on non-Woo pages

```php
add_action('wp_enqueue_scripts', function () {
    if (!is_woocommerce() && !is_cart() && !is_checkout() && !is_account_page()) {
        wp_dequeue_style('woocommerce-general');
        wp_dequeue_style('woocommerce-layout');
        wp_dequeue_style('woocommerce-smallscreen');
        wp_dequeue_script('wc-add-to-cart');
        wp_dequeue_script('wc-cart-fragments');
    }
}, 99);
```

> Keep `wc-cart-fragments` loading site-wide if you have a mini-cart in the header.

### Hide products out of stock

```php
add_filter('woocommerce_product_query_meta_query', function ($meta_query) {
    $meta_query[] = [
        'key'     => '_stock_status',
        'value'   => 'instock',
        'compare' => '=',
    ];
    return $meta_query;
});
```

### Change number of related products

```php
add_filter('woocommerce_output_related_products_args', function ($args) {
    $args['posts_per_page'] = 4;
    $args['columns']        = 4;
    return $args;
});
```

### Disable WooCommerce admin notices for non-admins

```php
add_action('admin_head', function () {
    if (!current_user_can('manage_woocommerce')) {
        remove_all_actions('admin_notices');
    }
}, 0);
```
