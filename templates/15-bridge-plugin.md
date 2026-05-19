# 15 — Woo4Etch Bridge Plugin

A small companion plugin shipped in this repo (`/plugin/woo4etch-bridge/`) that exposes WooCommerce PHP as shortcodes you can drop into Etch templates. The aim: cover everything Etch can't do natively, with the smallest possible surface.

> Inspired by [Zack Pyle's](https://community.etchwp.com/u/3f0028c4) `[do_action]` snippet in the Etch community, extended with a curated set of higher-level shortcodes.

## Install

1. Copy `plugin/woo4etch-bridge/` into `wp-content/plugins/`.
2. Activate.
3. Requires WooCommerce.

For dev convenience you can also `git clone` the repo directly into `wp-content/plugins/` and symlink just the plugin folder.

## When to use the plugin vs. raw Etch

| Need | Approach |
|---|---|
| Show a product title, image, excerpt, content | **Etch Dynamic Keys** — `{this.title}` etc. |
| Loop over products in an archive | **Etch loops** — `{#loop mainQuery as item}` |
| Render the actual WooCommerce add-to-cart form | **Bridge** — `[woo_add_to_cart]` |
| Fire a Woo hook so plugins can inject content | **Bridge** — `[do_action hook="..."]` |
| Show the cart counter in the header | **Bridge** — `[woo_cart_count]` (or use Woo fragments for live update) |
| Render product reviews | **Bridge** — `[woo_review_form]` |
| Show formatted price with sale strikethrough | **Bridge** — `[woo_price]` (Etch's `{this.meta._price}` gives raw value only) |
| Output a WooCommerce template part | **Bridge** — `[woo_template name="single-product/related"]` |

## The generic hook bridge

```text
[do_action hook="woocommerce_before_add_to_cart_button"]
```

This fires `do_action('woocommerce_before_add_to_cart_button')` at the exact spot in the rendered HTML. Anything other plugins (or your own snippets) have hooked into that action will render there.

With arguments:

```text
[do_action hook="woocommerce_thankyou" args="{this.id}"]
```

`args` is a comma-separated string list passed as positional arguments. Use Etch's Dynamic Keys to inject context-specific values like `{this.id}`.

### Restricting which hooks can be fired

By default any hook is allowed (it's a content-editor capability, and shortcodes can't be added by users without that capability). To harden further:

```php
// Only allow woo_* hooks
add_filter('woo4etch/allow_do_action', function ($allowed, $hook) {
    return strpos($hook, 'woocommerce_') === 0;
}, 10, 2);

// Or an explicit allowlist
add_filter('woo4etch/allow_do_action', function ($allowed, $hook) {
    return in_array($hook, [
        'woocommerce_before_add_to_cart_form',
        'woocommerce_after_add_to_cart_form',
        'woocommerce_after_shop_loop',
    ], true);
}, 10, 2);
```

## Shortcode quick reference

### Product

```text
[woo_price id="123"]
[woo_sku id="123" default="N/A"]
[woo_stock id="123" format="label"]      → <span class="stock in-stock">In stock</span>
[woo_stock id="123" format="quantity"]   → 7
[woo_stock id="123" format="status"]     → instock
[woo_meta id="123" key="_my_field" default="—"]
[woo_attribute id="123" name="pa_color"]
```

`id` is optional — falls back to the current product context (global `$product` or queried product on single-product pages).

### Product UI

```text
[woo_add_to_cart id="123"]                    → full form
[woo_quantity min="1" max="10" step="1"]      → just the quantity input
[woo_rating id="123"]                         → star rating HTML
[woo_review_form id="123"]                    → reviews + form
```

### Page

```text
[woo_notices]
[woo_breadcrumb]
```

### Cart

```text
[woo_cart_count]    → <span class="kr-cart-count" data-count="3">3</span>
[woo_cart_total]
[woo_cart_url]
```

### Customer

```text
[woo_user field="first_name" default="friend"]
```

Allowed fields: `display_name`, `user_login`, `user_email`, `first_name`, `last_name`, `ID`.

### Template loader

```text
[woo_template name="single-product/related"]
[woo_template name="cart/cross-sells"]
```

## Recipes

### Single product — fully functional buy box

```html
<section class="product__buy-box">
  <h1>{this.title}</h1>
  [woo_rating]
  <p class="price">[woo_price]</p>
  <p class="short">{this.excerpt}</p>

  [do_action hook="woocommerce_before_add_to_cart_form"]
  [woo_add_to_cart]
  [do_action hook="woocommerce_after_add_to_cart_form"]

  <p class="meta">SKU: [woo_sku default="—"]</p>
  [woo_stock]
</section>

<div class="notices">[woo_notices]</div>
```

### Header mini-cart link

```html
<a href="[woo_cart_url]" class="header-cart" aria-label="View cart">
  <svg>…</svg>
  [woo_cart_count]
</a>
```

For **live updates without reload**, also register a Woo fragment selector — see [`05-mini-cart.md`](./05-mini-cart.md).

### My Account welcome

```html
<h1>Hi, [woo_user field="first_name" default="there"] — welcome back.</h1>
<p>Email on file: [woo_user field="user_email"]</p>
```

### Trust block injected via hook

Put this snippet in your mu-plugin:

```php
add_action('woo4etch_trust_block', function () {
    echo '<ul class="trust-block">
            <li>Free shipping over €50</li>
            <li>Secure payment</li>
            <li>14-day returns</li>
          </ul>';
});
```

Then in Etch:

```html
[do_action hook="woo4etch_trust_block"]
```

You now have a clean, reusable "hook island" in Etch without touching WooCommerce internals.

### Render related products on a custom page

```html
<section class="related-products">
  <h2>You might also like</h2>
  [woo_template name="single-product/related"]
</section>
```

## Limitations

- **Shortcodes can't return JS-reactive markup directly.** If you need live updates on cart/account state, combine the bridge shortcodes with Woo Cart Fragments (see [`05-mini-cart.md`](./05-mini-cart.md)) or your own AJAX layer (see [`12-store-api-and-rest.md`](./12-store-api-and-rest.md)).
- **`[do_action]` runs synchronously during page render.** Avoid hooks that issue HTTP calls — they'll block the response.
- **The `args` attribute of `[do_action]` only passes strings.** PHP's loose typing usually does the right thing, but hooks expecting objects (e.g. `$order` instances) need a custom shortcode or PHP wrapper.

## Sources

- Zack Pyle — `[do_action]` snippet from the [Etch community](https://community.etchwp.com/c/general-discussion/woo-status)
- WooCommerce — [Shortcodes documentation](https://woocommerce.com/document/woocommerce-shortcodes/)
- WordPress — [Shortcode API](https://developer.wordpress.org/plugins/shortcodes/)
