# WooCommerce Templates for Etch — Overview

Minimal, ready-to-use templates for the main WooCommerce areas.
Each template is written as **Etch HTML with Dynamic Keys** plus a **PHP layer** you add later (hooks, hidden fields, functionality).

## Convention

All templates follow the same structure:

1. **When to use** — use case and context.
2. **Preparation** — what needs to exist in your theme / `functions.php`.
3. **Etch HTML** — copy-ready markup with Dynamic Keys (`{this.*}` on Single templates, `{item.*}` inside loops).
4. **Required classes / attributes** — what you *must not* drop.
5. **Hooks used** — compact table.
6. **PHP layer** — snippets for hook registration, form logic, enqueues.
7. **Common mistakes** — what tends to break in custom layouts.
8. **Test checklist** — quick verification steps.

## Templates in this folder

| File | Area |
|---|---|
| `01-single-product-simple.md` | Single product without variations |
| `02-single-product-variable.md` | Single product with variations |
| `03-product-archive.md` | Shop / category archive with product loop |
| `04-cart.md` | Cart page |
| `05-mini-cart.md` | Header mini-cart with live update |
| `06-checkout.md` | Checkout (classic shortcode) |
| `07-account.md` | My Account (dashboard, orders, addresses) |
| `08-thank-you.md` | Order received / thank-you page |
| `09-emails.md` | Transactional email templates |
| `10-etch-context-and-templates.md` | **Read first.** `this.*` vs `item.*`, Templates vs Pages, `mainQuery` loops — #1 cause of empty fields |
| `11-conditional-tags-and-product-api.md` | `is_product()` / `is_cart()` etc. + `$product` object methods |
| `12-store-api-and-rest.md` | Store API + custom REST endpoints for AJAX UI |
| `13-useful-snippets.md` | Buy-now button, custom add-to-cart URLs, free-shipping bar, refund request, more |
| `14-visual-hook-guides.md` | Links to Business Bloomer's annotated hook diagrams |
| `15-woo4etch-plugin.md` | **Woo4Etch** plugin (shortcodes + customizations) |
| `functions-snippets.md` | Consolidated PHP snippets from all templates |

The plugin lives at [`../plugin/woo4etch/`](../plugin/woo4etch/).

---

## Shared foundations

### Declare WooCommerce support in the theme

Add this once in `includes/customizations.php` inside your Woo4Etch install (see [`../plugin/woo4etch/`](../plugin/woo4etch/)). Without it, WooCommerce won't enqueue certain scripts and the single-product loop won't be output everywhere.

```php
add_action('after_setup_theme', function () {
    add_theme_support('woocommerce', [
        'thumbnail_image_width' => 600,
        'single_image_width'    => 1200,
        'product_grid'          => [
            'default_rows'    => 3,
            'min_rows'        => 1,
            'default_columns' => 3,
            'min_columns'     => 1,
            'max_columns'     => 6,
        ],
    ]);

    // Optional: zoom, lightbox, slider on the single-product gallery
    add_theme_support('wc-product-gallery-zoom');
    add_theme_support('wc-product-gallery-lightbox');
    add_theme_support('wc-product-gallery-slider');
});
```

### Disable default Woo CSS (optional, recommended for custom markup)

```php
add_filter('woocommerce_enqueue_styles', '__return_empty_array');
```

Keeps your own styling clean without Woo defaults interfering.

### Replace Woo content wrappers

So Woo's own templates don't force you to use `wrapper_start/end` hooks, you can redirect them to your theme's hooks or empty them out:

```php
remove_action('woocommerce_before_main_content', 'woocommerce_output_content_wrapper', 10);
remove_action('woocommerce_after_main_content',  'woocommerce_output_content_wrapper_end', 10);

add_action('woocommerce_before_main_content', function () {
    echo '<main id="main" class="site-main">';
});
add_action('woocommerce_after_main_content', function () {
    echo '</main>';
});
```

### Hook strategy in one sentence

> Build the layout yourself, define critical Woo regions as hook islands, place `do_action(...)` there — and don't remove standard hooks, so plugins and extensions can still plug in.

### Quantity hooks (used in single product and cart)

These two hooks are small but useful for UX (plus/minus buttons, helper text). They appear in multiple templates:

| Hook | Position | Use |
|---|---|---|
| `woocommerce_before_quantity_input_field` | Before the `<input>` | Minus button, prefix, helper text |
| `woocommerce_after_quantity_input_field` | After the `<input>` | Plus button, validation notes |

```php
add_action('woocommerce_before_quantity_input_field', function () {
    echo '<button type="button" class="qty-minus" aria-label="Decrease quantity">−</button>';
});
add_action('woocommerce_after_quantity_input_field', function () {
    echo '<button type="button" class="qty-plus" aria-label="Increase quantity">+</button>';
});
```

### Etch Dynamic Keys — quick reference

Same field names in both contexts — only the **keyword** changes. See [`10-etch-context-and-templates.md`](./10-etch-context-and-templates.md).

| Content | Single template (`product`) | Inside `{#loop … as item}` |
|---|---|---|
| Title | `{this.title}` | `{item.title}` |
| Permalink (relative) | `{this.permalink.relative}` | `{item.permalink.relative}` |
| Featured image URL | `{this.image.url}` | `{item.image.url}` |
| Excerpt | `{this.excerpt}` | `{item.excerpt}` |
| Content | `{this.content}` | `{item.content}` |
| Price | `{this.meta._price}` | `{item.meta._price}` |
| SKU | `{this.meta._sku}` | `{item.meta._sku}` |
| Stock status | `{this.meta._stock_status}` | `{item.meta._stock_status}` |
| Category | `{this.product_cat.0.name}` | `{item.product_cat.0.name}` |
| Custom attribute | `{this.pa_hersteller.0.name}` | `{item.pa_hersteller.0.name}` |
| Product ID | `{this.id}` | `{item.id}` |

Full list in the [main knowledge base](../WooCommerce-in-Etch-Knowledgebase.md#5-woocommerce-custom-layouts-guide-for-etch).

### Build order

1. Build Etch HTML with Dynamic Keys (visual + mock data).
2. Add required classes / hidden fields (see per template).
3. Register PHP snippets in the child theme (hooks, form logic).
4. Test with default Woo JS (DevTools: is the right script loaded?).
5. Only then consider template overrides under `wp-content/themes/<theme>/woocommerce/`.

## License

These templates are part of [woo4etch](https://github.com/tobiashaas/woo4etch) and shared under the [MIT License](../LICENSE). You may copy, adapt, and use them in your own projects (including commercial shops) at no cost; keep the copyright notice when redistributing substantial portions.
