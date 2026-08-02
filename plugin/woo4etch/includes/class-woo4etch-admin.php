<?php
/**
 * Admin UI: the Woo4Etch page under the Etch menu (when available) —
 * Overview (shop status + WC templates), Layouts, Settings and the
 * shortcode reference, as tabs on one page.
 *
 * @package Woo4Etch
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Registers the Woo4Etch admin page.
 */
final class Woo4Etch_Admin {

    const PAGE_SLUG = 'woo4etch';

    /**
     * Slug the page lived under while it was only the shortcode reference —
     * still redirected so old bookmarks keep working.
     */
    const LEGACY_PAGE_SLUG = 'woo4etch-shortcodes';

    /**
     * Hook admin menu registration.
     */
    public static function init() {
        add_action('admin_menu', [__CLASS__, 'register_menu'], 99);
        add_action('admin_enqueue_scripts', [__CLASS__, 'enqueue_assets']);
        add_action('admin_post_woo4etch_request_component', [__CLASS__, 'handle_request_component']);
        add_action('admin_post_woo4etch_insert_into_page', [__CLASS__, 'handle_insert_into_page']);
        add_action('admin_post_woo4etch_push_layout', [__CLASS__, 'handle_push_layout']);
        add_action('admin_post_woo4etch_materialize_template', [__CLASS__, 'handle_materialize_template']);
        add_action('admin_post_woo4etch_save_settings', [__CLASS__, 'handle_save_settings']);
        add_action('admin_init', [__CLASS__, 'cleanup_legacy_patterns']);
        // Not admin_init: wp-admin/menu.php rejects unknown page slugs before
        // that hook ever fires, and this one runs right before it wp_die()s.
        add_action('admin_page_access_denied', [__CLASS__, 'redirect_legacy_slug']);
    }

    /**
     * The page's tabs. Overview first: "is my shop wired correctly" is the
     * question users open this page for.
     *
     * @return array<string,string> slug => label.
     */
    private static function tabs() {
        return [
            'overview'   => __('Overview', 'woo4etch'),
            'layouts'    => __('Layouts', 'woo4etch'),
            'settings'   => __('Settings', 'woo4etch'),
            'shortcodes' => __('Shortcodes', 'woo4etch'),
        ];
    }

    /**
     * Currently selected tab (validated against tabs()).
     *
     * @return string Tab slug.
     */
    private static function current_tab() {
        // phpcs:disable WordPress.Security.NonceVerification.Recommended -- read-only view switch.
        $tab = isset($_GET['tab']) ? sanitize_key(wp_unslash($_GET['tab'])) : 'overview';
        // phpcs:enable
        return array_key_exists($tab, self::tabs()) ? $tab : 'overview';
    }

    /**
     * Old bookmarks: ?page=woo4etch-shortcodes → ?page=woo4etch (same tab).
     */
    public static function redirect_legacy_slug() {
        // phpcs:disable WordPress.Security.NonceVerification.Recommended -- read-only redirect.
        if (!isset($_GET['page']) || self::LEGACY_PAGE_SLUG !== $_GET['page']) {
            return;
        }
        if (!current_user_can(apply_filters('woo4etch/admin_capability', 'manage_woocommerce'))) {
            return; // Let WordPress deny the page as it normally would.
        }
        $url = admin_url('admin.php?page=' . self::PAGE_SLUG);
        if (isset($_GET['tab'])) {
            $url = add_query_arg('tab', sanitize_key(wp_unslash($_GET['tab'])), $url);
        }
        // phpcs:enable
        wp_safe_redirect($url);
        exit;
    }

    /**
     * One-time cleanup after the "Install as pattern" route was removed
     * (1.5.0 betas): trash the library patterns the old installer created and
     * drop its tracking options. Guarded by the tracking option itself, so
     * this is a single cheap get_option() on sites that never had patterns —
     * and runs exactly once where they exist.
     *
     * Patterns are trashed, not force-deleted: a user who edited one can
     * still restore it from the trash. Inserted copies on pages were always
     * detached and are untouched.
     */
    public static function cleanup_legacy_patterns() {
        $installed = get_option('woo4etch_installed_layouts', null);
        if (null === $installed) {
            return;
        }

        foreach ((array) $installed as $post_id) {
            $post = get_post((int) $post_id);
            if ($post && 'wp_block' === $post->post_type && strpos($post->post_title, 'Woo4Etch — ') === 0) {
                wp_trash_post($post->ID);
            }
        }

        delete_option('woo4etch_installed_layouts');
        delete_option('woo4etch_layout_hashes');
        delete_option('woo4etch_layout_content_hashes');

        // The pattern-library category, if nothing else uses it anymore.
        $term = get_term_by('name', 'Woo4Etch', 'wp_pattern_category');
        if ($term && !is_wp_error($term)) {
            $still_used = get_posts([
                'post_type'      => 'wp_block',
                'post_status'    => 'publish',
                'posts_per_page' => 1,
                'fields'         => 'ids',
                'tax_query'      => [
                    ['taxonomy' => 'wp_pattern_category', 'field' => 'term_id', 'terms' => $term->term_id],
                ],
            ]);
            if (!$still_used) {
                wp_delete_term($term->term_id, 'wp_pattern_category');
            }
        }
    }

    /**
     * admin-post handler: persist the plugin settings ("disable WooCommerce
     * styles" and "enable gallery scripts" checkboxes).
     */
    public static function handle_save_settings() {
        if (!current_user_can(apply_filters('woo4etch/admin_capability', 'manage_woocommerce'))) {
            wp_die(esc_html__('You do not have permission to do this.', 'woo4etch'));
        }
        check_admin_referer('woo4etch_save_settings');

        $settings = (array) get_option('woo4etch_settings', []);
        $settings['disable_woo_styles']     = !empty($_POST['disable_woo_styles']);
        $settings['enable_gallery_scripts'] = !empty($_POST['enable_gallery_scripts']);
        $settings['enable_pills']           = !empty($_POST['enable_pills']);
        $settings['checkout_rate_limit']    = !empty($_POST['checkout_rate_limit']);
        $settings['store_api_cart']         = !empty($_POST['store_api_cart']);
        update_option('woo4etch_settings', $settings);

        $redirect = wp_get_referer() ?: admin_url('admin.php?page=' . self::PAGE_SLUG . '&tab=settings');
        $redirect = add_query_arg('w4e_settings_saved', '1', remove_query_arg('w4e_settings_saved', $redirect));
        wp_safe_redirect($redirect);
        exit;
    }

    /**
     * admin-post handler: append a layout's blocks directly to the
     * WooCommerce-assigned page (health check "insert" action).
     */
    public static function handle_insert_into_page() {
        if (!current_user_can(apply_filters('woo4etch/admin_capability', 'manage_woocommerce'))) {
            wp_die(esc_html__('You do not have permission to do this.', 'woo4etch'));
        }
        $slug = isset($_POST['layout']) ? sanitize_key(wp_unslash($_POST['layout'])) : '';
        $area = isset($_POST['area']) ? sanitize_key(wp_unslash($_POST['area'])) : '';
        check_admin_referer('woo4etch_insert_into_page_' . $area . '_' . $slug);

        $result = Woo4Etch_Health::insert_into_page($slug, $area);

        $redirect = wp_get_referer() ?: admin_url('admin.php?page=' . self::PAGE_SLUG);
        $redirect = remove_query_arg(['w4e_health_error', 'w4e_health_ok'], $redirect);
        if (is_wp_error($result)) {
            $redirect = add_query_arg('w4e_health_error', rawurlencode($result->get_error_message()), $redirect);
        } else {
            $redirect = add_query_arg('w4e_health_ok', rawurlencode($slug . ':' . $area), $redirect);
        }
        wp_safe_redirect($redirect);
        exit;
    }

    /**
     * admin-post handler: install (or refresh) the "Woo Notices" Etch
     * component (see class-woo4etch-components.php).
     */
    public static function handle_request_component() {
        if (!current_user_can(apply_filters('woo4etch/admin_capability', 'manage_woocommerce'))) {
            wp_die(esc_html__('You do not have permission to do this.', 'woo4etch'));
        }
        check_admin_referer('woo4etch_request_component');

        $result = Woo4Etch_Components::install();

        $redirect = wp_get_referer() ?: admin_url('admin.php?page=' . self::PAGE_SLUG);
        $redirect = remove_query_arg(['w4e_component_error'], $redirect);
        if (is_wp_error($result)) {
            $redirect = add_query_arg('w4e_component_error', rawurlencode($result->get_error_message()), $redirect);
        }
        wp_safe_redirect($redirect);
        exit;
    }

    /**
     * admin-post handler: push a layout straight onto its target — the
     * WooCommerce-assigned page or the Etch template rendering the area.
     */
    public static function handle_push_layout() {
        if (!current_user_can(apply_filters('woo4etch/admin_capability', 'manage_woocommerce'))) {
            wp_die(esc_html__('You do not have permission to do this.', 'woo4etch'));
        }

        $slug = isset($_POST['layout']) ? sanitize_key(wp_unslash($_POST['layout'])) : '';
        check_admin_referer('woo4etch_push_layout_' . $slug);

        $result   = Woo4Etch_Health::push($slug);
        $redirect = wp_get_referer() ?: admin_url('admin.php?page=' . self::PAGE_SLUG);
        $redirect = remove_query_arg(['w4e_pushed', 'w4e_push_error'], $redirect);

        if (is_wp_error($result)) {
            $redirect = add_query_arg('w4e_push_error', rawurlencode($result->get_error_message()), $redirect);
        } else {
            $redirect = add_query_arg('w4e_pushed', rawurlencode($result['note']), $redirect);
        }

        wp_safe_redirect($redirect);
        exit;
    }

    /**
     * Create a WooCommerce-registered template as a wp_template post and send
     * the user straight into the Etch builder for it.
     *
     * Reached from the "WooCommerce" group the plugin injects into Etch's
     * template hub — the hub lists a Woo template type that has no post yet,
     * and this endpoint turns it into an editable one on click. A GET link
     * (nonce-protected) rather than a form, because the caller is the builder
     * shell, not a wp-admin screen.
     */
    public static function handle_materialize_template() {
        if (!current_user_can(apply_filters('woo4etch/admin_capability', 'manage_woocommerce'))) {
            wp_die(esc_html__('Insufficient permissions.', 'woo4etch'));
        }
        $slug = isset($_REQUEST['template']) ? sanitize_key(wp_unslash($_REQUEST['template'])) : '';
        check_admin_referer('woo4etch_materialize_' . $slug);

        $result = class_exists('Woo4Etch_Health')
            ? Woo4Etch_Health::materialize_wc_template($slug)
            : new WP_Error('woo4etch_missing', __('Health module unavailable.', 'woo4etch'));

        // Success — open the new template in the builder. If it already
        // existed (a second click, a parallel tab), open that one instead of
        // reporting an error the user can do nothing with.
        $post_id = 0;
        if (!is_wp_error($result)) {
            $post_id = (int) $result;
        } elseif ('woo4etch_template_exists' === $result->get_error_code() && class_exists('Woo4Etch_Health')) {
            $existing = Woo4Etch_Health::find_template($slug);
            $post_id  = $existing ? (int) $existing->ID : 0;
        }

        if ($post_id > 0) {
            wp_safe_redirect(add_query_arg(['etch' => 'magic', 'post_id' => $post_id], home_url('/')));
            exit;
        }

        wp_safe_redirect(add_query_arg(
            'w4e_push_error',
            rawurlencode($result->get_error_message()),
            admin_url('admin.php?page=' . self::PAGE_SLUG)
        ));
        exit;
    }

    public static function register_menu() {
        $parent = self::resolve_parent_slug();

        add_submenu_page(
            $parent,
            __('Woo4Etch', 'woo4etch'),
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
            . '.woo4etch-shortcodes .woo4etch-tabs{margin-bottom:1.2em;}'
            . '.woo4etch-shortcodes .woo4etch-details{max-width:60em;margin:.4em 0 0;}'
            . '.woo4etch-shortcodes .woo4etch-details summary{cursor:pointer;color:#2271b1;font-size:12px;}'
            . '.woo4etch-shortcodes .woo4etch-header-links{font-size:13px;font-weight:400;margin-left:12px;}'
        );
    }

    /**
     * Print a one-line setting description with the long explanation folded
     * into a <details> block — keeps the settings tab scannable.
     *
     * @param string $short One-sentence summary (already translated).
     * @param string $long  Full explanation (already translated).
     */
    private static function setting_description($short, $long) {
        ?>
        <p class="description"><?php echo esc_html($short); ?></p>
        <details class="woo4etch-details">
            <summary><?php esc_html_e('Details', 'woo4etch'); ?></summary>
            <p class="description"><?php echo esc_html($long); ?></p>
        </details>
        <?php
    }

    /**
     * Settings: toggles that would otherwise need a PHP snippet.
     */
    private static function render_settings_section() {
        $settings = (array) get_option('woo4etch_settings', []);
        $disabled_styles = !empty($settings['disable_woo_styles']);
        $gallery_scripts = !empty($settings['enable_gallery_scripts']);
        $pills           = !empty($settings['enable_pills']);
        $rate_limit      = !empty($settings['checkout_rate_limit']);
        $store_api       = !isset($settings['store_api_cart']) || !empty($settings['store_api_cart']);
        ?>
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
                        <?php self::setting_description(
                            __('Removes all three WooCommerce stylesheets so your Etch styles start from a blank slate — no specificity fights, no !important.', 'woo4etch'),
                            __('Covers the layout, smallscreen and general stylesheets. Uncheck to bring the Woo default styling back at any time. Note: payment gateways and some extensions enqueue their own CSS and are not affected. Developers can override this via the woo4etch/disable_woo_styles filter.', 'woo4etch')
                        ); ?>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e('Product gallery scripts', 'woo4etch'); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" name="enable_gallery_scripts" value="1" <?php checked($gallery_scripts); ?>>
                            <?php esc_html_e('Enable WooCommerce gallery scripts (hover zoom, lightbox, thumbnail slider)', 'woo4etch'); ?>
                        </label>
                        <?php self::setting_description(
                            __('Loads WooCommerce\'s own zoom, lightbox and slider scripts on single product pages — including on block themes like Etch\'s, where WooCommerce itself never loads them.', 'woo4etch'),
                            __('Your gallery markup must use the Woo gallery classes for the scripts to pick it up: the easiest way is the [woo_gallery mode="woo"] shortcode; the single-product template docs show a hand-written Etch variant. Developers can fine-tune via the woo4etch/gallery_features filter.', 'woo4etch')
                        ); ?>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e('Variation pills & quantity stepper', 'woo4etch'); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" name="enable_pills" value="1" <?php checked($pills); ?>>
                            <?php esc_html_e('Turn variation dropdowns into pill buttons and quantity inputs into a −/+ stepper', 'woo4etch'); ?>
                        </label>
                        <?php self::setting_description(
                            __('Progressive enhancement on single product pages, no extra markup needed — WooCommerce\'s variation logic stays in charge, so price, stock and availability keep updating exactly as before.', 'woo4etch'),
                            __('A pill click sets the native select underneath. Styling uses your design tokens (--primary, --space-*, --radius) with plain fallbacks and can be overridden via the .w4e-pill / .w4e-qty classes. For hand-built swatch markup use the always-on swatches bridge (data-w4e-swatch) instead — one or the other per form. Developers: woo4etch/enqueue_pills filter.', 'woo4etch')
                        ); ?>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e('Store API interactions', 'woo4etch'); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" name="store_api_cart" value="1" <?php checked($store_api); ?>>
                            <?php esc_html_e('Cart and checkout actions without page reloads via WooCommerce\'s Store API (recommended)', 'woo4etch'); ?>
                        </label>
                        <?php self::setting_description(
                            __('Quantity changes, item removal, coupons, add-to-cart — and on the ready-made checkout layout also shipping selection and placing the order — go through WooCommerce\'s modern Store API, with Woo\'s native validation, error messages and rate limiting.', 'woo4etch'),
                            __('After each write the page re-renders its own server-side Etch HTML in place: the script binds to the standard WooCommerce field names and swaps the [data-w4e-cart-region] / [data-w4e-checkout-region] containers with the fresh server render; it never generates markup itself. Forms carrying third-party fields automatically fall back to the classic submit, and everything keeps working without JavaScript. Turning this off also returns the checkout layout to classic full-page submits. Developers: woo4etch/enqueue_store_api filter, woo4etch:cart-updated event.', 'woo4etch')
                        ); ?>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e('Checkout rate limiting', 'woo4etch'); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" name="checkout_rate_limit" value="1" <?php checked($rate_limit); ?>>
                            <?php esc_html_e('Rate-limit place-order attempts on the classic checkout path (3 attempts per minute per client)', 'woo4etch'); ?>
                        </label>
                        <?php self::setting_description(
                            __('Protection against card-testing attacks on the classic (non-Store-API) checkout path, which has no native WooCommerce protection.', 'woo4etch'),
                            __('Orders placed through the Store API — the ready-made checkout layout with Store API interactions enabled — are already covered by WooCommerce\'s own Store API rate limiting; this toggle protects the classic shortcode checkout and the no-JavaScript fallback. It mirrors WooCommerce\'s block defaults and rejects further attempts with a checkout error once a client (IP + browser fingerprint) exceeds 3 submits in 60 seconds. Legitimate customers are unaffected — a normal purchase is a single submit. Developers: woo4etch/checkout_rate_limit filter (enabled/limit/window).', 'woo4etch')
                        ); ?>
                    </td>
                </tr>
            </table>
            <?php submit_button(__('Save settings', 'woo4etch')); ?>
        </form>
        <?php
    }

    /**
     * Ready-made layouts: one-click push + copy/paste JSON.
     */
    private static function render_layouts_section() {
        ?>
        <h2 class="category-heading"><?php esc_html_e('Ready-made layouts', 'woo4etch'); ?></h2>

        <p class="woo4etch-intro">
            <?php esc_html_e('Complete, editable Etch layouts for every shop area — built on the dynamic-data bridges, so they preview live in the builder. “Add to page/template” puts the layout straight where it renders: WooCommerce’s assigned page (cart, account — from WooCommerce → Settings → Advanced) or the Etch template for the area (shop archive, single product, order confirmation). It only ever appends — existing content is preserved, and a target that already contains the layout is left untouched. “Copy JSON” puts the layout on your clipboard for pasting onto the Etch canvas instead. In every route, existing styles with the same selectors are reused, never overwritten.', 'woo4etch'); ?>
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
                    $push = class_exists('Woo4Etch_Health') ? Woo4Etch_Health::push_status($slug) : ['available' => false];
                    ?>
                    <tr>
                        <td><?php echo esc_html($meta['area']); ?></td>
                        <td><strong><?php echo esc_html($meta['name']); ?></strong></td>
                        <td><?php echo esc_html($meta['description']); ?></td>
                        <td>
                            <?php if (!empty($push['available'])) : ?>
                                <?php if ($push['present']) : ?>
                                    <?php $present_label = '✓ ' . sprintf(
                                        /* translators: %s: target page/template name */
                                        __('On “%s”', 'woo4etch'),
                                        $push['where']
                                    ); ?>
                                    <?php if (!empty($push['edit_url'])) : ?>
                                        <a class="woo4etch-installed" href="<?php echo esc_url($push['edit_url']); ?>"
                                           title="<?php esc_attr_e('Open in the editor to arrange it (Etch picks it up from there).', 'woo4etch'); ?>"><?php echo esc_html($present_label); ?></a>
                                    <?php else : ?>
                                        <span class="woo4etch-installed"><?php echo esc_html($present_label); ?></span>
                                    <?php endif; ?>
                                <?php else : ?>
                                    <form method="post"
                                          action="<?php echo esc_url(admin_url('admin-post.php')); ?>"
                                          style="display:inline">
                                        <input type="hidden" name="action" value="woo4etch_push_layout">
                                        <input type="hidden" name="layout" value="<?php echo esc_attr($slug); ?>">
                                        <?php wp_nonce_field('woo4etch_push_layout_' . $slug); ?>
                                        <button type="submit" class="button button-small button-primary"
                                                title="<?php echo esc_attr($push['label']); ?>">
                                            <?php esc_html_e('Add to page/template', 'woo4etch'); ?>
                                        </button>
                                    </form>
                                <?php endif; ?>
                            <?php endif; ?>
                            <?php if ('notices' === $slug && class_exists('Woo4Etch_Components')) : ?>
                                <?php $component_id = Woo4Etch_Components::installed_post_id(); ?>
                                <form method="post"
                                      action="<?php echo esc_url(admin_url('admin-post.php')); ?>"
                                      style="display:inline">
                                    <input type="hidden" name="action" value="woo4etch_request_component">
                                    <?php wp_nonce_field('woo4etch_request_component'); ?>
                                    <button type="submit" class="button button-small <?php echo $component_id > 0 ? '' : 'button-primary'; ?>"
                                            title="<?php esc_attr_e('Installs the notices region as an Etch component (one globally editable instance, place it from the builder\'s component library). Reinstalling updates the existing component in place.', 'woo4etch'); ?>">
                                        <?php $component_id > 0 ? esc_html_e('Reinstall component', 'woo4etch') : esc_html_e('Install as component', 'woo4etch'); ?>
                                    </button>
                                </form>
                                <?php if ($component_id > 0) : ?>
                                    <span class="woo4etch-installed" title="<?php esc_attr_e('The “Woo Notices” Etch component is installed — place instances from the builder\'s component library.', 'woo4etch'); ?>">✓ <?php esc_html_e('Component installed', 'woo4etch'); ?></span>
                                <?php endif; ?>
                            <?php endif; ?>
                            <button type="button"
                                    class="button button-small woo4etch-copy-json"
                                    data-copy-target="woo4etch-layout-<?php echo esc_attr($slug); ?>">
                                <?php esc_html_e('Copy JSON', 'woo4etch'); ?>
                            </button>
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
     * Page health check: are the expected elements present on the pages
     * WooCommerce is configured to use?
     */
    private static function render_health_section() {
        $targets = Woo4Etch_Health::targets();
        if (empty($targets)) {
            return;
        }

        $insert_button = static function ($slug, $area, $label) {
            ?>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="display:inline">
                <input type="hidden" name="action" value="woo4etch_insert_into_page">
                <input type="hidden" name="layout" value="<?php echo esc_attr($slug); ?>">
                <input type="hidden" name="area" value="<?php echo esc_attr($area); ?>">
                <?php wp_nonce_field('woo4etch_insert_into_page_' . $area . '_' . $slug); ?>
                <button type="submit" class="button button-small"><?php echo esc_html($label); ?></button>
            </form>
            <?php
        };
        ?>
        <h2 class="category-heading"><?php esc_html_e('Shop status', 'woo4etch'); ?></h2>
        <p class="woo4etch-intro">
            <?php esc_html_e('Checks the pages WooCommerce is configured to use (WooCommerce → Settings → Advanced) for the elements each page needs — searched in the page content and in Etch templates. “Add layout” pushes the ready-made layout straight to where the area renders; “Insert notices” appends the notices region to the assigned page. Both only ever append — existing content is preserved.', 'woo4etch'); ?>
        </p>

        <table class="widefat striped">
            <thead>
                <tr>
                    <th scope="col" style="width:18%"><?php esc_html_e('Page', 'woo4etch'); ?></th>
                    <th scope="col" style="width:27%"><?php esc_html_e('Assigned post', 'woo4etch'); ?></th>
                    <th scope="col" style="width:25%"><?php esc_html_e('Layout', 'woo4etch'); ?></th>
                    <th scope="col"><?php esc_html_e('Notices', 'woo4etch'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($targets as $area => $target) : ?>
                    <?php
                    $page = $target['page_id'] > 0 ? get_post($target['page_id']) : null;
                    $layout_loc  = $page ? Woo4Etch_Health::locate($target['page_id'], $target['markers']) : ['found' => false, 'where' => ''];
                    $notices_loc = $page ? Woo4Etch_Health::locate($target['page_id'], Woo4Etch_Health::NOTICES_MARKERS) : ['found' => false, 'where' => ''];
                    ?>
                    <tr>
                        <td><strong><?php echo esc_html($target['label']); ?></strong></td>
                        <td>
                            <?php if ($page) : ?>
                                <a href="<?php echo esc_url((string) get_edit_post_link($page->ID)); ?>"><?php echo esc_html($page->post_title !== '' ? $page->post_title : ('#' . $page->ID)); ?></a>
                            <?php else : ?>
                                <strong><?php esc_html_e('No page assigned!', 'woo4etch'); ?></strong>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if (!$page) : ?>
                                —
                            <?php elseif ($layout_loc['found']) : ?>
                                <span class="woo4etch-installed">✓</span>
                                <?php echo 'page' === $layout_loc['where'] ? esc_html__('found in page', 'woo4etch') : esc_html(sprintf(/* translators: %s: Etch template title */ __('found in template “%s”', 'woo4etch'), $layout_loc['where'])); ?>
                            <?php else : ?>
                                <strong><?php esc_html_e('missing', 'woo4etch'); ?></strong>
                                <?php if ($target['layout'] !== '') : ?>
                                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="display:inline">
                                        <input type="hidden" name="action" value="woo4etch_push_layout">
                                        <input type="hidden" name="layout" value="<?php echo esc_attr($target['layout']); ?>">
                                        <?php wp_nonce_field('woo4etch_push_layout_' . $target['layout']); ?>
                                        <button type="submit" class="button button-small"><?php esc_html_e('Add layout', 'woo4etch'); ?></button>
                                    </form>
                                <?php endif; ?>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if (!$page || !$target['notices']) : ?>
                                —
                            <?php elseif ($notices_loc['found']) : ?>
                                <span class="woo4etch-installed">✓</span>
                                <?php echo 'page' === $notices_loc['where'] ? esc_html__('found in page', 'woo4etch') : esc_html(sprintf(/* translators: %s: Etch template title */ __('found in template “%s”', 'woo4etch'), $notices_loc['where'])); ?>
                            <?php else : ?>
                                <strong><?php esc_html_e('missing', 'woo4etch'); ?></strong>
                                <?php $insert_button('notices', $area, __('Insert notices', 'woo4etch')); ?>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php
    }

    /**
     * Success/error notices from the admin-post redirects, rendered once
     * under the H1 so every tab shows the outcome of its actions.
     */
    private static function render_notices() {
        // phpcs:disable WordPress.Security.NonceVerification.Recommended -- read-only notice flags.
        $success = [];
        $errors  = [];

        if (isset($_GET['w4e_settings_saved'])) {
            $success[] = __('Settings saved.', 'woo4etch');
        }
        if (isset($_GET['w4e_pushed'])) {
            $success[] = sanitize_text_field(wp_unslash($_GET['w4e_pushed']));
        }
        if (isset($_GET['w4e_health_ok'])) {
            $success[] = sprintf(
                /* translators: %s: "layout:area" that was inserted */
                __('Inserted (%s). Reload the page in the builder to arrange it.', 'woo4etch'),
                sanitize_text_field(wp_unslash($_GET['w4e_health_ok']))
            );
        }
        foreach (['w4e_push_error', 'w4e_component_error', 'w4e_health_error'] as $key) {
            if (isset($_GET[$key]) && '' !== $_GET[$key]) {
                $errors[] = sanitize_text_field(wp_unslash($_GET[$key]));
            }
        }
        // phpcs:enable

        foreach ($success as $message) {
            echo '<div class="notice notice-success inline"><p>' . esc_html($message) . '</p></div>';
        }
        foreach ($errors as $message) {
            echo '<div class="notice notice-error inline"><p>' . esc_html($message) . '</p></div>';
        }
    }

    /**
     * Overview tab: the shop status — the question this page is opened for.
     * (WooCommerce's own template types are handled where they're edited:
     * the "WooCommerce" group in Etch's template hub.)
     */
    private static function render_overview_section() {
        self::render_health_section();
    }

    /**
     * Shortcode reference tab.
     */
    private static function render_shortcodes_section() {
        $catalog     = Woo4Etch::get_shortcode_catalog();
        $by_category = [];

        foreach ($catalog as $tag => $entry) {
            $category = $entry['category'];
            if (!isset($by_category[$category])) {
                $by_category[$category] = [];
            }
            $by_category[$category][$tag] = $entry;
        }
        ?>
        <div class="woo4etch-intro notice notice-info inline">
            <p>
                <?php esc_html_e('Drop these shortcodes into Etch templates and pages wherever WooCommerce needs PHP output (forms, formatted prices, hooks, cart state). When id is omitted, shortcodes use the current product (global $product or the single product being viewed).', 'woo4etch'); ?>
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
        <?php
    }

    /**
     * Render the Woo4Etch admin page (tabbed).
     */
    public static function render_page() {
        if (!current_user_can(apply_filters('woo4etch/admin_capability', 'manage_woocommerce'))) {
            wp_die(esc_html__('You do not have permission to view this page.', 'woo4etch'));
        }

        $tab        = self::current_tab();
        $under_etch = self::detect_etch_menu_slug() !== null
            || in_array(self::resolve_parent_slug(), (array) apply_filters('woo4etch/etch_menu_slugs', ['etch', 'etch-builder', 'etch-settings', 'etchwp']), true);
        ?>
        <div class="wrap woo4etch-shortcodes">
            <h1>
                <?php esc_html_e('Woo4Etch', 'woo4etch'); ?>
                <span class="woo4etch-header-links">
                    <a href="https://github.com/tobiashaas/woo4etch" target="_blank" rel="noopener noreferrer">
                        <?php esc_html_e('Documentation on GitHub', 'woo4etch'); ?>
                    </a>
                </span>
            </h1>

            <?php if (!$under_etch) : ?>
                <div class="notice notice-info inline">
                    <p>
                        <?php esc_html_e('Etch was not detected in the admin menu — this page is listed under WooCommerce. Install and activate Etch to move it under the Etch menu automatically.', 'woo4etch'); ?>
                        <a href="<?php echo esc_url(WOO4ETCH_ETCH_AFFILIATE_URL); ?>" target="_blank" rel="noopener noreferrer sponsored">
                            <?php esc_html_e('Get Etch', 'woo4etch'); ?>
                        </a>
                    </p>
                </div>
            <?php endif; ?>

            <?php self::render_notices(); ?>

            <nav class="nav-tab-wrapper woo4etch-tabs">
                <?php foreach (self::tabs() as $slug => $label) : ?>
                    <a class="nav-tab <?php echo $tab === $slug ? 'nav-tab-active' : ''; ?>"
                       href="<?php echo esc_url(admin_url('admin.php?page=' . self::PAGE_SLUG . '&tab=' . $slug)); ?>">
                        <?php echo esc_html($label); ?>
                    </a>
                <?php endforeach; ?>
            </nav>

            <?php
            switch ($tab) {
                case 'layouts':
                    self::render_layouts_section();
                    break;
                case 'settings':
                    self::render_settings_section();
                    break;
                case 'shortcodes':
                    self::render_shortcodes_section();
                    break;
                default:
                    self::render_overview_section();
                    break;
            }
            ?>
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
