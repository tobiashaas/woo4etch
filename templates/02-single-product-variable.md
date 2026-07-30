# 02 — Single Product (Variable)

Product page with variations. Size, color, material, etc. — live update of price and availability via the `wc-add-to-cart-variation` script.

## When to use

- Product with at least one attribute (e.g. size, color, capacity).
- Variations are set up in the WP backend (`Product data → Variable`).
- Price and availability depend on variation choice.

## Preparation

> **Etch context:** Single template assigned to the `product` post type via the Template Hub. The current product is available as `{this.*}` — *not* `{item.*}` (that keyword is only inside `{#loop … as item}{/loop}` blocks). See [`10-etch-context-and-templates.md`](./10-etch-context-and-templates.md).

In addition to the base config in [`00-README.md`](./00-README.md):

> **Woo4Etch 1.6.0+ enqueues `wc-add-to-cart-variation` automatically** on variable-product pages (WooCommerce itself only enqueues it from its own add-to-cart template, which hand-built Etch forms never render — without the script, selecting a variation does nothing). Disable: `add_filter('woo4etch/enqueue_variation_script', '__return_false');`. On older versions, or for variation forms embedded outside product pages, enqueue manually:

```php
add_action('wp_enqueue_scripts', function () {
    if (is_product()) {
        wp_enqueue_script('wc-add-to-cart-variation');
    }
});
```

> **Shortcut — skip the hand-built form entirely:** place an empty `<div data-w4e-add-to-cart="{this.id}"></div>` in your layout (Woo4Etch 1.6.0+). The plugin fills it server-side with WooCommerce's complete native variations form — attribute selects with options, variations JSON, working price/stock updates — *after* Etch renders, so Etch's raw-html sanitizer (which strips `<form>/<input>/<select>` unless the off-by-default "allow unsafe raw HTML" setting is on) never touches it. The ready-made single-product layout uses exactly this for non-simple products; `swatches.js` bridges custom swatch markup on top. The hand-built form below remains the full-control alternative.

## Etch HTML

```html
<main id="main" class="site-main">
  <article class="product product-type-variable product--single"
           itemscope itemtype="https://schema.org/Product"
           data-product-id="{this.id}">

    <header class="product__header">
      <h1 class="product_title entry-title" itemprop="name">{this.title}</h1>
      <p class="price" aria-label="Price from">
        from <span>{this.meta._min_variation_price}</span>
      </p>
      <p class="woocommerce-product-details__short-description">{this.excerpt}</p>
    </header>

    <div class="product__layout">
      <section class="product__gallery" aria-labelledby="gallery-title">
        <h2 id="gallery-title" class="screen-reader-text">Product images</h2>
        <figure>
          <img src="{this.image.url}"
               alt="{this.title}"
               width="{this.image.width}"
               height="{this.image.height}"
               itemprop="image">
        </figure>
      </section>

      <section class="product__summary entry-summary"
               aria-labelledby="product-options-title">
        <h2 id="product-options-title">Configure product</h2>

        <!-- Hook: woocommerce_before_add_to_cart_form -->

        <form class="variations_form cart"
              action="{this.permalink.relative}"
              method="post"
              enctype="multipart/form-data"
              data-product_id="{this.id}"
              data-product_variations="{this.variations_json}">

          <!-- Hook: woocommerce_before_variations_form -->

          <table class="variations" cellspacing="0" role="presentation">
            <tbody>
              <tr>
                <th class="label">
                  <label for="pa_size">Size</label>
                </th>
                <td class="value">
                  <select id="pa_size"
                          name="attribute_pa_size"
                          data-attribute_name="attribute_pa_size"
                          required>
                    <option value="">Please choose</option>
                    <!-- Options are populated by the variations JS -->
                  </select>

                  <a class="reset_variations"
                     href="#"
                     style="visibility: hidden;"
                     aria-label="Clear options">Clear options</a>
                </td>
              </tr>

              <tr>
                <th class="label">
                  <label for="pa_color">Color</label>
                </th>
                <td class="value">
                  <select id="pa_color"
                          name="attribute_pa_color"
                          data-attribute_name="attribute_pa_color"
                          required>
                    <option value="">Please choose</option>
                  </select>
                </td>
              </tr>
            </tbody>
          </table>

          <!-- Hook: woocommerce_after_variations_table -->

          <div class="reset_variations_alert screen-reader-text"
               role="alert"
               aria-live="polite"
               aria-relevant="all"></div>

          <div class="single_variation_wrap">

            <!-- Hook: woocommerce_before_single_variation -->

            <div class="single_variation" aria-live="polite">
              <!-- Populated by the variations JS: price + availability -->
            </div>

            <!-- Hook: woocommerce_single_variation (renders add-to-cart area) -->

            <div class="woocommerce-variation-add-to-cart variations_button">
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
                       aria-label="Product quantity">

                <!-- Hook: woocommerce_after_quantity_input_field -->
              </div>

              <button type="submit"
                      class="single_add_to_cart_button button alt"
                      disabled>
                Add to cart
              </button>

              <input type="hidden" name="add-to-cart" value="{this.id}">
              <input type="hidden" name="product_id" value="{this.id}">
              <input type="hidden" name="variation_id" class="variation_id" value="0">
            </div>

            <!-- Hook: woocommerce_after_single_variation -->
          </div>

          <!-- Hook: woocommerce_after_variations_form -->
        </form>

        <!-- Hook: woocommerce_after_add_to_cart_form -->
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

## Required classes / attributes

| Element | Required | Why |
|---|---|---|
| `form.variations_form.cart` | yes | Exact class match — the variations JS looks for it |
| `data-product_id="{this.id}"` | yes | Variations JS reads the product ID |
| `data-product_variations` | yes | JSON array of all variations — otherwise the selector is empty |
| `<select name="attribute_pa_*">` | yes | Attribute names for variation resolution |
| `data-attribute_name="attribute_pa_*"` | recommended | Helps variations JS identify the DOM elements |
| `<a class="reset_variations">` | yes | JS hooks into it for reset behavior |
| `.reset_variations_alert.screen-reader-text` | recommended | Live region for screen readers |
| `.single_variation_wrap` | yes | Container where JS renders price/availability |
| `.single_variation[aria-live="polite"]` | yes | Live region for price display |
| Hidden `name="add-to-cart"` | yes | Identifies the parent product |
| Hidden `name="product_id"` | yes | Helper field for server logic |
| Hidden `name="variation_id"` with `.variation_id` class | yes | JS writes the chosen variation ID here |
| `<button … disabled>` | yes | Disabled by default; JS enables it after valid selection |

## Hooks used

| Hook | Position | Typical use |
|---|---|---|
| `woocommerce_before_add_to_cart_form` | Before `<form>` | Notices, size-guide link |
| `woocommerce_before_variations_form` | Inside the form, before options | Intro, "Please choose…" text |
| `woocommerce_after_variations_table` | After the variations table | Swatches wrapper, per-variant stock |
| `woocommerce_before_single_variation` | Inside `.single_variation_wrap`, before data | Prep content (e.g. config summary) |
| `woocommerce_single_variation` | Inside `.single_variation_wrap` | **Renders** price, availability, add-to-cart (standard callbacks!) |
| `woocommerce_after_single_variation` | After `.single_variation_wrap` | Shipping info, warranty badges |
| `woocommerce_after_variations_form` | After `</form>` | Reviews, cross-sells |
| `woocommerce_before_quantity_input_field` | Before `<input>` | Minus button |
| `woocommerce_after_quantity_input_field` | After `<input>` | Plus button |

> **Important:** `woocommerce_single_variation` must not be removed. Standard callbacks there render the variation price (`woocommerce_single_variation` priority 10) and the add-to-cart area (priority 20). If you render the hidden fields in HTML yourself, you can disable the 20-priority callback via `remove_action` — otherwise the markup will be duplicated.

## PHP layer

### Provide variations JSON to Etch

**Woo4Etch 1.6.0+ provides this as a real Dynamic Key:** `{this.variations_json}` — the product's available variations as the exact JSON `wc-add-to-cart-variation` expects in `data-product_variations`:

```html
<form class="variations_form cart" …
      data-product_id="{this.id}"
      data-product_variations="{this.variations_json}">
```

It is computed only for the **main product on its own page** (`get_available_variations()` renders every variation — a 10-product grid loop would pay that cost per item). To expose it elsewhere too: `add_filter('woo4etch/expose_variations_json', '__return_true');`.

> **Note:** earlier revisions of this page showed an illustrative `{this.variationsJson}` filter — replace it with the real key above.

### Prevent duplicate add-to-cart markup

If you already write the hidden fields, quantity block, and button in Etch HTML, you need to remove the standard callback so Woo doesn't render them additionally:

```php
add_action('init', function () {
    remove_action('woocommerce_single_variation', 'woocommerce_single_variation_add_to_cart_button', 20);
});
```

Keep the price-area callback (priority 10) active — it writes into the `.single_variation` div.

### Variation swatches (color blobs / image previews)

WooCommerce default offers only the bare `<select>` dropdowns. With Woo4Etch **1.6.0+** you can render swatches as your own Etch markup — fully visible and styleable in the builder — and the plugin's bundled script bridges clicks to the hidden native select, so Woo's variation logic (price, stock, `variation_id`) keeps working untouched.

**1. Keep the native `<select>` in the form** (hide it with CSS, don't remove it):

```css
.variations_form select[name^="attribute_"] { position: absolute; left: -9999px; }
```

**2. Render the swatches in Etch** — loop the attribute terms (`wp-terms` loop on the taxonomy, e.g. `pa_color`) and give each clickable element three data attributes:

```html
{#loop colorTerms as term}
  <button type="button"
          class="swatch swatch--color"
          data-w4e-swatch
          data-attribute="attribute_pa_color"
          data-value="{term.slug}"
          aria-pressed="false"
          aria-label="{term.name}"
          style="background-color: {term.meta.color}">
  </button>
{/loop}
```

Store the color value (or an image) as term meta on the attribute term (Etch CMS field or ACF) and use it freely in the markup — circles, pills, image thumbs, whatever you style.

**3. Done.** The plugin script (`assets/swatches.js`, enqueued on product pages) sets the matching select value on click, triggers Woo's `change` handling, toggles `.is-selected` + `aria-pressed` on the active swatch, and clears all swatches when Woo's "Clear" link fires `reset_data`.

```php
// Scope or disable the script:
add_filter('woo4etch/enqueue_swatches', '__return_false');
```

Style the selected state in Etch via `.swatch.is-selected`.

### Zero-markup alternative: auto-built pills + quantity stepper (`pills.js`)

The inverse of `swatches.js`: instead of bridging *hand-built* swatch markup into the
native selects, `assets/pills.js` (Woo4Etch → Settings → "Variation pills & quantity
stepper", or `add_filter('woo4etch/enqueue_pills', '__return_true')`) auto-builds
accessible pill buttons *from* the native attribute `<select>`s and wraps every
`.quantity input.qty` in a −/+ stepper — no extra Etch markup at all. Woo's variation
JS stays the source of truth: a pill click sets the native select and dispatches its
`change` event, so price/stock/`variation_id` keep working untouched. Works with both
the hand-built form on this page and the `data-w4e-add-to-cart` native form.

- Selected state: `.w4e-pill.is-selected` (+ `aria-pressed`); stepper: `.w4e-qty`,
  `.w4e-qty__btn`. The injected CSS uses design tokens (`--primary`, `--space-*`,
  `--radius`, `--text-*`) with plain fallbacks — override freely, the rules are plain
  classes in a `<style id="w4e-pills-css">`.
- Pick **one** mechanism per form: pills (zero markup, automatic) *or* swatches
  (full control over the markup, e.g. color blobs / image previews).
- Production-proven on a live shop (issue #19).

### Buy-box rhythm for variable products (companion CSS)

The native variations form renders extra rows (variation price, availability,
description) that a styled buy box usually wants deduplicated and spaced. This
production-proven set assumes the top price row mirrors the chosen variation
(`data-w4e-variation-price` sync) and hides Woo's duplicate; adapt selectors to your
wrapper classes. Give the variable-product form an extra class (e.g.
`w4e-product__form--variable`, or use the ready-made layout's `.w4e-native-cart`
wrapper) so the `:has()` dedupe can target it:

```css
/* dedupe: stock row outside the form duplicates Woo's availability row */
.product-info:has(.w4e-product__form--variable) > .product__stock { display: none; }
/* price row already shows the (synced) price */
.w4e-product__form--variable .woocommerce-variation-price { display: none; }
.w4e-product__form--variable .woocommerce-variation-availability p {
  margin: 0; font-size: var(--text-s, .9375rem); font-weight: 600; color: var(--primary, inherit);
}
.woocommerce-variation-description p { margin: 0; font-size: var(--text-s, .9375rem); color: var(--text-dark-muted, #6b7280); }
.single_variation_wrap { display: flex; flex-direction: column; gap: var(--space-s, .85rem); inline-size: 100%; }
.single_variation { display: flex; flex-direction: column; gap: calc(var(--space-xs, .5rem) / 2); }
.woocommerce-variation-add-to-cart,
form.cart.w4e-product__form--variable {
  display: flex; flex-direction: column; gap: var(--space-s, .85rem); align-items: stretch; max-inline-size: 26rem;
}
.w4e-product__form--variable .variations { margin: 0; inline-size: 100%; }
/* stepper sizing (2.9rem touch targets) */
.w4e-qty { align-self: flex-start; min-block-size: 2.9rem; }
.w4e-qty__btn { inline-size: 2.75rem; font-size: var(--text-l, 1.1875rem); line-height: 1; }
.w4e-qty input.qty { inline-size: 4rem; font-size: var(--text-m, 1.0625rem); text-align: center; }
```

### Swap variation image (optional)

By default Woo swaps the gallery image on variant change. If you have a custom gallery, listen to the event:

```js
jQuery(document).on('found_variation', 'form.variations_form', function (event, variation) {
  if (variation.image && variation.image.src) {
    document.querySelector('.product__gallery img').src = variation.image.src;
  }
});
```

### Communicate the reset state

```php
add_action('woocommerce_after_variations_table', function () {
    echo '<p class="variations-helper">Select all options to see price and availability.</p>';
});
```

### Enqueue script on custom pages

For loops with embedded variation forms (e.g. a "Quick Buy" variant on the category page):

```php
add_action('wp_enqueue_scripts', function () {
    if (is_shop() || is_product_category()) {
        wp_enqueue_script('wc-add-to-cart-variation');
    }
});
```

```js
// Initialize after DOMReady
jQuery(function ($) {
  $('.variations_form').each(function () {
    $(this).wc_variation_form();
  });
});
```

## Common mistakes

- `data-product_variations` missing or `'[]'` → no options rendered, button stays disabled.
- `attribute_pa_*` names don't match the backend attributes → JS can't find the values.
- `.single_variation_wrap` omitted → JS has nowhere to render prices.
- Submit button **not** initially `disabled` → user can submit without a valid selection.
- Hidden `variation_id` without class `.variation_id` → JS can't write the value.
- `wc-add-to-cart-variation` script not loaded (e.g. because Woo doesn't recognise the page as a product page).
- Standard callback `woocommerce_single_variation_add_to_cart_button` runs *in addition* to your markup → button and hidden fields duplicated in the DOM.

## Test checklist

- DevTools → Console: `jQuery._data($('.variations_form')[0], 'events')` shows a `found_variation` listener.
- Pick the first variant → `.single_variation` fills with price/status.
- Pick all variants → submit button enables.
- Reset one option → live region announces it, price disappears, submit goes back to disabled.
- Submit the form → cart shows the correct variant (not the parent product).
- Network tab: submit request contains `variation_id=<concrete ID>`, not `0`.
- With the Woo4Etch plugin active (1.7.0+): the submit goes to `POST /wc/store/v1/cart/add-item` with the resolved variation id + attributes, without a page reload — the buyer stays on the product, a notice confirms, and the mini-cart region updates. Forms with third-party extra fields (add-ons etc.) intentionally keep the classic POST. See [`04-cart.md`](./04-cart.md) → "Interaction layer".
