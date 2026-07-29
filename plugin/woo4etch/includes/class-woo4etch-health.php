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
     * Where a layout belongs on this site — the push target.
     *
     * Two target kinds, matching how Etch shops actually render:
     * - `page`: WooCommerce-assigned pages (WooCommerce → Settings →
     *   Advanced). Their Etch page templates are thin shells around
     *   `woocommerce/page-content-wrapper`, so the layout lives in the page
     *   content itself.
     * - `template`: areas without a page (product archive, single product,
     *   order confirmation) render via an Etch `wp_template` — the layout
     *   lives in the template content.
     *
     * @param string $slug Layout catalog key.
     * @return array{kind: string, page_id?: int, template_slug?: string, label: string, markers: array<int,string>}|null
     *         Null when the layout has no automatic target (mini-cart lives
     *         in the site header; notices go through the health-check area
     *         buttons).
     */
    public static function push_target($slug) {
        switch ($slug) {
            case 'cart':
                return [
                    'kind'    => 'page',
                    'page_id' => function_exists('wc_get_page_id') ? (int) wc_get_page_id('cart') : 0,
                    'label'   => __('Cart page', 'woo4etch'),
                    'markers' => ['w4e-cart', 'woocommerce-cart-form', '[woocommerce_cart', 'wp:woocommerce/cart'],
                ];
            case 'account':
                return [
                    'kind'    => 'page',
                    'page_id' => function_exists('wc_get_page_id') ? (int) wc_get_page_id('myaccount') : 0,
                    'label'   => __('My Account page', 'woo4etch'),
                    'markers' => ['w4e-account', '[woocommerce_my_account', '[woo_account_content'],
                ];
            case 'product-grid':
                return [
                    'kind'          => 'template',
                    'template_slug' => 'archive-product',
                    'label'         => __('Product archive template (archive-product)', 'woo4etch'),
                    'markers'       => ['w4e-shop'],
                ];
            case 'product-single':
                return [
                    'kind'          => 'template',
                    'template_slug' => 'single-product',
                    'label'         => __('Single product template (single-product)', 'woo4etch'),
                    'markers'       => ['w4e-product'],
                ];
            case 'thank-you':
                return [
                    'kind'          => 'template',
                    'template_slug' => 'order-confirmation',
                    'label'         => __('Order confirmation template (order-confirmation)', 'woo4etch'),
                    'markers'       => ['w4e-thankyou'],
                ];
        }
        return null;
    }

    /**
     * Target + presence info for the admin UI.
     *
     * @param string $slug Layout catalog key.
     * @return array{available: bool, label: string, present: bool, where: string, target_exists: bool}
     */
    public static function push_status($slug) {
        $target = self::push_target($slug);
        if ($target === null) {
            return ['available' => false, 'label' => '', 'present' => false, 'where' => '', 'target_exists' => false];
        }

        $post = null;
        if ('page' === $target['kind']) {
            $post = $target['page_id'] > 0 ? get_post($target['page_id']) : null;
        } else {
            $post = self::find_template($target['template_slug']);
        }

        return [
            'available'     => true,
            'label'         => $target['label'],
            'present'       => $post ? self::content_has((string) $post->post_content, $target['markers']) : false,
            'where'         => $post ? ('page' === $target['kind'] ? get_the_title($post) : $target['template_slug']) : '',
            'target_exists' => (bool) $post,
        ];
    }

    /**
     * Push a layout straight onto its target — the WooCommerce-assigned page
     * or the Etch template that renders the area. Append-only: existing
     * content is always preserved (issue #21 — never replace user layout).
     * A target that already contains the layout's markers is left untouched;
     * a missing template is created (bare — the user adds header/footer in
     * the builder). Styles merge like the pattern installer: existing
     * selectors are reused, never overwritten.
     *
     * @param string $slug Layout catalog key.
     * @return array{post_id: int, note: string}|WP_Error
     */
    public static function push($slug) {
        $target = self::push_target($slug);
        if ($target === null) {
            return new WP_Error('woo4etch_no_push_target', __('This layout has no automatic page target — install it as a pattern or paste its JSON where it belongs.', 'woo4etch'));
        }

        $blocks = Woo4Etch_Layouts::blocks_for_install($slug);
        if ($blocks === null) {
            return new WP_Error('woo4etch_unknown_layout', __('Unknown layout.', 'woo4etch'));
        }
        $append = serialize_blocks($blocks);

        if ('page' === $target['kind']) {
            $page = $target['page_id'] > 0 ? get_post($target['page_id']) : null;
            if (!$page) {
                return new WP_Error('woo4etch_page_missing', __('WooCommerce has no page assigned for this area (WooCommerce → Settings → Advanced).', 'woo4etch'));
            }
            if (self::content_has((string) $page->post_content, $target['markers'])) {
                return new WP_Error('woo4etch_already_present', sprintf(
                    /* translators: %s: page title */
                    __('“%s” already contains this layout — edit it in the Etch builder instead of inserting a second copy.', 'woo4etch'),
                    get_the_title($page)
                ));
            }
            $result = self::append_to_post($page->ID, rtrim($page->post_content) . "\n\n" . $append);
            if (is_wp_error($result)) {
                return $result;
            }
            return [
                'post_id' => (int) $result,
                'note'    => sprintf(
                    /* translators: %s: page title */
                    __('Layout added to “%s”. Open the page in the Etch builder to arrange it.', 'woo4etch'),
                    get_the_title($page)
                ),
            ];
        }

        $template = self::find_template($target['template_slug']);
        if ($template) {
            if (self::content_has((string) $template->post_content, $target['markers'])) {
                return new WP_Error('woo4etch_already_present', sprintf(
                    /* translators: %s: template slug */
                    __('The “%s” template already contains this layout — edit it in the Etch builder instead of inserting a second copy.', 'woo4etch'),
                    $target['template_slug']
                ));
            }
            $result = self::append_to_post($template->ID, rtrim($template->post_content) . "\n\n" . $append);
            if (is_wp_error($result)) {
                return $result;
            }
            return [
                'post_id' => (int) $result,
                'note'    => sprintf(
                    /* translators: %s: template slug */
                    __('Layout added to the “%s” template. Open it in the Etch builder to arrange it.', 'woo4etch'),
                    $target['template_slug']
                ),
            ];
        }

        $created = self::create_template($target['template_slug'], $append);
        if (is_wp_error($created)) {
            return $created;
        }
        return [
            'post_id' => (int) $created,
            'note'    => sprintf(
                /* translators: %s: template slug */
                __('Created the “%s” template with this layout. It has no header/footer yet — open it in the Etch builder to add your site frame.', 'woo4etch'),
                $target['template_slug']
            ),
        ];
    }

    /**
     * The active theme's wp_template post for a slug, if one exists.
     * (Templates only exist as posts once created/edited — theme-file
     * templates don't apply here: the Etch theme ships none for Woo areas.)
     *
     * @param string $slug Template slug (e.g. 'single-product').
     * @return WP_Post|null
     */
    public static function find_template($slug) {
        $posts = get_posts([
            'post_type'      => 'wp_template',
            'post_status'    => ['publish', 'draft'],
            'name'           => $slug,
            'posts_per_page' => 1,
            'tax_query'      => [
                [
                    'taxonomy' => 'wp_theme',
                    'field'    => 'name',
                    'terms'    => get_stylesheet(),
                ],
            ],
        ]);
        return $posts ? $posts[0] : null;
    }

    /**
     * Create a wp_template post for the active theme.
     *
     * @param string $slug    Template slug.
     * @param string $content Serialized block content.
     * @return int|WP_Error
     */
    private static function create_template($slug, $content) {
        kses_remove_filters();
        try {
            $post_id = wp_insert_post(wp_slash([
                'post_type'    => 'wp_template',
                'post_status'  => 'publish',
                'post_name'    => $slug,
                'post_title'   => $slug,
                'post_content' => $content,
            ]), true);
        } finally {
            kses_init_filters();
        }
        if (is_wp_error($post_id)) {
            return $post_id;
        }
        wp_set_object_terms((int) $post_id, [get_stylesheet()], 'wp_theme');
        return (int) $post_id;
    }

    /**
     * Save new content on a post with KSES lifted (Etch block comments would
     * otherwise be stripped for non-unfiltered users).
     *
     * @param int    $post_id Target post.
     * @param string $content Full new content.
     * @return int|WP_Error
     */
    private static function append_to_post($post_id, $content) {
        kses_remove_filters();
        try {
            $result = wp_update_post(wp_slash([
                'ID'           => $post_id,
                'post_content' => $content,
            ]), true);
        } finally {
            kses_init_filters();
        }
        return is_wp_error($result) ? $result : (int) $result;
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

        return self::append_to_post($page_id, $content);
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
