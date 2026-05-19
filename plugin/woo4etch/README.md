# Woo4Etch

A small WordPress plugin that fills the WooCommerce gap in [Etch](https://etchwp.com/?aff=06de86e5). Drop shortcodes into Etch templates and pages wherever you need to invoke WooCommerce PHP — until Etch adds native Woo support.

Built on top of [Zack Pyle's](https://community.etchwp.com/u/3f0028c4) elegant `[do_action]` snippet from the Etch community, with a curated set of convenience shortcodes on top.

## Install

1. Copy the `woo4etch/` folder into `wp-content/plugins/` (or zip it and upload via `Plugins → Add New → Upload`).
2. Activate it (requires **WooCommerce** to be active).
3. In the WordPress admin, open **Etch → Woo4Etch** for a copy-paste overview of every shortcode (falls back to **WooCommerce → Woo4Etch** if Etch is not installed).

The plugin is ready to use: all shortcodes register on `plugins_loaded` once WooCommerce is available. Drop them into Etch HTML like any other shortcode.

## Shortcode reference

### Generic `[do_action]` shortcode

#### `[do_action hook="..." args="..."]`

Fires any WordPress / WooCommerce action hook in place.

```text
[do_action hook="woocommerce_before_add_to_cart_button"]
[do_action hook="woocommerce_thankyou" args="{this.id}"]
```

`args` is a comma-separated list of positional arguments passed to the hook. Most callbacks coerce strings to the right type via PHP's loose typing.

**Hardening:** restrict which hooks are allowed via filter:

```php
add_filter('woo4etch/allow_do_action', function ($allowed, $hook) {
    return strpos($hook, 'woocommerce_') === 0;
}, 10, 2);
```

### Product data

| Shortcode | Output | Notes |
|---|---|---|
| `[woo_price id="123"]` | Formatted price HTML | Uses Woo's own `get_price_html()` — handles sales, "from", etc. |
| `[woo_sku id="123" default="N/A"]` | Plain SKU string | |
| `[woo_stock id="123" format="label"]` | `<span class="stock in-stock">In stock</span>` | `format` accepts `label`, `status`, or `quantity` |
| `[woo_meta id="123" key="_my_field" default="—"]` | Any product meta value | Escaped as HTML |
| `[woo_attribute id="123" name="pa_color" default=""]` | Attribute value(s), comma-separated | Pass the taxonomy slug |

When `id` is omitted, the shortcode uses the **current product context** — global `$product` if set, otherwise the queried product on a single-product page.

### Product UI

| Shortcode | Output | Notes |
|---|---|---|
| `[woo_add_to_cart id="123"]` | Full add-to-cart form | Renders the proper template for the product type (simple / variable / grouped / external) |
| `[woo_quantity id="123" min="1" max="10" step="1" value="1"]` | Just the quantity input | Useful when you want to compose your own form |
| `[woo_rating id="123"]` | Star rating HTML | Empty if no reviews |
| `[woo_review_form id="123"]` | Product reviews + comment form | Only output if comments are open |

### Page-level

| Shortcode | Output |
|---|---|
| `[woo_notices]` | Renders queued cart/checkout notices |
| `[woo_breadcrumb]` | WooCommerce breadcrumb navigation |

### Cart state

| Shortcode | Output |
|---|---|
| `[woo_cart_count]` | `<span class="kr-cart-count" data-count="3">3</span>` |
| `[woo_cart_total]` | Formatted cart total |
| `[woo_cart_url]` | URL to the cart page |

### Customer

#### `[woo_user field="display_name" default=""]`

Outputs a field from the current user. Falls back to `default` for guests.

Allowed fields: `display_name`, `user_login`, `user_email`, `first_name`, `last_name`, `ID`.

### Template loader

#### `[woo_template name="..."]`

Loads any WooCommerce template part. Restricted character set, no path traversal.

```text
[woo_template name="single-product/related"]
[woo_template name="cart/cross-sells"]
```

## Examples

### Product card with rating and price

```html
<article class="product-card">
  <h3>{this.title}</h3>
  [woo_rating]
  <p class="price">[woo_price]</p>
  <p class="stock">[woo_stock format="label"]</p>
</article>
```

### Header with cart counter

```html
<header>
  <a href="[woo_cart_url]" class="cart-link">
    Cart [woo_cart_count]
  </a>
</header>
```

### Single product — full PHP form drop-in

```html
<section class="product-buy-box">
  [do_action hook="woocommerce_before_add_to_cart_form"]
  [woo_add_to_cart]
  [do_action hook="woocommerce_after_add_to_cart_form"]
</section>

<div class="notices">[woo_notices]</div>
```

### Account dashboard greeting

```html
<h1>Welcome back, [woo_user field="first_name" default="friend"]!</h1>
```

## How it differs from `wp_kses`-style shortcode plugins

Most "shortcode for WP" plugins try to be everything. Woo4Etch is intentionally small:

- One generic hook shortcode (`[do_action]`).
- A curated set of shortcodes for things you can't do in pure HTML with Etch's Dynamic Keys.
- No settings page. No admin UI. No bloat.

If you need a shortcode that isn't here, write a 3-line custom one in your theme or extend this plugin — it's MIT-licensed.

## License

[MIT License](../../LICENSE) — same as the parent **woo4etch** repository. Free to use, modify, and distribute; attribution required when redistributing substantial portions.
