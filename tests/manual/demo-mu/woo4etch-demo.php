<?php
/**
 * Plugin Name: Woo4Etch Demo Styles
 * Description: Demo stylesheet for the Woo4Etch wp-env demo. Loads on the frontend
 *              AND inside the Etch builder canvas, so the builder preview matches
 *              the live site. (In a real project you'd style via Etch's own CSS
 *              panel / global styles — those already apply in both places.)
 *
 * @package Woo4Etch
 */

if (!defined('ABSPATH')) {
    exit;
}

/** URL + version of the demo stylesheet (mapped at wp-content/mu-plugins/demo.css). */
function woo4etch_demo_css() {
    $path = WPMU_PLUGIN_DIR . '/demo.css';
    $ver  = file_exists($path) ? (string) filemtime($path) : '1';
    return ['url' => content_url('mu-plugins/demo.css'), 'ver' => $ver];
}

// Frontend
add_action('wp_enqueue_scripts', static function () {
    $css = woo4etch_demo_css();
    wp_enqueue_style('woo4etch-demo', $css['url'], [], $css['ver']);
});

// Etch builder canvas — so the preview matches the frontend.
add_filter('etch/canvas/additional_stylesheets', static function ($sheets) {
    if (!is_array($sheets)) {
        $sheets = [];
    }

    // Demo stylesheet (our custom w4e-* styling).
    $css = woo4etch_demo_css();
    $sheets[] = ['id' => 'woo4etch-demo', 'url' => $css['url'] . '?ver=' . $css['ver']];

    // WooCommerce's own frontend CSS, so native Woo areas (checkout, my-account,
    // notices, tabs…) look right in the builder canvas too. AJAX/cart still don't
    // run in the builder — that's fine, this is just styling. Respects the
    // woocommerce_enqueue_styles filter (returns nothing if Woo CSS is disabled).
    if (class_exists('WC_Frontend_Scripts')) {
        foreach (WC_Frontend_Scripts::get_styles() as $handle => $style) {
            if (!empty($style['src'])) {
                $sheets[] = ['id' => (string) $handle, 'url' => (string) $style['src']];
            }
        }
    }

    return $sheets;
});
