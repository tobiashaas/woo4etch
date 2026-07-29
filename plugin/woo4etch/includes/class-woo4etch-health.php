<?php
/**
 * Page health check: are the expected Woo4Etch elements actually present on
 * the pages WooCommerce is configured to use?
 *
 * WooCommerce knows which post is the cart / checkout / account page
 * (wc_get_page_id()) — so instead of hoping the user pasted the right layout
 * in the right place, the admin page verifies it: each area defines content
 * markers (root classes / shortcodes) that are searched in the assigned
 * page's content and, because Etch layouts often live in an Etch template
 * rather than the page itself, in Etch template posts too.
 *
 * Missing pieces can be fixed in place: "insert" appends the layout's blocks
 * directly to the assigned page (styles merged like the pattern installer) —
 * no pattern-library detour needed.
 *
 * @package Woo4Etch
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Resolves WooCommerce's page assignments and checks them for markers.
 */
final class Woo4Etch_Health {

    /** Markers identifying the notices region in any content. */
    const NOTICES_MARKERS = ['w4e-notices', '[woo_notices'];

    /**
     * Checked areas: WooCommerce-assigned page + the markers that indicate
     * the area's layout is present + which ready-made layout can be inserted.
     *
     * @return array<string, array{label: string, page_id: int, layout: string, markers: array<int,string>, notices: bool}>
     */
    public static function targets() {
        if (!function_exists('wc_get_page_id')) {
            return [];
        }
        return [
            'cart' => [
                'label'   => __('Cart page', 'woo4etch'),
                'page_id' => (int) wc_get_page_id('cart'),
                'layout'  => 'cart',
                'markers' => ['w4e-cart', 'woocommerce-cart-form', '[woocommerce_cart', 'wp:woocommerce/cart'],
                'notices' => true,
            ],
            'checkout' => [
                'label'   => __('Checkout page', 'woo4etch'),
                'page_id' => (int) wc_get_page_id('checkout'),
                // No Woo4Etch checkout layout — the check is informational
                // (native Woo checkout shortcode/block present?).
                'layout'  => '',
                'markers' => ['[woocommerce_checkout', 'wp:woocommerce/checkout', '[woo_checkout'],
                'notices' => true,
            ],
            'myaccount' => [
                'label'   => __('My Account page', 'woo4etch'),
                'page_id' => (int) wc_get_page_id('myaccount'),
                'layout'  => 'account',
                'markers' => ['w4e-account', '[woocommerce_my_account', '[woo_account_content'],
                'notices' => true,
            ],
        ];
    }

    /**
     * Search the assigned page — and Etch template posts — for any of the
     * given content markers.
     *
     * @param int                $page_id Page to check first.
     * @param array<int,string>  $markers Substrings identifying the element.
     * @return array{found: bool, where: string} where: '' | 'page' | template title.
     */
    public static function locate($page_id, array $markers) {
        $page = $page_id > 0 ? get_post($page_id) : null;
        if ($page && self::content_has($page->post_content, $markers)) {
            return ['found' => true, 'where' => 'page'];
        }

        // Etch layouts frequently live in an Etch template assigned to the
        // page, not in the page content itself — scan those too (post type
        // names probed defensively; skipped when Etch stores them elsewhere).
        foreach (['etch_template', 'etch-template'] as $type) {
            if (!post_type_exists($type)) {
                continue;
            }
            $templates = get_posts([
                'post_type'      => $type,
                'post_status'    => 'any',
                'posts_per_page' => 100,
            ]);
            foreach ($templates as $template) {
                if (self::content_has((string) $template->post_content, $markers)) {
                    return ['found' => true, 'where' => $template->post_title !== '' ? $template->post_title : ('#' . $template->ID)];
                }
            }
        }

        return ['found' => false, 'where' => ''];
    }

    /**
     * Append a ready-made layout's blocks directly to a WooCommerce-assigned
     * page — no pattern-library detour. Styles merge exactly like the pattern
     * installer (existing selectors reused, never overwritten). Append-only:
     * existing page content is preserved.
     *
     * @param string $slug    Layout catalog key.
     * @param string $area    Target area key from targets().
     * @return int|WP_Error Page ID.
     */
    public static function insert_into_page($slug, $area) {
        $targets = self::targets();
        if (!isset($targets[$area])) {
            return new WP_Error('woo4etch_unknown_area', __('Unknown page area.', 'woo4etch'));
        }
        $page_id = $targets[$area]['page_id'];
        $page    = $page_id > 0 ? get_post($page_id) : null;
        if (!$page) {
            return new WP_Error('woo4etch_page_missing', __('WooCommerce has no page assigned for this area (WooCommerce → Settings → Advanced).', 'woo4etch'));
        }
        if ($slug !== 'notices' && $slug !== $targets[$area]['layout']) {
            return new WP_Error('woo4etch_layout_mismatch', __('This layout does not belong on that page.', 'woo4etch'));
        }

        $blocks = Woo4Etch_Layouts::blocks_for_install($slug);
        if ($blocks === null) {
            return new WP_Error('woo4etch_unknown_layout', __('Unknown layout.', 'woo4etch'));
        }

        $content = $page->post_content;
        $append  = serialize_blocks($blocks);
        // Notices belong above the page's existing content, layouts below it.
        $content = ('notices' === $slug)
            ? $append . "\n\n" . $content
            : rtrim($content) . "\n\n" . $append;

        // Etch block comments would be stripped by KSES for non-unfiltered users.
        kses_remove_filters();
        try {
            $result = wp_update_post(wp_slash([
                'ID'           => $page_id,
                'post_content' => $content,
            ]), true);
        } finally {
            kses_init_filters();
        }

        return is_wp_error($result) ? $result : (int) $result;
    }

    /**
     * True when the content contains any of the markers.
     *
     * @param string             $content Post content.
     * @param array<int,string>  $markers Substrings.
     * @return bool
     */
    private static function content_has($content, array $markers) {
        if ('' === $content) {
            return false;
        }
        foreach ($markers as $marker) {
            if (false !== strpos($content, $marker)) {
                return true;
            }
        }
        return false;
    }
}
