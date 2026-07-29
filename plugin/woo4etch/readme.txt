=== Woo4Etch ===
Contributors: tobiashaas
Tags: woocommerce, etch, shortcodes, page-builder
Requires at least: 6.0
Tested up to: 6.7
Requires PHP: 8.1
Stable tag: 1.5.0-beta.6
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
* `[woo_product_attributes]` — full attributes table (visible attributes + weight/dimensions), empty when the product has no data
* `[woo_categories]` / `[woo_tags]` — linked category / tag list
* `[woo_short_description]` / `[woo_description]` — product copy (filtered HTML)

Product media:

* `[woo_image size="woocommerce_single"]` — featured image
* `[woo_gallery size="..." include_featured="no" link="no"]` — gallery images (matches the `gallery_images` Dynamic Key; featured image excluded unless include_featured="yes")
* `[woo_gallery mode="woo" columns="4"]` — WooCommerce-native gallery markup (featured image first) that Woo's zoom/lightbox/slider scripts initialise on; enable those under Woo4Etch → Settings

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

= Unreleased =
* New: `[woo_product_attributes]` — the "Additional information" attributes table (visible attributes + weight/dimensions) as a shortcode. Needed because the woocommerce_product_additional_information hook expects the product as a do_action argument, which the hook island cannot pass.
* New: `woo4etch/cart_item_payload` filter — adjust each cart-item payload (e.g. strip Germanized's gzd-* rows from `meta`) before it reaches `{options.cart_items}`.
* New: `{this.is_sold_individually}` exposed in the product data bridge — hide the quantity input for one-per-order products via `{#if !this.is_sold_individually}`.
* Fix: the product-grid.json copy/paste artifact binds its archive loop to Etch's seeded `etch_main_query` preset instead of the installer-minted `w4e_main_query`, which does not exist on sites where the one-click installer never ran (a manual paste could render an empty archive). The one-click installer was unaffected.

= 1.5.0-beta.6 =
* Minimum PHP is now 8.1 (Etch itself requires it; 7.4/8.0 support was dead weight). No code changed — the plugin already ran on 8.1+.
* Fix: the copy/paste layout files (templates/etch-copy/*.json) were out of sync with the plugin's layouts — product-grid.json still had the broken mainQuery loop target (a pasted shop archive showed no products; fixed for the one-click installer in beta.5 but the copy/paste file was never regenerated) and product-single.json had the pre-beta.5 gallery + escaped excerpt. Both regenerated. The one-click installer was unaffected.
* Internal: a fast, WordPress-free CI test layer now gates every PR (version-marker sync, shortcode-catalog integrity, layout loop/Woo-contract invariants, and a copy/paste-artifact drift guard); PHP lint matrix runs 8.1 → 8.5.

= 1.5.0-beta.5 =
* Experimental {woo.*} dynamic-data root: the shop data from {options.*} additionally under a namespaced, structured root — {woo.cart.items}, {woo.cart.count}, {woo.checkout.url}, {woo.account.menu}, {woo.account.orders}, {woo.order}, … Same values and builder sample data as {options.*}; lazy registration; fully guarded against Etch internals changing (then the woo root disappears while {options.*} keeps working — options.* remains the documented spelling). Filters: woo4etch/enable_woo_root, woo4etch/woo_root_data.
* Ready-made layouts install without page reloads: per-layout install buttons now run via AJAX (button state and checkmark update in place), and a new "Install / reinstall all layouts" button installs the whole set in one click. The old form submit remains as a no-JS fallback.
* Single-product layout: the gallery now uses Woo's native gallery markup (woocommerce-product-gallery classes, data-thumb/data-large_image attributes), so zoom/lightbox/slider work as soon as the gallery scripts are enabled in Settings; without them the bundled CSS renders the same markup as featured image + thumbnail grid. New root class w4e-gal so reinstalling picks up the new styles.
* Single-product layout: the excerpt is now a Raw HTML element instead of a text element — Woo short descriptions may contain HTML, which text elements escape to literal tags.
* Fix: variable products couldn't be purchased from the single-product layout — the buy box is now type-aware: simple products keep the hand-built form, variable/grouped/external get WooCommerce's native form (variations fully working; swatches.js bridges custom swatch markup on top).
* Live variation price: swatches.js mirrors the chosen variation's price into elements marked data-w4e-variation-price (restores the range price on reset). The single-product layout marks its price row accordingly.
* Hook marker data-w4e-hook (kses-proof [do_action]): an empty <div data-w4e-hook="..." data-w4e-product="{this.id}"></div> is filled with captured do_action() output after Etch renders — third-party hooks emitting forms/buttons/scripts survive Etch's raw-html sanitizer. Same woo4etch/allow_do_action restriction. The single-product layout ships these markers around its simple-product form.
* WooCommerce's block-template compatibility layer is disabled on block themes (filter: woo4etch/disable_block_hook_compatibility) — Woo strips classic product/shop hook callbacks during block-template rendering and re-injects them only around woocommerce/* blocks, which Etch layouts don't contain; third-party hook output (e.g. Germanized legal info) silently disappeared even via [do_action]. With the layer off, classic hooks work again.
* data-w4e-skip-defaults for hook markers: fires a hook with WooCommerce core's own template callbacks unhooked (restored afterwards) — e.g. woocommerce_single_product_summary renders only third-party extras (Germanized unit price, tax/shipping notices, delivery time) instead of duplicating the layout's title/price/excerpt/form. The single-product layout ships a summary-extras marker after its price row. Filter: woo4etch/hook_core_defaults.
* New server-side embed marker data-w4e-add-to-cart: an empty <div data-w4e-add-to-cart="{this.id}"></div> is filled with Woo's native add-to-cart form after Etch renders — Etch's raw-html sanitizer would strip form/input/select tags from a shortcode in a raw-html block (unless the off-by-default "allow unsafe raw HTML" Etch setting is on).
* New product key {this.is_simple} (bool) for conditions — note: {this.product_type} is shadowed by Etch's own taxonomy term object on product posts; use {this.product_type.name} for display.
* Fix: wc-add-to-cart-variation never loaded for hand-built variation forms — now enqueued automatically on variable-product pages (filter: woo4etch/enqueue_variation_script).
* New Dynamic Key {this.variations_json} for hand-built form.variations_form markup (data-product_variations); computed only for the main product on its own page (filter: woo4etch/expose_variations_json).
* Fix: plugin updates wiped includes/customizations.php — the upgrader now preserves your edits across updates (backup before, restore after, skipped when the file is the untouched skeleton). New optional update-safe location: wp-content/woo4etch-customizations.php is loaded automatically when present and lives entirely outside the plugin folder.
* Fix: the shop-archive layout rendered no products — its loop referenced a raw mainQuery target, but Etch runs query-type loops only through loop presets (etch_loops option). The layout now resolves the site's main-query preset (or creates one) and binds the loop via loopId.

= 1.5.0-beta.4 =
* Native Woo gallery effects (hover zoom, PhotoSwipe lightbox, FlexSlider thumbnail slider) for Etch layouts: new settings checkbox "Enable WooCommerce gallery scripts" declares the wc-product-gallery-* theme supports AND enqueues Woo's registered gallery scripts on single product pages — necessary because WooCommerce only auto-loads them for classic themes, never on block themes like Etch's. Filter: woo4etch/gallery_features (receives the checkbox result; return a subset to enable only some effects).
* [woo_gallery mode="woo" columns="4"]: outputs WooCommerce-native gallery markup (wrapper + data-thumb/data-large_image attributes via wc_get_gallery_image_html, featured image first) that Woo's gallery scripts initialise on, and auto-enqueues them where it renders. The existing custom-markup output is unchanged (mode="custom", default).

= 1.5.0-beta.3 =
* Buy-now flow built in: a submit button name="buy_now" inside form.cart sends the customer straight to checkout after the normal add-to-cart — no snippet needed. Filters: woo4etch/enable_buy_now (default on), woo4etch/buy_now_empty_cart (default off; opt in for true one-click checkout where the cart is emptied first).
* Variation swatches, Etch-native: new bundled script (assets/swatches.js, enqueued on product pages) bridges clicks on your own Etch-built swatch markup (data-w4e-swatch / data-attribute / data-value) to the hidden native attribute select, so WooCommerce's variation logic (price, stock, variation_id) keeps working untouched. Selected state via .is-selected + aria-pressed; Woo's "Clear" link resets the swatches. Filter: woo4etch/enqueue_swatches.
* New Settings section (Etch → Woo4Etch): checkbox "Disable WooCommerce default styles" removes all three Woo stylesheets so Etch styles start from a blank slate — and brings them back when unchecked. Filter override: woo4etch/disable_woo_styles.

= 1.5.0-beta.2 =
* Ready-made layouts with one-click install: Etch → Woo4Etch → Ready-made layouts ships complete, editable Etch layouts for cart, single product, shop archive, header mini-cart, My Account and thank-you. "Install as pattern" adds them to Etch's pattern library (category Woo4Etch, unsynced) and merges their classes into Etch's style system — existing styles with the same selector are reused, never overwritten. "Copy JSON" exports Etch's native paste format. All layouts are built on the dynamic-data bridges, so they preview live in the builder.
* New {options.account_endpoint} — the current My Account endpoint as a scalar (dashboard on the account root, the endpoint key on sub-pages, empty outside the account area), so one Etch layout can switch its content per endpoint: {#if options.account_endpoint === "orders"}. Builder sample filter: woo4etch/account_endpoint_sample.

= 1.5.0-beta.1 =
* Product fields as Etch dynamic data: on product posts the plugin enriches Etch's post data (via etch/dynamic_data/post, the same seam Etch uses for gallery_images) with formatted/derived keys — {this.price}, {this.regular_price}, {this.sale_price}, {this.price_html}, {this.price_amount}, {this.currency_symbol}, {this.is_on_sale}, {this.sale_percentage}, {this.sku}, {this.product_type}, {this.stock_status}, {this.stock_label}, {this.stock_quantity}, {this.is_in_stock}, {this.is_purchasable}, {this.is_featured}, {this.rating}, {this.rating_count}, {this.review_count}, {this.add_to_cart_url}, {this.add_to_cart_text}, {this.weight}, {this.dimensions}, {this.upsell_ids}. Also available as {item.*} inside loops; renders live in the Etch builder canvas. Keys Etch sets itself are never overwritten. Filters: woo4etch/expose_product_data, woo4etch/product_data.

= 1.4.1 =
* Hardening: HTML-capable shortcode attributes are now filtered through wp_kses_post() — delimiter/wrap_before/wrap_after on [woo_breadcrumb], sep/before/after on [woo_categories] and [woo_tags]. Wrapper HTML keeps working; scripts and event handlers are stripped, so authors without the unfiltered_html capability cannot inject them via shortcode attributes.
* Updater: release packages are only accepted from this repository's GitHub Releases download URL (defense in depth against a tampered API response).

= 1.4.0 =
* Account & order data as Etch dynamic data, so the My Account and thank-you/order pages can be built as pure Etch loops too: {options.account_menu} (key, label, url, is_active), {options.account_orders} (id, number, date, status, status_name, total, item_count, view_url) and {options.order} (number, date, status, status_name, total, email, payment_method, billing_address, items[]). Real data on the frontend, sample data in the Etch builder. Filters: woo4etch/expose_account_data, woo4etch/account_order_data, woo4etch/account_orders_limit, woo4etch/account_orders_sample, woo4etch/order_sample.
* With the cart bridge from 1.3.0 this means the whole shop — single product, shop archive, cart, mini-cart, My Account, checkout summary and thank-you — can be built as editable Etch layouts that preview in the builder.

= 1.3.0 =
* Cart as Etch dynamic data: the cart is exposed on Etch's `options` root ({options.cart_items} with key/id/name/sku/quantity/price/subtotal/permalink/image/remove_url/on_sale, plus cart_count/cart_subtotal/cart_total/cart_url/checkout_url/cart_nonce/cart_is_empty), so the whole cart — items, quantity update, coupon — can be built as a pure Etch loop/form with full HTML control that also renders in the Etch builder (shortcodes only render on the frontend). Real cart on the frontend, sample rows in the builder canvas. Filters: woo4etch/expose_cart_data, woo4etch/cart_data, woo4etch/cart_image_size, woo4etch/cart_sample_data.
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
