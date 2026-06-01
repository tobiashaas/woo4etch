<?php
/**
 * Woo4Etch demo seeder — real Etch templates (FSE wp_template posts) combining
 * Etch Dynamic Keys ({this.*} / {item.*}) with Woo4Etch shortcodes, structured
 * with Etch's section/container convention:
 *   - full-width bands  → data-etch-element="section"
 *   - constrained inner → data-etch-element="container"
 * so every band shows up as a proper Section/Container in the Etch builder.
 *
 * Run: npx wp-env run cli wp eval-file wp-content/woo4etch-tests/manual/seed-demo.php
 *
 * @package Woo4Etch
 */

if (!defined('ABSPATH')) {
    exit(1);
}

$theme = get_stylesheet();
$sym   = function_exists('get_woocommerce_currency_symbol')
    ? html_entity_decode(get_woocommerce_currency_symbol(), ENT_QUOTES)
    : '$';

/* ---- Etch markup helpers ---------------------------------------------------- */
$E = '<!-- /wp:etch/element -->';
$section = function ($class) {
    return '<!-- wp:etch/element {"tag":"section","attributes":{"data-etch-element":"section","class":"' . $class . '"}} -->';
};
$container = function ($class = '') {
    $cls = $class !== '' ? ',"class":"' . $class . '"' : '';
    return '<!-- wp:etch/element {"tag":"div","attributes":{"data-etch-element":"container"' . $cls . '}} -->';
};
$el = function ($tag, $class, $inner, $extra = '') {
    $attrs = '"class":"' . $class . '"' . ($extra !== '' ? ',' . $extra : '');
    return '<!-- wp:etch/element {"tag":"' . $tag . '","attributes":{' . $attrs . '}} -->' . $inner . '<!-- /wp:etch/element -->';
};
$txt = function ($content) {
    return '<!-- wp:etch/text {"content":"' . $content . '"} /-->';
};

/* ============================================================
   1. Product loop preset (etch_loops) — wp-query over products
   ============================================================ */
$loops = (array) get_option('etch_loops', []);
$loops['products'] = [
    'name'   => 'Products',
    'key'    => 'products',
    'global' => true,
    'config' => [
        'type' => 'wp-query',
        'args' => [
            'post_type'      => 'product',
            'post_status'    => 'publish',
            'posts_per_page' => 12,
            'orderby'        => 'date',
            'order'          => 'DESC',
        ],
    ],
];
update_option('etch_loops', $loops);
echo "OK   loop preset 'products' (wp-query, post_type=product)\n";

/* ============================================================
   2. Helpers to create templates / parts
   ============================================================ */
$put_template = static function (string $slug, string $title, string $content) use ($theme) {
    foreach (get_posts([
        'post_type' => 'wp_template', 'name' => $slug, 'post_status' => 'any', 'numberposts' => -1,
        'tax_query' => [['taxonomy' => 'wp_theme', 'field' => 'name', 'terms' => $theme]], 'fields' => 'ids',
    ]) as $eid) {
        wp_delete_post($eid, true);
    }
    $id = wp_insert_post([
        'post_type' => 'wp_template', 'post_status' => 'publish',
        'post_name' => $slug, 'post_title' => $title, 'post_content' => $content,
    ], true);
    if (is_wp_error($id)) { echo "FAIL {$slug}: " . $id->get_error_message() . "\n"; return; }
    wp_set_object_terms($id, $theme, 'wp_theme');
    echo "OK   wp_template '{$slug}'\n";
};

$put_part = static function (string $slug, string $area, string $content) use ($theme) {
    foreach (get_posts([
        'post_type' => 'wp_template_part', 'name' => $slug, 'post_status' => 'any', 'numberposts' => -1,
        'tax_query' => [['taxonomy' => 'wp_theme', 'field' => 'name', 'terms' => $theme]], 'fields' => 'ids',
    ]) as $eid) {
        wp_delete_post($eid, true);
    }
    $id = wp_insert_post([
        'post_type' => 'wp_template_part', 'post_status' => 'publish',
        'post_name' => $slug, 'post_title' => ucfirst($slug), 'post_content' => $content,
    ], true);
    if (is_wp_error($id)) { echo "FAIL part {$slug}: " . $id->get_error_message() . "\n"; return; }
    wp_set_object_terms($id, $theme, 'wp_theme');
    wp_set_object_terms($id, $area, 'wp_template_part_area');
    echo "OK   template_part '{$slug}'\n";
};

// template-part refs (no tagName: the part's own <section> is the band wrapper)
$header = '<!-- wp:template-part {"slug":"header","theme":"' . $theme . '"} /-->';
$footer = '<!-- wp:template-part {"slug":"footer","theme":"' . $theme . '"} /-->';

/* ============================================================
   3. Header / footer parts (section > container)
   ============================================================ */
$nav = $el('a', '', $txt('Shop'), '"href":"/shop/"')
     . $el('a', '', $txt('Account'), '"href":"/my-account/"')
     . $el('a', 'w4e-cartlink', $txt('Cart [woo_cart_count]'), '"href":"/cart/"');

// Header/footer are the bands themselves — plain wrappers, NOT section/container.
$put_part('header', 'header',
    $el('div', 'w4e-header',
        $el('div', 'w4e-header__inner w4e-wrap',
            $el('a', 'w4e-logo', $txt('Woo4Etch'), '"href":"/"')
            . $el('nav', 'w4e-nav', $nav)
        )
    )
);

$put_part('footer', 'footer',
    $el('div', 'w4e-footer',
        $el('div', 'w4e-footer__inner w4e-wrap',
            $txt('Woo4Etch demo — WooCommerce in Etch, built with Etch Dynamic Keys + Woo4Etch shortcodes.')
        )
    )
);

/* ============================================================
   4. Generic page template (Cart / Checkout / Account chrome)
   ============================================================ */
$put_template('page', 'Page (Woo4Etch demo)',
    $header
    . $section('w4e-page')
    . $container()
      . $el('h1', 'w4e-page__title', $txt('{this.title}'))
      . '<!-- wp:post-content /-->'
    . $E
    . $E
    . $footer
);

// Cart uses a minimal template (no wrapping section/h1) so the cart PAGE content
// can provide its own section > container — fully editable as such in Etch.
$put_template('page-cart', 'Cart (Woo4Etch demo)',
    $header
    . '<!-- wp:post-content /-->'
    . $footer
);

/* ============================================================
   5. Single product (fully editable: dynamic keys, condition, modifier,
      gallery loop, hand-built add-to-cart form — no shortcodes)
   ============================================================ */
$breadcrumb = $el('a', '', $txt('Shop'), '"href":"/shop/"')
    . $txt(' / ')
    . $el('a', '', $txt('{this.product_cat.0.name}'), '"href":"/product-category/{this.product_cat.0.slug}/"')
    . $txt(' / ')
    . $txt('{this.title}');

$media = $el('figure', 'w4e-product__featured', $el('img', '', '', '"src":"{this.image.url}","alt":"{this.title}","loading":"eager"'))
    . $el('div', 'w4e-product__thumbs',
        '<!-- wp:etch/loop {"target":"this.gallery_images","itemId":"image"} -->'
        . $el('figure', 'w4e-thumb', $el('img', '', '', '"src":"{image.url}","alt":"{image.alt}","loading":"lazy"'))
        . '<!-- /wp:etch/loop -->'
      );

$price = '<!-- wp:etch/condition {"condition":{"leftHand":"this.meta._sale_price","operator":"isTruthy","rightHand":null}} -->'
    . $el('del', 'w4e-price__regular', $txt($sym . '{this.meta._regular_price.numberFormat(2, \'.\', \',\')}'))
    . '<!-- /wp:etch/condition -->'
    . $el('span', 'w4e-price__current', $txt($sym . '{this.meta._price.numberFormat(2, \'.\', \',\')}'));

$form = '<!-- wp:etch/element {"tag":"form","attributes":{"class":"cart w4e-cart","action":"{this.permalink.relative}","method":"post"}} -->'
    . $el('div', 'quantity', $el('input', 'input-text qty text', '', '"type":"number","name":"quantity","value":"1","min":"1","step":"1"'))
    . '<!-- wp:etch/element {"tag":"button","attributes":{"class":"single_add_to_cart_button button","type":"submit","name":"add-to-cart","value":"{this.id}"}} -->' . $txt('Add to cart') . $E
    . $E;

$summary = $el('h1', 'w4e-product__title', $txt('{this.title}'))
    . $el('div', 'w4e-product__price', $price)
    . $el('div', 'w4e-product__excerpt', $txt('{this.excerpt}'))
    . $form
    . $el('p', 'w4e-product__meta', $txt('SKU: {this.meta._sku} · Category: {this.product_cat.0.name}'));

$put_template('single-product', 'Single Product (Woo4Etch demo)',
    $header
    . $section('w4e-product')
    . $container()
      . $el('nav', 'w4e-breadcrumb', $breadcrumb)
      . $el('div', 'w4e-product__grid', $el('div', 'w4e-product__media', $media) . $el('div', 'w4e-product__summary', $summary))
    . $E
    . $E
    . $section('w4e-product__details')
    . $container()
      . $el('h2', '', $txt('Description'))
      . $txt('{this.content}')
    . $E
    . $E
    . $footer
);

/* ============================================================
   6. Shop archive (Etch product loop, {item.*})
   ============================================================ */
$card = $el('a', 'w4e-card__link',
        $el('img', 'w4e-card__img', '', '"src":"{item.image.url}","alt":"{item.title}","loading":"lazy"')
        . $el('h2', 'w4e-card__title', $txt('{item.title}')),
        '"href":"{item.permalink.relative}"'
    )
    . $el('div', 'w4e-card__price', $txt($sym . '{item.meta._price.numberFormat(2, \'.\', \',\')}'))
    . $el('a', 'w4e-card__btn', $txt('Add to cart'), '"href":"?add-to-cart={item.id}","data-product_id":"{item.id}"');

$put_template('archive-product', 'Shop Archive (Woo4Etch demo)',
    $header
    . $section('w4e-shop')
    . $container()
      . $el('header', 'w4e-shop__head', $el('h1', 'w4e-shop__title', $txt('Shop')) . $txt('[woo_result_count]'))
      . $el('div', 'w4e-grid',
          '<!-- wp:etch/loop {"loopId":"products","itemId":"item"} -->'
          . $el('article', 'w4e-card', $card)
          . '<!-- /wp:etch/loop -->'
        )
    . $E
    . $E
    . $footer
);

/* ============================================================
   7. Custom Cart page content (lives inside the page template's container)
   ============================================================ */
$cart_page_id = function_exists('wc_get_page_id') ? wc_get_page_id('cart') : 0;
if ($cart_page_id > 0) {
    // Complete cart: Etch layout (section > container) around the Woo4Etch cart
    // shortcodes. [woo_cart_items] is a faithful, hook-complete cart form (items,
    // coupon, update, remove, all cart hooks/filters → extensions work);
    // [woo_cart_totals] renders totals + checkout (+ its hooks); [woo_cross_sells]
    // the cross-sells. ([woo_cart_items] fires woocommerce_before_cart, which
    // prints notices.)
    $cart_content =
        $section('w4e-cart')
        . $container()
          . $el('h1', 'w4e-page__title', $txt('{this.title}'))
          . $el('div', 'w4e-cart-layout',
              $el('div', 'w4e-cart-main', $txt('[woo_cart_items]'))
              . $el('aside', 'w4e-cart-aside',
                  $el('h2', 'w4e-cart-aside__title', $txt('Order summary'))
                  . $txt('[woo_cart_totals]')
              )
          )
          . $el('div', 'w4e-cart-crosssells', $txt('[woo_cross_sells]'))
        . $E
        . $E;
    wp_update_post(['ID' => $cart_page_id, 'post_content' => $cart_content]);
    echo "OK   cart page #{$cart_page_id} → complete cart ([woo_cart_items] + totals + cross-sells)\n";
}

echo "\nDemo templates installed for theme '{$theme}' (section/container convention).\n";
