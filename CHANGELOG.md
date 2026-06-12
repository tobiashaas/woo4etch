# Changelog

All notable changes to the **Woo4Etch plugin** (`plugin/woo4etch/`) are documented here.
The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/); versions follow [Semantic Versioning](https://semver.org/).

Releases are published as [GitHub Releases](https://github.com/tobiashaas/woo4etch/releases); regular plugin installs self-update from there. The same changelog ships inside the plugin in `plugin/woo4etch/readme.txt` — keep both in sync.

## [1.5.0-beta.4] — 2026-06-12

Pre-release — not offered to installed sites via the auto-updater; install manually to test.

### Added

- **Native Woo gallery effects** (hover zoom, PhotoSwipe lightbox, FlexSlider thumbnail slider) for Etch layouts: new settings checkbox **Enable WooCommerce gallery scripts** declares the `wc-product-gallery-*` theme supports **and** enqueues Woo's registered gallery scripts on single product pages. The enqueue half matters: WooCommerce gates its gallery bundle behind `is_product() && ! wp_is_block_theme()`, so on a block theme (like Etch's) the theme supports alone load nothing. Filter: `woo4etch/gallery_features` (receives the checkbox result; return a subset to enable only some effects).
- **`[woo_gallery mode="woo" columns="4"]`**: WooCommerce-native gallery markup (wrapper + `data-thumb`/`data-large_image` attributes via `wc_get_gallery_image_html()`, featured image first) that Woo's gallery scripts initialise on; auto-enqueues them where it renders. The existing output is unchanged (`mode="custom"`, default).

### Documentation

- Single product: new gallery variant section — Woo's zoom/lightbox/slider on hand-written Etch markup, required classes/attributes table, styling fallback when Woo CSS is disabled (`templates/01-single-product-simple.md`); block-theme caveat for the `wc-product-gallery-*` supports (`templates/00-README.md`, `templates/functions-snippets.md`, `templates/15-woo4etch-plugin.md`).

## [1.5.0-beta.3] — 2026-06-12

Pre-release — not offered to installed sites via the auto-updater; install manually to test.

### Added

- **Buy-now flow built in:** a submit button `name="buy_now"` inside `form.cart` sends the customer straight to checkout after the normal, validated add-to-cart — no snippet needed. Filters: `woo4etch/enable_buy_now` (default on), `woo4etch/buy_now_empty_cart` (default off; opt in for true one-click checkout where the cart is emptied first). New template: `templates/16-one-click-checkout.md`.
- **Variation swatches, Etch-native:** new bundled script (`assets/swatches.js`, enqueued on product pages) bridges clicks on your own Etch-built swatch markup (`data-w4e-swatch` / `data-attribute` / `data-value`) to the hidden native attribute `<select>`, so WooCommerce's variation logic (price, stock, `variation_id`) keeps working untouched. Selected state via `.is-selected` + `aria-pressed`; Woo's "Clear" link resets the swatches. Filter: `woo4etch/enqueue_swatches`. Documented in `templates/02-single-product-variable.md`.
- **Settings section** (Etch → Woo4Etch): checkbox **Disable WooCommerce default styles** removes all three Woo stylesheets so Etch styles start from a blank slate — and brings them back when unchecked (no snippet hunt). Filter override: `woo4etch/disable_woo_styles`.

### Documentation

- New `templates/16-one-click-checkout.md` (Buy Now → checkout → thank-you, incl. guest checkout test checklist) and `templates/17-components.md` (Etch component blueprints with the pre-wired, do-not-touch Woo attributes).
- `docs/ADR-001-no-template-overrides.md`: architecture decision — Woo4Etch never overrides WooCommerce PHP template files; strict layer separation (plugin logic auto-updates, user layouts are never touched).
- Single product: the excerpt must be a **Raw HTML** element, not a Paragraph — Woo short descriptions may contain HTML (`templates/01-single-product-simple.md`).
- Cart: snippet to remove the duplicate Gutenberg `wp-block-post-title` heading on Woo pages (`templates/04-cart.md`, `templates/functions-snippets.md`).
- Etch context guide: new section on condition blocks hiding content in the builder, with workarounds (`templates/10-etch-context-and-templates.md`).

## [1.5.0-beta.2] — 2026-06-12

Pre-release — not offered to installed sites via the auto-updater; install manually to test.

### Added

- **Ready-made layouts with one-click install:** Etch → Woo4Etch → Ready-made layouts ships complete, editable Etch layouts for cart, single product, shop archive, header mini-cart, My Account and thank-you. "Install as pattern" adds them to Etch's pattern library (category Woo4Etch, unsynced — inserting gives a detached, freely editable copy) and merges their classes into Etch's style system; existing styles with the same selector are reused, never overwritten. "Copy JSON" exports Etch's native paste format. The `templates/etch-copy/*.json` files are generated from the same definitions (`tools/generate-etch-copy.php`).
- `{options.account_endpoint}` — the current My Account endpoint as a scalar (`dashboard` on the account root, the endpoint key on sub-pages, empty outside the account area; `dashboard` in the builder so endpoint sections preview). Lets one Etch layout switch its content area per endpoint: `{#if options.account_endpoint === "orders"}…{/if}`. Filter for the builder sample: `woo4etch/account_endpoint_sample`.

### Documentation

- Templates 07 (My Account) and 08 (thank-you) now explain the WooCommerce **endpoint** concept up front — one page, many endpoint views, nothing to register per endpoint — with the three switching patterns (let Woo render, Etch-native conditionals via the dynamic-data bridge, `[woo_if]`).
- `ETCH-FEATURE-REQUESTS.md`: new upstream proposal for endpoint-aware template conditions via an `etch/template_hierarchy` filter (including the lesson from Bricks' separate Woo template types).

## [1.5.0-beta.1] — 2026-06-12

Pre-release — published on GitHub as a pre-release, so it is **not** offered to installed sites via the auto-updater; install manually to test.

### Added

- Product fields as Etch dynamic data: on `product` posts the plugin enriches Etch's post data (via `etch/dynamic_data/post`, the same seam Etch uses for `gallery_images`) with formatted/derived keys — `price`, `regular_price`, `sale_price`, `price_html`, `price_amount`, `currency_symbol`, `is_on_sale`, `sale_percentage`, `sku`, `product_type`, `stock_status`, `stock_label`, `stock_quantity`, `is_in_stock`, `is_purchasable`, `is_featured`, `rating`, `rating_count`, `review_count`, `add_to_cart_url`, `add_to_cart_text`, `weight`, `dimensions`, `upsell_ids`. Usable as `{this.*}` in Single templates and `{item.*}` in loops; renders live in the builder canvas. Keys Etch sets itself are never overwritten. Filters: `woo4etch/expose_product_data`, `woo4etch/product_data`.

## [1.4.1] — 2026-06-11

### Security

- HTML-capable shortcode attributes are now filtered through `wp_kses_post()` — `delimiter`/`wrap_before`/`wrap_after` on `[woo_breadcrumb]`, `sep`/`before`/`after` on `[woo_categories]` and `[woo_tags]`. Wrapper HTML keeps working; scripts and event handlers are stripped, so authors without the `unfiltered_html` capability cannot inject them via shortcode attributes.
- Updater: release packages are only accepted from this repository's GitHub Releases download URL (defense in depth against a tampered API response).

## [1.4.0] — 2026-06-02

### Added

- Account & order data as Etch dynamic data, so the My Account and thank-you/order pages can be built as pure Etch loops too: `{options.account_menu}` (key, label, url, is_active), `{options.account_orders}` (id, number, date, status, status_name, total, item_count, view_url) and `{options.order}` (number, date, status, status_name, total, email, payment_method, billing_address, items[]). Real data on the frontend, sample data in the Etch builder. Filters: `woo4etch/expose_account_data`, `woo4etch/account_order_data`, `woo4etch/account_orders_limit`, `woo4etch/account_orders_sample`, `woo4etch/order_sample`.
- With the cart bridge from 1.3.0 this means the whole shop — single product, shop archive, cart, mini-cart, My Account, checkout summary and thank-you — can be built as editable Etch layouts that preview in the builder.

## [1.3.0] — 2026-06-02

### Added

- Cart as Etch dynamic data on Etch's `options` root: `{options.cart_items}` (key/id/name/sku/quantity/price/subtotal/permalink/image/remove_url/on_sale) plus `cart_count`/`cart_subtotal`/`cart_total`/`cart_url`/`checkout_url`/`cart_nonce`/`cart_is_empty`. Real cart on the frontend, sample rows in the builder canvas. Filters: `woo4etch/expose_cart_data`, `woo4etch/cart_data`, `woo4etch/cart_image_size`, `woo4etch/cart_sample_data`.
- New `[woo_cart_items]`: a complete, extension-compatible cart form with clean class-based markup (items, coupon, update + nonce, remove) that fires every WooCommerce cart hook and per-item filter. The customisable alternative to the monolithic `[woocommerce_cart]`; pair with `[woo_cart_totals]` / `[woo_cross_sells]`.
- Gap-filling shortcodes for the remaining PHP-bound areas: `[woo_account_menu]`, `[woo_account_content]`, `[woo_login_form]`, `[woo_order_details]`, `[woo_cart_totals]`, `[woo_coupon_form]`, `[woo_shipping_calculator]`, `[woo_cross_sells]`, `[woo_product_search]`. `[woo_if]` now also supports `is_user_logged_in` and `is_type`.
- Automatic WooCommerce theme support: the plugin declares `add_theme_support('woocommerce')` on behalf of the theme (late, only when no theme already does), so the official Etch theme needs no manual snippet. Filterable via `woo4etch/auto_theme_support`, `woo4etch/theme_support_args`, `woo4etch/gallery_features`.
- Expanded shortcode library across product data, media (`[woo_image]`, `[woo_gallery]`), product UI (`[woo_add_to_cart_url]`, `[woo_tabs]`, `[woo_related]`, `[woo_upsells]`), cart, account, and store/archive areas; new conditional `[woo_if cond="..."]…[/woo_if]`.
- Admin reference now also lists the native WooCommerce core shortcodes for discoverability.

### Fixed

- Cart-dependent shortcodes are guarded against a null `WC()->cart` (Etch builder / REST preview), so they render empty instead of fataling.
- `[woo_add_to_cart]` rendered nothing on themes without add-to-cart template overrides (e.g. the Etch theme); it now fires the `woocommerce_{type}_add_to_cart` action like WooCommerce core.
- `[woo_template]` now uses `wc_get_template()`, which falls back to WooCommerce's own templates directory.

## [1.2.2] — 2026-05-21

### Added

- Declare WooCommerce HPOS (High-Performance Order Storage) compatibility.

### Changed

- Updater: cache failed/empty GitHub API responses for an hour to avoid repeated requests when offline or rate-limited.

## [1.2.1] — 2026-05-19

### Added

- GitHub release updates: WordPress shows new versions when a GitHub Release with `woo4etch.zip` is published.

## [1.2.0] — 2026-05-19

### Changed

- Renamed from Woo4Etch Bridge to Woo4Etch; plugin folder `woo4etch/`.
- Snippets live in `includes/customizations.php` (one package, regular or MU install).

## [1.1.0] — 2026-05-19

### Added

- Admin shortcode reference under the Etch menu (auto-detect; WooCommerce fallback).

## [1.0.0] — 2026-05-19

### Added

- Initial release with 17 shortcodes.

[1.5.0-beta.4]: https://github.com/tobiashaas/woo4etch/releases/tag/v1.5.0-beta.4
[1.5.0-beta.3]: https://github.com/tobiashaas/woo4etch/releases/tag/v1.5.0-beta.3
[1.5.0-beta.2]: https://github.com/tobiashaas/woo4etch/releases/tag/v1.5.0-beta.2
[1.5.0-beta.1]: https://github.com/tobiashaas/woo4etch/releases/tag/v1.5.0-beta.1
[1.4.1]: https://github.com/tobiashaas/woo4etch/releases/tag/v1.4.1
[1.4.0]: https://github.com/tobiashaas/woo4etch/releases/tag/v1.4.0
[1.3.0]: https://github.com/tobiashaas/woo4etch/releases/tag/v1.3.0
[1.2.2]: https://github.com/tobiashaas/woo4etch/releases/tag/v1.2.2
[1.2.1]: https://github.com/tobiashaas/woo4etch/releases/tag/v1.2.1
[1.2.0]: https://github.com/tobiashaas/woo4etch/commits/main
[1.1.0]: https://github.com/tobiashaas/woo4etch/commits/main
[1.0.0]: https://github.com/tobiashaas/woo4etch/commits/main
