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

> **Etch context:** this is a **Single template** assigned to the `product` post type via the Template Hub. The current product is available as `{this.*}` — *not* `{this.*}`. See [`10-etch-context-and-templates.md`](./10-etch-context-and-templates.md).

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
        <span itemprop="price">{this.meta._price}</span>
        <meta itemprop="priceCurrency" content="EUR">
      </p>

      <p class="woocommerce-product-details__short-description" itemprop="description">
        {this.excerpt}
      </p>
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
            <span class="sku" itemprop="sku">{this.meta._sku}</span>
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

- Quantity input without `name="quantity"` → quantity doesn't end up in the cart.
- Forgot `value` on the submit button → server doesn't know *which* product.
- `form.cart` replaced by `<div>` → Woo doesn't recognise the form.
- Existing `do_action()` calls removed from the template → plugins (e.g. confirmation popups, volume discounts) no longer see your product.
- `enctype="multipart/form-data"` missing → breaks with some add-ons (e.g. Product Add-ons with file upload).
- `aria-label` on the submit button only "Add to cart" → valid, but not ideal in the loop without product reference; include the product title.

## Test checklist

- Open the product, click "Add to cart" — product appears in the cart.
- Change quantity to 3 — 3 end up in the cart.
- DevTools → Network → submit shows a request with `add-to-cart={this.id}` and `quantity=3`.
- Keyboard: tab through the form, Enter submits.
- Screen reader announces price, SKU, and button text.
