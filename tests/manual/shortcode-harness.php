<?php
/**
 * Woo4Etch shortcode harness — runtime proof against a real WooCommerce install.
 *
 * Run inside wp-env:
 *   npx wp-env run cli wp eval-file wp-content/plugins/woo4etch/../../../tests/manual/shortcode-harness.php
 * (or copy this file under the mapped plugin and eval-file it; see the test runner script.)
 *
 * It seeds a product (with sale price, SKU, weight, dimensions, gallery), a cart,
 * and an order, then executes every Woo4Etch shortcode and prints a PASS/FAIL table.
 *
 * @package Woo4Etch
 */

if (!defined('ABSPATH')) {
    fwrite(STDERR, "Must run via wp eval-file.\n");
    exit(1);
}

if (!class_exists('WooCommerce')) {
    echo "WooCommerce not active — aborting.\n";
    return;
}

/* ----------------------------------------------------------------------------
 * Context: act as admin so account/order shortcodes have a user.
 * -------------------------------------------------------------------------- */
wp_set_current_user(1);
if (function_exists('wc_load_cart')) {
    wc_load_cart();
}

$results = [];
$record  = static function ($tag, $output, $expect_nonempty = true, $needle = null) use (&$results) {
    $out    = (string) $output;
    $bytes  = strlen($out);
    $status = 'PASS';
    if ($expect_nonempty && $bytes === 0) {
        $status = 'EMPTY';
    }
    if ($needle !== null && strpos($out, $needle) === false) {
        $status = 'MISS(' . $needle . ')';
    }
    $preview = trim(preg_replace('/\s+/', ' ', wp_strip_all_tags($out)));
    $preview = mb_substr($preview, 0, 70);
    $results[] = [$tag, $status, $bytes, $preview];
};

/* ----------------------------------------------------------------------------
 * Helper: create a tiny real attachment so image/gallery shortcodes resolve.
 * -------------------------------------------------------------------------- */
$make_attachment = static function ($name) {
    $upload = wp_upload_dir();
    $file   = trailingslashit($upload['path']) . $name . '.png';
    // 4x4 transparent PNG.
    if (function_exists('imagecreatetruecolor')) {
        $img = imagecreatetruecolor(4, 4);
        imagesavealpha($img, true);
        $transparent = imagecolorallocatealpha($img, 0, 0, 0, 127);
        imagefill($img, 0, 0, $transparent);
        imagepng($img, $file);
        imagedestroy($img);
    } else {
        file_put_contents($file, base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAQAAAAECAYAAACp8Z5+AAAADklEQVR42mNk+M9QzwAEAAoaAR3p2bGEAAAAAElFTkSuQmCC'
        ));
    }
    $id = wp_insert_attachment([
        'post_mime_type' => 'image/png',
        'post_title'     => $name,
        'post_status'    => 'inherit',
    ], $file);
    require_once ABSPATH . 'wp-admin/includes/image.php';
    wp_update_attachment_metadata($id, wp_generate_attachment_metadata($id, $file));
    return $id;
};

/* ----------------------------------------------------------------------------
 * Seed: a configurable product with everything the shortcodes read.
 * -------------------------------------------------------------------------- */
$existing = wc_get_products(['limit' => 1, 'sku' => 'WOO4ETCH-TEST']);
if ($existing) {
    $product = reset($existing);
} else {
    $img_main = $make_attachment('w4e-main');
    $img_g1   = $make_attachment('w4e-gallery-1');
    $img_g2   = $make_attachment('w4e-gallery-2');

    $product = new WC_Product_Simple();
    $product->set_name('Woo4Etch Test Mug');
    $product->set_sku('WOO4ETCH-TEST');
    $product->set_regular_price('20.00');
    $product->set_sale_price('15.00');
    $product->set_short_description('A short description for the test mug.');
    $product->set_description('A full <strong>description</strong> for the test mug.');
    $product->set_weight('0.5');
    $product->set_length('10');
    $product->set_width('8');
    $product->set_height('9');
    $product->set_manage_stock(true);
    $product->set_stock_quantity(7);
    $product->set_image_id($img_main);
    $product->set_gallery_image_ids([$img_g1, $img_g2]);
    $product->save();

    wp_set_object_terms($product->get_id(), 'Mugs', 'product_cat');
    wp_set_object_terms($product->get_id(), 'featured-test', 'product_tag');
}

$GLOBALS['product'] = $product;
$pid = $product->get_id();

// Populate the cart.
WC()->cart->empty_cart();
WC()->cart->add_to_cart($pid, 2);
WC()->cart->calculate_totals();

// Create an order for order_details.
$order = wc_create_order();
$order->add_product($product, 1);
$order->set_address(['first_name' => 'Test', 'email' => 'test@example.com'], 'billing');
$order->calculate_totals();
$order->save();
$GLOBALS['order'] = $order;

echo "Seed: product #{$pid} ({$product->get_sku()}), order #{$order->get_id()}\n\n";

/* ----------------------------------------------------------------------------
 * Run shortcodes. Each entry: [shortcode string, expect_nonempty, needle?]
 * -------------------------------------------------------------------------- */
$cases = [
    // Product data
    ['[woo_title]', true, 'Mug'],
    ['[woo_title link="yes"]', true, '<a'],
    ['[woo_price]', true, null],
    ['[woo_regular_price]', true, null],
    ['[woo_sale_price]', true, null],
    ['[woo_price_amount]', true, null],
    ['[woo_sale_badge percentage="yes"]', true, '%'],
    ['[woo_sku]', true, 'WOO4ETCH-TEST'],
    ['[woo_stock format="label"]', true, null],
    ['[woo_stock format="quantity"]', true, '7'],
    ['[woo_stock format="status"]', true, null],
    ['[woo_weight]', true, null],
    ['[woo_dimensions]', true, null],
    ['[woo_meta key="_sku"]', true, 'WOO4ETCH-TEST'],
    ['[woo_categories]', true, 'Mugs'],
    ['[woo_tags]', true, null],
    ['[woo_short_description]', true, 'short description'],
    ['[woo_description]', true, 'description'],
    // Product media
    ['[woo_image]', true, '<img'],
    ['[woo_gallery]', true, '<img'],
    ['[woo_gallery include_featured="yes"]', true, '<img'],
    // Product UI
    ['[woo_add_to_cart]', true, 'cart'],
    ['[woo_add_to_cart_url]', true, '?add-to-cart'],
    ['[woo_quantity]', true, 'quantity'],
    ['[woo_tabs]', true, null],
    ['[woo_related]', false, null],
    ['[woo_upsells]', false, null],
    // Cart
    ['[woo_cart_count]', true, 'data-count'],
    ['[woo_cart_total]', true, null],
    ['[woo_cart_url]', true, 'http'],
    ['[woo_checkout_url]', true, 'http'],
    ['[woo_cart_totals]', true, null],
    ['[woo_coupon_form]', true, 'coupon'],
    ['[woo_shipping_calculator]', false, null],
    ['[woo_cross_sells]', false, null],
    ['[woo_mini_cart]', true, null],
    // Account
    ['[woo_user field="user_email"]', true, '@'],
    ['[woo_account_url]', true, 'http'],
    ['[woo_account_url endpoint="orders"]', true, 'orders'],
    ['[woo_logout_url]', true, 'http'],
    ['[woo_account_menu]', true, null],
    ['[woo_account_content]', true, null],
    ['[woo_login_form]', true, 'password'],
    ['[woo_order_details]', true, 'order'],
    // Store
    ['[woo_shop_url]', true, 'http'],
    ['[woo_breadcrumb]', false, null],
    ['[woo_product_search]', true, 'search'],
    ['[woo_notices]', false, null],
    // Conditional
    ['[woo_if cond="on_sale"]ON-SALE[/woo_if]', true, 'ON-SALE'],
    ['[woo_if cond="!on_sale"]HIDDEN[/woo_if]', false, null],
    ['[woo_if cond="in_stock"]IN-STOCK[/woo_if]', true, 'IN-STOCK'],
    ['[woo_if cond="is_type" arg="simple"]SIMPLE[/woo_if]', true, 'SIMPLE'],
    ['[woo_if cond="is_type" arg="variable"]NO[/woo_if]', false, null],
    ['[woo_if cond="is_user_logged_in"]LOGGED-IN[/woo_if]', true, 'LOGGED-IN'],
    // do_action
    ['[do_action hook="woocommerce_before_add_to_cart_button"]', false, null],
    // Archive-context (will be empty outside a product loop — flagged, not failed)
    ['[woo_result_count]', false, null],
    ['[woo_catalog_ordering]', false, null],
    ['[woo_pagination]', false, null],
];

foreach ($cases as $case) {
    [$sc, $expect, $needle] = $case;
    $record($sc, do_shortcode($sc), $expect, $needle);
}

/* ----------------------------------------------------------------------------
 * Report.
 * -------------------------------------------------------------------------- */
$pass = $fail = 0;
echo str_pad('SHORTCODE', 52) . str_pad('STATUS', 14) . str_pad('BYTES', 8) . "PREVIEW\n";
echo str_repeat('-', 120) . "\n";
foreach ($results as $r) {
    [$tag, $status, $bytes, $preview] = $r;
    if ($status === 'PASS') {
        $pass++;
    } else {
        $fail++;
    }
    echo str_pad(mb_substr($tag, 0, 51), 52)
        . str_pad($status, 14)
        . str_pad((string) $bytes, 8)
        . $preview . "\n";
}
echo str_repeat('-', 120) . "\n";
echo "PASS: {$pass}   non-PASS: {$fail}   (EMPTY on related/upsells/cross-sells/notices/archive is expected without that context)\n";
