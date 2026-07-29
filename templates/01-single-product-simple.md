# 01 — Single Product (Simple)

Product page without variations. Image, title, price, short description, quantity, add-to-cart, description tab.

## When to use

- Product without options (e.g. a single stainless-steel container).
- Add-to-cart as a classic form submit (no AJAX required; AJAX variant is a bonus).
- No variations, no set selector.

## Preparation

`add_theme_support('woocommerce')` must be active in `functions.php` (see [`00-README.md`](./00-README.md#declare-woocommerce-support-in-the-theme)).

Optional but useful for custom markup:

```php
// Disable default Woo CSS so your own styles take effect
add_filter('woocommerce_enqueue_styles', '__return_empty_array');
```

Optional — for the native Woo gallery effects (hover zoom, lightbox, thumbnail slider): check **Etch → Woo4Etch → Settings → Enable WooCommerce gallery scripts** and use the [Woo gallery markup variant](#gallery-variant--woocommerce-zoom-lightbox--thumbnail-slider) below instead of the plain gallery.

> **Etch context:** this is a **Single template** assigned to the `product` post type via the Template Hub. The current product is available as `{this.*}` — *not* `{item.*}` (that keyword is only inside `{#loop … as item}{/loop}` blocks). See [`10-etch-context-and-templates.md`](./10-etch-context-and-templates.md).

## Etch HTML

```html
<main id="main" class="site-main">
  <article class="product product-type-simple product--single"
           itemscope itemtype="https://schema.org/Product"
           data-product-id="{this.id}">

    <header class="product__header">
      <nav aria-label="Breadcrumb">
        <ol class="breadcrumb">
          <li><a href="/shop">Shop</a></li>
          <li><a href="/product-category/{this.product_cat.0.slug}">{this.product_cat.0.name}</a></li>
          <li aria-current="page">{this.title}</li>
        </ol>
      </nav>

      <h1 class="product_title entry-title" itemprop="name">{this.title}</h1>

      <p class="price" aria-label="Price">
        <span itemprop="price" content="{this.price_amount}">{this.price}</span>
        <meta itemprop="priceCurrency" content="EUR">
      </p>

      <!-- Use a RAW HTML element for the excerpt, not a Paragraph/Text element:
           Woo short descriptions may contain HTML (<strong>, <br>, lists), and
           Etch text blocks escape it — the tags would show as literal text. -->
      <div class="woocommerce-product-details__short-description" itemprop="description">
        {this.excerpt}
      </div>
    </header>

    <div class="product__layout">
      <section class="product__gallery" aria-labelledby="gallery-title">
        <h2 id="gallery-title" class="screen-reader-text">Product images</h2>

        <!-- Featured image first (gallery_images does NOT include it) -->
        <figure class="product__gallery-featured">
          <img src="{this.image.url}"
               alt="{this.title}"
               width="{this.image.width}"
               height="{this.image.height}"
               itemprop="image">
        </figure>

        <!-- Then the gallery images via the gallery_images Dynamic Key -->
        {#loop this.gallery_images as image}
          <figure class="product__gallery-item">
            <img src="{image.url}"
                 alt="{image.alt}"
                 width="{image.width}"
                 height="{image.height}"
                 srcset="{image.srcset}"
                 loading="lazy">
          </figure>
        {/loop}
      </section>

      <section class="product__summary entry-summary"
               aria-labelledby="product-options-title">
        <h2 id="product-options-title" class="screen-reader-text">Buy product</h2>

        <!-- Hook: woocommerce_before_add_to_cart_form (PHP layer) -->

        <form class="cart"
              action="{this.permalink.relative}"
              method="post"
              enctype="multipart/form-data">

          <!-- Hook: woocommerce_before_add_to_cart_quantity -->

          <div class="quantity">
            <label for="quantity_{this.id}" class="screen-reader-text">
              {this.title} quantity
            </label>

            <!-- Hook: woocommerce_before_quantity_input_field -->

            <input id="quantity_{this.id}"
                   class="input-text qty text"
                   type="number"
                   name="quantity"
                   value="1"
                   min="1"
                   step="1"
                   inputmode="numeric"
                   autocomplete="on"
                   aria-label="Product quantity">

            <!-- Hook: woocommerce_after_quantity_input_field -->
          </div>

          <!-- Hook: woocommerce_after_add_to_cart_quantity -->

          <button type="submit"
                  name="add-to-cart"
                  value="{this.id}"
                  class="single_add_to_cart_button button alt"
                  aria-label="Add {this.title} to cart">
            Add to cart
          </button>

          <!-- Hook: woocommerce_after_add_to_cart_button -->
        </form>

        <!-- Hook: woocommerce_after_add_to_cart_form -->

        <ul class="product_meta">
          <li class="sku_wrapper">
            SKU:
            <span class="sku" itemprop="sku">{this.sku}</span>
          </li>
          <li class="posted_in">
            Category:
            <a href="/product-category/{this.product_cat.0.slug}"
               rel="tag">{this.product_cat.0.name}</a>
          </li>
        </ul>
      </section>
    </div>

    <section class="product__details" aria-labelledby="details-title">
      <h2 id="details-title">Product details</h2>
      <div class="woocommerce-Tabs-panel woocommerce-Tabs-panel--description">
        {this.content}
      </div>
    </section>
  </article>
</main>
```

### Gallery variant — WooCommerce zoom, lightbox & thumbnail slider

The plain gallery above only renders images — nothing is clickable. WooCommerce ships its own gallery scripts (hover zoom, PhotoSwipe lightbox, FlexSlider thumbnail slider), and they work on hand-written Etch markup too: they initialise on the Woo gallery classes and data attributes, not on Woo-generated templates.

**Step 1 — enable the scripts:** check **Etch → Woo4Etch → Settings → Enable WooCommerce gallery scripts** (or use the `woo4etch/gallery_features` filter). The checkbox declares the three `wc-product-gallery-*` theme supports **and** enqueues the scripts on product pages. The theme supports alone are not enough: WooCommerce only auto-loads the gallery bundle for classic themes (`is_product() && ! wp_is_block_theme()` in `WC_Frontend_Scripts`), so on a block theme like Etch's nothing would load without the plugin closing that gap.

**Step 2 — use Woo's gallery markup.** Easiest: replace the gallery section with the shortcode (Raw HTML element):

```html
[woo_gallery mode="woo" columns="4"]
```

It outputs Woo's native wrapper with the featured image first and proper thumbnail sizes for the slider strip. Or hand-write it for full markup control:

```html
<div class="woocommerce-product-gallery woocommerce-product-gallery--with-images woocommerce-product-gallery--columns-4 images"
     data-columns="4"
     style="opacity: 0; transition: opacity .25s ease-in-out;">
  <figure class="woocommerce-product-gallery__wrapper">

    <!-- Featured image first = the main slide -->
    <div data-thumb="{this.image.url}" data-thumb-alt="{this.title}"
         class="woocommerce-product-gallery__image">
      <a href="{this.image.url}">
        <img src="{this.image.url}" alt="{this.title}"
             width="{this.image.width}" height="{this.image.height}"
             data-src="{this.image.url}"
             data-large_image="{this.image.url}"
             data-large_image_width="{this.image.width}"
             data-large_image_height="{this.image.height}"
             class="wp-post-image">
      </a>
    </div>

    {#loop this.gallery_images as image}
      <div data-thumb="{image.url}" data-thumb-alt="{image.alt}"
           class="woocommerce-product-gallery__image">
        <a href="{image.url}">
          <img src="{image.url}" alt="{image.alt}"
               width="{image.width}" height="{image.height}"
               data-caption="{image.caption}"
               data-src="{image.url}"
               data-large_image="{image.url}"
               data-large_image_width="{image.width}"
               data-large_image_height="{image.height}">
        </a>
      </div>
    {/loop}
  </figure>
</div>
```

What the scripts key off — don't drop these:

| Piece | Why |
|---|---|
| `.woocommerce-product-gallery` wrapper | `single-product.js` initialises here |
| `.woocommerce-product-gallery__wrapper` | FlexSlider viewport |
| `.woocommerce-product-gallery__image` per image | zoom target + lightbox slide list |
| `<a href="full-size-url">` around the `<img>` | click opens the lightbox |
| `data-large_image` + `data-large_image_width/height` | PhotoSwipe needs the full-size source and its dimensions |
| `data-thumb` | FlexSlider builds the thumbnail nav from it |
| `data-columns` + `--columns-4` class | thumbnail column layout |
| inline `opacity: 0` style | Woo's JS fades the gallery in after init — only keep it while the scripts are enabled, otherwise the gallery stays invisible |

Caveats:

- **Give slide images an `aspect-ratio` guard** — FlexSlider measures the viewport height at init, and images that haven't finished loading measure 0: the main slide collapses and the gallery degrades to a bare thumbnail grid (races on slow connections and in headless tests; also a CLS win). One rule fixes it:

  ```css
  .woocommerce-product-gallery__image img { inline-size: 100%; aspect-ratio: 1 / 1; object-fit: contain; display: block; }
  ```

- In the hand-written variant `data-thumb="{image.url}"` points at the full-size file — fine for small galleries, but the thumbnail strip then downloads full images. `[woo_gallery mode="woo"]` uses the registered `woocommerce_gallery_thumbnail` size instead; prefer it for image-heavy products.
- Slider and zoom-trigger styling (`.flex-control-thumbs`, the 🔍 button) lives in WooCommerce's stylesheets. With **Disable WooCommerce default styles** checked, the plugin ships a replacement automatically: **`assets/gallery.css`** is enqueued together with the gallery scripts (Woo4Etch 1.6.0+; disable via `add_filter('woo4etch/enqueue_gallery_css', '__return_false')`). It's the production-proven set (issue #20) — worth knowing what it guards even if you restyle it:

  ```css
  /* belt-and-braces against the inline opacity:0 — late/failed JS init must not leave the gallery invisible */
  .woocommerce-product-gallery { position: relative; opacity: 1 !important; min-inline-size: 0; }
  /* FlexSlider's viewport otherwise shrinks-to-fit mid-init inside grid/flex parents */
  .woocommerce-product-gallery .flex-viewport { inline-size: 100%; }
  /* the aspect-ratio guard from above (see the CLS caveat) */
  .woocommerce-product-gallery__image img:not(.zoomImg) { inline-size: 100%; aspect-ratio: 1 / 1; object-fit: contain; display: block; }
  /* 🔍 as a round button */
  .woocommerce-product-gallery__trigger { position: absolute; inset-block-start: var(--space-xs, .75rem); inset-inline-end: var(--space-xs, .75rem); z-index: 9; display: inline-flex; align-items: center; justify-content: center; inline-size: 2.25rem; block-size: 2.25rem; border-radius: 999px; background: var(--base-ultra-light, #fff); border: 1px solid var(--border-color-light, #e5e7eb); }
  /* thumbs as a stable grid — flex:1 would stretch thumbs when there are fewer than data-columns images */
  .flex-control-thumbs { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: var(--space-xs, .5rem); margin: var(--space-xs, .5rem) 0 0; padding: 0; list-style: none; }
  .flex-control-thumbs li { cursor: pointer; margin: 0; min-inline-size: 0; }
  /* active state: opacity + a --primary border (opacity alone is a weak affordance) */
  .flex-control-thumbs img { inline-size: 100%; aspect-ratio: 1; object-fit: contain; display: block; border: 1px solid var(--border-color-light, #e5e7eb); border-radius: calc(var(--radius, 10px) - 2px); opacity: .55; transition: opacity .2s, border-color .2s; }
  .flex-control-thumbs img.flex-active,
  .flex-control-thumbs img:hover { opacity: 1; border-color: var(--primary, currentColor); }
  ```

  The shipped file also carries `--columns-2/3/5` grid variants matching `data-columns`, and uses design tokens with plain fallbacks throughout — override any of it from Etch (all rules are low-specificity class selectors). If the gallery column lives in a CSS grid, keep its track `minmax(0, …)` *and* the `min-inline-size: 0` guard — the classic grid-blowout pair.

  PhotoSwipe brings its own stylesheet either way (separate handle, unaffected by the checkbox).
- Want only some effects? Pick features individually via the filter instead of the checkbox:

  ```php
  // e.g. zoom + lightbox, no slider
  add_filter('woo4etch/gallery_features', fn() => ['wc-product-gallery-zoom', 'wc-product-gallery-lightbox']);
  ```

## Required classes / attributes

| Element | Required | Why |
|---|---|---|
| `form.cart` | yes | Woo recognises this as the add-to-cart form |
| `name="quantity"` on the input | yes | Otherwise the quantity doesn't reach the server |
| `<div class="quantity">` wrapper | yes | Standard quantity hooks target it |
| `name="add-to-cart"` on the submit button | yes | Trigger for Woo's server logic |
| `value="{this.id}"` on the submit button | yes | Identifies the product |
| `.single_add_to_cart_button` | recommended | Theme/plugin styling + AJAX hook |
| `data-product-id` on `<article>` | recommended | Makes custom JS easier; optional |
| `product-type-simple` class | recommended | Consistency with Woo loop classes |

## Hooks used

Only the positions marked as comments in the markup above:

| Hook | Position | Typical use |
|---|---|---|
| `woocommerce_before_add_to_cart_form` | Before `<form class="cart">` | Trust badges, notices, stock notes |
| `woocommerce_before_add_to_cart_quantity` | Before the quantity wrapper | Helper text, preview |
| `woocommerce_before_quantity_input_field` | Before the `<input>` | Minus button |
| `woocommerce_after_quantity_input_field` | After the `<input>` | Plus button |
| `woocommerce_after_add_to_cart_quantity` | After the quantity wrapper | Bundle offers, volume discount display |
| `woocommerce_after_add_to_cart_button` | After the submit button | Express-checkout buttons (Apple/Google Pay) |
| `woocommerce_after_add_to_cart_form` | After the form | USP list, shipping/delivery notes |

## PHP layer

### Add quantity buttons

```php
add_action('woocommerce_before_quantity_input_field', function () {
    echo '<button type="button" class="qty-minus" aria-label="Decrease quantity">−</button>';
});

add_action('woocommerce_after_quantity_input_field', function () {
    echo '<button type="button" class="qty-plus" aria-label="Increase quantity">+</button>';
});
```

```js
// Minimal JS companion (vanilla)
document.addEventListener('click', (e) => {
  const target = e.target;
  if (!target.matches('.qty-minus, .qty-plus')) return;
  const input = target.closest('.quantity')?.querySelector('input[name="quantity"]');
  if (!input) return;
  const step = Number(input.step) || 1;
  const min  = Number(input.min)  || 1;
  const current = Number(input.value) || min;
  input.value = target.classList.contains('qty-minus')
    ? Math.max(min, current - step)
    : current + step;
  input.dispatchEvent(new Event('change', { bubbles: true }));
});
```

### Trust badges after the add-to-cart button

```php
add_action('woocommerce_after_add_to_cart_button', function () {
    echo '<ul class="trust-badges" role="list">
            <li>Free shipping over €50</li>
            <li>Secure payment</li>
            <li>14-day return policy</li>
          </ul>';
});
```

### Dynamic button text based on stock

```php
add_filter('woocommerce_product_single_add_to_cart_text', function ($text, $product) {
    if (!$product->is_in_stock()) {
        return 'Notify me when available';
    }
    return $text; // Default "Add to cart"
}, 10, 2);
```

### AJAX add-to-cart (bonus)

WooCommerce has a filter to make single products AJAX-capable too. By default AJAX is only active in the loop.

```php
add_filter('woocommerce_add_to_cart_redirect', function ($url) {
    // Don't jump to the cart page on AJAX variant
    return wp_get_referer() ?: $url;
});
```

If you want real AJAX, intercept the submit via JS and post to `wc-ajax=add_to_cart` — snippet on request.

## Common mistakes

- Excerpt rendered in a Paragraph/Text element → HTML in the short description (`<strong>`, `<br>`, lists) shows as literal text. Use a **Raw HTML** element for `{this.excerpt}`.
- Quantity input without `name="quantity"` → quantity doesn't end up in the cart.
- Forgot `value` on the submit button → server doesn't know *which* product.
- `form.cart` replaced by `<div>` → Woo doesn't recognise the form.
- Existing `do_action()` calls removed from the template → plugins (e.g. confirmation popups, volume discounts) no longer see your product.
- `enctype="multipart/form-data"` missing → breaks with some add-ons (e.g. Product Add-ons with file upload).
- `aria-label` on the submit button only "Add to cart" → valid, but not ideal in the loop without product reference; include the product title.
- Woo gallery markup with inline `opacity: 0` but the gallery scripts disabled → the gallery never becomes visible (only Woo's JS fades it in). Remove the inline style or enable the scripts.
- Gallery scripts enabled but the plain (non-Woo-classes) gallery markup kept → nothing happens; the scripts only initialise on `.woocommerce-product-gallery`.

## Test checklist

- Open the product, click "Add to cart" — product appears in the cart.
- Change quantity to 3 — 3 end up in the cart.
- DevTools → Network → submit shows a request with `add-to-cart={this.id}` and `quantity=3`.
- Keyboard: tab through the form, Enter submits.
- Screen reader announces price, SKU, and button text.
- With gallery scripts enabled: hovering the main image zooms, clicking it (or the 🔍 trigger) opens the PhotoSwipe lightbox, and the thumbnail strip switches slides.
