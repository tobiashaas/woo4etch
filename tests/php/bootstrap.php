<?php
/**
 * Minimal WordPress shim so the Woo4Etch plugin files load under plain PHP-CLI
 * — no WordPress, no WooCommerce, no database. It defines the handful of
 * constants and functions that run at *include time* (the top-level
 * add_action()/add_filter() calls in woo4etch.php) and the ones the
 * pure-data code paths touch (the shortcode catalog's __()/apply_filters(),
 * the layout builder's get_option()/update_option()).
 *
 * This is the foundation of the fast, service-free test layer (issue #10,
 * layers 2–3). The shortcode *implementations* are never executed here — only
 * metadata (get_shortcode_catalog) and the layout block trees
 * (Woo4Etch_Layouts) are inspected — so the stubs only need to cover loading
 * and those two surfaces, not the full WordPress API.
 *
 * @package Woo4Etch\Tests
 */

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "bootstrap.php is for CLI test runs only.\n");
    exit(1);
}

define('WOO4ETCH_TESTS_DIR', __DIR__);
define('WOO4ETCH_REPO_ROOT', dirname(dirname(__DIR__)));
define('WOO4ETCH_PLUGIN_DIR', WOO4ETCH_REPO_ROOT . '/plugin/woo4etch');

/* ---- WordPress constants the plugin reads at load time ---- */
if (!defined('ABSPATH')) {
    define('ABSPATH', WOO4ETCH_REPO_ROOT . '/');
}
if (!defined('WP_CONTENT_DIR')) {
    define('WP_CONTENT_DIR', WOO4ETCH_REPO_ROOT . '/wp-content-stub');
}
if (!defined('WPINC')) {
    define('WPINC', 'wp-includes');
}

/*
 * In-memory option store so the layout builder's ensure_main_query_loop()
 * (get_option/update_option) is deterministic across runs.
 */
$GLOBALS['__w4e_options'] = [];

/* ---- i18n: identity passthroughs ---- */
if (!function_exists('__')) {
    function __($text, $domain = 'default') { return $text; }
}
if (!function_exists('_e')) {
    function _e($text, $domain = 'default') { echo $text; }
}
if (!function_exists('_x')) {
    function _x($text, $context, $domain = 'default') { return $text; }
}
if (!function_exists('_n')) {
    function _n($single, $plural, $number, $domain = 'default') { return $number == 1 ? $single : $plural; }
}
if (!function_exists('esc_html__')) {
    function esc_html__($text, $domain = 'default') { return $text; }
}
if (!function_exists('esc_attr__')) {
    function esc_attr__($text, $domain = 'default') { return $text; }
}

/* ---- Escaping / sanitising: enough to not destroy the data we inspect ---- */
if (!function_exists('esc_html')) {
    function esc_html($text) { return htmlspecialchars((string) $text, ENT_QUOTES); }
}
if (!function_exists('esc_attr')) {
    function esc_attr($text) { return htmlspecialchars((string) $text, ENT_QUOTES); }
}
if (!function_exists('esc_url')) {
    function esc_url($url) { return (string) $url; }
}
if (!function_exists('wp_kses_post')) {
    function wp_kses_post($text) { return $text; }
}
if (!function_exists('sanitize_key')) {
    function sanitize_key($key) { return strtolower(preg_replace('/[^a-z0-9_\-]/', '', (string) $key)); }
}
if (!function_exists('sanitize_text_field')) {
    function sanitize_text_field($str) { return trim(preg_replace('/\s+/', ' ', strip_tags((string) $str))); }
}
if (!function_exists('sanitize_title')) {
    function sanitize_title($title) { return strtolower(preg_replace('/[^a-z0-9_\-]+/', '-', (string) $title)); }
}
if (!function_exists('wp_slash')) {
    function wp_slash($value) { return $value; }
}
if (!function_exists('absint')) {
    function absint($n) { return abs((int) $n); }
}
if (!function_exists('array_is_list')) {
    // PHP 8.0+ builtin; polyfilled so the harness also runs under PHP 7.4.
    function array_is_list(array $arr) {
        if ($arr === []) {
            return true;
        }
        return array_keys($arr) === range(0, count($arr) - 1);
    }
}

/* ---- Hook system: record nothing, just don't fatal ---- */
if (!function_exists('add_action')) {
    function add_action($hook, $cb, $priority = 10, $args = 1) { return true; }
}
if (!function_exists('add_filter')) {
    function add_filter($hook, $cb, $priority = 10, $args = 1) { return true; }
}
if (!function_exists('do_action')) {
    function do_action($hook, ...$args) {}
}
if (!function_exists('apply_filters')) {
    // Return the value unchanged — catalogs/flags resolve to their defaults.
    function apply_filters($hook, $value = null, ...$args) { return $value; }
}
if (!function_exists('remove_action')) {
    function remove_action($hook, $cb, $priority = 10) { return true; }
}
if (!function_exists('has_action')) {
    function has_action($hook, $cb = false) { return false; }
}
if (!function_exists('register_activation_hook')) {
    function register_activation_hook($file, $cb) {}
}
if (!function_exists('register_deactivation_hook')) {
    function register_deactivation_hook($file, $cb) {}
}
if (!function_exists('__return_true')) {
    function __return_true() { return true; }
}
if (!function_exists('__return_false')) {
    function __return_false() { return false; }
}
if (!function_exists('__return_empty_array')) {
    function __return_empty_array() { return []; }
}

/* ---- Options: backed by the in-memory store ---- */
if (!function_exists('get_option')) {
    function get_option($name, $default = false) {
        return array_key_exists($name, $GLOBALS['__w4e_options']) ? $GLOBALS['__w4e_options'][$name] : $default;
    }
}
if (!function_exists('update_option')) {
    function update_option($name, $value) { $GLOBALS['__w4e_options'][$name] = $value; return true; }
}
if (!function_exists('add_option')) {
    function add_option($name, $value) { $GLOBALS['__w4e_options'][$name] = $value; return true; }
}
if (!function_exists('delete_option')) {
    function delete_option($name) { unset($GLOBALS['__w4e_options'][$name]); return true; }
}

/* ---- Misc helpers the plugin touches at load / metadata time ---- */
if (!function_exists('plugin_basename')) {
    function plugin_basename($file) { return basename(dirname($file)) . '/' . basename($file); }
}
if (!function_exists('plugins_url')) {
    function plugins_url($path = '', $plugin = '') { return $path; }
}
if (!function_exists('trailingslashit')) {
    function trailingslashit($string) { return rtrim((string) $string, '/\\') . '/'; }
}
if (!function_exists('get_temp_dir')) {
    function get_temp_dir() { return sys_get_temp_dir() . '/'; }
}
if (!function_exists('is_admin')) {
    function is_admin() { return false; }
}
if (!function_exists('wp_is_block_theme')) {
    function wp_is_block_theme() { return true; }
}
if (!function_exists('current_theme_supports')) {
    function current_theme_supports($feature) { return false; }
}
if (!function_exists('add_theme_support')) {
    function add_theme_support($feature, ...$args) { return true; }
}

/**
 * Load the plugin's source so its classes are available to the test files.
 * woo4etch.php pulls in the includes/ classes and registers (stubbed) hooks.
 */
require_once WOO4ETCH_PLUGIN_DIR . '/woo4etch.php';
