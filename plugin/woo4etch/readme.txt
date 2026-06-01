=== Woo4Etch ===
Contributors: tobiashaas
Tags: woocommerce, etch, shortcodes, page-builder
Requires at least: 6.0
Tested up to: 6.7
Requires PHP: 7.4
Stable tag: 1.3.0
License: MIT
License URI: https://opensource.org/licenses/MIT

WooCommerce shortcodes and customizations for Etch templates — for everything Etch can't do natively yet.

== Description ==

Etch is a WordPress visual builder that doesn't (yet) have native WooCommerce blocks. Woo4Etch provides a small set of carefully scoped shortcodes you can drop into Etch templates to invoke WooCommerce PHP where you need it.

The foundation is a generic `[do_action]` shortcode that fires any WordPress action hook from inside content. On top of that, a comprehensive set of convenience shortcodes covers product data, media, UI, cart, account, store/archive, and conditional rendering — so you don't have to hunt for hook names or template paths yourself. The admin reference (Etch → Woo4Etch) lists every shortcode, including the native WooCommerce ones, with copy buttons.

= Shortcodes =

Hooks:

* `[do_action hook="..." args="..."]` — fire any WP/Woo action hook

Product data:

* `[woo_title link="yes|no"]` — product name (optionally linked)
* `[woo_price]` — formatted price (sale strikethrough, "from" for variables)
* `[woo_regular_price]` / `[woo_sale_price]` — regular / sale price
* `[woo_price_amount]` — raw numeric price for itemprop/schema
* `[woo_sale_badge percentage="yes"]` — "Sale!" badge or discount %
* `[woo_sku default="..."]` — product SKU
* `[woo_stock format="label|status|quantity"]` — stock state
* `[woo_weight]` / `[woo_dimensions]` — formatted weight / dimensions
* `[woo_meta key="..." default="..."]` — any product meta field
* `[woo_attribute name="pa_color" default="..."]` — product attribute by taxonomy
* `[woo_categories]` / `[woo_tags]` — linked category / tag list
* `[woo_short_description]` / `[woo_description]` — product copy (filtered HTML)

Product media:

* `[woo_image size="woocommerce_single"]` — featured image
* `[woo_gallery size="..." include_featured="no" link="no"]` — gallery images (matches the `gallery_images` Dynamic Key; featured image excluded unless include_featured="yes")

Product UI:

* `[woo_add_to_cart]` — full add-to-cart form (simple, variable, grouped, external)
* `[woo_add_to_cart_url]` — direct add-to-cart URL for custom buttons
* `[woo_quantity min="..." max="..." step="..." value="..."]` — quantity input only
* `[woo_rating]` — star rating HTML
* `[woo_review_form]` — product reviews comment form
* `[woo_tabs]` — product data tabs
* `[woo_related]` / `[woo_upsells]` — related / up-sell products

Cart:

* `[woo_cart_count]` — item count span with data-count
* `[woo_cart_total]` — formatted cart total
* `[woo_cart_url]` / `[woo_checkout_url]` — cart / checkout URL
* `[woo_mini_cart]` — mini-cart widget markup
* `[woo_cart_items]` — cart line items with your own class-based markup (qty update + remove, classic form); the customisable alternative to [woocommerce_cart]
* `[woo_cart_totals]` — cart totals block
* `[woo_coupon_form]` — apply-coupon form
* `[woo_shipping_calculator]` — cart shipping calculator
* `[woo_cross_sells]` — cross-sell products

Account:

* `[woo_user field="..." default="..."]` — current user field
* `[woo_account_url endpoint="orders"]` — My Account / endpoint URL
* `[woo_logout_url]` — nonce-protected logout URL
* `[woo_account_menu]` — My Account navigation menu
* `[woo_account_content]` — current My Account endpoint content
* `[woo_login_form]` — login form
* `[woo_order_details]` — order details table (thank-you / view-order)

Store & archive:

* `[woo_shop_url]` — shop page URL
* `[woo_breadcrumb]` — breadcrumb trail
* `[woo_result_count]` — "Showing 1–12 of 48 results"
* `[woo_catalog_ordering]` — sort-by dropdown
* `[woo_pagination]` — product loop pagination
* `[woo_product_search]` — product search form
* `[woo_notices]` — queued WooCommerce notices

Conditional:

* `[woo_if cond="is_product"]…[/woo_if]` — render only when a Woo conditional is true (prefix `!` to negate; supports page and product conditionals)

Templates:

* `[woo_template name="single-product/related"]` — load any Woo template part

The reference page also lists the native WooCommerce shortcodes (`[woocommerce_cart]`, `[woocommerce_checkout]`, `[woocommerce_my_account]`, `[products]`, `[product_page]`, `[product_categories]`, `[add_to_cart]`, …) so every option is in one place. Those are registered by WooCommerce itself, not Woo4Etch.

= Hardening =

Restrict which hooks `[do_action]` may fire:

`add_filter('woo4etch/allow_do_action', function ($allowed, $hook) {
    return strpos($hook, 'woocommerce_') === 0; // only woo_* hooks
}, 10, 2);`

== Installation ==

1. Upload the `woo4etch` folder to `/wp-content/plugins/` (or `/wp-content/mu-plugins/woo4etch/` as an MU-plugin).
2. Activate from `Plugins → Installed Plugins` (regular install only).
3. Drop shortcodes into your Etch templates. Add hooks from the templates into `includes/customizations.php`.

WooCommerce must be installed and active.

In the admin, open **Etch → Woo4Etch** for a table of all shortcodes with copy buttons (or **WooCommerce → Woo4Etch** when Etch is not active).

== Changelog ==

= 1.3.0 =
* New [woo_cart_items]: a complete, extension-compatible cart form with clean class-based markup — items, coupon, Update cart + nonce, remove — that fires every WooCommerce cart hook and per-item filter (so third-party cart plugins keep working). The customisable alternative to the monolithic [woocommerce_cart]; pair with [woo_cart_totals] / [woo_cross_sells] in your own Etch layout. No AJAX required.
* Cart-dependent shortcodes (cart_items/totals/cross_sells/shipping_calculator/mini_cart) are guarded against a null WC()->cart (Etch builder / REST preview), so they render empty instead of fataling.
* Fix: [woo_add_to_cart] rendered nothing on themes without add-to-cart template overrides (e.g. the Etch theme) because it used wc_get_template_part(), which only looks in the theme. It now fires the woocommerce_{type}_add_to_cart action like WooCommerce core, so it works everywhere.
* Fix: [woo_template] now uses wc_get_template(), which falls back to WooCommerce's own templates directory, so paths like "single-product/related" resolve without a theme override.
* Gap-filling shortcodes for the remaining PHP-bound areas: [woo_account_menu], [woo_account_content] (renders any My Account endpoint), [woo_login_form], [woo_order_details], [woo_cart_totals], [woo_coupon_form], [woo_shipping_calculator], [woo_cross_sells], [woo_product_search]. [woo_if] now also supports is_user_logged_in and is_type (arg="grouped|external|…").
* Automatic WooCommerce theme support: the plugin now declares add_theme_support('woocommerce') on behalf of the theme (late, and only when no theme already declares it), so the official Etch theme no longer needs a manual snippet. Filterable via woo4etch/auto_theme_support, woo4etch/theme_support_args, and woo4etch/gallery_features.
* Expanded the shortcode library: product data (title, regular/sale price, price amount, sale badge, weight, dimensions, categories, tags, short/long description), product media ([woo_image], [woo_gallery]), product UI ([woo_add_to_cart_url], [woo_tabs], [woo_related], [woo_upsells]), cart ([woo_checkout_url], [woo_mini_cart]), account ([woo_account_url], [woo_logout_url]), and store/archive ([woo_shop_url], [woo_result_count], [woo_catalog_ordering], [woo_pagination]).
* New conditional shortcode [woo_if cond="..."]…[/woo_if] for WooCommerce page and product conditionals (negate with a leading !).
* [woo_gallery] mirrors Etch's gallery_images Dynamic Key: the featured image is excluded by default (include_featured="yes" to prepend it).
* Admin reference now also lists the native WooCommerce core shortcodes for discoverability (not registered by Woo4Etch).

= 1.2.2 =
* Declare WooCommerce HPOS (High-Performance Order Storage) compatibility.
* Updater: cache failed/empty GitHub API responses for an hour to avoid repeated requests when offline or rate-limited.

= 1.2.1 =
* GitHub release updates: WordPress shows new versions when a GitHub Release with woo4etch.zip is published.

= 1.2.0 =
* Renamed from Woo4Etch Bridge to Woo4Etch; plugin folder `woo4etch/`.
* Snippets live in `includes/customizations.php` (one package, regular or MU install).

= 1.1.0 =
* Admin shortcode reference under the Etch menu (auto-detect; WooCommerce fallback).

= 1.0.0 =
* Initial release with 17 shortcodes.
