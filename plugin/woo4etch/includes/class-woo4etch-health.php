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
                    'markers' => ['w4e-cart', 'woocommerce-cart-form', '[woocommerce_cart', 'wp:woocommerce/cart'],
                ];
            case 'account':
                return [
                    'kind'    => 'page',
                    'page_id' => function_exists('wc_get_page_id') ? (int) wc_get_page_id('myaccount') : 0,
                    'label'   => __('My Account page', 'woo4etch'),
                    'markers' => ['w4e-account', '[woocommerce_my_account', '[woo_account_content'],
                ];
            case 'checkout':
                return [
                    'kind'    => 'page',
                    'page_id' => function_exists('wc_get_page_id') ? (int) wc_get_page_id('checkout') : 0,
                    'label'   => __('Checkout page', 'woo4etch'),
                    // Refuse when ANY checkout already renders there — a
                    // second checkout on the same page would double-submit.
                    'markers' => ['w4e-checkout-form', '[woocommerce_checkout', 'wp:woocommerce/checkout', '[woo_checkout_block'],
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
     * @param int    $post_id Target post.
     * @param string $slug    Layout catalog key.
     * @param int    $count   How many top-level blocks were appended.
     * @return void
     */
    public static function record_install($post_id, $slug, $count) {
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
        $ours = $count > 0 ? array_slice($blocks, -$count) : [];
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
