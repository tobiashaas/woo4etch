# 15 — Woo4Etch Plugin

The **Woo4Etch** plugin in this repo (`/plugin/woo4etch/`) exposes WooCommerce PHP as shortcodes for [Etch](https://etchwp.com/?aff=06de86e5) templates, plus `includes/customizations.php` for your project's hooks and filters.

> Inspired by [Zack Pyle's](https://community.etchwp.com/u/3f0028c4) `[do_action]` snippet in the Etch community, extended with a curated set of higher-level shortcodes.

## Install

Use **one** install path — do not duplicate the package:

| Method | Path |
|---|---|
| **Regular plugin** | `wp-content/plugins/woo4etch/` → activate under **Plugins** |
| **MU-plugin** | `wp-content/mu-plugins/woo4etch/` → same folder, auto-loads |

1. Copy the `plugin/woo4etch/` folder from this repo to one of the paths above.
2. WooCommerce must be active.
3. PHP snippets from the templates → edit **`includes/customizations.php`** inside that folder.
4. Admin shortcode list: **Etch → Woo4Etch** (or **WooCommerce → Woo4Etch** without Etch).

## WooCommerce theme support (automatic)

WooCommerce expects the active theme to call `add_theme_support('woocommerce')`. If nothing does, WooCommerce runs an *"unsupported theme"* compatibility mode that wraps shop/product pages in its own markup and shows a *"theme does not declare WooCommerce support"* notice — which gets in the way of an Etch layout. The official Etch theme does **not** declare it (and asks you not to edit its `functions.php`), so **Woo4Etch declares it for you**.

- Runs on `after_setup_theme` at **priority 99**, so a theme or child theme that already declares support always wins — the plugin only fills the gap.
- Sets sensible image sizes (`thumbnail_image_width` 600, `single_image_width` 1200, a 3-column product grid).
- Does **not** enable Woo's gallery JS (zoom/lightbox/slider) by default — Etch layouts usually build their own gallery.

You normally don't touch this. To customise:

```php
// Turn it off entirely (declare support yourself instead)
add_filter('woo4etch/auto_theme_support', '__return_false');

// Change the image sizes / product grid
add_filter('woo4etch/theme_support_args', function ($args) {
    $args['single_image_width'] = 1600;
    return $args;
});

// Opt in to Woo's built-in product gallery JS
add_filter('woo4etch/gallery_features', function () {
    return ['wc-product-gallery-zoom', 'wc-product-gallery-lightbox', 'wc-product-gallery-slider'];
});
```

See the plain-language explanation in [`00-README.md`](./00-README.md#declare-woocommerce-support-in-the-theme).

## When to use the plugin vs. raw Etch

| Need | Approach |
|---|---|
| Show a product title, image, excerpt, content | **Etch Dynamic Keys** — `{this.title}` etc. |
| Loop over products in an archive | **Etch loops** — `{#loop mainQuery as item}` |
| Render the actual WooCommerce add-to-cart form | **Woo4Etch** — `[woo_add_to_cart]` |
| Fire a Woo hook so plugins can inject content | **Woo4Etch** — `[do_action hook="..."]` |
| Show the cart counter in the header | **Woo4Etch** — `[woo_cart_count]` (or use Woo fragments for live update) |
| Render product reviews | **Woo4Etch** — `[woo_review_form]` |
| Show formatted price with sale strikethrough | **Woo4Etch** — `[woo_price]` (Etch's `{this.meta._price}` gives raw value only) |
| Loop the product image gallery | **Etch loop** — `{#loop this.gallery_images as image}` (or **Woo4Etch** `[woo_gallery]`) |
| Show/hide by Woo context (is_cart, on_sale, …) | **Woo4Etch** — `[woo_if cond="..."]…[/woo_if]` |
| Product loop pagination on an archive | **Woo4Etch** — `[woo_pagination]` |
| Output a WooCommerce template part | **Woo4Etch** — `[woo_template name="single-product/related"]` |

## The generic `[do_action]` shortcode

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

`id` is optional everywhere — it falls back to the current product context (global `$product`, or the queried product on single-product pages). The full table with copy buttons lives in the admin under **Etch → Woo4Etch**.

### Product data

```text
[woo_title link="yes"]                          → linked product name
[woo_price id="123"]                            → formatted price (sale, "from")
[woo_regular_price]   [woo_sale_price]          → regular / sale price
[woo_price_amount]                              → raw number for itemprop="price"
[woo_sale_badge percentage="yes"]              → -25%  (or "Sale!")
[woo_sku default="N/A"]
[woo_stock format="label"]    → <span class="stock in-stock">In stock</span>
[woo_stock format="quantity"] → 7
[woo_stock format="status"]   → instock
[woo_weight default="—"]      [woo_dimensions default="—"]
[woo_meta key="_my_field" default="—"]
[woo_attribute name="pa_color"]
[woo_categories sep=", "]     [woo_tags sep=", "]
[woo_short_description]       [woo_description]
```

### Product media

```text
[woo_image size="woocommerce_single"]           → featured image
[woo_gallery size="woocommerce_thumbnail"]      → gallery (featured NOT included)
[woo_gallery include_featured="yes" link="yes"] → prepend featured + link to full size
```

The gallery shortcode mirrors the `gallery_images` Dynamic Key (see [`00-README.md`](./00-README.md#product-image-gallery--gallery_images)). For custom markup, prefer the Etch loop `{#loop this.gallery_images as image}`; reach for `[woo_gallery]` when you want Woo's standard image attributes server-side.

### Product UI

```text
[woo_add_to_cart id="123"]                    → full form
[woo_add_to_cart_url]                         → direct add-to-cart URL
[woo_quantity min="1" max="10" step="1"]      → just the quantity input
[woo_rating id="123"]                         → star rating HTML
[woo_review_form id="123"]                    → reviews + form
[woo_tabs]                                    → description / info / reviews tabs
[woo_related]     [woo_upsells]               → related / up-sell blocks
```

### Cart

```text
[woo_cart_count]    → <span class="kr-cart-count" data-count="3">3</span>
[woo_cart_total]
[woo_cart_url]      [woo_checkout_url]
[woo_mini_cart]     → mini-cart widget markup
[woo_cart_totals]            → totals block (subtotal, shipping, total)
[woo_coupon_form]            → "Have a coupon?" + apply form
[woo_shipping_calculator]    → cart shipping calculator
[woo_cross_sells]            → cross-sell products
```

### Account

```text
[woo_user field="first_name" default="friend"]
[woo_account_url endpoint="orders"]           → My Account / endpoint URL
[woo_logout_url]
[woo_account_menu]                            → account navigation menu
[woo_account_content]                         → current endpoint's content
[woo_login_form]                              → login form
[woo_order_details]                           → order table (thank-you / view-order)
```

Allowed `[woo_user]` fields: `display_name`, `user_login`, `user_email`, `first_name`, `last_name`, `ID`.

`[woo_account_content]` renders whatever the current My Account endpoint is (dashboard, orders, downloads, edit-account, view-order, payment-methods …), so a single account layout — `[woo_account_menu]` + `[woo_account_content]` — covers every sub-page. `[woo_order_details]` uses the current order on the thank-you/view-order endpoints; an explicit `order_id` is only honoured with ownership, the order `key`, or shop-manager rights.

### Store & archive

```text
[woo_shop_url]
[woo_breadcrumb]
[woo_result_count]        → "Showing 1–12 of 48 results"
[woo_catalog_ordering]    → sort-by dropdown
[woo_pagination]          → product loop pagination
[woo_product_search]      → product-only search form
[woo_notices]
```

`[woo_result_count]`, `[woo_catalog_ordering]`, and `[woo_pagination]` read the current loop, so place them on a shop/archive template where the main product query runs.

### Conditional rendering

```text
[woo_if cond="is_product"] … [/woo_if]
[woo_if cond="!is_cart"] shown everywhere except the cart [/woo_if]
[woo_if cond="on_sale"] [woo_sale_badge] [/woo_if]
[woo_if cond="is_product_category" arg="hoodies"] … [/woo_if]
```

Prefix the condition with `!` to negate. **Page conditionals:** `is_shop`, `is_product`, `is_cart`, `is_checkout`, `is_account_page`, `is_product_category`, `is_product_tag`, `is_product_taxonomy`, `is_wc_endpoint_url`, `is_woocommerce`, `is_user_logged_in`. **Product conditionals** (use the current/`id` product): `on_sale`, `in_stock`, `purchasable`, `featured`, `on_backorder`, `downloadable`, `virtual`, `sold_individually`, and `is_type` (with `arg="grouped|external|variable|simple"`).

### Template loader

```text
[woo_template name="single-product/related"]
[woo_template name="cart/cross-sells"]
```

### Native WooCommerce shortcodes (built-in)

These are registered by **WooCommerce itself** — Woo4Etch doesn't add them, but the admin reference lists them so every option is in one place. Use them for full pages where custom markup isn't needed:

```text
[woocommerce_cart]              [woocommerce_checkout]
[woocommerce_my_account]        [woocommerce_order_tracking]
[products limit="4" columns="4" on_sale="true"]
[product_page id="99"]          [product_categories number="12" parent="0"]
[add_to_cart id="99"]           [add_to_cart_url id="99"]
[shop_messages]
```

For the Cart, Checkout, and My Account areas the Woo4Etch templates ([`04-cart.md`](./04-cart.md), [`06-checkout.md`](./06-checkout.md), [`07-account.md`](./07-account.md)) use these native shortcodes as the baseline and layer custom markup/hooks around them.

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

### Product gallery (featured image + gallery loop)

`gallery_images` deliberately excludes the featured image, so render it first, then loop the gallery:

```html
<figure class="product__featured">
  <img src="{this.image.url}" alt="{this.title}">
</figure>

{#loop this.gallery_images as image}
  <figure class="product__gallery-item">
    <img src="{image.url}" alt="{image.alt}" srcset="{image.srcset}" loading="lazy">
  </figure>
{/loop}
```

Server-rendered alternative (Woo's standard image markup, featured image included):

```html
[woo_gallery include_featured="yes"]
```

### Conditional content by Woo context

```html
[woo_if cond="on_sale"]
  <span class="badge">[woo_sale_badge percentage="yes"]</span>
[/woo_if]

[woo_if cond="!in_stock"]
  <p class="backorder-note">Currently unavailable — [do_action hook="woo4etch_notify_form"]</p>
[/woo_if]
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

Put this snippet in `includes/customizations.php`:

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
