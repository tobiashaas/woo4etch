# 04 — Cart

Classic cart page. Item list with quantity update, coupon code, cart totals, update button.

> **Note:** with WooCommerce ≥ 8, the **Cart block** is the default for new shops. This doc shows the **classic shortcode cart** because you're building custom HTML in Etch. If you want the block instead, switch the cart page back to `[woocommerce_cart]` in WooCommerce.

## When to use

- On the cart page (`/cart`).
- When cart markup needs to be controlled from Etch HTML.
- When classic form handling (update button, coupon apply) is preferred over block AJAX.

## Preparation

> **Etch context:** the cart is a **Page**, not a Template. There is no `{this.*}` product context. The pseudo-keys in the markup below (`{cart.*}`, `{cartItem.*}`) are **placeholders** — you have to wire them up to a custom data source (REST endpoint, custom field source, or by letting WooCommerce render the markup via shortcode and only theming around it). See [`10-etch-context-and-templates.md`](./10-etch-context-and-templates.md).

In WooCommerce settings, switch the cart page to the **classic shortcode**:

`WooCommerce → Settings → Advanced → Pages → Cart` → the page must contain `[woocommerce_cart]` (or render your own markup using the hooks in your theme).

## Etch HTML — Cart page

```html
<main id="main" class="site-main woocommerce">
  <h1 class="page-title">Cart</h1>

  <!-- Hook: woocommerce_before_cart -->

  <form class="woocommerce-cart-form"
        action="{cart.url}"
        method="post">

    <!-- Hook: woocommerce_before_cart_table -->

    <table class="shop_table shop_table_responsive cart woocommerce-cart-form__contents"
           cellspacing="0">
      <thead>
        <tr>
          <th class="product-remove" aria-label="Remove"></th>
          <th class="product-thumbnail" aria-label="Image"></th>
          <th class="product-name">Product</th>
          <th class="product-price">Price</th>
          <th class="product-quantity">Quantity</th>
          <th class="product-subtotal">Subtotal</th>
        </tr>
      </thead>

      <tbody>
        <!-- Hook: woocommerce_before_cart_contents -->

        {#loop cartItems as cartItem}
        <tr class="woocommerce-cart-form__cart-item cart_item">
          <td class="product-remove">
            <a href="{cartItem.removeUrl}"
               class="remove"
               aria-label="Remove {cartItem.title}"
               data-product_id="{cartItem.productId}"
               data-product_sku="{cartItem.sku}">×</a>
          </td>

          <td class="product-thumbnail">
            <a href="{cartItem.permalink}">
              <img src="{cartItem.image.url}"
                   alt="{cartItem.title}"
                   width="80" height="80">
            </a>
          </td>

          <td class="product-name" data-title="Product">
            <a href="{cartItem.permalink}">{cartItem.title}</a>
            <!-- Optional: variation details -->
            <dl class="variation">
              <dt>Size:</dt>
              <dd>{cartItem.variation.pa_size}</dd>
            </dl>
          </td>

          <td class="product-price" data-title="Price">
            <span class="woocommerce-Price-amount">{cartItem.price}</span>
          </td>

          <td class="product-quantity" data-title="Quantity">
            <div class="quantity">
              <label for="qty_{cartItem.key}" class="screen-reader-text">
                Quantity for {cartItem.title}
              </label>
              <input id="qty_{cartItem.key}"
                     class="input-text qty text"
                     type="number"
                     name="cart[{cartItem.key}][qty]"
                     value="{cartItem.quantity}"
                     min="0"
                     step="1"
                     inputmode="numeric"
                     aria-label="Product quantity">
            </div>
          </td>

          <td class="product-subtotal" data-title="Subtotal">
            <span class="woocommerce-Price-amount">{cartItem.subtotal}</span>
          </td>
        </tr>
        {/loop}

        <!-- Hook: woocommerce_cart_contents -->

        <tr>
          <td colspan="6" class="actions">
            <div class="coupon">
              <label for="coupon_code">Coupon code</label>
              <input type="text"
                     name="coupon_code"
                     id="coupon_code"
                     class="input-text"
                     placeholder="Coupon code">
              <button type="submit"
                      class="button"
                      name="apply_coupon"
                      value="Apply coupon">
                Apply coupon
              </button>

              <!-- Hook: woocommerce_cart_coupon -->
            </div>

            <button type="submit"
                    class="button"
                    name="update_cart"
                    value="Update cart">
              Update cart
            </button>

            <!-- Hook: woocommerce_cart_actions -->

            <input type="hidden" name="woocommerce-cart-nonce" value="{cart.nonce}">
          </td>
        </tr>

        <!-- Hook: woocommerce_after_cart_contents -->
      </tbody>
    </table>

    <!-- Hook: woocommerce_after_cart_table -->
  </form>

  <!-- Hook: woocommerce_before_cart_collaterals -->

  <div class="cart-collaterals">
    <!-- Hook: woocommerce_cart_collaterals (default: cart totals) -->

    <div class="cart_totals">
      <h2>Cart totals</h2>

      <table class="shop_table shop_table_responsive">
        <tbody>
          <tr class="cart-subtotal">
            <th>Subtotal</th>
            <td>{cart.subtotal}</td>
          </tr>

          {#loop activeCoupons as coupon}
          <tr class="cart-discount coupon-{coupon.code}">
            <th>Coupon: {coupon.code}</th>
            <td>−{coupon.amount}
              <a href="{coupon.removeUrl}" class="woocommerce-remove-coupon">[Remove]</a>
            </td>
          </tr>
          {/loop}

          <tr class="shipping">
            <th>Shipping</th>
            <td>{cart.shippingTotal}</td>
          </tr>

          <!-- Hook: woocommerce_cart_totals_before_order_total -->

          <tr class="order-total">
            <th>Total</th>
            <td><strong>{cart.total}</strong></td>
          </tr>

          <!-- Hook: woocommerce_cart_totals_after_order_total -->
        </tbody>
      </table>

      <div class="wc-proceed-to-checkout">
        <!-- Hook: woocommerce_proceed_to_checkout -->
        <a href="{checkout.url}"
           class="checkout-button button alt wc-forward">
          Proceed to checkout
        </a>
      </div>
    </div>
  </div>

  <!-- Hook: woocommerce_after_cart -->
</main>
```

## Required classes / attributes

| Element | Required | Why |
|---|---|---|
| `<form class="woocommerce-cart-form">` | yes | Woo looks for this form to process updates |
| `name="cart[<key>][qty]"` on the quantity input | yes | Server key pattern for update |
| `name="apply_coupon"` on the coupon button | yes | Woo recognises the coupon submit |
| `name="update_cart"` on the update button | yes | Triggers the quantity update |
| Hidden `woocommerce-cart-nonce` | yes | Security / CSRF — generate with `wp_create_nonce('woocommerce-cart')` |
| `.shop_table` table | recommended | Default styling + responsive markup |
| `data-title="…"` on `<td>`s | recommended | Used by responsive CSS as the mobile label |
| `<a class="remove">` with `?remove_item=` param | yes | Direct remove link, GET-based |
| `.cart_totals` wrapper | recommended | Default hook target |
| `.checkout-button` class | yes | Default styling + plugin hooks |

## Hooks used

### Wrapper hooks

| Hook | Position | Use |
|---|---|---|
| `woocommerce_before_cart` | Before `<form>` | Notices, empty-cart message |
| `woocommerce_after_cart` | After everything | Cross-sells |

### Cart form

| Hook | Position | Use |
|---|---|---|
| `woocommerce_before_cart_table` | Before `<table>` | Banner, shipping notes |
| `woocommerce_before_cart_contents` | Before first `<tr>` | Group headers |
| `woocommerce_cart_contents` | Inside the loop | Custom rows (e.g. free item) |
| `woocommerce_after_cart_contents` | After last item | Notices |
| `woocommerce_cart_coupon` | Inside actions block | Coupon hint |
| `woocommerce_cart_actions` | Inside actions block | More buttons (e.g. "Empty cart") |
| `woocommerce_after_cart_table` | After `</table>` | Trust badges |

### Cart totals

| Hook | Position | Use |
|---|---|---|
| `woocommerce_before_cart_collaterals` | Before cart totals | Cross-sells |
| `woocommerce_cart_collaterals` | Render slot | **Default: cart-totals** block |
| `woocommerce_cart_totals_before_order_total` | Before total | Tax breakdown |
| `woocommerce_cart_totals_after_order_total` | After total | "incl. VAT" note |
| `woocommerce_proceed_to_checkout` | Render slot | **Default: checkout button** |

## PHP layer

### Pass cart data to Etch

Etch normally gets standard items from the WP loop. The cart contents you need to inject yourself via a render hook or load via AJAX/REST. Minimal REST endpoint:

```php
add_action('rest_api_init', function () {
    register_rest_route('shop/v1', '/cart', [
        'methods'  => 'GET',
        'permission_callback' => '__return_true',
        'callback' => function () {
            $cart = WC()->cart;
            $items = [];
            foreach ($cart->get_cart() as $key => $item) {
                $product = $item['data'];
                $items[] = [
                    'key'        => $key,
                    'productId'  => $product->get_id(),
                    'title'      => $product->get_name(),
                    'permalink'  => $product->get_permalink(),
                    'image'      => ['url' => wp_get_attachment_url($product->get_image_id())],
                    'price'      => wc_price($product->get_price()),
                    'quantity'   => $item['quantity'],
                    'subtotal'   => wc_price($item['line_subtotal']),
                    'sku'        => $product->get_sku(),
                    'removeUrl'  => wc_get_cart_remove_url($key),
                    'variation'  => $item['variation'] ?? new stdClass(),
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

If you render cross-sells yourself:

```php
add_action('init', function () {
    remove_action('woocommerce_cart_collaterals', 'woocommerce_cross_sell_display');
});
```

### Custom note after the total

```php
add_action('woocommerce_cart_totals_after_order_total', function () {
    echo '<tr><td colspan="2" class="cart-note">All prices include VAT, plus shipping.</td></tr>';
});
```

### "Empty cart" button

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

### Recalculate shipping on quantity change

Classic cart updates via the update button work out of the box. For live update (without the update button):

```js
// jQuery-based because Woo's own cart scripts respond to it
jQuery(function ($) {
  $('form.woocommerce-cart-form').on('change', 'input.qty', function () {
    $('[name="update_cart"]').prop('disabled', false).trigger('click');
  });
});
```

## Common mistakes

- `name="cart[<key>][qty]"` replaced by custom names → update doesn't process quantities.
- Cart nonce missing or stale → update rejected with "security check failed".
- Coupon button not named `apply_coupon` → code isn't applied.
- `.remove` link without `wc_get_cart_remove_url($key)` (i.e. no nonce) → remove fails.
- Block cart active but custom shortcode markup rendered → double cart or blank page.
- Loop items output directly from `WC()->cart->get_cart()` without filters — for example missing `apply_filters('woocommerce_cart_item_visible', …)`, hidden items still show.

## Test checklist

- Change quantity + "Update cart" → subtotal/total are correct.
- Apply coupon → discount row appears, total decreases.
- Remove item → row disappears, mini-cart counter decreases.
- DevTools → Network: update submit includes `woocommerce-cart-nonce` as a form field.
- Mobile: table is responsive, `data-title` values appear as mobile labels.
- With empty cart: "Your cart is empty" message + link back to shop.
