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

**Proposal:** either open up `DynamicContentRegistry` (`classes/Blocks/Global/DynamicContent/DynamicContentRegistry.php` — `enqueue()` already exists but is undocumented and its timing relative to `build_global_context()` is unclear), or a registration filter:

```php
add_filter('etch/dynamic_data/roots', function (array $roots) {
    $roots['woo'] = fn() => Woo4Etch::dynamic_root(); // lazily resolved
    return $roots;
});
```

Lazy resolution matters: shop data should only be computed when a layout actually references the root.

## Nice-to-have (lower priority)

- **Component install API:** components are `wp_block` posts plus `etch_component_*` meta and there are CRUD REST routes (`classes/RestApi/Routes/ComponentsRoutes.php`) — but no import/install mechanism for a packaged set. A documented "install component package" entry point would let companion plugins ship one-click demo layouts instead of copy-paste JSON.
- **Docs for the existing seams:** `etch/dynamic_data/post|term|user|option`, `etch/canvas/additional_stylesheets`, `etch/canvas/enqueue_assets`, `etch_autocompletion_classes` and `etch/register_custom_styles` are all useful today but undocumented — a short "extending Etch" page would surface them.
