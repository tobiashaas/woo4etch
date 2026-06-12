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
        add_action('admin_post_woo4etch_install_layout', [__CLASS__, 'handle_install_layout']);
        add_action('admin_post_woo4etch_save_settings', [__CLASS__, 'handle_save_settings']);
    }

    /**
     * admin-post handler: persist the plugin settings (currently the
     * "disable WooCommerce styles" checkbox).
     */
    public static function handle_save_settings() {
        if (!current_user_can(apply_filters('woo4etch/admin_capability', 'manage_woocommerce'))) {
            wp_die(esc_html__('You do not have permission to do this.', 'woo4etch'));
        }
        check_admin_referer('woo4etch_save_settings');

        $settings = (array) get_option('woo4etch_settings', []);
        $settings['disable_woo_styles'] = !empty($_POST['disable_woo_styles']);
        update_option('woo4etch_settings', $settings);

        $redirect = wp_get_referer() ?: admin_url('admin.php?page=' . self::PAGE_SLUG);
        $redirect = add_query_arg('w4e_settings_saved', '1', remove_query_arg('w4e_settings_saved', $redirect));
        wp_safe_redirect($redirect);
        exit;
    }

    /**
     * admin-post handler: install a ready-made layout as an Etch pattern.
     */
    public static function handle_install_layout() {
        if (!current_user_can(apply_filters('woo4etch/admin_capability', 'manage_woocommerce'))) {
            wp_die(esc_html__('You do not have permission to do this.', 'woo4etch'));
        }

        $slug = isset($_POST['layout']) ? sanitize_key(wp_unslash($_POST['layout'])) : '';
        check_admin_referer('woo4etch_install_layout_' . $slug);

        $result   = Woo4Etch_Layouts::install($slug);
        $redirect = wp_get_referer() ?: admin_url('admin.php?page=' . self::PAGE_SLUG);
        $redirect = remove_query_arg(['w4e_installed', 'w4e_error'], $redirect);

        if (is_wp_error($result)) {
            $redirect = add_query_arg('w4e_error', rawurlencode($result->get_error_message()), $redirect);
        } else {
            $redirect = add_query_arg('w4e_installed', rawurlencode($slug), $redirect);
        }

        wp_safe_redirect($redirect);
        exit;
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
            . '.woo4etch-shortcodes .woo4etch-installed{color:#00a32a;font-weight:700;margin-left:4px;}'
        );
    }

    /**
     * Settings: toggles that would otherwise need a PHP snippet.
     */
    private static function render_settings_section() {
        // phpcs:disable WordPress.Security.NonceVerification.Recommended -- read-only notice flag.
        $saved = isset($_GET['w4e_settings_saved']);
        // phpcs:enable
        $settings = (array) get_option('woo4etch_settings', []);
        $disabled_styles = !empty($settings['disable_woo_styles']);
        ?>
        <h2 class="category-heading"><?php esc_html_e('Settings', 'woo4etch'); ?></h2>

        <?php if ($saved) : ?>
            <div class="notice notice-success inline"><p><?php esc_html_e('Settings saved.', 'woo4etch'); ?></p></div>
        <?php endif; ?>

        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
            <input type="hidden" name="action" value="woo4etch_save_settings">
            <?php wp_nonce_field('woo4etch_save_settings'); ?>
            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row"><?php esc_html_e('WooCommerce styles', 'woo4etch'); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" name="disable_woo_styles" value="1" <?php checked($disabled_styles); ?>>
                            <?php esc_html_e('Disable WooCommerce default styles', 'woo4etch'); ?>
                        </label>
                        <p class="description">
                            <?php esc_html_e('Removes all three WooCommerce stylesheets (layout, smallscreen, general) so your Etch styles start from a blank slate — no specificity fights, no !important. Uncheck to bring the Woo default styling back at any time. Note: payment gateways and some extensions enqueue their own CSS and are not affected. Developers can override this via the woo4etch/disable_woo_styles filter.', 'woo4etch'); ?>
                        </p>
                    </td>
                </tr>
            </table>
            <?php submit_button(__('Save settings', 'woo4etch')); ?>
        </form>
        <?php
    }

    /**
     * Ready-made layouts: one-click pattern install + copy/paste JSON.
     */
    private static function render_layouts_section() {
        // phpcs:disable WordPress.Security.NonceVerification.Recommended -- read-only notice flags.
        $installed_flag = isset($_GET['w4e_installed']) ? sanitize_key(wp_unslash($_GET['w4e_installed'])) : '';
        $error_flag     = isset($_GET['w4e_error']) ? sanitize_text_field(wp_unslash($_GET['w4e_error'])) : '';
        // phpcs:enable
        ?>
        <h2 class="category-heading"><?php esc_html_e('Ready-made layouts', 'woo4etch'); ?></h2>

        <?php if ($installed_flag !== '') : ?>
            <div class="notice notice-success inline"><p>
                <?php
                printf(
                    /* translators: %s: layout slug */
                    esc_html__('Layout “%s” installed. In the Etch builder, open the pattern library and insert it from the “Woo4Etch” category.', 'woo4etch'),
                    esc_html($installed_flag)
                );
                ?>
            </p></div>
        <?php endif; ?>
        <?php if ($error_flag !== '') : ?>
            <div class="notice notice-error inline"><p><?php echo esc_html($error_flag); ?></p></div>
        <?php endif; ?>

        <p class="woo4etch-intro">
            <?php esc_html_e('Complete, editable Etch layouts for every shop area — built on the dynamic-data bridges, so they preview live in the builder. “Install as pattern” adds them to Etch’s pattern library (as detached copies you can restyle freely; existing styles with the same selectors are reused, never overwritten). “Copy JSON” puts the layout on your clipboard — paste it straight onto the Etch canvas.', 'woo4etch'); ?>
        </p>

        <table class="widefat striped">
            <thead>
                <tr>
                    <th scope="col" style="width:12%"><?php esc_html_e('Area', 'woo4etch'); ?></th>
                    <th scope="col" style="width:28%"><?php esc_html_e('Layout', 'woo4etch'); ?></th>
                    <th scope="col"><?php esc_html_e('Contents', 'woo4etch'); ?></th>
                    <th scope="col" style="width:24%"><?php esc_html_e('Actions', 'woo4etch'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach (Woo4Etch_Layouts::catalog() as $slug => $meta) : ?>
                    <?php
                    $json = Woo4Etch_Layouts::clipboard_json($slug);
                    if ($json === '') {
                        continue;
                    }
                    $is_installed = Woo4Etch_Layouts::installed_post_id($slug) > 0;
                    ?>
                    <tr>
                        <td><?php echo esc_html($meta['area']); ?></td>
                        <td><strong><?php echo esc_html($meta['name']); ?></strong></td>
                        <td><?php echo esc_html($meta['description']); ?></td>
                        <td>
                            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="display:inline">
                                <input type="hidden" name="action" value="woo4etch_install_layout">
                                <input type="hidden" name="layout" value="<?php echo esc_attr($slug); ?>">
                                <?php wp_nonce_field('woo4etch_install_layout_' . $slug); ?>
                                <button type="submit" class="button button-small <?php echo $is_installed ? '' : 'button-primary'; ?>">
                                    <?php $is_installed ? esc_html_e('Reinstall pattern', 'woo4etch') : esc_html_e('Install as pattern', 'woo4etch'); ?>
                                </button>
                            </form>
                            <button type="button"
                                    class="button button-small woo4etch-copy-json"
                                    data-copy-target="woo4etch-layout-<?php echo esc_attr($slug); ?>">
                                <?php esc_html_e('Copy JSON', 'woo4etch'); ?>
                            </button>
                            <?php if ($is_installed) : ?>
                                <span class="woo4etch-installed" aria-label="<?php esc_attr_e('Installed', 'woo4etch'); ?>">✓</span>
                            <?php endif; ?>
                            <textarea id="woo4etch-layout-<?php echo esc_attr($slug); ?>"
                                      readonly
                                      style="display:none"
                                      aria-hidden="true"><?php echo esc_textarea($json); ?></textarea>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php
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

            <?php self::render_settings_section(); ?>

            <?php self::render_layouts_section(); ?>

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
            document.querySelectorAll('.woo4etch-copy-json').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    var el = document.getElementById(btn.getAttribute('data-copy-target'));
                    if (!el || !navigator.clipboard) return;
                    navigator.clipboard.writeText(el.value).then(function () {
                        var original = btn.textContent;
                        btn.textContent = '<?php echo esc_js(__('Copied — paste it in Etch', 'woo4etch')); ?>';
                        setTimeout(function () { btn.textContent = original; }, 2500);
                    });
                });
            });
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
