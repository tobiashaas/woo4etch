<?php
/**
 * Plugin Name:       Woo4Etch Bridge
 * Plugin URI:        https://github.com/tobiashaas/woo4etch
 * Description:       Shortcodes that bridge WooCommerce PHP into Etch templates. Includes a generic [do_action] plus targeted shortcodes for prices, stock, quantity inputs, notices, breadcrumbs, cart state, and more — for everything Etch can't do natively yet.
 * Version:           1.0.0
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Requires Plugins:  woocommerce
 * Author:            Tobias Haas
 * Author URI:        https://github.com/tobiashaas
 * License:           MIT
 * License URI:       https://opensource.org/licenses/MIT
 * Text Domain:       woo4etch
 *
 * @package Woo4Etch
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Bootstraps the plugin once WooCommerce is loaded.
 * Shows an admin notice and exits early if WooCommerce isn't active.
 */
add_action('plugins_loaded', static function () {
    if (!class_exists('WooCommerce')) {
        add_action('admin_notices', static function () {
            echo '<div class="notice notice-error"><p><strong>Woo4Etch Bridge</strong> requires WooCommerce to be active.</p></div>';
        });
        return;
    }

    Woo4Etch_Bridge::init();
});

/**
 * All bridge shortcodes in one class to keep the global namespace clean.
 */
final class Woo4Etch_Bridge {

    /** Plugin version. */
    const VERSION = '1.0.0';

    /**
     * Register all shortcodes.
     */
    public static function init() {
        // Generic hook bridge — Zack Pyle's snippet, hardened.
        add_shortcode('do_action', [__CLASS__, 'shortcode_do_action']);

        // Product data
        add_shortcode('woo_price',       [__CLASS__, 'shortcode_price']);
        add_shortcode('woo_sku',         [__CLASS__, 'shortcode_sku']);
        add_shortcode('woo_stock',       [__CLASS__, 'shortcode_stock']);
        add_shortcode('woo_meta',        [__CLASS__, 'shortcode_meta']);
        add_shortcode('woo_attribute',   [__CLASS__, 'shortcode_attribute']);

        // Product UI parts
        add_shortcode('woo_add_to_cart', [__CLASS__, 'shortcode_add_to_cart']);
        add_shortcode('woo_quantity',    [__CLASS__, 'shortcode_quantity']);
        add_shortcode('woo_rating',      [__CLASS__, 'shortcode_rating']);
        add_shortcode('woo_review_form', [__CLASS__, 'shortcode_review_form']);

        // Page-level UI
        add_shortcode('woo_notices',     [__CLASS__, 'shortcode_notices']);
        add_shortcode('woo_breadcrumb',  [__CLASS__, 'shortcode_breadcrumb']);

        // Cart state
        add_shortcode('woo_cart_count',  [__CLASS__, 'shortcode_cart_count']);
        add_shortcode('woo_cart_total',  [__CLASS__, 'shortcode_cart_total']);
        add_shortcode('woo_cart_url',    [__CLASS__, 'shortcode_cart_url']);

        // Customer
        add_shortcode('woo_user',        [__CLASS__, 'shortcode_user']);

        // WooCommerce template part loader
        add_shortcode('woo_template',    [__CLASS__, 'shortcode_template']);
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
