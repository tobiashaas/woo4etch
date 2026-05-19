# 14 — Visual Hook Guides

The single most useful resource for figuring out *where exactly* a WooCommerce hook fires: Business Bloomer's visual hook guides. Each guide is an annotated screenshot of the rendered page with every action and filter labeled in place.

When you're working on a custom Etch layout and you need to figure out "where can I inject this snippet?", bookmark these.

## Guides by page

### Shop / archive

[Visual hook guide — Shop page](https://www.businessbloomer.com/woocommerce-visual-hook-guide-shop-page/)

Covers: `woocommerce_before_main_content`, `woocommerce_before_shop_loop`, the loop item hooks (`woocommerce_before_shop_loop_item`, `…_title`, `_after_…`), `woocommerce_after_shop_loop`, pagination, sidebar hooks.

### Single product page

[Visual hook guide — Single product](https://www.businessbloomer.com/woocommerce-visual-hook-guide-single-product-page/)

Covers: gallery hooks (`woocommerce_before_single_product_summary`, `_product_thumbnails`), summary hooks (`woocommerce_single_product_summary` priority 5/10/20/30/40), add-to-cart hooks (covered in template 01/02), tab hooks (`woocommerce_product_tabs`, `_after_single_product_summary`), related products, upsells.

### Cart page

[Visual hook guide — Cart page](https://www.businessbloomer.com/woocommerce-visual-hook-guide-cart-page/)

Covers: `woocommerce_before_cart`, the contents hooks (`_before_cart_table`, `_cart_contents`, `_after_cart_contents`), coupon/actions slots, cart-totals hooks (`woocommerce_cart_totals_before_order_total`, `_after_…`), `woocommerce_proceed_to_checkout`, `woocommerce_after_cart`.

### Checkout page (classic)

[Visual hook guide — Checkout page](https://www.businessbloomer.com/woocommerce-visual-hook-guide-checkout-page/)

Covers: form hooks (`woocommerce_before_checkout_form`, `_billing/shipping_form`, `_order_notes`), order review (`_review_order_before_cart_contents`, `_before_shipping`, `_before_order_total`, `_before_payment`, `_before_submit`), `woocommerce_after_checkout_form`.

### My Account

[Visual hook guide — My Account page](https://www.businessbloomer.com/woocommerce-visual-hook-guide-my-account-page/)

Covers: navigation hooks (`woocommerce_before_account_navigation`, `_after_…`), endpoint-specific hooks (orders, downloads, edit-address, edit-account), login/register form hooks.

### Order received / thank-you

[Visual hook guide — Thank You page](https://www.businessbloomer.com/woocommerce-visual-hook-guide-thank-you-page/)

Covers: `woocommerce_before_thankyou`, `woocommerce_thankyou_<payment_method>`, `woocommerce_thankyou`, `woocommerce_order_details_before_order_table`, `_after_order_table`, `_before_customer_details`, `_after_customer_details`.

### Emails

[Visual hook guide — Emails](https://www.businessbloomer.com/woocommerce-visual-hook-guide-emails/)

Covers: `woocommerce_email_header`, `woocommerce_email_order_details`, `woocommerce_email_order_meta`, `woocommerce_email_customer_details`, `woocommerce_email_footer`.

### Mini-cart (cart widget)

[Visual hook guide — Cart widget](https://www.businessbloomer.com/woocommerce-visual-hook-guide-cart-widget/)

Covers: `woocommerce_before_mini_cart`, `woocommerce_mini_cart_contents`, `woocommerce_widget_shopping_cart_before_buttons`, `_after_…`, `woocommerce_after_mini_cart`.

## How to read a hook guide

Each guide annotates the rendered page with two kinds of marker:

- **`woocommerce_action_name`** — fires at this exact position in the markup. Hook a callback to `add_action()` to insert content here.
- **Numbers like `10`, `20`** — the priority of the default callback already attached. To replace it, use `remove_action('hook_name', 'callback_name', priority)`.

Example from the single-product page:

> `woocommerce_single_product_summary` — priorities 5 (title), 10 (price), 20 (excerpt), 30 (add to cart), 40 (meta), 50 (sharing)

So if you want to move the price below the add-to-cart button, you `remove_action(... priority 10)` and `add_action(... priority 35)`.

## Finding hooks not in a guide

For more obscure spots (admin pages, REST API, payment gateways), search the WooCommerce source on GitHub:

```
https://github.com/search?q=repo%3Awoocommerce%2Fwoocommerce+do_action(%27<hook_name>&type=code
```

Or grep your local copy:

```bash
grep -r "do_action('woocommerce_" wp-content/plugins/woocommerce/ | less
```

## Companion utility — `kr_show_hook` debug callback

Drop this into your mu-plugin during development to see which hooks fire on a page:

```php
add_action('all', function ($hook) {
    if (!current_user_can('manage_options')) return;
    if (!isset($_GET['debug_hooks'])) return;
    if (strpos($hook, 'woocommerce_') !== 0) return;
    echo "<!-- HOOK: $hook -->\n";
});
```

Visit any Woo page with `?debug_hooks=1` while logged in as admin, then view source.

## Sources

- Business Bloomer — [WooCommerce Visual Hook Guides index](https://www.businessbloomer.com/?s=visual+hook+guide)
- WooCommerce — [Introduction to Hooks, Actions & Filters](https://woocommerce.com/document/introduction-to-hooks-actions-and-filters/)
