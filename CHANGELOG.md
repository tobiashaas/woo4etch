# Changelog

All notable changes to the **Woo4Etch plugin** (`plugin/woo4etch/`) are documented here.
The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/); versions follow [Semantic Versioning](https://semver.org/).

Releases are published as [GitHub Releases](https://github.com/tobiashaas/woo4etch/releases); regular plugin installs self-update from there. The same changelog ships inside the plugin in `plugin/woo4etch/readme.txt` — keep both in sync.

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

[1.4.1]: https://github.com/tobiashaas/woo4etch/releases/tag/v1.4.1
[1.4.0]: https://github.com/tobiashaas/woo4etch/releases/tag/v1.4.0
[1.3.0]: https://github.com/tobiashaas/woo4etch/releases/tag/v1.3.0
[1.2.2]: https://github.com/tobiashaas/woo4etch/releases/tag/v1.2.2
[1.2.1]: https://github.com/tobiashaas/woo4etch/releases/tag/v1.2.1
[1.2.0]: https://github.com/tobiashaas/woo4etch/commits/main
[1.1.0]: https://github.com/tobiashaas/woo4etch/commits/main
[1.0.0]: https://github.com/tobiashaas/woo4etch/commits/main
