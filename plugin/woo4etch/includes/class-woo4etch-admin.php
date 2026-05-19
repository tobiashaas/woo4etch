<?php
/**
 * Admin UI: shortcode reference under the Etch menu (when available).
 *
 * @package Woo4Etch
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Registers the Woo4Etch shortcode overview page.
 */
final class Woo4Etch_Admin {

    const PAGE_SLUG = 'woo4etch-shortcodes';

    /**
     * Hook admin menu registration.
     */
    public static function init() {
        add_action('admin_menu', [__CLASS__, 'register_menu'], 99);
        add_action('admin_enqueue_scripts', [__CLASS__, 'enqueue_assets']);
    }

    /**
     * Attach under Etch when present; otherwise under WooCommerce.
     */
    public static function register_menu() {
        $parent = self::resolve_parent_slug();

        add_submenu_page(
            $parent,
            __('Woo4Etch Shortcodes', 'woo4etch'),
            __('Woo4Etch', 'woo4etch'),
            apply_filters('woo4etch/admin_capability', 'manage_woocommerce'),
            self::PAGE_SLUG,
            [__CLASS__, 'render_page']
        );
    }

    /**
     * Find Etch's top-level admin menu slug, or fall back to WooCommerce.
     *
     * @return string Parent slug for add_submenu_page().
     */
    private static function resolve_parent_slug() {
        $forced = apply_filters('woo4etch/admin_parent_slug', '');
        if (is_string($forced) && $forced !== '') {
            return $forced;
        }

        $detected = self::detect_etch_menu_slug();
        if ($detected !== null) {
            return $detected;
        }

        return 'woocommerce';
    }

    /**
     * Scan registered admin menus for an Etch top-level item.
     *
     * @return string|null Menu slug.
     */
    private static function detect_etch_menu_slug() {
        global $menu;

        if (!is_array($menu)) {
            return null;
        }

        $preferred = apply_filters('woo4etch/etch_menu_slugs', [
            'etch',
            'etch-builder',
            'etch-settings',
            'etchwp',
        ]);

        foreach ($menu as $item) {
            if (!isset($item[2])) {
                continue;
            }

            $slug = (string) $item[2];

            if (in_array($slug, $preferred, true)) {
                return $slug;
            }

            $title = isset($item[0]) ? wp_strip_all_tags((string) $item[0]) : '';
            if ($title !== '' && stripos($title, 'etch') !== false) {
                return $slug;
            }
        }

        return null;
    }

    /**
     * Minimal styles on our admin page only.
     *
     * @param string $hook_suffix Current admin page hook.
     */
    public static function enqueue_assets($hook_suffix) {
        if (strpos($hook_suffix, self::PAGE_SLUG) === false) {
            return;
        }

        wp_register_style('woo4etch-admin', false, [], Woo4Etch::VERSION);
        wp_enqueue_style('woo4etch-admin');
        wp_add_inline_style(
            'woo4etch-admin',
            '.woo4etch-shortcodes .widefat code{background:#f6f7f7;padding:2px 6px;border-radius:3px;}'
            . '.woo4etch-shortcodes .woo4etch-copy{margin-left:6px;}'
            . '.woo4etch-shortcodes .category-heading{margin:2em 0 .5em;font-size:1.1em;}'
            . '.woo4etch-shortcodes .woo4etch-intro{max-width:72em;}'
        );
    }

    /**
     * Render the shortcode reference.
     */
    public static function render_page() {
        if (!current_user_can(apply_filters('woo4etch/admin_capability', 'manage_woocommerce'))) {
            wp_die(esc_html__('You do not have permission to view this page.', 'woo4etch'));
        }

        $under_etch = self::detect_etch_menu_slug() !== null
            || in_array(self::resolve_parent_slug(), (array) apply_filters('woo4etch/etch_menu_slugs', ['etch', 'etch-builder', 'etch-settings', 'etchwp']), true);
        $catalog       = Woo4Etch::get_shortcode_catalog();
        $by_category   = [];

        foreach ($catalog as $tag => $entry) {
            $category = $entry['category'];
            if (!isset($by_category[$category])) {
                $by_category[$category] = [];
            }
            $by_category[$category][$tag] = $entry;
        }

        ?>
        <div class="wrap woo4etch-shortcodes">
            <h1><?php esc_html_e('Woo4Etch — Shortcodes', 'woo4etch'); ?></h1>

            <div class="woo4etch-intro notice notice-info inline">
                <p>
                    <?php
                    if ($under_etch) {
                        esc_html_e('Drop these shortcodes into Etch templates and pages wherever WooCommerce needs PHP output (forms, formatted prices, hooks, cart state).', 'woo4etch');
                    } else {
                        esc_html_e('Etch was not detected in the admin menu — this page is listed under WooCommerce. Install and activate Etch to move it under the Etch menu automatically.', 'woo4etch');
                    }
                    ?>
                </p>
                <p>
                    <?php esc_html_e('When id is omitted, shortcodes use the current product (global $product or the single product being viewed).', 'woo4etch'); ?>
                    <a href="<?php echo esc_url(WOO4ETCH_ETCH_AFFILIATE_URL); ?>" target="_blank" rel="noopener noreferrer sponsored">
                        <?php esc_html_e('Get Etch', 'woo4etch'); ?>
                    </a>
                    ·
                    <a href="https://github.com/tobiashaas/woo4etch" target="_blank" rel="noopener noreferrer">
                        <?php esc_html_e('Documentation on GitHub', 'woo4etch'); ?>
                    </a>
                </p>
            </div>

            <?php foreach ($by_category as $category => $shortcodes) : ?>
                <h2 class="category-heading"><?php echo esc_html($category); ?></h2>
                <table class="widefat striped">
                    <thead>
                        <tr>
                            <th scope="col" style="width:14%"><?php esc_html_e('Shortcode', 'woo4etch'); ?></th>
                            <th scope="col" style="width:22%"><?php esc_html_e('Attributes', 'woo4etch'); ?></th>
                            <th scope="col"><?php esc_html_e('Description', 'woo4etch'); ?></th>
                            <th scope="col" style="width:32%"><?php esc_html_e('Example', 'woo4etch'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($shortcodes as $tag => $entry) : ?>
                            <tr>
                                <td><code>[<?php echo esc_html($tag); ?>]</code></td>
                                <td><?php echo esc_html($entry['attributes']); ?></td>
                                <td><?php echo esc_html($entry['description']); ?></td>
                                <td>
                                    <code class="woo4etch-example" id="woo4etch-ex-<?php echo esc_attr($tag); ?>">
                                        <?php echo esc_html($entry['example']); ?>
                                    </code>
                                    <button type="button"
                                            class="button button-small woo4etch-copy"
                                            data-copy-target="woo4etch-ex-<?php echo esc_attr($tag); ?>">
                                        <?php esc_html_e('Copy', 'woo4etch'); ?>
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endforeach; ?>
        </div>

        <script>
        (function () {
            document.querySelectorAll('.woo4etch-copy').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    var id = btn.getAttribute('data-copy-target');
                    var el = document.getElementById(id);
                    if (!el) return;
                    var text = el.textContent.trim();
                    if (navigator.clipboard && navigator.clipboard.writeText) {
                        navigator.clipboard.writeText(text).then(function () {
                            btn.textContent = '<?php echo esc_js(__('Copied', 'woo4etch')); ?>';
                            setTimeout(function () {
                                btn.textContent = '<?php echo esc_js(__('Copy', 'woo4etch')); ?>';
                            }, 1500);
                        });
                    }
                });
            });
        })();
        </script>
        <?php
    }
}
