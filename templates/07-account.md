# 07 — My Account

Customer account area: dashboard, orders, downloads, addresses, account details, logout. Built on top of WooCommerce's `[woocommerce_my_account]` shortcode.

## When to use

- On the My Account page (`/my-account`).
- For sub-endpoints: `/my-account/orders`, `/my-account/edit-address`, etc.
- When you want custom markup for the dashboard but keep WooCommerce's auth flow.

## Preparation

> **Etch context:** the My Account view is a **Page** (not a Template), with the shortcode `[woocommerce_my_account]` doing the heavy lifting. The current user is available via `{user.*}` (e.g. `{user.displayName}`, `{user.loggedIn}`). Order data inside endpoints is rendered by WooCommerce PHP — Etch only wraps it. See [`10-etch-context-and-templates.md`](./10-etch-context-and-templates.md).

The My Account page must contain `[woocommerce_my_account]`. Check at `WooCommerce → Settings → Advanced → Pages → My account`.

WooCommerce routes the sub-pages through **endpoints** (rewrite rules), not separate pages. If your URLs look wrong (`?orders=` instead of `/orders/`), flush permalinks once: `Settings → Permalinks → Save`.

## Etch HTML — Account wrapper (logged-in view)

```html
<main id="main" class="site-main woocommerce woocommerce-account">

  <!-- Hook: woocommerce_before_account_navigation -->

  <div class="woocommerce-MyAccount-navigation">
    <nav aria-label="Account navigation">
      <ul role="list">
        <li class="woocommerce-MyAccount-navigation-link
                   woocommerce-MyAccount-navigation-link--dashboard
                   is-active">
          <a href="/my-account/">Dashboard</a>
        </li>
        <li class="woocommerce-MyAccount-navigation-link
                   woocommerce-MyAccount-navigation-link--orders">
          <a href="/my-account/orders/">Orders</a>
        </li>
        <li class="woocommerce-MyAccount-navigation-link
                   woocommerce-MyAccount-navigation-link--downloads">
          <a href="/my-account/downloads/">Downloads</a>
        </li>
        <li class="woocommerce-MyAccount-navigation-link
                   woocommerce-MyAccount-navigation-link--edit-address">
          <a href="/my-account/edit-address/">Addresses</a>
        </li>
        <li class="woocommerce-MyAccount-navigation-link
                   woocommerce-MyAccount-navigation-link--edit-account">
          <a href="/my-account/edit-account/">Account details</a>
        </li>
        <li class="woocommerce-MyAccount-navigation-link
                   woocommerce-MyAccount-navigation-link--customer-logout">
          <a href="{account.logoutUrl}">Logout</a>
        </li>
      </ul>
    </nav>
  </div>

  <!-- Hook: woocommerce_after_account_navigation -->

  <div class="woocommerce-MyAccount-content">

    <!-- Hook: woocommerce_account_content -->

    <!-- The actual content here depends on the endpoint -->
    <!-- Etch should either render endpoint-specific markup or let WooCommerce handle it via PHP callbacks -->
  </div>
</main>
```

## Etch HTML — Dashboard endpoint

```html
<p>
  Hello <strong>{user.displayName}</strong>
  (not {user.displayName}? <a href="{account.logoutUrl}">Log out</a>)
</p>

<p>
  From your account dashboard you can view your
  <a href="/my-account/orders/">recent orders</a>,
  manage your <a href="/my-account/edit-address/">shipping and billing addresses</a>,
  and <a href="/my-account/edit-account/">edit your password and account details</a>.
</p>

<!-- Hook: woocommerce_account_dashboard -->
```

## Etch HTML — Orders endpoint

```html
<table class="woocommerce-orders-table
              woocommerce-MyAccount-orders
              shop_table shop_table_responsive my_account_orders account-orders-table">
  <thead>
    <tr>
      <th class="woocommerce-orders-table__header
                 woocommerce-orders-table__header-order-number">
        <span class="nobr">Order</span>
      </th>
      <th class="woocommerce-orders-table__header
                 woocommerce-orders-table__header-order-date">
        <span class="nobr">Date</span>
      </th>
      <th class="woocommerce-orders-table__header
                 woocommerce-orders-table__header-order-status">
        <span class="nobr">Status</span>
      </th>
      <th class="woocommerce-orders-table__header
                 woocommerce-orders-table__header-order-total">
        <span class="nobr">Total</span>
      </th>
      <th class="woocommerce-orders-table__header
                 woocommerce-orders-table__header-order-actions">
        <span class="nobr">Actions</span>
      </th>
    </tr>
  </thead>

  <tbody>
    {#loop customerOrders as order}
    <tr class="woocommerce-orders-table__row
               woocommerce-orders-table__row--status-{order.status}
               order">
      <td class="woocommerce-orders-table__cell
                 woocommerce-orders-table__cell-order-number"
          data-title="Order">
        <a href="{order.viewUrl}">#{order.number}</a>
      </td>
      <td class="woocommerce-orders-table__cell-order-date"
          data-title="Date">
        <time datetime="{order.date}">{order.dateFormatted}</time>
      </td>
      <td class="woocommerce-orders-table__cell-order-status"
          data-title="Status">
        {order.statusLabel}
      </td>
      <td class="woocommerce-orders-table__cell-order-total"
          data-title="Total">
        {order.total} for {order.itemCount} item(s)
      </td>
      <td class="woocommerce-orders-table__cell-order-actions"
          data-title="Actions">
        <a href="{order.viewUrl}" class="woocommerce-button button view">
          View
        </a>
        {#loop order.actions as action}
          <a href="{action.url}" class="woocommerce-button button {action.name}">{action.name}</a>
        {/loop}
      </td>
    </tr>
    {/loop}
  </tbody>
</table>

<!-- Hook: woocommerce_before_account_orders_pagination -->

<div class="woocommerce-pagination
            woocommerce-pagination--without-numbers
            woocommerce-Pagination">
  <a class="woocommerce-button woocommerce-button--previous button"
     href="?orders-page={pagination.prev}">Previous</a>
  <a class="woocommerce-button woocommerce-button--next button"
     href="?orders-page={pagination.next}">Next</a>
</div>
```

## Etch HTML — Addresses endpoint

```html
<p>
  The following addresses will be used on the checkout page by default.
</p>

<div class="woocommerce-Addresses col2-set addresses">
  <div class="woocommerce-Address">
    <header class="woocommerce-Address-title title">
      <h3>Billing address</h3>
      <a href="/my-account/edit-address/billing/"
         class="edit">Edit</a>
    </header>
    <address>
      {customer.billing.formatted}
    </address>
  </div>

  <div class="woocommerce-Address">
    <header class="woocommerce-Address-title title">
      <h3>Shipping address</h3>
      <a href="/my-account/edit-address/shipping/"
         class="edit">Edit</a>
    </header>
    <address>
      {customer.shipping.formatted}
    </address>
  </div>
</div>
```

## Etch HTML — Login form (logged-out view)

When the user is not logged in, `[woocommerce_my_account]` outputs a login + registration form.

```html
<div class="woocommerce-account-fields">
  <h2>Login</h2>

  <!-- Hook: woocommerce_login_form_start -->

  <form class="woocommerce-form woocommerce-form-login login"
        method="post">
    <p class="form-row form-row-wide">
      <label for="username">Username or email <span class="required">*</span></label>
      <input type="text"
             class="woocommerce-Input woocommerce-Input--text input-text"
             name="username"
             id="username"
             autocomplete="username"
             required>
    </p>

    <p class="form-row form-row-wide">
      <label for="password">Password <span class="required">*</span></label>
      <input type="password"
             class="woocommerce-Input woocommerce-Input--text input-text"
             name="password"
             id="password"
             autocomplete="current-password"
             required>
    </p>

    <!-- Hook: woocommerce_login_form -->

    <p class="form-row">
      <label class="woocommerce-form__label
                    woocommerce-form__label-for-checkbox
                    woocommerce-form-login__rememberme">
        <input type="checkbox"
               class="woocommerce-form__input
                      woocommerce-form__input-checkbox"
               name="rememberme"
               value="forever">
        <span>Remember me</span>
      </label>

      <input type="hidden" name="woocommerce-login-nonce" value="{account.loginNonce}">
      <input type="hidden" name="_wp_http_referer" value="/my-account/">

      <button type="submit"
              class="woocommerce-button button woocommerce-form-login__submit"
              name="login"
              value="Log in">
        Log in
      </button>
    </p>

    <p class="woocommerce-LostPassword lost_password">
      <a href="/my-account/lost-password/">Lost your password?</a>
    </p>

    <!-- Hook: woocommerce_login_form_end -->
  </form>
</div>
```

## Required classes / attributes

| Element | Required | Why |
|---|---|---|
| `.woocommerce-MyAccount-navigation` | recommended | Default styling + plugin extensibility |
| `.woocommerce-MyAccount-content` | required | Container that endpoint content renders into |
| Navigation link classes (`--orders`, `--downloads`, etc.) | recommended | Default-active-state styling |
| `.woocommerce-orders-table` on orders table | recommended | Default-responsive markup |
| `data-title="..."` on `<td>`s | recommended | Mobile labels (responsive) |
| Login form: `name="username"`, `name="password"` | required | WordPress core authentication |
| Hidden `woocommerce-login-nonce` | required | CSRF protection |
| `name="login"` on submit | required | Triggers WC login handler |

## Hooks used

### Navigation

| Hook | Position | Use |
|---|---|---|
| `woocommerce_before_account_navigation` | Before nav | Avatar, welcome message |
| `woocommerce_after_account_navigation` | After nav | Help links, support badge |
| `woocommerce_account_menu_items` (Filter) | — | Add/remove/reorder menu items |

### Dashboard

| Hook | Position | Use |
|---|---|---|
| `woocommerce_account_content` | Inside content wrapper | **Default**: routes to endpoint callback |
| `woocommerce_account_dashboard` | Dashboard endpoint | Custom dashboard widgets |

### Orders

| Hook | Position | Use |
|---|---|---|
| `woocommerce_my_account_my_orders_actions` (Filter) | — | Add/remove order action buttons |
| `woocommerce_before_account_orders` | Before orders table | Filters, banners |
| `woocommerce_before_account_orders_pagination` | Before pagination | Notice |

### Login / Register

| Hook | Position | Use |
|---|---|---|
| `woocommerce_login_form_start` | Inside form, top | Social-login buttons |
| `woocommerce_login_form` | Inside form, mid | Custom fields |
| `woocommerce_login_form_end` | Inside form, bottom | reCAPTCHA |
| `woocommerce_register_form_start` | Register form, top | (analogous) |
| `woocommerce_register_form` | Register form, mid | Privacy policy checkbox |
| `woocommerce_register_form_end` | Register form, bottom | (analogous) |

## PHP layer

### Add/remove menu items

```php
add_filter('woocommerce_account_menu_items', function ($items) {
    // Add a custom endpoint
    $items['favorites'] = 'Favorites';

    // Remove default
    unset($items['downloads']);

    // Reorder
    $order = ['dashboard', 'orders', 'favorites', 'edit-address', 'edit-account', 'customer-logout'];
    $ordered = [];
    foreach ($order as $key) {
        if (isset($items[$key])) {
            $ordered[$key] = $items[$key];
        }
    }
    return $ordered;
});
```

### Register a custom endpoint

```php
// 1. Register the endpoint
add_action('init', function () {
    add_rewrite_endpoint('favorites', EP_PAGES);
});

// 2. Tell WC about the menu item slug
add_filter('woocommerce_get_query_vars', function ($vars) {
    $vars['favorites'] = 'favorites';
    return $vars;
});

// 3. Render content when the endpoint is hit
add_action('woocommerce_account_favorites_endpoint', function () {
    echo '<h2>Your favorites</h2>';
    echo '<p>You have not saved any favorites yet.</p>';
    // Loop over user's favorites here
});
```

**After adding an endpoint, flush permalinks once** (`Settings → Permalinks → Save`), otherwise the URL 404s.

### Customize the order actions

```php
add_filter('woocommerce_my_account_my_orders_actions', function ($actions, $order) {
    // Add a "reorder" button on completed orders
    if ($order->has_status('completed')) {
        $actions['reorder'] = [
            'url'  => wp_nonce_url(add_query_arg('order_again', $order->get_id()), 'woocommerce-order_again'),
            'name' => 'Reorder',
        ];
    }
    return $actions;
}, 10, 2);
```

### Pre-fill account details

```php
add_action('woocommerce_save_account_details', function ($user_id) {
    // Sync a custom field to the WP user meta
    if (!empty($_POST['account_phone'])) {
        update_user_meta($user_id, 'billing_phone', sanitize_text_field($_POST['account_phone']));
    }
});
```

### Redirect after login

```php
add_filter('woocommerce_login_redirect', function ($redirect, $user) {
    return user_can($user, 'manage_options')
        ? admin_url()
        : wc_get_account_endpoint_url('orders');
}, 10, 2);
```

### Auto-login after registration

```php
add_filter('woocommerce_registration_auth_new_customer', '__return_true');
```

## Common mistakes

- Menu links built as plain `<a>` without matching the WC slug → "active" state never highlights.
- Endpoint added but permalinks not flushed → endpoint URLs 404.
- Login form `name` attributes renamed → WP core auth doesn't recognize the form.
- Login nonce missing → "Sorry, your session has expired" on submit.
- Endpoint callback registered but no nav item → users can reach the URL but won't see it.
- Custom field in the edit-account form, but no `woocommerce_save_account_details` handler → the value is lost on save.

## Test checklist

- Logged out: visiting `/my-account/` shows the login form.
- Login submits successfully → redirects to dashboard (or your custom redirect).
- Logged in: navigation links route to `/my-account/orders/`, `/my-account/edit-address/`, etc.
- Mobile: orders table shows `data-title` labels instead of column headers.
- Permalinks: `/my-account/favorites/` (custom endpoint) renders without 404.
- Logout link logs out and redirects to the configured URL.
