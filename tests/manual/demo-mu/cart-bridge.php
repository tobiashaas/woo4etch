<?php
/**
 * PROTOTYPE — Cart as Etch dynamic data.
 *
 * Etch lets plugins extend the `option` dynamic-data root (filter
 * etch/dynamic_data/option), and Etch loops can target a dynamic array. So we
 * expose the WooCommerce cart as {options.cart_items} (+ cart_total/cart_count),
 * which means the cart can be built as REAL Etch HTML with a loop — full builder
 * control + preview — instead of an opaque [woocommerce_cart] shortcode.
 *
 * When no cart is loaded (Etch builder / editor preview), mock rows are returned
 * so the loop renders in the builder.
 *
 * @package Woo4Etch
 */

if (!defined('ABSPATH')) {
    exit;
}

add_filter('etch/dynamic_data/option', function ($data) {
    if (!is_array($data)) {
        $data = [];
    }

    $cart  = function_exists('WC') && WC() ? WC()->cart : null;
    $items = [];

    if ($cart && !$cart->is_empty()) {
        foreach ($cart->get_cart() as $key => $ci) {
            $p = $ci['data'] ?? null;
            if (!$p instanceof WC_Product) {
                continue;
            }
            $img_id = $p->get_image_id();
            $img    = $img_id ? wp_get_attachment_image_url($img_id, 'woocommerce_thumbnail') : '';
            if (!$img && function_exists('wc_placeholder_img_src')) {
                $img = wc_placeholder_img_src('woocommerce_thumbnail');
            }
            $items[] = [
                'key'        => $key,
                'name'       => $p->get_name(),
                'price'      => html_entity_decode(wp_strip_all_tags($cart->get_product_price($p)), ENT_QUOTES),
                'qty'        => $ci['quantity'],
                'image'      => $img,
                'subtotal'   => html_entity_decode(wp_strip_all_tags($cart->get_product_subtotal($p, $ci['quantity'])), ENT_QUOTES),
                'permalink'  => get_permalink($p->get_id()),
                'remove_url' => wc_get_cart_remove_url($key),
            ];
        }
        $data['cart_total'] = html_entity_decode(wp_strip_all_tags($cart->get_total()), ENT_QUOTES);
        $data['cart_count'] = $cart->get_cart_contents_count();
    } else {
        // Builder / empty context → mock rows so the loop previews in Etch.
        $ph = function_exists('wc_placeholder_img_src') ? wc_placeholder_img_src('woocommerce_thumbnail') : '';
        $items = [
            ['key' => 'demo1', 'name' => 'Sample Product', 'price' => '€20.00', 'qty' => 1, 'image' => $ph, 'subtotal' => '€20.00', 'permalink' => '#', 'remove_url' => '#'],
            ['key' => 'demo2', 'name' => 'Another Product', 'price' => '€15.00', 'qty' => 2, 'image' => $ph, 'subtotal' => '€30.00', 'permalink' => '#', 'remove_url' => '#'],
        ];
        $data['cart_total'] = '€50.00';
        $data['cart_count'] = 3;
    }

    $data['cart_items'] = $items;
    return $data;
});
