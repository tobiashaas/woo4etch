# 02 — Single Product (Variable)

Product page with variations. Size, color, material, etc. — live update of price and availability via the `wc-add-to-cart-variation` script.

## When to use

- Product with at least one attribute (e.g. size, color, capacity).
- Variations are set up in the WP backend (`Product data → Variable`).
- Price and availability depend on variation choice.

## Preparation

> **Etch context:** Single template assigned to the `product` post type via the Template Hub. The current product is available as `{this.*}` — *not* `{item.*}` (that keyword is only inside `{#loop … as item}{/loop}` blocks). See [`10-etch-context-and-templates.md`](./10-etch-context-and-templates.md).

In addition to the base config in [`00-README.md`](./00-README.md):

```php
// Make sure the variation script is loaded on custom single templates
add_action('wp_enqueue_scripts', function () {
    if (is_product()) {
        wp_enqueue_script('wc-add-to-cart-variation');
    }
});
```

On a "real" single-product page this happens automatically. On heavily customized builder templates or loops with embedded variation forms, you need to enqueue manually.

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
              data-product_variations="{this.variationsJson}">

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

Etch currently doesn't receive a ready-made variations dataset. Until Etch's team addresses this, you can inject it yourself:

```php
add_filter('etch/dynamic_data/item', function ($data, $post) {
    if ($post->post_type !== 'product') {
        return $data;
    }

    $product = wc_get_product($post->ID);
    if (!$product instanceof WC_Product_Variable) {
        return $data;
    }

    $data['variationsJson']               = wc_esc_json(wp_json_encode($product->get_available_variations()));
    $data['meta']['_min_variation_price'] = wc_price($product->get_variation_price('min'));

    return $data;
}, 10, 2);
```

> Filter name is illustrative — the actual Etch filter may differ. When in doubt, ask the Etch team if there's an official hook for extending the `item` object.

### Prevent duplicate add-to-cart markup

If you already write the hidden fields, quantity block, and button in Etch HTML, you need to remove the standard callback so Woo doesn't render them additionally:

```php
add_action('init', function () {
    remove_action('woocommerce_single_variation', 'woocommerce_single_variation_add_to_cart_button', 20);
});
```

Keep the price-area callback (priority 10) active — it writes into the `.single_variation` div.

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
