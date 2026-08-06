<?php
/**
 * Plugin Name:       Woo4Etch
 * Plugin URI:        https://github.com/tobiashaas/woo4etch
 * Description:       WooCommerce shortcodes and customization layer for Etch templates — [do_action], prices, stock, add-to-cart, gallery, conditionals, archive, and Woo data as Etch dynamic data (cart, account, orders).
 * Version:           1.9.0
 * Requires at least: 6.0
 * Requires PHP:      8.1
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
require_once __DIR__ . '/includes/class-woo4etch-layouts.php';
require_once __DIR__ . '/includes/class-woo4etch-components.php';
require_once __DIR__ . '/includes/class-woo4etch-health.php';
require_once __DIR__ . '/includes/class-woo4etch-woo-root.php';
require_once __DIR__ . '/includes/class-woo4etch-updater.php';
require_once __DIR__ . '/includes/customizations.php';

// Update-safe alternative to includes/customizations.php: a file OUTSIDE the
// plugin folder that no plugin update can ever touch. Optional — create it
// and it loads; the bundled file keeps working too (and is preserved across
// updates by the upgrader hooks below).
if (file_exists(WP_CONTENT_DIR . '/woo4etch-customizations.php')) {
    require_once WP_CONTENT_DIR . '/woo4etch-customizations.php';
}

/*
 * Preserve includes/customizations.php across plugin updates.
 *
 * WordPress replaces the whole plugin folder on update, which would wipe the
 * snippets users pasted into customizations.php — breaking the ADR-001
 * promise that updates never touch user customisations. Before this plugin
 * is updated the file is copied aside — but only when the user actually
 * edited it (it differs from the shipped skeleton, WOO4ETCH_SKELETON_MD5);
 * afterwards the backup, if any, is restored. An untouched skeleton is never
 * preserved, so skeleton improvements still arrive for users who never
 * edited it. (Comparing the backup against the *new* file instead — the old
 * behaviour — couldn't tell "user edited" from "skeleton improved" and
 * clobbered improved skeletons with the old one.)
 *
 * WOO4ETCH_SKELETON_MD5 must equal md5 of the shipped skeleton — the
 * service-free test layer asserts this on every PR.
 */
define('WOO4ETCH_SKELETON_MD5', '2f16c60ee54637bae945f4b62b939ba0');

add_filter('upgrader_pre_install', static function ($response, $hook_extra) {
    if (is_wp_error($response)
        || !isset($hook_extra['plugin'])
        || plugin_basename(__FILE__) !== $hook_extra['plugin']) {
        return $response;
    }

    // Pre-check: WordPress's copy_dir() fails mid-update with the generic
    // "some files could not be copied" when file ownership/permissions under
    // the plugin folder drifted from the PHP user (seen on GridPane after
    // certain provisioning steps). Detect that BEFORE any file is touched
    // and fail with an actionable message instead. (Issue #23)
    $unwritable = [];
    $iterator   = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator(__DIR__, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );
    foreach ($iterator as $file) {
        if (!is_writable($file->getPathname())) {
            $unwritable[] = str_replace(trailingslashit(dirname(__DIR__)), '', $file->getPathname());
            if (count($unwritable) >= 5) {
                break;
            }
        }
    }
    if (!is_writable(__DIR__)) {
        array_unshift($unwritable, basename(__DIR__) . '/');
    }
    if ($unwritable) {
        return new WP_Error(
            'woo4etch_files_not_writable',
            sprintf(
                /* translators: 1: plugin directory path, 2: list of example files */
                __('Woo4Etch update aborted before touching any files: the web server cannot overwrite parts of %1$s (e.g. %2$s). Fix the file ownership/permissions for that folder — on GridPane run the site\'s permission-reset tool; generally: chown the folder to the PHP user, directories 755, files 644 — then retry the update. Your current plugin version is untouched.', 'woo4etch'),
                'wp-content/plugins/' . basename(__DIR__) . '/',
                implode(', ', array_slice($unwritable, 0, 5))
            )
        );
    }

    if (file_exists(__DIR__ . '/includes/customizations.php')
        && md5_file(__DIR__ . '/includes/customizations.php') !== WOO4ETCH_SKELETON_MD5) {
        @copy(__DIR__ . '/includes/customizations.php', get_temp_dir() . 'woo4etch-customizations.preserved.php');
    }
    return $response;
}, 10, 2);

add_filter('upgrader_post_install', static function ($response, $hook_extra, $result) {
    if (is_wp_error($response)
        || empty($hook_extra['plugin'])
        || plugin_basename(__FILE__) !== $hook_extra['plugin']) {
        return $response;
    }
    $backup = get_temp_dir() . 'woo4etch-customizations.preserved.php';
    if (file_exists($backup)) {
        $dest = isset($result['destination']) ? trailingslashit($result['destination']) . 'includes/customizations.php' : '';
        if ($dest !== '' && file_exists($dest)) {
            @copy($backup, $dest);
        }
        @unlink($backup);
    }
    return $response;
}, 10, 3);

/**
 * Declare compatibility with WooCommerce High-Performance Order Storage (HPOS).
 * This plugin only renders product/cart shortcodes and never touches order
 * storage directly, so it is compatible with both the legacy and HPOS engines.
 */
add_action('before_woocommerce_init', static function () {
    if (class_exists(\Automattic\WooCommerce\Utilities\FeaturesUtil::class)) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
            'custom_order_tables',
            __FILE__,
            true
        );
    }
});

/**
 * Declare WooCommerce theme support on behalf of the active theme.
 *
 * Why this matters: when no theme declares add_theme_support('woocommerce'),
 * WooCommerce treats the theme as "unsupported" and switches on a compatibility
 * shim (WC_Template_Loader::unsupported_theme_*) that injects its own content
 * wrappers and page title into shop/product pages via the_content. In an Etch
 * build — where YOU own the markup — that shim fights your layout and triggers
 * the "theme does not declare WooCommerce support" admin notice.
 *
 * The official Etch theme does not declare it (and tells you not to edit its
 * functions.php), so Woo4Etch fills the gap automatically. Runs late (priority
 * 99) so a theme or child theme that DOES declare support always wins; we only
 * step in when nothing else has.
 *
 * Disable entirely:        add_filter('woo4etch/auto_theme_support', '__return_false');
 * Customise the sizes:     add_filter('woo4etch/theme_support_args', fn($a) => [...]);
 * Enable Woo gallery JS:   add_filter('woo4etch/gallery_features', fn() => ['wc-product-gallery-zoom', 'wc-product-gallery-lightbox', 'wc-product-gallery-slider']);
 */
add_action('after_setup_theme', static function () {
    if (!class_exists('WooCommerce')) {
        return;
    }

    if (!apply_filters('woo4etch/auto_theme_support', true)) {
        return;
    }

    // Respect a theme/child theme that already declares support — never override it.
    if (!current_theme_supports('woocommerce')) {
        $args = apply_filters('woo4etch/theme_support_args', [
            'thumbnail_image_width' => 600,
            'single_image_width'    => 1200,
            'product_grid'          => [
                'default_rows'    => 3,
                'min_rows'        => 1,
                'default_columns' => 3,
                'min_columns'     => 1,
                'max_columns'     => 6,
            ],
        ]);

        add_theme_support('woocommerce', $args);
    }

    // Off by default: Etch layouts usually build their own gallery. Opt in via
    // the admin checkbox (Woo4Etch → Settings) or this filter; the filter
    // receives the checkbox result and wins either way.
    $settings = (array) get_option('woo4etch_settings', []);
    $default_features = empty($settings['enable_gallery_scripts']) ? [] : [
        'wc-product-gallery-zoom',
        'wc-product-gallery-lightbox',
        'wc-product-gallery-slider',
    ];
    $gallery_features = (array) apply_filters('woo4etch/gallery_features', $default_features);
    foreach ($gallery_features as $feature) {
        add_theme_support(sanitize_key($feature));
    }
}, 99);

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

    // Register on `init` (not here on plugins_loaded): the shortcode catalog
    // uses __() for the admin reference, and calling translation functions
    // before `init` triggers the WP 6.7+ "textdomain loaded too early" notice.
    add_action('init', ['Woo4Etch', 'init']);
    Woo4Etch_Updater::init(__FILE__);
});

/**
 * All shortcodes in one class to keep the global namespace clean.
 */
final class Woo4Etch {

    /** Plugin version. */
    const VERSION = '1.9.0';

    /**
     * Register all shortcodes and the admin reference screen.
     *
     * Catalog entries flagged `native` (WooCommerce core shortcodes) are listed
     * in the admin reference for discoverability but NOT registered here —
     * WooCommerce already owns those tags.
     */
    public static function init() {
        foreach (self::get_shortcode_catalog() as $tag => $entry) {
            if (!empty($entry['native']) || empty($entry['method'])) {
                continue;
            }
            add_shortcode($tag, [__CLASS__, $entry['method']]);
        }

        // Expose WooCommerce runtime data to Etch as dynamic data so cart, account
        // and thank-you pages can be built as pure Etch loops with full HTML control.
        // Disable individually: woo4etch/expose_cart_data, woo4etch/expose_account_data.
        add_filter('etch/dynamic_data/option', [__CLASS__, 'expose_cart_data']);
        add_filter('etch/dynamic_data/option', [__CLASS__, 'expose_checkout_data']);
        add_filter('etch/dynamic_data/option', [__CLASS__, 'expose_account_order_data']);
        add_filter('etch/dynamic_data/option', [__CLASS__, 'expose_shop_data']);

        // WooCommerce applies its native archive filters (?min_price,
        // ?max_price, ?filter_<attribute>) to the MAIN query only — Etch's
        // main-query loop runs its own product query, so the filters would be
        // silently ignored. Re-apply them to secondary product queries while
        // on a Woo archive. Disable:
        //   add_filter('woo4etch/filter_secondary_product_queries', '__return_false');
        add_action('pre_get_posts', [__CLASS__, 'apply_attribute_filters_to_secondary_queries'], 20);
        add_filter('posts_clauses', [__CLASS__, 'apply_price_filter_to_secondary_queries'], 20, 2);
        // Same seam for the page size: Woo's per-page (loop_shop_per_page)
        // also reaches the main query only, so Etch's loop falls back to the
        // blog reading setting while [woo_pagination] counts pages from Woo's
        // per-page — tail products become unreachable. Disable:
        //   add_filter('woo4etch/sync_secondary_per_page', '__return_false');
        add_action('pre_get_posts', [__CLASS__, 'sync_per_page_on_secondary_queries'], 20);

        // Product fields ({this.price}, {this.is_on_sale}, …) on the post root —
        // the same seam Etch's own integration uses for gallery_images.
        // Disable: woo4etch/expose_product_data.
        add_filter('etch/dynamic_data/post', [__CLASS__, 'expose_product_data'], 10, 2);

        // Experimental {woo.*} root — same data as {options.*}, namespaced.
        // Disable: woo4etch/enable_woo_root. See class-woo4etch-woo-root.php.
        Woo4Etch_Woo_Root::init();

        self::register_frontend_features();

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

            /* ---- Hooks ---- */
            'do_action' => [
                'method'      => 'shortcode_do_action',
                'category'    => __('Hooks', 'woo4etch'),
                'attributes'  => 'hook (required), args',
                'description' => __('Fires any WordPress or WooCommerce action hook.', 'woo4etch'),
                'example'     => '[do_action hook="woocommerce_before_add_to_cart_button"]',
            ],

            /* ---- Product data ---- */
            'woo_title' => [
                'method'      => 'shortcode_title',
                'category'    => __('Product data', 'woo4etch'),
                'attributes'  => 'id, link (yes|no)',
                'description' => __('Product name, optionally wrapped in a permalink.', 'woo4etch'),
                'example'     => '[woo_title]',
            ],
            'woo_price' => [
                'method'      => 'shortcode_price',
                'category'    => __('Product data', 'woo4etch'),
                'attributes'  => 'id',
                'description' => __('Formatted price HTML (sales, variable “from” prices).', 'woo4etch'),
                'example'     => '[woo_price]',
            ],
            'woo_regular_price' => [
                'method'      => 'shortcode_regular_price',
                'category'    => __('Product data', 'woo4etch'),
                'attributes'  => 'id',
                'description' => __('Formatted regular (non-sale) price.', 'woo4etch'),
                'example'     => '[woo_regular_price]',
            ],
            'woo_sale_price' => [
                'method'      => 'shortcode_sale_price',
                'category'    => __('Product data', 'woo4etch'),
                'attributes'  => 'id',
                'description' => __('Formatted sale price; empty when not on sale.', 'woo4etch'),
                'example'     => '[woo_sale_price]',
            ],
            'woo_price_amount' => [
                'method'      => 'shortcode_price_amount',
                'category'    => __('Product data', 'woo4etch'),
                'attributes'  => 'id',
                'description' => __('Raw numeric price (no currency markup) — for itemprop/schema.', 'woo4etch'),
                'example'     => '<meta itemprop="price" content="[woo_price_amount]">',
            ],
            'woo_sale_badge' => [
                'method'      => 'shortcode_sale_badge',
                'category'    => __('Product data', 'woo4etch'),
                'attributes'  => 'id, text, percentage (yes|no)',
                'description' => __('“Sale!” badge (or discount %) when the product is on sale; empty otherwise.', 'woo4etch'),
                'example'     => '[woo_sale_badge percentage="yes"]',
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
            'woo_weight' => [
                'method'      => 'shortcode_weight',
                'category'    => __('Product data', 'woo4etch'),
                'attributes'  => 'id, default',
                'description' => __('Formatted product weight with the store unit.', 'woo4etch'),
                'example'     => '[woo_weight default="—"]',
            ],
            'woo_dimensions' => [
                'method'      => 'shortcode_dimensions',
                'category'    => __('Product data', 'woo4etch'),
                'attributes'  => 'id, default',
                'description' => __('Formatted product dimensions with the store unit.', 'woo4etch'),
                'example'     => '[woo_dimensions default="—"]',
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
            'woo_product_attributes' => [
                'method'      => 'shortcode_product_attributes',
                'category'    => __('Product data', 'woo4etch'),
                'attributes'  => 'id',
                'description' => __('Full attributes table (visible attributes plus weight/dimensions) — the "Additional information" tab as a table, empty when the product has no data.', 'woo4etch'),
                'example'     => '[woo_product_attributes]',
            ],
            'woo_categories' => [
                'method'      => 'shortcode_categories',
                'category'    => __('Product data', 'woo4etch'),
                'attributes'  => 'id, sep, before, after',
                'description' => __('Linked product category list.', 'woo4etch'),
                'example'     => '[woo_categories sep=", "]',
            ],
            'woo_tags' => [
                'method'      => 'shortcode_tags',
                'category'    => __('Product data', 'woo4etch'),
                'attributes'  => 'id, sep, before, after',
                'description' => __('Linked product tag list.', 'woo4etch'),
                'example'     => '[woo_tags sep=", "]',
            ],
            'woo_short_description' => [
                'method'      => 'shortcode_short_description',
                'category'    => __('Product data', 'woo4etch'),
                'attributes'  => 'id',
                'description' => __('Product short description (filtered HTML).', 'woo4etch'),
                'example'     => '[woo_short_description]',
            ],
            'woo_description' => [
                'method'      => 'shortcode_description',
                'category'    => __('Product data', 'woo4etch'),
                'attributes'  => 'id',
                'description' => __('Full product description (filtered HTML).', 'woo4etch'),
                'example'     => '[woo_description]',
            ],

            /* ---- Product media ---- */
            'woo_image' => [
                'method'      => 'shortcode_image',
                'category'    => __('Product media', 'woo4etch'),
                'attributes'  => 'id, size',
                'description' => __('Featured product image at a registered size.', 'woo4etch'),
                'example'     => '[woo_image size="woocommerce_single"]',
            ],
            'woo_gallery' => [
                'method'      => 'shortcode_gallery',
                'category'    => __('Product media', 'woo4etch'),
                'attributes'  => 'id, size, include_featured (yes|no), link (yes|no), mode (custom|woo), columns',
                'description' => __('Product gallery images (matches the gallery_images Dynamic Key; featured image excluded unless include_featured="yes"). mode="woo" outputs WooCommerce-native markup (featured first) that Woo\'s zoom/lightbox/slider scripts initialise on — enable those under Woo4Etch → Settings.', 'woo4etch'),
                'example'     => '[woo_gallery mode="woo" columns="4"]',
            ],

            /* ---- Product UI ---- */
            'woo_add_to_cart' => [
                'method'      => 'shortcode_add_to_cart',
                'category'    => __('Product UI', 'woo4etch'),
                'attributes'  => 'id',
                'description' => __('Full add-to-cart form for simple, variable, grouped, or external products.', 'woo4etch'),
                'example'     => '[woo_add_to_cart]',
            ],
            'woo_add_to_cart_url' => [
                'method'      => 'shortcode_add_to_cart_url',
                'category'    => __('Product UI', 'woo4etch'),
                'attributes'  => 'id',
                'description' => __('Direct add-to-cart URL (for custom buttons / archive cards).', 'woo4etch'),
                'example'     => '<a href="[woo_add_to_cart_url]">Buy</a>',
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
            'woo_tabs' => [
                'method'      => 'shortcode_tabs',
                'category'    => __('Product UI', 'woo4etch'),
                'attributes'  => 'id',
                'description' => __('Product data tabs (description, additional info, reviews).', 'woo4etch'),
                'example'     => '[woo_tabs]',
            ],
            'woo_related' => [
                'method'      => 'shortcode_related',
                'category'    => __('Product UI', 'woo4etch'),
                'attributes'  => 'id',
                'description' => __('Related products block for the current product.', 'woo4etch'),
                'example'     => '[woo_related]',
            ],
            'woo_upsells' => [
                'method'      => 'shortcode_upsells',
                'category'    => __('Product UI', 'woo4etch'),
                'attributes'  => 'id',
                'description' => __('Up-sell products block for the current product.', 'woo4etch'),
                'example'     => '[woo_upsells]',
            ],

            /* ---- Cart ---- */
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
            'woo_checkout_url' => [
                'method'      => 'shortcode_checkout_url',
                'category'    => __('Cart', 'woo4etch'),
                'attributes'  => '—',
                'description' => __('Checkout page URL.', 'woo4etch'),
                'example'     => '[woo_checkout_url]',
            ],
            'woo_mini_cart' => [
                'method'      => 'shortcode_mini_cart',
                'category'    => __('Cart', 'woo4etch'),
                'attributes'  => '—',
                'description' => __('Mini-cart widget markup (pair with Woo cart fragments for live updates).', 'woo4etch'),
                'example'     => '[woo_mini_cart]',
            ],

            /* ---- Account ---- */
            'woo_user' => [
                'method'      => 'shortcode_user',
                'category'    => __('Account', 'woo4etch'),
                'attributes'  => 'field, default',
                'description' => __('Current user field (display_name, user_email, first_name, …).', 'woo4etch'),
                'example'     => '[woo_user field="first_name" default="Guest"]',
            ],
            'woo_account_url' => [
                'method'      => 'shortcode_account_url',
                'category'    => __('Account', 'woo4etch'),
                'attributes'  => 'endpoint',
                'description' => __('My Account page URL, or a specific account endpoint.', 'woo4etch'),
                'example'     => '[woo_account_url endpoint="orders"]',
            ],
            'woo_logout_url' => [
                'method'      => 'shortcode_logout_url',
                'category'    => __('Account', 'woo4etch'),
                'attributes'  => 'redirect',
                'description' => __('Nonce-protected logout URL.', 'woo4etch'),
                'example'     => '[woo_logout_url]',
            ],

            /* ---- Store & archive ---- */
            'woo_shop_url' => [
                'method'      => 'shortcode_shop_url',
                'category'    => __('Store & archive', 'woo4etch'),
                'attributes'  => '—',
                'description' => __('Shop page URL.', 'woo4etch'),
                'example'     => '[woo_shop_url]',
            ],
            'woo_breadcrumb' => [
                'method'      => 'shortcode_breadcrumb',
                'category'    => __('Store & archive', 'woo4etch'),
                'attributes'  => 'delimiter, wrap_before, wrap_after',
                'description' => __('WooCommerce breadcrumb trail.', 'woo4etch'),
                'example'     => '[woo_breadcrumb]',
            ],
            'woo_result_count' => [
                'method'      => 'shortcode_result_count',
                'category'    => __('Store & archive', 'woo4etch'),
                'attributes'  => '—',
                'description' => __('“Showing 1–12 of 48 results” count (archive/shop loop).', 'woo4etch'),
                'example'     => '[woo_result_count]',
            ],
            'woo_catalog_ordering' => [
                'method'      => 'shortcode_catalog_ordering',
                'category'    => __('Store & archive', 'woo4etch'),
                'attributes'  => '—',
                'description' => __('Catalog sort-by dropdown (archive/shop loop).', 'woo4etch'),
                'example'     => '[woo_catalog_ordering]',
            ],
            'woo_pagination' => [
                'method'      => 'shortcode_pagination',
                'category'    => __('Store & archive', 'woo4etch'),
                'attributes'  => '—',
                'description' => __('Product loop pagination (archive/shop). Fills the gap Etch loops leave open.', 'woo4etch'),
                'example'     => '[woo_pagination]',
            ],
            'woo_notices' => [
                'method'      => 'shortcode_notices',
                'category'    => __('Store & archive', 'woo4etch'),
                'attributes'  => 'format',
                'description' => __('Queued WooCommerce notices (cart, checkout). format="plain" renders minimal class-based markup (.w4e-notice .w4e-notice--error/success/notice) styleable in Etch instead of Woo\'s template markup.', 'woo4etch'),
                'example'     => '[woo_notices format="plain"]',
            ],

            /* ---- Conditional ---- */
            'woo_if' => [
                'method'      => 'shortcode_if',
                'category'    => __('Conditional', 'woo4etch'),
                'attributes'  => 'cond (required, prefix ! to negate), arg, id',
                'description' => __('Renders inner content only when a WooCommerce conditional is true (is_product, is_cart, on_sale, in_stock, …).', 'woo4etch'),
                'example'     => '[woo_if cond="is_product"]…[/woo_if]',
            ],

            /* ---- Templates ---- */
            'woo_template' => [
                'method'      => 'shortcode_template',
                'category'    => __('Templates', 'woo4etch'),
                'attributes'  => 'name (required)',
                'description' => __('Loads a WooCommerce template part by path.', 'woo4etch'),
                'example'     => '[woo_template name="single-product/related"]',
            ],

            /* ---- Cart (extended) ---- */
            'woo_cart_items' => [
                'method'      => 'shortcode_cart_items',
                'category'    => __('Cart', 'woo4etch'),
                'attributes'  => 'thumbnail_size',
                'description' => __('Complete cart form with your own class-based markup: items, coupon, quantity update + remove, and every WooCommerce cart hook/filter (extension-compatible). The customisable alternative to [woocommerce_cart]. No AJAX required.', 'woo4etch'),
                'example'     => '[woo_cart_items]',
            ],
            'woo_cart_totals' => [
                'method'      => 'shortcode_cart_totals',
                'category'    => __('Cart', 'woo4etch'),
                'attributes'  => '—',
                'description' => __('Cart totals block (subtotal, shipping, total).', 'woo4etch'),
                'example'     => '[woo_cart_totals]',
            ],
            'woo_coupon_form' => [
                'method'      => 'shortcode_coupon_form',
                'category'    => __('Cart', 'woo4etch'),
                'attributes'  => '—',
                'description' => __('“Have a coupon?” toggle + apply-coupon form.', 'woo4etch'),
                'example'     => '[woo_coupon_form]',
            ],
            'woo_shipping_calculator' => [
                'method'      => 'shortcode_shipping_calculator',
                'category'    => __('Cart', 'woo4etch'),
                'attributes'  => '—',
                'description' => __('Cart shipping calculator (country/state/postcode).', 'woo4etch'),
                'example'     => '[woo_shipping_calculator]',
            ],
            'woo_cross_sells' => [
                'method'      => 'shortcode_cross_sells',
                'category'    => __('Cart', 'woo4etch'),
                'attributes'  => '—',
                'description' => __('Cross-sell products block for the current cart.', 'woo4etch'),
                'example'     => '[woo_cross_sells]',
            ],

            /* ---- Account (extended) ---- */
            'woo_account_menu' => [
                'method'      => 'shortcode_account_menu',
                'category'    => __('Account', 'woo4etch'),
                'attributes'  => '—',
                'description' => __('My Account navigation menu (dashboard, orders, addresses, logout …).', 'woo4etch'),
                'example'     => '[woo_account_menu]',
            ],
            'woo_account_content' => [
                'method'      => 'shortcode_account_content',
                'category'    => __('Account', 'woo4etch'),
                'attributes'  => '—',
                'description' => __('Content of the current My Account endpoint (dashboard, orders, downloads, edit-account, view-order …).', 'woo4etch'),
                'example'     => '[woo_account_content]',
            ],
            'woo_login_form' => [
                'method'      => 'shortcode_login_form',
                'category'    => __('Account', 'woo4etch'),
                'attributes'  => 'message, redirect, hidden (yes|no)',
                'description' => __('WooCommerce login form (My Account / checkout).', 'woo4etch'),
                'example'     => '[woo_login_form]',
            ],
            'woo_order_details' => [
                'method'      => 'shortcode_order_details',
                'category'    => __('Account', 'woo4etch'),
                'attributes'  => 'order_id, key',
                'description' => __('Order details table (thank-you / view-order). Falls back to the current order; explicit order_id needs ownership, the order key, or shop-manager rights.', 'woo4etch'),
                'example'     => '[woo_order_details]',
            ],
            'woo_checkout_block' => [
                'method'      => 'shortcode_checkout_block',
                'category'    => __('Cart', 'woo4etch'),
                'attributes'  => '—',
                'description' => __('Embeds WooCommerce\'s native Checkout BLOCK inside an Etch layout: full native protections (card-testing rate limiting via WooCommerce → Settings → Advanced → Features) and every gateway\'s official client integration. Trade-off: the markup inside the block is WooCommerce\'s — customize via the Additional Checkout Fields API, block attributes and CSS, while Etch owns everything around it. The classic [woocommerce_checkout] remains the full-markup-control route.', 'woo4etch'),
                'example'     => '[woo_checkout_block]',
            ],

            /* ---- Store & archive (extended) ---- */
            'woo_product_search' => [
                'method'      => 'shortcode_product_search',
                'category'    => __('Store & archive', 'woo4etch'),
                'attributes'  => '—',
                'description' => __('Product search form (searches products only).', 'woo4etch'),
                'example'     => '[woo_product_search]',
            ],

            /* ---- WooCommerce core (built-in — registered by WooCommerce, listed here for reference) ---- */
            'woocommerce_cart' => [
                'method'      => '',
                'native'      => true,
                'category'    => __('WooCommerce core (built-in)', 'woo4etch'),
                'attributes'  => '—',
                'description' => __('Full cart page. Place on the page assigned as Cart.', 'woo4etch'),
                'example'     => '[woocommerce_cart]',
            ],
            'woocommerce_checkout' => [
                'method'      => '',
                'native'      => true,
                'category'    => __('WooCommerce core (built-in)', 'woo4etch'),
                'attributes'  => '—',
                'description' => __('Full checkout. Place on the page assigned as Checkout.', 'woo4etch'),
                'example'     => '[woocommerce_checkout]',
            ],
            'woocommerce_my_account' => [
                'method'      => '',
                'native'      => true,
                'category'    => __('WooCommerce core (built-in)', 'woo4etch'),
                'attributes'  => '—',
                'description' => __('My Account dashboard, orders, addresses, downloads.', 'woo4etch'),
                'example'     => '[woocommerce_my_account]',
            ],
            'woocommerce_order_tracking' => [
                'method'      => '',
                'native'      => true,
                'category'    => __('WooCommerce core (built-in)', 'woo4etch'),
                'attributes'  => '—',
                'description' => __('Order tracking form (order ID + email).', 'woo4etch'),
                'example'     => '[woocommerce_order_tracking]',
            ],
            'products' => [
                'method'      => '',
                'native'      => true,
                'category'    => __('WooCommerce core (built-in)', 'woo4etch'),
                'attributes'  => 'limit, columns, category, on_sale, best_selling, orderby, ids, …',
                'description' => __('Product grid by query. Etch loops are usually a better fit for custom markup.', 'woo4etch'),
                'example'     => '[products limit="4" columns="4" on_sale="true"]',
            ],
            'product_page' => [
                'method'      => '',
                'native'      => true,
                'category'    => __('WooCommerce core (built-in)', 'woo4etch'),
                'attributes'  => 'id or sku',
                'description' => __('Renders a full single-product page for one product.', 'woo4etch'),
                'example'     => '[product_page id="99"]',
            ],
            'product_categories' => [
                'method'      => '',
                'native'      => true,
                'category'    => __('WooCommerce core (built-in)', 'woo4etch'),
                'attributes'  => 'number, parent, orderby, columns, ids, …',
                'description' => __('Grid of product categories.', 'woo4etch'),
                'example'     => '[product_categories number="12" parent="0"]',
            ],
            'add_to_cart' => [
                'method'      => '',
                'native'      => true,
                'category'    => __('WooCommerce core (built-in)', 'woo4etch'),
                'attributes'  => 'id or sku, style, show_price, quantity',
                'description' => __('Price + add-to-cart button for one product (Woo core; Woo4Etch’s [woo_add_to_cart] is context-aware).', 'woo4etch'),
                'example'     => '[add_to_cart id="99"]',
            ],
            'add_to_cart_url' => [
                'method'      => '',
                'native'      => true,
                'category'    => __('WooCommerce core (built-in)', 'woo4etch'),
                'attributes'  => 'id or sku',
                'description' => __('Raw add-to-cart URL for one product (Woo core).', 'woo4etch'),
                'example'     => '[add_to_cart_url id="99"]',
            ],
            'shop_messages' => [
                'method'      => '',
                'native'      => true,
                'category'    => __('WooCommerce core (built-in)', 'woo4etch'),
                'attributes'  => '—',
                'description' => __('Outputs queued Woo notices (Woo core equivalent of [woo_notices]).', 'woo4etch'),
                'example'     => '[shop_messages]',
            ],
        ]);
    }

    /* ============================================================
       Frontend behaviours (no markup — they only support your Etch layout)
       ============================================================ */

    /**
     * Register the frontend behaviours: optional Woo-CSS removal (admin
     * checkbox), the buy-now → checkout redirect, and the variation-swatch
     * sync script. None of these output markup; the layout stays yours.
     */
    private static function register_frontend_features() {
        // Drop WooCommerce's three stylesheets so Etch styles start from a
        // blank slate. Driven by the admin checkbox (Woo4Etch → Settings);
        // the filter wins over the option either way:
        //   add_filter('woo4etch/disable_woo_styles', '__return_true');
        $settings = (array) get_option('woo4etch_settings', []);
        if (apply_filters('woo4etch/disable_woo_styles', !empty($settings['disable_woo_styles']))) {
            add_filter('woocommerce_enqueue_styles', '__return_empty_array', 20);
        }

        // Buy-now flow: a second submit button inside form.cart —
        //   <button type="submit" name="buy_now" value="1" class="single_add_to_cart_button">Buy now</button>
        // WooCommerce adds the product as usual; we only redirect to checkout.
        // Disable: add_filter('woo4etch/enable_buy_now', '__return_false');
        if (apply_filters('woo4etch/enable_buy_now', true)) {
            add_filter('woocommerce_add_to_cart_redirect', [__CLASS__, 'buy_now_redirect'], 20);
            add_action('wp_loaded', [__CLASS__, 'buy_now_maybe_empty_cart'], 15);
        }

        add_action('wp_enqueue_scripts', [__CLASS__, 'enqueue_swatches_script']);

        // Price-filter slider: dual-handle range enhancement for min_price/
        // max_price forms on shop archives (assets/price-slider.js).
        // Disable: add_filter('woo4etch/enqueue_price_slider', '__return_false');
        add_action('wp_enqueue_scripts', [__CLASS__, 'enqueue_price_slider_script']);

        // Store API cart layer (issue #25): cart writes (quantity, remove,
        // coupons, add-to-cart) go through /wc/store/v1/cart — Woo's native
        // validation + Store API rate limiting — while the page re-renders
        // its own server-side Etch HTML (region swap, markup stays yours).
        // On by default; Settings checkbox / filter to disable:
        //   add_filter('woo4etch/enqueue_store_api', '__return_false');
        add_action('wp_enqueue_scripts', [__CLASS__, 'enqueue_store_api_script']);
        add_action('wp_enqueue_scripts', [__CLASS__, 'enqueue_etch_hub_templates']);
        // Late (100): must run AFTER WooCommerce enqueued wc-checkout — at the
        // same priority the dequeue is a no-op depending on plugin load order.
        add_action('wp_enqueue_scripts', [__CLASS__, 'dequeue_classic_checkout_js'], 100);

        // Opt-in rate limit for the CLASSIC checkout (issue #24): WooCommerce's
        // native card-testing rate limiting (Advanced → Features) only covers
        // the Checkout block's Store API path — ?wc-ajax=checkout, which the
        // Etch shortcode checkout uses, has no native protection at all.
        // Enable via the Settings checkbox or the woo4etch/checkout_rate_limit
        // filter; defaults mirror Woo's block limits (3 attempts / 60 s,
        // fingerprinted by IP + user agent + accept-language).
        add_action('woocommerce_after_checkout_validation', [__CLASS__, 'checkout_rate_limit'], 10, 2);

        // Optional zero-markup variation UX: native attribute <select>s become
        // pill buttons, .quantity inputs get a −/+ stepper (assets/pills.js).
        // Driven by the admin checkbox (Woo4Etch → Settings) or the filter:
        //   add_filter('woo4etch/enqueue_pills', '__return_true');
        add_action('wp_enqueue_scripts', [__CLASS__, 'enqueue_pills_script']);

        // Woo's gallery JS (zoom/lightbox/slider) never loads on block themes,
        // even with the wc-product-gallery-* supports declared — WooCommerce
        // only enqueues the bundle for classic themes. Close that gap.
        add_action('wp_enqueue_scripts', [__CLASS__, 'enqueue_gallery_scripts'], 20);

        // Same gap for variable products: Woo enqueues wc-add-to-cart-variation
        // from its own add-to-cart template, which hand-built Etch forms never
        // render. Without it, selecting a variation does nothing.
        // Disable: add_filter('woo4etch/enqueue_variation_script', '__return_false');
        add_action('wp_enqueue_scripts', [__CLASS__, 'enqueue_variation_script'], 20);

        // WooCommerce's block-template compatibility layer strips the
        // callbacks from the classic product/shop hooks on block themes and
        // re-injects them around woocommerce/* blocks — which hand-written
        // Etch layouts don't contain, so third-party hook output (Germanized
        // legal info, trust badges, …) silently disappears even when fired
        // via [do_action] or data-w4e-hook. Disable the layer: Etch layouts
        // fire their hooks explicitly where they want them.
        // Re-enable: add_filter('woo4etch/disable_block_hook_compatibility', '__return_false');
        if (apply_filters('woo4etch/disable_block_hook_compatibility', true)) {
            add_filter('woocommerce_disable_compatibility_layer', '__return_true');
        }

        // Server-side embed markers, filled AFTER Etch renders the block —
        // Etch's raw-html sanitizer strips <form>/<input>/<select>/<script>
        // unless the global "allow unsafe raw HTML" setting is on (off by
        // default), so shortcodes in raw-html blocks lose exactly the markup
        // forms and many third-party hooks emit. The markers sidestep the
        // sanitizer without touching Etch security settings:
        //   <div data-w4e-add-to-cart="{this.id}"></div>          → native add-to-cart form
        //   <div data-w4e-hook="hook_name" data-w4e-product="{this.id}"></div> → do_action() output
        add_filter('render_block', [__CLASS__, 'render_etch_placeholders'], 20);
    }

    /**
     * Send buy-now submissions straight to checkout after the add-to-cart.
     *
     * @param string|false $url Redirect URL WooCommerce decided on.
     * @return string|false
     */
    public static function buy_now_redirect($url) {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Woo's own add-to-cart flow has no nonce.
        if (!empty($_REQUEST['buy_now']) && function_exists('wc_get_checkout_url')) {
            return wc_get_checkout_url();
        }
        return $url;
    }

    /**
     * Optionally start buy-now with an empty cart (true one-click checkout).
     * Off by default — opt in: add_filter('woo4etch/buy_now_empty_cart', '__return_true');
     *
     * Hooks into woocommerce_add_to_cart (fires only after a *successful* add),
     * so the existing cart is only cleared when the product was actually added —
     * not when WooCommerce rejects the request (e.g. variable product without a
     * chosen variation). The just-added item is kept; all other items are removed.
     */
    public static function buy_now_maybe_empty_cart() {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Woo's own add-to-cart flow has no nonce.
        if (empty($_REQUEST['buy_now']) || empty($_REQUEST['add-to-cart'])) {
            return;
        }
        if (apply_filters('woo4etch/buy_now_empty_cart', false)) {
            add_action('woocommerce_add_to_cart', static function ($cart_item_key) {
                if (!WC()->cart) {
                    return;
                }
                foreach (array_keys(WC()->cart->get_cart()) as $key) {
                    if ($key !== $cart_item_key) {
                        WC()->cart->remove_cart_item($key);
                    }
                }
            }, 10, 1);
        }
    }

    /**
     * Variation-swatch sync script: clicks on [data-w4e-swatch] elements set
     * the matching attribute <select> inside form.variations_form and trigger
     * Woo's variation JS. The swatch markup itself is built in Etch (loop over
     * the attribute terms) — the script only bridges clicks to the hidden select.
     * Scope/disable: add_filter('woo4etch/enqueue_swatches', '__return_false');
     */
    public static function enqueue_swatches_script() {
        $enqueue = function_exists('is_product') && is_product();
        if (!apply_filters('woo4etch/enqueue_swatches', $enqueue)) {
            return;
        }
        wp_enqueue_script(
            'woo4etch-swatches',
            plugins_url('assets/swatches.js', __FILE__),
            [],
            self::VERSION,
            true
        );
    }

    /**
     * Store API cart layer (assets/store-api.js): progressive enhancement
     * that moves cart WRITES onto WooCommerce's Store API while the reads
     * stay server-rendered Etch HTML (region swap) — see the asset header
     * for the architecture. Enqueued site-wide (the mini-cart lives in the
     * header); the script is a few KB and no-ops without matching markup.
     *
     * Default ON (a store on the newest Woo API is the point — issue #25);
     * disable via the Settings checkbox or:
     *   add_filter('woo4etch/enqueue_store_api', '__return_false');
     */
    /**
     * A+ checkout page: WooCommerce enqueues wc-checkout.js on is_checkout()
     * regardless of the page content. Its form-level submit handler
     * `return false`s (preventDefault + stopPropagation) and re-posts via the
     * classic ?wc-ajax=checkout — hijacking the Store API layer's submit.
     * When the assigned checkout page carries the data-w4e-checkout marker,
     * the classic checkout scripts have no job here: dequeue them. Hooked
     * LATE (priority 100) so WooCommerce has already enqueued the handle.
     */
    /**
     * Gateways the Store API checkout may place orders through — redirect/
     * offline flows only. Shared by the frontend module (interception
     * allowlist) and the checkout bridge (payment_methods filtering):
     * inline-tokenizing gateways (Stripe Elements & co.) can't work in a
     * hand-built form — they need their client JS + payment_fields markup —
     * so offering them there would sell a broken option. `*` = prefix match.
     *
     * @return array<int,string>
     */
    public static function store_api_checkout_gateways() {
        return (array) apply_filters('woo4etch/store_api_checkout_gateways', [
            'bacs',
            'cheque',
            'cod',
            'invoice',
            'mollie_wc_gateway_*',
        ]);
    }

    /**
     * Does a gateway id match the Store API checkout allowlist?
     *
     * @param string $id Gateway id.
     * @return bool
     */
    private static function gateway_allowlisted($id) {
        foreach (self::store_api_checkout_gateways() as $pattern) {
            if (substr($pattern, -1) === '*'
                ? strpos($id, substr($pattern, 0, -1)) === 0
                : $id === $pattern) {
                return true;
            }
        }
        return false;
    }

    /**
     * Surface WooCommerce templates inside Etch's template hub (builder
     * shell only). Etch's hub renders only slugs its catalog knows —
     * Woo-registered templates (order-confirmation, coming-soon, …) exist in
     * the REST list but are never displayed, and types that have no
     * wp_template post yet aren't offered at all.
     *
     * The bridge script appends a "WooCommerce" group covering both cases:
     * existing templates get Etch's own ?etch=magic&post_id= deep link,
     * missing ones a nonce-protected link that creates the template and then
     * opens it in the builder. This is the only place these templates are
     * managed — there is no wp-admin duplicate.
     *
     * Disable: woo4etch/etch_hub_templates filter.
     */
    public static function enqueue_etch_hub_templates() {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        if (!isset($_GET['etch']) || 'magic' !== $_GET['etch'] || !current_user_can('manage_options')) {
            return;
        }
        if (!class_exists('Woo4Etch_Health') || !apply_filters('woo4etch/etch_hub_templates', true)) {
            return;
        }

        $items = [];
        foreach (Woo4Etch_Health::wc_templates() as $slug => $meta) {
            // 'hub' => false keeps a template out of the group — the page
            // frames, which exist for WooCommerce's sake and are rarely
            // edited. Opt one back in via the woo4etch/wc_templates filter.
            if (isset($meta['hub']) && false === $meta['hub']) {
                continue;
            }
            $post = Woo4Etch_Health::find_template($slug);
            $items[] = [
                'slug'   => $slug,
                'name'   => $meta['name'],
                'exists' => (bool) $post,
                // Built with add_query_arg, not wp_nonce_url: the latter runs
                // the URL through esc_html, and the resulting &amp; would be
                // taken literally when the script assigns location.href.
                'url'    => $post
                    ? add_query_arg(['etch' => 'magic', 'post_id' => $post->ID], home_url('/'))
                    : add_query_arg(
                        [
                            'action'   => 'woo4etch_materialize_template',
                            'template' => $slug,
                            '_wpnonce' => wp_create_nonce('woo4etch_materialize_' . $slug),
                        ],
                        admin_url('admin-post.php')
                    ),
            ];
        }
        if (!$items) {
            return;
        }

        wp_enqueue_script(
            'woo4etch-etch-hub-templates',
            plugins_url('assets/etch-hub-templates.js', __FILE__),
            [],
            self::VERSION,
            true
        );
        wp_localize_script('woo4etch-etch-hub-templates', 'w4eHubTemplates', [
            'groupLabel'  => __('WooCommerce', 'woo4etch'),
            'editLabel'   => __('Edit', 'woo4etch'),
            'createLabel' => __('Create', 'woo4etch'),
            'items'       => $items,
        ]);
    }

    public static function dequeue_classic_checkout_js() {
        if (!function_exists('is_checkout') || !is_checkout() || is_wc_endpoint_url()) {
            return;
        }
        $page = get_post(wc_get_page_id('checkout'));
        if ($page && strpos((string) $page->post_content, 'data-w4e-checkout') !== false
            && apply_filters('woo4etch/dequeue_classic_checkout_js', true)) {
            wp_dequeue_script('wc-checkout');
        }
    }

    public static function enqueue_store_api_script() {
        $settings = (array) get_option('woo4etch_settings', []);
        $enqueue  = !isset($settings['store_api_cart']) || !empty($settings['store_api_cart']);
        if (!apply_filters('woo4etch/enqueue_store_api', $enqueue) || !function_exists('WC')) {
            return;
        }

        wp_enqueue_script(
            'woo4etch-store-api',
            plugins_url('assets/store-api.js', __FILE__),
            [],
            self::VERSION,
            true
        );
        wp_localize_script('woo4etch-store-api', 'w4eStoreApi', [
            'restUrl' => esc_url_raw(rest_url('wc/store/v1')),
            'nonce'   => wp_create_nonce('wc_store_api'),
            /*
             * Store API checkout (issue #27): gateways safe to place the order
             * through POST /wc/store/v1/checkout — redirect/offline flows whose
             * payment_result carries a redirect_url. Anything needing client-side
             * tokenization (inline card fields) is NOT listed and keeps the
             * classic submit. `*` suffix = prefix match.
             */
            'checkoutGateways' => self::store_api_checkout_gateways(),
            /*
             * Germanized guard: its Store API checkbox validation is skipped
             * entirely when the request lacks the extensions key — the
             * checkout module therefore ALWAYS sends it while Germanized is
             * active (verified against the plugin source).
             */
            'gzd'     => class_exists('WC_GZD_Legal_Checkbox_Manager'),
            'i18n'    => [
                'added'         => __('Added to your cart.', 'woo4etch'),
                'couponApplied' => __('Coupon code applied successfully.', 'woo4etch'),
                'error'         => __('Something went wrong. Please try again.', 'woo4etch'),
            ],
        ]);
    }

    /**
     * [woo_checkout_block] — embed WooCommerce's native Checkout BLOCK inside
     * an Etch layout (issue #26, spike-verified). The bare self-closing block
     * renders empty (it is a container whose render() outputs the inner
     * blocks), so the full inner tree is resolved from, in order:
     *
     * 1. the WooCommerce-assigned checkout page, when it carries the block
     *    (keeps any block-attribute customization the user made there),
     * 2. WooCommerce's own default checkout block content (WC_Install).
     *
     * Verified in wp-env: the block hydrates on any page (its render()
     * enqueues the scripts), a full purchase completes, and the native
     * card-testing rate limiting (Advanced → Features) engages on the
     * Store API endpoint the block posts to — 3 attempts / 60 s with
     * RateLimit-* headers, admin users exempt by Woo's own design.
     */
    public static function shortcode_checkout_block() {
        if (!function_exists('WC') || !class_exists('WC_Install')) {
            return '';
        }

        $content = '';
        $page    = get_post(wc_get_page_id('checkout'));
        if ($page && has_block('woocommerce/checkout', $page)) {
            foreach (parse_blocks($page->post_content) as $block) {
                if ('woocommerce/checkout' === ($block['blockName'] ?? '')) {
                    $content = serialize_block($block);
                    break;
                }
            }
        }
        if ('' === $content) {
            try {
                $method = new ReflectionMethod('WC_Install', 'get_checkout_block_content');
                $method->setAccessible(true);
                $content = (string) $method->invoke(null);
            } catch (ReflectionException $e) {
                $content = '<!-- wp:woocommerce/checkout /-->';
            }
        }

        return do_blocks($content);
    }

    /**
     * Rate-limit place-order attempts on the classic checkout (issue #24).
     * Hooked to woocommerce_after_checkout_validation — every submit counts,
     * and once the fingerprint exceeds the limit inside the window the order
     * is rejected with a checkout error notice. Defaults mirror the Checkout
     * block's native protection (3 / 60 s). Off by default; enable via the
     * Settings checkbox or override everything via the filter:
     *
     *   add_filter('woo4etch/checkout_rate_limit', function ($o) {
     *       $o['enabled'] = true; $o['limit'] = 5; $o['window'] = 120;
     *       return $o;
     *   });
     *
     * @param array    $data   Posted checkout data (unused).
     * @param WP_Error $errors Checkout validation errors.
     */
    public static function checkout_rate_limit($data, $errors) {
        $settings = (array) get_option('woo4etch_settings', []);
        $opts     = apply_filters('woo4etch/checkout_rate_limit', [
            'enabled' => !empty($settings['checkout_rate_limit']),
            'limit'   => 3,
            'window'  => 60,
        ]);
        if (empty($opts['enabled']) || !$errors instanceof WP_Error) {
            return;
        }
        if (self::checkout_rate_limit_exceeded((int) $opts['limit'], (int) $opts['window'])) {
            $errors->add(
                'woo4etch_rate_limited',
                __('Too many checkout attempts. Please wait a minute and try again.', 'woo4etch')
            );
        }
    }

    /**
     * Sliding-window attempt counter per client fingerprint (proxy-aware IP +
     * user agent + accept-language — the same grouping WooCommerce's Store
     * API rate limiting uses, so attackers rotating IPs alone are still
     * grouped by the rest of the fingerprint).
     *
     * Public so the integration tests can exercise the window semantics.
     *
     * @param int $limit  Allowed attempts inside the window.
     * @param int $window Window in seconds.
     * @return bool True when this attempt exceeds the limit.
     */
    public static function checkout_rate_limit_exceeded($limit, $window) {
        $ip = class_exists('WC_Geolocation')
            ? WC_Geolocation::get_ip_address()
            : (isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'])) : '');
        $fingerprint = md5(
            $ip
            . '|' . (isset($_SERVER['HTTP_USER_AGENT']) ? sanitize_text_field(wp_unslash($_SERVER['HTTP_USER_AGENT'])) : '')
            . '|' . (isset($_SERVER['HTTP_ACCEPT_LANGUAGE']) ? sanitize_text_field(wp_unslash($_SERVER['HTTP_ACCEPT_LANGUAGE'])) : '')
        );
        $key  = 'w4e_rl_' . $fingerprint;
        $now  = time();
        $hits = get_transient($key);
        $hits = is_array($hits) ? array_values(array_filter($hits, static function ($t) use ($now, $window) {
            return (int) $t > $now - $window;
        })) : [];

        $exceeded = count($hits) >= $limit;
        if (!$exceeded) {
            $hits[] = $now;
        }
        set_transient($key, $hits, max(1, (int) $window));

        return $exceeded;
    }

    /**
     * Price-filter slider (assets/price-slider.js): dual-handle range slider
     * for forms carrying min_price/max_price inputs — WooCommerce's native
     * price filter params. Enqueued on product archives; the script no-ops
     * when no such form is on the page.
     */
    public static function enqueue_price_slider_script() {
        $enqueue = (function_exists('is_shop') && is_shop())
            || (function_exists('is_product_taxonomy') && is_product_taxonomy());
        if (!apply_filters('woo4etch/enqueue_price_slider', $enqueue)) {
            return;
        }
        wp_enqueue_script(
            'woo4etch-price-slider',
            plugins_url('assets/price-slider.js', __FILE__),
            [],
            self::VERSION,
            true
        );
        wp_localize_script('woo4etch-price-slider', 'w4ePriceSliderI18n', [
            'min' => __('Minimum price', 'woo4etch'),
            'max' => __('Maximum price', 'woo4etch'),
            // See enqueue_pills_script(): styles live as Etch records.
            'stylesInEtch' => defined('ETCH_PLUGIN_FILE'),
        ]);
    }

    /**
     * Pills widget (assets/pills.js): progressive enhancement that turns the
     * native attribute <select>s into pill buttons and wraps .quantity inputs
     * in a −/+ stepper. Woo's variation JS stays leading — a pill click sets
     * the select and dispatches its change event. The inverse of swatches.js
     * (hand-built markup → native select); both bridge into the same event.
     *
     * Off by default; enable per checkbox (Woo4Etch → Settings) or filter:
     *   add_filter('woo4etch/enqueue_pills', '__return_true');
     */
    public static function enqueue_pills_script() {
        $settings = (array) get_option('woo4etch_settings', []);
        $enqueue  = !empty($settings['enable_pills'])
            && function_exists('is_product')
            && (is_product() || (function_exists('is_cart') && is_cart()));
        if (!apply_filters('woo4etch/enqueue_pills', $enqueue)) {
            return;
        }
        wp_enqueue_script(
            'woo4etch-pills',
            plugins_url('assets/pills.js', __FILE__),
            [],
            self::VERSION,
            true
        );
        wp_localize_script('woo4etch-pills', 'w4ePillsI18n', [
            /* translators: %s: attribute label (e.g. "Size") */
            'selectLabel' => __('Choose %s', 'woo4etch'),
            'decrease'    => __('Decrease quantity', 'woo4etch'),
            'increase'    => __('Increase quantity', 'woo4etch'),
            // With Etch active the widget styles live as Etch class records
            // (nested in the layouts' records — editable in the builder);
            // the script then must NOT inject its fallback stylesheet.
            'stylesInEtch' => defined('ETCH_PLUGIN_FILE'),
        ]);
    }

    /**
     * Enqueue WooCommerce's own gallery scripts (hover zoom, PhotoSwipe
     * lightbox, FlexSlider thumbnail slider) on single product pages.
     *
     * The wc-product-gallery-* theme supports alone do nothing with a block
     * theme: WC_Frontend_Scripts gates the whole gallery bundle behind
     * `is_product() && ! wp_is_block_theme()`. The handles are always
     * *registered* though, so we enqueue them ourselves — WooCommerce then
     * localizes `wc_single_product_params` for every queued handle, and
     * single-product.js initialises on `.woocommerce-product-gallery`.
     */
    public static function enqueue_gallery_scripts() {
        if (!function_exists('is_product') || !is_product()) {
            return;
        }
        if (!wp_is_block_theme()) {
            return; // Classic theme: WooCommerce enqueues the bundle itself.
        }
        self::enqueue_gallery_assets();
    }

    /**
     * Fill empty `data-w4e-*` marker elements after block rendering, so
     * Etch's raw-html sanitizer never sees the generated markup:
     *
     *   data-w4e-add-to-cart="<id>" — WooCommerce's native add-to-cart form
     *     (simple, variable, grouped, external).
     *   data-w4e-hook="<hook>" [data-w4e-product="<id>"] — captured
     *     do_action() output: the kses-proof equivalent of [do_action].
     *     Restricted by the same woo4etch/allow_do_action filter; the
     *     optional product id sets the global $product for the hook.
     *
     * @param string $content Rendered block HTML.
     * @return string
     */
    public static function render_etch_placeholders($content) {
        if (!is_string($content) || strpos($content, 'data-w4e-') === false) {
            return $content;
        }

        if (strpos($content, 'data-w4e-add-to-cart') !== false && function_exists('wc_get_product')) {
            $content = preg_replace_callback(
                '/<(div|span)([^>]*)\sdata-w4e-add-to-cart="(\d+)"([^>]*)>\s*<\/\1>/',
                static function ($m) {
                    $form = self::shortcode_add_to_cart(['id' => (int) $m[3]]);
                    return '<' . $m[1] . $m[2] . ' data-w4e-add-to-cart="' . $m[3] . '"' . $m[4] . '>' . $form . '</' . $m[1] . '>';
                },
                $content
            );
        }

        if (strpos($content, 'data-w4e-hook') !== false) {
            $content = preg_replace_callback(
                '/<(div|span)([^>]*)\sdata-w4e-hook="([a-z0-9_\-]+)"([^>]*)>\s*<\/\1>/i',
                static function ($m) {
                    $hook = sanitize_key($m[3]);
                    if (!$hook || !apply_filters('woo4etch/allow_do_action', true, $hook)) {
                        return $m[0];
                    }

                    $extra_attrs = $m[2] . $m[4];

                    $product = null;
                    if (function_exists('wc_get_product') && preg_match('/data-w4e-product="(\d+)"/', $extra_attrs, $pm)) {
                        $maybe   = wc_get_product((int) $pm[1]);
                        $product = $maybe instanceof WC_Product ? $maybe : null;
                    }

                    // data-w4e-skip-defaults: temporarily unhook WooCommerce
                    // core's own template callbacks so the hook renders ONLY
                    // what third parties added. Essential for hooks like
                    // woocommerce_single_product_summary, where core would
                    // duplicate the layout's title/price/excerpt/form but
                    // plugins (e.g. Germanized: unit price, tax notice,
                    // delivery time) attach their extras between them.
                    $removed = [];
                    if (strpos($extra_attrs, 'data-w4e-skip-defaults') !== false) {
                        $defaults = apply_filters('woo4etch/hook_core_defaults', self::CORE_HOOK_DEFAULTS, $hook);
                        foreach (isset($defaults[$hook]) ? $defaults[$hook] : [] as $cb) {
                            if (remove_action($hook, $cb[0], $cb[1])) {
                                $removed[] = $cb;
                            }
                        }
                    }

                    if ($product) {
                        $out = self::with_product($product, static function () use ($hook) {
                            do_action($hook);
                        });
                    } else {
                        ob_start();
                        do_action($hook);
                        $out = ob_get_clean();
                    }

                    foreach ($removed as $cb) {
                        add_action($hook, $cb[0], $cb[1]);
                    }

                    return '<' . $m[1] . $m[2] . ' data-w4e-hook="' . esc_attr($hook) . '"' . $m[4] . '>' . $out . '</' . $m[1] . '>';
                },
                $content
            );
        }

        return $content;
    }

    /**
     * WooCommerce core template callbacks per hook — the ones
     * data-w4e-skip-defaults unhooks (the layout already renders that content
     * itself). Third-party callbacks on the same hooks are untouched.
     * Filterable: woo4etch/hook_core_defaults.
     */
    const CORE_HOOK_DEFAULTS = [
        'woocommerce_single_product_summary' => [
            ['woocommerce_template_single_title', 5],
            ['woocommerce_template_single_rating', 10],
            ['woocommerce_template_single_price', 10],
            ['woocommerce_template_single_excerpt', 20],
            ['woocommerce_template_single_add_to_cart', 30],
            ['woocommerce_template_single_meta', 40],
            ['woocommerce_template_single_sharing', 50],
        ],
        'woocommerce_before_main_content' => [
            ['woocommerce_output_content_wrapper', 10],
            ['woocommerce_breadcrumb', 20],
        ],
        'woocommerce_after_main_content' => [
            ['woocommerce_output_content_wrapper_end', 10],
        ],
        'woocommerce_before_shop_loop' => [
            ['woocommerce_output_all_notices', 10],
            ['woocommerce_result_count', 20],
            ['woocommerce_catalog_ordering', 30],
        ],
    ];

    /**
     * Enqueue Woo's variation script on variable-product pages so hand-built
     * `form.variations_form` markup works (price/stock update, variation_id).
     */
    public static function enqueue_variation_script() {
        $enqueue = function_exists('is_product') && is_product();
        if ($enqueue) {
            $product = wc_get_product(get_queried_object_id());
            $enqueue = $product instanceof WC_Product && $product->is_type('variable');
        }
        if (!apply_filters('woo4etch/enqueue_variation_script', $enqueue)) {
            return;
        }
        if (wp_script_is('wc-add-to-cart-variation', 'registered')) {
            wp_enqueue_script('wc-add-to-cart-variation');
        }
    }

    /**
     * Enqueue whichever Woo gallery features are theme-supported.
     * Shared by the wp_enqueue_scripts hook and [woo_gallery mode="woo"].
     */
    private static function enqueue_gallery_assets() {
        $zoom     = current_theme_supports('wc-product-gallery-zoom');
        $lightbox = current_theme_supports('wc-product-gallery-lightbox');
        $slider   = current_theme_supports('wc-product-gallery-slider');

        if (!$zoom && !$lightbox && !$slider) {
            return;
        }

        if ($zoom && wp_script_is('zoom', 'registered')) {
            wp_enqueue_script('zoom');
        }
        if ($slider && wp_script_is('flexslider', 'registered')) {
            wp_enqueue_script('flexslider');
        }
        if ($lightbox && wp_script_is('photoswipe-ui-default', 'registered')) {
            wp_enqueue_script('photoswipe-ui-default');
            wp_enqueue_style('photoswipe-default-skin');
            // Prints the .pswp lightbox root markup PhotoSwipe opens into.
            if (function_exists('woocommerce_photoswipe') && !has_action('wp_footer', 'woocommerce_photoswipe')) {
                add_action('wp_footer', 'woocommerce_photoswipe', 15);
            }
        }
        if (wp_script_is('wc-single-product', 'registered')) {
            wp_enqueue_script('wc-single-product');
        }

        // No companion stylesheet here on purpose: the gallery styling
        // (FlexSlider viewport, thumbnail grid, lightbox trigger, the
        // opacity/collapse guards) ships as an Etch class record — nested in
        // the ready-made layout's .w4e-gal record, visible and editable in
        // the builder's style panel. A plugin stylesheet would sit outside
        // Etch and fight the user's class edits. For hand-written galleries
        // outside the layout, copy the documented CSS onto your own gallery
        // class in Etch (templates/01, gallery variant).
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

    /**
     * Full attributes table — WooCommerce's "Additional information" tab as a
     * plain table (visible attributes plus weight/dimensions when enabled).
     * Returns an empty string when the product has nothing to show, so a
     * surrounding heading can be shipped conditionally by the layout.
     *
     * Why a shortcode and not the woocommerce_product_additional_information
     * hook via [do_action]/data-w4e-hook: that hook expects the product as a
     * do_action ARGUMENT, which the hook island does not pass — the callbacks
     * would receive null. wc_display_product_attributes() needs the object.
     */
    public static function shortcode_product_attributes($atts) {
        $atts = shortcode_atts(['id' => 0], $atts, 'woo_product_attributes');
        $product = self::resolve_product($atts);
        if (!$product || !function_exists('wc_display_product_attributes')) {
            return '';
        }
        ob_start();
        wc_display_product_attributes($product);
        return trim(ob_get_clean());
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

        // Fire the type-specific add-to-cart action (woocommerce_simple_add_to_cart,
        // _variable_, _grouped_, _external_). This is how WooCommerce core renders
        // the form and, unlike wc_get_template_part(), it falls back to the plugin's
        // own templates when the theme has no override (e.g. the Etch theme).
        return self::with_product($product, function () use ($product) {
            do_action('woocommerce_' . $product->get_type() . '_add_to_cart');
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

    public static function shortcode_notices($atts = []) {
        $atts = shortcode_atts(['format' => 'woo'], $atts, 'woo_notices');
        if (!function_exists('wc_print_notices')) {
            return '';
        }
        if ('plain' !== $atts['format']) {
            ob_start();
            wc_print_notices();
            return ob_get_clean();
        }

        // format="plain": Woo's notice templates assume Woo's stylesheets,
        // which these builds often disable — emit minimal class-based markup
        // instead so the notices can be styled in Etch:
        //   .w4e-notice .w4e-notice--error|--success|--notice
        // In the builder canvas a sample notice renders so the (frontend-wise
        // empty-hidden) region stays visible and styleable while designing.
        if (self::is_etch_builder()) {
            return '<div class="w4e-notice w4e-notice--success" role="status">'
                . esc_html__('Cart updated. (sample notice — only shown in the builder)', 'woo4etch') . '</div>';
        }
        $all = function_exists('wc_get_notices') ? wc_get_notices() : [];
        if (empty($all)) {
            return '';
        }
        wc_clear_notices();
        $out = '';
        foreach (['error', 'success', 'notice'] as $type) {
            foreach ((array) ($all[$type] ?? []) as $notice) {
                // Woo >= 3.9 queues ['notice' => ..., 'data' => ...]; older code paths plain strings.
                $message = is_array($notice) ? (string) ($notice['notice'] ?? '') : (string) $notice;
                if ('' === trim($message)) {
                    continue;
                }
                $safe = function_exists('wc_kses_notice') ? wc_kses_notice($message) : wp_kses_post($message);
                $out .= '<div class="w4e-notice w4e-notice--' . esc_attr($type) . '"'
                    . ('error' === $type ? ' role="alert"' : ' role="status"') . '>'
                    . $safe . '</div>';
            }
        }
        return $out;
    }

    public static function shortcode_breadcrumb($atts) {
        $atts = shortcode_atts([
            'delimiter'   => ' / ',
            'wrap_before' => '<nav class="woocommerce-breadcrumb" aria-label="Breadcrumb">',
            'wrap_after'  => '</nav>',
        ], $atts, 'woo_breadcrumb');

        // Shortcodes also run in content from authors without unfiltered_html.
        $atts['delimiter']   = wp_kses_post($atts['delimiter']);
        $atts['wrap_before'] = wp_kses_post($atts['wrap_before']);
        $atts['wrap_after']  = wp_kses_post($atts['wrap_after']);

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

        // Use wc_get_template (not wc_get_template_part): it resolves theme
        // overrides AND falls back to WooCommerce's own templates directory,
        // so paths like "single-product/related" work without a theme override.
        if (substr($name, -4) !== '.php') {
            $name .= '.php';
        }

        ob_start();
        wc_get_template($name);
        return ob_get_clean();
    }

    /* ============================================================
       Product data shortcodes (extended)
       ============================================================ */

    public static function shortcode_title($atts) {
        $atts    = shortcode_atts(['id' => 0, 'link' => 'no'], $atts, 'woo_title');
        $product = self::resolve_product($atts);
        if (!$product) {
            return '';
        }
        $name = esc_html($product->get_name());
        if ($atts['link'] === 'yes') {
            return sprintf('<a href="%s">%s</a>', esc_url($product->get_permalink()), $name);
        }
        return $name;
    }

    public static function shortcode_regular_price($atts) {
        $atts    = shortcode_atts(['id' => 0], $atts, 'woo_regular_price');
        $product = self::resolve_product($atts);
        if (!$product) {
            return '';
        }
        $regular = $product->get_regular_price();
        return ($regular === '' || $regular === null) ? '' : wc_price($regular);
    }

    public static function shortcode_sale_price($atts) {
        $atts    = shortcode_atts(['id' => 0], $atts, 'woo_sale_price');
        $product = self::resolve_product($atts);
        if (!$product || !$product->is_on_sale()) {
            return '';
        }
        $sale = $product->get_sale_price();
        return ($sale === '' || $sale === null) ? '' : wc_price($sale);
    }

    /**
     * Raw, unformatted display price for itemprop="price" / schema.
     */
    public static function shortcode_price_amount($atts) {
        $atts    = shortcode_atts(['id' => 0], $atts, 'woo_price_amount');
        $product = self::resolve_product($atts);
        if (!$product) {
            return '';
        }
        $price = wc_get_price_to_display($product);
        return ($price === '' || $price === null) ? '' : esc_attr(wc_format_decimal($price, wc_get_price_decimals()));
    }

    public static function shortcode_sale_badge($atts) {
        $atts = shortcode_atts([
            'id'         => 0,
            'text'       => __('Sale!', 'woo4etch'),
            'percentage' => 'no',
        ], $atts, 'woo_sale_badge');

        $product = self::resolve_product($atts);
        if (!$product || !$product->is_on_sale()) {
            return '';
        }

        $label = $atts['text'];

        if ($atts['percentage'] === 'yes' && $product->is_type('simple')) {
            $regular = (float) $product->get_regular_price();
            $sale    = (float) $product->get_sale_price();
            if ($regular > 0 && $sale >= 0 && $sale < $regular) {
                $label = '-' . round(100 - ($sale / $regular * 100)) . '%';
            }
        }

        return sprintf('<span class="onsale">%s</span>', esc_html($label));
    }

    public static function shortcode_weight($atts) {
        $atts    = shortcode_atts(['id' => 0, 'default' => ''], $atts, 'woo_weight');
        $product = self::resolve_product($atts);
        if (!$product || !$product->has_weight()) {
            return esc_html($atts['default']);
        }
        return esc_html(wc_format_weight((float) $product->get_weight()));
    }

    public static function shortcode_dimensions($atts) {
        $atts    = shortcode_atts(['id' => 0, 'default' => ''], $atts, 'woo_dimensions');
        $product = self::resolve_product($atts);
        if (!$product || !$product->has_dimensions()) {
            return esc_html($atts['default']);
        }
        return esc_html(wc_format_dimensions($product->get_dimensions(false)));
    }

    public static function shortcode_categories($atts) {
        $atts = shortcode_atts([
            'id'     => 0,
            'sep'    => ', ',
            'before' => '',
            'after'  => '',
        ], $atts, 'woo_categories');

        $product = self::resolve_product($atts);
        if (!$product) {
            return '';
        }
        return wc_get_product_category_list($product->get_id(), wp_kses_post($atts['sep']), wp_kses_post($atts['before']), wp_kses_post($atts['after']));
    }

    public static function shortcode_tags($atts) {
        $atts = shortcode_atts([
            'id'     => 0,
            'sep'    => ', ',
            'before' => '',
            'after'  => '',
        ], $atts, 'woo_tags');

        $product = self::resolve_product($atts);
        if (!$product) {
            return '';
        }
        return wc_get_product_tag_list($product->get_id(), wp_kses_post($atts['sep']), wp_kses_post($atts['before']), wp_kses_post($atts['after']));
    }

    public static function shortcode_short_description($atts) {
        $atts    = shortcode_atts(['id' => 0], $atts, 'woo_short_description');
        $product = self::resolve_product($atts);
        if (!$product) {
            return '';
        }
        return apply_filters('woocommerce_short_description', $product->get_short_description());
    }

    public static function shortcode_description($atts) {
        $atts    = shortcode_atts(['id' => 0], $atts, 'woo_description');
        $product = self::resolve_product($atts);
        if (!$product) {
            return '';
        }
        $content = $product->get_description();
        if (function_exists('wc_format_content')) {
            return wc_format_content($content);
        }
        return wpautop(do_shortcode($content));
    }

    /* ============================================================
       Product media shortcodes
       ============================================================ */

    public static function shortcode_image($atts) {
        $atts    = shortcode_atts(['id' => 0, 'size' => 'woocommerce_single'], $atts, 'woo_image');
        $product = self::resolve_product($atts);
        if (!$product) {
            return '';
        }
        return $product->get_image(sanitize_key($atts['size']));
    }

    /**
     * Render the product gallery images.
     *
     * Mirrors Etch's `gallery_images` Dynamic Key: the featured image is NOT
     * included by default (the gallery is returned exactly as stored). Set
     * include_featured="yes" to prepend the featured image.
     */
    public static function shortcode_gallery($atts) {
        $atts = shortcode_atts([
            'id'               => 0,
            'size'             => 'woocommerce_thumbnail',
            'include_featured' => 'no',
            'link'             => 'no',
            'mode'             => 'custom',
            'columns'          => 4,
        ], $atts, 'woo_gallery');

        $product = self::resolve_product($atts);
        if (!$product) {
            return '';
        }

        // mode="woo": WooCommerce-native gallery markup that Woo's own
        // zoom/lightbox/slider scripts initialise on (featured image first,
        // like Woo's product-image.php). size/include_featured/link don't
        // apply here — Woo's wc_get_gallery_image_html() decides those.
        if ($atts['mode'] === 'woo') {
            return self::render_native_woo_gallery($product, absint($atts['columns']));
        }

        $size = sanitize_key($atts['size']);
        $ids  = $product->get_gallery_image_ids();

        if ($atts['include_featured'] === 'yes' && $product->get_image_id()) {
            array_unshift($ids, $product->get_image_id());
        }

        if (empty($ids)) {
            return '';
        }

        $items = '';
        foreach ($ids as $attachment_id) {
            $img = wp_get_attachment_image((int) $attachment_id, $size, false, ['class' => 'woo-gallery__image']);
            if (!$img) {
                continue;
            }
            if ($atts['link'] === 'yes') {
                $full = wp_get_attachment_image_url((int) $attachment_id, 'full');
                if ($full) {
                    $img = sprintf('<a href="%s" class="woo-gallery__link">%s</a>', esc_url($full), $img);
                }
            }
            $items .= sprintf('<figure class="woo-gallery__item">%s</figure>', $img);
        }

        return $items === '' ? '' : sprintf('<div class="woo-gallery">%s</div>', $items);
    }

    /**
     * WooCommerce-native gallery for [woo_gallery mode="woo"]: the exact
     * wrapper + per-image data attributes (data-thumb, data-large_image, …)
     * that Woo's single-product.js initialises zoom, PhotoSwipe and
     * FlexSlider on. Featured image always comes first — without a main
     * slide the slider has nothing to show, exactly like Woo core.
     *
     * @param WC_Product $product Product to render.
     * @param int        $columns Thumbnail columns (data-columns).
     * @return string
     */
    private static function render_native_woo_gallery($product, $columns) {
        if (!function_exists('wc_get_gallery_image_html')) {
            return '';
        }

        $ids = $product->get_gallery_image_ids();
        if ($product->get_image_id()) {
            array_unshift($ids, $product->get_image_id());
        }
        $ids = array_values(array_unique(array_map('absint', $ids)));
        if (empty($ids)) {
            return '';
        }

        // The supports may be active without is_product() (e.g. a quick-view
        // pattern) — make sure the scripts come along wherever this renders.
        self::enqueue_gallery_assets();

        $columns = max(1, $columns);
        $images  = '';
        foreach ($ids as $i => $attachment_id) {
            $images .= wc_get_gallery_image_html($attachment_id, 0 === $i);
        }

        $has_gallery_js = current_theme_supports('wc-product-gallery-zoom')
            || current_theme_supports('wc-product-gallery-lightbox')
            || current_theme_supports('wc-product-gallery-slider');

        return sprintf(
            '<div class="woocommerce-product-gallery woocommerce-product-gallery--with-images woocommerce-product-gallery--columns-%1$d images" data-columns="%1$d"%2$s><figure class="woocommerce-product-gallery__wrapper">%3$s</figure></div>',
            $columns,
            // single-product.js fades the gallery in after init; without the
            // scripts the inline opacity would hide it forever, so skip it.
            $has_gallery_js ? ' style="opacity: 0; transition: opacity .25s ease-in-out;"' : '',
            $images
        );
    }

    /* ============================================================
       Product UI shortcodes (extended)
       ============================================================ */

    public static function shortcode_add_to_cart_url($atts) {
        $atts    = shortcode_atts(['id' => 0], $atts, 'woo_add_to_cart_url');
        $product = self::resolve_product($atts);
        return $product ? esc_url($product->add_to_cart_url()) : '';
    }

    public static function shortcode_tabs($atts) {
        $atts    = shortcode_atts(['id' => 0], $atts, 'woo_tabs');
        $product = self::resolve_product($atts);
        if (!$product) {
            return '';
        }
        return self::with_product($product, static function () {
            woocommerce_output_product_data_tabs();
        });
    }

    public static function shortcode_related($atts) {
        $atts    = shortcode_atts(['id' => 0], $atts, 'woo_related');
        $product = self::resolve_product($atts);
        if (!$product) {
            return '';
        }
        return self::with_product($product, static function () {
            woocommerce_output_related_products();
        });
    }

    public static function shortcode_upsells($atts) {
        $atts    = shortcode_atts(['id' => 0], $atts, 'woo_upsells');
        $product = self::resolve_product($atts);
        if (!$product) {
            return '';
        }
        return self::with_product($product, static function () {
            woocommerce_upsell_display();
        });
    }

    /* ============================================================
       Cart shortcodes (extended)
       ============================================================ */

    public static function shortcode_checkout_url() {
        return esc_url(wc_get_checkout_url());
    }

    public static function shortcode_mini_cart() {
        if (!function_exists('woocommerce_mini_cart') || is_null(WC()->cart)) {
            return '';
        }
        ob_start();
        echo '<div class="widget_shopping_cart_content">';
        woocommerce_mini_cart();
        echo '</div>';
        return ob_get_clean();
    }

    /* ============================================================
       Account shortcodes
       ============================================================ */

    public static function shortcode_account_url($atts) {
        $atts = shortcode_atts(['endpoint' => ''], $atts, 'woo_account_url');
        if ($atts['endpoint'] !== '') {
            return esc_url(wc_get_account_endpoint_url(sanitize_key($atts['endpoint'])));
        }
        return esc_url(wc_get_page_permalink('myaccount'));
    }

    public static function shortcode_logout_url($atts) {
        $atts     = shortcode_atts(['redirect' => ''], $atts, 'woo_logout_url');
        $redirect = $atts['redirect'] !== '' ? esc_url_raw($atts['redirect']) : '';
        return esc_url(wc_logout_url($redirect));
    }

    /* ============================================================
       Store & archive shortcodes
       ============================================================ */

    public static function shortcode_shop_url() {
        return esc_url(wc_get_page_permalink('shop'));
    }

    public static function shortcode_result_count() {
        if (!function_exists('woocommerce_result_count')) {
            return '';
        }
        ob_start();
        woocommerce_result_count();
        return ob_get_clean();
    }

    public static function shortcode_catalog_ordering() {
        if (!function_exists('woocommerce_catalog_ordering')) {
            return '';
        }
        ob_start();
        woocommerce_catalog_ordering();
        return ob_get_clean();
    }

    public static function shortcode_pagination() {
        if (!function_exists('woocommerce_pagination')) {
            return '';
        }
        ob_start();
        woocommerce_pagination();
        return ob_get_clean();
    }

    /* ============================================================
       Conditional shortcode
       ============================================================ */

    /**
     * Render inner content only when a WooCommerce conditional is true.
     *
     * Page conditionals (no product needed):
     *   is_shop, is_product, is_cart, is_checkout, is_account_page,
     *   is_product_category, is_product_tag, is_product_taxonomy,
     *   is_wc_endpoint_url, is_woocommerce
     * Product conditionals (use the current/explicit product):
     *   on_sale, in_stock, purchasable, featured, on_backorder,
     *   downloadable, virtual, sold_individually
     *
     * Prefix the condition with ! to negate, e.g. cond="!is_cart".
     * Optional `arg` is passed to page conditionals, e.g.
     *   [woo_if cond="is_product_category" arg="hoodies"]…[/woo_if]
     */
    public static function shortcode_if($atts, $content = '') {
        $atts = shortcode_atts(['cond' => '', 'arg' => '', 'id' => 0], $atts, 'woo_if');

        $cond = trim((string) $atts['cond']);
        if ($cond === '' || $content === null) {
            return '';
        }

        $negate = false;
        if (strpos($cond, '!') === 0) {
            $negate = true;
            $cond   = ltrim($cond, '! ');
        }

        $page_conds = [
            'is_shop',
            'is_product',
            'is_cart',
            'is_checkout',
            'is_account_page',
            'is_product_category',
            'is_product_tag',
            'is_product_taxonomy',
            'is_wc_endpoint_url',
            'is_woocommerce',
            'is_user_logged_in',
        ];

        $product_conds = [
            'on_sale'           => 'is_on_sale',
            'in_stock'          => 'is_in_stock',
            'purchasable'       => 'is_purchasable',
            'featured'          => 'is_featured',
            'on_backorder'      => 'is_on_backorder',
            'downloadable'      => 'is_downloadable',
            'virtual'           => 'is_virtual',
            'sold_individually' => 'is_sold_individually',
        ];

        $result = false;

        if (in_array($cond, $page_conds, true) && function_exists($cond)) {
            $result = $atts['arg'] !== ''
                ? (bool) call_user_func($cond, sanitize_text_field($atts['arg']))
                : (bool) call_user_func($cond);
        } elseif ($cond === 'is_type') {
            // Product type check, e.g. cond="is_type" arg="grouped|external|variable|simple".
            $product = self::resolve_product($atts);
            $result  = ($product && $atts['arg'] !== '') ? (bool) $product->is_type(sanitize_key($atts['arg'])) : false;
        } elseif (isset($product_conds[$cond])) {
            $product = self::resolve_product($atts);
            $method  = $product_conds[$cond];
            $result  = ($product && method_exists($product, $method)) ? (bool) $product->$method() : false;
        }

        if ($negate) {
            $result = !$result;
        }

        return $result ? do_shortcode($content) : '';
    }

    /* ============================================================
       Cart shortcodes (Tier 1)
       ============================================================ */

    public static function shortcode_cart_totals() {
        // WC()->cart is null outside a loaded frontend cart (editor/builder/REST
        // preview) — calling the totals template there fatals, so bail safely.
        if (!function_exists('woocommerce_cart_totals') || is_null(WC()->cart)) {
            return '';
        }
        ob_start();
        woocommerce_cart_totals();
        return ob_get_clean();
    }

    /**
     * Complete cart line items + form, with clean class-based markup you can
     * style/rearrange. A faithful, extension-compatible reproduction of
     * WooCommerce's cart.php form: it fires every cart hook (before_cart,
     * before/after_cart_table, before/after_cart_contents, cart_contents,
     * after_cart_item_name, cart_actions) and per-item filter (product,
     * permalink, thumbnail, name, price, quantity, subtotal, class,
     * remove_link), and includes the coupon field + update button + nonce — so
     * quantity update, remove, coupons and third-party cart plugins all work.
     *
     * Pair with [woo_cart_totals] (totals/checkout) and [woo_cross_sells] in
     * your own Etch layout. No AJAX required (classic submit).
     */
    public static function shortcode_cart_items($atts) {
        $atts = shortcode_atts(['thumbnail_size' => 'woocommerce_thumbnail'], $atts, 'woo_cart_items');

        if (is_null(WC()->cart)) {
            return '';
        }
        if (WC()->cart->is_empty()) {
            return '<p class="woo-cart-empty">' . esc_html__('Your cart is currently empty.', 'woo4etch') . '</p>';
        }

        $size = sanitize_key($atts['thumbnail_size']);

        ob_start();
        do_action('woocommerce_before_cart'); // prints notices + lets plugins inject
        ?>
        <form class="woocommerce-cart-form woo-cart-form" action="<?php echo esc_url(wc_get_cart_url()); ?>" method="post">
            <?php do_action('woocommerce_before_cart_table'); ?>
            <ul class="woo-cart-items">
                <?php
                do_action('woocommerce_before_cart_contents');

                foreach (WC()->cart->get_cart() as $cart_item_key => $cart_item) {
                    $product  = apply_filters('woocommerce_cart_item_product', $cart_item['data'], $cart_item, $cart_item_key);
                    $quantity = $cart_item['quantity'];

                    if (!$product instanceof WC_Product || !$product->exists() || $quantity <= 0
                        || !apply_filters('woocommerce_cart_item_visible', true, $cart_item, $cart_item_key)) {
                        continue;
                    }

                    $permalink = apply_filters('woocommerce_cart_item_permalink', $product->is_visible() ? $product->get_permalink($cart_item) : '', $cart_item, $cart_item_key);
                    $thumbnail = apply_filters('woocommerce_cart_item_thumbnail', $product->get_image($size), $cart_item, $cart_item_key);

                    // Quantity: "1" for sold-individually, otherwise a real input.
                    if ($product->is_sold_individually()) {
                        $qty_html = sprintf('1 <input type="hidden" name="cart[%s][qty]" value="1" />', esc_attr($cart_item_key));
                    } else {
                        $qty_html = woocommerce_quantity_input([
                            'input_name'   => "cart[{$cart_item_key}][qty]",
                            'input_value'  => $quantity,
                            'max_value'    => $product->get_max_purchase_quantity(),
                            'min_value'    => '0',
                            'product_name' => $product->get_name(),
                        ], $product, false);
                    }

                    $item_class = apply_filters('woocommerce_cart_item_class', 'woo-cart-item', $cart_item, $cart_item_key);

                    // phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped -- Woo filters return prepared HTML.
                    ?>
                    <li class="<?php echo esc_attr($item_class); ?>">
                        <div class="woo-cart-item__thumb"><?php
                            echo $permalink ? sprintf('<a href="%s">%s</a>', esc_url($permalink), $thumbnail) : $thumbnail;
                        ?></div>

                        <div class="woo-cart-item__info">
                            <span class="woo-cart-item__name"><?php
                                echo wp_kses_post(apply_filters('woocommerce_cart_item_name',
                                    $permalink ? sprintf('<a href="%s">%s</a>', esc_url($permalink), $product->get_name()) : $product->get_name(),
                                    $cart_item, $cart_item_key));
                                do_action('woocommerce_after_cart_item_name', $cart_item, $cart_item_key);
                            ?></span>
                            <span class="woo-cart-item__meta"><?php echo wc_get_formatted_cart_item_data($cart_item); ?></span>
                            <span class="woo-cart-item__price"><?php
                                echo apply_filters('woocommerce_cart_item_price', WC()->cart->get_product_price($product), $cart_item, $cart_item_key);
                            ?></span>
                        </div>

                        <div class="woo-cart-item__qty"><?php
                            echo apply_filters('woocommerce_cart_item_quantity', $qty_html, $cart_item_key, $cart_item);
                        ?></div>

                        <div class="woo-cart-item__subtotal"><?php
                            echo apply_filters('woocommerce_cart_item_subtotal', WC()->cart->get_product_subtotal($product, $quantity), $cart_item, $cart_item_key);
                        ?></div>

                        <div class="woo-cart-item__remove"><?php
                            echo apply_filters('woocommerce_cart_item_remove_link', sprintf(
                                '<a href="%s" class="remove" aria-label="%s" data-product_id="%s" data-product_sku="%s">&times;</a>',
                                esc_url(wc_get_cart_remove_url($cart_item_key)),
                                esc_attr(sprintf(__('Remove %s from cart', 'woo4etch'), wp_strip_all_tags($product->get_name()))),
                                esc_attr($product->get_id()),
                                esc_attr($product->get_sku())
                            ), $cart_item_key);
                        ?></div>
                    </li>
                    <?php
                    // phpcs:enable WordPress.Security.EscapeOutput.OutputNotEscaped
                }

                do_action('woocommerce_cart_contents');
                ?>
            </ul>

            <div class="woo-cart-form__actions">
                <?php if (wc_coupons_enabled()) : ?>
                    <div class="woo-cart-coupon coupon">
                        <label for="coupon_code" class="screen-reader-text"><?php esc_html_e('Coupon:', 'woo4etch'); ?></label>
                        <input type="text" name="coupon_code" class="input-text" id="coupon_code" value="" placeholder="<?php esc_attr_e('Coupon code', 'woo4etch'); ?>" />
                        <button type="submit" class="button" name="apply_coupon" value="<?php esc_attr_e('Apply coupon', 'woo4etch'); ?>"><?php esc_html_e('Apply coupon', 'woo4etch'); ?></button>
                        <?php do_action('woocommerce_cart_coupon'); ?>
                    </div>
                <?php endif; ?>

                <button type="submit" class="button woo-cart-update" name="update_cart" value="<?php esc_attr_e('Update cart', 'woo4etch'); ?>"><?php esc_html_e('Update cart', 'woo4etch'); ?></button>

                <?php do_action('woocommerce_cart_actions'); ?>
                <?php wp_nonce_field('woocommerce-cart', 'woocommerce-cart-nonce'); ?>
            </div>

            <?php do_action('woocommerce_after_cart_contents'); ?>
            <?php do_action('woocommerce_after_cart_table'); ?>
        </form>
        <?php
        do_action('woocommerce_after_cart');
        return ob_get_clean();
    }

    public static function shortcode_coupon_form() {
        if (!function_exists('woocommerce_checkout_coupon_form')) {
            return '';
        }
        ob_start();
        woocommerce_checkout_coupon_form();
        return ob_get_clean();
    }

    public static function shortcode_shipping_calculator() {
        if (!function_exists('woocommerce_shipping_calculator') || is_null(WC()->cart)) {
            return '';
        }
        ob_start();
        woocommerce_shipping_calculator();
        return ob_get_clean();
    }

    public static function shortcode_cross_sells() {
        if (!function_exists('woocommerce_cross_sell_display') || is_null(WC()->cart)) {
            return '';
        }
        ob_start();
        woocommerce_cross_sell_display();
        return ob_get_clean();
    }

    /* ============================================================
       Account shortcodes (Tier 1)
       ============================================================ */

    public static function shortcode_account_menu() {
        if (!function_exists('wc_get_template')) {
            return '';
        }
        ob_start();
        wc_get_template('myaccount/navigation.php');
        return ob_get_clean();
    }

    /**
     * Renders the content of the current My Account endpoint (dashboard,
     * orders, downloads, edit-account, view-order, payment-methods, …).
     * WooCommerce's own access control applies per endpoint.
     */
    public static function shortcode_account_content() {
        ob_start();
        do_action('woocommerce_account_content');
        return ob_get_clean();
    }

    public static function shortcode_login_form($atts) {
        $atts = shortcode_atts([
            'message'  => '',
            'redirect' => '',
            'hidden'   => 'no',
        ], $atts, 'woo_login_form');

        if (!function_exists('woocommerce_login_form')) {
            return '';
        }

        ob_start();
        woocommerce_login_form([
            'message'  => $atts['message'],
            'redirect' => $atts['redirect'] !== '' ? esc_url_raw($atts['redirect']) : '',
            'hidden'   => $atts['hidden'] === 'yes',
        ]);
        return ob_get_clean();
    }

    /**
     * Order details table. Falls back to the current order on the
     * order-received / view-order endpoints. An explicit order_id is only
     * honoured when the visitor owns the order, supplies the matching order
     * key, or can manage WooCommerce — so it can't be used to enumerate orders.
     */
    public static function shortcode_order_details($atts) {
        $atts  = shortcode_atts(['order_id' => 0, 'key' => ''], $atts, 'woo_order_details');
        $order = self::resolve_order($atts);
        if (!$order || !function_exists('woocommerce_order_details_table')) {
            return '';
        }
        ob_start();
        woocommerce_order_details_table($order->get_id());
        return ob_get_clean();
    }

    /* ============================================================
       Store & archive shortcodes (Tier 1)
       ============================================================ */

    public static function shortcode_product_search() {
        if (!function_exists('get_product_search_form')) {
            return '';
        }
        return get_product_search_form(false);
    }

    /* ============================================================
       Internal: resolve an order safely
       ============================================================ */

    /**
     * Resolve the order for order-bound shortcodes.
     *
     * Priority: explicit & authorised order_id → global $order → the order on
     * the order-received / view-order endpoint. Returns null when nothing is
     * safely resolvable.
     *
     * @param array $atts
     * @return WC_Order|null
     */
    private static function resolve_order($atts) {
        if (!empty($atts['order_id'])) {
            $order = wc_get_order(absint($atts['order_id']));
            if (!$order instanceof WC_Order) {
                return null;
            }

            $key_ok   = !empty($atts['key']) && hash_equals((string) $order->get_order_key(), wc_clean(wp_unslash($atts['key'])));
            $owns_it  = is_user_logged_in() && (int) $order->get_customer_id() === get_current_user_id();
            $is_admin = current_user_can('manage_woocommerce');

            return ($key_ok || $owns_it || $is_admin) ? $order : null;
        }

        global $order;
        if ($order instanceof WC_Order) {
            return $order;
        }

        $endpoint_id = absint(get_query_var('order-received'));
        if (!$endpoint_id) {
            $endpoint_id = absint(get_query_var('view-order'));
        }
        if ($endpoint_id) {
            $maybe = wc_get_order($endpoint_id);
            return $maybe instanceof WC_Order ? $maybe : null;
        }

        return null;
    }

    /* ============================================================
       Dynamic data bridge — expose Woo data (products, cart) to Etch
       ============================================================ */

    /**
     * Enrich Etch's post dynamic data for WooCommerce products, so price, stock,
     * rating and sale state are real Dynamic Keys ({this.price}, {this.is_on_sale}, …)
     * instead of shortcodes — visible and previewable in the Etch builder canvas.
     * Hooked to `etch/dynamic_data/post`, the same seam Etch's own WooCommerce
     * integration uses for gallery_images. Inside loops the keys read {item.price} etc.
     *
     * Exposed keys (plain text unless noted — Etch text blocks HTML-escape values):
     *   price                      — formatted price (sale range / variable "from")
     *   regular_price, sale_price  — formatted; empty for variable products
     *   price_html                 — Woo's price markup incl. <del>/<ins>; renders
     *                                only in Raw-HTML blocks (text blocks escape it)
     *   price_amount               — raw decimal for itemprop/schema
     *   currency_symbol
     *   is_on_sale (bool), sale_percentage (int; cheapest variation for variables)
     *   sku                        — product SKU
     *   product_type               — NOTE: shadowed by Etch on product posts
     *                                (Etch exposes the product_type taxonomy
     *                                term object under the same key, and Etch
     *                                keys win). {this.product_type.name} gives
     *                                the type string either way.
     *   is_simple (bool)           — condition-safe product-type check
     *                                ({#if this.is_simple} … isTruthy/isFalsy)
     *   stock_status               — instock | outofstock | onbackorder
     *   stock_label                — localized availability text (may be empty for
     *                                in-stock products, per Woo inventory settings)
     *   stock_quantity (int|''), is_in_stock (bool)
     *   is_purchasable, is_featured, is_sold_individually (bool)
     *   rating (float), rating_count, review_count (int)
     *   add_to_cart_url, add_to_cart_text
     *   weight, dimensions         — formatted; empty when not set
     *   upsell_ids                 — array of product IDs
     *
     * Keys Etch already set are never overwritten. Disable:
     * add_filter('woo4etch/expose_product_data','__return_false').
     * Reshape/extend: add_filter('woo4etch/product_data', fn($d, $product) => $d, 10, 2).
     *
     * @param array<string,mixed> $data    Etch post data.
     * @param int                 $post_id Post being resolved.
     * @return array<string,mixed>
     */
    public static function expose_product_data($data, $post_id) {
        if (!is_array($data)) {
            $data = [];
        }
        if (!apply_filters('woo4etch/expose_product_data', true)) {
            return $data;
        }
        if (!function_exists('wc_get_product') || 'product' !== get_post_type($post_id)) {
            return $data;
        }
        $product = wc_get_product($post_id);
        if (!$product instanceof WC_Product) {
            return $data;
        }

        $regular = $product->get_regular_price();
        $sale    = $product->get_sale_price();
        if ($product->is_type('variable')) {
            // Variable products have no own prices — use the cheapest variation
            // for the discount percentage (regular/sale stay empty below).
            $regular = $product->get_variation_regular_price('min');
            $sale    = $product->get_variation_sale_price('min');
        }
        $percentage = 0;
        if ($product->is_on_sale() && is_numeric($regular) && (float) $regular > 0
            && is_numeric($sale) && (float) $sale < (float) $regular) {
            $percentage = (int) round(100 - ((float) $sale / (float) $regular) * 100);
        }

        $price_html    = $product->get_price_html();
        $display_price = wc_get_price_to_display($product);
        $availability  = $product->get_availability();
        $is_variable   = $product->is_type('variable');

        $payload = [
            'price'            => self::plain($price_html),
            'regular_price'    => (!$is_variable && $regular !== '' && $regular !== null) ? self::plain(wc_price($regular)) : '',
            'sale_price'       => (!$is_variable && $product->is_on_sale() && $sale !== '' && $sale !== null) ? self::plain(wc_price($sale)) : '',
            'price_html'       => $price_html,
            'price_amount'     => ($display_price === '' || $display_price === null) ? '' : wc_format_decimal($display_price, wc_get_price_decimals()),
            'currency_symbol'  => self::plain(get_woocommerce_currency_symbol()),
            'is_on_sale'       => $product->is_on_sale(),
            'sale_percentage'  => $percentage,
            'sku'              => (string) $product->get_sku(),
            // CAUTION: on product posts Etch itself exposes `product_type` as
            // the WooCommerce product_type TAXONOMY TERM (an object), and Etch
            // keys always win — so this scalar is shadowed there. Use
            // {this.product_type.name} for display, `is_simple` in conditions.
            'product_type'     => $product->get_type(),
            'is_simple'        => $product->is_type('simple'),
            'stock_status'     => $product->get_stock_status(),
            'stock_label'      => isset($availability['availability']) ? self::plain($availability['availability']) : '',
            'stock_quantity'   => $product->get_stock_quantity() ?? '',
            'is_in_stock'      => $product->is_in_stock(),
            'is_purchasable'   => $product->is_purchasable(),
            'is_featured'      => $product->is_featured(),
            'is_sold_individually' => $product->is_sold_individually(),
            'rating'           => (float) $product->get_average_rating(),
            'rating_count'     => (int) $product->get_rating_count(),
            'review_count'     => (int) $product->get_review_count(),
            'add_to_cart_url'  => $product->add_to_cart_url(),
            'add_to_cart_text' => $product->add_to_cart_text(),
            'weight'           => $product->has_weight() ? self::plain(wc_format_weight($product->get_weight())) : '',
            'dimensions'       => $product->has_dimensions() ? self::plain(wc_format_dimensions($product->get_dimensions(false))) : '',
            'upsell_ids'       => array_map('intval', (array) $product->get_upsell_ids()),
        ];

        // Variations JSON for hand-built variation forms (template 02):
        //   <form class="variations_form cart" … data-product_variations="{this.variations_json}">
        // get_available_variations() renders every variation, so it's computed
        // only for the main product on its own page — never for loop items
        // (a 10-product grid would otherwise pay it 10 times). Force it on:
        // add_filter('woo4etch/expose_variations_json', '__return_true').
        $is_main_product = function_exists('is_product') && is_product() && (int) get_queried_object_id() === (int) $post_id;
        if ($product->is_type('variable') && apply_filters('woo4etch/expose_variations_json', $is_main_product, $product)) {
            $payload['variations_json'] = wc_esc_json(wp_json_encode($product->get_available_variations()));
        }

        $payload = apply_filters('woo4etch/product_data', $payload, $product);

        // Etch's own keys (and future ones) always win.
        foreach ($payload as $key => $value) {
            if (!array_key_exists($key, $data)) {
                $data[$key] = $value;
            }
        }
        return $data;
    }

    /**
     * Add the WooCommerce cart to Etch's `option` dynamic-data root, so the cart
     * can be built as a pure Etch loop (full HTML control in the builder) instead
     * of a shortcode. Hooked to `etch/dynamic_data/option`.
     *
     * Exposes:
     *   {options.cart_items}     — array; each: key, id, name, sku, quantity,
     *                              price, subtotal, permalink, image, remove_url, on_sale
     *   {options.cart_count} {options.cart_subtotal} {options.cart_total}
     *   {options.cart_coupons}   — array; each: code, amount, remove_url
     *   {options.cart_discount}  — formatted total discount ('' when none)
     *   {options.cart_shipping_total} — formatted shipping ('' when nothing ships)
     *   {options.cart_url} {options.checkout_url} {options.shop_url}
     *   {options.cart_is_empty}
     *
     * In the Etch builder canvas (no shopping session) it returns sample rows so
     * the loop previews. Disable: add_filter('woo4etch/expose_cart_data','__return_false').
     * Reshape: add_filter('woo4etch/cart_data', fn($d) => $d).
     *
     * @param array<string,mixed> $data Existing option data.
     * @return array<string,mixed>
     */
    public static function expose_cart_data($data) {
        if (!is_array($data)) {
            $data = [];
        }
        if (!apply_filters('woo4etch/expose_cart_data', true)) {
            return $data;
        }

        $size = apply_filters('woo4etch/cart_image_size', 'woocommerce_thumbnail');
        $cart = (function_exists('WC') && WC()) ? WC()->cart : null;

        if ($cart instanceof WC_Cart && !$cart->is_empty()) {
            $items = [];
            foreach ($cart->get_cart() as $key => $cart_item) {
                $product = $cart_item['data'] ?? null;
                if (!$product instanceof WC_Product || !$product->exists() || $cart_item['quantity'] <= 0) {
                    continue;
                }
                $items[] = self::cart_item_payload($key, $cart_item, $product, $size);
            }
            $data['cart_items']    = $items;
            $data['cart_count']    = $cart->get_cart_contents_count();
            $data['cart_subtotal'] = self::plain($cart->get_cart_subtotal());
            $data['cart_total']    = self::plain($cart->get_total());
            $data['cart_coupons']  = self::cart_coupons($cart);
            $discount              = $cart->get_discount_total() + ($cart->display_cart_ex_tax ? 0 : $cart->get_discount_tax());
            $data['cart_discount'] = $discount > 0 ? self::plain(wc_price($discount)) : '';
            $data['cart_shipping_total'] = $cart->needs_shipping() ? self::plain($cart->get_cart_shipping_total()) : '';
            $data['cart_is_empty'] = false;
        } elseif (self::is_etch_builder()) {
            // Builder canvas → sample rows so the loop has something to preview.
            $data = array_merge($data, self::sample_cart_data($size));
        } else {
            $data['cart_items']    = [];
            $data['cart_count']    = 0;
            $data['cart_subtotal'] = '';
            $data['cart_total']    = '';
            $data['cart_coupons']  = [];
            $data['cart_discount'] = '';
            $data['cart_shipping_total'] = '';
            $data['cart_is_empty'] = true;
        }

        $data['cart_url']     = function_exists('wc_get_cart_url') ? wc_get_cart_url() : '';
        $data['checkout_url'] = function_exists('wc_get_checkout_url') ? wc_get_checkout_url() : '';
        // For empty-cart states: "Return to shop" target.
        $data['shop_url']     = function_exists('wc_get_page_permalink') ? wc_get_page_permalink('shop') : '';
        // Lets you build a working cart FORM entirely in Etch (qty update + coupon):
        // <input type="hidden" name="woocommerce-cart-nonce" value="{options.cart_nonce}">
        $data['cart_nonce']   = wp_create_nonce('woocommerce-cart');

        // Cross-sells ("You may also like") — loop {options.cross_sells} in Etch.
        $data['cross_sells']  = self::cart_cross_sells($cart, $size, self::is_etch_builder());

        return apply_filters('woo4etch/cart_data', $data);
    }

    /**
     * Build one cart-item payload for the dynamic-data bridge.
     *
     * @param string     $key
     * @param array      $cart_item
     * @param WC_Product $product
     * @param string     $size
     * @return array<string,mixed>
     */
    private static function cart_item_payload($key, $cart_item, WC_Product $product, $size) {
        $image_id = $product->get_image_id();
        $image    = $image_id ? wp_get_attachment_image_url($image_id, $size) : '';
        if (!$image && function_exists('wc_placeholder_img_src')) {
            $image = wc_placeholder_img_src($size);
        }

        $payload = [
            'key'        => $key,
            'id'         => $product->get_id(),
            'name'       => $product->get_name(),
            'sku'        => $product->get_sku(),
            // Variation / add-on attributes as a flat string, e.g. "Color: Blue, Size: M".
            // Note: third parties can inject noisy rows via woocommerce_get_item_data
            // (Germanized adds gzd-* entries) — filter the payload below, or unhook
            // their get_item_data filter for classic renders (see 13-useful-snippets.md).
            'meta'       => self::plain(wc_get_formatted_cart_item_data($cart_item, true)),
            'quantity'   => $cart_item['quantity'],
            'price'      => self::plain(WC()->cart->get_product_price($product)),
            'subtotal'   => self::plain(WC()->cart->get_product_subtotal($product, $cart_item['quantity'])),
            'permalink'  => $product->is_visible() ? get_permalink($product->get_id()) : '',
            'image'      => (string) $image,
            'remove_url' => wc_get_cart_remove_url($key),
            'on_sale'    => $product->is_on_sale(),
            // Sold-individually items must not render an editable quantity
            // (Zack's product-context flag, mirrored per cart line).
            'sold_individually' => $product->is_sold_individually(),
        ];

        /**
         * Adjust one cart-item payload before it reaches the dynamic-data bridge
         * ({options.cart_items} / {woo.cart.items}).
         *
         * @param array      $payload   The item payload (key, id, name, meta, …).
         * @param array      $cart_item Raw WooCommerce cart item.
         * @param WC_Product $product   Resolved product object.
         */
        return apply_filters('woo4etch/cart_item_payload', $payload, $cart_item, $product);
    }

    /**
     * Applied coupons for {options.cart_coupons}. Amount and remove URL follow
     * WooCommerce's own cart-totals rendering (wc_cart_totals_coupon_html), so
     * the classic ?remove_coupon= GET keeps working as the no-JS fallback and
     * the Store API layer can intercept the same link.
     *
     * @param WC_Cart $cart
     * @return array<int,array<string,string>>
     */
    private static function cart_coupons(WC_Cart $cart) {
        $out = [];
        foreach ($cart->get_applied_coupons() as $code) {
            $amount = $cart->get_coupon_discount_amount($code, $cart->display_cart_ex_tax);
            $out[] = apply_filters('woo4etch/cart_coupon_payload', [
                'code'       => $code,
                'amount'     => self::plain(wc_price($amount)),
                'remove_url' => add_query_arg('remove_coupon', rawurlencode($code), wc_get_cart_url()),
            ], $code);
        }
        return $out;
    }

    /**
     * Checkout data for the Store API checkout (issue #27) as Etch dynamic
     * data — so payment methods, shipping rates and legal checkboxes are
     * hand-written Etch loops, never injected markup. Hooked to
     * `etch/dynamic_data/option`.
     *
     * Exposes {options.checkout}:
     *   payment_methods — array; each: id, title, description, icon (HTML)
     *   shipping_rates  — array; each: id, package, label, price, selected
     *   checkboxes      — array (Germanized legal checkboxes when active);
     *                     each: id, label (HTML), error
     *   needs_shipping  — bool
     *   nonce           — classic checkout nonce (for the no-JS fallback form)
     *
     * Disable: add_filter('woo4etch/expose_checkout_data','__return_false').
     * Reshape: add_filter('woo4etch/checkout_data', fn($d) => $d).
     *
     * @param array<string,mixed> $data Existing option data.
     * @return array<string,mixed>
     */
    public static function expose_checkout_data($data) {
        if (!is_array($data)) {
            $data = [];
        }
        if (!apply_filters('woo4etch/expose_checkout_data', true)) {
            return $data;
        }

        static $checkout = null;
        if ($checkout !== null) {
            $data['checkout'] = $checkout;
            return $data;
        }

        if (self::is_etch_builder()) {
            $checkout = self::sample_checkout_data();
            $data['checkout'] = $checkout;
            return $data;
        }

        $cart  = (function_exists('WC') && WC()) ? WC()->cart : null;
        $empty = [
            'payment_methods' => [],
            'shipping_rates'  => [],
            'checkboxes'      => [],
            'countries'       => [],
            'needs_shipping'  => false,
            'nonce'           => '',
        ];
        if (!$cart instanceof WC_Cart || $cart->is_empty() || !WC()->session) {
            $checkout = $empty;
            $data['checkout'] = $checkout;
            return $data;
        }

        $methods = [];
        if (WC()->payment_gateways()) {
            $chosen = (string) WC()->session->get('chosen_payment_method');
            foreach (WC()->payment_gateways()->get_available_payment_gateways() as $gateway) {
                // Only gateways that actually WORK in a hand-built form
                // (redirect/offline — same allowlist the JS module uses).
                // Inline-tokenizing gateways need their own payment_fields
                // markup + client JS; offer those via [woo_checkout_block].
                if (!self::gateway_allowlisted($gateway->id)) {
                    continue;
                }
                $methods[] = [
                    'id'          => $gateway->id,
                    'title'       => $gateway->get_title(),
                    'description' => wp_kses_post(wpautop(wptexturize((string) $gateway->get_description()))),
                    'icon'        => wp_kses_post((string) $gateway->get_icon()),
                    'selected'    => $gateway->id === $chosen,
                ];
            }
            // No session choice yet → preselect the first gateway, so the
            // layout's checked-state conditions always mark exactly one.
            if ($methods && !array_filter(array_column($methods, 'selected'))) {
                $methods[0]['selected'] = true;
            }
        }

        // Allowed countries for a hand-built country <select> — Etch loop with
        // per-option selected conditions ({options.checkout.countries}).
        $countries = [];
        if (WC()->countries) {
            $current = WC()->customer ? WC()->customer->get_billing_country() : '';
            if ('' === $current) {
                $current = WC()->countries->get_base_country();
            }
            foreach (WC()->countries->get_allowed_countries() as $code => $label) {
                $countries[] = [
                    'code'     => (string) $code,
                    'name'     => html_entity_decode((string) $label, ENT_QUOTES),
                    'selected' => $code === $current,
                ];
            }
        }

        $rates          = [];
        $needs_shipping = $cart->needs_shipping();
        if ($needs_shipping) {
            if (!WC()->shipping()->get_packages()) {
                $cart->calculate_shipping();
            }
            $chosen    = (array) WC()->session->get('chosen_shipping_methods');
            $incl_tax  = 'incl' === $cart->get_tax_price_display_mode();
            foreach (WC()->shipping()->get_packages() as $i => $package) {
                foreach ($package['rates'] as $rate) {
                    $cost = (float) $rate->get_cost();
                    if ($incl_tax) {
                        $cost += array_sum(array_map('floatval', (array) $rate->get_taxes()));
                    }
                    $rates[] = [
                        'id'       => $rate->get_id(),
                        'package'  => $i,
                        'label'    => $rate->get_label(),
                        'price'    => self::plain(wc_price($cost)),
                        'selected' => in_array($rate->get_id(), $chosen, true),
                    ];
                }
            }
        }

        $checkout = apply_filters('woo4etch/checkout_data', [
            'payment_methods' => $methods,
            'shipping_rates'  => $rates,
            'checkboxes'      => self::checkout_checkboxes(),
            'countries'       => $countries,
            'needs_shipping'  => $needs_shipping,
            // For the no-JS fallback: a hand-built form posting classically to
            // ?wc-ajax=checkout needs this as woocommerce-process-checkout-nonce.
            'nonce'           => wp_create_nonce('woocommerce-process_checkout'),
        ]);
        $data['checkout'] = $checkout;
        return $data;
    }

    /**
     * Germanized legal checkboxes for the current checkout, using the same
     * manager call the plugin's own blocks integration uses (locations
     * checkout, render context — only checkboxes that apply to THIS cart).
     * Empty when Germanized isn't active.
     *
     * @return array<int,array<string,string>>
     */
    private static function checkout_checkboxes() {
        if (!class_exists('WC_GZD_Legal_Checkbox_Manager')) {
            return [];
        }
        $manager = \WC_GZD_Legal_Checkbox_Manager::instance();
        // Same relevance filtering as Germanized's own blocks integration
        // (is_printable + its force-print filter), so both paths show the
        // identical set for the current cart.
        $force = apply_filters('woocommerce_gzd_checkout_block_checkboxes_force_print_checkboxes', ['privacy']);
        $out   = [];
        foreach ($manager->get_checkboxes(['locations' => 'checkout', 'sort' => true], 'render') as $id => $checkbox) {
            if (method_exists($checkbox, 'is_printable') && !$checkbox->is_printable() && !in_array((string) $id, (array) $force, true)) {
                continue;
            }
            $out[] = [
                'id'       => (string) $id,
                'label'    => wp_kses_post((string) $checkbox->get_label()),
                'error'    => self::plain((string) $checkbox->get_error_message()),
                'required' => method_exists($checkbox, 'is_mandatory') ? (bool) $checkbox->is_mandatory() : true,
            ];
        }
        return $out;
    }

    /**
     * Sample checkout data for the Etch builder canvas preview.
     *
     * @return array<string,mixed>
     */
    private static function sample_checkout_data() {
        return apply_filters('woo4etch/checkout_sample_data', [
            'payment_methods' => [
                ['id' => 'sample_card', 'title' => __('Card', 'woo4etch'), 'description' => __('Pay securely by card.', 'woo4etch'), 'icon' => '', 'selected' => true],
                ['id' => 'sample_transfer', 'title' => __('Bank transfer', 'woo4etch'), 'description' => __('Pay by direct bank transfer.', 'woo4etch'), 'icon' => '', 'selected' => false],
            ],
            'countries'       => [
                ['code' => 'DE', 'name' => 'Germany', 'selected' => true],
                ['code' => 'AT', 'name' => 'Austria', 'selected' => false],
            ],
            'shipping_rates'  => [
                ['id' => 'flat_rate:1', 'package' => 0, 'label' => __('Standard shipping', 'woo4etch'), 'price' => self::plain(wc_price(4.9)), 'selected' => true],
                ['id' => 'flat_rate:2', 'package' => 0, 'label' => __('Express', 'woo4etch'), 'price' => self::plain(wc_price(12.9)), 'selected' => false],
            ],
            'checkboxes'      => [
                ['id' => 'terms', 'label' => __('I agree to the terms and conditions.', 'woo4etch'), 'error' => __('Please accept the terms and conditions.', 'woo4etch')],
            ],
            'needs_shipping'  => true,
            'nonce'           => '',
        ]);
    }

    /**
     * Sample cart data for the Etch builder canvas preview.
     *
     * @param string $size
     * @return array<string,mixed>
     */
    private static function sample_cart_data($size) {
        $ph = function_exists('wc_placeholder_img_src') ? wc_placeholder_img_src($size) : '';
        $row = static function ($key, $name, $qty, $unit, $line, $meta = '', $on_sale = false) use ($ph) {
            return [
                'key' => $key, 'id' => 0, 'name' => $name, 'sku' => strtoupper($key),
                'meta' => $meta,
                'quantity' => $qty,
                'price' => self::plain(wc_price($unit)),
                'subtotal' => self::plain(wc_price($line)),
                'permalink' => '#', 'image' => $ph, 'remove_url' => '#', 'on_sale' => $on_sale,
                'sold_individually' => 'sample-cap' === $key,
            ];
        };
        // Varied rows so the builder preview reflects real carts: a variation,
        // a multi-quantity line, and an on-sale item.
        return apply_filters('woo4etch/cart_sample_data', [
            'cart_items'    => [
                $row('sample-tee', __('Logo T-Shirt', 'woo4etch'), 2, 18, 36, 'Color: Blue, Size: M'),
                $row('sample-hoodie', __('Zip Hoodie', 'woo4etch'), 1, 45, 45, 'Color: Green'),
                $row('sample-cap', __('Baseball Cap', 'woo4etch'), 1, 18, 16, '', true),
            ],
            'cart_count'    => 4,
            'cart_subtotal' => self::plain(wc_price(97)),
            // Sample coupon so the discount line can be styled in the builder.
            'cart_coupons'  => [
                ['code' => 'welcome10', 'amount' => self::plain(wc_price(5)), 'remove_url' => '#'],
            ],
            'cart_discount' => self::plain(wc_price(5)),
            'cart_shipping_total' => self::plain(wc_price(4.9)),
            'cart_total'    => self::plain(wc_price(96.9)),
            'cart_is_empty' => false,
        ]);
    }

    /**
     * Cross-sell products ("You may also like") for {options.cross_sells}.
     * Real products from the cart; sample products in the Etch builder.
     *
     * @param WC_Cart|null $cart
     * @param string       $size
     * @param bool         $builder
     * @return array<int,array<string,mixed>>
     */
    private static function cart_cross_sells($cart, $size, $builder) {
        $out = [];

        $payload = static function (WC_Product $p) use ($size) {
            $img_id = $p->get_image_id();
            $img    = $img_id ? wp_get_attachment_image_url($img_id, $size) : '';
            if (!$img && function_exists('wc_placeholder_img_src')) {
                $img = wc_placeholder_img_src($size);
            }
            return [
                'id'              => $p->get_id(),
                'name'            => $p->get_name(),
                'price'           => self::plain(wc_price(wc_get_price_to_display($p))),
                'image'           => (string) $img,
                'permalink'       => get_permalink($p->get_id()),
                'add_to_cart_url' => $p->add_to_cart_url(),
                'on_sale'         => $p->is_on_sale(),
            ];
        };

        $limit = (int) apply_filters('woo4etch/cross_sells_limit', 4);

        // Never suggest what can't be bought: out-of-stock (no backorders)
        // or otherwise non-purchasable products are excluded in both paths.
        $sellable = static function ($p) {
            return $p instanceof WC_Product && $p->is_visible() && $p->is_in_stock() && $p->is_purchasable();
        };

        if ($cart instanceof WC_Cart) {
            $ids = (array) apply_filters('woocommerce_cross_sells_total', $cart->get_cross_sells());
            foreach ($ids as $pid) {
                if (count($out) >= $limit) {
                    break;
                }
                $p = wc_get_product($pid);
                if (!$sellable($p)) {
                    continue;
                }
                $out[] = $payload($p);
            }

            // No cross-sells maintained on the cart's products (Woo: product →
            // Linked Products) → fall back to random visible products so the
            // "You may also like" section isn't empty on real shops. Disable:
            // add_filter('woo4etch/cross_sells_fallback', '__return_false');
            if (empty($out) && !$cart->is_empty() && apply_filters('woo4etch/cross_sells_fallback', true)) {
                $exclude = [];
                foreach ($cart->get_cart() as $cart_item) {
                    $prod = $cart_item['data'] ?? null;
                    if ($prod instanceof WC_Product) {
                        $exclude[] = $prod->get_parent_id() ? $prod->get_parent_id() : $prod->get_id();
                    }
                }
                $random = wc_get_products([
                    'status'       => 'publish',
                    'limit'        => $limit * 2, // headroom: stock is re-checked below
                    'orderby'      => 'rand',
                    'exclude'      => array_unique($exclude),
                    'visibility'   => 'catalog',
                    'stock_status' => 'instock',
                ]);
                foreach ($random as $p) {
                    if (count($out) >= $limit) {
                        break;
                    }
                    if ($sellable($p)) {
                        $out[] = $payload($p);
                    }
                }
            }
        }

        if (empty($out) && $builder) {
            $ph  = function_exists('wc_placeholder_img_src') ? wc_placeholder_img_src($size) : '';
            $s   = static function ($name, $price, $sale = false) use ($ph) {
                return [
                    'id' => 0, 'name' => $name, 'price' => self::plain(wc_price($price)),
                    'image' => $ph, 'permalink' => '#', 'add_to_cart_url' => '#', 'on_sale' => $sale,
                ];
            };
            $out = apply_filters('woo4etch/cross_sells_sample', [
                $s(__('Matching Socks', 'woo4etch'), 9),
                $s(__('Care Kit', 'woo4etch'), 12, true),
                $s(__('Gift Wrap', 'woo4etch'), 5),
            ]);
        }

        return $out;
    }

    /**
     * Add My Account + order data to Etch's `option` root, so the My Account and
     * thank-you/order pages can be built as pure Etch loops. Hooked to
     * etch/dynamic_data/option.
     *
     * Exposes:
     *   {options.is_logged_in}     — bool (true in the builder so sections preview)
     *   {options.account_menu}     — array: key, label, url, is_active
     *   {options.account_endpoint} — current endpoint key ('dashboard', 'orders', …;
     *                                '' outside the account area)
     *   {options.account_orders}   — array: id, number, date, status, status_name,
     *                                total, item_count, view_url
     *   {options.order}          — current order (thank-you / view-order):
     *                              number, date, status, status_name, total, email,
     *                              payment_method, billing_address, items[]
     *
     * Real data on the frontend; sample data in the Etch builder so the loops
     * preview. Disable: add_filter('woo4etch/expose_account_data','__return_false').
     *
     * @param array<string,mixed> $data
     * @return array<string,mixed>
     */
    public static function expose_account_order_data($data) {
        if (!is_array($data)) {
            $data = [];
        }
        if (!apply_filters('woo4etch/expose_account_data', true)) {
            return $data;
        }

        $builder = self::is_etch_builder();
        $size    = apply_filters('woo4etch/cart_image_size', 'woocommerce_thumbnail');

        // Login state, so account layouts can gate their sections and show a
        // login form to guests. True in the builder so the sections preview.
        $data['is_logged_in'] = $builder ? true : is_user_logged_in();

        // Account navigation (cheap, always available).
        $data['account_menu'] = self::account_menu();

        // Current My Account endpoint as a scalar, so one Etch layout can switch
        // its content area per endpoint: {#if options.account_endpoint === "orders"}.
        // 'dashboard' on the account root, the endpoint key on sub-pages
        // ('orders', 'downloads', 'edit-address', …), '' outside the account area.
        // In the builder it defaults to 'dashboard' so endpoint sections preview.
        $endpoint = '';
        if (function_exists('is_account_page') && is_account_page()) {
            $current  = (function_exists('WC') && WC() && WC()->query) ? (string) WC()->query->get_current_endpoint() : '';
            $endpoint = ('' !== $current) ? $current : 'dashboard';
        } elseif ($builder) {
            $endpoint = apply_filters('woo4etch/account_endpoint_sample', 'dashboard');
        }
        $data['account_endpoint'] = $endpoint;

        // Orders list — query only on the account area or in the builder.
        if ($builder) {
            $data['account_orders'] = self::sample_orders();
        } elseif (function_exists('is_account_page') && is_account_page() && is_user_logged_in()) {
            $data['account_orders'] = self::account_orders();
        } else {
            $data['account_orders'] = [];
        }

        // Current order (thank-you / view-order endpoint), or a sample in the builder.
        $order = self::resolve_order([]);
        if ($order instanceof WC_Order) {
            $data['order'] = self::order_payload($order, $size);
        } elseif ($builder) {
            $data['order'] = self::sample_order($size);
        } else {
            $data['order'] = null;
        }

        return apply_filters('woo4etch/account_order_data', $data);
    }

    /**
     * Shop/archive data for filterable product grids. Hooked to
     * etch/dynamic_data/option.
     *
     * Exposes:
     *   {options.shop_categories} — array: id, name, slug, url, count, image,
     *                               is_active (matches the queried term)
     *   {options.shop_max_price}  — highest catalog price (plain number, for
     *                               "highest price is X" hints / input max)
     *   {options.filter_min_price} / {options.filter_max_price}
     *                             — the current ?min_price/?max_price values,
     *                               for pre-filling the filter form
     *
     * The filtering itself is 100% native WooCommerce: a GET form submitting
     * min_price/max_price (and filter_<attribute> checkboxes) to the shop URL
     * filters the main product query server-side — the Etch main-query loop
     * picks it up automatically. No AJAX, no plugin JS.
     *
     * Disable: add_filter('woo4etch/expose_shop_data','__return_false').
     *
     * @param array<string,mixed> $data
     * @return array<string,mixed>
     */
    public static function expose_shop_data($data) {
        if (!is_array($data)) {
            $data = [];
        }
        if (!apply_filters('woo4etch/expose_shop_data', true) || !function_exists('wc_get_page_permalink')) {
            return $data;
        }

        $builder = self::is_etch_builder();

        // Categories (cheap, cached taxonomy query) — always available so
        // pills/menus can live outside the shop archive too.
        $active_id = 0;
        if (function_exists('is_product_taxonomy') && is_product_taxonomy()) {
            $qo        = get_queried_object();
            $active_id = $qo instanceof WP_Term ? (int) $qo->term_id : 0;
        }
        // Top-level categories only (subcategories belong on the term pages),
        // without WooCommerce's default "Uncategorized" bucket. Reshape via
        // the woo4etch/shop_data filter if you need the full tree.
        $terms = get_terms([
            'taxonomy'   => 'product_cat',
            'hide_empty' => true,
            'parent'     => 0,
            'exclude'    => [(int) get_option('default_product_cat', 0)],
        ]);
        $categories = [];
        if (!is_wp_error($terms)) {
            foreach ($terms as $term) {
                // Default/uncategorized buckets (slug varies by locale).
                if (in_array($term->slug, apply_filters('woo4etch/shop_categories_exclude_slugs', ['uncategorized', 'unkategorisiert', 'uncategorised']), true)) {
                    continue;
                }
                $thumb_id = (int) get_term_meta($term->term_id, 'thumbnail_id', true);
                $image    = $thumb_id ? wp_get_attachment_image_url($thumb_id, 'woocommerce_thumbnail') : '';
                if (!$image && function_exists('wc_placeholder_img_src')) {
                    $image = wc_placeholder_img_src('woocommerce_thumbnail');
                }
                $categories[] = [
                    'id'        => (int) $term->term_id,
                    'name'      => $term->name,
                    'slug'      => $term->slug,
                    'url'       => (string) get_term_link($term),
                    'count'     => (int) $term->count,
                    'image'     => (string) $image,
                    'is_active' => $active_id === (int) $term->term_id,
                ];
            }
        }
        $data['shop_categories'] = $categories;

        // Price bounds + current filter values — archive pages and builder only.
        $on_archive = (function_exists('is_shop') && is_shop())
            || (function_exists('is_product_taxonomy') && is_product_taxonomy());
        if ($on_archive || $builder) {
            global $wpdb;
            $max = $wpdb->get_var("SELECT MAX(max_price) FROM {$wpdb->wc_product_meta_lookup}");
            $data['shop_max_price']     = $max ? self::plain(wc_price(ceil((float) $max))) : '';
            $data['shop_max_price_raw'] = $max ? (string) (int) ceil((float) $max) : '';
            // phpcs:disable WordPress.Security.NonceVerification.Recommended -- Woo's own public filter params.
            $data['filter_min_price'] = isset($_GET['min_price']) ? (string) absint(wp_unslash($_GET['min_price'])) : '';
            $data['filter_max_price'] = isset($_GET['max_price']) ? (string) absint(wp_unslash($_GET['max_price'])) : '';
            // phpcs:enable
        } else {
            $data['shop_max_price']     = '';
            $data['shop_max_price_raw'] = '';
            $data['filter_min_price']   = '';
            $data['filter_max_price']   = '';
        }

        return apply_filters('woo4etch/shop_data', $data);
    }

    /**
     * True when Woo's archive filters should be re-applied to this query:
     * a frontend, non-main product query while a Woo archive is displayed.
     *
     * @param WP_Query $query
     * @return bool
     */
    private static function is_filterable_secondary_query($query) {
        if (is_admin() || !$query instanceof WP_Query || $query->is_main_query()) {
            return false;
        }
        if (!apply_filters('woo4etch/filter_secondary_product_queries', true)) {
            return false;
        }
        if (!function_exists('is_shop') || !(is_shop() || (function_exists('is_product_taxonomy') && is_product_taxonomy()))) {
            return false;
        }
        $post_type = $query->get('post_type');
        if ('product' === $post_type || (is_array($post_type) && in_array('product', $post_type, true))) {
            return true;
        }
        // Taxonomy archives leave post_type empty (the taxonomy implies it) —
        // accept such queries only when they reference a product taxonomy,
        // which the cloned main query on a term archive does.
        if ('' === $post_type || null === $post_type || 'any' === $post_type) {
            if ($query->get('product_cat') || $query->get('product_tag')) {
                return true;
            }
            foreach ((array) $query->get('tax_query') as $clause) {
                if (is_array($clause) && isset($clause['taxonomy'])
                    && ('product_cat' === $clause['taxonomy'] || 'product_tag' === $clause['taxonomy'] || 0 === strpos((string) $clause['taxonomy'], 'pa_'))) {
                    return true;
                }
            }
        }
        return false;
    }

    /**
     * Layered-nav attribute filters (?filter_<attribute>=a,b&query_type_…)
     * for secondary product queries (Etch loops). Mirrors what WC_Query does
     * for the main query.
     *
     * @param WP_Query $query
     */
    public static function apply_attribute_filters_to_secondary_queries($query) {
        if (!self::is_filterable_secondary_query($query) || !class_exists('WC_Query')) {
            return;
        }
        $chosen = WC_Query::get_layered_nav_chosen_attributes();
        if (!$chosen) {
            return;
        }
        $tax_query = (array) $query->get('tax_query');
        foreach ($chosen as $taxonomy => $data) {
            $tax_query[] = [
                'taxonomy'         => $taxonomy,
                'field'            => 'slug',
                'terms'            => $data['terms'],
                'operator'         => ('and' === $data['query_type']) ? 'AND' : 'IN',
                'include_children' => false,
            ];
        }
        $query->set('tax_query', $tax_query);
    }

    /**
     * Page-size sync for secondary product queries. Etch's main-query loop
     * re-runs the request as a NEW WP_Query, which never passes through
     * WC_Query::product_query() — Woo's per-page (the loop_shop_per_page
     * filter, default columns × rows from the Customizer, typically 16)
     * applies to the main query only, so the loop falls back to the blog
     * reading setting (usually 10). [woo_pagination] counts pages from the
     * MAIN query's per-page; with the two out of sync, tail products are
     * unreachable ("missing" products past the last pagination link).
     *
     * Only queries that arrive WITHOUT an explicit page size are touched —
     * an Etch wp-query loop (or any custom loop) that sets its own
     * posts_per_page keeps it.
     *
     * @param WP_Query $query
     */
    public static function sync_per_page_on_secondary_queries($query) {
        if (!self::is_filterable_secondary_query($query)) {
            return;
        }
        if (!apply_filters('woo4etch/sync_secondary_per_page', true)) {
            return;
        }
        $raw = (array) $query->query;
        if (isset($raw['posts_per_page']) || isset($raw['numberposts']) || !empty($raw['nopaging'])) {
            return;
        }
        $main     = isset($GLOBALS['wp_query']) ? $GLOBALS['wp_query'] : null;
        $per_page = ($main instanceof WP_Query) ? (int) $main->get('posts_per_page') : 0;
        if ($per_page === 0 && function_exists('wc_get_default_products_per_row') && function_exists('wc_get_default_product_rows_per_page')) {
            // Same computation WC_Query::product_query() uses for the main query.
            $per_page = (int) apply_filters('loop_shop_per_page', wc_get_default_products_per_row() * wc_get_default_product_rows_per_page());
        }
        if ($per_page !== 0) {
            $query->set('posts_per_page', $per_page);
        }
    }

    /**
     * Price filter (?min_price/?max_price) for secondary product queries —
     * WC implements it as posts_clauses on the main query only, so it is
     * replicated here against the product meta lookup table.
     *
     * @param array<string,string> $clauses
     * @param WP_Query             $query
     * @return array<string,string>
     */
    public static function apply_price_filter_to_secondary_queries($clauses, $query) {
        // phpcs:disable WordPress.Security.NonceVerification.Recommended -- Woo's own public filter params.
        if ((!isset($_GET['min_price']) && !isset($_GET['max_price'])) || !self::is_filterable_secondary_query($query)) {
            return $clauses;
        }
        global $wpdb;
        if (strpos((string) $clauses['join'], 'w4e_price_lookup') === false) {
            $clauses['join'] .= " LEFT JOIN {$wpdb->wc_product_meta_lookup} w4e_price_lookup ON {$wpdb->posts}.ID = w4e_price_lookup.product_id ";
        }
        // Same range semantics as WC_Query::price_filter_post_clauses.
        if (isset($_GET['min_price'])) {
            $clauses['where'] .= $wpdb->prepare(' AND w4e_price_lookup.max_price >= %f ', (float) wp_unslash($_GET['min_price']));
        }
        if (isset($_GET['max_price'])) {
            $clauses['where'] .= $wpdb->prepare(' AND w4e_price_lookup.min_price <= %f ', (float) wp_unslash($_GET['max_price']));
        }
        // phpcs:enable
        return $clauses;
    }

    /** My Account navigation items. */
    private static function account_menu() {
        if (!function_exists('wc_get_account_menu_items')) {
            return [];
        }
        $out = [];
        foreach (wc_get_account_menu_items() as $key => $label) {
            $classes = function_exists('wc_get_account_menu_item_classes') ? (array) wc_get_account_menu_item_classes($key) : [];
            $out[]   = [
                'key'       => $key,
                'label'     => $label,
                'url'       => ('customer-logout' === $key) ? wc_logout_url() : wc_get_account_endpoint_url($key),
                'is_active' => in_array('is-active', $classes, true),
            ];
        }
        return $out;
    }

    /** The current user's recent orders (My Account → Orders). */
    private static function account_orders() {
        if (!function_exists('wc_get_orders')) {
            return [];
        }
        $orders = wc_get_orders([
            'customer' => get_current_user_id(),
            'limit'    => (int) apply_filters('woo4etch/account_orders_limit', 10),
            'orderby'  => 'date',
            'order'    => 'DESC',
        ]);
        $out = [];
        foreach ($orders as $o) {
            if ($o instanceof WC_Order) {
                $out[] = self::order_row($o);
            }
        }
        return $out;
    }

    /** One order summary row for the orders list. */
    private static function order_row(WC_Order $o) {
        return [
            'id'          => $o->get_id(),
            'number'      => $o->get_order_number(),
            'date'        => wc_format_datetime($o->get_date_created()),
            'status'      => $o->get_status(),
            'status_name' => function_exists('wc_get_order_status_name') ? wc_get_order_status_name($o->get_status()) : $o->get_status(),
            'total'       => self::plain($o->get_formatted_order_total()),
            'item_count'  => $o->get_item_count(),
            'view_url'    => $o->get_view_order_url(),
        ];
    }

    private static function sample_orders() {
        return apply_filters('woo4etch/account_orders_sample', [
            ['id' => 0, 'number' => '1042', 'date' => 'June 1, 2026',  'status' => 'completed',  'status_name' => 'Completed',  'total' => self::plain(wc_price(78)),  'item_count' => 3, 'view_url' => '#'],
            ['id' => 0, 'number' => '1031', 'date' => 'May 24, 2026',  'status' => 'processing', 'status_name' => 'Processing', 'total' => self::plain(wc_price(45)),  'item_count' => 1, 'view_url' => '#'],
            ['id' => 0, 'number' => '1009', 'date' => 'May 12, 2026',  'status' => 'completed',  'status_name' => 'Completed',  'total' => self::plain(wc_price(120)), 'item_count' => 2, 'view_url' => '#'],
        ]);
    }

    /** Full order payload for the thank-you / view-order page. */
    private static function order_payload(WC_Order $o, $size) {
        $items = [];
        foreach ($o->get_items() as $it) {
            $p   = $it->get_product();
            $img = ($p instanceof WC_Product && $p->get_image_id()) ? wp_get_attachment_image_url($p->get_image_id(), $size) : '';
            if (!$img && function_exists('wc_placeholder_img_src')) {
                $img = wc_placeholder_img_src($size);
            }
            $items[] = [
                'name'     => $it->get_name(),
                'quantity' => $it->get_quantity(),
                'total'    => self::plain(wc_price($it->get_total())),
                'image'    => (string) $img,
            ];
        }
        return [
            'number'          => $o->get_order_number(),
            'date'            => wc_format_datetime($o->get_date_created()),
            'status'          => $o->get_status(),
            'status_name'     => function_exists('wc_get_order_status_name') ? wc_get_order_status_name($o->get_status()) : $o->get_status(),
            'total'           => self::plain($o->get_formatted_order_total()),
            'email'           => $o->get_billing_email(),
            'payment_method'  => $o->get_payment_method_title(),
            'billing_address' => self::plain($o->get_formatted_billing_address()),
            'items'           => $items,
        ];
    }

    private static function sample_order($size) {
        $ph = function_exists('wc_placeholder_img_src') ? wc_placeholder_img_src($size) : '';
        return apply_filters('woo4etch/order_sample', [
            'number'          => '1042',
            'date'            => 'June 1, 2026',
            'status'          => 'processing',
            'status_name'     => 'Processing',
            'total'           => self::plain(wc_price(81)),
            'email'           => 'jane@example.com',
            'payment_method'  => 'Direct bank transfer',
            'billing_address' => "Jane Doe, 123 Demo Street, 12345 Sampletown",
            'items'           => [
                ['name' => 'Logo T-Shirt', 'quantity' => 2, 'total' => self::plain(wc_price(36)), 'image' => $ph],
                ['name' => 'Zip Hoodie',   'quantity' => 1, 'total' => self::plain(wc_price(45)), 'image' => $ph],
            ],
        ]);
    }

    /**
     * True inside the Etch builder — either the canvas (?etch=magic) or an
     * Etch REST request (the builder fetches dynamic data via /etch-api/*, e.g.
     * GET /etch-api/options, which is where the loop preview gets its data).
     */
    private static function is_etch_builder() {
        // phpcs:disable WordPress.Security.NonceVerification.Recommended
        if (isset($_GET['etch']) && 'magic' === sanitize_text_field(wp_unslash($_GET['etch']))) {
            return true;
        }
        // phpcs:enable WordPress.Security.NonceVerification.Recommended
        if (defined('REST_REQUEST') && REST_REQUEST) {
            $uri = isset($_SERVER['REQUEST_URI']) ? (string) wp_unslash($_SERVER['REQUEST_URI']) : '';
            if (false !== strpos($uri, 'etch-api')) {
                return true;
            }
        }
        return false;
    }

    /** Strip tags + decode entities so formatted Woo prices are clean strings for Etch. */
    private static function plain($html) {
        // Drop screen-reader spans BEFORE stripping tags — wc_price() sale
        // markup carries "Original price was: …" helper text that would
        // otherwise leak into the visible string.
        $html = preg_replace('/<span[^>]*class="[^"]*screen-reader-text[^"]*"[^>]*>.*?<\/span>/s', '', (string) $html);
        return html_entity_decode(wp_strip_all_tags($html), ENT_QUOTES);
    }
}

