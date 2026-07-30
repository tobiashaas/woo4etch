# 05 — Mini-Cart (Header)

Small cart indicator in the header with live update via Woo fragments. Counter + dropdown with cart content.

## When to use

- Visible in the site header on every page.
- Should update without reload when someone uses AJAX add-to-cart from the loop.
- Optionally a dropdown/drawer with the cart items.

## Preparation

> **Ready-made version (Woo4Etch 1.6.0+):** the plugin ships this as the "Header mini-cart (link + dropdown)" layout — link with live `{options.cart_count}`, a hover/focus dropdown with item rows, subtotal and view-cart/checkout buttons, and a "Your cart is empty." message instead of an empty panel. Pure CSS reveal (`:hover` + `:focus-within`, keyboard-accessible, no JS); the wrapper carries `data-w4e-cart-region="mini-cart"`, so the whole dropdown re-renders in place after every Store API cart write (1.7.0+), and the count span carries the `mini-cart-count` class both update mechanisms target. Copy JSON from the admin page (or `templates/etch-copy/mini-cart.json`) and paste it into your header. The hand-built variant below remains the full-control alternative.

> **Etch context:** the mini-cart is a **Component** placed in the header (or other global areas). There is no `{this.*}` context — item rows, count and subtotal come from the Woo4Etch cart bridge (`{options.cart_items}`, `{options.cart_count}`, `{options.cart_subtotal}` — real Dynamic Keys, sample rows in the builder). Live updates after cart changes come from the two mechanisms described below. See [`10-etch-context-and-templates.md`](./10-etch-context-and-templates.md).

## Two live-update mechanisms

The mini-cart stays current through two mechanisms that coexist:

1. **Woo4Etch Store API layer (1.7.0+, default on).** After every cart write it
   makes (add-to-cart form submits, cart-page quantity/coupon/remove actions),
   the plugin refetches the current page and swaps every
   `[data-w4e-cart-region]` element — mark your mini-cart wrapper with
   `data-w4e-cart-region="mini-cart"` and the whole dropdown re-renders as
   fresh server-side Etch HTML. Plain `.mini-cart-count` counters anywhere on
   the page are synced too. Your scripts can listen for the
   `woo4etch:cart-updated` event on `document`.

2. **Classic WooCommerce fragments.** Woo's own AJAX add-to-cart buttons in
   archive loops (`?wc-ajax=add_to_cart`) update the DOM by replacing
   registered CSS selectors — that path doesn't go through the Woo4Etch layer,
   so a fragment for `.mini-cart-count` (PHP layer below) keeps the counter
   live for those buttons as well. The Woo4Etch layer triggers Woo's
   `wc_fragment_refresh` after its own writes, so fragment-based third-party
   mini-carts stay in sync too.

Fragments are loaded automatically when AJAX add-to-cart is enabled
(`WooCommerce → Settings → Products → General`). The script is
`wc-cart-fragments`. If fragments don't seem to load, force-enqueue once:

```php
add_action('wp_enqueue_scripts', function () {
    wp_enqueue_script('wc-cart-fragments');
});
```

## Etch HTML — Minimal counter

```html
<a href="{options.cart_url}"
   class="mini-cart"
   aria-label="View cart">
  <span class="mini-cart__icon" aria-hidden="true">
    <!-- SVG cart icon -->
  </span>
  <span class="mini-cart-count"
        data-count="{options.cart_count}">{options.cart_count}</span>
</a>
```

> The class `mini-cart-count` is **important** — both update mechanisms target it: the Woo4Etch layer syncs its text after every write, and it's the fragment selector for Woo's AJAX loop buttons (PHP layer below).

## Etch HTML — Mini-cart with dropdown

```html
<!-- data-w4e-cart-region: the Woo4Etch layer swaps this whole element with
     freshly rendered HTML after every cart write — rows, count, subtotal and
     the empty state all stay current without any fragment registration. -->
<div class="mini-cart-wrapper"
     data-w4e-cart-region="mini-cart"
     data-cart-open="false">

  <a href="{options.cart_url}"
     class="mini-cart"
     aria-haspopup="dialog"
     aria-expanded="false"
     aria-controls="mini-cart-dropdown"
     aria-label="View cart ({options.cart_count} items)">
    <span class="mini-cart__icon" aria-hidden="true">
      <!-- SVG cart icon -->
    </span>
    <span class="mini-cart-count" data-count="{options.cart_count}">{options.cart_count}</span>
  </a>

  <div id="mini-cart-dropdown"
       class="mini-cart-dropdown widget_shopping_cart_content"
       role="dialog"
       aria-label="Cart contents"
       hidden>

    <!-- Hook: woocommerce_before_mini_cart -->

    <ul class="woocommerce-mini-cart cart_list product_list_widget">
      {#loop options.cart_items as item}
      <li class="woocommerce-mini-cart-item mini_cart_item">
        <a href="{item.remove_url}"
           class="remove remove_from_cart_button"
           aria-label="Remove {item.name}"
           data-product_id="{item.id}"
           data-cart_item_key="{item.key}">×</a>

        <a href="{item.permalink}">
          <img src="{item.image}"
               alt="{item.name}"
               width="80" height="80">
          {item.name}
        </a>

        <span class="quantity">
          {item.quantity} ×
          <span class="woocommerce-Price-amount">{item.price}</span>
        </span>
      </li>
      {/loop}

      <!-- Hook: woocommerce_mini_cart_contents -->
    </ul>

    <p class="woocommerce-mini-cart__total total">
      <strong>Subtotal:</strong>
      <span class="woocommerce-Price-amount">{options.cart_subtotal}</span>
    </p>

    <!-- Hook: woocommerce_widget_shopping_cart_before_buttons -->

    <p class="woocommerce-mini-cart__buttons buttons">
      <a href="{options.cart_url}" class="button wc-forward">View cart</a>
      <a href="{options.checkout_url}" class="button checkout wc-forward">Checkout</a>
    </p>

    <!-- Hook: woocommerce_widget_shopping_cart_after_buttons -->

    <!-- Hook: woocommerce_after_mini_cart -->
  </div>
</div>
```

## Required classes / attributes

| Element | Required | Why |
|---|---|---|
| `data-w4e-cart-region="mini-cart"` on the wrapper | recommended | Woo4Etch swaps this element after every Store API cart write |
| `.mini-cart-count` | yes | Counter target for both mechanisms (Woo4Etch text sync + fragment selector) |
| `.widget_shopping_cart_content` wrapper | recommended | Default fragment target — Woo's standard widget replaces it whole |
| `.cart_list.product_list_widget` | recommended | Default styling of the mini-cart list |
| `.remove_from_cart_button` | yes | Class triggers AJAX remove |
| `data-cart_item_key` on the remove link | yes | Identifies the cart row |
| `aria-expanded`, `aria-controls`, `aria-haspopup` on the toggle | recommended | Accessibility for dropdown |

## Hooks used

| Hook | Position | Use |
|---|---|---|
| `woocommerce_before_mini_cart` | Before `<ul>` | Banner, shipping-threshold note |
| `woocommerce_mini_cart_contents` | Inside the loop | Custom items, notices |
| `woocommerce_after_mini_cart` | After everything | Trust badges |
| `woocommerce_widget_shopping_cart_before_buttons` | Before `.buttons` | Shipping note |
| `woocommerce_widget_shopping_cart_after_buttons` | After `.buttons` | "Continue shopping" link |

> The **important concept** here is the **fragments**, not the hooks. Hooks are useful, but the update behavior runs entirely through `woocommerce_add_to_cart_fragments`.

## PHP layer — registering fragments

### Counter without dropdown

```php
add_filter('woocommerce_add_to_cart_fragments', function ($fragments) {
    ob_start();
    $count = WC()->cart->get_cart_contents_count();
    ?>
    <span class="mini-cart-count" data-count="<?php echo esc_attr($count); ?>">
      <?php echo esc_html($count); ?>
    </span>
    <?php
    $fragments['span.mini-cart-count'] = ob_get_clean();
    return $fragments;
});
```

**Important:** the array key (`span.mini-cart-count`) must be a **CSS selector** that matches **exactly one element** on the page. Woo replaces that element completely with the HTML from the fragment.

### Subtotal as a second fragment

```php
add_filter('woocommerce_add_to_cart_fragments', function ($fragments) {
    ob_start();
    ?>
    <span class="mini-cart-subtotal">
      <?php echo WC()->cart->get_cart_subtotal(); ?>
    </span>
    <?php
    $fragments['span.mini-cart-subtotal'] = ob_get_clean();
    return $fragments;
});
```

### Replace the entire dropdown

If you want to re-render the whole dropdown on every cart change:

```php
add_filter('woocommerce_add_to_cart_fragments', function ($fragments) {
    ob_start();
    ?>
    <div id="mini-cart-dropdown"
         class="mini-cart-dropdown widget_shopping_cart_content"
         role="dialog"
         aria-label="Cart contents"
         hidden>
      <?php woocommerce_mini_cart(); ?>
    </div>
    <?php
    $fragments['#mini-cart-dropdown'] = ob_get_clean();
    return $fragments;
});
```

`woocommerce_mini_cart()` renders the standard mini-cart template (including all hooks).

### Shipping-threshold display ("Add X more for free shipping")

```php
add_action('woocommerce_before_mini_cart', function () {
    $threshold = 50.00;
    $current = WC()->cart->get_subtotal();
    $diff = $threshold - $current;

    if ($diff > 0) {
        printf(
            '<p class="mini-cart__threshold">Add <strong>%s</strong> for free shipping</p>',
            wc_price($diff)
        );
    } else {
        echo '<p class="mini-cart__threshold mini-cart__threshold--unlocked">You qualify for free shipping ✓</p>';
    }
});
```

### Auto-open the mini-cart on add

```js
jQuery(document.body).on('added_to_cart', function () {
  document.querySelector('.mini-cart-wrapper')?.setAttribute('data-cart-open', 'true');
  document.querySelector('#mini-cart-dropdown')?.removeAttribute('hidden');
  document.querySelector('.mini-cart')?.setAttribute('aria-expanded', 'true');
});
```

### Toggle button logic (vanilla JS)

```js
document.addEventListener('click', (e) => {
  const toggle = e.target.closest('.mini-cart');
  if (!toggle) return;

  // Prevent navigation to the cart page
  e.preventDefault();

  const wrapper = toggle.closest('.mini-cart-wrapper');
  const dropdown = wrapper.querySelector('#mini-cart-dropdown');
  const isOpen = wrapper.getAttribute('data-cart-open') === 'true';

  wrapper.setAttribute('data-cart-open', String(!isOpen));
  toggle.setAttribute('aria-expanded', String(!isOpen));

  if (isOpen) {
    dropdown.setAttribute('hidden', '');
  } else {
    dropdown.removeAttribute('hidden');
  }
});

// Click outside closes
document.addEventListener('click', (e) => {
  if (!e.target.closest('.mini-cart-wrapper')) {
    document.querySelectorAll('.mini-cart-wrapper').forEach((el) => {
      el.setAttribute('data-cart-open', 'false');
      el.querySelector('.mini-cart')?.setAttribute('aria-expanded', 'false');
      el.querySelector('#mini-cart-dropdown')?.setAttribute('hidden', '');
    });
  }
});
```

## Common mistakes

- **Toggle state resets after a cart write** — the Woo4Etch region swap replaces the wrapper, so JS-managed open/close state (`data-cart-open`, `hidden`) reverts to the markup default. Prefer the pure-CSS reveal (`:hover` + `:focus-within`, as in the shipped layout), or re-open in a `woo4etch:cart-updated` listener.
- Fragment selector matches multiple elements → only the *first* gets replaced, the rest stay stale.
- Selector matches no element → silent fail, counter stays stale without an error.
- HTML in the fragment has a different root element than the selector → after the first replace, the selector no longer matches.
- `wc-cart-fragments` script not loaded (e.g. because AJAX add-to-cart is disabled) → no live update.
- Counter cached by page caching → cart value is wrong on first page view. Exclude logged-in users + cart cookies from caching.
- Mini-cart dropdown uses `position: fixed` layout that doesn't work on mobile → drawer variant is better.

## Test checklist

- Add a product from the loop → counter increases without reload.
- Add a product via the single-product form (plugin active) → dropdown rows, count and subtotal update without reload (Network: `POST /wc/store/v1/cart/add-item`, then a page refetch).
- DevTools → Network: request `?wc-ajax=add_to_cart`, response contains a `fragments` object with your selector.
- Counter syncs across tabs → open a second tab, add a product, the first tab updates (Woo uses `localStorage` for cross-tab sync).
- Visit cart page, remove an item → go back to home, counter is correct.
- Keyboard: toggle reachable via Tab, Enter opens dropdown, Escape closes.
- Screen reader: `aria-label` with item count is announced.
