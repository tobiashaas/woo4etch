=== Woo4Etch Bridge ===
Contributors: tobiashaas
Tags: woocommerce, etch, shortcodes, page-builder
Requires at least: 6.0
Tested up to: 6.7
Requires PHP: 7.4
Stable tag: 1.0.0
License: MIT
License URI: https://opensource.org/licenses/MIT

Shortcodes that bridge WooCommerce PHP into Etch templates — for everything Etch can't do natively yet.

== Description ==

Etch is a WordPress visual builder that doesn't (yet) have native WooCommerce blocks. Woo4Etch Bridge provides a small set of carefully scoped shortcodes you can drop into Etch templates to invoke WooCommerce PHP where you need it.

The foundation is a generic `[do_action]` shortcode that fires any WordPress action hook from inside content. On top of that, a curated set of convenience shortcodes covers the most common needs: product price, stock, quantity input, add-to-cart form, notices, breadcrumb, cart counter, and more.

= Shortcodes =

* `[do_action hook="..." args="..."]` — fire any WP/Woo action hook
* `[woo_price id="..."]` — formatted product price (with sale strikethrough, "from" for variables)
* `[woo_sku id="..." default="..."]` — product SKU
* `[woo_stock id="..." format="label|status|quantity"]` — stock state
* `[woo_meta id="..." key="..." default="..."]` — any product meta field
* `[woo_attribute id="..." name="..." default="..."]` — product attribute by taxonomy name
* `[woo_add_to_cart id="..."]` — full add-to-cart form (simple, variable, grouped, external)
* `[woo_quantity id="..." min="..." max="..." step="..." value="..."]` — just the quantity input
* `[woo_rating id="..."]` — star rating HTML
* `[woo_review_form id="..."]` — product reviews comment form
* `[woo_notices]` — `wc_print_notices()`
* `[woo_breadcrumb]` — WooCommerce breadcrumb
* `[woo_cart_count]` — number of items in the cart
* `[woo_cart_total]` — formatted cart total
* `[woo_cart_url]` — cart page URL
* `[woo_user field="display_name|user_email|..." default="..."]` — current user field
* `[woo_template name="single-product/related"]` — load any Woo template part

= Hardening =

Restrict which hooks `[do_action]` may fire:

`add_filter('woo4etch/allow_do_action', function ($allowed, $hook) {
    return strpos($hook, 'woocommerce_') === 0; // only woo_* hooks
}, 10, 2);`

== Installation ==

1. Upload the `woo4etch-bridge` folder to `/wp-content/plugins/`.
2. Activate from `Plugins → Installed Plugins`.
3. Drop shortcodes into your Etch templates or any WordPress content.

WooCommerce must be installed and active.

== Changelog ==

= 1.0.0 =
* Initial release with 17 shortcodes.
