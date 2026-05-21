# 08 — Thank You / Order Received

Order confirmation page shown after a successful checkout. Order summary, customer details, payment instructions (for offline payment methods like bank transfer).

## When to use

- After successful checkout — WooCommerce redirects to `/checkout/order-received/<order_id>/?key=<order_key>`.
- For payment methods that need post-order instructions (BACS, COD).
- As a tracking entry point (Google Ads conversion, GA4 purchase event, Meta pixel).

## Preparation

> **Etch context:** the thank-you page is the `order-received` endpoint on the **Checkout Page** — so technically it's still a Page. The `{order.*}` keys in the markup below are **placeholders** that need to be filled either by letting WooCommerce render the order summary via `woocommerce_thankyou` hook callbacks, or by exposing the order to Etch via a custom data source. See [`10-etch-context-and-templates.md`](./10-etch-context-and-templates.md).

The thank-you page is the `order-received` endpoint on the checkout page. It needs:

- The checkout page exists with `[woocommerce_checkout]` or the Checkout Block.
- Permalinks set to anything other than "Plain".

The page handles two access types:
1. **Just placed the order** → `order` query var contains the order ID.
2. **Customer follows the link later** → requires `key` query param matching the order key.

## Etch HTML — Thank-you template

```html
<main id="main" class="site-main woocommerce woocommerce-order-received">

  <!-- Hook: woocommerce_before_thankyou (passes $order_id) -->

  <article class="woocommerce-order">

    <!-- Hook: woocommerce_thankyou_<payment_method> (e.g. woocommerce_thankyou_bacs) -->
    <!-- Default: payment-method-specific instructions, like bank transfer details -->

    <!-- Hook: woocommerce_thankyou (passes $order_id) -->
    <!-- Default: renders the entire thank-you block below -->

    <p class="woocommerce-notice
              woocommerce-notice--success
              woocommerce-thankyou-order-received">
      Thank you. Your order has been received.
    </p>

    <ul class="woocommerce-order-overview
               woocommerce-thankyou-order-details order_details">
      <li class="woocommerce-order-overview__order order">
        Order number:
        <strong>{order.number}</strong>
      </li>
      <li class="woocommerce-order-overview__date date">
        Date:
        <strong>{order.dateFormatted}</strong>
      </li>
      <li class="woocommerce-order-overview__email email">
        Email:
        <strong>{order.billingEmail}</strong>
      </li>
      <li class="woocommerce-order-overview__total total">
        Total:
        <strong>{order.total}</strong>
      </li>
      <li class="woocommerce-order-overview__payment-method method">
        Payment method:
        <strong>{order.paymentMethodTitle}</strong>
      </li>
    </ul>

    <!-- Hook: woocommerce_order_details_before_order_table -->

    <section class="woocommerce-order-details">
      <h2 class="woocommerce-order-details__title">Order details</h2>

      <table class="woocommerce-table
                    woocommerce-table--order-details
                    shop_table order_details">
        <thead>
          <tr>
            <th class="woocommerce-table__product-name product-name">Product</th>
            <th class="woocommerce-table__product-total product-total">Total</th>
          </tr>
        </thead>

        <tbody>
          {#loop order.items as orderItem}
          <tr class="woocommerce-table__line-item order_item">
            <td class="woocommerce-table__product-name product-name">
              {orderItem.title}
              <strong class="product-quantity">×&nbsp;{orderItem.quantity}</strong>
              <!-- Optional: variations -->
              <dl class="variation">
                <dt>Size:</dt>
                <dd>{orderItem.variation.pa_size}</dd>
              </dl>
            </td>
            <td class="woocommerce-table__product-total product-total">
              {orderItem.subtotal}
            </td>
          </tr>
          {/loop}
        </tbody>

        <tfoot>
          <tr>
            <th scope="row">Subtotal:</th>
            <td>{order.subtotal}</td>
          </tr>
          <tr>
            <th scope="row">Shipping:</th>
            <td>{order.shippingTotal} via {order.shippingMethodTitle}</td>
          </tr>
          <tr>
            <th scope="row">Payment method:</th>
            <td>{order.paymentMethodTitle}</td>
          </tr>
          <tr>
            <th scope="row">Total:</th>
            <td><strong>{order.total}</strong></td>
          </tr>

          <!-- Optional: customer note -->
          <tr>
            <th scope="row">Note:</th>
            <td>{order.customerNote}</td>
          </tr>
        </tfoot>
      </table>
    </section>

    <!-- Hook: woocommerce_order_details_after_order_table -->

    <!-- Hook: woocommerce_order_details_before_customer_details -->

    <section class="woocommerce-customer-details">
      <h2>Customer details</h2>

      <div class="col2-set addresses">
        <div class="col-1">
          <h3>Billing address</h3>
          <address>{order.billing.formatted}</address>

          <!-- Hook: woocommerce_order_details_after_customer_details (passes $order) -->
        </div>

        <div class="col-2">
          <h3>Shipping address</h3>
          <address>{order.shipping.formatted}</address>
        </div>
      </div>
    </section>

    <!-- Hook: woocommerce_order_details_after_customer_details -->
  </article>
</main>
```

## Required classes / attributes

| Element | Required | Why |
|---|---|---|
| `.woocommerce-order-received` body class | recommended | Plugin compatibility (tracking pixels, etc.) |
| `.woocommerce-order-overview` summary list | recommended | Default styling + plugin extensibility |
| `.shop_table.order_details` on items table | recommended | Default-responsive styling |
| `.product-quantity` for line-item count | recommended | Plugin styling hooks |
| `<address>` element for addresses | recommended | Semantic + screen-reader-friendly |

## Hooks used

| Hook | Position | Use / default callback |
|---|---|---|
| `woocommerce_before_thankyou` | Before order block, passes `$order_id` | Custom banner |
| `woocommerce_thankyou_<payment_method>` | Before main thank-you block | **Default**: payment-method-specific instructions (e.g. BACS bank details) |
| `woocommerce_thankyou` | Main thank-you slot, passes `$order_id` | **Default**: renders order overview + details — careful if you replace it |
| `woocommerce_order_details_before_order_table` | Before items table | Banner |
| `woocommerce_order_details_after_order_table` | After items table | Trust badges |
| `woocommerce_order_details_before_customer_details` | Before customer block | (analogous) |
| `woocommerce_order_details_after_customer_details` | After customer block, passes `$order` | Cross-sells, support link |
| `woocommerce_get_order_item_totals` (Filter) | — | Add/remove rows in the totals `<tfoot>` |

## PHP layer

### Tracking pixel (Google Ads conversion)

```php
add_action('woocommerce_thankyou', function ($order_id) {
    if (!$order_id) return;
    $order = wc_get_order($order_id);
    if (!$order) return;

    // Avoid double-firing on page reload (HPOS-safe: flag stored on the order).
    if ($order->get_meta('_conversion_tracked')) return;
    $order->update_meta_data('_conversion_tracked', '1');
    $order->save();

    ?>
    <!-- Google Ads conversion -->
    <script>
      gtag('event', 'conversion', {
        send_to: 'AW-XXXXXX/YYYYYY',
        value: <?php echo (float) $order->get_total(); ?>,
        currency: '<?php echo esc_js($order->get_currency()); ?>',
        transaction_id: '<?php echo esc_js($order->get_order_number()); ?>'
      });
    </script>
    <?php
});
```

### GA4 purchase event

```php
add_action('woocommerce_thankyou', function ($order_id) {
    $order = wc_get_order($order_id);
    if (!$order) return;
    if ($order->get_meta('_ga4_tracked')) return;
    $order->update_meta_data('_ga4_tracked', '1');
    $order->save();

    $items = [];
    foreach ($order->get_items() as $item) {
        $product = $item->get_product();
        if (!$product) continue;
        $items[] = [
            'item_id'   => $product->get_sku() ?: $product->get_id(),
            'item_name' => $item->get_name(),
            'price'     => (float) $order->get_item_subtotal($item, false, false),
            'quantity'  => (int) $item->get_quantity(),
        ];
    }

    ?>
    <script>
      gtag('event', 'purchase', {
        transaction_id: '<?php echo esc_js($order->get_order_number()); ?>',
        value: <?php echo (float) $order->get_total(); ?>,
        currency: '<?php echo esc_js($order->get_currency()); ?>',
        items: <?php echo wp_json_encode($items); ?>
      });
    </script>
    <?php
});
```

### Add a row to the totals table

```php
add_filter('woocommerce_get_order_item_totals', function ($totals, $order) {
    $totals['custom_note'] = [
        'label' => 'Estimated delivery:',
        'value' => '3–5 business days',
    ];
    return $totals;
}, 10, 2);
```

### Custom thank-you headline per payment method

```php
add_action('woocommerce_thankyou_bacs', function ($order_id) {
    echo '<div class="bacs-notice">';
    echo '<h2>Please complete your bank transfer</h2>';
    echo '<p>Your order will ship as soon as payment is received. Use the bank details below.</p>';
    echo '</div>';
}, 5);
```

### Send the user to a custom page after order

```php
add_action('woocommerce_thankyou', function ($order_id) {
    $order = wc_get_order($order_id);
    if ($order && $order->is_paid()) {
        wp_safe_redirect(home_url('/welcome-customer/?order=' . $order_id));
        exit;
    }
}, 1); // priority 1 = early
```

> Only redirect for **paid** orders. Don't redirect when the order is `pending` (BACS, COD), because the user still needs the on-page instructions.

### Hide the thank-you content if the order is not theirs

For orders placed by a **logged-in customer**, WooCommerce already verifies that the current user matches the order's customer — otherwise it shows a login form instead of the order. This is controlled by `woocommerce_order_received_verify_known_shoppers`, which **defaults to `true`**, so adding `__return_true` changes nothing. You'd only touch it to *relax* the check (not recommended):

```php
// Stop verifying that the logged-in user owns the order.
add_filter('woocommerce_order_received_verify_known_shoppers', '__return_false');
```

This check does **not** cover guest orders (no customer ID) — those stay accessible to anyone holding the order key in the URL. To protect sensitive content on guest orders, gate it yourself (e.g. check `is_user_logged_in()` or match the order email) before printing it.

## Common mistakes

- Tracking pixel fires on every page load (e.g. user hits back/refresh) → use a meta flag like `_conversion_tracked`.
- Redirecting BACS orders → bank details never shown to the customer.
- Customer email shown without checking permissions → leaks if URL is shared.
- Replacing the entire `woocommerce_thankyou` callback → loses payment-method-specific instructions for offline payments.
- Tracking script before `gtag` is initialized → silent failure.

## Test checklist

- Complete an order with a paid method → thank-you shows order summary.
- Complete an order with BACS → bank details appear above the order summary.
- Open the order URL again → page still loads (with order key) but no double tracking.
- Network: GA4 / Google Ads requests fire **once** per order.
- Mobile: order details table is responsive.
- Confirm email is sent (separate concern, see `09-emails.md`).
