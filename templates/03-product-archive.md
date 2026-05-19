# 03 — Product Archive / Shop

Loop with product cards for `/shop`, categories, and tag pages. AJAX add-to-cart from the loop, semantic list structure, filter/sort bar as a hook point.

## When to use

- Main shop page (`/shop`).
- Product categories (`/product-category/<slug>`).
- Product tags (`/product-tag/<slug>`).
- Custom loops that render WooCommerce products (e.g. "Popular products" on the home page).

## Preparation

> **Etch context:** this is an **Archive template** in the Etch Template Hub. `{this.*}` refers to the *archive term* (e.g. the category being viewed); products are iterated with `{#loop mainQuery as item}`. See [`10-etch-context-and-templates.md`](./10-etch-context-and-templates.md).

WooCommerce must be declared in the theme (see [`00-README.md`](./00-README.md)).

For built-in AJAX add-to-cart to work, make sure the setting is enabled:

`WooCommerce → Settings → Products → General → Enable AJAX add to cart buttons on archives` ✓

That loads the `wc-add-to-cart` script and fragments for mini-cart updates.

## Etch HTML — Archive wrapper

```html
<main id="main" class="site-main woocommerce woocommerce-page">

  <!-- Hook: woocommerce_before_main_content (wrapper) -->
  <!-- Hook: woocommerce_archive_description -->

  <header class="archive-header">
    <h1 class="woocommerce-products-header__title page-title">
      {this.title}
    </h1>
    <div class="term-description">{this.description}</div>
  </header>

  <!-- Hook: woocommerce_before_shop_loop -->

  <section aria-labelledby="products-title">
    <h2 id="products-title" class="screen-reader-text">Product list</h2>

    <ul class="products columns-3" role="list">
      {#loop mainQuery as item}
        <!-- Card markup — see "Product card" section below -->
      {/loop}
    </ul>
  </section>

  <!-- Hook: woocommerce_after_shop_loop -->

</main>
```

## Etch HTML — Product card (loop item)

```html
<li class="product type-product product-type-simple status-publish instock"
    data-product-id="{item.id}">
  <article class="product-card">

    <a href="{item.permalink.relative}"
       class="woocommerce-LoopProduct-link woocommerce-loop-product__link">

      <figure class="product-card__image">
        <!-- Hook: woocommerce_before_shop_loop_item_title (default: product image) -->
        <img src="{item.image.url}"
             alt="{item.title}"
             width="600"
             height="600"
             loading="lazy">

        <!-- Optional: sale badge -->
        <span class="onsale" aria-hidden="true" hidden>Sale</span>
      </figure>

      <div class="product-card__body">
        <p class="product-card__eyebrow">{item.product_cat.0.name}</p>

        <!-- Hook: woocommerce_shop_loop_item_title -->
        <h2 class="woocommerce-loop-product__title">{item.title}</h2>

        <!-- Hook: woocommerce_after_shop_loop_item_title -->
        <p class="price">{item.meta._price}</p>

        <p class="product-card__excerpt">{item.excerpt}</p>
      </div>
    </a>

    <!-- Hook: woocommerce_after_shop_loop_item -->
    <div class="product-card__actions">
      <a href="?add-to-cart={item.id}"
         data-quantity="1"
         data-product_id="{item.id}"
         data-product_sku="{item.meta._sku}"
         class="button product_type_simple add_to_cart_button ajax_add_to_cart"
         aria-label="Add {item.title} to cart"
         rel="nofollow">
        Add to cart
      </a>
    </div>
  </article>
</li>
```

## Required classes / attributes

| Element | Required | Why |
|---|---|---|
| `<ul class="products">` with `<li class="product">` | recommended | Consistency with classic Woo archives |
| `product-type-simple` class | recommended | Plugins filter on it |
| `instock` / `outofstock` class | recommended | Theme and plugin styling |
| AJAX button: `button` + `add_to_cart_button` + `ajax_add_to_cart` | yes | Triggers AJAX logic |
| `data-product_id` on the button | yes | Identifies the product |
| `data-quantity` on the button | yes | Otherwise quantity is hardcoded to 1 |
| `data-product_sku` on the button | recommended | Passed through in events |
| `rel="nofollow"` on the button | recommended | Prevents crawlers from filling the cart |
| `?add-to-cart={item.id}` as `href` | yes | No-JS fallback still works |
| `aria-label` with product name | recommended | Accessibility — otherwise screen reader just hears "Add to cart" |

> **Important:** For variable products in the loop, render a **link to the product page** with class `product_type_variable` instead of the AJAX button — no AJAX because the user has to choose variants.

```html
<!-- Variant for variable products in the loop -->
<a href="{item.permalink.relative}"
   class="button product_type_variable"
   aria-label="Choose options for {item.title}">
  Select options
</a>
```

## Hooks used

### Archive wrapper

| Hook | Position | Typical use |
|---|---|---|
| `woocommerce_before_main_content` | Before the loop wrapper | Sidebar open, filter slot |
| `woocommerce_archive_description` | After `<h1>` | Category description |
| `woocommerce_before_shop_loop` | Before `<ul.products>` | Sort dropdown, result count, filter bar |
| `woocommerce_after_shop_loop` | After `</ul>` | Pagination |
| `woocommerce_after_main_content` | After everything | Sidebar close |

### Loop item

| Hook | Position | Typical use / default callback |
|---|---|---|
| `woocommerce_before_shop_loop_item` | Before `<a>` | Rarely used |
| `woocommerce_before_shop_loop_item_title` | Inside `<a>`, before title | **Default: product image** (`woocommerce_template_loop_product_thumbnail`) — don't remove |
| `woocommerce_shop_loop_item_title` | Title slot | **Default: product title** (`woocommerce_template_loop_product_title`) |
| `woocommerce_after_shop_loop_item_title` | After title | **Default: price** (`woocommerce_template_loop_price`) and **rating** (`woocommerce_template_loop_rating`) |
| `woocommerce_after_shop_loop_item` | Outside `<a>` | **Default: add-to-cart button** (`woocommerce_template_loop_add_to_cart`) |

## PHP layer

### Customize the sort dropdown

```php
// Only show specific options
add_filter('woocommerce_catalog_orderby', function ($options) {
    return [
        'menu_order' => 'Default',
        'price'      => 'Price: low to high',
        'price-desc' => 'Price: high to low',
        'date'       => 'Newest',
    ];
});
```

### Products per page

```php
add_filter('loop_shop_per_page', function () {
    return 24;
});
```

### Columns

```php
add_filter('loop_shop_columns', function () {
    return 3;
});
```

### Remove default loop callbacks (use your own markup)

If Etch controls the entire card markup, disable Woo's default callbacks so they don't render *additionally*. Example — turn off default image and price in the loop:

```php
add_action('init', function () {
    remove_action('woocommerce_before_shop_loop_item_title', 'woocommerce_template_loop_product_thumbnail', 10);
    remove_action('woocommerce_after_shop_loop_item_title', 'woocommerce_template_loop_price', 10);
    remove_action('woocommerce_after_shop_loop_item_title', 'woocommerce_template_loop_rating', 5);
});
```

### Custom sale badge in the loop

```php
add_action('woocommerce_after_shop_loop_item_title', function () {
    global $product;
    if ($product->is_on_sale()) {
        echo '<span class="badge badge--sale">Sale</span>';
    }
}, 15);
```

### Add-to-cart button text based on stock

```php
add_filter('woocommerce_product_add_to_cart_text', function ($text, $product) {
    if (!$product->is_in_stock()) return 'Unavailable';
    if ($product->is_type('variable')) return 'Select options';
    return 'Add to cart';
}, 10, 2);
```

### AJAX fragments for a custom mini-cart counter

If you show a counter in the header (see `05-mini-cart.md`), filter the AJAX fragments:

```php
add_filter('woocommerce_add_to_cart_fragments', function ($fragments) {
    ob_start();
    ?>
    <span class="mini-cart-count" data-count="<?php echo WC()->cart->get_cart_contents_count(); ?>">
      <?php echo esc_html(WC()->cart->get_cart_contents_count()); ?>
    </span>
    <?php
    $fragments['span.mini-cart-count'] = ob_get_clean();
    return $fragments;
});
```

## Common mistakes

- AJAX button without `ajax_add_to_cart` class → no AJAX, classic reload.
- `data-product_id` missing → server gets no product ID.
- Variable products rendered as AJAX buttons → user ends up in the cart with the parent product (or an error).
- Default callbacks not removed → duplicate images/prices in the markup.
- Filter bar as static HTML in Etch → user sort choices aren't persisted (sort logic belongs in the hook).
- Loop uses its own `WP_Query` instead of Woo's `wc_get_template_part` mechanism → standard hooks don't fire, AJAX add-to-cart breaks.

## Test checklist

- Click an AJAX button → mini-cart counter increases without reload.
- Network tab → `?wc-ajax=add_to_cart` request, status 200, response contains `fragments`.
- Variable products show "Select options" instead of "Add to cart".
- Change the sort dropdown → `?orderby=…` is set in the URL and order matches.
- Keyboard: tab through the loop, every card link and button is reachable.
- Screen reader: each card cleanly announces product name, price, and button action.
