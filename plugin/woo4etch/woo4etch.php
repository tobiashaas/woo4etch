<?php
/**
 * Plugin Name:       Woo4Etch
 * Plugin URI:        https://github.com/tobiashaas/woo4etch
 * Description:       WooCommerce shortcodes and customization layer for Etch templates — [do_action], prices, stock, add-to-cart, notices, cart state, and more.
 * Version:           1.2.1
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Requires Plugins:  woocommerce
 * Author:            Tobias Haas
 * Author URI:        https://etchwp.com/?aff=06de86e5
 * License:           MIT
 * License URI:       https://opensource.org/licenses/MIT
 * Text Domain:       woo4etch
 *
 * @package Woo4Etch
 */

if (!defined('ABSPATH')) {
    exit;
}

/** Affiliate link for Etch (marketing URLs in this plugin). */
if (!defined('WOO4ETCH_ETCH_AFFILIATE_URL')) {
    define('WOO4ETCH_ETCH_AFFILIATE_URL', 'https://etchwp.com/?aff=06de86e5');
}

require_once __DIR__ . '/includes/class-woo4etch-admin.php';
require_once __DIR__ . '/includes/class-woo4etch-updater.php';
require_once __DIR__ . '/includes/customizations.php';

/**
 * Bootstraps the plugin once WooCommerce is loaded.
 * Shows an admin notice and exits early if WooCommerce isn't active.
 */
add_action('plugins_loaded', static function () {
    if (!class_exists('WooCommerce')) {
        add_action('admin_notices', static function () {
            echo '<div class="notice notice-error"><p><strong>Woo4Etch</strong> requires WooCommerce to be active.</p></div>';
        });
        return;
    }

    Woo4Etch::init();
    Woo4Etch_Updater::init(__FILE__);
});

/**
 * All shortcodes in one class to keep the global namespace clean.
 */
final class Woo4Etch {

    /** Plugin version. */
    const VERSION = '1.2.1';

    /**
     * Register all shortcodes and the admin reference screen.
     */
    public static function init() {
        foreach (self::get_shortcode_catalog() as $tag => $entry) {
            add_shortcode($tag, [__CLASS__, $entry['method']]);
        }

        if (is_admin()) {
            Woo4Etch_Admin::init();
        }
    }

    /**
     * Shortcode metadata for registration and the admin overview.
     *
     * @return array<string, array{method: string, category: string, attributes: string, description: string, example: string}>
     */
    public static function get_shortcode_catalog() {
        return apply_filters('woo4etch/shortcode_catalog', [
            'do_action' => [
                'method'      => 'shortcode_do_action',
                'category'    => __('Hooks', 'woo4etch'),
                'attributes'  => 'hook (required), args',
                'description' => __('Fires any WordPress or WooCommerce action hook.', 'woo4etch'),
                'example'     => '[do_action hook="woocommerce_before_add_to_cart_button"]',
            ],
            'woo_price' => [
                'method'      => 'shortcode_price',
                'category'    => __('Product data', 'woo4etch'),
                'attributes'  => 'id',
                'description' => __('Formatted price HTML (sales, variable “from” prices).', 'woo4etch'),
                'example'     => '[woo_price]',
            ],
            'woo_sku' => [
                'method'      => 'shortcode_sku',
                'category'    => __('Product data', 'woo4etch'),
                'attributes'  => 'id, default',
                'description' => __('Product SKU as plain text.', 'woo4etch'),
                'example'     => '[woo_sku default="N/A"]',
            ],
            'woo_stock' => [
                'method'      => 'shortcode_stock',
                'category'    => __('Product data', 'woo4etch'),
                'attributes'  => 'id, format (label|status|quantity)',
                'description' => __('Stock label HTML, status slug, or quantity.', 'woo4etch'),
                'example'     => '[woo_stock format="label"]',
            ],
            'woo_meta' => [
                'method'      => 'shortcode_meta',
                'category'    => __('Product data', 'woo4etch'),
                'attributes'  => 'id, key (required), default',
                'description' => __('Single product meta value.', 'woo4etch'),
                'example'     => '[woo_meta key="_sku"]',
            ],
            'woo_attribute' => [
                'method'      => 'shortcode_attribute',
                'category'    => __('Product data', 'woo4etch'),
                'attributes'  => 'id, name (required), default',
                'description' => __('Product attribute by taxonomy slug (e.g. pa_color).', 'woo4etch'),
                'example'     => '[woo_attribute name="pa_color"]',
            ],
            'woo_add_to_cart' => [
                'method'      => 'shortcode_add_to_cart',
                'category'    => __('Product UI', 'woo4etch'),
                'attributes'  => 'id',
                'description' => __('Full add-to-cart form for simple, variable, grouped, or external products.', 'woo4etch'),
                'example'     => '[woo_add_to_cart]',
            ],
            'woo_quantity' => [
                'method'      => 'shortcode_quantity',
                'category'    => __('Product UI', 'woo4etch'),
                'attributes'  => 'id, min, max, step, value',
                'description' => __('Quantity input only (no surrounding form).', 'woo4etch'),
                'example'     => '[woo_quantity min="1" max="10"]',
            ],
            'woo_rating' => [
                'method'      => 'shortcode_rating',
                'category'    => __('Product UI', 'woo4etch'),
                'attributes'  => 'id',
                'description' => __('Star rating HTML; empty when there are no reviews.', 'woo4etch'),
                'example'     => '[woo_rating]',
            ],
            'woo_review_form' => [
                'method'      => 'shortcode_review_form',
                'category'    => __('Product UI', 'woo4etch'),
                'attributes'  => 'id',
                'description' => __('Product reviews list and comment form.', 'woo4etch'),
                'example'     => '[woo_review_form]',
            ],
            'woo_notices' => [
                'method'      => 'shortcode_notices',
                'category'    => __('Page-level', 'woo4etch'),
                'attributes'  => '—',
                'description' => __('Queued WooCommerce notices (cart, checkout).', 'woo4etch'),
                'example'     => '[woo_notices]',
            ],
            'woo_breadcrumb' => [
                'method'      => 'shortcode_breadcrumb',
                'category'    => __('Page-level', 'woo4etch'),
                'attributes'  => 'delimiter, wrap_before, wrap_after',
                'description' => __('WooCommerce breadcrumb trail.', 'woo4etch'),
                'example'     => '[woo_breadcrumb]',
            ],
            'woo_cart_count' => [
                'method'      => 'shortcode_cart_count',
                'category'    => __('Cart', 'woo4etch'),
                'attributes'  => '—',
                'description' => __('Cart item count in a span with data-count (fragment-friendly).', 'woo4etch'),
                'example'     => '[woo_cart_count]',
            ],
            'woo_cart_total' => [
                'method'      => 'shortcode_cart_total',
                'category'    => __('Cart', 'woo4etch'),
                'attributes'  => '—',
                'description' => __('Formatted cart total.', 'woo4etch'),
                'example'     => '[woo_cart_total]',
            ],
            'woo_cart_url' => [
                'method'      => 'shortcode_cart_url',
                'category'    => __('Cart', 'woo4etch'),
                'attributes'  => '—',
                'description' => __('Cart page URL.', 'woo4etch'),
                'example'     => '[woo_cart_url]',
            ],
            'woo_user' => [
                'method'      => 'shortcode_user',
                'category'    => __('Customer', 'woo4etch'),
                'attributes'  => 'field, default',
                'description' => __('Current user field (display_name, user_email, first_name, …).', 'woo4etch'),
                'example'     => '[woo_user field="first_name" default="Guest"]',
            ],
            'woo_template' => [
                'method'      => 'shortcode_template',
                'category'    => __('Templates', 'woo4etch'),
                'attributes'  => 'name (required)',
                'description' => __('Loads a WooCommerce template part by path.', 'woo4etch'),
                'example'     => '[woo_template name="single-product/related"]',
            ],
        ]);
    }

    /* ============================================================
       Internal helpers
       ============================================================ */

    /**
     * Resolve the product to use:
     * - explicit id attribute, or
     * - the global $product (inside Woo template hooks), or
     * - the queried object when on a single product page.
     *
     * @param array $atts
     * @return WC_Product|null
     */
    private static function resolve_product($atts) {
        if (!empty($atts['id'])) {
            $product = wc_get_product(absint($atts['id']));
            return $product instanceof WC_Product ? $product : null;
        }

        global $product;
        if ($product instanceof WC_Product) {
            return $product;
        }

        if (function_exists('is_product') && is_product()) {
            $maybe = wc_get_product(get_queried_object_id());
            return $maybe instanceof WC_Product ? $maybe : null;
        }

        return null;
    }

    /**
     * Temporarily set the $product global and restore on shutdown of the closure.
     *
     * @param WC_Product $product
     * @param callable   $callback
     * @return string
     */
    private static function with_product(WC_Product $product, callable $callback) {
        $original = isset($GLOBALS['product']) ? $GLOBALS['product'] : null;
        $GLOBALS['product'] = $product;
        ob_start();
        try {
            $callback();
        } finally {
            $GLOBALS['product'] = $original;
        }
        return ob_get_clean();
    }

    /* ============================================================
       Generic [do_action]
       ============================================================ */

    /**
     * Fire any WordPress / WooCommerce action hook from inside content.
     *
     * Examples:
     *   [do_action hook="woocommerce_before_add_to_cart_button"]
     *   [do_action hook="woocommerce_thankyou" args="{this.id}"]
     *
     * Restrict allowed hooks via the `woo4etch/allow_do_action` filter:
     *   add_filter('woo4etch/allow_do_action', function ($allowed, $hook) {
     *       return strpos($hook, 'woocommerce_') === 0;
     *   }, 10, 2);
     */
    public static function shortcode_do_action($atts) {
        $atts = shortcode_atts([
            'hook' => '',
            'args' => '',
        ], $atts, 'do_action');

        $hook = sanitize_key($atts['hook']);
        if (!$hook) {
            return '';
        }

        if (!apply_filters('woo4etch/allow_do_action', true, $hook)) {
            return '';
        }

        $args = [];
        if ($atts['args'] !== '') {
            $args = array_map('trim', explode(',', $atts['args']));
        }

        ob_start();
        do_action($hook, ...$args);
        return ob_get_clean();
    }

    /* ============================================================
       Product data shortcodes
       ============================================================ */

    public static function shortcode_price($atts) {
        $atts    = shortcode_atts(['id' => 0], $atts, 'woo_price');
        $product = self::resolve_product($atts);
        return $product ? $product->get_price_html() : '';
    }

    public static function shortcode_sku($atts) {
        $atts    = shortcode_atts(['id' => 0, 'default' => ''], $atts, 'woo_sku');
        $product = self::resolve_product($atts);
        if (!$product) {
            return esc_html($atts['default']);
        }
        $sku = $product->get_sku();
        return $sku === '' ? esc_html($atts['default']) : esc_html($sku);
    }

    public static function shortcode_stock($atts) {
        $atts    = shortcode_atts(['id' => 0, 'format' => 'label'], $atts, 'woo_stock');
        $product = self::resolve_product($atts);
        if (!$product) {
            return '';
        }

        switch ($atts['format']) {
            case 'quantity':
                return (string) ($product->get_stock_quantity() ?? '');
            case 'status':
                return esc_html($product->get_stock_status());
            case 'label':
            default:
                $avail = $product->get_availability();
                if (empty($avail['availability'])) {
                    return '';
                }
                return sprintf(
                    '<span class="stock %s">%s</span>',
                    esc_attr($avail['class'] ?? ''),
                    esc_html($avail['availability'])
                );
        }
    }

    public static function shortcode_meta($atts) {
        $atts = shortcode_atts(['id' => 0, 'key' => '', 'default' => ''], $atts, 'woo_meta');
        if (!$atts['key']) {
            return '';
        }
        $product = self::resolve_product($atts);
        if (!$product) {
            return esc_html($atts['default']);
        }
        $value = $product->get_meta(sanitize_text_field($atts['key']));
        if ($value === '' || $value === null) {
            return esc_html($atts['default']);
        }
        return esc_html(is_scalar($value) ? (string) $value : '');
    }

    public static function shortcode_attribute($atts) {
        $atts = shortcode_atts(['id' => 0, 'name' => '', 'default' => ''], $atts, 'woo_attribute');
        if (!$atts['name']) {
            return '';
        }
        $product = self::resolve_product($atts);
        if (!$product) {
            return esc_html($atts['default']);
        }
        $value = $product->get_attribute(sanitize_text_field($atts['name']));
        return $value === '' ? esc_html($atts['default']) : esc_html($value);
    }

    /* ============================================================
       Product UI shortcodes
       ============================================================ */

    /**
     * Render the full add-to-cart form (simple, variable, grouped, or external).
     * Falls back to nothing if no product can be resolved.
     */
    public static function shortcode_add_to_cart($atts) {
        $atts    = shortcode_atts(['id' => 0], $atts, 'woo_add_to_cart');
        $product = self::resolve_product($atts);
        if (!$product) {
            return '';
        }

        return self::with_product($product, function () use ($product) {
            wc_get_template_part('single-product/add-to-cart/' . $product->get_type());
        });
    }

    /**
     * Render just the quantity input (no surrounding form).
     */
    public static function shortcode_quantity($atts) {
        $atts = shortcode_atts([
            'id'    => 0,
            'min'   => 1,
            'max'   => '',
            'step'  => 1,
            'value' => 1,
        ], $atts, 'woo_quantity');

        $product = self::resolve_product($atts);

        $args = [
            'min_value'   => max(0, intval($atts['min'])),
            'max_value'   => $atts['max'] === '' ? '' : intval($atts['max']),
            'input_value' => max(0, intval($atts['value'])),
            'step'        => max(1, intval($atts['step'])),
        ];

        ob_start();
        woocommerce_quantity_input($args, $product, true);
        return ob_get_clean();
    }

    public static function shortcode_rating($atts) {
        $atts    = shortcode_atts(['id' => 0], $atts, 'woo_rating');
        $product = self::resolve_product($atts);
        if (!$product) {
            return '';
        }
        $rating = (float) $product->get_average_rating();
        if ($rating <= 0) {
            return '';
        }
        return wc_get_rating_html($rating, $product->get_rating_count());
    }

    /**
     * Render the product review/comment form (needs comments_template support).
     */
    public static function shortcode_review_form($atts) {
        $atts    = shortcode_atts(['id' => 0], $atts, 'woo_review_form');
        $product = self::resolve_product($atts);
        if (!$product || !comments_open($product->get_id())) {
            return '';
        }
        return self::with_product($product, static function () {
            comments_template();
        });
    }

    /* ============================================================
       Page-level shortcodes
       ============================================================ */

    public static function shortcode_notices() {
        if (!function_exists('wc_print_notices')) {
            return '';
        }
        ob_start();
        wc_print_notices();
        return ob_get_clean();
    }

    public static function shortcode_breadcrumb($atts) {
        $atts = shortcode_atts([
            'delimiter'   => ' / ',
            'wrap_before' => '<nav class="woocommerce-breadcrumb" aria-label="Breadcrumb">',
            'wrap_after'  => '</nav>',
        ], $atts, 'woo_breadcrumb');

        ob_start();
        woocommerce_breadcrumb($atts);
        return ob_get_clean();
    }

    /* ============================================================
       Cart shortcodes
       ============================================================ */

    public static function shortcode_cart_count() {
        $count = WC()->cart ? WC()->cart->get_cart_contents_count() : 0;
        return sprintf(
            '<span class="kr-cart-count" data-count="%1$d">%1$d</span>',
            (int) $count
        );
    }

    public static function shortcode_cart_total() {
        if (!WC()->cart) {
            return '';
        }
        return WC()->cart->get_cart_total();
    }

    public static function shortcode_cart_url() {
        return esc_url(wc_get_cart_url());
    }

    /* ============================================================
       Customer shortcodes
       ============================================================ */

    /**
     * Output a field from the current user. Falls back to `default` for guests
     * or when the field doesn't exist.
     *
     * Allowed fields: display_name, user_login, user_email, first_name, last_name, ID
     */
    public static function shortcode_user($atts) {
        $atts = shortcode_atts(['field' => 'display_name', 'default' => ''], $atts, 'woo_user');
        if (!is_user_logged_in()) {
            return esc_html($atts['default']);
        }

        $allowed = ['display_name', 'user_login', 'user_email', 'first_name', 'last_name', 'ID'];
        $field   = in_array($atts['field'], $allowed, true) ? $atts['field'] : 'display_name';

        $user  = wp_get_current_user();
        $value = isset($user->$field) ? (string) $user->$field : $atts['default'];

        return esc_html($value);
    }

    /* ============================================================
       Template part loader
       ============================================================ */

    /**
     * Load any WooCommerce template part.
     *
     * Example:
     *   [woo_template name="single-product/related"]
     *   [woo_template name="cart/cross-sells"]
     */
    public static function shortcode_template($atts) {
        $atts = shortcode_atts(['name' => ''], $atts, 'woo_template');
        if (!$atts['name']) {
            return '';
        }

        // Strict whitelist of characters; block parent-directory traversal.
        $name = preg_replace('#[^a-z0-9\-_/\.]#i', '', $atts['name']);
        if ($name === '' || strpos($name, '..') !== false) {
            return '';
        }

        ob_start();
        wc_get_template_part($name);
        return ob_get_clean();
    }
}

