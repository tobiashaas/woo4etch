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
| `16-one-click-checkout.md` | One-click checkout (Buy Now → checkout → thank-you) |
| `17-components.md` | Component blueprints with pre-wired Woo markup |
| `functions-snippets.md` | Consolidated PHP snippets from all templates |
| `etch-copy/` | Copy/paste Etch snippets — paste a ready-made layout (e.g. the full cart) into the builder |

The plugin lives at [`../plugin/woo4etch/`](../plugin/woo4etch/).

---

## Shared foundations

### Declare WooCommerce support in the theme

**Do I need this? — Yes. But the Woo4Etch plugin already does it for you. If you use the plugin, skip this section.**

**What it is, in plain terms:** WooCommerce wants the active theme to say *"I'll provide the WooCommerce markup myself"* by calling `add_theme_support('woocommerce')` once. If **no** theme says that, WooCommerce decides the theme is *"unsupported"* and silently switches on a **compatibility mode**: it wraps shop and product pages in its **own** containers and page title and pushes them through `the_content`. In a normal theme that's a helpful fallback. In an **Etch** build — where *you* design the markup — that fallback **fights your layout** (double wrappers, an extra page title you didn't add, odd spacing) and WooCommerce shows the *"Your theme does not declare WooCommerce support"* notice in `wp-admin`.

**Why it bites Etch users specifically:** the official Etch theme does **not** declare WooCommerce support, and it tells you **not** to edit its `functions.php`. So out of the box, nobody declares it.

**The fix — pick one:**

1. **Recommended: install the Woo4Etch plugin.** It declares the support for you automatically (only if your theme hasn't already), with sensible image sizes. Nothing to copy, nothing to configure. See [`../plugin/woo4etch/`](../plugin/woo4etch/) and [`15-woo4etch-plugin.md`](./15-woo4etch-plugin.md#woocommerce-theme-support-automatic).

2. **Or do it by hand** (child theme `functions.php`, or `includes/customizations.php` in your Woo4Etch install) if you're not using the plugin:

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

       // Optional: only if you use Woo's built-in gallery JS (zoom/lightbox/slider).
       // Skip these if you build your own gallery in Etch.
       // add_theme_support('wc-product-gallery-zoom');
       // add_theme_support('wc-product-gallery-lightbox');
       // add_theme_support('wc-product-gallery-slider');
   });
   ```

   > **Block-theme caveat:** on a block theme (like Etch's) the three `wc-product-gallery-*` supports do nothing by themselves — WooCommerce only enqueues the gallery scripts for classic themes. The Woo4Etch plugin closes that gap: check **Woo4Etch → Settings → Enable WooCommerce gallery scripts**, which declares the supports *and* loads the scripts on product pages. Markup requirements: see the gallery variant in [`01-single-product-simple.md`](./01-single-product-simple.md#gallery-variant--woocommerce-zoom-lightbox--thumbnail-slider).

> The plugin runs this **late** (`after_setup_theme`, priority 99) and only when no theme has declared support, so a theme or child theme that *does* declare it always wins. To turn the automatic behaviour off: `add_filter('woo4etch/auto_theme_support', '__return_false');`

### Disable default Woo CSS (optional, recommended for custom markup)

> Easiest way: the checkbox under **Etch → Woo4Etch → Settings → Disable WooCommerce default styles** (Woo4Etch 1.6.0+) — togglable any time, no code. Snippet alternative:

```php
add_filter('woocommerce_enqueue_styles', '__return_empty_array');
```

Keeps your own styling clean without Woo defaults interfering. Don't fight Woo's CSS with overrides — remove it and style on a blank slate. (See also [`../docs/ADR-001-no-template-overrides.md`](../docs/ADR-001-no-template-overrides.md) for why Woo4Etch never overrides Woo template files.)

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
| Price (formatted) * | `{this.price}` | `{item.price}` |
| Price (raw meta) | `{this.meta._price}` | `{item.meta._price}` |
| SKU * | `{this.sku}` (or `{this.meta._sku}`) | `{item.sku}` |
| Stock status * | `{this.stock_status}` / `{this.stock_label}` | `{item.stock_status}` / `{item.stock_label}` |
| On sale (boolean) * | `{this.is_on_sale}` | `{item.is_on_sale}` |
| Category | `{this.product_cat.0.name}` | `{item.product_cat.0.name}` |
| Custom attribute | `{this.pa_hersteller.0.name}` | `{item.pa_hersteller.0.name}` |
| Product ID | `{this.id}` | `{item.id}` |
| Product gallery (array) | `{this.gallery_images}` — loop it | `{item.gallery_images}` — loop it |

Keys marked __*__ are added by the **Woo4Etch plugin** (product-data bridge; full list in [`15-woo4etch-plugin.md`](./15-woo4etch-plugin.md#product-fields-as-etch-dynamic-data)). The raw `meta._*` keys come from Etch itself and always work. Full list in the [main knowledge base](../WooCommerce-in-Etch-Knowledgebase.md#5-woocommerce-custom-layouts-guide-for-etch).

### Product image gallery — `gallery_images`

Etch ships a WooCommerce integration that adds a ready-to-loop `gallery_images` property to every **product**, available in **Etch 1.4.20+** (the ETC-800 fix; 1.4.19 and earlier do not have it). The raw `_product_image_gallery` meta is only a comma-separated string of attachment IDs, so `{this.meta._product_image_gallery}` is **not** usable on its own — use `gallery_images` instead. Loop it with an Etch loop whose target is `this.gallery_images`:

> **Important:** `gallery_images` contains the gallery **exactly as stored** — the featured image is **not** prepended (this keeps Etch's behaviour identical to WooCommerce). So render the featured image yourself first, then loop the gallery for the rest.

Each entry exposes: `id`, `url`, `alt`, `title`, `caption`, `description`, `filename`, `sizes`, `srcset`, `width`, `height`, `filesize`, `mime_type`.

```html
<figure class="product__featured">
  <img src="{this.image.url}" alt="{this.title}"
       width="{this.image.width}" height="{this.image.height}">
</figure>

{#loop this.gallery_images as image}
  <figure class="product__gallery-item">
    <img src="{image.url}" alt="{image.alt}"
         width="{image.width}" height="{image.height}"
         srcset="{image.srcset}">
  </figure>
{/loop}
```

The Woo4Etch plugin offers an equivalent server-rendered shortcode: `[woo_gallery]` (with `include_featured="yes"` if you *do* want the featured image first). See [`15-woo4etch-plugin.md`](./15-woo4etch-plugin.md).

### Modifiers — format values inline

Dynamic Keys support chainable **modifiers** — formatting/transform functions written as method calls inside the braces. This is the clean way to format prices, dates, and text without extra PHP.

```html
<!-- Raw meta price formatted with 2 decimals, comma decimal, dot thousands.
     (With the Woo4Etch plugin, {this.price} is already store-formatted.) -->
<span class="price">€ {this.meta._price.numberFormat(2, ',', '.')}</span>

<!-- Date formatted with a PHP date format string -->
<time>{this.date.format('d.m.Y')}</time>

<!-- Text transforms -->
<p>{this.excerpt.truncateChars(120, '…')}</p>
<span class="badge">{this.product_cat.0.name.toUpperCase()}</span>
```

Common families: **string** (`toUpperCase`, `toLowerCase`, `trim`, `truncateChars`, `truncateWords`, `replace`, `stripTags`, `toSlug`), **numeric** (`numberFormat`, `round`, `ceil`, `floor`, `add`, `subtract`, `multiply`, `divide`), **date** (`format`), **comparison** (`equal(v, ifTrue, ifFalse)`, `greater(v, ifTrue, ifFalse)`, …), **collection** (`length`, `pluck`, `join`, `slice`, `reverse`, `includes`).

> Inline arithmetic and ternaries (`{a + b}`, `{a ? b : c}`) are **not** supported — but the comparison modifiers (`.equal()`, `.greater()`, …) give you conditional output, and dedicated condition blocks handle show/hide logic.

### Layout structure — sections & containers

Build every layout band the Etch way: a full-width **section** wrapping a max-width **container**, marked with Etch's data attributes so the builder (and Automatic.css) treat them as real Sections/Containers.

```html
<section data-etch-element="section" class="product">
  <div data-etch-element="container">
    <!-- your content: dynamic keys, shortcodes, nested elements -->
  </div>
</section>
```

Use this for **content bands** (single product, archive, cart, page content). The **header and footer are their own bands** — leave them as plain wrappers, don't wrap them in section/container. And don't leave core Gutenberg blocks (e.g. `wp:post-title`) in the visible layout — use Etch elements + Dynamic Keys (`{this.title}`) so every part stays editable in the builder.

### Build order

1. Build Etch HTML with Dynamic Keys (visual + mock data).
2. Add required classes / hidden fields (see per template).
3. Register PHP snippets in the child theme (hooks, form logic).
4. Test with default Woo JS (DevTools: is the right script loaded?).
5. Only then consider template overrides under `wp-content/themes/<theme>/woocommerce/`.

## License

These templates are part of [woo4etch](https://github.com/tobiashaas/woo4etch) and shared under the [MIT License](../LICENSE). You may copy, adapt, and use them in your own projects (including commercial shops) at no cost; keep the copyright notice when redistributing substantial portions.
