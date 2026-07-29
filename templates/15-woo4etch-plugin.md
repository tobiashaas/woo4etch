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
- Does **not** enable Woo's gallery JS (zoom/lightbox/slider) by default — Etch layouts usually build their own gallery. Opt in via the checkbox **Woo4Etch → Settings → Enable WooCommerce gallery scripts** or the `woo4etch/gallery_features` filter; the plugin then also *enqueues* the scripts on product pages, because WooCommerce itself never loads them on block themes (like Etch's) even with the theme supports declared.

You normally don't touch this. To customise:

```php
// Turn it off entirely (declare support yourself instead)
add_filter('woo4etch/auto_theme_support', '__return_false');

// Change the image sizes / product grid
add_filter('woo4etch/theme_support_args', function ($args) {
    $args['single_image_width'] = 1600;
    return $args;
});

// Opt in to Woo's built-in product gallery JS (or just use the Settings
// checkbox; the filter receives the checkbox result and wins either way —
// handy to enable only some features, e.g. zoom + lightbox without slider)
add_filter('woo4etch/gallery_features', function () {
    return ['wc-product-gallery-zoom', 'wc-product-gallery-lightbox', 'wc-product-gallery-slider'];
});
```

The gallery scripts initialise on Woo's gallery classes — use `[woo_gallery mode="woo"]` or the hand-written markup variant in [`01-single-product-simple.md`](./01-single-product-simple.md#gallery-variant--woocommerce-zoom-lightbox--thumbnail-slider).

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
| Show the formatted price, stock label, rating, sale state | **Etch Dynamic Keys via Woo4Etch** — `{this.price}`, `{this.stock_label}`, `{this.is_on_sale}` … (see [Product fields](#product-fields-as-etch-dynamic-data)); `[woo_price]` if you want Woo's `<del>/<ins>` strikethrough markup in place |
| Loop the product image gallery | **Etch loop** — `{#loop this.gallery_images as image}` (or **Woo4Etch** `[woo_gallery]`) |
| Show/hide by Woo context (is_cart, on_sale, …) | **Woo4Etch** — `[woo_if cond="..."]…[/woo_if]` |
| Product loop pagination on an archive | **Woo4Etch** — `[woo_pagination]` |
| Output a WooCommerce template part | **Woo4Etch** — `[woo_template name="single-product/related"]` |

## Settings (admin checkbox)

Under **Etch → Woo4Etch → Settings**:

- **Disable WooCommerce default styles** — removes all three Woo stylesheets (`woocommerce-layout`, `woocommerce-smallscreen`, `woocommerce-general`) so your Etch styles start from a blank slate: no specificity fights, no `!important`. Uncheck to bring the Woo styling back at any time. Payment gateways and some extensions enqueue their own CSS and are not affected. Developers can override programmatically: `add_filter('woo4etch/disable_woo_styles', '__return_true');` (the filter wins over the checkbox).
- **Variation pills & quantity stepper** — enables `assets/pills.js` on single product pages: the native attribute `<select>`s become accessible pill buttons and every `.quantity input.qty` gets a −/+ stepper, with zero extra markup. Woo's variation JS stays leading (a pill click sets the native select and fires its `change` event). Off by default; filter: `woo4etch/enqueue_pills`. Details + companion buy-box CSS in [`02-single-product-variable.md`](./02-single-product-variable.md#zero-markup-alternative-auto-built-pills--quantity-stepper-pillsjs).

## Built-in frontend behaviours (no markup)

These ship with the plugin and never output HTML — they only support your own Etch markup:

| Behaviour | Trigger in your markup | Filters |
|---|---|---|
| **Buy-now → checkout redirect** | a submit button `name="buy_now"` inside `form.cart` (see [`16-one-click-checkout.md`](./16-one-click-checkout.md)) | `woo4etch/enable_buy_now` (default on), `woo4etch/buy_now_empty_cart` (default off) |
| **Variation swatch sync** | clickable elements with `data-w4e-swatch`, `data-attribute`, `data-value` (see [`02-single-product-variable.md`](./02-single-product-variable.md#variation-swatches-color-blobs--image-previews)) | `woo4etch/enqueue_swatches` (default: product pages) |
| **Variation pills + qty stepper** | none — auto-builds from the native selects when the Settings checkbox is on (the inverse of the swatch sync: zero markup vs. full markup control; both drive the same native `change` event); also builds steppers on cart quantity fields (`cart[<key>][qty]`) and yields automatically when a dedicated swatch plugin owns the selects | `woo4etch/enqueue_pills` (default: off) |
| **Price-range slider** | a form containing `min_price` + `max_price` inputs on a product archive (WooCommerce's native filter params) — enhanced into a dual-handle slider synced with the fields; bound from `data-w4e-price-max` (see [`03-product-archive.md`](./03-product-archive.md#filter-sidebar--native-woocommerce-filtering-no-plugin)) | `woo4etch/enqueue_price_slider` (default: shop/taxonomy archives) |
| **Archive filters for Etch loops** | WooCommerce's native `?min_price` / `?filter_<attribute>` params — Woo applies them to the main query only; the plugin re-applies them to Etch's main-query loop (a secondary query) on shop/category/tag pages | `woo4etch/filter_secondary_product_queries` (default: on) |

## Ready-made layouts (one-click page install)

Under **Etch → Woo4Etch → Ready-made layouts** the plugin ships complete, editable Etch layouts for every shop area — cart, single product, shop archive (working filter sidebar + category slider), category archive (SEO intro + `{term.description}`), header mini-cart (hover dropdown with empty state), My Account (login gate for guests), thank-you and the Woo notices region. All of them are built on the dynamic-data bridges (no shortcodes except where real Woo PHP is required), so they render live in the builder canvas.

Two ways to use them:

- **Add to page/template** — installs the layout straight where it renders: WooCommerce's assigned page (cart, account — from WooCommerce → Settings → Advanced) or the Etch `wp_template` for the area (`archive-product`, `single-product`, `order-confirmation`; created bare when missing). Strictly **append-only**: existing content is preserved, and a target that already contains the layout is refused instead of double-inserted ("On its page ✓"). The layout's classes are merged into Etch's style system; **existing styles with the same selector are reused, never overwritten**, so installing won't fight your design system.
- **Copy JSON** — puts the layout on your clipboard in Etch's native copy/paste format; paste straight onto the canvas. The same files live in [`templates/etch-copy/`](./etch-copy/README.md) for use without wp-admin access.

The **Woo notices** row additionally offers **Install as component**: the notices region as a real Etch component (one globally editable definition — place instances from the builder's component library; reinstalling updates it in place).

The styling is intentionally plain (neutral grays, rounded cards) — adjust the `w4e-*` classes in Etch's CSS panel or wire them to your design tokens. Mini-cart has no automatic target (it lives in your site header) — paste it there.

## Third-party WooCommerce plugins

Hand-written Etch layouts change *where markup comes from*, not Woo's server behaviour — most plugins keep working, but they integrate through four different seams with different outcomes (all verified against real plugins):

1. **Classic hook output — works via the hook islands.** Plugins that `add_action` on Woo's standard hooks render wherever you place the matching island. Verified with **WooCommerce Germanized**: unit price, tax/shipping notices and delivery time all render inside `<div data-w4e-hook="woocommerce_single_product_summary" data-w4e-skip-defaults="1" data-w4e-product="{this.id}">` (the ready-made single-product layout ships this "summary extras" island). Note the plugin disables WooCommerce's block-hook compatibility layer (`woo4etch/disable_block_hook_compatibility`), because it would strip classic-hook callbacks on block themes before the islands can fire them.
2. **Checkout / payment / legal — untouched.** Checkout stays native Woo (shortcode or block), so payment gateways (verified: **Mollie**, 4 methods) and legal machinery (verified: **Germanized** checkbox + "Buy Now" button) work exactly as on any site. Same for emails and order processing.
3. **The blockified-detection trap.** Some plugins detect block themes and then inject via `woocommerce/*` **block render filters** instead of classic hooks — hand-written Etch layouts contain no Woo blocks, so nothing appears (typical detection: no `woocommerce/legacy-template` block in the template → assume blockified). Verified with **YITH Wishlist**: the button silently didn't render. Escape hatch: virtually all of these plugins offer a **shortcode or a placement setting** — set YITH's button position to "shortcode" and drop `[yith_wcwl_add_to_wishlist]` into your layout where you want it (shortcodes run everywhere in Etch). Explicit placement fits this approach anyway.
4. **Same-territory UI plugins.** A dedicated variation-swatch plugin owns the attribute selects; running the Woo4Etch pills on top would double the UI. `pills.js` detects foreign swatch UIs (e.g. **Variation Swatches for WooCommerce**) and backs off automatically for those selects — the quantity stepper keeps working. If you use such a plugin site-wide, simply leave the pills setting off.

Rule of thumb: if a plugin's output is missing, first place its **shortcode** in the layout; if it has none, place a hook island (`data-w4e-hook`) where its `add_action` target fires.

## Product fields as Etch dynamic data

On `product` posts, Woo4Etch enriches Etch's post data (the same seam Etch's own integration uses for `gallery_images`), so the most-needed product fields are **real Dynamic Keys** — they render live in the builder canvas and need no shortcode. In a Single template use `{this.*}`; inside a loop use `{item.*}`.

```html
<p class="price">
  <span itemprop="price" content="{this.price_amount}">{this.price}</span>
</p>
{#if this.is_on_sale}
  <span class="badge badge--sale">-{this.sale_percentage}%</span>
{/if}
<p class="stock stock--{this.stock_status}">{this.stock_label}</p>
```

| Key | Value |
|---|---|
| `{this.price}` | formatted price, plain text (sale range, variable "from") |
| `{this.regular_price}` / `{this.sale_price}` | formatted; empty for variable products |
| `{this.price_html}` | Woo's price markup incl. `<del>/<ins>` — **Raw-HTML blocks only** (text blocks escape HTML) |
| `{this.price_amount}` | raw decimal for `itemprop`/schema |
| `{this.currency_symbol}` | active currency symbol |
| `{this.is_on_sale}` | boolean — for Etch condition blocks |
| `{this.sale_percentage}` | integer discount (cheapest variation for variables); `0` when not on sale |
| `{this.sku}` | product SKU |
| `{this.product_type}` | **Caution:** on product posts Etch itself sets this key to the WooCommerce product_type *taxonomy term object*, and Etch keys win — use `{this.product_type.name}` for the type string (`simple` \| `variable` \| …); plain `{this.product_type}` comparisons in conditions silently fail |
| `{this.is_simple}` | bool — condition-safe type check: `{#if this.is_simple}` (use this instead of comparing `product_type`) |
| `{this.stock_status}` | `instock` \| `outofstock` \| `onbackorder` — handy as a CSS-class suffix |
| `{this.stock_label}` | localized availability text (can be empty for in-stock products, per Woo inventory settings) |
| `{this.stock_quantity}` | number, or empty when stock management is off |
| `{this.is_in_stock}` / `{this.is_purchasable}` / `{this.is_featured}` | booleans |
| `{this.is_sold_individually}` | boolean — one-per-order products; wrap the quantity input in `{#if !this.is_sold_individually}` |
| `{this.rating}` / `{this.rating_count}` / `{this.review_count}` | average rating + counts |
| `{this.add_to_cart_url}` / `{this.add_to_cart_text}` | direct add-to-cart URL + localized button label |
| `{this.weight}` / `{this.dimensions}` | formatted, empty when not set |
| `{this.upsell_ids}` | array of product IDs (e.g. `{this.upsell_ids|join(',')}`) |

**Notes:**

- The raw meta keys (`{this.meta._price}`, `{this.meta._sku}`, …) still work — the new keys are formatted/derived conveniences on top. There is no `is_on_sale` meta in WooCommerce; before this bridge, sale state required comparing `_sale_price` manually.
- Keys Etch sets itself are never overwritten; if Etch ships a same-named key later, Etch wins.
- Disable: `add_filter('woo4etch/expose_product_data', '__return_false');` — extend/reshape: `woo4etch/product_data` filter (receives the payload and the `WC_Product`).
- The add-to-cart **form** stays hand-written markup or `[woo_add_to_cart]` — these keys are display data, not the form.

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

### Hook markers — when `[do_action]` output gets stripped

`[do_action]` works wherever shortcodes run, **but** in Etch raw-html blocks the output passes through Etch's sanitizer, which strips `<form>`, `<input>`, `<select>` and `<script>` unless the off-by-default Etch setting "allow unsafe raw HTML" is on. Hooks whose callbacks emit exactly that markup (express-pay buttons, forms, widgets) come out broken — and look like "the hook doesn't work".

The marker variant sidesteps this (Woo4Etch 1.6.0+): place an **empty element** with `data-w4e-hook`, and the plugin fills it with the captured `do_action()` output *after* Etch has rendered — the sanitizer never sees it:

```html
<div data-w4e-hook="woocommerce_after_add_to_cart_button"
     data-w4e-product="{this.id}"></div>
```

`data-w4e-product` (optional) sets the global `$product` while the hook fires, so product-aware callbacks work. The same `woo4etch/allow_do_action` filter applies. The ready-made single-product layout uses these markers around its add-to-cart form. The same mechanism powers `data-w4e-add-to-cart="{this.id}"` (the full native add-to-cart form, see [`02-single-product-variable.md`](./02-single-product-variable.md)).

**`data-w4e-skip-defaults`** — for hooks where WooCommerce core renders its own templates. The classic case is `woocommerce_single_product_summary`: plugins like **Germanized** attach their extras there (unit price at 11, tax/shipping notices at 12, delivery time at 27), *between* core's title (5), price (10), excerpt (20) and add-to-cart (30) callbacks. Firing the hook plainly would duplicate everything your layout already renders. With skip-defaults the core callbacks are unhooked for this one call (and restored afterwards) — only the third-party extras render:

```html
<!-- After your price row: Germanized legal info, delivery time, etc. -->
<div data-w4e-hook="woocommerce_single_product_summary"
     data-w4e-skip-defaults="1"
     data-w4e-product="{this.id}"></div>
```

Which callbacks count as "core defaults" per hook is filterable via `woo4etch/hook_core_defaults`.

> **Why hooks used to vanish entirely on block themes:** WooCommerce's *block-template compatibility layer* strips the callbacks from the classic product/shop hooks while rendering block templates and re-injects them only around `woocommerce/*` blocks — which Etch layouts don't contain. Even `[do_action]`/`data-w4e-hook` then fired into emptied hooks. Woo4Etch disables that layer on block themes (Woo's official `woocommerce_disable_compatibility_layer` switch); re-enable it via `add_filter('woo4etch/disable_block_hook_compatibility', '__return_false')` if your Etch templates embed WooCommerce product blocks that rely on it.

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
[woo_product_attributes]      → full attributes table ("Additional information":
                                visible attributes + weight/dimensions; empty
                                output when the product has no data — ship the
                                surrounding heading conditionally)
[woo_categories sep=", "]     [woo_tags sep=", "]
[woo_short_description]       [woo_description]
```

### Product media

```text
[woo_image size="woocommerce_single"]           → featured image
[woo_gallery size="woocommerce_thumbnail"]      → gallery (featured NOT included)
[woo_gallery include_featured="yes" link="yes"] → prepend featured + link to full size
[woo_gallery mode="woo" columns="4"]            → Woo-native markup (featured first) for
                                                  Woo's zoom/lightbox/slider scripts
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
[woo_cart_items]             → line items, your own markup (qty update + remove)
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

### Custom cart page (your layout, complete & hook-compatible)

> **Shortcut:** the whole cart below is available as a ready-to-paste Etch snippet — copy [`etch-copy/cart.json`](./etch-copy/cart.json) and paste it into the Etch builder (styled, with all the dynamic-data bindings). See [`etch-copy/README.md`](./etch-copy/README.md).

The cart lives in `WC()->cart` (runtime state + AJAX), not in `{this.*}` Dynamic Keys — so the cart contents come from shortcodes. But you build the **layout** in Etch (section/container, columns), and `[woo_cart_items]` renders a **complete, extension-compatible** cart form with your own class-based markup instead of the monolithic `[woocommerce_cart]`.

```html
<section data-etch-element="section" class="cart">
  <div data-etch-element="container">
    <h1>{this.title}</h1>
    <div class="cart-layout">
      <div class="cart-main">
        [woo_cart_items]      <!-- items + coupon + update + ALL cart hooks -->
      </div>
      <aside class="cart-summary">
        <h2>Order summary</h2>
        [woo_cart_totals]     <!-- subtotal, shipping, total, checkout (+ hooks) -->
      </aside>
    </div>
    [woo_cross_sells]         <!-- "You may also like" -->
  </div>
</section>
```

`[woo_cart_items]` is a faithful reproduction of WooCommerce's `cart.php`: it outputs `.woo-cart-item` rows (thumb, name, price, qty input, subtotal, remove), the **coupon field**, the **Update cart** button and nonce — and it **fires every cart hook and per-item filter**, so quantity update, removal, coupons and third-party cart plugins all work. No AJAX required (classic submit). It also fires `woocommerce_before_cart`, which prints cart notices for you.

**Hooks fired by `[woo_cart_items]`** (so extensions keep working): `woocommerce_before_cart`, `woocommerce_before_cart_table`, `woocommerce_before_cart_contents`, `woocommerce_after_cart_item_name`, `woocommerce_cart_contents`, `woocommerce_cart_coupon`, `woocommerce_cart_actions`, `woocommerce_after_cart_contents`, `woocommerce_after_cart_table`, `woocommerce_after_cart`. Per-item filters: `woocommerce_cart_item_{product,permalink,thumbnail,name,price,quantity,subtotal,class,remove_link,visible}`. `[woo_cart_totals]` adds `woocommerce_before_cart_totals`, `woocommerce_proceed_to_checkout`, `woocommerce_after_cart_totals` and the shipping/order-total sub-hooks.

#### Alternative: cart as pure Etch HTML (Dynamic Keys)

For the cart **items** as fully builder-editable HTML (a real Etch loop, not a shortcode), Woo4Etch exposes the cart as Etch dynamic data on the **`options`** root — automatically. Just loop it:

```html
{#loop options.cart_items as item}
  <div class="row">
    <img src="{item.image}" alt="{item.name}">
    <a href="{item.permalink}">{item.name}</a>
    <span>{item.price}</span> · <span>Qty: {item.quantity}</span> · <span>{item.subtotal}</span>
    <a href="{item.remove_url}">Remove</a>
  </div>
{/loop}

<p>Total: {options.cart_total}</p>
```

Available keys:

| Key | Contents |
|---|---|
| `{options.cart_items}` | array — each item: `key, id, name, sku, quantity, price, subtotal, permalink, image, remove_url, on_sale` |
| `{options.cart_count}` | total item count |
| `{options.cart_subtotal}` / `{options.cart_total}` | formatted subtotal / total |
| `{options.cart_url}` / `{options.checkout_url}` | cart / checkout URLs |
| `{options.cart_nonce}` | cart nonce token — lets you build a working cart **form** in Etch |
| `{options.cart_is_empty}` | boolean |

It renders the **real cart** on the frontend, and **sample rows in the Etch builder canvas** so the loop previews while you design. Remove works via `{item.remove_url}`.

Because `{options.cart_nonce}` is exposed, you can wrap the loop in WooCommerce's cart form and get a **fully working** cart (quantity update + coupon) built entirely in Etch — no shortcode, and it still renders in the builder:

```html
<form class="woocommerce-cart-form" action="{options.cart_url}" method="post">
  {#loop options.cart_items as item}
    <img src="{item.image}" alt="{item.name}">
    <a href="{item.permalink}">{item.name}</a> — {item.price}
    <input type="number" name="cart[{item.key}][qty]" value="{item.quantity}" min="0">
    {item.subtotal}
    <a href="{item.remove_url}">Remove</a>
  {/loop}
  <input type="text" name="coupon_code" placeholder="Coupon code">
  <button type="submit" name="apply_coupon" value="Apply coupon">Apply coupon</button>
  <button type="submit" name="update_cart" value="Update cart">Update cart</button>
  <input type="hidden" name="woocommerce-cart-nonce" value="{options.cart_nonce}">
</form>
```

Toggle / reshape it:

```php
add_filter('woo4etch/expose_cart_data', '__return_false');     // turn the bridge off
add_filter('woo4etch/cart_data', fn($d) => $d);                // reshape the exposed data
add_filter('woo4etch/cart_image_size', fn() => 'thumbnail');   // item image size
```

**Trade-off:** the loop form handles quantity update, coupon and removal, but it does *not* fire WooCommerce's per-item cart **hooks/filters**, so third-party cart extensions that inject via those won't show. Use the loop when you want full HTML control in the builder; use `[woo_cart_items]` above when you need the complete, **extension-compatible** cart (all hooks fire) — at the cost of it appearing as a shortcode placeholder in the builder.

> **Why the difference shows in the builder:** shortcodes are processed only at frontend render, so `[woo_cart_items]` (and any shortcode) appears as literal text in the Etch builder canvas. Dynamic-data loops (`{options.cart_items}`) resolve in the builder, so they preview live. That's the trade-off between the two cart approaches.

### Account, orders & thank-you as Etch dynamic data

The same bridge exposes My Account and order data, so those pages can be built as Etch loops too (real data on the frontend, **sample data in the builder** so the loops preview):

| Key | Contents |
|---|---|
| `{options.account_menu}` | array — each: `key, label, url, is_active` (My Account nav) |
| `{options.account_endpoint}` | current My Account endpoint key — `dashboard` on the account root, `orders` / `downloads` / `edit-address` / … on sub-pages, empty outside the account area. Lets one layout switch its content per endpoint: `{#if options.account_endpoint === "orders"}…{/if}` (see [`07-account.md`](./07-account.md#how-endpoints-work-read-this-first)) |
| `{options.account_orders}` | array — each: `id, number, date, status, status_name, total, item_count, view_url` |
| `{options.order}` | current order (thank-you / view-order): `number, date, status, status_name, total, email, payment_method, billing_address`, and `items` (each: `name, quantity, total, image`) |

```html
<!-- My Account: nav + recent orders, fully in Etch -->
{#loop options.account_menu as m}<a href="{m.url}">{m.label}</a>{/loop}

{#loop options.account_orders as o}
  <div>Order #{o.number} — {o.date} — {o.status_name} — {o.total} <a href="{o.view_url}">View</a></div>
{/loop}

<!-- Thank-you / order-received -->
<h1>Thank you. Order #{options.order.number}</h1>
<p>{options.order.status_name} · {options.order.total} · {options.order.payment_method}</p>
{#loop options.order.items as it}<div>{it.name} × {it.quantity} — {it.total}</div>{/loop}
```

Toggle/reshape: `woo4etch/expose_account_data` (off switch), `woo4etch/account_order_data`, `woo4etch/account_orders_limit`, `woo4etch/account_orders_sample`, `woo4etch/order_sample`.

**Notes:** `{options.account_orders}` is queried only on the My Account area (or in the builder). `{options.order}` populates on the checkout **order-received / view-order** endpoint (or in the builder) — a standalone page won't have an order in context. **Checkout** itself keeps the native `[woocommerce_checkout]` shortcode for the form (payment + validation are real PHP); build the page chrome and an order-summary sidebar (loop `{options.cart_items}`) around it in Etch.

### Experimental: the namespaced `{woo.*}` root

The shop data above lives on Etch's shared `options` root because that's the only root Etch currently lets third parties extend (filter `etch/dynamic_data/option`). Don't confuse it with ACF/Metabox-style "options pages" — `options` is simply Etch's global dynamic-data namespace, and *every* plugin/theme that extends it competes for the same key names.

Woo4Etch therefore also registers the same data under its own **`woo`** root — instantly recognisable, collision-free, and structured:

| `{woo.*}` (experimental) | Same data as |
|---|---|
| `{woo.cart.items}` | `{options.cart_items}` |
| `{woo.cart.count}` / `{woo.cart.subtotal}` / `{woo.cart.total}` / `{woo.cart.is_empty}` | `{options.cart_count}` / `…cart_subtotal` / `…cart_total` / `…cart_is_empty` |
| `{woo.cart.url}` / `{woo.cart.nonce}` / `{woo.cart.cross_sells}` | `{options.cart_url}` / `…cart_nonce` / `…cross_sells` |
| `{woo.checkout.url}` | `{options.checkout_url}` |
| `{woo.account.menu}` / `{woo.account.endpoint}` / `{woo.account.orders}` | `{options.account_menu}` / `…account_endpoint` / `…account_orders` |
| `{woo.order}` | `{options.order}` |

Both roots are fed by the same builders — identical values, identical sample data in the builder canvas. The root is registered lazily: only pages whose blocks actually reference `woo.` pay for the data assembly.

**Why "experimental":** Etch has no public API for registering dynamic-data roots yet, so this rides on an Etch internal (`DynamicContentRegistry::enqueue()` — see `ETCH-FEATURE-REQUESTS.md` #3, where we ask for exactly this as a public API). The access is fully guarded: if an Etch update renames that internal, the `woo` root silently disappears while **`{options.*}` keeps working** — which is why `options.*` remains the documented, guaranteed spelling. If/when Etch ships the public API, Woo4Etch swaps the registration mechanism internally and the `{woo.*}` keys stay exactly the same — layouts built on them keep running.

Toggle/reshape: `woo4etch/enable_woo_root` (off switch), `woo4etch/woo_root_data`.

## Limitations

- **Shortcodes can't return JS-reactive markup directly.** If you need live updates on cart/account state, combine the bridge shortcodes with Woo Cart Fragments (see [`05-mini-cart.md`](./05-mini-cart.md)) or your own AJAX layer (see [`12-store-api-and-rest.md`](./12-store-api-and-rest.md)).
- **`[do_action]` runs synchronously during page render.** Avoid hooks that issue HTTP calls — they'll block the response.
- **The `args` attribute of `[do_action]` only passes strings.** PHP's loose typing usually does the right thing, but hooks expecting objects (e.g. `$order` instances) need a custom shortcode or PHP wrapper.

## Sources

- Zack Pyle — `[do_action]` snippet from the [Etch community](https://community.etchwp.com/c/general-discussion/woo-status)
- WooCommerce — [Shortcodes documentation](https://woocommerce.com/document/woocommerce-shortcodes/)
- WordPress — [Shortcode API](https://developer.wordpress.org/plugins/shortcodes/)
