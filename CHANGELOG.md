# Changelog

All notable changes to the **Woo4Etch plugin** (`plugin/woo4etch/`) are documented here.
The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/); versions follow [Semantic Versioning](https://semver.org/).

Releases are published as [GitHub Releases](https://github.com/tobiashaas/woo4etch/releases); regular plugin installs self-update from there. The same changelog ships inside the plugin in `plugin/woo4etch/readme.txt` — keep both in sync.

## [1.6.2] — 2026-07-30

### Fixed

- **Unstyled cart / "You may also like" on sites with pre-existing empty style records.** `merge_styles` reused existing records by selector but never wrote CSS into them — so an **empty** record (left behind by an earlier install or builder session) shadowed the shipped styles forever; on the staging build this left the cart aside, cross-sell cards, coupon field and badges completely unstyled while the local demo's `demo.css` masked the same gaps. Empty records for the plugin's own `.w4e-*` selectors are now filled with the shipped CSS during install/push (record IDs and block references stay untouched, so nothing else moves); **non-empty** records and generic contract selectors (`.button`, `.cart`, …) remain exactly as the site defines them. The demo environment's `demo.css` was cut down to the bare shell (header/footer/reset) so it can never mask shipped-record gaps again.

## [1.6.1] — 2026-07-30

### Fixed

- **Widget styling now lives IN Etch — never as a plugin stylesheet.** 1.6.0 shipped the pills/quantity-stepper and price-slider CSS as JS-injected stylesheets and the gallery companion as `assets/gallery.css` — all of it outside Etch, invisible in the builder's style panel and fighting the user's class edits. The CSS moved into the layouts' **Etch class records** as nested rules: pills/stepper/variations-reset under `.w4e-product-info`, the cart stepper under `.w4e-cartrow`, the range slider under `.w4e-filter__form`; the gallery set already lived in `.w4e-gal`, so `assets/gallery.css` is removed (`woo4etch/enqueue_gallery_css` gone with it). The records render exactly with the blocks (Etch tree-shakes unreferenced class records — verified against the Etch source), are editable per class in the builder, and — bonus — their `var(--primary, …)` token fallbacks now resolve real ACSS variables in site context. The scripts keep their inline CSS only as a fallback for installs without Etch (localized `stylesInEtch` flag).

## [1.6.0] — 2026-07-30

First full release after the 1.5.0 beta series — sites on 1.4.x are offered this update (it includes everything from betas 1–6 below). Findings from a production build and a full E2E test round (Caracciolo Olivenöl staging — Etch 1.6.4, WooCommerce block theme, Germanized, Mollie — plus wp-env with Germanized, YITH Wishlist and Variation Swatches).

### Added

- **"Category archive" layout for SEO category pages.** Installs into `taxonomy-product_cat`: term title, an editable intro copy block (placeholder text — write category-specific copy), the term description from Products → Categories via Etch's native `{term.description}` key (Raw HTML block — descriptions may contain markup), then the same filter sidebar + grid as the shop (shared block builders). Per-category pages: duplicate as `taxonomy-product_cat-{slug}` in the editor — the hierarchy picks it up automatically. Template 03 documents the pattern. Note: category pages worked before via `archive-product` fallback; this adds the copy layer.
- **Dual-range price slider (`assets/price-slider.js`).** Progressive enhancement on product archives: any form carrying WooCommerce's `min_price`/`max_price` inputs gets a two-handle range slider (overlapping native range inputs, highlighted active segment, keyboard-accessible, grab cursors) inserted above the fields — slider and number fields stay in sync both ways, handles restore from the URL after filtering, submission stays the plain native GET. Upper bound from `data-w4e-price-max` (new bridge key `{options.shop_max_price_raw}`, bound by the shipped layout) with a graceful fallback. Token-based styling with plain fallbacks; disable via `woo4etch/enqueue_price_slider`.
- **Mini-cart layout is now a full hover/focus dropdown with a proper empty state.** The header mini-cart grew from a bare link into link + count + dropdown panel: item rows (image, name, qty × price, line subtotal), subtotal row, view-cart/checkout buttons — and when the cart is empty, a "Your cart is empty." message instead of an empty box with orphaned action buttons. Pure CSS reveal (`:hover` + `:focus-within`, keyboard-accessible, no JS).
- **Category slider on the shop archive** — the category quick links are a horizontally snapping strip of round image cards (CSS scroll-snap, swipeable on touch, right edge-fade hints at more; long single-word category names wrap instead of clipping) linking to the term archives.
- **Shop archive layout redesigned: heading + category pills + working filter sidebar.** The product-grid layout now ships a page title (`{archive.title}`), a category quick-link bar (image pills), and a filter sidebar (category list with counts + active state, price min/max form, reset) next to the product grid; cards restyled (light image tile, red "Sale" pill, bold price). The filters are **native WooCommerce GET params** (`min_price`/`max_price`, `filter_<attribute>`, `orderby`) — crucially, Woo applies them to the *main* query only, and Etch's main-query loop runs a *secondary* query, so the plugin now re-applies both the price clauses (meta-lookup join, same range semantics as `WC_Query`) and layered-nav attribute tax queries to secondary product queries on Woo archives (`woo4etch/filter_secondary_product_queries` to disable). New bridge keys: `{options.shop_categories}` (id/name/slug/url/count/image/is_active — top-level, default bucket excluded), `{options.shop_max_price}`, `{options.filter_min_price}`/`{options.filter_max_price}`; also under `{woo.shop.*}`. Template 03 documents the whole native-filter toolbox. Sale prices in all bridges no longer leak Woo's screen-reader helper text ("Original price was: …").
- **Integration test layer (`tests/integration/`, issue #12 layers 4–5).** Non-destructive `wp eval-file` checks that run against any real WordPress + WooCommerce install — the repo's wp-env (`run.sh --wp-env`, also a new CI job), a local site (`--path`), or a staging server over SSH (`--ssh`): upgrader preservation of `customizations.php` (both directions: edits survive, untouched skeletons are not resurrected), `Woo4Etch_Woo_Root::build_data()` shape with WooCommerce active, external `wp-content/woo4etch-customizations.php` loading (verified in a fresh WP boot), and a frontend smoke (Woo-assigned pages respond, installed layouts render their markers, the product page renders the Woo add-to-cart contract). No wp-phpunit needed — deliberately, so the suite is safe against live staging sites (typical managed hosts grant only one database, which rules the DB-wiping harness out there).
- **Gallery companion CSS (`assets/gallery.css`)** (#20). Enqueued together with the gallery scripts: the styling WooCommerce's own stylesheets would provide for the gallery DOM, for builds that disable them ("Disable WooCommerce default styles" — exactly the situation where the gallery previously fell into an unstyled hole). Production-proven guards: `opacity: 1 !important` against the inline fade-in style when the gallery JS initialises late or errors out (gallery stayed invisible), full-width `.flex-viewport` (shrinks-to-fit mid-init inside grid/flex parents), `min-inline-size: 0` grid-blowout guard, thumbnail strip as a **stable grid** matching `data-columns` (the old `flex: 1` stretched thumbs when there were fewer than N images; `--columns-2/3/5` variants included), `--primary` active/hover border on thumbs, and the 🔍 lightbox trigger as a proper round button. Design tokens with plain fallbacks; low-specificity class selectors; disable via `woo4etch/enqueue_gallery_css`. The shipped single-product layout's scoped gallery CSS is upgraded to match (its higher specificity would otherwise win with the outdated rules), and template 01's caveats now document the full set.
- **Variation pills + quantity stepper (`assets/pills.js`)** (#19). Optional zero-markup progressive enhancement on single product pages (Settings checkbox, off by default; `woo4etch/enqueue_pills` filter): the native attribute `<select>`s become accessible pill buttons (`.w4e-pill`, `aria-pressed`, focus-visible outline) and every `.quantity input.qty` is wrapped in a −/+ stepper (`.w4e-qty`). WooCommerce's variation JS stays the source of truth — a pill click sets the native select and dispatches its bubbled `change` event, so price/stock/`variation_id` keep working untouched. The inverse of `swatches.js` (hand-built markup → native select); both drive the same native event — use one per form. Injected CSS uses design tokens (`--primary`, `--space-*`, `--radius`, `--text-*`) with plain fallbacks; aria-labels localized via `wp_localize_script`. Production-proven (previously carried as a script block inside a site template — which a re-scaffold promptly deleted; that's why it belongs in the plugin). Companion buy-box CSS for variable products documented in template 02.
- **"Add to page/template" — layouts push straight to where they render.** The admin page now resolves each layout's real target and inserts it there in one click, no pattern-library detour: cart and account go onto the WooCommerce-assigned pages (WooCommerce → Settings → Advanced), shop archive / single product / order confirmation go into the Etch `wp_template` for the area (`archive-product`, `single-product`, `order-confirmation`; created bare when missing — add your site frame in the builder). Strictly append-only: existing content is preserved, and a target that already contains the layout is refused (`woo4etch_already_present`) instead of double-inserting. The table shows "On its page ✓" once the layout is found at its target. This **replaces the previous "Install as pattern" route entirely** — installing directly where the layout renders makes the pattern-library detour (and its reinstall/update-tracking machinery) redundant; Copy JSON stays for manual placement, which is also the route for the mini-cart (it lives in the site header). The "Woo Notices" Etch-component install lives in the layouts table (notices row) instead of a separate section. A one-time cleanup trashes the library patterns the beta installer created (recoverable from the trash; inserted copies on pages are untouched) and drops the old tracking options + the empty "Woo4Etch" pattern category. Once a layout is at its target, the "✓ On …" state links straight to the page/template editor.
- **Layout installer binds every literal class to an Etch style record** (#21). Etch's save reconciliation keeps a class on a block only while the block also references a style record whose selector matches it — classes shipped without a record (above all the WooCommerce contract classes `cart`, `single_add_to_cart_button`, `button`, `quantity`) were silently stripped on the first builder save, breaking Woo's variation/AJAX JS contract while the page kept rendering. The layout build now attaches a record reference for every literal class (creating empty records where none exist; existing site records with the same selector are reused, never overwritten). Applies to the page push, the health-check insert, the component installer and the copy/paste JSON artifacts alike; CI asserts the invariant for both the live block trees and the committed artifacts. Dynamic classes (`stock--{this.stock_status}`) are exempt — no static selector can match them.
- **`[woo_product_attributes]`** — the "Additional information" attributes table (visible attributes plus weight/dimensions) as a shortcode; empty output when the product has no data. Needed because `woocommerce_product_additional_information` expects the product as a `do_action` **argument**, which neither `[do_action]` nor the `data-w4e-hook` island passes — the hooked `wc_display_product_attributes()` would receive `null`.
- **`woo4etch/cart_item_payload` filter** — adjust each cart-item payload before it reaches `{options.cart_items}` / `{woo.cart.items}`. Primary use case: WooCommerce Germanized injects raw `gzd-*` rows (delivery time, item description) via `woocommerce_get_item_data` at priority ≥ 1000, which `{item.meta}` otherwise dumps verbatim into custom cart layouts.
- **`{this.is_sold_individually}`** exposed in the product data bridge, matching its sibling booleans (`is_purchasable`, `is_featured`, …) — lets layouts hide the quantity input for one-per-order products via `{#if !this.is_sold_individually}`. The `[woo_if]` shortcode already supported `sold_individually`; this brings the Etch context to parity. (#15, thanks @zackpyle)
- **`[woo_notices format="plain"]`** — queued Woo notices as minimal class-based markup (`.w4e-notice` + `--error/--success/--notice`) styleable in Etch, for builds that disable Woo's stylesheets. The builder canvas shows a sample notice so the (frontend-wise empty-hidden) region stays styleable. Default format unchanged.
- **`{options.shop_url}` / `{woo.shop.url}`** — shop page URL, for empty-cart "Return to shop" links.
- **"Woo notices" ready-made layout** — the feedback region as a standalone install/paste for any page layout; the single-product and account layouts now inline the same block (add-to-cart errors, login/address feedback), the cart layout already had it. Without a notices region, Woo feedback ("Cart updated.", coupon/security errors) is invisible and failed actions look like silent no-ops.
- **"Woo Notices" as a real Etch component** — one click installs the notices region as an Etch component (server-side: `wp_block` post + Etch component meta, styles merged like the pattern installer; idempotent). New file: `includes/class-woo4etch-components.php`.
- **Page health check** on the admin page — resolves WooCommerce's cart/checkout/account page assignments and verifies the layout + notices elements exist there (page content and Etch templates are searched); missing elements can be inserted straight into the assigned page (append-only). New file: `includes/class-woo4etch-health.php`.

### Fixed

Findings from a full E2E purchase run against wp-env (shop grid → product pages → cart interactions → COD checkout → thank-you → account):

- **Archive filters now also apply on category/tag pages.** Taxonomy archives leave `post_type` empty (the taxonomy implies it), which the secondary-query filter bridge required to be `product` — so `?min_price=…` was silently ignored on category pages. Empty-post_type queries are now accepted when they reference a product taxonomy (`product_cat`/`product_tag`/`pa_*`), which the cloned main query does.

- **`pills.js` backs off when a dedicated swatch plugin owns the selects.** Running both doubled the variation UI. The script now detects foreign swatch UIs (marker classes like `.woo-variation-raw-select` / `.variable-items-wrapper`, plus a generic already-hidden check) before building, and a deferred sweep removes our pills if a late-loading plugin replaced the selects afterwards — the plugin's UI wins, the quantity stepper keeps working. Verified against Variation Swatches for WooCommerce. A new "Third-party WooCommerce plugins" section in template 15 documents all four integration seams (classic hooks via islands ✓ Germanized; checkout/payment untouched ✓ Mollie + Germanized legal; the blockified-detection trap → use the plugin's shortcode, ✓ YITH Wishlist; same-territory UI plugins → pills back off).
- **Cart "Update cart" button stayed disabled forever.** WooCommerce's cart.js disables the update button on load and re-enables it only when an input inside `.woocommerce-cart-form .cart_item` changes — the shipped cart layout's rows didn't carry Woo's `cart_item` contract class, so typing a new quantity never re-enabled the button and updates looked broken. The layout rows now ship `cart_item` (style-record-bound), and template 04's required-classes table documents the contract.
- **Cross-sells never offer what can't be bought** — out-of-stock (without backorders) or non-purchasable products are excluded from `{options.cross_sells}` in both paths: maintained Linked-Products cross-sells (skipped entries are backfilled up to the limit) and the random-catalog fallback (queries `stock_status=instock` and re-checks per product).
- **Quantity stepper on the cart page.** `pills.js` now also enqueues on the cart and builds the same −/+ stepper on the line-item quantity fields (`cart[<key>][qty]`); the bubbled change event re-enables Woo's update button via the `.cart_item` listener.
- **Account layout: guests get a login form instead of an empty dashboard.** As a guest, the layout rendered the account nav plus "Dashboard / Hello !" (no user) — the endpoint switch didn't know about login state. New bridge key `{options.is_logged_in}` / `{woo.account.is_logged_in}` (true in the builder so sections preview); the shipped layout now gates nav + endpoint views on it and shows WooCommerce's real login/register form (`[woo_login_form]`) to guests. Documented in template 07.
- **Quantity stepper worked only with Woo's classic markup.** `pills.js` targeted `.quantity input.qty`, which hand-built Etch forms don't necessarily carry — the stepper silently didn't build on the shipped simple-product layout. It now targets the actual Woo contract, `input[name="quantity"]` inside `form.cart` (hidden/sold-individually inputs skipped); the shipped layout additionally wraps its quantity input in Woo's classic `div.quantity` > `input.qty` markup so third-party scripts targeting the native classes work too.
- **Plugin updates no longer clobber a shipped, improved `customizations.php` skeleton** (found while writing its integration test, #12). The upgrader-preservation hooks compared the pre-update backup against the *freshly shipped* file — which can't distinguish "user edited the file" from "the update ships an improved skeleton", so users who never touched `customizations.php` got the old skeleton restored over the new one on every update. The pre-install hook now decides on the old side using the shipped skeleton's fingerprint (`WOO4ETCH_SKELETON_MD5`, asserted against the actual file by the service-free test layer): only a user-edited file is preserved and restored; an untouched skeleton is never backed up, so improvements arrive.
- **The cart layout (one-click pattern + `cart.json`) now has an empty-cart state** — previously coupon, totals and the checkout button rendered on an empty cart with no message — and outputs Woo notices via `[woo_notices format="plain"]`, so "Cart updated." and coupon/security errors are visible instead of updates failing silently. The notices region hides itself while empty (no reserved blank space).
- **Cart cross-sells fall back to random catalog products** when no Linked-Products cross-sells are maintained (disable via the `woo4etch/cross_sells_fallback` filter); the "You may also like" section disappears entirely when the list is empty.
- **`product-grid.json` copy/paste artifact now binds its archive loop to Etch's seeded main-query preset (`etch_main_query`) instead of the installer-minted `w4e_main_query`** — an id that only exists after the one-click installer ran, so a manual paste on a fresh site referenced a missing preset and the archive could render empty. The one-click installer was unaffected (it resolves the preset live). CI now rejects installer-minted `w4e_*` loop ids in the committed artifacts. (#13)

### Docs

- **Archive context corrected** (10, 03): on product archives in Etch 1.6.x, `{this.title}` resolves to the **first product**, not the archive title, and `{taxonomy.name}` yields the taxonomy slug (`product_cat`), not the term. `{archive.title}` is the key that works for both term archives and the shop page. Search views expose **no** query keyword ( `{search.query}`/`{this.query}` render empty) — use a static heading.
- **Gallery caveat** (01): with Woo styles disabled, give slide images an `aspect-ratio` guard — FlexSlider measures the viewport at init, and images that haven't loaded yet collapse it to 0 (the layout degrades to a bare thumbnail grid; also a CLS win).
- **Copy/paste layouts** (etch-copy): builder-assigned classes ride as style-record references — when those records are lost, the next `saveAsync` silently strips the classes from the blocks. Re-apply critical classes as explicit `class` attributes.
- **New snippets** (13): product option checkbox (pre-assembly & similar) via four hooks incl. the `$_REQUEST` add-to-cart-URL pitfall, and taming Germanized's `gzd-*` rows in classic cart renders (priority `PHP_INT_MAX` + REST guard so the checkout block keeps its mandatory delivery-time display).

## [1.5.0-beta.6] — 2026-06-13

Pre-release — not offered to installed sites via the auto-updater; install manually to test.

### Changed

- **Minimum PHP is now 8.1** (header `Requires PHP`, `readme.txt`, updater fallbacks). Etch itself requires PHP 8.1, so the older 7.4/8.0 floor was dead weight. No code changed — the plugin already ran on 8.1+.

### Fixed

- **Copy/paste layout artifacts were out of sync with the plugin's layouts.** The `templates/etch-copy/*.json` files are generated from the layout definitions, but two had drifted: `product-grid.json` still carried the broken raw `mainQuery` loop target (so a pasted shop archive rendered no products — the same bug fixed for the one-click installer in beta.5, but the copy/paste file was never regenerated), and `product-single.json` still had the pre-beta.5 gallery markup and an escaped (text-element) excerpt. Both regenerated to match the current layouts. The one-click installer was unaffected; this only fixes the manual copy/paste route.

### Internal

- **CI test layer (no WordPress required).** A fast, service-free GitHub Actions job (`tests/php/`) now gates every PR: version-marker sync (`Version` header == `Woo4Etch::VERSION` == `Stable tag`), shortcode-catalog integrity (every entry maps to an existing `shortcode_*` method; every shortcode documented), and layout DSL invariants — most importantly that every `etch/loop` binds to a data path or a `loopId`, never a bare query key like `mainQuery` (the empty-archive bug). A drift guard re-runs `tools/generate-etch-copy.php` and fails if the committed copy/paste artifacts change. The PHP lint matrix now runs 8.1 → 8.5. See `tests/php/README.md`.

## [1.5.0-beta.5] — 2026-06-12

Pre-release — not offered to installed sites via the auto-updater; install manually to test. Everything below was verified live on a staging shop (Etch 1.5.1, WooCommerce 10.8.1, block theme, Germanized).

### Added

- **Experimental `{woo.*}` dynamic-data root** — the shop data from `{options.*}` additionally under a namespaced, structured root: `{woo.cart.items}`, `{woo.cart.count}`, `{woo.checkout.url}`, `{woo.account.menu}`, `{woo.account.endpoint}`, `{woo.account.orders}`, `{woo.order}`, … Both roots are fed by the same builders (identical values, identical builder sample data); registration is lazy (only pages referencing `woo.` assemble the data) and fully guarded — it uses an Etch internal (`DynamicContentRegistry::enqueue()`, no public API exists yet; see ETCH-FEATURE-REQUESTS.md #3), so if Etch refactors it the `woo` root disappears while `{options.*}` keeps working. `options.*` remains the documented, guaranteed spelling. Filters: `woo4etch/enable_woo_root` (off switch), `woo4etch/woo_root_data` (reshape). New file: `includes/class-woo4etch-woo-root.php`.

### Changed

- **Ready-made layouts install without page reloads:** the per-layout "Install as pattern" buttons now run via AJAX (button state and ✓ update in place — no reload, no losing your scroll position), and a new **Install / reinstall all layouts** button installs the whole set in one click. The old form submit remains as a no-JS fallback.
- **Single-product layout: gallery in Woo's native markup.** The gallery now uses Woo's gallery classes and data attributes (`.woocommerce-product-gallery`, `data-thumb`, `data-large_image`, …), so zoom/lightbox/slider work as soon as the gallery scripts are enabled in Settings. Without the scripts the bundled CSS lays the same markup out as featured image + thumbnail grid (thumbnails link to the full-size file); FlexSlider's generated DOM (`.flex-control-thumbs`, viewport) is styled too. New root class `w4e-gal` so reinstalling picks up the new styles (the style merger never overwrites existing selectors).
- **Single-product layout: excerpt is now a Raw HTML element** (`etch/raw-html`) instead of a text element — Woo short descriptions may contain HTML, which etch/text escapes to literal tags. Matches the documented fix in `templates/01-single-product-simple.md`.

### Fixed

- **Variable products couldn't be purchased from the single-product layout:** the buy box only rendered the simple-product form, so variations were unselectable. The layout is now type-aware — simple products keep the hand-built classic-POST form; variable/grouped/external products get WooCommerce's native form (attribute selects with options, variations JSON, price/stock updates; `swatches.js` bridges custom swatch markup on top). Verified live: variation select → add to cart → correct variation in the cart.
- **Live variation price:** `swatches.js` now mirrors the chosen variation's price into every element marked `data-w4e-variation-price` (and restores the original range price on reset). Woo only renders the variation price *inside* the form — the page's prominent price element stayed static. The single-product layout marks its price row accordingly and hides Woo's duplicate in-form price.
- **Hook marker `data-w4e-hook` (kses-proof `[do_action]`):** in Etch raw-html blocks, `[do_action]` output is sanitized — hooks emitting forms/buttons/scripts (express pay, trust widgets) came out broken and looked like "third-party hooks don't work". An empty `<div data-w4e-hook="woocommerce_after_add_to_cart_button" data-w4e-product="{this.id}"></div>` is now filled with the captured hook output after Etch renders; the optional product id sets the global `$product`. Same `woo4etch/allow_do_action` restriction as the shortcode. The single-product layout ships these markers around its simple-product form (the native form for variable products fires the standard hooks itself).
- **WooCommerce's block-template compatibility layer is disabled on block themes** (filter: `woo4etch/disable_block_hook_compatibility`, default on; sets Woo's official `woocommerce_disable_compatibility_layer`). On block themes Woo strips the callbacks from the classic product/shop hooks during template rendering and re-injects them only around `woocommerce/*` blocks — which hand-written Etch layouts don't contain. Result: third-party hook output (Germanized legal info, trust badges, …) silently disappeared **even when fired via `[do_action]` or `data-w4e-hook`**. With the layer off, classic hooks stay intact and Etch layouts fire them explicitly where wanted. Verified live with Germanized (tax notice, shipping-costs notice, delivery time).
- **`data-w4e-skip-defaults` for hook markers:** fires a hook with WooCommerce core's own template callbacks unhooked (restored afterwards) — needed for `woocommerce_single_product_summary`, where plugins like Germanized attach unit price / tax & shipping notices / delivery time *between* core's title/price/excerpt/add-to-cart callbacks; firing the hook plainly would duplicate the layout's own content. The single-product layout ships a summary-extras marker after its price row, so Germanized info appears exactly where shoppers expect it. Core-defaults table filterable via `woo4etch/hook_core_defaults`.
- **New server-side embed marker `data-w4e-add-to-cart`:** an empty `<div data-w4e-add-to-cart="{this.id}"></div>` is filled with Woo's native add-to-cart form *after* Etch renders the block. Needed because Etch's raw-html sanitizer strips `<form>/<input>/<select>` unless the global "allow unsafe raw HTML" setting is enabled (off by default) — a `[woo_add_to_cart]` shortcode in a raw-html block therefore renders a broken form. The marker works in any Etch element, with no Etch security setting changes.
- **New product key `{this.is_simple}` (bool):** condition-safe product-type check. Discovered in live testing: on product posts Etch itself exposes `product_type` as the WooCommerce product_type **taxonomy term object** under the same key, and Etch keys always win — so `{this.product_type}` comparisons silently fail. Use `{this.is_simple}` in conditions (`isTruthy`/`isFalsy`) and `{this.product_type.name}` for display.
- **`wc-add-to-cart-variation` never loaded for hand-built variation forms:** WooCommerce only enqueues it from its own add-to-cart template. The plugin now enqueues it on variable-product pages automatically (filter: `woo4etch/enqueue_variation_script`) — the manual snippet from template 02 is no longer needed.
- **New Dynamic Key `{this.variations_json}`** for hand-built `form.variations_form` markup (`data-product_variations`); computed only for the main product on its own page (`get_available_variations()` is expensive — loop items skip it; force via `woo4etch/expose_variations_json`). Replaces the illustrative filter previously shown in template 02.
- **Plugin updates wiped `includes/customizations.php`:** WordPress replaces the whole plugin folder on update, deleting any snippets users pasted into the file the docs explicitly point them to — breaking the ADR-001 promise that updates never touch user customisations. The upgrader now backs the file up before our plugin updates and restores it afterwards (only when it differs from the freshly shipped skeleton). Additionally, `wp-content/woo4etch-customizations.php` is loaded automatically when present — an opt-in location entirely outside the plugin folder that no update can ever touch.
- **Shop-archive layout rendered no products:** the product grid's loop referenced a raw `mainQuery` target, but Etch only runs query-type loops (main-query/wp-query) through **loop presets** (`etch_loops` option) bound via `loopId`. The layout now resolves the site's existing main-query preset (matching by config type, since the default id isn't guaranteed) or creates one, and binds the loop to it. Verified live on a real product archive.

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

[1.5.0-beta.5]: https://github.com/tobiashaas/woo4etch/releases/tag/v1.5.0-beta.5
[1.5.0-beta.4]: https://github.com/tobiashaas/woo4etch/releases/tag/v1.5.0-beta.4
[1.5.0-beta.3]: https://github.com/tobiashaas/woo4etch/releases/tag/v1.5.0-beta.3
[1.5.0-beta.2]: https://github.com/tobiashaas/woo4etch/releases/tag/v1.5.0-beta.2
[1.5.0-beta.1]: https://github.com/tobiashaas/woo4etch/releases/tag/v1.5.0-beta.1
[1.6.2]: https://github.com/tobiashaas/woo4etch/releases/tag/v1.6.2
[1.6.1]: https://github.com/tobiashaas/woo4etch/releases/tag/v1.6.1
[1.6.0]: https://github.com/tobiashaas/woo4etch/releases/tag/v1.6.0
[1.4.1]: https://github.com/tobiashaas/woo4etch/releases/tag/v1.4.1
[1.4.0]: https://github.com/tobiashaas/woo4etch/releases/tag/v1.4.0
[1.3.0]: https://github.com/tobiashaas/woo4etch/releases/tag/v1.3.0
[1.2.2]: https://github.com/tobiashaas/woo4etch/releases/tag/v1.2.2
[1.2.1]: https://github.com/tobiashaas/woo4etch/releases/tag/v1.2.1
[1.2.0]: https://github.com/tobiashaas/woo4etch/commits/main
[1.1.0]: https://github.com/tobiashaas/woo4etch/commits/main
[1.0.0]: https://github.com/tobiashaas/woo4etch/commits/main
