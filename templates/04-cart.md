# 04 — Cart

Classic cart page. Item list with quantity update, coupon code, cart totals, update button.

> **Note:** with WooCommerce ≥ 8, the **Cart block** is the default for new shops. This doc shows the **classic shortcode cart** because you're building custom HTML in Etch. If you want the block instead, switch the cart page back to `[woocommerce_cart]` in WooCommerce.

## When to use

- On the cart page (`/cart`).
- When cart markup needs to be controlled from Etch HTML.
- When classic form handling (update button, coupon apply) is preferred over block AJAX.

## Preparation

> **Ready-made version (Woo4Etch 1.6.0+):** the plugin ships a complete cart layout built on real dynamic data (`{options.cart_items}` — no placeholder keys, previews live in the builder) with quantity update, coupon form + per-coupon discount lines, totals, cross-sells (in-stock only), notices and an empty-cart state. Install it via **Etch → Woo4Etch → Ready-made layouts → Add to page/template**. With the "Variation pills & quantity stepper" setting on, the line-item quantity fields additionally get −/+ stepper buttons; with **Store API cart interactions** on (default), every action updates in place without a page reload. The hand-built variant below remains the full-control alternative — the same interactions work there too, because the layer binds to Woo's field names, not to this layout.

> **Etch context:** the cart is a **Page**, not a Template. There is no `{this.*}` product context. All keys in the markup below (`{options.cart_items}`, `{options.cart_total}`, …) are **real Dynamic Keys** provided by the Woo4Etch cart bridge — they resolve live on the front end and show sample rows in the builder canvas. See [`10-etch-context-and-templates.md`](./10-etch-context-and-templates.md) for the context model and [`15-woo4etch-plugin.md`](./15-woo4etch-plugin.md) for every key the bridge exposes.

In WooCommerce settings, switch the cart page to the **classic shortcode**:

`WooCommerce → Settings → Advanced → Pages → Cart` → the page must contain `[woocommerce_cart]` (or render your own markup using the hooks in your theme).

## Etch HTML — Cart page

```html
<!-- data-w4e-cart-region marks the block the Store API layer live-updates
     after add/update/remove/coupon — see "Interaction layer" below.
     Optional: without any marker the plugin falls back to .w4e-cart. -->
<main id="main" class="site-main woocommerce" data-w4e-cart-region="cart">
  <h1 class="page-title">Cart</h1>

  <!-- Woo feedback ("Cart updated.", coupon + security errors). Without a
       notices output, a failed update looks like nothing happened.
       format="plain" renders minimal .w4e-notice markup styleable in Etch
       (Woo4Etch plugin — see 15-woo4etch-plugin.md). -->
  [woo_notices format="plain"]

  <!-- Empty-cart state. {options.cart_is_empty} / {options.shop_url} come
       from the Woo4Etch cart bridge (15-woo4etch-plugin.md). Without this
       branch, coupon, totals and the checkout button render on an empty cart. -->
  {#if options.cart_is_empty}
  <p class="cart-empty woocommerce-info">Your cart is currently empty.</p>
  <p class="return-to-shop">
    <a class="button wc-backward" href="{options.shop_url}">Return to shop</a>
  </p>
  {/if}

  {#if !options.cart_is_empty}

  <!-- Hook: woocommerce_before_cart -->

  <form class="woocommerce-cart-form"
        action="{options.cart_url}"
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

        {#loop options.cart_items as item}
        <tr class="woocommerce-cart-form__cart-item cart_item">
          <td class="product-remove">
            <a href="{item.remove_url}"
               class="remove"
               aria-label="Remove {item.name}"
               data-product_id="{item.id}"
               data-product_sku="{item.sku}">×</a>
          </td>

          <td class="product-thumbnail">
            <a href="{item.permalink}">
              <img src="{item.image}"
                   alt="{item.name}"
                   width="80" height="80">
            </a>
          </td>

          <td class="product-name" data-title="Product">
            <a href="{item.permalink}">{item.name}</a>
            <!-- Variation / add-on attributes as one flat string,
                 e.g. "Color: Blue, Size: M" — empty for simple products. -->
            <span class="variation">{item.meta}</span>
          </td>

          <td class="product-price" data-title="Price">
            <span class="woocommerce-Price-amount">{item.price}</span>
          </td>

          <td class="product-quantity" data-title="Quantity">
            <div class="quantity">
              <label for="qty_{item.key}" class="screen-reader-text">
                Quantity for {item.name}
              </label>
              <input id="qty_{item.key}"
                     class="input-text qty text"
                     type="number"
                     name="cart[{item.key}][qty]"
                     value="{item.quantity}"
                     min="0"
                     step="1"
                     inputmode="numeric"
                     aria-label="Product quantity">
            </div>
          </td>

          <td class="product-subtotal" data-title="Subtotal">
            <span class="woocommerce-Price-amount">{item.subtotal}</span>
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

            <input type="hidden" name="woocommerce-cart-nonce" value="{options.cart_nonce}">
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
            <td>{options.cart_subtotal}</td>
          </tr>

          <!-- One row per applied coupon; renders nothing when no coupon is
               active. The remove link is a plain Woo ?remove_coupon= GET —
               works without JS, and the Store API layer upgrades it in place. -->
          {#loop options.cart_coupons as coupon}
          <tr class="cart-discount">
            <th>Coupon: {coupon.code}</th>
            <td>−{coupon.amount}
              <a href="{coupon.remove_url}" class="woocommerce-remove-coupon">[Remove]</a>
            </td>
          </tr>
          {/loop}

          <tr class="shipping">
            <th>Shipping</th>
            <td>{options.cart_shipping_total}</td>
          </tr>

          <!-- Hook: woocommerce_cart_totals_before_order_total -->

          <tr class="order-total">
            <th>Total</th>
            <td><strong>{options.cart_total}</strong></td>
          </tr>

          <!-- Hook: woocommerce_cart_totals_after_order_total -->
        </tbody>
      </table>

      <div class="wc-proceed-to-checkout">
        <!-- Hook: woocommerce_proceed_to_checkout -->
        <a href="{options.checkout_url}"
           class="checkout-button button alt wc-forward">
          Proceed to checkout
        </a>
      </div>
    </div>
  </div>

  {/if}

  <!-- Hook: woocommerce_after_cart -->
</main>
```

## Required classes / attributes

| Element | Required | Why |
|---|---|---|
| `<form class="woocommerce-cart-form">` | yes | Woo looks for this form to process updates |
| `.cart_item` on every line-item row | yes | Woo's cart.js **disables the update button until an input inside `.woocommerce-cart-form .cart_item` fires a change** — without this class on your rows, "Update cart" stays disabled forever |
| `name="cart[<key>][qty]"` on the quantity input | yes | Server key pattern for update |
| `name="apply_coupon"` on the coupon button | yes | Woo recognises the coupon submit |
| `name="update_cart"` on the update button | yes | Triggers the quantity update |
| Hidden `woocommerce-cart-nonce` | yes | Security / CSRF — generate with `wp_create_nonce('woocommerce-cart')` |
| `.shop_table` table | recommended | Default styling + responsive markup |
| `data-title="…"` on `<td>`s | recommended | Used by responsive CSS as the mobile label |
| `<a class="remove">` with `?remove_item=` param | yes | Direct remove link, GET-based |
| `.cart_totals` wrapper | recommended | Default hook target |
| `.checkout-button` class | yes | Default styling + plugin hooks |
| `data-w4e-cart-region="cart"` on the wrapper | recommended | Marks what the Store API layer live-updates (see below) |

## Interaction layer — WooCommerce Store API (Woo4Etch 1.7.0+)

By default the markup above works **classically**: every quantity update, coupon
apply and remove is a full-page POST/GET handled by WooCommerce's form handlers.
That never breaks — but it reloads the page on every click.

With the Woo4Etch plugin active (setting **Store API cart interactions**, on by
default), the same markup is upgraded in place:

- **Writes go through WooCommerce's Store API** (`/wc/store/v1/cart/*` —
  `add-item`, `update-item`, `remove-item`, `apply-coupon`, `remove-coupon`).
  That puts every action under Woo's own validation, stock checks, JSON error
  messages, and Store API rate limiting. No custom endpoints.
- **Reads stay server-rendered Etch HTML.** After each write the plugin
  refetches the current page and swaps every `[data-w4e-cart-region]` element
  (fallback: `.w4e-cart`, `.w4e-minicart`) with the freshly rendered version.
  The plugin never renders cart markup in JavaScript — whatever you build in
  Etch is exactly what re-renders. This is the flagship principle: your markup
  stays yours.
- **No new markup contract.** The layer binds to the same Woo names this doc
  already requires: `cart[<key>][qty]` inputs, `?remove_item=` /
  `?remove_coupon=` links, `apply_coupon` buttons, `form.cart` submits. Turn
  the plugin (or JS) off and everything falls back to the classic POST flow.
- **Events for your own scripts:** every successful write dispatches
  `woo4etch:cart-updated` on `document` (with `detail.cart` = the Store API
  cart JSON), and triggers Woo's `wc_fragment_refresh` when a fragment-based
  third-party mini-cart is present.

```js
// React to live cart changes anywhere (analytics, badges, custom UI):
document.addEventListener('woo4etch:cart-updated', function (e) {
  console.log('items now:', e.detail.cart ? e.detail.cart.items_count : '?');
});
```

Third-party compatibility: an add-to-cart form is only intercepted when it
contains exclusively Woo's own field names. Any extra named input (product
add-ons, gift wrap, …) means a plugin collects custom item data through the
classic `woocommerce_add_cart_item_data` path — that form submits classically
so nothing is lost. Details in
[`12-store-api-and-rest.md`](./12-store-api-and-rest.md).

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

### Remove the duplicate Gutenberg page title

On the page WooCommerce assigns as Cart, the block templates (`woocommerce/page-content-wrapper`) auto-render a `<h1 class="wp-block-post-title">` above your content. With your own `<h1>` in the Etch layout the heading appears twice — and the Gutenberg one is not visible/editable in Etch. Suppress it in `customizations.php`:

```php
// Drop the auto-rendered post title on Woo pages (cart/checkout/account) —
// the Etch layout brings its own <h1>.
add_filter('render_block', function ($content, $block) {
    if (($block['blockName'] ?? '') === 'core/post-title'
        && function_exists('is_cart')
        && (is_cart() || is_checkout() || is_account_page())) {
        return '';
    }
    return $content;
}, 10, 2);
```

### Reshape the cart data

The cart Dynamic Keys come from the Woo4Etch bridge — there is nothing to
register yourself. To adjust what the keys contain (add a field, filter noisy
third-party meta, change the image size), use the bridge filters in
`customizations.php`:

```php
// Add a per-item field, e.g. the product's brand:
add_filter('woo4etch/cart_item_payload', function ($payload, $cart_item, $product) {
    $payload['brand'] = strip_tags(wc_get_product_tag_list($product->get_id()));
    return $payload;
}, 10, 3);

// Reshape the whole option set (e.g. inject a custom total line):
add_filter('woo4etch/cart_data', function ($data) {
    $data['free_shipping_gap'] = ''; // your calculation here
    return $data;
});
```

If you need cart JSON in your own scripts, don't build a custom endpoint —
WooCommerce's Store API already serves it at `GET /wp-json/wc/store/v1/cart`
(see [`12-store-api-and-rest.md`](./12-store-api-and-rest.md)).

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

### Live update on quantity change

Nothing to add — with the Woo4Etch plugin active, quantity changes update the
cart through the Store API automatically (see "Interaction layer" above); a
quantity of 0 removes the line. Keep the "Update cart" button in the markup
anyway: it is the no-JS fallback, and stays the only path when the **Store API
cart interactions** setting is off. Without the plugin, this classic snippet
auto-submits the form instead:

```js
// jQuery-based because Woo's own cart scripts respond to it
jQuery(function ($) {
  $('form.woocommerce-cart-form').on('change', 'input.qty', function () {
    $('[name="update_cart"]').prop('disabled', false).trigger('click');
  });
});
```

## Common mistakes

- **No notices output** → every cart action fails *silently*. "Cart updated.", coupon errors, and the nonce error ("security check failed") all arrive as Woo notices — without `[woo_notices]` in the layout an update that does nothing and an update that was rejected look identical. Add the notices block first when debugging "update cart does nothing".
- **No empty-cart branch** → coupon field, totals, and the checkout button render even when the cart is empty; only the item rows disappear. Wrap the form + collaterals in `{#if !options.cart_is_empty}` and show the empty message in the inverse condition (see the markup above).
- Duplicate `<h1>` on the cart page — Gutenberg's `wp-block-post-title` renders above your Etch layout. Remove it with the `render_block` snippet in the PHP layer above.
- **Old placeholder keys** (`{cartItem.title}`, `{cart.subtotal}` from pre-1.4 versions of this doc) render as literal text — the real keys are `{item.*}` inside a `{#loop options.cart_items as item}` loop and `{options.cart_*}` outside it.
- **No coupon rows in the totals** → an applied coupon changes the total with no visible explanation and no way to remove it. Loop `{options.cart_coupons}` (renders nothing when no coupon is active).
- `name="cart[<key>][qty]"` replaced by custom names → update doesn't process quantities (classic **and** Store API path — the key comes from that name).
- Cart nonce missing or stale → update rejected with "security check failed".
- Coupon button not named `apply_coupon` → code isn't applied.
- `.remove` link without `wc_get_cart_remove_url($key)` (i.e. no nonce) → remove fails.
- Block cart active but custom shortcode markup rendered → double cart or blank page.
- Loop items output directly from `WC()->cart->get_cart()` without filters — for example missing `apply_filters('woocommerce_cart_item_visible', …)`, hidden items still show.

## Test checklist

- Change quantity + "Update cart" → subtotal/total are correct.
- With the plugin active: change a quantity → totals update **without a page reload** (watch Network: a `POST /wc/store/v1/cart/update-item`, then a refetch of the page).
- Apply coupon → discount row appears, total decreases; "Remove" link on the coupon row restores the total.
- Remove item → row disappears, mini-cart counter decreases.
- Disable JavaScript (or the "Store API cart interactions" setting) → every action still works as a classic form POST / GET link.
- DevTools → Network: update submit includes `woocommerce-cart-nonce` as a form field.
- "Update cart" appears to do nothing → check in the same Network request that the POST body contains `update_cart` (non-empty value), a real `cart[<hash>][qty]` field (the `<hash>` interpolated, not a literal `{item.key}`), and the nonce — then check the response: a `302` to the cart URL means Woo processed it; a `200` re-render without notices means the nonce or field names didn't reach the handler.
- After update/coupon: a notice appears (requires the `[woo_notices]` block in the layout).
- Mobile: table is responsive, `data-title` values appear as mobile labels.
- With empty cart: "Your cart is empty" message + link back to shop.
