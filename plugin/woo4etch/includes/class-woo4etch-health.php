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
                'layout'  => 'checkout',
                // Any checkout counts as present — the ready-made layout as
                // well as the native shortcode/block a site may already use.
                'markers' => ['w4e-checkout-form', '[woocommerce_checkout', 'wp:woocommerce/checkout', '[woo_checkout'],
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
                    // Only OUR layout — stock Woo cart block/shortcode is
                    // replaceable (issue #36), not "already present".
                    'markers' => ['w4e-cart'],
                ];
            case 'account':
                return [
                    'kind'    => 'page',
                    'page_id' => function_exists('wc_get_page_id') ? (int) wc_get_page_id('myaccount') : 0,
                    'label'   => __('My Account page', 'woo4etch'),
                    'markers' => ['w4e-account'],
                ];
            case 'checkout':
                return [
                    'kind'    => 'page',
                    'page_id' => function_exists('wc_get_page_id') ? (int) wc_get_page_id('checkout') : 0,
                    'label'   => __('Checkout page', 'woo4etch'),
                    // Refuse when OUR checkout (or the documented block
                    // fallback) already renders — a second checkout would
                    // double-submit. Stock `wp:woocommerce/checkout` alone
                    // is replaceable via is_replaceable_stock_page().
                    'markers' => ['w4e-checkout-form', '[woo_checkout_block'],
                ];
            case 'product-grid':
                return [
                    'kind'          => 'template',
                    'template_slug' => 'archive-product',
                    'label'         => __('Product archive template (archive-product — also renders category pages until a taxonomy-product_cat template exists)', 'woo4etch'),
                    'markers'       => ['w4e-shop'],
                ];
            case 'category':
                return [
                    'kind'          => 'template',
                    'template_slug' => 'taxonomy-product_cat',
                    'label'         => __('Category archive template (taxonomy-product_cat)', 'woo4etch'),
                    'markers'       => ['w4e-category'],
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
     * @return array{available: bool, label: string, present: bool, where: string, target_exists: bool, edit_url: string, outdated: string, state: string, installed_version: string}
     */
    public static function push_status($slug) {
        $target = self::push_target($slug);
        if ($target === null) {
            return ['available' => false, 'label' => '', 'present' => false, 'where' => '', 'target_exists' => false, 'edit_url' => '', 'outdated' => '', 'state' => 'untracked', 'installed_version' => ''];
        }

        $post     = null;
        $edit_url = '';
        if ('page' === $target['kind']) {
            $post     = $target['page_id'] > 0 ? get_post($target['page_id']) : null;
            $edit_url = $post ? (string) get_edit_post_link($post->ID, 'raw') : '';
        } else {
            $post     = self::find_template($target['template_slug']);
            $edit_url = $post ? admin_url('site-editor.php?postId=' . rawurlencode(get_stylesheet() . '//' . $target['template_slug']) . '&postType=wp_template&canvas=edit') : '';
        }

        $content = $post ? (string) $post->post_content : '';
        $present = $post ? self::content_has($content, $target['markers']) : false;

        // Ownership beats markers: when we recorded the install and the blocks
        // still hash to that record, we know exactly where the layout stands.
        // The marker heuristic only has to cover installs from before that.
        $state = $present && $post ? self::layout_state($slug, $post->ID) : ['status' => 'untracked', 'version' => ''];
        $outdated = '';
        if ($present && 'untracked' === $state['status']) {
            $outdated = self::outdated_reason($slug, $content);
        }

        return [
            'available'     => true,
            'label'         => $target['label'],
            'present'       => $present,
            'where'         => $post ? ('page' === $target['kind'] ? get_the_title($post) : $target['template_slug']) : '',
            'target_exists' => (bool) $post,
            'edit_url'      => $edit_url,
            'outdated'      => $outdated,
            'state'         => $state['status'],
            'installed_version' => $state['version'],
        ];
    }

    /**
     * Is the layout ALREADY on the page an older revision, missing something
     * a later release added?
     *
     * The push route is append-only and refuses a target that already
     * carries the layout, so a fix shipped inside a layout does not reach
     * anyone who installed it earlier — they keep the old blocks and report
     * the bug as unfixed. Rather than version-stamping every block, each
     * entry below names a marker that a current install must contain plus
     * what is missing without it. Add one whenever a release changes a
     * layout's markup in a way that matters.
     *
     * @param string $slug    Layout catalog key.
     * @param string $content The target's stored post content.
     * @return string Empty when current; otherwise a human-readable reason.
     */
    private static function outdated_reason($slug, $content) {
        $revisions = self::layout_revisions($slug);
        $revision  = isset($revisions[$slug]) ? $revisions[$slug] : null;
        if (!$revision || '' === $content) {
            return '';
        }
        return strpos($content, (string) $revision['marker']) === false ? (string) $revision['note'] : '';
    }

    /**
     * Marker + explanation per layout whose markup changed in a way that
     * matters. The marker MUST exist in the layout as it ships today — a
     * fast-check asserts exactly that, because a stale marker here would
     * report every fresh install as outdated.
     *
     * @param string $slug Layout catalog key (passed to the filter as context).
     * @return array<string, array{marker: string, note: string}>
     */
    public static function layout_revisions($slug = '') {
        return (array) apply_filters('woo4etch/layout_revisions', [
            'checkout' => [
                'marker' => 'billing_state',
                'note'   => __('installed before the state/province and address-line-2 fields existed — in countries where WooCommerce requires a state (AU, US, CA, ES, IN, JP …) orders from this checkout fail validation', 'woo4etch'),
            ],
            'thank-you' => [
                'marker' => 'woocommerce_thankyou',
                'note'   => __('installed before the payment-instruction hooks existed — offline gateways (bank transfer, cash on delivery) render no instructions on it', 'woo4etch'),
            ],
            'cart' => [
                'marker' => 'cart_show_shipping',
                'note'   => __('installed before the summary disclosed shipping — it shows subtotal and total with nothing between them', 'woo4etch'),
            ],
        ], $slug);
    }

    /* ============================================================
       Ownership tracking — what the plugin installed, still untouched?
       ============================================================

       The push route is append-only and refuses a target that already
       carries the layout. That protects builder work absolutely, and it also
       means a fix shipped inside a layout never reaches anyone who installed
       it earlier: they keep the old blocks and report the bug as unfixed.

       So record what we appended. On a later check the installed section is
       located BY ITS HASH — a match is proof that nothing has touched it
       since, which makes replacing it safe. No match means the section was
       edited, moved or removed, and the automatic route steps aside for the
       manual one. False negatives (reporting "customized" for a layout that
       only got re-serialized) cost a button; a false positive would cost
       someone's work, so the comparison is deliberately strict.
    */

    /** Post meta holding the install record, keyed by layout slug. */
    const OWNERSHIP_META = '_woo4etch_layout_installs';

    /**
     * Normalize block markup so two serializations of the same tree compare
     * equal regardless of the whitespace WordPress's own serializer emits.
     *
     * @param string $markup Serialized block markup.
     * @return string
     */
    private static function normalize_markup($markup) {
        $blocks = parse_blocks((string) $markup);
        return $blocks ? serialize_blocks($blocks) : (string) $markup;
    }

    /**
     * Hash of a layout's markup as it ships today.
     *
     * @param string $slug Layout catalog key.
     * @return string Empty when the layout is unknown.
     */
    public static function shipped_hash($slug) {
        $blocks = Woo4Etch_Layouts::blocks_for_install($slug);
        if ($blocks === null) {
            return '';
        }
        return hash('sha256', self::normalize_markup(serialize_blocks($blocks)));
    }

    /**
     * Record what was just installed, so a later release can tell whether it
     * is still untouched. Hashes the blocks as they came back OUT of the
     * saved post, not as they went in: WordPress normalizes on save, and a
     * record that never matches its own target would be worse than none.
     *
     * Public so an integration check can set up a realistic install on a
     * throwaway post instead of mutating a real one.
     *
     * @param int         $post_id Target post.
     * @param string      $slug    Layout catalog key.
     * @param int         $count   How many top-level blocks belong to the layout.
     * @param int|null    $offset  Named-block index where the layout starts.
     *                             Null means "appended at the end" (the common
     *                             case). Set when push() replaced the middle of
     *                             a stock Woo template and the layout sits
     *                             between header and footer template parts.
     * @return void
     */
    public static function record_install($post_id, $slug, $count, $offset = null) {
        $post = get_post((int) $post_id);
        if (!$post) {
            return;
        }
        $blocks = array_values(array_filter(
            parse_blocks((string) $post->post_content),
            static function ($b) {
                return !empty($b['blockName']);
            }
        ));
        if ($offset !== null) {
            $ours = $count > 0 ? array_slice($blocks, (int) $offset, $count) : [];
        } else {
            $ours = $count > 0 ? array_slice($blocks, -$count) : [];
        }
        if (!$ours) {
            return;
        }

        $record = get_post_meta((int) $post_id, self::OWNERSHIP_META, true);
        if (!is_array($record)) {
            $record = [];
        }
        $record[$slug] = [
            'hash'    => hash('sha256', self::normalize_markup(serialize_blocks($ours))),
            'count'   => (int) $count,
            'version' => Woo4Etch::VERSION,
            'time'    => time(),
        ];
        update_post_meta((int) $post_id, self::OWNERSHIP_META, $record);
    }

    /**
     * Locate the run of top-level blocks this plugin installed, by hash.
     *
     * @param array<int,array<string,mixed>> $blocks Top-level blocks.
     * @param string                         $hash   Recorded hash.
     * @param int                            $count  How many blocks it covered.
     * @return int Offset of the first block, or -1 when no run matches.
     */
    private static function locate_owned_run(array $blocks, $hash, $count) {
        $count = max(1, (int) $count);
        $total = count($blocks);
        for ($i = 0; $i + $count <= $total; $i++) {
            $run = array_slice($blocks, $i, $count);
            if (hash('sha256', self::normalize_markup(serialize_blocks($run))) === $hash) {
                return $i;
            }
        }
        return -1;
    }

    /**
     * Can this post be re-serialized without changing anything we did not
     * mean to change?
     *
     * Replacing a layout rewrites the whole post, and most of that post
     * belongs to the user — a header, a footer, whatever else lives on the
     * template. `parse_blocks()` → `serialize_blocks()` is lossless for
     * well-formed block markup, but "well-formed" is an assumption about
     * someone else's content, not a fact. So prove it for this exact post
     * before touching it, and decline when it does not hold.
     *
     * @param string $content Post content.
     * @return bool
     */
    public static function can_rewrite_safely($content) {
        $content = (string) $content;
        return serialize_blocks(parse_blocks($content)) === $content;
    }

    /**
     * What state is the installed copy of this layout in?
     *
     * - `untracked` — installed before ownership was recorded (or by hand).
     *   Nothing can be proven about it, so the manual route applies.
     * - `customized` — recorded, but the blocks no longer match the record.
     *   Someone edited them. Never touched automatically.
     * - `current` — recorded, untouched, and identical to what ships now.
     * - `updatable` — recorded, untouched, and the shipped version differs.
     *
     * @param string $slug    Layout catalog key.
     * @param int    $post_id Target post.
     * @return array{status:string, version:string}
     */
    public static function layout_state($slug, $post_id) {
        $none   = ['status' => 'untracked', 'version' => ''];
        $record = get_post_meta((int) $post_id, self::OWNERSHIP_META, true);
        if (!is_array($record) || empty($record[$slug]['hash'])) {
            return $none;
        }
        $entry = $record[$slug];
        $post  = get_post((int) $post_id);
        if (!$post) {
            return $none;
        }

        $blocks = array_values(array_filter(
            parse_blocks((string) $post->post_content),
            static function ($b) {
                return !empty($b['blockName']);
            }
        ));
        $at = self::locate_owned_run($blocks, (string) $entry['hash'], isset($entry['count']) ? $entry['count'] : 1);
        if ($at < 0) {
            return ['status' => 'customized', 'version' => (string) ($entry['version'] ?? '')];
        }

        $shipped = self::shipped_hash($slug);
        $status  = ('' !== $shipped && $shipped === (string) $entry['hash']) ? 'current' : 'updatable';

        return ['status' => $status, 'version' => (string) ($entry['version'] ?? '')];
    }

    /**
     * Replace an untouched installed layout with the version that ships now.
     *
     * Two things must hold, and both are checked rather than assumed:
     *
     * 1. The recorded hash still locates the run, i.e. nobody edited it.
     * 2. Re-serializing the WHOLE post reproduces it byte for byte. The
     *    surrounding content belongs to the user — a header, a footer,
     *    whatever else lives on that template — and this route rewrites the
     *    entire post. If the round trip is not lossless for this exact post,
     *    the update is refused rather than risking their markup.
     *
     * @param string $slug Layout catalog key.
     * @return array{post_id:int, note:string}|WP_Error
     */
    public static function update_layout($slug) {
        $target = self::push_target($slug);
        if ($target === null) {
            return new WP_Error('woo4etch_no_push_target', __('This layout has no automatic page target.', 'woo4etch'));
        }

        $blocks = Woo4Etch_Layouts::blocks_for_install($slug);
        if ($blocks === null) {
            return new WP_Error('woo4etch_unknown_layout', __('Unknown layout.', 'woo4etch'));
        }

        $post = null;
        if ('page' === $target['kind'] && $target['page_id'] > 0) {
            $post = get_post($target['page_id']);
        } elseif ('page' !== $target['kind']) {
            $post = self::find_template($target['template_slug']);
        }
        if (!$post) {
            return new WP_Error('woo4etch_target_missing', __('The target page or template no longer exists.', 'woo4etch'));
        }

        $content = (string) $post->post_content;
        $parsed  = parse_blocks($content);

        // Gate 2 first — it is the one that protects content we do not own.
        if (!self::can_rewrite_safely($content)) {
            return new WP_Error('woo4etch_unsafe_rewrite', __('This page cannot be updated automatically without re-serializing content that is not ours, and that is not a risk worth taking. Delete the layout’s section in the Etch builder and add it again.', 'woo4etch'));
        }

        $record = get_post_meta($post->ID, self::OWNERSHIP_META, true);
        if (!is_array($record) || empty($record[$slug]['hash'])) {
            return new WP_Error('woo4etch_untracked', __('This layout was installed before Woo4Etch started recording what it installed, so there is no way to tell your edits from the original. Delete its section in the Etch builder and add it again.', 'woo4etch'));
        }
        $entry = $record[$slug];

        // Index map from the filtered view back into the parsed array.
        $index = [];
        foreach ($parsed as $i => $block) {
            if (!empty($block['blockName'])) {
                $index[] = $i;
            }
        }
        $filtered = array_map(static function ($i) use ($parsed) {
            return $parsed[$i];
        }, $index);

        $count = max(1, (int) ($entry['count'] ?? 1));
        $at    = self::locate_owned_run($filtered, (string) $entry['hash'], $count);
        if ($at < 0) {
            return new WP_Error('woo4etch_customized', __('This layout has been edited since it was installed, so replacing it would throw that work away. Delete its section in the Etch builder and add it again if you want the new version.', 'woo4etch'));
        }

        $from   = $index[$at];
        $to     = $index[$at + $count - 1];
        $merged = array_merge(
            array_slice($parsed, 0, $from),
            $blocks,
            array_slice($parsed, $to + 1)
        );

        $result = self::append_to_post($post->ID, serialize_blocks($merged));
        if (is_wp_error($result)) {
            return $result;
        }

        self::record_install($post->ID, $slug, count($blocks));

        return [
            'post_id' => (int) $result,
            'note'    => sprintf(
                /* translators: %s: page or template name */
                __('Updated the layout in “%s”. Your styles were untouched — only the layout’s own blocks were replaced.', 'woo4etch'),
                'page' === $target['kind'] ? get_the_title($post) : $target['template_slug']
            ),
        ];
    }

    /**
     * Push a layout straight onto its target — the WooCommerce-assigned page
     * or the Etch template that renders the area.
     *
     * Default is append-only: existing builder content is preserved (issue
     * #21 — never replace user layout). Two stock-Woo exceptions (issue #36)
     * replace WooCommerce's own defaults so a one-click install on a fresh
     * block shop lands the Etch layout instead of stacking UIs or refusing:
     *
     * - **Pages** whose sole content is the stock Cart/Checkout block or the
     *   My Account shortcode → replace that content with the layout.
     * - **Templates** that are still the untouched Woo blockified shape
     *   (header template-part + Woo blocks + footer template-part, no
     *   `w4e-*` marker) → keep the template parts, replace the middle.
     *
     * A target that already contains *our* layout markers is left untouched.
     * A missing template is created bare (the user adds header/footer in the
     * builder). Styles merge like the pattern installer: existing selectors
     * are reused, never overwritten.
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
            $content = (string) $page->post_content;
            if (self::content_has($content, $target['markers'])) {
                return new WP_Error('woo4etch_already_present', sprintf(
                    /* translators: %s: page title */
                    __('“%s” already contains this layout — edit it in the Etch builder instead of inserting a second copy.', 'woo4etch'),
                    get_the_title($page)
                ));
            }

            // Fresh Woo install: page holds only the stock Cart/Checkout block
            // or My Account shortcode. Replace it so one click lands the Etch
            // layout (issue #36) instead of refusing as "already present".
            // No can_rewrite_safely gate: the whole page content is discarded
            // on purpose, so a round-trip of Woo's markup is irrelevant.
            if (self::is_replaceable_stock_page($content, $slug)) {
                $result = self::append_to_post($page->ID, $append);
                if (is_wp_error($result)) {
                    return $result;
                }
                self::record_install($page->ID, $slug, count($blocks));
                return [
                    'post_id' => (int) $result,
                    'note'    => sprintf(
                        /* translators: %s: page title */
                        __('Replaced WooCommerce’s default block on “%s” with this layout. Open the page in the Etch builder to arrange it.', 'woo4etch'),
                        get_the_title($page)
                    ),
                ];
            }

            // Stock Woo commerce UI still present alongside other content —
            // appending would stack two carts/checkouts. Refuse with a clear ask.
            if (self::content_has($content, self::stock_page_needles($slug))) {
                return new WP_Error('woo4etch_stock_commerce', sprintf(
                    /* translators: %s: page title */
                    __('“%s” still carries WooCommerce’s default cart/checkout/account block or shortcode alongside other content. Remove that block in the Etch builder first, then add the layout — otherwise both UIs would render.', 'woo4etch'),
                    get_the_title($page)
                ));
            }

            $result = self::append_to_post($page->ID, rtrim($content) . "\n\n" . $append);
            if (is_wp_error($result)) {
                return $result;
            }
            self::record_install($page->ID, $slug, count($blocks));
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
            $content = (string) $template->post_content;
            if (self::content_has($content, $target['markers'])) {
                return new WP_Error('woo4etch_already_present', sprintf(
                    /* translators: %s: template slug */
                    __('The “%s” template already contains this layout — edit it in the Etch builder instead of inserting a second copy.', 'woo4etch'),
                    $target['template_slug']
                ));
            }

            // Untouched Woo blockified template: header + Woo blocks + footer.
            // Keep the site frame, swap the middle for the Etch layout (#36).
            // No can_rewrite_safely gate: the Woo middle is discarded on
            // purpose; header/footer template-parts are re-emitted from the
            // parsed tree rather than byte-preserved.
            if (self::is_stock_woo_block_template($content)) {
                $replaced = self::replace_stock_template_middle($content, $blocks);
                if (is_wp_error($replaced)) {
                    return $replaced;
                }
                $result = self::append_to_post($template->ID, $replaced['content']);
                if (is_wp_error($result)) {
                    return $result;
                }
                self::record_install($template->ID, $slug, count($blocks), $replaced['offset']);
                return [
                    'post_id' => (int) $result,
                    'note'    => sprintf(
                        /* translators: %s: template slug */
                        __('Replaced WooCommerce’s default blocks in the “%s” template with this layout (header and footer kept). Open it in the Etch builder to arrange it.', 'woo4etch'),
                        $target['template_slug']
                    ),
                ];
            }

            $result = self::append_to_post($template->ID, rtrim($content) . "\n\n" . $append);
            if (is_wp_error($result)) {
                return $result;
            }
            self::record_install($template->ID, $slug, count($blocks));
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
        self::record_install((int) $created, $slug, count($blocks));
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
    /**
     * Curated WooCommerce block templates worth having editable in Etch.
     *
     * WooCommerce REGISTERS these template types, but Etch's template picker
     * only knows the standard hierarchy — so until a wp_template post exists,
     * they're reachable only through the WP Site Editor. Materializing them
     * as posts (with sensible content) makes them show up in Etch's hub.
     * Note: deleting them later does NOT remove them from the frontend —
     * WooCommerce backfills its plugin default (generic template parts).
     *
     * @return array<string,array{name:string,description:string}>
     */
    public static function wc_templates() {
        return apply_filters('woo4etch/wc_templates', [
            // 'hub' => false keeps a template out of the builder-hub group,
            // which is the only place these are surfaced: the page frames
            // exist for WooCommerce's sake, are rarely edited, and would add
            // noise next to the content templates. Sites that do want to
            // shape a frame flip this via the woo4etch/wc_templates filter
            // (or create it in the WP Site Editor).
            'page-cart' => [
                'name'        => __('Page: Cart (frame)', 'woo4etch'),
                'description' => __('The frame around the cart PAGE (any slug — WooCommerce maps it to the assigned page). Created as a clone of your generic “page” template; edit it only for a cart-specific frame.', 'woo4etch'),
                'hub'         => false,
            ],
            'page-checkout' => [
                'name'        => __('Page: Checkout (frame)', 'woo4etch'),
                'description' => __('The frame around the checkout PAGE. Typical use: a reduced header (logo + trust, no navigation) while the checkout content itself lives on the page.', 'woo4etch'),
                'hub'         => false,
            ],
            'order-confirmation' => [
                'name'        => __('Order confirmation (thank-you)', 'woo4etch'),
                'description' => __('Renders the order-received endpoint after payment. The Thank-you layout above installs into it.', 'woo4etch'),
            ],
            'product-search-results' => [
                'name'        => __('Product search results', 'woo4etch'),
                'description' => __('Renders product search result pages.', 'woo4etch'),
            ],
            'coming-soon' => [
                'name'        => __('Coming soon', 'woo4etch'),
                'description' => __('Shown while WooCommerce → Site Visibility is set to “Coming soon”. Inactive on live sites.', 'woo4etch'),
            ],
        ]);
    }

    /**
     * Make a WooCommerce-registered template editable in Etch by creating
     * its wp_template post. Frames (page-cart / page-checkout) clone the
     * site's generic "page" template so the house frame applies; everything
     * else starts from WooCommerce's own default content (blockified file).
     *
     * @param string $slug Template slug from wc_templates().
     * @return int|WP_Error New template post ID.
     */
    public static function materialize_wc_template($slug) {
        if (!array_key_exists($slug, self::wc_templates())) {
            return new WP_Error('woo4etch_unknown_template', __('Unknown WooCommerce template.', 'woo4etch'));
        }
        if (self::find_template($slug)) {
            return new WP_Error('woo4etch_template_exists', __('This template already exists — it is already visible in Etch.', 'woo4etch'));
        }

        $content = '';
        // Frames: prefer the site's own generic page frame over WC's default
        // (which uses generic header/footer template parts).
        if (in_array($slug, ['page-cart', 'page-checkout'], true)) {
            $page = self::find_template('page');
            if ($page) {
                $content = (string) $page->post_content;
            }
        }
        if ('' === $content) {
            foreach ([
                WP_PLUGIN_DIR . '/woocommerce/templates/templates/blockified/' . $slug . '.html',
                WP_PLUGIN_DIR . '/woocommerce/templates/templates/' . $slug . '.html',
            ] as $file) {
                if (is_readable($file)) {
                    $content = (string) file_get_contents($file);
                    break;
                }
            }
        }
        if ('' === $content) {
            return new WP_Error('woo4etch_no_default', __('No default content found for this template (WooCommerce files not readable).', 'woo4etch'));
        }

        return self::create_template($slug, $content);
    }

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
     * Substrings that identify WooCommerce's own cart/checkout/account UI on
     * a page (block or shortcode). Used to refuse a push that would stack two
     * UIs when stock content sits alongside other builder work.
     *
     * @param string $slug Layout catalog key.
     * @return array<int,string>
     */
    public static function stock_page_needles($slug) {
        switch ($slug) {
            case 'cart':
                return ['wp:woocommerce/cart', '[woocommerce_cart'];
            case 'checkout':
                return ['wp:woocommerce/checkout', '[woocommerce_checkout'];
            case 'account':
                return ['[woocommerce_my_account'];
            default:
                return [];
        }
    }

    /**
     * True when page content is ONLY WooCommerce's stock cart/checkout block
     * or My Account shortcode — safe to replace with a Woo4Etch layout
     * (issue #36). False when our layout is already there, when the page is
     * empty, or when anything else sits alongside the stock UI.
     *
     * Public so the fast PHP suite can assert the shapes without WordPress.
     *
     * @param string $content Page post_content.
     * @param string $slug    Layout catalog key (cart|checkout|account).
     * @return bool
     */
    public static function is_replaceable_stock_page($content, $slug) {
        $content = (string) $content;
        if ('' === trim($content) || false !== strpos($content, 'w4e-')) {
            return false;
        }
        $needles = self::stock_page_needles($slug);
        if (!$needles || !self::content_has($content, $needles)) {
            return false;
        }

        if (!function_exists('parse_blocks')) {
            return self::content_is_sole_stock_shortcode($content, $slug)
                || self::content_is_sole_stock_block($content, $slug);
        }

        $named = array_values(array_filter(
            parse_blocks($content),
            static function ($b) {
                return !empty($b['blockName']);
            }
        ));

        if (!$named) {
            return self::content_is_sole_stock_shortcode($content, $slug);
        }
        if (count($named) !== 1) {
            return false;
        }

        $block = $named[0];
        $name  = (string) ($block['blockName'] ?? '');
        if ('cart' === $slug && 'woocommerce/cart' === $name) {
            return true;
        }
        if ('checkout' === $slug && 'woocommerce/checkout' === $name) {
            return true;
        }
        if ('core/shortcode' === $name) {
            return self::content_has($content, $needles);
        }
        return false;
    }

    /**
     * @param string $content Trimmed-capable page content.
     * @param string $slug    cart|checkout|account.
     * @return bool
     */
    private static function content_is_sole_stock_shortcode($content, $slug) {
        $trimmed = trim($content);
        switch ($slug) {
            case 'cart':
                return (bool) preg_match('/^\[woocommerce_cart[^\]]*\]$/', $trimmed);
            case 'checkout':
                return (bool) preg_match('/^\[woocommerce_checkout[^\]]*\]$/', $trimmed);
            case 'account':
                return (bool) preg_match('/^\[woocommerce_my_account[^\]]*\]$/', $trimmed);
            default:
                return false;
        }
    }

    /**
     * String-level "this page is only the stock Woo cart/checkout block"
     * check for environments without parse_blocks (fast PHP suite).
     *
     * @param string $content Page content.
     * @param string $slug    cart|checkout.
     * @return bool
     */
    private static function content_is_sole_stock_block($content, $slug) {
        $trimmed = trim($content);
        if ('cart' === $slug) {
            return (bool) preg_match(
                '/^<!--\s*wp:woocommerce\/cart\b.*<!--\s*\/wp:woocommerce\/cart\s*-->$/s',
                $trimmed
            );
        }
        if ('checkout' === $slug) {
            return (bool) preg_match(
                '/^<!--\s*wp:woocommerce\/checkout\b.*<!--\s*\/wp:woocommerce\/checkout\s*-->$/s',
                $trimmed
            );
        }
        if ('account' === $slug) {
            // Default Woo Account page: a single core/shortcode block.
            return (bool) preg_match(
                '/^<!--\s*wp:shortcode\s*-->\s*\[woocommerce_my_account[^\]]*\]\s*<!--\s*\/wp:shortcode\s*-->$/s',
                $trimmed
            );
        }
        return false;
    }

    /**
     * True when content is still WooCommerce's untouched blockified template
     * shape: header template-part, Woo-only middle, footer template-part, and
     * no `w4e-*` marker (issue #36). Public for the fast PHP suite.
     *
     * @param string $content Template post_content.
     * @return bool
     */
    public static function is_stock_woo_block_template($content) {
        $content = (string) $content;
        if ('' === $content || false !== strpos($content, 'w4e-')) {
            return false;
        }
        // Stock blockified templates always carry at least one Woo block.
        if (false === strpos($content, 'wp:woocommerce/')
            && false === strpos($content, '__woocommerceNamespace')) {
            return false;
        }
        if (!function_exists('parse_blocks')) {
            // Fast-suite fallback: header + footer template-parts framing
            // Woo blocks, with no builder customization cues beyond Woo.
            return (bool) preg_match(
                '/^\s*<!--\s*wp:template-part\b.*-->\s*.*wp:woocommerce\/.*<!--\s*wp:template-part\b.*-->\s*$/s',
                $content
            ) && false === strpos($content, 'wp:paragraph')
              && false === strpos($content, 'wp:heading')
              && false === strpos($content, 'wp:html')
              && false === strpos($content, 'wp:etch');
        }
        $named = array_values(array_filter(
            parse_blocks($content),
            static function ($b) {
                return !empty($b['blockName']);
            }
        ));
        return self::named_blocks_are_stock_woo_template($named);
    }

    /**
     * Structural check on already-parsed named top-level blocks.
     * Separated so tests can feed hand-built trees without parse_blocks.
     *
     * @param array<int,array<string,mixed>> $named Top-level blocks with blockName set.
     * @return bool
     */
    public static function named_blocks_are_stock_woo_template(array $named) {
        $n = count($named);
        if ($n < 3) {
            return false;
        }
        if (($named[0]['blockName'] ?? '') !== 'core/template-part') {
            return false;
        }
        if (($named[$n - 1]['blockName'] ?? '') !== 'core/template-part') {
            return false;
        }
        $middle = array_slice($named, 1, -1);
        foreach ($middle as $block) {
            if (!self::block_is_woo_stock_shaped($block)) {
                return false;
            }
        }
        return true;
    }

    /**
     * A block that appears in WooCommerce's blockified templates (or nests
     * only such blocks). Anything else means the builder has customized the
     * middle — push stays append-only for that case.
     *
     * @param array<string,mixed> $block
     * @return bool
     */
    public static function block_is_woo_stock_shaped(array $block) {
        $name = (string) ($block['blockName'] ?? '');
        if ('' === $name) {
            return true;
        }
        if (0 === strpos($name, 'woocommerce/')) {
            return true;
        }
        if ('core/pattern' === $name) {
            $slug = (string) ($block['attrs']['slug'] ?? '');
            return false !== strpos($slug, 'woocommerce');
        }

        static $woo_core = [
            'core/post-title'                 => true,
            'core/post-excerpt'               => true,
            'core/post-terms'                 => true,
            'core/query-title'                => true,
            'core/term-description'           => true,
            'core/query-pagination'           => true,
            'core/query-pagination-previous'  => true,
            'core/query-pagination-numbers'   => true,
            'core/query-pagination-next'      => true,
        ];
        if (isset($woo_core[$name])) {
            return true;
        }

        if (in_array($name, ['core/group', 'core/columns', 'core/column'], true)) {
            foreach ($block['innerBlocks'] ?? [] as $inner) {
                if (!empty($inner['blockName']) && !self::block_is_woo_stock_shaped($inner)) {
                    return false;
                }
            }
            return true;
        }

        return false;
    }

    /**
     * Keep header + footer template parts; put the layout blocks in between.
     *
     * @param string               $content        Full template content.
     * @param array<int,array>     $layout_blocks  Blocks from blocks_for_install().
     * @return array{content:string,offset:int}|WP_Error offset = named-block index of the layout run.
     */
    public static function replace_stock_template_middle($content, array $layout_blocks) {
        if (!function_exists('parse_blocks') || !function_exists('serialize_blocks')) {
            return new WP_Error('woo4etch_no_blocks_api', __('Block parsing is unavailable.', 'woo4etch'));
        }
        $parsed = parse_blocks((string) $content);
        $named_indices = [];
        foreach ($parsed as $i => $block) {
            if (!empty($block['blockName'])) {
                $named_indices[] = $i;
            }
        }
        if (count($named_indices) < 3) {
            return new WP_Error('woo4etch_not_stock_template', __('This template is not in the expected header / content / footer shape.', 'woo4etch'));
        }
        $first = $named_indices[0];
        $last  = $named_indices[count($named_indices) - 1];
        if (($parsed[$first]['blockName'] ?? '') !== 'core/template-part'
            || ($parsed[$last]['blockName'] ?? '') !== 'core/template-part') {
            return new WP_Error('woo4etch_not_stock_template', __('This template is not in the expected header / content / footer shape.', 'woo4etch'));
        }

        $merged = array_merge(
            array_slice($parsed, 0, $first + 1),
            $layout_blocks,
            array_slice($parsed, $last)
        );

        return [
            'content' => serialize_blocks($merged),
            // After save, named blocks are: header, ...layout..., footer.
            'offset'  => 1,
        ];
    }

    /**
     * True when the content contains any of the markers.
     *
     * @param string             $content Post content.
     * @param array<int,string>  $markers Substrings.
     * @return bool
     */
    public static function content_has($content, array $markers) {
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
