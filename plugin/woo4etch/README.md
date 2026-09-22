# Woo4Etch

The WooCommerce layer for [Etch](https://etchwp.com/?aff=06de86e5).

Etch is a server-rendered WordPress visual builder without native WooCommerce
blocks. Woo4Etch closes that gap **without taking the markup back**: it supplies
WooCommerce *data* (as Etch Dynamic Keys), *behavior* (form handlers, the Store
API) and *escape hatches* (shortcodes, hook markers) — while the HTML stays
yours, written and restyled in the Etch builder.

That is the whole design rule. The plugin never generates a layout you cannot
edit: the ready-made layouts it installs are ordinary Etch blocks with ordinary
class records, and the interactive features read by re-rendering *your*
server-side HTML rather than templating on the client. Restructure anything in
the builder and the features keep working. See
[`docs/PRODUCT-PRINCIPLES.md`](../../docs/PRODUCT-PRINCIPLES.md).

## What's in it

| | |
|---|---|
| **9 ready-made layouts** | Complete, editable Etch layouts for shop archive, category, single product, cart, checkout, mini-cart, My Account, thank-you and notices — installed straight onto the page or template that renders them. |
| **Dynamic-data bridges** | WooCommerce as Etch Dynamic Keys: products, cart, checkout, shop/archive, account and orders — live in the builder canvas, no shortcode needed. |
| **55 shortcodes** | For everything Dynamic Keys can't express: real WooCommerce PHP (add-to-cart forms, tabs, reviews, pagination, notices…), plus `[do_action]` for any hook. The admin reference lists all of them, including the 10 native WooCommerce tags, with copy buttons. |
| **Store API layer** | Cart writes and the A+ checkout go through WooCommerce's own Store API endpoints — so Woo's native validation and rate limiting apply — and read back by swapping server-rendered regions of your own markup. |
| **Frontend enhancements** | Optional: variation pills/swatches, quantity stepper, price-range slider, WooCommerce's gallery scripts (zoom/lightbox/slider). Their styling ships as Etch class records inside the layouts, never as plugin stylesheets. |
| **Admin** | **Etch → Woo4Etch** in four tabs: Overview (shop status), Layouts, Settings, Shortcodes. Plus a **WooCommerce group** in Etch's own template hub for Woo's template types. |
| **Housekeeping** | Declares `add_theme_support('woocommerce')` for you (the Etch theme doesn't), declares HPOS compatibility, self-updates from GitHub Releases, and preserves your `customizations.php` across updates. |

## Requirements

- WordPress **6.0+**
- PHP **8.1+** (Etch's own floor)
- **WooCommerce** active (`Requires Plugins: woocommerce`)
- **Etch 1.4.20+** for the layouts and Dynamic Keys — the plugin's shortcodes
  work without Etch, the Etch-facing parts don't. Verified against **Etch
  1.6.7**; the seams it uses (`etch/dynamic_data/post` and `/option`,
  `{this.meta.*}`, shortcode processing, the component meta schema) are
  unchanged across that range.

## Install

1. Copy the `woo4etch/` folder into `wp-content/plugins/` (or zip it and upload
   via **Plugins → Add New → Upload**).
2. Activate it.
3. Open **Etch → Woo4Etch** (falls back to **WooCommerce → Woo4Etch** without Etch).

Shortcodes register on `init` once WooCommerce is available; drop them into Etch
HTML like any other shortcode.

Paste your own hook snippets into **`includes/customizations.php`** — that file
is empty by design and is the one place the updater preserves across plugin
updates. Ready-to-paste snippets: [`templates/functions-snippets.md`](../../templates/functions-snippets.md).

## Ready-made layouts

**Etch → Woo4Etch → Layouts**: complete, editable Etch layouts —
shop archive (working filter sidebar + category slider), category archive (SEO
intro + term description), single product, cart, **checkout (Store API)**,
header mini-cart (hover dropdown), My Account, thank-you, Woo notices.

**Add to page/template** installs each one straight where it renders
(WooCommerce's assigned page or the area's Etch template; append-only, never
double-inserts), or copy them as Etch paste-JSON. Built on the dynamic-data
bridges below, so they preview live in the builder.

Each entry also carries a **"Don't reach for this when"** line: every layout
leaves out something WooCommerce's own template renders, and the omission is
silent. Read it before installing — collected in
[`templates/15-woo4etch-plugin.md`](../../templates/15-woo4etch-plugin.md#where-each-layout-stops).
Transactional emails have no layout by design (Woo renders those from PHP templates).

**Updating the plugin does not update a layout already on your page** — the push
route is append-only so it can never overwrite builder work. The Layouts tab
flags an installed copy that predates a fix; delete the layout's section in Etch
and press **Add to page/template** again. Style records are reused, never overwritten.

## Dynamic data bridges (no shortcode needed)

The plugin exposes WooCommerce data as **Etch Dynamic Keys**, so it renders live
in the builder canvas:

- **Products** — `{this.price}`, `{this.is_on_sale}`, `{this.sale_percentage}`,
  `{this.stock_status}`, `{this.rating}`, … on every `product` post (also as
  `{item.*}` in loops).
- **Cart** — `{options.cart_items}` (loopable), `{options.cart_count}`,
  `{options.cart_total}`, `{options.cart_show_shipping}`, `{options.cart_nonce}`, …
- **Checkout** — `{options.checkout.payment_methods}`,
  `{options.checkout.shipping_rates}`, `{options.checkout.countries}`,
  `{options.checkout.states}` and the address-locale flags.
- **Shop & archives** — `{options.shop_categories}` (loopable, with counts +
  active state), `{options.shop_max_price}`,
  `{options.filter_min_price}`/`{options.filter_max_price}`, `{options.shop_url}`.
- **Account & orders** — `{options.is_logged_in}`, `{options.account_menu}`,
  `{options.account_orders}`, `{options.order}`.

Full key reference, and the filters that reshape or disable each bridge:
[`templates/15-woo4etch-plugin.md`](../../templates/15-woo4etch-plugin.md).

## Shortcode reference

The tables below are the common ones. The **complete** reference — all 55
shortcodes plus the 10 native WooCommerce tags, with attributes and examples — is
the admin **Shortcodes** tab and
[`templates/15-woo4etch-plugin.md`](../../templates/15-woo4etch-plugin.md).

### Generic `[do_action]` shortcode

#### `[do_action hook="..." args="..." skip_defaults="yes|no"]`

Fires any WordPress / WooCommerce action hook in place.

```text
[do_action hook="woocommerce_before_add_to_cart_button"]
[do_action hook="woocommerce_thankyou" args="{options.order.id}" skip_defaults="yes"]
```

`args` is a comma-separated list of positional arguments. Numeric values are
passed as **integers** (an order id a callback compares strictly would fail as
`"1042"`); everything else is passed as a string.

Pick the key that actually holds what the hook expects. On the order-received
endpoint `{this.id}` is the **checkout page's** id, not the order's — thank-you
hooks want `{options.order.id}`.

`skip_defaults="yes"` temporarily unhooks WooCommerce core's *own* template
callbacks for that hook, so only third-party output renders — for when your
layout already draws what core would draw. Third-party callbacks are never
touched. Filter: `woo4etch/hook_core_defaults`.

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
| `[woo_product_attributes id="123"]` | Full attributes table | Visible attributes + weight/dimensions; empty when there's no data |

When `id` is omitted, the shortcode uses the **current product context** — global
`$product` if set, otherwise the queried product on a single-product page.

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
| `[woo_notices]` | Renders queued cart/checkout notices (`format="plain"` for `.w4e-notice` markup) |
| `[woo_breadcrumb]` | WooCommerce breadcrumb navigation |
| `[woo_pagination]` | Woo's numbered archive pagination |

### Cart state

| Shortcode | Output |
|---|---|
| `[woo_cart_count]` | `<span class="kr-cart-count" data-count="3">3</span>` |
| `[woo_cart_total]` | Formatted cart total |
| `[woo_cart_url]` | URL to the cart page |

### Customer

#### `[woo_user field="display_name" default=""]`

Outputs a field from the current user. Falls back to `default` for guests.

Allowed fields: `display_name`, `user_login`, `user_email`, `first_name`,
`last_name`, `ID`.

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

## Updates from GitHub

When installed under `wp-content/plugins/woo4etch/`, Woo4Etch checks
[GitHub Releases](https://github.com/tobiashaas/woo4etch/releases) for new
versions. Updates appear under **Dashboard → Updates**. MU-plugin copies must be
updated manually.

Your `includes/customizations.php` survives updates — an untouched skeleton is
replaced so shipped improvements arrive, an edited file is preserved. Disable
remote checks with `add_filter('woo4etch/enable_github_updates', '__return_false');`.

**Troubleshooting — "some files could not be copied. This is usually due to
inconsistent file permissions."**: the ownership/permissions under
`wp-content/plugins/woo4etch/` no longer match the PHP user (occasionally happens
on managed hosts, e.g. after certain GridPane provisioning steps — not a Woo4Etch
bug). Reset the site's file permissions (GridPane has a one-click permission-reset
tool; generic fix: `chown -R` the folder to the PHP user, directories `755`, files
`644`) and retry. Since 1.6.3 the updater detects this **before** touching any
files and aborts with this guidance instead of failing mid-update.

## Design rules

The plugin is deliberately scoped, and the scope is about *ownership*, not size:

- **Markup stays in Etch.** Nothing here writes a layout you can't open and
  restructure in the builder. Where WooCommerce PHP must render (add-to-cart
  forms, tabs, reviews), it is behind an explicit shortcode *you* place.
- **Data via filters, behavior via hooks.** Every bridge is filterable
  (`woo4etch/*_data`) and every bridge can be switched off
  (`woo4etch/expose_*`). No hidden injection.
- **No WooCommerce template overrides.** Woo4Etch never ships
  `woocommerce/` template files — see
  [ADR-001](../../docs/ADR-001-no-template-overrides.md).
- **Standard extension points.** WooCommerce hooks, shortcodes and the Store
  API, not a parallel API of our own.

If you need a shortcode that isn't here, write a 3-line custom one in
`includes/customizations.php` or extend the catalog via
`woo4etch/shortcode_catalog` — it's MIT-licensed.

## Tests

The repo's fast checks cover this plugin's catalog and layout invariants and run
on every PR:

```bash
php tests/php/run.php
```

Integration checks against a real WordPress + WooCommerce:
[`tests/integration/`](../../tests/integration/README.md).

## License

[MIT License](../../LICENSE) — same as the parent **woo4etch** repository. Free
to use, modify, and distribute; attribution required when redistributing
substantial portions.
