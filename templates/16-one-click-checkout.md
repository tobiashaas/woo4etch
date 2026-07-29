# 16 — One-Click Checkout (Buy Now)

A "Buy now" button on the single product page that adds the product and sends the customer straight to checkout, skipping the cart. The flow ends on the thank-you page ([`08-thank-you.md`](./08-thank-you.md)) — works for guests too.

## When to use

- Single-product shops or impulse-buy products where the cart is friction.
- Alongside the normal add-to-cart button (both can coexist in the same form).
- Combined with `woo4etch/buy_now_empty_cart` for a true one-click flow (the new product replaces the cart).

## Preparation

- Woo4Etch plugin **1.6.0+** active — the buy-now redirect is built in (no snippet required).
- A working add-to-cart form from [`01-single-product-simple.md`](./01-single-product-simple.md) or [`02-single-product-variable.md`](./02-single-product-variable.md).
- Checkout page assigned under WooCommerce → Settings → Advanced.

## Etch HTML

Add a second submit button **inside the existing `form.cart`** (or `form.variations_form`):

```html
<form class="cart" action="{this.permalink.relative}" method="post" enctype="multipart/form-data">

  <input type="number" name="quantity" value="1" min="1" step="1" aria-label="Quantity">

  <button type="submit"
          name="add-to-cart"
          value="{this.id}"
          class="single_add_to_cart_button button alt">
    Add to cart
  </button>

  <!-- Buy now: same form, extra flag. The plugin redirects to checkout. -->
  <button type="submit"
          name="buy_now"
          value="1"
          class="single_add_to_cart_button button buy-now-button">
    Buy now
  </button>

  <!-- The add-to-cart value must still be submitted when buy_now is clicked.
       Browsers only submit the *clicked* submit button, so carry the product
       id in a hidden input instead of relying on the other button: -->
  <input type="hidden" name="add-to-cart" value="{this.id}">
</form>
```

> If you keep the hidden `add-to-cart` input, change the regular button to `type="submit"` **without** a `name` (or keep both — duplicate `add-to-cart` values are harmless since they're identical).

## Required classes / attributes

| Selector / attribute | Required | Why |
|---|---|---|
| `form.cart` | yes | Woo's add-to-cart handler keys off the form |
| `name="add-to-cart"` + `value="{this.id}"` | yes | Tells the server which product (hidden input recommended) |
| `name="buy_now"` | yes | The flag the Woo4Etch redirect checks (`$_REQUEST['buy_now']`) |
| `name="quantity"` | yes | Quantity ends up in the cart |
| `.single_add_to_cart_button` | yes | Woo JS/extensions hook onto it |

## Hooks used

None beyond the standard add-to-cart hooks — the buy-now button lives inside the same form, so `woocommerce_before_add_to_cart_button` / `woocommerce_after_add_to_cart_button` apply unchanged.

## PHP layer

Built into the Woo4Etch plugin — when the submitted request contains `buy_now`, the customer is redirected to checkout after the (normal, validated) add-to-cart:

```php
// Disable the built-in buy-now redirect:
add_filter('woo4etch/enable_buy_now', '__return_false');

// True one-click: empty the cart first, so checkout contains only this product.
// Off by default — existing cart contents are preserved.
add_filter('woo4etch/buy_now_empty_cart', '__return_true');
```

No snippet in `customizations.php` is needed for the default behaviour.

## Common mistakes

- Buy-now button placed **outside** `form.cart` → nothing is added; the flag never reaches Woo's handler.
- Product id only on the regular button (`name="add-to-cart"` on the *other* submit button) → browsers submit only the clicked button, the server doesn't know the product. Use the hidden input.
- Expecting the cart to be emptied — that's opt-in via `woo4etch/buy_now_empty_cart`.
- Variable products: buy-now works the same inside `form.variations_form`, but a variation must be selected first (Woo validates this server-side and bounces back with a notice — render `[woo_notices]`).

## Test checklist

- Click "Buy now" → product is in the cart and the browser lands on checkout.
- Click the regular "Add to cart" → normal behaviour (stays on / redirects per Woo settings).
- Guest checkout: complete an order without an account → thank-you page renders ([`08-thank-you.md`](./08-thank-you.md)).
- With `buy_now_empty_cart` enabled: pre-fill the cart, buy-now another product → checkout shows only the new product.
- Variable product without a chosen variation: buy-now shows Woo's "select options" notice instead of a broken checkout.
