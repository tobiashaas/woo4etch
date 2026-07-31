=== Woo4Etch ===
Contributors: tobiashaas
Tags: woocommerce, etch, shortcodes, page-builder
Requires at least: 6.0
Tested up to: 6.7
Requires PHP: 8.1
Stable tag: 1.8.0
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
* `[woo_checkout_block]` — embed WooCommerce's native Checkout BLOCK inside an Etch layout: full native protections (incl. card-testing rate limiting) and every gateway's official client integration; customize via the Additional Checkout Fields API + CSS while Etch owns the surroundings

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
* `[woo_notices format="plain"]` — queued WooCommerce notices; `plain` renders minimal `.w4e-notice` markup styleable in Etch (default: Woo's template markup)

Conditional:

* `[woo_if cond="is_product"]…[/woo_if]` — render only when a Woo conditional is true (prefix `!` to negate; supports page and product conditionals)

Templates:

* `[woo_template name="single-product/related"]` — load any Woo template part

The reference page also lists the native WooCommerce shortcodes (`[woocommerce_cart]`, `[woocommerce_checkout]`, `[woocommerce_my_account]`, `[products]`, `[product_page]`, `[product_categories]`, `[add_to_cart]`, …) so every option is in one place. Those are registered by WooCommerce itself, not Woo4Etch.

= Ready-made layouts =

Under Etch → Woo4Etch → Ready-made layouts the plugin ships complete, editable Etch layouts: shop archive (working filter sidebar with category counts and a dual-handle price slider, category slider), category archive (SEO intro + term description), single product (gallery, type-aware add-to-cart), cart (quantity update, coupon, cross-sells, empty state), checkout (Store API "option A+": contact/billing fields, country select, live shipping selector, payment methods, Germanized legal checkboxes, order summary with coupon — natively rate-limited order placement, classic no-JS fallback), header mini-cart (hover dropdown with empty state), My Account (login gate, dashboard, orders), thank-you and a Woo notices region (also installable as an Etch component).

"Add to page/template" installs each layout straight where it renders — the plugin resolves WooCommerce's page assignments and the area's Etch template, appends without touching existing content, and refuses double-inserts. "Copy JSON" exports Etch's native paste format instead. Everything previews live in the builder via the plugin's dynamic-data bridges ({options.cart_items}, {options.shop_categories}, {options.account_menu}, {options.order}, …).

All shipped styles are written as Automatic.css tokens with plain fallbacks (var(--radius, 14px), var(--primary, #111827), var(--space-s, 12px), buttons on the --btn-* tokens): on an ACSS site the layouts follow the site's palette, spacing, radius and type scale out of the box; without ACSS they render the same neutral look as before.

= Frontend enhancements =

* Store API cart interactions (Settings checkbox, on by default): add-to-cart submits, cart quantity changes, coupon apply/remove and item removal go through WooCommerce's Store API (/wc/store/v1/cart/*) without page reloads — Woo's own validation, stock checks and error messages included. Reads stay server-rendered: after every write the marked [data-w4e-cart-region] elements re-render as your own Etch HTML, so any hand-built layout live-updates without a markup convention. Forms with third-party extra fields (product add-ons etc.) keep the classic POST; everything degrades to the classic flow without JS.
* Store API checkout (1.8.0+, same setting): mark your hand-built checkout form with data-w4e-checkout — live shipping/totals recalculation on address edits, shipping-rate selection, and order placement through WooCommerce's natively rate-limited Store API checkout endpoint, for redirect/offline gateways (Mollie, PayPal, COD, invoice, bank transfer). Full markup control and native protections at the same time.
* Variation pills + quantity stepper (Settings checkbox): native attribute selects become pill buttons, quantity fields get −/+ steppers — on product pages and cart rows. Yields automatically to dedicated swatch plugins.
* WooCommerce gallery scripts (Settings checkbox): zoom, lightbox and thumbnail slider on hand-written Etch gallery markup — including block themes, where WooCommerce itself never loads them. The gallery styling ships inside the layout's Etch class record (editable in the builder), not as a plugin stylesheet.
* Price-range slider on archives: min_price/max_price filter forms are enhanced into a dual-handle slider; filtering stays native WooCommerce.
* Archive filters for Etch loops: WooCommerce applies ?min_price / ?filter_<attribute> to the main query only — the plugin re-applies them to Etch's main-query loop on shop, category and tag pages.

= Hardening =

* Checkout rate limiting (Settings checkbox): protection against card-testing attacks on the classic shortcode checkout — WooCommerce's native limiter only covers the block checkout. Mirrors Woo's defaults (3 place-order attempts / 60 s per client fingerprint); tunable via woo4etch/checkout_rate_limit.

Restrict which hooks `[do_action]` may fire:

`add_filter('woo4etch/allow_do_action', function ($allowed, $hook) {
    return strpos($hook, 'woocommerce_') === 0; // only woo_* hooks
}, 10, 2);`

== Frequently Asked Questions ==

= The update fails with "some files could not be copied. This is usually due to inconsistent file permissions." =

WordPress could not overwrite the existing files under wp-content/plugins/woo4etch/ because their ownership/permissions no longer match the user PHP runs as (this happens occasionally on managed hosts, e.g. after certain GridPane provisioning or development steps — it is not a Woo4Etch bug). Fix: reset the file permissions for the site (GridPane ships a one-click permission-reset tool; the generic equivalent is chown-ing wp-content/plugins/woo4etch/ to the PHP user with directories 755 and files 644), then retry the update. Since 1.6.3 the updater detects this before touching any files and aborts with this exact guidance instead of failing mid-update.

== Installation ==

1. Upload the `woo4etch` folder to `/wp-content/plugins/` (or `/wp-content/mu-plugins/woo4etch/` as an MU-plugin).
2. Activate from `Plugins → Installed Plugins` (regular install only).
3. Drop shortcodes into your Etch templates. Add hooks from the templates into `includes/customizations.php`.

WooCommerce must be installed and active.

In the admin, open **Etch → Woo4Etch** for a table of all shortcodes with copy buttons (or **WooCommerce → Woo4Etch** when Etch is not active).

== Changelog ==

= 1.8.0 =
* New: Store API checkout ("option A+") — add data-w4e-checkout to your hand-built checkout form and the layer upgrades it in place: address edits recalculate shipping/totals live (cart/update-customer), shipping picks go through select-shipping-rate, and the order is placed via POST /wc/store/v1/checkout — putting the fully hand-written Etch checkout under WooCommerce's NATIVE checkout rate limiting (Advanced → Features) and Store API validation, following payment_result.redirect_url to hosted payment pages (Mollie, PayPal) or order-received (COD, invoice, bank transfer). [data-w4e-checkout-region] blocks re-render as server-side Etch HTML after every write. Redirect/offline gateways only (filter woo4etch/store_api_checkout_gateways); other gateways and no-JS submit classically.
* New: checkout bridge {options.checkout} — payment_methods, shipping_rates, checkboxes (Germanized legal checkboxes, same relevance filtering as its own block integration), needs_shipping and the classic-fallback nonce as Etch dynamic data with builder sample previews: payment list, shipping selector and legal checkboxes are hand-written Etch loops.
* Compliance: Germanized's Store API checkbox validation is skipped when a request lacks its extensions key — the layer ALWAYS sends it (with every data-w4e-checkbox input's state) while Germanized is active, so required legal confirmations are enforced instead of silently bypassed.
* Change: shipped layout styling now uses Automatic.css tokens with plain fallbacks (var(--radius, 14px), var(--primary, #111827), --btn-* on buttons, --border-color-dark for borders on light surfaces) — on ACSS sites layouts follow the site's palette/spacing/radius/type out of the box; without ACSS rendering is pixel-identical. Existing installs keep their records; reset via tools/reset-layout-styles.php (repo).

= 1.7.0 =
* New: Store API cart interactions (on by default, Settings checkbox) — the classic cart/product markup is upgraded in place: add-to-cart, quantity updates, coupon apply/remove and item removal write through WooCommerce's Store API (/wc/store/v1/cart/*) with no page reload; afterwards the plugin refetches the page and swaps every [data-w4e-cart-region] element with freshly server-rendered Etch HTML (fallback: .w4e-cart/.w4e-minicart; .mini-cart-count text-synced). No client-side templating, no markup contract beyond Woo's own field names; add-to-cart forms with third-party extra fields intentionally keep the classic POST; everything falls back to the classic flow without JS. Dispatches woo4etch:cart-updated and triggers wc_fragment_refresh for fragment-based mini-carts.
* New: [woo_checkout_block] — embed WooCommerce's native Checkout block inside an Etch layout: full native protections (incl. Store API card-testing rate limiting) and every gateway's official client integration, while Etch owns everything around it.
* New: cart bridge keys {options.cart_coupons} (code/amount/remove_url per applied coupon), {options.cart_discount} and {options.cart_shipping_total}; the ready-made cart layout gains per-coupon discount lines with a working remove link.
* Docs: Store API layer reference in the Store API template; cart/mini-cart/product templates rewritten onto the real bridge keys; Store API rate-limit snippet.
* New: opt-in checkout rate limiting for the classic shortcode checkout (Settings checkbox) — WooCommerce's native card-testing protection only covers the block checkout's Store API path; ?wc-ajax=checkout had none. Mirrors Woo's block defaults (3 attempts / 60 s per client fingerprint: proxy-aware IP + user agent + accept-language), rejects further submits with a checkout error notice, tunable via woo4etch/checkout_rate_limit. Security guidance incl. defense-in-depth options documented in the checkout template.
* Fix: the self-updater pre-checks that all plugin files are writable BEFORE the update touches anything, and aborts with actionable guidance (permission reset / chown) instead of WordPress's mid-update "some files could not be copied" failure. FAQ entry added.

= 1.6.2 =
* Fix: unstyled cart / "You may also like" on sites with pre-existing empty style records. The style merger reused existing records by selector without ever writing CSS into them — an EMPTY record (e.g. created by an earlier install or builder session) therefore shadowed the shipped styles forever. Empty records for the plugin's own .w4e-* selectors are now filled with the shipped CSS on install/push (IDs and block references stay untouched); non-empty records and generic selectors like .button remain exactly as the site defines them.

= 1.6.1 =
* Fix: widget styling now lives IN Etch, not in plugin stylesheets. The pills/quantity-stepper and price-slider CSS moved from JS-injected stylesheets into the layouts' Etch class records (nested rules on .w4e-product-info, .w4e-cartrow and .w4e-filter__form — visible and editable in the builder's style panel, rendered exactly with the blocks). assets/gallery.css removed: the gallery styling already ships inside the layout's w4e-gal class record. The scripts keep a small inline fallback only for installs without Etch. Bonus: because the rules now render in site context, their var(--primary, …)-style token fallbacks pick up ACSS variables automatically where present.

= 1.6.0 =
* New: "Category archive" layout — installs into taxonomy-product_cat: term title, editable SEO intro copy (placeholder), term description via Etch's native {term.description}, filter sidebar + grid. Duplicate as taxonomy-product_cat-{slug} for per-category pages.
* Fix: the native archive filters (min_price/filter_*) now also apply on category/tag pages — taxonomy archives leave post_type empty, which the secondary-query bridge previously required to be 'product'.
* New: dual-range price slider (assets/price-slider.js) — progressive enhancement for min_price/max_price filter forms on product archives: two-handle slider synced with the number fields, native GET submission, bound from {options.shop_max_price_raw}. Disable via woo4etch/enqueue_price_slider.
* New: the mini-cart layout is a full hover/focus dropdown — item rows, subtotal, view-cart/checkout buttons, and a "Your cart is empty." message instead of an empty box. Pure CSS (:hover/:focus-within), no JS.
* New: category slider on the shop archive — horizontally snapping round image cards linking to the term archives (CSS scroll-snap, swipeable).
* New: shop archive layout with working filter sidebar — heading, category pills, category list with counts, native price filter (min_price/max_price) and reset; restyled cards (image tile, Sale pill). The plugin re-applies WooCommerce's native archive filters (price, filter_<attribute>) to Etch's main-query loop (a secondary query Woo ignores by default; woo4etch/filter_secondary_product_queries). New keys: {options.shop_categories}, {options.shop_max_price}, {options.filter_min_price}/{options.filter_max_price} (+ {woo.shop.*}).
* Fix: sale prices in the data bridges no longer include Woo's screen-reader text ("Original price was: ...").
* Fix: pills.js backs off when a dedicated variation-swatch plugin owns the attribute selects (detected via marker classes + hidden state, with a deferred sweep for late-loading plugins) — no doubled UI; the quantity stepper keeps working. Third-party integration seams documented in the plugin guide (hook islands ✓ Germanized, checkout/payment untouched ✓ Mollie, blockified-detection trap → plugin shortcode ✓ YITH, swatch plugins → pills yield).
* Fix: the cart layout's "Update cart" button stayed disabled forever — Woo's cart.js only re-enables it on changes inside .woocommerce-cart-form .cart_item, a contract class the layout rows didn't carry. Rows now ship cart_item; documented in the cart template.
* Fix: cross-sells exclude out-of-stock / non-purchasable products — in maintained Linked-Products lists and in the random-catalog fallback.
* New: the quantity stepper (pills.js) also builds on the cart page's line-item quantity fields; stepping re-enables Woo's update button.
* Fix: account layout shows WooCommerce's login/register form to guests instead of an empty dashboard — new bridge key {options.is_logged_in} / {woo.account.is_logged_in} (true in the builder), shipped layout gated on it.
* Fix: the quantity stepper (pills.js) now targets input[name="quantity"] inside form.cart — it silently didn't build on hand-built forms without Woo's classic .quantity/.qty classes; the shipped product layout also carries the classic markup now.
* Fix: plugin updates no longer clobber a shipped, improved customizations.php skeleton. The upgrader hooks couldn't distinguish "user edited the file" from "the update ships an improved skeleton" and restored the old skeleton over the new one for users who never edited it. Preservation now keys off the shipped skeleton's fingerprint: only user-edited files are carried across updates.
* New: integration test layer (tests/integration/) — non-destructive wp-cli checks (upgrader preservation, {woo.*} data shape, external customizations file, frontend smoke) runnable against wp-env, a local install, or a staging server over SSH.
* New: gallery companion CSS (assets/gallery.css), enqueued with the gallery scripts — replaces what Woo's disabled stylesheets would provide: opacity guard (no invisible gallery on late/failed JS init), full-width flex-viewport, stable thumbnail grid matching data-columns (with column variants), active-thumb border, styled lightbox trigger, grid-blowout guard. Token-based with fallbacks; disable via woo4etch/enqueue_gallery_css. The single-product layout's gallery CSS upgraded to match.
* New: variation pills + quantity stepper (assets/pills.js) — optional zero-markup enhancement (Settings checkbox, off by default; woo4etch/enqueue_pills filter): native attribute selects become accessible pill buttons, quantity inputs get a −/+ stepper. Woo's variation JS stays leading. The inverse of swatches.js (hand-built markup); both drive the same native change event. Token-based styling with plain fallbacks, localized aria-labels.
* New: "Add to page/template" — layouts install straight to where they render: cart/account onto the WooCommerce-assigned pages, shop archive / single product / order confirmation into the area's Etch template (created bare when missing). Append-only, refuses double-inserts, shows "On its page ✓" once present. Copy JSON stays for manual placement.
* Fix: the layout installer binds every literal class to an Etch style record (existing records with the same selector are reused, empty ones created otherwise). Without a referenced record, Etch's save reconciliation stripped record-less classes — including the Woo contract classes cart / single_add_to_cart_button / button / quantity — on the first builder save, silently breaking variation + add-to-cart JS. Applies to install, health-check insert, component install and the copy/paste JSON alike.
* Change: "Install as pattern" removed — "Add to page/template" installs layouts directly where they render (append-only, refuses double-inserts), which makes the pattern-library detour redundant. Copy JSON stays for manual placement. The "Woo Notices" component install moved into the layouts table (notices row). A one-time cleanup trashes the beta installer's library patterns (recoverable; inserted copies untouched) and removes its tracking options + empty pattern category. The "On …" state links to the page/template editor.
* New: `[woo_product_attributes]` — the "Additional information" attributes table (visible attributes + weight/dimensions) as a shortcode. Needed because the woocommerce_product_additional_information hook expects the product as a do_action argument, which the hook island cannot pass.
* New: `woo4etch/cart_item_payload` filter — adjust each cart-item payload (e.g. strip Germanized's gzd-* rows from `meta`) before it reaches `{options.cart_items}`.
* New: `{this.is_sold_individually}` exposed in the product data bridge — hide the quantity input for one-per-order products via `{#if !this.is_sold_individually}`.
* New: `[woo_notices format="plain"]` — Woo notices as minimal class-based markup (.w4e-notice + --error/--success/--notice), styleable in Etch. Default format unchanged.
* New: `{options.shop_url}` / `{woo.shop.url}` — shop page URL for empty-cart "Return to shop" links.
* New: "Woo notices" ready-made layout — the feedback region ([woo_notices format="plain"] in a styled .w4e-notices wrapper) as a standalone install/paste, for any layout that needs Woo feedback. The single-product and account layouts now include the same block (add-to-cart errors, login/address feedback); the cart layout already has it.
* New: "Woo Notices" as a real Etch component — one click on the admin page installs the notices region as an Etch component (server-side: wp_block post + Etch component meta, styles merged like the pattern installer). Idempotent: reinstalling updates the existing component in place. Place instances from the builder's component library for one globally editable notices region.
* Fix: the cart layout (one-click pattern + cart.json) now has an empty-cart state — previously coupon, totals and the checkout button rendered on an empty cart with no message — and outputs Woo notices via [woo_notices format="plain"], so "Cart updated." and coupon/security errors are visible instead of updates failing silently.
* Fix: the notices region hides itself while empty (no reserved blank space); the builder canvas shows a sample notice so the region stays styleable.
* Fix: cart cross-sells fall back to random catalog products when no Linked-Products cross-sells are maintained (disable: woo4etch/cross_sells_fallback filter), and the "You may also like" section disappears entirely when the list is empty.
* New: Page health check on the admin page — resolves WooCommerce's cart/checkout/account page assignments and verifies the layout + notices elements exist there (page content and Etch templates are searched); missing elements can be inserted straight into the assigned page.
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
