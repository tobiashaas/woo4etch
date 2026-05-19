# 05 — Mini-Cart (Header)

Small cart indicator in the header with live update via Woo fragments. Counter + dropdown with cart content.

## When to use

- Visible in the site header on every page.
- Should update without reload when someone uses AJAX add-to-cart from the loop.
- Optionally a dropdown/drawer with the cart items.

## Preparation

> **Etch context:** the mini-cart is a **Component** placed in the header (or other global areas). There is no `{this.*}` context. Counter values and item data come from `{user.*}` (e.g. logged-in state), a custom data source, and — most importantly — **WooCommerce fragments**, which replace specific DOM nodes via the matching CSS selector. See [`10-etch-context-and-templates.md`](./10-etch-context-and-templates.md).

WooCommerce fragments are loaded automatically when AJAX add-to-cart is enabled (`WooCommerce → Settings → Products → General`). The script is `wc-cart-fragments` and handles live updates without reload.

If fragments don't seem to load, force-enqueue once:

```php
add_action('wp_enqueue_scripts', function () {
    wp_enqueue_script('wc-cart-fragments');
});
```

## Etch HTML — Minimal counter

```html
<a href="{cart.url}"
   class="mini-cart"
   aria-label="View cart">
  <span class="mini-cart__icon" aria-hidden="true">
    <!-- SVG cart icon -->
  </span>
  <span class="mini-cart-count"
        data-count="{cart.count}">{cart.count}</span>
</a>
```

> The class `mini-cart-count` is **important** — it's the fragment selector. More on this in the PHP layer below.

## Etch HTML — Mini-cart with dropdown

```html
<div class="mini-cart-wrapper"
     data-cart-open="false">

  <a href="{cart.url}"
     class="mini-cart"
     aria-haspopup="dialog"
     aria-expanded="false"
     aria-controls="mini-cart-dropdown"
     aria-label="View cart ({cart.count} items)">
    <span class="mini-cart__icon" aria-hidden="true">
      <!-- SVG cart icon -->
    </span>
    <span class="mini-cart-count" data-count="{cart.count}">{cart.count}</span>
  </a>

  <div id="mini-cart-dropdown"
       class="mini-cart-dropdown widget_shopping_cart_content"
       role="dialog"
       aria-label="Cart contents"
       hidden>

    <!-- Hook: woocommerce_before_mini_cart -->

    <ul class="woocommerce-mini-cart cart_list product_list_widget">
      {#loop cartItems as cartItem}
      <li class="woocommerce-mini-cart-item mini_cart_item">
        <a href="{cartItem.removeUrl}"
           class="remove remove_from_cart_button"
           aria-label="Remove {cartItem.title}"
           data-product_id="{cartItem.productId}"
           data-cart_item_key="{cartItem.key}">×</a>

        <a href="{cartItem.permalink}">
          <img src="{cartItem.image.url}"
               alt="{cartItem.title}"
               width="80" height="80">
          {cartItem.title}
        </a>

        <span class="quantity">
          {cartItem.quantity} ×
          <span class="woocommerce-Price-amount">{cartItem.price}</span>
        </span>
      </li>
      {/loop}

      <!-- Hook: woocommerce_mini_cart_contents -->
    </ul>

    <p class="woocommerce-mini-cart__total total">
      <strong>Subtotal:</strong>
      <span class="woocommerce-Price-amount">{cart.subtotal}</span>
    </p>

    <!-- Hook: woocommerce_widget_shopping_cart_before_buttons -->

    <p class="woocommerce-mini-cart__buttons buttons">
      <a href="{cart.url}" class="button wc-forward">View cart</a>
      <a href="{checkout.url}" class="button checkout wc-forward">Checkout</a>
    </p>

    <!-- Hook: woocommerce_widget_shopping_cart_after_buttons -->

    <!-- Hook: woocommerce_after_mini_cart -->
  </div>
</div>
```

## Required classes / attributes

| Element | Required | Why |
|---|---|---|
| `.mini-cart-count` | yes | Fragment selector — Woo writes the counter through it |
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

- Fragment selector matches multiple elements → only the *first* gets replaced, the rest stay stale.
- Selector matches no element → silent fail, counter stays stale without an error.
- HTML in the fragment has a different root element than the selector → after the first replace, the selector no longer matches.
- `wc-cart-fragments` script not loaded (e.g. because AJAX add-to-cart is disabled) → no live update.
- Counter cached by page caching → cart value is wrong on first page view. Exclude logged-in users + cart cookies from caching.
- Mini-cart dropdown uses `position: fixed` layout that doesn't work on mobile → drawer variant is better.

## Test checklist

- Add a product from the loop → counter increases without reload.
- DevTools → Network: request `?wc-ajax=add_to_cart`, response contains a `fragments` object with your selector.
- Counter syncs across tabs → open a second tab, add a product, the first tab updates (Woo uses `localStorage` for cross-tab sync).
- Visit cart page, remove an item → go back to home, counter is correct.
- Keyboard: toggle reachable via Tab, Enter opens dropdown, Escape closes.
- Screen reader: `aria-label` with item count is announced.
