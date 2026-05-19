# 11 — Conditional Tags & `$product` API

Quick reference for the most-used WooCommerce PHP helpers you'll reach for from the PHP layer in any of the other templates. Two categories: **conditional tags** (where am I?) and **`$product` methods** (what do I know about this product?).

## Conditional tags

Use these in `functions.php` to scope hooks to specific contexts. They mirror WordPress's own `is_*()` family but for WooCommerce pages.

### Page-type checks

| Function | True when | Notes |
|---|---|---|
| `is_woocommerce()` | Viewing any Woo-driven page | Shop, single product, category, tag |
| `is_shop()` | Viewing the shop page (`/shop`) | Main archive |
| `is_product_category( $slug? )` | On a product category archive | Optional slug filter: `is_product_category('shoes')` |
| `is_product_tag( $slug? )` | On a product tag archive | |
| `is_product()` | On a single product page | True for both simple and variable |
| `is_cart()` | On the cart page | |
| `is_checkout()` | On the checkout page | Also true on `order-pay` and `order-received` |
| `is_account_page()` | On any My Account page | All endpoints |
| `is_view_order_page()` | Viewing a specific order | `/my-account/view-order/{id}` |
| `is_order_received_page()` | On the thank-you page | |
| `is_wc_endpoint_url( 'orders' )` | On a specific account endpoint | Pass the endpoint slug |
| `is_ajax()` | During an AJAX request | Use to skip slow code paths during XHR |

### Cart / order state

| Function | True when |
|---|---|
| `WC()->cart->is_empty()` | Cart has no items |
| `WC()->cart->get_cart_contents_count()` | Returns number of items (use `> 0` for boolean) |
| `WC()->cart->has_discount( $coupon_code? )` | A coupon is applied (optionally a specific one) |
| `WC()->cart->needs_shipping()` | At least one item ships |
| `WC()->cart->needs_payment()` | Total is greater than zero |

### Customer state

| Function | True when |
|---|---|
| `is_user_logged_in()` | User is authenticated (WP core) |
| `wc_customer_bought_product( $email, $user_id, $product_id )` | The customer has bought this product before |
| `current_user_can('manage_woocommerce')` | Admin/shop manager |

### Example — scope a hook to a specific page

```php
// Add a banner only on the shoes category archive
add_action('woocommerce_before_shop_loop', function () {
    if (!is_product_category('shoes')) return;
    echo '<div class="category-banner">Spring shoes — free shipping on all orders this week.</div>';
});
```

```php
// Only run expensive logic on the single product page
add_action('woocommerce_after_single_product', function () {
    if (!is_product()) return;
    // expensive thing
});
```

## The `$product` object

Inside hooks like `woocommerce_after_shop_loop_item` and templates, you have access to a `$product` (an instance of `WC_Product`). Methods are categorized below.

### Identity

| Method | Returns | Notes |
|---|---|---|
| `$product->get_id()` | int | Product or variation ID |
| `$product->get_name()` | string | Product title |
| `$product->get_slug()` | string | URL slug |
| `$product->get_type()` | string | `simple`, `variable`, `grouped`, `external` |
| `$product->get_sku()` | string | SKU |
| `$product->get_permalink()` | string | Full URL |
| `$product->get_status()` | string | `publish`, `draft`, … |
| `$product->is_visible()` | bool | False if hidden from catalog/search |

### Pricing

| Method | Returns | Notes |
|---|---|---|
| `$product->get_price()` | string | Current effective price (sale or regular) |
| `$product->get_regular_price()` | string | The non-sale price |
| `$product->get_sale_price()` | string | Sale price if set |
| `$product->is_on_sale()` | bool | |
| `$product->get_price_html()` | string | Fully formatted HTML — includes currency, strikethrough, "from" for variables |
| `wc_price( $product->get_price() )` | string | Format any number with the store's currency settings |

### Stock & availability

| Method | Returns | Notes |
|---|---|---|
| `$product->is_in_stock()` | bool | |
| `$product->is_purchasable()` | bool | Has a price + is in stock + is visible |
| `$product->get_stock_quantity()` | int\|null | `null` when "manage stock" is off |
| `$product->get_stock_status()` | string | `instock`, `outofstock`, `onbackorder` |
| `$product->is_on_backorder()` | bool | |
| `$product->get_availability()` | array | `['availability' => 'In stock', 'class' => 'in-stock']` |

### Type checks

| Method | True when |
|---|---|
| `$product->is_type('simple')` | Simple product |
| `$product->is_type('variable')` | Variable product (parent) |
| `$product->is_type('variation')` | A specific variation |
| `$product->is_type('grouped')` | Grouped product |
| `$product->is_type('external')` | External/affiliate product |
| `$product->is_downloadable()` | Has downloads |
| `$product->is_virtual()` | No shipping required |

### Variations (only on `WC_Product_Variable`)

| Method | Returns |
|---|---|
| `$product->get_available_variations()` | array of all variations as arrays |
| `$product->get_variation_attributes()` | array of attribute → possible values |
| `$product->get_variation_default_attributes()` | array of pre-selected attributes |
| `$product->get_variation_price('min'\|'max')` | string |
| `$product->get_children()` | array of variation IDs |

### Images & media

| Method | Returns |
|---|---|
| `$product->get_image_id()` | int — featured image attachment ID |
| `$product->get_image()` | string — full `<img>` tag |
| `$product->get_gallery_image_ids()` | array of attachment IDs |
| `wp_get_attachment_image_url( $product->get_image_id(), 'large' )` | string URL at a specific size |

### Taxonomies / attributes

| Method | Returns |
|---|---|
| `$product->get_category_ids()` | array of term IDs |
| `$product->get_tag_ids()` | array of term IDs |
| `$product->get_attributes()` | array of `WC_Product_Attribute` |
| `$product->get_attribute( 'pa_color' )` | comma-separated string of values |

### Relations

| Method | Returns |
|---|---|
| `$product->get_related( $limit = 5 )` | array of related product IDs |
| `$product->get_upsell_ids()` | array of upsell IDs |
| `$product->get_cross_sell_ids()` | array of cross-sell IDs |

### Custom meta

```php
// Get a custom field
$value = $product->get_meta('_my_custom_field');

// Set and save
$product->update_meta_data('_my_custom_field', 'new value');
$product->save();
```

## Getting `$product` outside the loop

```php
// By ID
$product = wc_get_product( 1234 );

// By global on a single product page
global $product;

// In a loop callback (e.g. woocommerce_after_shop_loop_item)
add_action('woocommerce_after_shop_loop_item', function () {
    global $product;
    if (!$product) return;
    // $product is set by Woo before the loop iteration callback
});

// From an order item
$order = wc_get_order( $order_id );
foreach ($order->get_items() as $item) {
    $product = $item->get_product();
}
```

## Global `WC()` helper

`WC()` returns the main `WooCommerce` class instance. Most-used getters:

| Call | Returns |
|---|---|
| `WC()->cart` | `WC_Cart` — the current cart |
| `WC()->customer` | `WC_Customer` — current customer (logged-in or session) |
| `WC()->session` | `WC_Session` — guest session storage |
| `WC()->mailer()` | `WC_Emails` — email engine |
| `WC()->checkout()` | `WC_Checkout` — checkout state |
| `WC()->countries` | `WC_Countries` — country/state data |
| `wc_get_page_id( 'cart' )` | int — page ID for cart, checkout, etc. |
| `wc_get_cart_url()` | string |
| `wc_get_checkout_url()` | string |
| `wc_get_account_endpoint_url( 'orders' )` | string |

## Notices

WooCommerce has a built-in notice system used across cart, checkout, and account pages.

```php
// Add a notice (will show on the next page render)
wc_add_notice( 'Item added to cart.', 'success' );  // or 'error', 'notice'

// Display all queued notices immediately
wc_print_notices();

// Get notices without rendering
$notices = wc_get_notices( 'error' );

// Clear all notices
wc_clear_notices();

// Has any notices?
if ( wc_notice_count() > 0 ) { /* … */ }
```

In Etch markup, add a container where notices should render:

```html
<div class="woocommerce-notices-wrapper"></div>
```

Woo automatically populates this container on cart/checkout pages.

## Common combinations

### Show different content based on customer's history

```php
add_action('woocommerce_before_single_product', function () {
    if (!is_user_logged_in()) return;
    global $product;
    $user = wp_get_current_user();
    if (wc_customer_bought_product($user->user_email, $user->ID, $product->get_id())) {
        echo '<p class="repeat-buyer">Welcome back! You bought this before.</p>';
    }
});
```

### Hide the "Add to Cart" button for out-of-stock products in the loop

```php
add_filter('woocommerce_loop_add_to_cart_link', function ($html, $product) {
    if (!$product->is_in_stock()) {
        return '<span class="out-of-stock-tag">Out of stock</span>';
    }
    return $html;
}, 10, 2);
```

### Show free-shipping threshold note above the cart

```php
add_action('woocommerce_before_cart', function () {
    $threshold = 50;
    $subtotal = WC()->cart->get_subtotal();
    $diff = $threshold - $subtotal;
    if ($diff > 0 && WC()->cart->needs_shipping()) {
        printf(
            '<div class="free-shipping-note">Add <strong>%s</strong> more for free shipping.</div>',
            wc_price($diff)
        );
    }
});
```

## Sources

- WooCommerce — [Useful functions reference](https://github.com/woocommerce/woocommerce/blob/trunk/docs/code-snippets/useful-functions.md)
- Business Bloomer — [Get product data from `$product` object](https://www.businessbloomer.com/woocommerce-easily-get-product-info-title-sku-desc-product-object/)
- WooCommerce Developer Docs — [Conditional tags](https://developer.woocommerce.com/docs/code-snippets/conditional-tags/)
