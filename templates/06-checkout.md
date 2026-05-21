# 06 — Checkout (Classic, Shortcode-based)

Classic shortcode checkout. Billing/shipping form, order review, payment methods, place-order button.

> **Important:** with WooCommerce ≥ 8, the **Checkout block** is the default. The block is a React frontend that accepts almost no PHP hooks. If you want custom markup in Etch, **first switch back to the shortcode** (`[woocommerce_checkout]`). Otherwise you end up in a React tree where classic hooks don't fire.
>
> If you want to use the block anyway, that's a separate topic (block extensions via `@woocommerce/blocks-registry`). This doc covers only the classic setup.

## When to use

- Checkout page (`/checkout`) with shortcode `[woocommerce_checkout]`.
- When you want to control the markup in Etch.
- When classic hooks and PHP snippets are sufficient.

## Preparation

> **Etch context:** the checkout is a **Page**, not a Template. In the classic flow, WooCommerce renders the form fields via PHP into the containers you provide — Etch's role is to wrap that markup. You generally don't need many `{this.*}` or `{item.*}` keys here; the dynamic parts come from server-side PHP. See [`10-etch-context-and-templates.md`](./10-etch-context-and-templates.md).

`WooCommerce → Settings → Advanced → Pages → Checkout` — the checkout page must contain `[woocommerce_checkout]` and **not** the Checkout block.

Quick check:

```php
add_action('init', function () {
    $checkout_page_id = wc_get_page_id('checkout');
    $page_content = get_post_field('post_content', $checkout_page_id);
    if (strpos($page_content, 'woocommerce/checkout') !== false) {
        // Block checkout is active — switch back to shortcode if needed
    }
});
```

## Etch HTML — base scaffold

```html
<main id="main" class="site-main woocommerce woocommerce-checkout">

  <!-- Hook: woocommerce_before_checkout_form -->
  <!-- (Standard callbacks: login form for guests + coupon form) -->

  <form name="checkout"
        method="post"
        class="checkout woocommerce-checkout"
        action="{checkout.url}"
        enctype="multipart/form-data">

    <div class="checkout__layout">

      <!-- Left column: billing + shipping + additional -->
      <section class="checkout__customer"
               aria-labelledby="checkout-customer-title">
        <h2 id="checkout-customer-title">Your details</h2>

        <!-- Hook: woocommerce_checkout_before_customer_details -->

        <div class="col-1">
          <div class="woocommerce-billing-fields">
            <h3 class="woocommerce-billing-fields__heading">Billing address</h3>

            <!-- Hook: woocommerce_before_checkout_billing_form -->

            <div class="woocommerce-billing-fields__field-wrapper">
              <!-- Hook: woocommerce_checkout_fields → renders all billing fields -->
              <!-- Etch should leave this container empty and let PHP render -->
            </div>

            <!-- Hook: woocommerce_after_checkout_billing_form -->
          </div>

          <div class="woocommerce-shipping-fields">
            <h3 class="woocommerce-shipping-fields__heading">
              <label>
                <input id="ship-to-different-address-checkbox"
                       class="input-checkbox"
                       type="checkbox"
                       name="ship_to_different_address"
                       value="1">
                Ship to a different address?
              </label>
            </h3>

            <div class="shipping_address">
              <!-- Hook: woocommerce_before_checkout_shipping_form -->
              <!-- Hook: woocommerce_after_checkout_shipping_form -->
            </div>
          </div>
        </div>

        <div class="col-2">
          <div class="woocommerce-additional-fields">
            <h3>Additional information</h3>

            <!-- Hook: woocommerce_before_order_notes -->

            <p class="form-row notes">
              <label for="order_comments">Order notes (optional)</label>
              <textarea name="order_comments"
                        class="input-text"
                        id="order_comments"
                        placeholder="Notes about your order"
                        rows="2"
                        cols="5"></textarea>
            </p>

            <!-- Hook: woocommerce_after_order_notes -->
          </div>
        </div>

        <!-- Hook: woocommerce_checkout_after_customer_details -->
      </section>

      <!-- Right column: order review + payment -->
      <aside class="checkout__summary"
             aria-labelledby="checkout-summary-title">
        <h2 id="checkout-summary-title">Your order</h2>

        <!-- Hook: woocommerce_checkout_before_order_review_heading -->
        <!-- Hook: woocommerce_checkout_before_order_review -->

        <div id="order_review" class="woocommerce-checkout-review-order">
          <!-- Re-rendered via AJAX on field/shipping/payment change -->
          <!-- Default content: items table + totals + payment methods + place-order -->
          <!-- Etch should leave this container empty and let PHP / AJAX render -->
        </div>

        <!-- Hook: woocommerce_checkout_after_order_review -->
      </aside>
    </div>
  </form>

  <!-- Hook: woocommerce_after_checkout_form -->
</main>
```

> **Conceptual note:** in the classic checkout almost all fields are rendered via PHP callbacks. Etch markup is more like the **container** around it; the fields themselves (first name, last name, address, payment methods …) are rendered by WooCommerce via hooks and refreshed via AJAX (`?wc-ajax=update_order_review`) on every change.

## Etch HTML — order review (overwritten via AJAX)

This is the standard markup WooCommerce renders inside `#order_review`. You can use it as a reference but **don't** need to write it yourself — the AJAX update replaces it on every change.

```html
<table class="shop_table woocommerce-checkout-review-order-table">
  <thead>
    <tr>
      <th class="product-name">Product</th>
      <th class="product-total">Subtotal</th>
    </tr>
  </thead>
  <tbody>
    <!-- Hook: woocommerce_review_order_before_cart_contents -->
    <!-- One <tr> per item -->
    <tr class="cart_item">
      <td class="product-name">{cartItem.title} <strong class="product-quantity">× {cartItem.quantity}</strong></td>
      <td class="product-total">{cartItem.subtotal}</td>
    </tr>
    <!-- Hook: woocommerce_review_order_after_cart_contents -->
  </tbody>
  <tfoot>
    <tr class="cart-subtotal">
      <th>Subtotal</th>
      <td>{cart.subtotal}</td>
    </tr>

    <!-- Hook: woocommerce_review_order_before_shipping -->
    <tr class="shipping">
      <th>Shipping</th>
      <td>{cart.shippingTotal}</td>
    </tr>
    <!-- Hook: woocommerce_review_order_after_shipping -->

    <!-- Hook: woocommerce_review_order_before_order_total -->
    <tr class="order-total">
      <th>Total</th>
      <td><strong>{cart.total}</strong></td>
    </tr>
    <!-- Hook: woocommerce_review_order_after_order_total -->
  </tfoot>
</table>

<div id="payment" class="woocommerce-checkout-payment">
  <!-- Hook: woocommerce_review_order_before_payment -->

  <ul class="wc_payment_methods payment_methods methods">
    <!-- One <li> per active payment method -->
  </ul>

  <div class="form-row place-order">
    <!-- Hook: woocommerce_review_order_before_submit -->

    <button type="submit"
            class="button alt"
            name="woocommerce_checkout_place_order"
            id="place_order"
            value="Place order"
            data-value="Place order">
      Place order
    </button>

    <!-- Hook: woocommerce_review_order_after_submit -->

    <input type="hidden" name="woocommerce-process-checkout-nonce" value="{checkout.nonce}">
  </div>

  <!-- Hook: woocommerce_review_order_after_payment -->
</div>
```

## Required classes / attributes

| Element | Required | Why |
|---|---|---|
| `<form name="checkout" class="checkout">` | yes | Checkout JS and AJAX update look for exactly this form |
| `#order_review` container | yes | AJAX update replaces this container's contents |
| `name="ship_to_different_address"` checkbox | yes | Default JS toggles the shipping fields on it |
| `#place_order` button with `name="woocommerce_checkout_place_order"` | yes | Otherwise no place-order submit |
| Hidden `woocommerce-process-checkout-nonce` | yes | Security — automatically rendered by Woo |
| `.payment_methods.methods` `<ul>` | yes | Container for payment-method list |
| `.woocommerce-checkout-review-order-table` | recommended | Default hook target for review hooks |

## Hooks used

### Wrapper / form

| Hook | Position | Standard callback / use |
|---|---|---|
| `woocommerce_before_checkout_form` | Before `<form>` | **Default: login form** (guests) + **coupon form** |
| `woocommerce_after_checkout_form` | After `</form>` | Trust badges |
| `woocommerce_checkout_before_customer_details` | Before left column | Banner |
| `woocommerce_checkout_after_customer_details` | After left column | Helper text |

### Billing / shipping

| Hook | Position | Use |
|---|---|---|
| `woocommerce_before_checkout_billing_form` | Before billing fields | Custom fields above |
| `woocommerce_after_checkout_billing_form` | After billing fields | Custom fields below |
| `woocommerce_before_checkout_shipping_form` | Before shipping fields | (analogous) |
| `woocommerce_after_checkout_shipping_form` | After shipping fields | (analogous) |
| `woocommerce_checkout_fields` (filter) | — | **Filter** for field definition: add, remove, reorder |

### Order notes

| Hook | Position | Use |
|---|---|---|
| `woocommerce_before_order_notes` | Before notes textarea | Helper text |
| `woocommerce_after_order_notes` | After notes textarea | Alternate position for terms checkbox |

### Order review

| Hook | Position | Use |
|---|---|---|
| `woocommerce_checkout_before_order_review` | Before `#order_review` | Custom banner |
| `woocommerce_checkout_after_order_review` | After `#order_review` | Trust badges |
| `woocommerce_review_order_before_cart_contents` | Before items | Banner in the table |
| `woocommerce_review_order_after_cart_contents` | After items | (analogous) |
| `woocommerce_review_order_before_shipping` | Before shipping row | Helper text |
| `woocommerce_review_order_after_shipping` | After shipping row | (analogous) |
| `woocommerce_review_order_before_order_total` | Before total | Tax breakdown |
| `woocommerce_review_order_after_order_total` | After total | "incl. VAT" |
| `woocommerce_review_order_before_payment` | Before payment block | Shipping note |
| `woocommerce_review_order_before_submit` | Before place-order button | Terms checkbox |
| `woocommerce_review_order_after_submit` | After place-order button | Security note |
| `woocommerce_review_order_after_payment` | After payment block | Payment logos |

## PHP layer

### Reorder / add / remove fields

```php
add_filter('woocommerce_checkout_fields', function ($fields) {
    // Make phone required
    $fields['billing']['billing_phone']['required'] = true;

    // Remove company field
    unset($fields['billing']['billing_company']);

    // Add custom field
    $fields['order']['order_referrer'] = [
        'type'        => 'text',
        'label'       => 'How did you hear about us?',
        'required'    => false,
        'class'       => ['form-row-wide'],
        'priority'    => 110,
    ];

    return $fields;
});
```

### Reorder via `priority`

Each field has a `priority` (10, 20, 30 …). Lower values come first:

```php
add_filter('woocommerce_checkout_fields', function ($fields) {
    $fields['billing']['billing_email']['priority'] = 5;
    $fields['billing']['billing_phone']['priority'] = 25;
    return $fields;
});
```

### Validate a custom field

```php
add_action('woocommerce_checkout_process', function () {
    if (!empty($_POST['order_referrer'])
        && mb_strlen(sanitize_text_field(wp_unslash($_POST['order_referrer']))) > 200) {
        wc_add_notice('Please keep the "How did you hear about us?" answer under 200 characters.', 'error');
    }
});
```

### Save a custom field on the order

`woocommerce_checkout_create_order` runs while the order is being built, so `update_meta_data()`
persists under both the legacy and HPOS storage engines (`update_post_meta()` would not write to
HPOS order tables).

```php
add_action('woocommerce_checkout_create_order', function ($order, $data) {
    if (!empty($_POST['order_referrer'])) {
        $order->update_meta_data(
            '_order_referrer',
            sanitize_text_field(wp_unslash($_POST['order_referrer']))
        );
    }
}, 10, 2);
```

### Remove default login and coupon forms (when you render them yourself)

```php
add_action('init', function () {
    remove_action('woocommerce_before_checkout_form', 'woocommerce_checkout_login_form', 10);
    remove_action('woocommerce_before_checkout_form', 'woocommerce_checkout_coupon_form', 10);
});
```

### Custom terms checkbox text

```php
add_filter('woocommerce_get_terms_and_conditions_checkbox_text', function () {
    return 'I have read and agree to the <a href="/terms" target="_blank">Terms</a> and <a href="/privacy" target="_blank">Privacy Policy</a>.';
});
```

### Dynamic place-order button text

```php
add_filter('woocommerce_order_button_text', function () {
    return 'Place order';
});
```

### Filter shipping methods (e.g. only show DHL)

```php
add_filter('woocommerce_package_rates', function ($rates) {
    return array_filter($rates, function ($rate) {
        return $rate->method_id === 'flat_rate' || strpos($rate->method_id, 'dhl') !== false;
    });
});
```

### Recalculate shipping on address change

WooCommerce does this automatically via AJAX (`update_order_review`). If you have a custom field that should affect shipping, mark it `update_totals_on_change`:

```php
add_filter('woocommerce_checkout_fields', function ($fields) {
    $fields['billing']['billing_postcode']['class'][] = 'update_totals_on_change';
    return $fields;
});
```

## Common mistakes

- **Block checkout active** instead of shortcode → classic hooks don't fire, custom markup ignored. First thing to check on any custom checkout.
- `#order_review` container missing → AJAX update has no target, shipping/total don't refresh.
- Custom field without `'required' => false` → automatic validation blocks the submit.
- `form name="checkout"` omitted → checkout JS can't find the form.
- `name="ship_to_different_address"` renamed → toggle JS doesn't work.
- Place-order button has `type="submit"` but **not** `name="woocommerce_checkout_place_order"` → order isn't placed.
- Fields written directly in HTML instead of via `woocommerce_checkout_fields` filter → plugins like tax/shipping calculation, Stripe address element, etc. don't know about the fields.
- Nonce field forgotten or hand-set → server rejects with "security check failed".

## Test checklist

- Open the checkout → fields are rendered (via PHP callbacks), not empty.
- Change address → DevTools → Network: AJAX request `?wc-ajax=update_order_review`, response updates `#order_review`.
- Switch shipping method → total in the review column updates.
- Terms not accepted → submit shows error notice.
- Submit → redirect to `/thank-you` or `/order-received/<id>/` with order data.
- DevTools → form-data on submit: all required fields + nonce + place-order name.
- Confirmation email arrives.

## Block checkout — note

If you do want to keep the block checkout:

- Classic hooks (`woocommerce_checkout_fields`, `woocommerce_review_order_*` …) do **not** fire.
- Instead: register block field extensions via `@woocommerce/blocks-registry`.
- Docs: <https://github.com/woocommerce/woocommerce-blocks/blob/trunk/docs/third-party-developers/extensibility/checkout-block/README.md>
- Register custom fields there via `__experimentalRegisterCheckoutFilters` and `registerCheckoutBlock`.
- Etch HTML has **no influence** on the render markup in the block context — it's React-rendered.

→ If you want full markup control in Etch, stay classic.
