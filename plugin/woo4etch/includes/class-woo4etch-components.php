<?php
/**
 * "Woo Notices" Etch component installer.
 *
 * Etch's public scripting API (window.etch — docs.etchwp.com/public-api) can
 * create components programmatically via etch.components.createAsync() /
 * updateAsync({key, description, blocks}), but the API runtime only exists
 * inside the builder (the front-end page loaded with ?etch=magic) — there is
 * still no server-side install route a plugin could call directly (see
 * ETCH-FEATURE-REQUESTS.md). The install is therefore a two-step handshake:
 *
 *  1. The admin opts in on the Woo4Etch page → a pending flag is stored.
 *  2. On the next builder load, assets/component-install.js waits for
 *     window.etch, creates the component through the public API (guarded
 *     paths: validation + undo/redo), and reports the new component id back
 *     via admin-ajax. The flag clears; the admin page shows the result.
 *
 * The component wraps [woo_notices format="plain"] in the same .w4e-notices
 * element the ready-made layouts inline — one globally editable notices
 * region. Replacing the inline copies with component instances is a manual
 * step in the builder (select → replace), until Etch exposes instances to
 * the pattern format.
 *
 * @package Woo4Etch
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Handles the builder-side component install handshake.
 */
final class Woo4Etch_Components {

    /** Option holding {pending: bool, id: int, error: string}. */
    const OPTION = 'woo4etch_notices_component';

    /** Component reference key (PascalCase, Etch convention). */
    const KEY = 'WooNotices';

    /** Component display name. */
    const NAME = 'Woo Notices';

    /**
     * Hook registration.
     */
    public static function init() {
        add_action('wp_enqueue_scripts', [__CLASS__, 'enqueue_builder_script'], 30);
        add_action('wp_ajax_woo4etch_component_result', [__CLASS__, 'ajax_result']);
    }

    /**
     * Current install state.
     *
     * @return array{pending: bool, id: int, error: string}
     */
    public static function state() {
        $state = (array) get_option(self::OPTION, []);
        return [
            'pending' => !empty($state['pending']),
            'id'      => isset($state['id']) ? (int) $state['id'] : 0,
            'error'   => isset($state['error']) ? (string) $state['error'] : '',
        ];
    }

    /**
     * Arm the pending flag (admin opt-in). The actual creation happens on the
     * next builder load.
     */
    public static function request_install() {
        update_option(self::OPTION, [
            'pending' => true,
            'id'      => self::state()['id'],
            'error'   => '',
        ]);
    }

    /**
     * The Etch builder is the front-end page loaded with ?etch=magic — the
     * same signal Woo4Etch::is_etch_builder() keys on for sample data.
     */
    private static function is_builder_request() {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only routing check.
        return isset($_GET['etch']) && 'magic' === sanitize_text_field(wp_unslash($_GET['etch']));
    }

    /**
     * Enqueue the install script on builder loads while an install is pending.
     */
    public static function enqueue_builder_script() {
        if (!self::is_builder_request() || !self::state()['pending']) {
            return;
        }
        if (!current_user_can(apply_filters('woo4etch/admin_capability', 'manage_woocommerce'))) {
            return;
        }

        wp_enqueue_script(
            'woo4etch-component-install',
            plugins_url('assets/component-install.js', dirname(__DIR__) . '/woo4etch.php'),
            [],
            Woo4Etch::VERSION,
            true
        );
        wp_localize_script('woo4etch-component-install', 'woo4etchComponentInstall', [
            'pending'     => true,
            'ajaxUrl'     => admin_url('admin-ajax.php'),
            'nonce'       => wp_create_nonce('woo4etch_component_result'),
            'key'         => self::KEY,
            'name'        => self::NAME,
            'description' => __('WooCommerce feedback region: queued notices ("Cart updated.", coupon/form/security errors) as styleable .w4e-notice markup. Place once near the top of a page layout.', 'woo4etch'),
            'shortcode'   => '[woo_notices format="plain"]',
        ]);
    }

    /**
     * admin-ajax: the builder script reports the outcome.
     */
    public static function ajax_result() {
        if (!current_user_can(apply_filters('woo4etch/admin_capability', 'manage_woocommerce'))) {
            wp_send_json_error(['message' => __('You do not have permission to do this.', 'woo4etch')], 403);
        }
        check_ajax_referer('woo4etch_component_result');

        $status = isset($_POST['status']) ? sanitize_key(wp_unslash($_POST['status'])) : '';
        $state  = self::state();

        if ('installed' === $status) {
            $state = [
                'pending' => false,
                'id'      => isset($_POST['component_id']) ? absint(wp_unslash($_POST['component_id'])) : 0,
                'error'   => '',
            ];
        } else {
            $state['pending'] = false;
            $state['error']   = isset($_POST['message'])
                ? sanitize_text_field(wp_unslash($_POST['message']))
                : __('Component install failed in the builder.', 'woo4etch');
        }

        update_option(self::OPTION, $state);
        wp_send_json_success($state);
    }
}
