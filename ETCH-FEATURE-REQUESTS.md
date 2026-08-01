# Upstream feature requests to Etch

What Woo4Etch (and any companion plugin) would need from Etch to integrate more deeply. All findings verified against the Etch plugin source, version 1.5.0. Written to be shared with the Etch team — each request includes the current state in the source, the gap, and a minimal proposal.

## Context: what already works well

Etch's dynamic-data filters are a great integration seam, and Woo4Etch builds entirely on them today:

- `etch/dynamic_data/option` (`classes/Traits/DynamicData.php`) — Woo4Etch exposes the live cart, account menu, orders and the current order on the `options` root, loopable in the builder.
- `etch/dynamic_data/post` (same file) — the seam Etch's own `WoocommerceIntegration` uses for `gallery_images`; Woo4Etch uses it to add formatted product fields (`{this.price}`, `{this.is_on_sale}`, …).

The three requests below are the points where no public seam exists yet.

## 1. Registration hook for custom loop handlers

**Current state:** `LoopHandlerManager` (`classes/Blocks/Global/Utilities/LoopHandlerManager.php`) hard-codes its five handlers (`wp-query`, `main-query`, `wp-terms`, `wp-users`, `json`) in a private static array. The `LoopHandlerInterface` is clean and a third-party class could implement it — but there is no way to register one.

**Gap:** WooCommerce-shaped loop sources are impossible: a `woo-products` source with native Woo args (on-sale, featured, category, stock), or "related products of the current product", "cross-sells of the cart" as loop targets. Today these need shortcode workarounds that don't preview in the builder.

**Proposal:** one filter where the handler array is built:

```php
self::$loop_handlers = apply_filters('etch/loop_handlers', array(
    'wp-query' => new WpQueryLoopHandler(),
    // …
));
```

A companion plugin then ships `'woo-products' => new WooProductsLoopHandler()` and the builder's loop UI lists it like any other source. A second, smaller win in the same area: a filter on the resolved `WP_Query` args in `WpQueryLoopHandler` (e.g. `etch/loop_query_args`, with the loop id as context), so plugins can inject `meta_query`/`tax_query` for existing presets.

## 2. Official server-side builder/canvas detection

**Current state:** `DynamicContextProvider::build_global_context()` hard-codes `environment.current = 'frontend'`; the canvas overrides it client-side (the `{#if environment.current === "etch"}` pattern works in markup, which is great). But on the **PHP side** there is no way to know that a request is rendering for the builder canvas.

**Gap:** plugins that feed dynamic data need to behave differently in the canvas. Example: the cart is empty/null in a builder REST render, so Woo4Etch serves sample cart rows there — otherwise loop-based cart layouts preview as nothing. To detect the canvas it currently sniffs `?etch=magic` and `REQUEST_URI` containing `etch-api`. That works, but it's undocumented internals: if the param or route ever changes, sample data silently disappears and to the user it looks like the layout broke.

**Proposal:** any one of these, whichever fits Etch's architecture best — and documented as public API:

```php
// a) function
if (function_exists('etch_is_canvas_render') && etch_is_canvas_render()) { … }

// b) constant defined at the start of a canvas render
if (defined('ETCH_CANVAS_RENDER')) { … }

// c) filterable flag passed into the dynamic-data filters as extra arg
apply_filters('etch/dynamic_data/option', $data, $context); // $context['is_canvas']
```

Option (c) is the nicest for data providers since the answer arrives exactly where it's needed.

## 3. A namespaced dynamic-data root for integrations

**Current state:** third-party data can only ride on existing roots. Woo4Etch puts cart/account/order data on `options` (`{options.cart_items}`, `{options.order}` …). The keys are documented and filterable, but the root is shared: any plugin/theme using `etch/dynamic_data/option` competes for the same namespace, and to users `options` reads like site settings, not live shop state.

**Gap:** no way to register `{woo.cart.items}` / `{woo.order.total}` — a root that makes ownership obvious, avoids collisions and groups an integration's keys in the builder's key-picker.

**Field evidence that the seam already works:** source research into existing third-party Etch integrations shows that `DynamicContentRegistry::enqueue()` is already being used in production today to register custom loopable roots (live cart items, payment methods, shipping rates), typically guarded by a bare `class_exists()` check on the registry class. So the mechanism is proven viable end-to-end — what's missing is only that it's public, documented and stable: integrations currently depend on an internal class name and undocumented timing, which can silently break on any Etch refactor.

**Proposal:** either open up `DynamicContentRegistry` (`classes/Blocks/Global/DynamicContent/DynamicContentRegistry.php` — `enqueue()` already exists but is undocumented and its timing relative to `build_global_context()` is unclear), or a registration filter:

```php
add_filter('etch/dynamic_data/roots', function (array $roots) {
    $roots['woo'] = fn() => Woo4Etch::dynamic_root(); // lazily resolved
    return $roots;
});
```

Lazy resolution matters: shop data should only be computed when a layout actually references the root.

> **Status (2026-07-31, answered by Pedro Bartulihe):** a roots filter doesn't exist, and the endorsed pattern is nesting inside the existing option root — `$data['woo'] = [...]` → `{options.woo.cart}`. That covers the collision/ownership concern (Woo4Etch already uses this shape for `{options.checkout.*}` and `{options.order.*}`); existing flat keys stay for compatibility. What remains of this request is small: top-level grouping in the builder's key-picker, and making `DynamicContentRegistry`'s timing/stability official for integrations that already use it.

## 4. Endpoint-aware template conditions (WooCommerce thank-you, My Account sub-pages)

**Current state:** Etch resolves templates strictly via WordPress's FSE hierarchy (`classes/Traits/DynamicData.php:536-596` — `page-{slug}` → `page-{id}` → `page` → `index`, matched against `wp_template` posts). WooCommerce **endpoints** are invisible to that hierarchy: `/checkout/order-received/{id}/` and `/my-account/orders/` are rewrite endpoints on the checkout / My Account *page*, not separate posts — so the page's template renders for every endpoint URL, with no way to vary it.

**Gap:** the thank-you page and each My Account sub-view (dashboard, orders, downloads, addresses, edit-account, view-order) cannot get their own template or template variation. Woo4Etch works around it inside one layout (conditionals on `is_wc_endpoint_url()` / dynamic data that only populates on an endpoint), which works — but users coming from other builders look for "the thank-you template" in the template list and conclude Etch can't do it.

**A lesson from Bricks worth taking seriously:** Bricks modeled these as extra Woo-specific *template types* — a separate "Thank you" template, separate account templates. It technically works but produced persistent user confusion, because the model hides the underlying reality (one page, many endpoint views): people built a thank-you template and wondered why their checkout template styling/wrappers didn't apply, or vice versa; the two templates competed for one URL space, and conditions behaved differently between the Woo template types and normal ones. **Recommendation: don't introduce new template *types*.** Keep the one-page model visible and add *conditions* to it — that matches how WordPress itself thinks, and it's the model Etch users already learned from page/single/archive templates.

**Proposal (minimal, architecture-conform):** a single filter where Etch builds the hierarchy slug list in `get_template_data()`:

```php
$hierarchy = apply_filters('etch/template_hierarchy', $hierarchy, $post);
```

That one line keeps Etch 100% Woo-agnostic and lets a companion plugin prepend endpoint-specific slugs, e.g.:

```php
// In Woo4Etch — prepend more specific template slugs when an endpoint is active:
add_filter('etch/template_hierarchy', function (array $hierarchy, $post) {
    if (function_exists('is_wc_endpoint_url') && is_wc_endpoint_url()) {
        $endpoint = WC()->query->get_current_endpoint();             // 'order-received', 'orders', …
        array_unshift($hierarchy, "page-{$post->post_name}-endpoint-{$endpoint}", "endpoint-{$endpoint}");
    }
    return $hierarchy;
}, 10, 2);
```

A user (or Woo4Etch programmatically, via the existing `POST /etch-api/templates` route) then creates a template with slug `endpoint-order-received`, and it wins over `page-checkout` only on that endpoint — standard hierarchy semantics, no new UI concepts required. Etch's template UI could later surface this as a friendly condition ("only on endpoint …"), but the filter alone unblocks the whole use case.

**Division of labor offer:** with this filter merged, Woo4Etch would ship and maintain the entire WooCommerce endpoint mapping (incl. docs and preset template slugs) — Etch core stays free of Woo-specific code. We'd prototype against a fork to keep the patch minimal and aligned with Etch's code style.

## 5. Template hub should list plugin-registered templates (not just its catalog)

> **Sharpened after UI-level testing:** the gap is bigger than the "new
> template" picker. The hub's LIST itself (`Templates.svelte`) derives its
> rendered items exclusively from the static/generated catalog and matches
> `templates.list` against it — **any existing `wp_template` post whose slug
> the catalog doesn't know is silently invisible**, even though the
> `/etch-api/templates` REST response contains it. On a live Woo site all
> five WooCommerce templates existed as posts with correct theme terms and
> were editable via deep link (`?etch=magic&post_id=…`) — but none appeared
> anywhere in the hub. A catch-all "Other templates" group for unmatched
> list entries would fix both the list and (with the registry) the picker.
> Until then, Woo4Etch bridges it by injecting a "WooCommerce" group into
> `.etch-templates__content` with deep links (assets/etch-hub-templates.js)
> — happy to retire that the moment the hub lists unknown slugs natively.

Etch's "new template" picker (`Templates.svelte`) is a static catalog — site
templates (index, 404, search, front-page, …) plus generated entries for CPTs
(`single-{cpt}`, `archive-{cpt}`) and taxonomies. What it never offers are
template types that plugins **register** with the block-template system:
WooCommerce's `page-cart`, `page-checkout`, `order-confirmation`,
`product-search-results`, `coming-soon`, `taxonomy-product_tag` and
`taxonomy-product_attribute`. The WordPress Site Editor lists all of these
under "Add New Template" because it reads the registry; a user comparing the
two concludes Etch "can't see" WooCommerce templates.

Once such a template *exists* as a `wp_template` post (created via the Site
Editor, or programmatically), Etch's hub shows and edits it perfectly — the
gap is only the creation catalog. And Etch is already 90 % there: its
`list_templates` REST route runs `ensure_templates_saved()`, which
materializes `source === 'plugin'` templates from `get_block_templates()`
into posts. The missing piece is surfacing the same registry entries in the
picker (or simply treating "registered but not yet materialized" like the
theme-file case it already handles).

**Related fix on our side is not possible cleanly:** Woo4Etch pre-creates the
four templates its layouts target (`archive-product`, `taxonomy-product_cat`,
`single-product`, `order-confirmation`) on push, but blanket-creating the
rest as empty posts would shadow WooCommerce's fallback rendering with blank
pages — the picker is the right place.

## Nice-to-have (lower priority)

- **Server-side component install:** Etch's public scripting API now covers component CRUD (`etch.components.createAsync()/updateAsync({key, description, properties, blocks})` — docs.etchwp.com/public-api/components), which is great — but the runtime only exists inside the builder (`window.etch` on `?etch=magic` pages). A *supported server-side* equivalent (PHP function or documented REST payload) is still missing, so plugins that want to ship components fall back to writing the storage format directly: a `wp_block` post with serialized block markup plus `etch_component_html_key` / `etch_component_properties` meta (both Woo4Etch's notices component and Bricks2Etch's bundled system components do exactly this, in production). It works, but it hard-couples third-party code to Etch's internal storage contract — a one-line supported `etch_install_component($definition)` (or documented REST payload) would remove that coupling.
- **Docs for the existing seams:** `etch/dynamic_data/post|term|user|option`, `etch/canvas/additional_stylesheets`, `etch/canvas/enqueue_assets`, `etch_autocompletion_classes` and `etch/register_custom_styles` are all useful today but undocumented — a short "extending Etch" page would surface them.
