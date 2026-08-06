<?php
/**
 * Ready-made Etch layouts: one-click install as Etch patterns + copy/paste JSON.
 *
 * Layouts are defined as Etch block trees (the same structure Etch's own
 * copy/paste clipboard uses, version 2.1) plus a style map. They can be
 * - installed as an Etch *pattern* (wp_block post, mirroring Etch's own
 *   PatternsRoutes::create_pattern), with the styles merged into the global
 *   `etch_styles` option (deduplicated by selector, like Etch's paste flow), or
 * - exported as clipboard JSON the user pastes straight into the Etch builder.
 *
 * @package Woo4Etch
 */

if (!defined('ABSPATH') && PHP_SAPI !== 'cli') {
    exit;
}

/**
 * Layout catalog + installer.
 */
final class Woo4Etch_Layouts {

    /** Etch global styles option (same one Etch's StylesRoutes writes). */
    const ETCH_STYLES_OPTION = 'etch_styles';

    /* ============================================================
       Catalog
       ============================================================ */

    /**
     * All shippable layouts.
     *
     * @return array<string, array{name: string, description: string, area: string}>
     */
    public static function catalog() {
        return [
            'cart' => [
                'name'        => 'Cart — items, coupon, totals, cross-sells',
                'description' => 'Complete cart page as an Etch loop over {options.cart_items}: quantity update + coupon form (classic submit), order summary, checkout button, cross-sells.',
                'area'        => 'Cart',
            ],
            'product-single' => [
                'name'        => 'Single product — gallery + buy box',
                'description' => 'Featured image + gallery loop in Woo\'s gallery markup (zoom/lightbox/slider work when the gallery scripts are enabled in Settings), title, formatted price with sale badge, stock label, type-aware add-to-cart (hand-built form for simple products, Woo\'s native form for variable/grouped/external — variations fully working), SKU. Uses {this.*} product keys.',
                'area'        => 'Single product',
            ],
            'category' => [
                'name'        => 'Category archive — SEO intro + filtered grid',
                'description' => 'Category page template: term title, an editable intro copy block (placeholder text — write category-specific SEO copy), the term description from Products → Categories, then the same working filter sidebar + product grid as the shop. Installs into taxonomy-product_cat (all categories); duplicate as taxonomy-product_cat-{slug} in the editor for per-category pages.',
                'area'        => 'Category',
            ],
            'product-grid' => [
                'name'        => 'Shop archive — product grid',
                'description' => 'Product cards over the main archive query: image, sale badge, title, price, AJAX add-to-cart button. Uses {item.*} product keys.',
                'area'        => 'Archive',
            ],
            'checkout' => [
                'name'        => 'Checkout — Store API (option A+)',
                'description' => 'Complete hand-written checkout on the Store API layer: contact + billing fields, country select, live shipping selector, payment methods, legal checkboxes (Germanized) and an order summary — all as Etch loops over {options.checkout.*}. Address edits recalculate shipping/totals without a reload; the order is placed through WooCommerce\'s natively rate-limited Store API endpoint (redirect/offline gateways; others submit classically). Works as a classic form without JS.',
                'area'        => 'Checkout',
            ],
            'mini-cart' => [
                'name'        => 'Header mini-cart (link + dropdown)',
                'description' => 'Cart link with live item count plus a hover/focus dropdown: item rows, subtotal, view-cart/checkout buttons — and a friendly message instead of an empty box when the cart is empty. Pure CSS reveal (keyboard-accessible via :focus-within); the count span carries the mini-cart-count class used by the fragment snippet.',
                'area'        => 'Header',
            ],
            'account' => [
                'name'        => 'My Account — nav + endpoint views',
                'description' => 'Account navigation from {options.account_menu}; dashboard and orders views switched via {options.account_endpoint}; remaining endpoints fall back to [woo_account_content].',
                'area'        => 'Account',
            ],
            'thank-you' => [
                'name'        => 'Thank-you / order received',
                'description' => 'Order confirmation from {options.order}: notice, order overview (number, date, total, payment), line items loop. Shows only when an order is in context.',
                'area'        => 'Thank-you',
            ],
            'notices' => [
                'name'        => 'Woo notices — feedback messages',
                'description' => 'Queued WooCommerce feedback ("Cart updated.", coupon/form/security errors) as styleable .w4e-notice markup via [woo_notices format="plain"]. Already included in the cart, single-product and account layouts; insert this standalone version near the top of any other page layout — or use "Woo Notices as an Etch component" below to manage the region globally.',
                'area'        => 'Global',
            ],
        ];
    }

    /**
     * One layout: blocks + styles.
     *
     * @param string $slug Catalog key.
     * @return array{name: string, description: string, block: array<string,mixed>, styles: array<string,array<string,mixed>>}|null
     */
    public static function get($slug) {
        $meta = self::catalog()[$slug] ?? null;
        if ($meta === null) {
            return null;
        }

        if ($slug === 'cart') {
            $file = __DIR__ . '/../layouts/cart.json';
            $raw  = is_readable($file) ? file_get_contents($file) : '';
            $data = $raw ? json_decode($raw, true) : null;
            if (!is_array($data) || empty($data['gutenbergBlock'])) {
                return null;
            }
            $styles = isset($data['styles']) && is_array($data['styles']) ? $data['styles'] : [];
            $block  = self::bind_class_styles($data['gutenbergBlock'], $styles);
            return [
                'name'        => $meta['name'],
                'description' => $meta['description'],
                'block'       => $block,
                'styles'      => $styles,
            ];
        }

        $method = 'layout_' . str_replace('-', '_', $slug);
        if (!method_exists(__CLASS__, $method)) {
            return null;
        }
        $built  = self::$method();
        $styles = $built['styles'];
        $block  = self::bind_class_styles($built['block'], $styles);
        return [
            'name'        => $meta['name'],
            'description' => $meta['description'],
            'block'       => $block,
            'styles'      => $styles,
        ];
    }

    /**
     * Guarantee every literal class in a block's class attribute is backed by
     * a referenced style record.
     *
     * Etch's save reconciliation keeps a class on an element only while the
     * block also references a style record whose selector matches it. Classes
     * that ship without a record — above all the WooCommerce contract classes
     * (`cart`, `single_add_to_cart_button`, `button`, `quantity`, the gallery
     * classes) — are silently stripped on the first builder save, breaking
     * Woo's JS/styling contract while the page keeps rendering (issue #21).
     *
     * For every class without a matching record reference this attaches the
     * existing record for that selector, creating an empty one when none is
     * defined. Dynamic classes (`stock--{this.stock_status}`) are skipped —
     * no static selector can match them.
     *
     * @param array<string,mixed>                 $block  Block (parse_blocks shape).
     * @param array<string,array<string,mixed>>   $styles Style map, extended in place.
     * @return array<string,mixed>
     */
    private static function bind_class_styles(array $block, array &$styles) {
        $class_attr = $block['attrs']['attributes']['class'] ?? '';
        if (is_string($class_attr) && trim($class_attr) !== '') {
            $refs = isset($block['attrs']['styles']) && is_array($block['attrs']['styles'])
                ? array_values($block['attrs']['styles'])
                : [];

            $by_selector = [];
            foreach ($styles as $id => $style) {
                if (is_array($style) && isset($style['selector'])) {
                    $by_selector[(string) $style['selector']] = (string) $id;
                }
            }
            $ref_selectors = [];
            foreach ($refs as $ref) {
                if (isset($styles[$ref]['selector'])) {
                    $ref_selectors[(string) $styles[$ref]['selector']] = true;
                }
            }

            foreach (preg_split('/\s+/', trim($class_attr)) as $class) {
                if ($class === '' || strpos($class, '{') !== false) {
                    continue;
                }
                $selector = '.' . $class;
                if (isset($ref_selectors[$selector])) {
                    continue;
                }
                if (isset($by_selector[$selector])) {
                    $id = $by_selector[$selector];
                } else {
                    $id = self::sid($class);
                    // crc32 collision with a different selector → suffix.
                    while (isset($styles[$id]) && (string) ($styles[$id]['selector'] ?? '') !== $selector) {
                        $id .= 'w';
                    }
                    $styles[$id] = [
                        'type'       => 'class',
                        'selector'   => $selector,
                        'collection' => 'default',
                        'css'        => '',
                        'readonly'   => false,
                    ];
                    $by_selector[$selector] = $id;
                }
                $refs[]                   = $id;
                $ref_selectors[$selector] = true;
            }

            if ($refs) {
                $block['attrs']['styles'] = array_values(array_unique($refs));
            }
        }

        if (!empty($block['innerBlocks']) && is_array($block['innerBlocks'])) {
            foreach ($block['innerBlocks'] as $i => $inner) {
                if (is_array($inner)) {
                    $block['innerBlocks'][$i] = self::bind_class_styles($inner, $styles);
                }
            }
        }
        return $block;
    }

    /**
     * Clipboard JSON (Etch copy/paste format, version 2.1) for a layout.
     *
     * @param string $slug Catalog key.
     * @return string Empty string when unknown.
     */
    public static function clipboard_json($slug) {
        $layout = self::get($slug);
        if ($layout === null) {
            return '';
        }
        return (string) json_encode([
            'type'           => 'block',
            'gutenbergBlock' => $layout['block'],
            'styles'         => $layout['styles'],
            'version'        => 2.1,
        ], JSON_UNESCAPED_SLASHES);
    }

    /* ============================================================
       Install support (shared by the page push + component installer)
       ============================================================ */

    /**
     * Merge a layout's styles into the site's Etch style system and return
     * its block tree with the style references remapped accordingly — shared
     * by the page push (Woo4Etch_Health) and the component installer
     * (Woo4Etch_Components).
     *
     * @param string $slug Catalog key.
     * @return array<int,array<string,mixed>>|null Block list, null when unknown.
     */
    public static function blocks_for_install($slug) {
        $layout = self::get($slug);
        if ($layout === null) {
            return null;
        }
        $map = self::merge_styles($layout['styles']);
        return [self::remap_style_ids($layout['block'], $map)];
    }

    /**
     * Merge layout styles into the etch_styles option.
     *
     * @param array<string,array<string,mixed>> $styles Style ID => definition.
     * @return array<string,string> Old ID => final ID map.
     */
    private static function merge_styles(array $styles) {
        $existing = (array) get_option(self::ETCH_STYLES_OPTION, []);
        $map      = [];
        $changed  = false;

        foreach ($styles as $id => $style) {
            $selector   = isset($style['selector']) ? (string) $style['selector'] : '';
            $collection = isset($style['collection']) ? (string) $style['collection'] : 'default';

            // A style for this selector already exists → reuse its ID (keeps
            // every block reference on the site stable). A NON-EMPTY existing
            // record is never overwritten — that's the user's design. An
            // EMPTY record, however, contains no user work and would block
            // the shipped CSS forever (this is exactly how installs ended up
            // with unstyled carts: empty records created earlier "shadowed"
            // the shipped styles) — fill it with the shipped CSS instead.
            $matched = null;
            foreach ($existing as $ex_id => $ex) {
                if (is_array($ex)
                    && (string) ($ex['selector'] ?? '') === $selector
                    && (string) ($ex['collection'] ?? 'default') === $collection) {
                    $matched = (string) $ex_id;
                    break;
                }
            }
            if ($matched !== null) {
                // Fill only OUR selectors (.w4e-*): a generic contract class
                // like .button may be intentionally empty on a site whose
                // design system styles it elsewhere — filling it would
                // change site-wide styling.
                $shipped_css = isset($style['css']) ? trim((string) $style['css']) : '';
                if ('' !== $shipped_css
                    && 0 === strpos($selector, '.w4e-')
                    && '' === trim((string) ($existing[$matched]['css'] ?? ''))) {
                    $existing[$matched]['css'] = $style['css'];
                    $changed                   = true;
                }
                $map[$id] = $matched;
                continue;
            }

            // Free ID (avoid clobbering an unrelated style with the same ID).
            $final = (string) $id;
            while (isset($existing[$final])) {
                $final .= 'w';
            }
            $existing[$final] = $style;
            $map[$id]         = $final;
            $changed          = true;
        }

        if ($changed) {
            update_option(self::ETCH_STYLES_OPTION, $existing);
        }
        return $map;
    }

    /**
     * Rewrite style ID references in a block tree.
     *
     * @param array<string,mixed>  $block Block (parse_blocks shape).
     * @param array<string,string> $map   Old ID => final ID.
     * @return array<string,mixed>
     */
    private static function remap_style_ids(array $block, array $map) {
        if (isset($block['attrs']['styles']) && is_array($block['attrs']['styles'])) {
            $block['attrs']['styles'] = array_map(
                static function ($id) use ($map) {
                    return $map[$id] ?? $id;
                },
                $block['attrs']['styles']
            );
        }
        if (!empty($block['innerBlocks']) && is_array($block['innerBlocks'])) {
            foreach ($block['innerBlocks'] as $i => $inner) {
                if (is_array($inner)) {
                    $block['innerBlocks'][$i] = self::remap_style_ids($inner, $map);
                }
            }
        }
        return $block;
    }

    /* ============================================================
       Block DSL — produces the exact parse_blocks() shapes Etch uses
       ============================================================ */

    /**
     * etch/element block.
     *
     * @param string                    $tag        HTML tag.
     * @param array<string,string>      $attributes HTML attributes (dynamic keys allowed).
     * @param array<int,string>         $styles     Style IDs.
     * @param array<int,array>          $children   Child blocks.
     * @param string                    $name       Builder label.
     * @return array<string,mixed>
     */
    private static function el($tag, array $attributes = [], array $styles = [], array $children = [], $name = '') {
        $attrs = [];
        if ($name !== '') {
            $attrs['metadata'] = ['name' => $name];
        }
        $attrs['tag'] = $tag;
        // Never empty: an empty assoc array would JSON-encode as [] instead of {}.
        $attrs['attributes'] = $attributes ?: ['class' => ''];
        if ($styles) {
            $attrs['styles'] = array_values($styles);
        }
        return self::block('etch/element', $attrs, $children);
    }

    /** etch/text block. */
    private static function txt($content) {
        return [
            'blockName'    => 'etch/text',
            'attrs'        => ['metadata' => ['name' => 'Text'], 'content' => $content],
            'innerBlocks'  => [],
            'innerHTML'    => '',
            'innerContent' => [],
        ];
    }

    /** Element wrapping a single text child — the most common leaf. */
    private static function text_el($tag, $content, array $attributes = [], array $styles = [], $name = '') {
        return self::el($tag, $attributes, $styles, [self::txt($content)], $name);
    }

    /**
     * etch/raw-html block — for values that may contain HTML ({this.excerpt},
     * …): etch/text escapes HTML, so tags would show as literal text there.
     */
    private static function raw($content, $name = 'Raw HTML') {
        return [
            'blockName'    => 'etch/raw-html',
            'attrs'        => ['metadata' => ['name' => $name], 'content' => $content],
            'innerBlocks'  => [],
            'innerHTML'    => '',
            'innerContent' => [],
        ];
    }

    /** etch/loop block over a data path ({this.gallery_images}, {options.cart_items}, …). */
    private static function loop($target, $item_id, array $children) {
        return self::block('etch/loop', ['target' => $target, 'itemId' => $item_id], $children);
    }

    /**
     * etch/loop block bound to a loop preset (etch_loops option). Query-type
     * loops (main-query, wp-query) only work via presets — a raw target
     * string renders nothing for them.
     */
    private static function preset_loop($loop_id, $item_id, array $children) {
        return self::block('etch/loop', ['loopId' => $loop_id, 'itemId' => $item_id], $children);
    }

    /**
     * Resolve the site's global main-query loop preset, creating one if none
     * exists. Etch seeds installs with such a preset (id `etch_main_query`),
     * but the id is not guaranteed — match by config type instead.
     *
     * @return string Preset id for use as a loop block's loopId.
     */
    private static function ensure_main_query_loop() {
        $loops = (array) get_option('etch_loops', []);
        foreach ($loops as $id => $loop) {
            if (is_array($loop) && isset($loop['config']['type']) && 'main-query' === $loop['config']['type']) {
                return (string) $id;
            }
        }
        // Mirrors the shape of Etch's own default main-query preset.
        $loops['w4e_main_query'] = [
            'name'   => 'Main Query',
            'key'    => 'mainQuery',
            'global' => true,
            'config' => [
                'type' => 'main-query',
                'args' => [
                    'posts_per_page' => '$count ?? 10',
                    'orderby'        => "\$orderby ?? 'date'",
                    'order'          => "\$order ?? 'DESC'",
                    'offset'         => '$offset ?? 0',
                ],
            ],
        ];
        update_option('etch_loops', $loops);
        return 'w4e_main_query';
    }

    /**
     * etch/condition block.
     *
     * CRITICAL: conditionString must be the real EXPRESSION, not a label.
     * The Etch builder treats conditionString as the source of truth and
     * re-derives the condition object from it on every save — a label there
     * becomes leftHand after one builder round-trip, the path is lost, and
     * the condition silently evaluates false forever (real bug: the pushed
     * checkout page went blank after a builder save). The human-readable
     * label goes into metadata.name instead.
     *
     * @param array<string,mixed>|string $left     Data path, or nested condition array.
     * @param string                     $operator isTruthy|isFalsy|===|!==|==|!=|>|<|>=|<=|&&|\|\|.
     * @param mixed                      $right    Literal ('orders' must be passed quoted: "'orders'"), path, or nested condition.
     * @param array<int,array>           $children Child blocks.
     * @param string                     $label    Display name for the builder tree (metadata.name).
     * @return array<string,mixed>
     */
    private static function cond($left, $operator, $right, array $children, $label) {
        return self::block(
            'etch/condition',
            [
                'metadata'        => ['name' => $label],
                'condition'       => ['leftHand' => $left, 'operator' => $operator, 'rightHand' => $right],
                'conditionString' => self::cond_expression($left, $operator, $right),
            ],
            $children
        );
    }

    /**
     * Derive the builder-parseable expression for a condition — the exact
     * string the Etch builder round-trips (see cond()).
     *
     * @param array<string,mixed>|string $left
     * @param string                     $operator
     * @param mixed                      $right
     * @return string
     */
    private static function cond_expression($left, $operator, $right) {
        $l = is_array($left)
            ? self::cond_expression($left['leftHand'], $left['operator'], $left['rightHand'])
            : (string) $left;
        if ('isTruthy' === $operator) {
            return $l;
        }
        if ('isFalsy' === $operator) {
            return '!' . $l;
        }
        $r = is_array($right)
            ? self::cond_expression($right['leftHand'], $right['operator'], $right['rightHand'])
            : (string) $right;
        return $l . ' ' . $operator . ' ' . $r;
    }

    /** Shorthand: nested condition operand (not a block). */
    private static function c($left, $operator, $right = null) {
        return ['leftHand' => $left, 'operator' => $operator, 'rightHand' => $right];
    }

    /**
     * Assemble a block with Etch/Gutenberg innerHTML/innerContent bookkeeping.
     *
     * @param string              $block_name etch/element etc.
     * @param array<string,mixed> $attrs      Block attrs.
     * @param array<int,array>    $children   Child blocks.
     * @return array<string,mixed>
     */
    private static function block($block_name, array $attrs, array $children) {
        if (!$children) {
            return [
                'blockName'    => $block_name,
                'attrs'        => $attrs,
                'innerBlocks'  => [],
                'innerHTML'    => "\n\n",
                'innerContent' => ["\n", "\n"],
            ];
        }

        $content = ["\n"];
        $count   = count($children);
        foreach (array_values($children) as $i => $unused) {
            $content[] = null;
            $content[] = ($i === $count - 1) ? "\n" : "\n\n";
        }

        return [
            'blockName'    => $block_name,
            'attrs'        => $attrs,
            'innerBlocks'  => array_values($children),
            'innerHTML'    => implode('', array_filter($content, 'is_string')),
            'innerContent' => $content,
        ];
    }

    /** Deterministic style ID for a selector (stable across installs/exports). */
    private static function sid($class) {
        return 'w4e' . base_convert(sprintf('%u', crc32($class)), 10, 36);
    }

    /** Style map entry for a CSS class. */
    private static function cls(array &$styles, $class, $css) {
        $id = self::sid($class);
        $styles[$id] = [
            'type'       => 'class',
            'selector'   => '.' . $class,
            'collection' => 'default',
            'css'        => $css,
            'readonly'   => false,
        ];
        return $id;
    }

    /* ============================================================
       Layout definitions
       ============================================================ */

    /** Shared .button style (same look as the cart layout). */
    private static function button_style(array &$styles) {
        return self::cls($styles, 'button', 'display: inline-block; background: var(--primary, #111827); color: var(--white, #fff); border: 0; padding: var(--btn-padding-block, 13px) var(--btn-padding-inline, 22px); border-radius: var(--btn-radius, 10px); font-weight: var(--btn-font-weight, 600); font-size: var(--btn-font-size, 15px); cursor: pointer; text-align: center;');
    }

    /** Shared sale badge style. */
    private static function badge_style(array &$styles) {
        return self::cls($styles, 'w4e-badge', 'display: inline-block; align-self: flex-start; background: var(--accent, #ff4d2d); color: var(--white, #fff); font-size: 11px; font-weight: 700; padding: 2px var(--space-xs, 8px); border-radius: 999px;');
    }

    /**
     * Shared Woo-notices region — [woo_notices format="plain"] wrapped in a
     * styleable .w4e-notices element. The one block every page layout needs:
     * without it, Woo feedback ("Cart updated.", coupon/form/security errors)
     * is invisible and failed actions look like silent no-ops.
     *
     * Structurally identical in every layout on purpose: select any instance
     * in Etch and "save as component" to manage it globally (Etch has no API
     * for plugins to install components directly — see ETCH-FEATURE-REQUESTS.md).
     * The element carries all notice style refs so the records survive with it.
     */
    private static function notices_block(array &$styles) {
        $refs = [
            // Hidden while empty ([woo_notices] returns '' when nothing is
            // queued) so the wrapper never reserves space on quiet pages; in
            // the builder the shortcode renders a sample notice instead.
            self::cls($styles, 'w4e-notices', 'display: flex; flex-direction: column; gap: var(--space-xs, 10px); margin-block-end: 20px; &:not(:has(.w4e-notice)) { display: none; }'),
            self::cls($styles, 'w4e-notice', 'padding: var(--space-xs, 12px) var(--space-s, 16px); border-radius: var(--radius, 8px); border: var(--border, 1px solid #e5e5e5); background: var(--neutral-ultra-light, #fafafa); font-size: var(--text-s, 14px); line-height: var(--text-line-height, 1.5);'),
            self::cls($styles, 'w4e-notice--error', 'background: var(--danger-ultra-light, #fef2f2); border-color: var(--danger-light, #fecaca); color: var(--danger-dark, #b91c1c);'),
            self::cls($styles, 'w4e-notice--success', 'background: var(--success-ultra-light, #f0fdf4); border-color: var(--success-light, #bbf7d0); color: var(--success-dark, #166534);'),
            self::cls($styles, 'w4e-notice--notice', 'background: var(--info-ultra-light, #eff6ff); border-color: var(--info-light, #bfdbfe); color: var(--info-dark, #1e40af);'),
        ];
        return self::el('div', ['class' => 'w4e-notices'], $refs, [
            self::raw('[woo_notices format="plain"]', 'Woo notices'),
        ], 'Notices');
    }

    /** Standalone "Woo notices" layout — just the shared block, insert anywhere. */
    private static function layout_notices() {
        $s = [];
        return ['block' => self::notices_block($s), 'styles' => $s];
    }

    /** Single product: gallery + buy box. */
    private static function layout_product_single() {
        $s = [];

        $section  = self::cls($s, 'w4e-product', '');
        $layout   = self::cls($s, 'w4e-product-layout', 'display: grid; grid-template-columns: minmax(0, 1fr); gap: var(--grid-gap, 32px); align-items: start; padding-block: var(--space-m, 24px) var(--space-xl, 48px); @media (min-width: 992px) { grid-template-columns: minmax(0, 1.1fr) minmax(0, 1fr); gap: var(--grid-gap, 40px); }');
        // Gallery: Woo's gallery classes/attributes so WooCommerce's own
        // zoom/lightbox/slider scripts initialise on it when enabled
        // (Woo4Etch → Settings). Without the scripts the nested CSS lays the
        // same markup out as featured image + thumbnail grid; the .flex-*
        // rules style the FlexSlider DOM (thumbnails, viewport) once active.
        // Production-hardened (issue #20): opacity guard against the inline
        // fade-in style, full-width flex-viewport (shrinks-to-fit mid-init
        // otherwise), min-inline-size grid-blowout guard, thumbs as a stable
        // grid (flex:1 stretches thumbs when there are fewer than
        // data-columns images), --primary active border. assets/gallery.css
        // ships the same rules generically; these scoped ones must match or
        // they'd win the specificity fight with the outdated version.
        $gallery = self::cls(
            $s,
            'w4e-gal',
            'position: relative; opacity: 1 !important; min-inline-size: 0;'
            . ' & .w4e-gal-wrap { display: grid; grid-template-columns: var(--grid-4, repeat(4, 1fr)); gap: var(--space-xs, 10px); margin: 0; }'
            . ' & .flex-viewport .w4e-gal-wrap { display: block; }'
            . ' & .w4e-gal-item--featured { grid-column: 1 / -1; }'
            . ' & .w4e-gal-item a { display: block; }'
            . ' & .w4e-gal-item img:not(.zoomImg) { width: 100%; aspect-ratio: 1; object-fit: cover; border-radius: var(--radius, 14px); background: var(--neutral-ultra-light, #f3f4f6); display: block; }'
            . ' & .flex-viewport { inline-size: 100%; border-radius: var(--radius, 14px); }'
            . ' & .flex-control-thumbs { display: grid; grid-template-columns: var(--grid-4, repeat(4, minmax(0, 1fr))); gap: var(--space-xs, 10px); margin: 10px 0 0; padding: 0; list-style: none; }'
            . ' & .flex-control-thumbs li { cursor: pointer; margin: 0; min-inline-size: 0; }'
            . ' & .flex-control-thumbs img { width: 100%; aspect-ratio: 1; object-fit: cover; border-radius: var(--radius, 10px); border: var(--border, 1px solid #e5e7eb); opacity: .6; transition: opacity .2s, border-color .2s; }'
            . ' & .flex-control-thumbs img.flex-active, & .flex-control-thumbs img:hover { opacity: 1; border-color: var(--primary, currentColor); }'
            . ' & .woocommerce-product-gallery__trigger { position: absolute; top: 12px; right: 12px; z-index: 9; display: grid; place-items: center; width: 36px; height: 36px; background: var(--white, #fff); border: var(--border, 1px solid #e5e7eb); border-radius: 999px; }'
        );
        // The buy-box container also carries the styles for the runtime UI
        // pills.js builds inside it (variation pills, quantity stepper,
        // variations-table reset) — nested here instead of a plugin
        // stylesheet, so they live as an Etch class record: visible and
        // editable in the builder's style panel, rendered exactly when the
        // block is on the page. Token-based with plain fallbacks.
        $info     = self::cls(
            $s,
            'w4e-product-info',
            'display: flex; flex-direction: column; gap: var(--space-s, 14px);'
            . ' & .w4e-pills { display: flex; flex-wrap: wrap; gap: var(--space-xs, 8px); }'
            . ' & .w4e-pill { font-family: var(--text-font-family, inherit); font-size: var(--text-s, 15px); font-weight: 500; color: var(--text-dark, #16181d); background: var(--neutral-ultra-light, #f6f6f7); border: var(--border, 1px solid #d9dbe0); border-radius: calc(var(--radius, 10px) / 2); padding: calc(var(--space-xs, 8px) / 1.5) var(--space-s, 14px); cursor: pointer; }'
            . ' & .w4e-pill:hover { border-color: var(--primary, #111827); }'
            . ' & .w4e-pill.is-selected { background: var(--primary, #111827); border-color: var(--primary, #111827); color: var(--white, #fff); }'
            . ' & .w4e-pill:focus-visible { outline: 2px solid var(--primary, #111827); outline-offset: 2px; }'
            . ' & .variations select[data-w4e-pills] { position: absolute; inline-size: 1px; block-size: 1px; clip-path: inset(50%); overflow: hidden; }'
            . ' & .variations, & .variations tbody, & .variations tr, & .variations th, & .variations td { display: block; padding: 0; border: 0; }'
            . ' & .variations tr { margin-block-end: var(--space-s, 14px); }'
            . ' & .variations th.label { margin-block-end: calc(var(--space-xs, 8px) / 2); font-size: var(--text-xs, 13px); font-weight: 600; letter-spacing: .08em; text-transform: uppercase; color: var(--text-dark, #16181d); }'
            . ' & .reset_variations { font-size: var(--text-xs, 13px); color: var(--text-dark-muted, #6b7280); }'
            . ' & .w4e-qty { display: inline-flex; align-items: stretch; border: var(--border, 1px solid #d9dbe0); border-radius: var(--radius, 10px); overflow: hidden; background: var(--neutral-ultra-light, #f6f6f7); }'
            . ' & .w4e-qty input.qty, & .w4e-qty input[name="quantity"] { border: 0; border-radius: 0; text-align: center; inline-size: 3.5rem; background: transparent; }'
            . ' & .w4e-qty__btn { border: 0; background: transparent; color: var(--text-dark, #16181d); font-size: var(--text-m, 17px); font-weight: 600; inline-size: 2.4rem; cursor: pointer; }'
            . ' & .w4e-qty__btn:hover { background: var(--neutral-light, #e8e9ec); }'
        );
        $title    = self::cls($s, 'w4e-product__title', 'font-size: var(--h1, 32px); letter-spacing: var(--heading-letter-spacing, -0.02em); margin: 0;');
        $pricerow = self::cls($s, 'w4e-product__pricerow', 'display: flex; align-items: center; gap: var(--space-xs, 12px);');
        $price    = self::cls($s, 'w4e-product__price', 'font-size: var(--text-xxl, 24px); font-weight: 800;');
        $excerpt  = self::cls($s, 'w4e-product__excerpt', 'color: var(--text-dark-muted, #6b7280); margin: 0;');
        $stock    = self::cls($s, 'w4e-product__stock', 'font-size: var(--text-s, 14px); color: var(--success-dark, #15803d); margin: 0;');
        $form     = self::cls($s, 'w4e-product__form', 'display: flex; flex-wrap: wrap; gap: var(--space-xs, 10px); align-items: stretch; margin-top: 4px;');
        // The top price row syncs to the chosen variation (swatches.js), so the
        // duplicate price Woo renders inside the form is hidden here.
        $nativecart = self::cls($s, 'w4e-native-cart', '& .woocommerce-variation-price { display: none; }');
        $qty      = self::cls($s, 'w4e-product__qty', 'width: 84px; padding: var(--space-xs, 11px); border: var(--border, 1px solid #e6e7eb); border-radius: var(--radius, 10px); font-size: var(--text-s, 15px);');
        $meta     = self::cls($s, 'w4e-product__meta', 'color: var(--text-dark-muted, #6b7280); font-size: var(--text-xs, 13px); border-top: var(--border, 1px solid #e6e7eb); padding-top: var(--space-s, 14px); margin-top: 6px;');
        $badge    = self::badge_style($s);
        $button   = self::button_style($s);

        $block = self::el('section', ['data-etch-element' => 'section', 'class' => 'w4e-product'], ['etch-section-style', $section], [
            self::el('div', ['data-etch-element' => 'container'], ['etch-container-style'], [
                // Add-to-cart feedback (qty limits, out-of-stock, required
                // variation …) arrives as Woo notices on this page.
                self::notices_block($s),
                self::el('div', ['class' => 'w4e-product-layout'], [$layout], [

                    self::el('div', [
                        'class'        => 'w4e-gal woocommerce-product-gallery woocommerce-product-gallery--with-images woocommerce-product-gallery--columns-4 images',
                        'data-columns' => '4',
                    ], [$gallery], [
                        self::el('figure', ['class' => 'w4e-gal-wrap woocommerce-product-gallery__wrapper'], [], [
                            self::el('div', [
                                'class'          => 'w4e-gal-item w4e-gal-item--featured woocommerce-product-gallery__image',
                                'data-thumb'     => '{this.image.url}',
                                'data-thumb-alt' => '{this.title}',
                            ], [], [
                                self::el('a', ['href' => '{this.image.url}'], [], [
                                    self::el('img', [
                                        'class'                   => 'wp-post-image',
                                        'src'                     => '{this.image.url}',
                                        'alt'                     => '{this.title}',
                                        'data-src'                => '{this.image.url}',
                                        'data-large_image'        => '{this.image.url}',
                                        'data-large_image_width'  => '{this.image.width}',
                                        'data-large_image_height' => '{this.image.height}',
                                    ], [], [], 'Featured image'),
                                ], 'Featured link'),
                            ], 'Featured'),
                            self::loop('this.gallery_images', 'image', [
                                self::el('div', [
                                    'class'          => 'w4e-gal-item woocommerce-product-gallery__image',
                                    'data-thumb'     => '{image.url}',
                                    'data-thumb-alt' => '{image.alt}',
                                ], [], [
                                    self::el('a', ['href' => '{image.url}'], [], [
                                        self::el('img', [
                                            'src'                     => '{image.url}',
                                            'alt'                     => '{image.alt}',
                                            'data-src'                => '{image.url}',
                                            'data-large_image'        => '{image.url}',
                                            'data-large_image_width'  => '{image.width}',
                                            'data-large_image_height' => '{image.height}',
                                        ], [], [], 'Image'),
                                    ], 'Link'),
                                ], 'Slide'),
                            ]),
                        ], 'Wrapper'),
                    ], 'Gallery'),

                    self::el('div', ['class' => 'w4e-product-info'], [$info], [
                        self::text_el('h1', '{this.title}', ['class' => 'w4e-product__title product_title entry-title'], [$title], 'Title'),
                        self::el('div', ['class' => 'w4e-product__pricerow'], [$pricerow], [
                            // data-w4e-variation-price: swatches.js mirrors the
                            // chosen variation's live price into this element.
                            self::text_el('span', '{this.price}', ['class' => 'w4e-product__price', 'data-w4e-variation-price' => ''], [$price], 'Price'),
                            self::cond('this.is_on_sale', 'isTruthy', null, [
                                self::text_el('span', '-{this.sale_percentage}%', ['class' => 'w4e-badge'], [$badge], 'Sale badge'),
                            ], 'this.is_on_sale'),
                        ], 'Price row'),
                        // Third-party summary extras (Germanized unit price /
                        // tax & shipping notices / delivery time, review stars,
                        // structured data, …): fires the standard summary hook
                        // with WooCommerce core's own callbacks skipped — the
                        // layout already renders title/price/excerpt/form.
                        self::el('div', ['class' => 'w4e-hook w4e-product__summary-extras', 'data-w4e-hook' => 'woocommerce_single_product_summary', 'data-w4e-skip-defaults' => '1', 'data-w4e-product' => '{this.id}'], [], [], 'Hook: summary extras'),
                        // Raw HTML, not a text element: Woo short descriptions
                        // may contain HTML and etch/text would escape it.
                        self::el('div', ['class' => 'w4e-product__excerpt woocommerce-product-details__short-description'], [$excerpt], [
                            self::raw('{this.excerpt}', 'Excerpt HTML'),
                        ], 'Excerpt'),
                        self::text_el('p', '{this.stock_label}', ['class' => 'w4e-product__stock stock--{this.stock_status}'], [$stock], 'Stock'),
                        // Simple products: hand-built classic-POST form.
                        // is_simple (bool, from Woo4Etch) — NOT product_type:
                        // Etch shadows that key with the taxonomy term object.
                        // The data-w4e-hook markers are filled server-side with
                        // do_action() output, so third-party plugins hooking the
                        // standard Woo positions (trust badges, express-pay
                        // buttons, …) appear like on a native product page.
                        self::cond('this.is_simple', 'isTruthy', null, [
                            self::el('div', ['class' => 'w4e-hook', 'data-w4e-hook' => 'woocommerce_before_add_to_cart_form', 'data-w4e-product' => '{this.id}'], [], [], 'Hook: before form'),
                            self::el('form', ['class' => 'cart w4e-product__form', 'action' => '{this.permalink.relative}', 'method' => 'post', 'enctype' => 'multipart/form-data'], [$form], [
                                // Woo's classic quantity classes (div.quantity >
                                // input.qty) so third-party scripts targeting the
                                // native markup (steppers, min/max plugins) work.
                                self::el('div', ['class' => 'quantity'], [], [
                                    self::el('input', ['class' => 'w4e-product__qty input-text qty text', 'type' => 'number', 'name' => 'quantity', 'value' => '1', 'min' => '1', 'step' => '1'], [$qty], [], 'Qty'),
                                ], 'Quantity'),
                                self::text_el('button', '{this.add_to_cart_text}', ['class' => 'single_add_to_cart_button button', 'type' => 'submit', 'name' => 'add-to-cart', 'value' => '{this.id}'], [$button], 'Add to cart'),
                                self::el('div', ['class' => 'w4e-hook', 'data-w4e-hook' => 'woocommerce_after_add_to_cart_button', 'data-w4e-product' => '{this.id}'], [], [], 'Hook: after button'),
                            ], 'Add-to-cart form'),
                            self::el('div', ['class' => 'w4e-hook', 'data-w4e-hook' => 'woocommerce_after_add_to_cart_form', 'data-w4e-product' => '{this.id}'], [], [], 'Hook: after form'),
                        ], 'this.is_simple'),
                        // Variable/grouped/external: WooCommerce's native form
                        // (attribute selects with options, variations JSON,
                        // wc-add-to-cart-variation script — all from Woo core;
                        // swatches.js bridges custom swatch markup on top).
                        // The marker div is filled server-side by the plugin
                        // (render_block) — raw-html + shortcode would get the
                        // form tags stripped by Etch's sanitizer instead. The
                        // native form fires the standard Woo hooks itself.
                        self::cond('this.is_simple', 'isFalsy', null, [
                            self::el('div', ['class' => 'w4e-native-cart', 'data-w4e-add-to-cart' => '{this.id}'], [$nativecart], [], 'Native add-to-cart'),
                        ], '!this.is_simple'),
                        self::text_el('div', 'SKU: {this.sku}', ['class' => 'w4e-product__meta'], [$meta], 'Meta'),
                    ], 'Buy box'),

                ]),
            ]),
        ], 'W4e Product');

        return ['block' => $block, 'styles' => self::with_base_styles($s)];
    }

    /**
     * Shop archive: heading + category pills + filter sidebar + product grid.
     *
     * The filters are 100% native WooCommerce: the sidebar's GET form submits
     * `min_price`/`max_price` to the current archive URL and WC_Query filters
     * the main product query server-side — the Etch main-query loop simply
     * renders the filtered result. Category pills/links navigate the term
     * archives. Attribute checkbox filters (`filter_<slug>`) can be added the
     * same way — see templates/03-product-archive.md.
     */
    private static function layout_product_grid() {
        $s = [];

        $section = self::cls($s, 'w4e-shop', '');
        $title   = self::cls($s, 'w4e-shop__title', 'font-size: var(--h1, 40px); font-weight: var(--heading-font-weight, 800); letter-spacing: var(--heading-letter-spacing, -0.02em); margin: 24px 0 20px;');

        $block = self::el('section', ['data-etch-element' => 'section', 'class' => 'w4e-shop'], ['etch-section-style', $section], [
            self::el('div', ['data-etch-element' => 'container'], ['etch-container-style'], [
                self::text_el('h1', '{archive.title}', ['class' => 'w4e-shop__title'], [$title], 'Title'),
                self::category_slider($s),
                self::archive_columns($s),
            ]),
        ], 'W4e Shop Grid');

        return ['block' => $block, 'styles' => self::with_base_styles($s)];
    }

    /**
     * Category archive: the SEO-focused variant of the shop layout — term
     * title, an editable intro copy block (placeholder text: write real,
     * category-specific copy here), the term description maintained under
     * Products → Categories, then the same filter sidebar + product grid.
     *
     * Installs into the `taxonomy-product_cat` template (all categories).
     * For per-category copy beyond the term description, duplicate the
     * template in the editor as `taxonomy-product_cat-{slug}` — WordPress's
     * template hierarchy picks the specific one automatically.
     */
    private static function layout_category() {
        $s = [];

        $section = self::cls($s, 'w4e-category', '');
        $title   = self::cls($s, 'w4e-shop__title', 'font-size: var(--h1, 40px); font-weight: var(--heading-font-weight, 800); letter-spacing: var(--heading-letter-spacing, -0.02em); margin: 24px 0 20px;');
        $intro   = self::cls($s, 'w4e-category__intro', 'max-width: 68ch; margin: 0 0 28px; display: flex; flex-direction: column; gap: var(--space-xs, 12px); & p { margin: 0; color: var(--text-dark, #374151); font-size: var(--text-m, 16px); line-height: var(--text-line-height, 1.65); }');

        $block = self::el('section', ['data-etch-element' => 'section', 'class' => 'w4e-category'], ['etch-section-style', $section], [
            self::el('div', ['data-etch-element' => 'container'], ['etch-container-style'], [
                self::text_el('h1', '{archive.title}', ['class' => 'w4e-shop__title'], [$title], 'Title'),
                self::el('div', ['class' => 'w4e-category__intro'], [$intro], [
                    // Placeholder copy — replace with real category copy (this
                    // is the SEO text block; it renders on every category
                    // unless you fork a per-category template).
                    self::text_el('p', 'Lorem ipsum dolor sit amet, consectetur adipiscing elit. Praesent commodo lacus at porta congue. Integer euismod, nibh sit amet dignissim tincidunt, augue urna luctus arcu, non varius nunc nibh id ligula. Curabitur nec justo vitae magna faucibus efficitur.', ['class' => ''], [], 'Intro copy'),
                    self::text_el('p', 'Suspendisse potenti. Aliquam erat volutpat — vivamus pretium, sapien sed dictum egestas, purus lacus tristique justo, in convallis nulla est in ligula.', ['class' => ''], [], 'Intro copy 2'),
                    // The term description from Products → Categories — Etch
                    // exposes the queried term natively as {term.*} on
                    // taxonomy archives. Raw HTML block: descriptions may
                    // contain markup, which etch/text would escape.
                    self::raw('{term.description}', 'Term description'),
                ], 'SEO intro'),
                self::archive_columns($s),
            ]),
        ], 'W4e Category');

        return ['block' => $block, 'styles' => self::with_base_styles($s)];
    }

    /**
     * Category slider: a horizontally snapping strip of round image cards
     * (CSS scroll-snap — swipeable on touch, no JS, thin scrollbar on
     * desktop; edge-fade hints at more content). Shared block builder.
     *
     * @param array<string,array<string,mixed>> $s Style map, extended in place.
     * @return array<string,mixed>
     */
    private static function category_slider(array &$s) {
        $catbar  = self::cls($s, 'w4e-catslider', 'display: flex; gap: var(--space-s, 20px); margin: 0 0 28px; overflow-x: auto; scroll-snap-type: x proximity; padding: 4px 4px var(--space-xs, 12px); scrollbar-width: thin; scrollbar-color: var(--neutral-light, #d1d5db) transparent; -webkit-overflow-scrolling: touch; mask-image: linear-gradient(to right, #000 calc(100% - 32px), transparent);');
        $catpill = self::cls($s, 'w4e-catslide', 'scroll-snap-align: start; flex: 0 0 auto; display: flex; flex-direction: column; align-items: center; gap: var(--space-xs, 10px); min-width: 112px; max-width: 160px; color: inherit; font-weight: 600; font-size: var(--text-s, 14px); text-align: center; text-decoration: none; overflow-wrap: anywhere; &:hover .w4e-catslide__img { border-color: var(--primary, #111827); transform: translateY(-2px); }');
        $catimg  = self::cls($s, 'w4e-catslide__img', 'width: 96px; height: 96px; border-radius: 999px; object-fit: cover; background: var(--neutral-ultra-light, #f0f0f1); border: var(--border, 1px solid #e6e7eb); transition: .15s;');

        return self::el('div', ['class' => 'w4e-catslider'], [$catbar], [
            self::loop('options.shop_categories', 'c', [
                self::el('a', ['class' => 'w4e-catslide', 'href' => '{c.url}'], [$catpill], [
                    self::el('img', ['class' => 'w4e-catslide__img', 'src' => '{c.image}', 'alt' => '{c.name}'], [$catimg], [], 'Img'),
                    self::txt('{c.name}'),
                ], 'Category slide'),
            ]),
        ], 'Category slider');
    }

    /**
     * Filter sidebar + product grid columns — the working core of the
     * archive layouts, shared by the shop grid and the category template.
     *
     * @param array<string,array<string,mixed>> $s Style map, extended in place.
     * @return array<string,mixed>
     */
    private static function archive_columns(array &$s) {
        $cols = self::cls($s, 'w4e-shop-cols', 'display: grid; grid-template-columns: minmax(0, 1fr); gap: var(--grid-gap, 32px); align-items: start; padding-block: 0 var(--space-xl, 48px); @media (min-width: 992px) { grid-template-columns: 280px minmax(0, 1fr); }');

        $filter    = self::cls($s, 'w4e-filter', 'background: var(--white, #fff); border: var(--border, 1px solid #e6e7eb); border-radius: var(--radius, 16px); padding: var(--space-s, 20px); display: flex; flex-direction: column; gap: var(--space-s, 20px); @media (min-width: 992px) { position: sticky; top: 88px; }');
        $fgroup    = self::cls($s, 'w4e-filter__group', 'display: flex; flex-direction: column; gap: var(--space-xs, 10px); &:not(:last-child) { border-bottom: var(--border, 1px solid #e6e7eb); padding-bottom: var(--space-s, 20px); }');
        $fheading  = self::cls($s, 'w4e-filter__heading', 'margin: 0; font-size: var(--text-m, 16px); font-weight: 700;');
        $fhint     = self::cls($s, 'w4e-filter__hint', 'margin: 0; font-size: var(--text-xs, 13px); color: var(--text-dark-muted, #6b7280);');
        $flist     = self::cls($s, 'w4e-filter__list', 'list-style: none; margin: 0; padding: 0; display: flex; flex-direction: column; gap: var(--space-xs, 8px);');
        $flink     = self::cls($s, 'w4e-filter__link', 'display: flex; justify-content: space-between; gap: var(--space-xs, 8px); color: var(--text-dark, #16181d); text-decoration: none; font-size: var(--text-s, 14px); &:hover { text-decoration: underline; }');
        $factive   = self::cls($s, 'w4e-filter__link--active', 'font-weight: 700;');
        $fcount    = self::cls($s, 'w4e-filter__count', 'color: var(--text-dark-muted, #9ca3af);');
        // The price form record carries the styles for the dual-range slider
        // price-slider.js builds inside it — as an Etch class record, not a
        // plugin stylesheet, so it renders with the block and is editable in
        // the builder's style panel.
        $fform     = self::cls(
            $s,
            'w4e-filter__form',
            '& .w4e-range { position: relative; height: 28px; margin: 6px 2px 2px; }'
            . ' & .w4e-range__track { position: absolute; inset-inline: 0; top: 50%; height: 4px; transform: translateY(-50%); border-radius: 999px; background: var(--base-light, #e6e7eb); }'
            . ' & .w4e-range__fill { position: absolute; top: 50%; height: 4px; transform: translateY(-50%); border-radius: 999px; background: var(--primary, #111827); }'
            . ' & .w4e-range input[type="range"] { position: absolute; inset-inline: 0; top: 0; width: 100%; height: 28px; margin: 0; background: transparent; -webkit-appearance: none; appearance: none; pointer-events: none; }'
            . ' & .w4e-range input[type="range"]::-webkit-slider-thumb { -webkit-appearance: none; appearance: none; pointer-events: auto; width: 20px; height: 20px; border-radius: 999px; background: var(--white, #fff); border: 2px solid var(--primary, #111827); box-shadow: 0 1px 4px rgba(0, 0, 0, .15); cursor: grab; }'
            . ' & .w4e-range input[type="range"]::-moz-range-thumb { pointer-events: auto; width: 16px; height: 16px; border-radius: 999px; background: var(--white, #fff); border: 2px solid var(--primary, #111827); box-shadow: 0 1px 4px rgba(0, 0, 0, .15); cursor: grab; }'
            . ' & .w4e-range input[type="range"]:focus-visible::-webkit-slider-thumb { outline: 2px solid var(--primary, #111827); outline-offset: 2px; }'
        );
        $prices    = self::cls($s, 'w4e-filter__prices', 'display: flex; gap: var(--space-xs, 8px);');
        $pricein   = self::cls($s, 'w4e-filter__price', 'width: 100%; min-width: 0; padding: var(--space-xs, 9px) var(--space-xs, 12px); border: var(--border, 1px solid #e6e7eb); border-radius: 999px; font-size: var(--text-s, 14px);');
        $fapply    = self::cls($s, 'w4e-filter__apply', 'width: 100%;');
        $freset    = self::cls($s, 'w4e-filter__reset', 'font-size: var(--text-xs, 13px); color: var(--text-dark-muted, #6b7280); text-align: center; text-decoration: underline;');

        $maincol = self::cls($s, 'w4e-shop-main', 'display: flex; flex-direction: column; gap: var(--space-l, 32px); min-width: 0;');
        $grid    = self::cls($s, 'w4e-shopgrid', 'display: grid; grid-template-columns: repeat(auto-fill, minmax(150px, 1fr)); gap: var(--grid-gap, 16px); @media (min-width: 768px) { grid-template-columns: repeat(auto-fill, minmax(230px, 1fr)); gap: var(--grid-gap, 24px); }');
        // Styles Woo's own pagination markup ([woo_pagination] →
        // woocommerce_pagination(): nav.woocommerce-pagination >
        // ul.page-numbers > li > a/span.page-numbers). Hidden while empty —
        // the template renders nothing when there is only one page.
        $pagwrap = self::cls(
            $s,
            'w4e-shop-pagination',
            '&:not(:has(.page-numbers)) { display: none; }'
            . ' & ul { display: flex; flex-wrap: wrap; justify-content: center; gap: var(--space-xs, 8px); list-style: none; margin: 0; padding: 0; }'
            . ' & li { margin: 0; }'
            . ' & a.page-numbers, & span.page-numbers { display: inline-flex; align-items: center; justify-content: center; min-width: 40px; min-height: 40px; padding: 0 var(--space-xs, 12px); border: var(--border, 1px solid #e6e7eb); border-radius: 999px; background: var(--white, #fff); color: var(--text-dark, #16181d); font-size: var(--text-s, 14px); font-weight: 600; text-decoration: none; transition: .15s; }'
            . ' & a.page-numbers:hover { border-color: var(--primary, #111827); }'
            . ' & span.page-numbers.current { background: var(--primary, #111827); border-color: var(--primary, #111827); color: var(--white, #fff); }'
        );
        $card    = self::cls($s, 'w4e-card', 'display: flex; flex-direction: column; gap: 4px;');
        $media   = self::cls($s, 'w4e-card__media', 'position: relative; display: block; background: var(--neutral-ultra-light, #f0f0f1); border-radius: var(--radius, 14px); padding: var(--space-m, 24px); margin-bottom: 8px;');
        $img     = self::cls($s, 'w4e-card__img', 'aspect-ratio: 1; width: 100%; object-fit: contain; mix-blend-mode: multiply;');
        $sale    = self::cls($s, 'w4e-card__sale', 'position: absolute; top: 12px; left: 12px; background: var(--accent, #d43a1f); color: var(--white, #fff); font-size: 11px; font-weight: 700; letter-spacing: .05em; text-transform: uppercase; padding: 4px var(--space-xs, 12px); border-radius: 999px;');
        $link    = self::cls($s, 'w4e-card__link', 'color: inherit; text-decoration: none; &:hover { text-decoration: underline; }');
        $name    = self::cls($s, 'w4e-card__name', 'font-size: var(--text-s, 15px); font-weight: 500; margin: 0;');
        $price   = self::cls($s, 'w4e-card__price', 'margin: 0; font-size: var(--text-l, 18px); font-weight: 800;');
        $btn     = self::cls($s, 'w4e-card__btn', 'align-self: flex-start; margin-top: 6px; font-size: var(--text-xs, 13px); padding: var(--space-xs, 8px) var(--space-s, 16px);');
        $button  = self::button_style($s);

        $cat_link = static function ($active, $extra_style, $label) use ($flink, $factive, $fcount) {
            $styles = $active ? [$flink, $factive] : [$flink];
            $class  = 'w4e-filter__link' . ($active ? ' w4e-filter__link--active' : '');
            return self::cond('c.is_active', $active ? 'isTruthy' : 'isFalsy', null, [
                self::el('li', ['class' => ''], [], [
                    self::el('a', ['class' => $class, 'href' => '{c.url}'], $styles, [
                        self::txt('{c.name}'),
                        self::text_el('span', '{c.count}', ['class' => 'w4e-filter__count'], [$fcount], 'Count'),
                    ], 'Category link'),
                ], 'Item'),
            ], $label);
        };

        return self::el('div', ['class' => 'w4e-shop-cols'], [$cols], [

                    self::el('aside', ['class' => 'w4e-filter', 'aria-label' => 'Product filters'], [$filter], [
                        self::el('div', ['class' => 'w4e-filter__group'], [$fgroup], [
                            self::text_el('h3', 'Categories', ['class' => 'w4e-filter__heading'], [$fheading], 'Heading'),
                            self::el('ul', ['class' => 'w4e-filter__list'], [$flist], [
                                self::loop('options.shop_categories', 'c', [
                                    $cat_link(true, $factive, 'c.is_active'),
                                    $cat_link(false, '', '!c.is_active'),
                                ]),
                            ], 'Category list'),
                        ], 'Categories'),
                        self::el('div', ['class' => 'w4e-filter__group'], [$fgroup], [
                            self::text_el('h3', 'Price', ['class' => 'w4e-filter__heading'], [$fheading], 'Heading'),
                            self::text_el('p', 'Highest price: {options.shop_max_price}', ['class' => 'w4e-filter__hint'], [$fhint], 'Hint'),
                            // Native Woo filtering: GET min_price/max_price to
                            // the current archive URL (no action attribute).
                            // data-w4e-price-max feeds the dual-range slider
                            // enhancement (assets/price-slider.js).
                            self::el('form', ['class' => 'w4e-filter__form', 'method' => 'get', 'data-w4e-price-max' => '{options.shop_max_price_raw}'], [$fform], [
                                self::el('div', ['class' => 'w4e-filter__prices'], [$prices], [
                                    self::el('input', ['class' => 'w4e-filter__price', 'type' => 'number', 'name' => 'min_price', 'value' => '{options.filter_min_price}', 'placeholder' => 'Min', 'min' => '0', 'inputmode' => 'numeric'], [$pricein], [], 'Min'),
                                    self::el('input', ['class' => 'w4e-filter__price', 'type' => 'number', 'name' => 'max_price', 'value' => '{options.filter_max_price}', 'placeholder' => 'Max', 'min' => '0', 'inputmode' => 'numeric'], [$pricein], [], 'Max'),
                                ], 'Price inputs'),
                                self::text_el('button', 'Apply', ['class' => 'w4e-filter__apply button', 'type' => 'submit'], [$fapply, $button], 'Apply'),
                            ], 'Price form'),
                            self::text_el('a', 'Reset filters', ['class' => 'w4e-filter__reset', 'href' => '{options.shop_url}'], [$freset], 'Reset'),
                        ], 'Price'),
                    ], 'Filter sidebar'),

                    self::el('div', ['class' => 'w4e-shop-main'], [$maincol], [
                    self::el('div', ['class' => 'w4e-shopgrid products'], [$grid], [
                        self::preset_loop(self::ensure_main_query_loop(), 'item', [
                            self::el('article', ['class' => 'w4e-card product'], [$card], [
                                self::el('a', ['class' => 'w4e-card__media', 'href' => '{item.permalink.relative}'], [$media], [
                                    self::cond('item.is_on_sale', 'isTruthy', null, [
                                        self::text_el('span', 'Sale', ['class' => 'w4e-card__sale'], [$sale], 'Sale badge'),
                                    ], 'item.is_on_sale'),
                                    self::el('img', ['class' => 'w4e-card__img', 'src' => '{item.image.url}', 'alt' => '{item.title}'], [$img], [], 'Img'),
                                ], 'Media'),
                                self::el('a', ['class' => 'w4e-card__link', 'href' => '{item.permalink.relative}'], [$link], [
                                    self::text_el('h3', '{item.title}', ['class' => 'w4e-card__name woocommerce-loop-product__title'], [$name], 'Name'),
                                ], 'Link'),
                                self::text_el('div', '{item.price}', ['class' => 'w4e-card__price price'], [$price], 'Price'),
                                self::text_el(
                                    'a',
                                    'Add to cart',
                                    [
                                        'class'           => 'w4e-card__btn button product_type_simple add_to_cart_button ajax_add_to_cart',
                                        'href'            => '?add-to-cart={item.id}',
                                        'data-product_id' => '{item.id}',
                                        'data-quantity'   => '1',
                                        'rel'             => 'nofollow',
                                    ],
                                    [$btn, $button],
                                    'Add to cart'
                                ),
                            ], 'W4e Card'),
                        ]),
                    ]),
                    // Woo's pagination for the main product query — the Etch
                    // loop rides on the same per-page (sync_per_page_on_
                    // secondary_queries), so the page count always matches.
                    self::el('div', ['class' => 'w4e-shop-pagination'], [$pagwrap], [
                        self::raw('[woo_pagination]', 'Pagination'),
                    ], 'Pagination'),
                    ], 'Main column'),

        ]);
    }

    /**
     * Header mini-cart: link with live count + hover/focus dropdown panel.
     * The panel never shows an empty box — an empty cart gets a message
     * instead of orphaned action buttons. Pure CSS reveal (hover +
     * :focus-within, so it is keyboard-accessible); no JS.
     */
    /**
     * Checkout — Store API / option A+ (issue #27): the entire checkout as
     * hand-written Etch markup over the {options.checkout.*} bridge. The
     * data-w4e-checkout marker opts the form into the Store API layer
     * (live shipping/totals recalc, natively rate-limited order placement);
     * without JS it posts classically to ?wc-ajax=checkout via the bridge
     * nonce. Checked states (country, shipping rate, payment method) are
     * rendered server-side via per-item conditions, so the form is correct
     * before any script runs.
     */
    private static function layout_checkout() {
        $s = [];

        $section  = self::cls($s, 'w4e-checkout', '');
        $formcls  = self::cls($s, 'w4e-checkout-form', '');
        $title    = self::cls($s, 'w4e-page__title', 'font-size: var(--h1, 32px); letter-spacing: var(--heading-letter-spacing, -0.02em); margin: 24px 0 20px;');
        $layout   = self::cls($s, 'w4e-checkout-layout', 'display: grid; grid-template-columns: minmax(0, 1fr); gap: var(--grid-gap, 20px); align-items: start; padding-block: var(--space-xs, 8px) var(--space-l, 32px); @media (min-width: 992px) { grid-template-columns: minmax(0, 1fr) 380px; gap: var(--grid-gap, 32px); }');
        $main     = self::cls($s, 'w4e-checkout-main', 'display: flex; flex-direction: column; gap: var(--space-s, 14px); min-width: 0;');
        $heading  = self::cls($s, 'w4e-checkout__heading', 'margin: var(--space-xs, 12px) 0 0; font-size: var(--text-l, 18px); letter-spacing: var(--heading-letter-spacing, -0.01em);');
        $fieldrow = self::cls($s, 'w4e-fieldrow', 'display: grid; grid-template-columns: minmax(0, 1fr); gap: var(--space-xs, 12px); @media (min-width: 560px) { grid-template-columns: var(--grid-2, repeat(2, minmax(0, 1fr))); }');
        $field    = self::cls($s, 'w4e-field', 'width: 100%; padding: var(--space-xs, 11px) var(--space-xs, 12px); border: var(--border, 1px solid #e6e7eb); border-radius: var(--radius, 8px); font-size: var(--text-s, 15px); background: var(--white, #fff);');
        $choice   = self::cls($s, 'w4e-choice', 'display: flex; gap: var(--space-xs, 10px); align-items: center; padding: var(--space-xs, 12px) var(--space-s, 14px); border: var(--border, 1px solid #e6e7eb); border-radius: var(--radius, 10px); background: var(--white, #fff); cursor: pointer; font-size: var(--text-s, 15px); &:has(input:checked) { border-color: var(--primary, #111827); box-shadow: 0 0 0 1px var(--primary, #111827); } & input { accent-color: var(--primary, #111827); }');
        $choicegp = self::cls($s, 'w4e-choicegroup', 'display: flex; flex-direction: column; gap: var(--space-xs, 8px); &[aria-busy="true"] { opacity: .55; pointer-events: none; transition: opacity .15s; }');
        $chprice  = self::cls($s, 'w4e-choice__price', 'margin-inline-start: auto; font-weight: 700; white-space: nowrap;');
        $chicon   = self::cls($s, 'w4e-choice__icon', 'display: inline-flex; align-items: center; margin-inline-start: auto; & img { height: 24px; width: auto; max-width: 64px; }');
        $paydesc  = self::cls($s, 'w4e-choice__desc', 'font-size: var(--text-xs, 13px); color: var(--text-dark-muted, #6b7280); & p { margin: 0; }');
        $legal    = self::cls($s, 'w4e-legal', 'display: flex; gap: var(--space-xs, 10px); align-items: flex-start; font-size: var(--text-s, 14px); color: var(--text-dark-muted, #52525b); & a { text-decoration: underline; } & input { margin-top: 3px; accent-color: var(--primary, #111827); }');
        $aside    = self::cls($s, 'w4e-checkout-aside', 'background: var(--white, #fff); border: var(--border, 1px solid #e6e7eb); border-radius: var(--radius, 14px); padding: var(--space-m, 22px); display: flex; flex-direction: column; gap: var(--space-xs, 8px); &[aria-busy="true"] { opacity: .55; pointer-events: none; transition: opacity .15s; } @media (min-width: 992px) { position: sticky; top: 88px; }');
        $atitle   = self::cls($s, 'w4e-checkout-aside__title', 'margin: 0 0 var(--space-xs, 8px); font-size: var(--text-xl, 20px); letter-spacing: var(--heading-letter-spacing, -0.01em);');
        $sumrow   = self::cls($s, 'w4e-sumrow', 'display: flex; justify-content: space-between; gap: var(--space-xs, 12px); font-size: var(--text-s, 14px); color: var(--text-dark-muted, #6b7280); & span:last-child { white-space: nowrap; }');
        $line     = self::cls($s, 'w4e-checkout-aside__line', 'display: flex; justify-content: space-between; gap: var(--space-xs, 12px); padding: 6px 0; color: var(--text-dark-muted, #6b7280);');
        $cpnline  = self::cls($s, 'w4e-checkout-aside__line--coupon', 'color: var(--success, #16a34a); & a { margin-inline-start: 8px; font-size: var(--text-xs, 12px); color: var(--text-dark-muted, #6b7280); text-decoration: underline; }');
        $total    = self::cls($s, 'w4e-checkout-aside__line--total', 'color: var(--text-dark, #16181d); font-weight: 800; font-size: var(--text-l, 18px); border-top: var(--border, 1px solid #e6e7eb); margin-top: 6px; padding-top: var(--space-s, 14px);');
        $cpnrow   = self::cls($s, 'w4e-coupon-row', 'display: flex; gap: var(--space-xs, 8px); margin-block: var(--space-xs, 8px); & input { flex: 1; min-width: 0; } & .button { padding-inline: var(--space-s, 14px); }');
        $button   = self::button_style($s);
        $place    = self::cls($s, 'w4e-place-order', 'display: block; width: 100%; text-align: center; margin-top: var(--space-xs, 12px);');
        $empty    = self::cls($s, 'w4e-checkout-empty', 'display: flex; flex-direction: column; align-items: flex-start; gap: var(--space-s, 16px); padding-block: var(--space-l, 32px);');

        /* Checked-state pattern: one condition pair per option, so the
           server-rendered form is correct without JS. */
        $radio = static function ($flag_path, array $attributes, array $styles) {
            $checked = $attributes;
            $checked['checked'] = 'checked';
            return [
                self::cond($flag_path, 'isTruthy', null, [self::el('input', $checked, $styles)], 'Selected'),
                self::cond($flag_path, 'isFalsy', null, [self::el('input', $attributes, $styles)], 'Not selected'),
            ];
        };

        $field_el = static function ($type, $name, $placeholder, array $extra = []) use ($field) {
            return self::el('input', array_merge([
                'class'       => 'w4e-field',
                'type'        => $type,
                'name'        => $name,
                'placeholder' => $placeholder,
            ], $extra), [$field], [], $placeholder ?: $name);
        };

        $block = self::el('section', ['data-etch-element' => 'section', 'class' => 'w4e-checkout'], ['etch-section-style', $section], [
            self::el('div', ['data-etch-element' => 'container'], ['etch-container-style'], [
                self::text_el('h1', '{this.title}', ['class' => 'w4e-page__title'], [$title], 'Title'),
                self::notices_block($s),

                self::cond('options.cart_is_empty', 'isFalsy', null, [
                    self::el('form', [
                        'class'             => 'checkout w4e-checkout-form',
                        'data-w4e-checkout' => '1',
                        'action'            => '/?wc-ajax=checkout',
                        'method'            => 'post',
                    ], [$formcls], [
                        self::el('div', ['class' => 'w4e-checkout-layout'], [$layout], [

                            self::el('div', ['class' => 'w4e-checkout-main'], [$main], [
                                self::text_el('h2', 'Contact', ['class' => 'w4e-checkout__heading'], [$heading], 'Contact heading'),
                                $field_el('email', 'billing_email', 'E-mail address', ['required' => 'required', 'autocomplete' => 'email']),

                                self::text_el('h2', 'Billing address', ['class' => 'w4e-checkout__heading'], [$heading], 'Billing heading'),
                                self::el('div', ['class' => 'w4e-fieldrow'], [$fieldrow], [
                                    $field_el('text', 'billing_first_name', 'First name', ['required' => 'required', 'autocomplete' => 'given-name']),
                                    $field_el('text', 'billing_last_name', 'Last name', ['required' => 'required', 'autocomplete' => 'family-name']),
                                ], 'Name row'),
                                $field_el('text', 'billing_address_1', 'Street and number', ['required' => 'required', 'autocomplete' => 'address-line1']),
                                self::el('div', ['class' => 'w4e-fieldrow'], [$fieldrow], [
                                    $field_el('text', 'billing_postcode', 'Postcode', ['required' => 'required', 'autocomplete' => 'postal-code']),
                                    $field_el('text', 'billing_city', 'City', ['required' => 'required', 'autocomplete' => 'address-level2']),
                                ], 'City row'),
                                self::el('select', ['class' => 'w4e-field', 'name' => 'billing_country', 'autocomplete' => 'country'], [$field], [
                                    self::loop('options.checkout.countries', 'c', [
                                        self::cond('c.selected', 'isTruthy', null, [
                                            self::text_el('option', '{c.name}', ['value' => '{c.code}', 'selected' => 'selected'], [], 'Selected country'),
                                        ], 'Selected'),
                                        self::cond('c.selected', 'isFalsy', null, [
                                            self::text_el('option', '{c.name}', ['value' => '{c.code}'], [], 'Country'),
                                        ], 'Not selected'),
                                    ]),
                                ], 'Country'),
                                $field_el('tel', 'billing_phone', 'Phone (optional)', ['autocomplete' => 'tel']),

                                self::cond('options.checkout.needs_shipping', 'isTruthy', null, [
                                    self::text_el('h2', 'Shipping', ['class' => 'w4e-checkout__heading'], [$heading], 'Shipping heading'),
                                    self::el('div', ['class' => 'w4e-choicegroup', 'data-w4e-checkout-region' => 'shipping'], [$choicegp], [
                                        self::loop('options.checkout.shipping_rates', 'rate', [
                                            self::el('label', ['class' => 'w4e-choice'], [$choice], array_merge(
                                                $radio('rate.selected', ['type' => 'radio', 'name' => 'shipping_method[0]', 'value' => '{rate.id}'], []),
                                                [
                                                    self::txt('{rate.label}'),
                                                    self::text_el('span', '{rate.price}', ['class' => 'w4e-choice__price'], [$chprice], 'Price'),
                                                ]
                                            ), 'Rate'),
                                        ]),
                                    ], 'Shipping options'),
                                ], 'Needs shipping'),

                                self::text_el('h2', 'Payment', ['class' => 'w4e-checkout__heading'], [$heading], 'Payment heading'),
                                self::el('div', ['class' => 'w4e-choicegroup'], [$choicegp], [
                                    self::loop('options.checkout.payment_methods', 'pm', [
                                        self::el('label', ['class' => 'w4e-choice'], [$choice], array_merge(
                                            $radio('pm.selected', ['type' => 'radio', 'name' => 'payment_method', 'value' => '{pm.id}'], []),
                                            [
                                                self::txt('{pm.title}'),
                                                // Gateway icons/descriptions carry HTML (gateways
                                                // provide their logos via get_icon()).
                                                self::el('span', ['class' => 'w4e-choice__desc'], [$paydesc], [self::raw('{pm.description}', 'Description')], 'Description'),
                                                self::el('span', ['class' => 'w4e-choice__icon'], [$chicon], [self::raw('{pm.icon}', 'Icon')], 'Icon'),
                                            ]
                                        ), 'Payment method'),
                                    ]),
                                ], 'Payment methods'),

                                // Germanized legal checkboxes — label HTML contains links.
                                self::loop('options.checkout.checkboxes', 'cb', [
                                    self::el('label', ['class' => 'w4e-legal'], [$legal], [
                                        self::el('input', ['type' => 'checkbox', 'name' => '{cb.id}', 'data-w4e-checkbox' => '{cb.id}'], []),
                                        self::raw('{cb.label}', 'Checkbox label'),
                                    ], 'Legal checkbox'),
                                ]),
                            ], 'Checkout main'),

                            self::el('aside', ['class' => 'w4e-checkout-aside', 'data-w4e-checkout-region' => 'totals'], [$aside], [
                                self::text_el('h2', 'Order summary', ['class' => 'w4e-checkout-aside__title'], [$atitle], 'Summary title'),
                                self::loop('options.cart_items', 'item', [
                                    self::el('div', ['class' => 'w4e-sumrow'], [$sumrow], [
                                        self::txt('{item.name} × {item.quantity}'),
                                        self::text_el('span', '{item.subtotal}', ['class' => ''], [], 'Line total'),
                                    ], 'Item'),
                                ]),
                                self::el('div', ['class' => 'w4e-coupon-row'], [$cpnrow], [
                                    self::el('input', ['class' => 'w4e-field', 'type' => 'text', 'name' => 'coupon_code', 'placeholder' => 'Coupon code'], [$field]),
                                    self::text_el('button', 'Apply', ['class' => 'button', 'type' => 'submit', 'name' => 'apply_coupon', 'value' => 'Apply coupon'], [$button], 'Apply coupon'),
                                ], 'Coupon'),
                                self::el('div', ['class' => 'w4e-checkout-aside__line'], [$line], [
                                    self::text_el('span', 'Subtotal', ['class' => ''], []),
                                    self::text_el('span', '{options.cart_subtotal}', ['class' => ''], []),
                                ], 'Subtotal'),
                                self::loop('options.cart_coupons', 'coupon', [
                                    self::el('div', ['class' => 'w4e-checkout-aside__line w4e-checkout-aside__line--coupon'], [$line, $cpnline], [
                                        self::el('span', ['class' => ''], [], [
                                            self::txt('Coupon: {coupon.code}'),
                                            self::text_el('a', 'Remove', ['href' => '{coupon.remove_url}', 'class' => ''], [], 'Remove coupon'),
                                        ], 'Coupon name'),
                                        self::text_el('span', '−{coupon.amount}', ['class' => ''], []),
                                    ], 'Coupon line'),
                                ]),
                                self::cond('options.checkout.needs_shipping', 'isTruthy', null, [
                                    self::el('div', ['class' => 'w4e-checkout-aside__line'], [$line], [
                                        self::text_el('span', 'Shipping', ['class' => ''], []),
                                        self::text_el('span', '{options.cart_shipping_total}', ['class' => ''], []),
                                    ], 'Shipping line'),
                                ], 'Shipping total'),
                                self::el('div', ['class' => 'w4e-checkout-aside__line w4e-checkout-aside__line--total'], [$line, $total], [
                                    self::text_el('span', 'Total', ['class' => ''], []),
                                    self::text_el('span', '{options.cart_total}', ['class' => ''], []),
                                ], 'Total line'),
                                self::el('input', ['class' => '', 'type' => 'hidden', 'name' => 'woocommerce-process-checkout-nonce', 'value' => '{options.checkout.nonce}'], []),
                                self::text_el('button', 'Place order', ['class' => 'button w4e-place-order', 'type' => 'submit', 'name' => 'woocommerce_checkout_place_order'], [$button, $place], 'Place order'),
                            ], 'Order summary'),
                        ], 'Checkout layout'),
                    ], 'Checkout form'),
                ], 'Has items'),

                self::cond('options.cart_is_empty', 'isTruthy', null, [
                    self::el('div', ['class' => 'w4e-checkout-empty'], [$empty], [
                        self::text_el('p', 'Your cart is currently empty.', ['class' => ''], [], 'Empty message'),
                        self::text_el('a', 'Return to shop', ['class' => 'button', 'href' => '{options.shop_url}'], [$button], 'Return to shop'),
                    ], 'Empty checkout'),
                ], 'Empty cart'),
            ]),
        ], 'W4e Checkout');

        return ['block' => $block, 'styles' => $s];
    }

    private static function layout_mini_cart() {
        $s = [];

        $wrap  = self::cls($s, 'w4e-minicart', 'position: relative; display: inline-flex; &:hover .w4e-minicart__panel, &:focus-within .w4e-minicart__panel { opacity: 1; visibility: visible; transform: none; }');
        $link  = self::cls($s, 'w4e-minicart__link', 'display: inline-flex; align-items: center; gap: var(--space-xs, 8px); padding: var(--space-xs, 9px) var(--space-s, 16px); border: var(--border, 1px solid #e6e7eb); border-radius: 999px; color: inherit; font-weight: 600; font-size: var(--text-s, 14px); text-decoration: none;');
        $count = self::cls($s, 'w4e-minicart__count', 'display: inline-grid; place-items: center; min-width: 22px; height: 22px; padding: 0 6px; border-radius: 999px; background: var(--primary, #111827); color: var(--white, #fff); font-size: var(--text-xs, 12px); font-weight: 700;');
        $panel = self::cls($s, 'w4e-minicart__panel', 'position: absolute; right: 0; top: calc(100% + 10px); width: min(380px, calc(100vw - 24px)); background: var(--white, #fff); border: var(--border, 1px solid #e6e7eb); border-radius: var(--radius, 14px); box-shadow: 0 12px 30px rgba(0, 0, 0, .12); padding: var(--space-s, 16px); display: flex; flex-direction: column; gap: var(--space-xs, 12px); opacity: 0; visibility: hidden; transform: translateY(6px); transition: .15s; z-index: 40;');
        $empty = self::cls($s, 'w4e-minicart__empty', 'margin: 0; padding: var(--space-xs, 8px) 0; text-align: center; color: var(--text-dark-muted, #6b7280); font-size: var(--text-s, 14px);');
        $items = self::cls($s, 'w4e-minicart__items', 'display: flex; flex-direction: column; gap: var(--space-xs, 10px); max-height: 320px; overflow: auto;');
        $row   = self::cls($s, 'w4e-minirow', 'display: grid; grid-template-columns: 48px minmax(0, 1fr) auto; gap: var(--space-xs, 10px); align-items: center;');
        $rimg  = self::cls($s, 'w4e-minirow__img', 'width: 48px; height: 48px; object-fit: cover; border-radius: var(--radius, 8px); background: var(--neutral-ultra-light, #f3f4f6);');
        $rname = self::cls($s, 'w4e-minirow__name', 'font-size: var(--text-xs, 13px); font-weight: 600; margin: 0;');
        $rqty  = self::cls($s, 'w4e-minirow__qty', 'font-size: var(--text-xs, 12px); color: var(--text-dark-muted, #6b7280);');
        $rsub  = self::cls($s, 'w4e-minirow__sub', 'font-size: var(--text-xs, 13px); font-weight: 700; white-space: nowrap;');
        $total = self::cls($s, 'w4e-minicart__total', 'display: flex; justify-content: space-between; font-weight: 700; font-size: var(--text-s, 14px); border-top: var(--border, 1px solid #e6e7eb); padding-top: var(--space-xs, 10px);');
        $acts  = self::cls($s, 'w4e-minicart__actions', 'display: flex; flex-wrap: wrap; gap: var(--space-xs, 8px); & > * { flex: 1 1 auto; white-space: nowrap; text-align: center; }');
        $view  = self::cls($s, 'w4e-minicart__view', 'display: inline-block; text-align: center; padding: var(--space-xs, 11px) var(--space-xs, 12px); border: var(--border, 1px solid #e6e7eb); border-radius: var(--radius, 10px); color: inherit; font-weight: 600; font-size: var(--text-s, 14px); text-decoration: none;');
        $button = self::button_style($s);

        // data-w4e-cart-region: the Store API cart layer swaps this element
        // with the fresh server render after every cart write.
        $block = self::el('div', ['class' => 'w4e-minicart', 'data-w4e-cart-region' => 'mini-cart'], [$wrap], [
            self::el('a', ['class' => 'w4e-minicart__link', 'href' => '{options.cart_url}'], [$link], [
                self::txt('Cart'),
                self::text_el('span', '{options.cart_count}', ['class' => 'w4e-minicart__count mini-cart-count'], [$count], 'Count'),
            ], 'Cart link'),
            self::el('div', ['class' => 'w4e-minicart__panel'], [$panel], [
                self::cond('options.cart_is_empty', 'isTruthy', null, [
                    self::text_el('p', 'Your cart is empty.', ['class' => 'w4e-minicart__empty'], [$empty], 'Empty message'),
                ], 'options.cart_is_empty'),
                self::cond('options.cart_is_empty', 'isFalsy', null, [
                    self::el('div', ['class' => 'w4e-minicart__items'], [$items], [
                        self::loop('options.cart_items', 'mi', [
                            self::el('div', ['class' => 'w4e-minirow'], [$row], [
                                self::el('img', ['class' => 'w4e-minirow__img', 'src' => '{mi.image}', 'alt' => '{mi.name}'], [$rimg], [], 'Img'),
                                self::el('div', ['class' => ''], [], [
                                    self::text_el('p', '{mi.name}', ['class' => 'w4e-minirow__name'], [$rname], 'Name'),
                                    self::text_el('span', '{mi.quantity} × {mi.price}', ['class' => 'w4e-minirow__qty'], [$rqty], 'Qty'),
                                ], 'Info'),
                                self::text_el('span', '{mi.subtotal}', ['class' => 'w4e-minirow__sub'], [$rsub], 'Sub'),
                            ], 'Row'),
                        ]),
                    ], 'Items'),
                    self::el('div', ['class' => 'w4e-minicart__total'], [$total], [
                        self::text_el('span', 'Subtotal', ['class' => ''], [], 'Label'),
                        self::text_el('span', '{options.cart_subtotal}', ['class' => ''], [], 'Value'),
                    ], 'Total'),
                    self::el('div', ['class' => 'w4e-minicart__actions'], [$acts], [
                        self::text_el('a', 'View cart', ['class' => 'w4e-minicart__view', 'href' => '{options.cart_url}'], [$view], 'View cart'),
                        self::text_el('a', 'Checkout', ['class' => 'w4e-minicart__checkout button', 'href' => '{options.checkout_url}'], [$button], 'Checkout'),
                    ], 'Actions'),
                ], '!options.cart_is_empty'),
            ], 'Panel'),
        ], 'W4e Mini-cart');

        return ['block' => $block, 'styles' => $s];
    }

    /** My Account: navigation + endpoint views in one layout. */
    private static function layout_account() {
        $s = [];

        $section = self::cls($s, 'w4e-account', '');
        $layout  = self::cls($s, 'w4e-account-layout', 'display: grid; grid-template-columns: minmax(0, 1fr); gap: var(--grid-gap, 20px); align-items: start; padding-block: var(--space-m, 24px) var(--space-xl, 48px); @media (min-width: 768px) { grid-template-columns: 240px minmax(0, 1fr); gap: var(--grid-gap, 32px); }');
        $nav     = self::cls($s, 'w4e-account-nav', 'display: flex; flex-direction: row; flex-wrap: wrap; gap: 2px; background: var(--white, #fff); border: var(--border, 1px solid #e6e7eb); border-radius: var(--radius, 14px); padding: var(--space-xs, 10px); @media (min-width: 768px) { flex-direction: column; position: sticky; top: 88px; }');
        $navlink = self::cls($s, 'w4e-account-nav__link', 'padding: var(--space-xs, 10px) var(--space-xs, 12px); border-radius: var(--radius, 8px); color: var(--text-dark, #16181d); font-weight: 500; text-decoration: none;');
        $content = self::cls($s, 'w4e-account-content', 'background: var(--white, #fff); border: var(--border, 1px solid #e6e7eb); border-radius: var(--radius, 14px); padding: var(--space-m, 24px); display: flex; flex-direction: column; gap: var(--space-s, 14px);');
        $heading = self::cls($s, 'w4e-account__heading', 'margin: 0 0 6px; font-size: var(--text-xl, 22px); letter-spacing: var(--heading-letter-spacing, -0.01em);');
        $orders  = self::cls($s, 'w4e-orders', 'list-style: none; margin: 0; padding: 0; display: flex; flex-direction: column;');
        $row     = self::cls($s, 'w4e-orderrow', 'display: grid; grid-template-columns: 1fr auto; gap: calc(var(--space-xs, 8px) / 2) var(--space-s, 16px); align-items: center; padding: var(--space-xs, 12px) 0; border-bottom: var(--border, 1px solid #e6e7eb); @media (min-width: 768px) { grid-template-columns: auto 1fr auto auto; gap: var(--space-s, 16px); }');
        $number  = self::cls($s, 'w4e-orderrow__number', 'font-weight: 700; color: inherit;');
        $status  = self::cls($s, 'w4e-orderrow__status', 'font-size: var(--text-xs, 13px); color: var(--text-dark-muted, #6b7280);');
        $login   = self::cls($s, 'w4e-account-login', 'max-width: 420px; margin-inline: auto; background: var(--white, #fff); border: var(--border, 1px solid #e6e7eb); border-radius: var(--radius, 14px); padding: var(--space-m, 24px); display: flex; flex-direction: column; gap: var(--space-s, 14px); & input { width: 100%; padding: var(--space-xs, 10px) var(--space-xs, 12px); border: var(--border, 1px solid #e6e7eb); border-radius: var(--radius, 8px); } & label { display: block; margin-bottom: 4px; font-weight: 500; } & .form-row { margin: 0 0 12px; }');

        $ep = 'options.account_endpoint';

        $block = self::el('section', ['data-etch-element' => 'section', 'class' => 'w4e-account woocommerce-account'], ['etch-section-style', $section], [
            self::el('div', ['data-etch-element' => 'container'], ['etch-container-style'], [
                // Login/register errors, "address saved", password changes —
                // all account feedback arrives as Woo notices.
                self::notices_block($s),
                // Guests: no nav, no dashboard — WooCommerce's login/register
                // form instead (real Woo PHP, so lost-password etc. work).
                self::cond('options.is_logged_in', 'isFalsy', null, [
                    self::el('div', ['class' => 'w4e-account-login'], [$login], [
                        self::text_el('h2', 'Login', ['class' => 'w4e-account__heading'], [$heading], 'Heading'),
                        self::txt('[woo_login_form]'),
                    ], 'Login'),
                ], '!options.is_logged_in'),

                self::cond('options.is_logged_in', 'isTruthy', null, [
                    self::el('div', ['class' => 'w4e-account-layout'], [$layout], [

                        self::el('nav', ['class' => 'w4e-account-nav', 'aria-label' => 'Account navigation'], [$nav], [
                            self::loop('options.account_menu', 'm', [
                                self::text_el('a', '{m.label}', ['class' => 'w4e-account-nav__link', 'href' => '{m.url}'], [$navlink], 'Nav link'),
                            ]),
                        ], 'Account nav'),

                        self::el('div', ['class' => 'w4e-account-content woocommerce-MyAccount-content'], [$content], [
                            self::cond($ep, '===', "'dashboard'", [
                                self::text_el('h2', 'Dashboard', ['class' => 'w4e-account__heading'], [$heading], 'Heading'),
                                self::text_el('p', 'Hello {user.displayName}!', ['class' => ''], [], 'Greeting'),
                            ], "options.account_endpoint === 'dashboard'"),

                            self::cond($ep, '===', "'orders'", [
                                self::text_el('h2', 'Orders', ['class' => 'w4e-account__heading'], [$heading], 'Heading'),
                                self::el('ul', ['class' => 'w4e-orders'], [$orders], [
                                    self::loop('options.account_orders', 'o', [
                                        self::el('li', ['class' => 'w4e-orderrow'], [$row], [
                                            self::text_el('a', '#{o.number}', ['class' => 'w4e-orderrow__number', 'href' => '{o.view_url}'], [$number], 'Number'),
                                            self::text_el('span', '{o.date}', ['class' => ''], [], 'Date'),
                                            self::text_el('span', '{o.status_name}', ['class' => 'w4e-orderrow__status'], [$status], 'Status'),
                                            self::text_el('span', '{o.total}', ['class' => ''], [], 'Total'),
                                        ], 'Order row'),
                                    ]),
                                ], 'Orders list'),
                            ], "options.account_endpoint === 'orders'"),

                            // Everything else (forms, downloads, …) is real Woo PHP → shortcode.
                            self::cond(
                                self::c($ep, '!==', "'dashboard'"),
                                '&&',
                                self::c($ep, '!==', "'orders'"),
                                [self::txt('[woo_account_content]')],
                                "options.account_endpoint !== 'dashboard' && options.account_endpoint !== 'orders'"
                            ),
                        ], 'Account content'),

                    ]),
                ], 'options.is_logged_in'),
            ]),
        ], 'W4e Account');

        return ['block' => $block, 'styles' => self::with_base_styles($s)];
    }

    /** Thank-you / order received. */
    private static function layout_thank_you() {
        $s = [];

        $section  = self::cls($s, 'w4e-thankyou', '');
        $wrap     = self::cls($s, 'w4e-order', 'display: flex; flex-direction: column; gap: var(--space-s, 18px); padding-block: var(--space-m, 24px) var(--space-xl, 48px); max-width: 720px;');
        $notice   = self::cls($s, 'w4e-thankyou__notice', 'background: var(--success-ultra-light, #ecfdf5); border: 1px solid var(--success-light, #a7f3d0); color: var(--success-dark, #065f46); padding: var(--space-s, 14px) var(--space-s, 18px); border-radius: var(--radius, 12px); font-weight: 600; margin: 0;');
        $overview = self::cls($s, 'w4e-order-overview', 'list-style: none; margin: 0; padding: 0; display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: var(--grid-gap, 14px);');
        $cell     = self::cls($s, 'w4e-order-overview__item', 'background: var(--white, #fff); border: var(--border, 1px solid #e6e7eb); border-radius: var(--radius, 12px); padding: var(--space-xs, 12px) var(--space-s, 14px); display: flex; flex-direction: column; gap: 4px;');
        $celllab  = self::cls($s, 'w4e-order-overview__label', 'font-size: var(--text-xs, 12px); color: var(--text-dark-muted, #6b7280); text-transform: uppercase; letter-spacing: .04em;');
        $cellval  = self::cls($s, 'w4e-order-overview__value', 'font-weight: 700;');
        $heading  = self::cls($s, 'w4e-thankyou__heading', 'margin: 8px 0 0; font-size: var(--text-xl, 20px); letter-spacing: var(--heading-letter-spacing, -0.01em);');
        $items    = self::cls($s, 'w4e-orderitems', 'list-style: none; margin: 0; padding: 0; border: var(--border, 1px solid #e6e7eb); border-radius: var(--radius, 14px); background: var(--white, #fff); overflow: hidden;');
        $itemrow  = self::cls($s, 'w4e-orderitem', 'display: grid; grid-template-columns: 56px 1fr auto; gap: var(--space-s, 14px); align-items: center; padding: var(--space-xs, 12px) var(--space-s, 16px); border-bottom: var(--border, 1px solid #e6e7eb);');
        $itemimg  = self::cls($s, 'w4e-orderitem__img', 'width: 56px; height: 56px; object-fit: cover; border-radius: var(--radius, 8px); background: var(--neutral-ultra-light, #f3f4f6);');
        $itemname = self::cls($s, 'w4e-orderitem__name', 'font-weight: 600;');
        $itemtot  = self::cls($s, 'w4e-orderitem__total', 'font-weight: 700; white-space: nowrap;');

        $cellblock = static function ($label, $value, $name) use ($cell, $celllab, $cellval) {
            return self::el('li', ['class' => 'w4e-order-overview__item'], [$cell], [
                self::text_el('span', $label, ['class' => 'w4e-order-overview__label'], [$celllab], 'Label'),
                self::text_el('span', $value, ['class' => 'w4e-order-overview__value'], [$cellval], 'Value'),
            ], $name);
        };

        $block = self::el('section', ['data-etch-element' => 'section', 'class' => 'w4e-thankyou woocommerce-order-received'], ['etch-section-style', $section], [
            self::el('div', ['data-etch-element' => 'container'], ['etch-container-style'], [
                self::cond('options.order.number', 'isTruthy', null, [
                    self::el('div', ['class' => 'w4e-order'], [$wrap], [
                        self::text_el('p', 'Thank you. Your order has been received.', ['class' => 'w4e-thankyou__notice woocommerce-thankyou-order-received'], [$notice], 'Notice'),
                        self::el('ul', ['class' => 'w4e-order-overview order_details'], [$overview], [
                            $cellblock('Order', '#{options.order.number}', 'Order no'),
                            $cellblock('Date', '{options.order.date}', 'Date'),
                            $cellblock('Total', '{options.order.total}', 'Total'),
                            $cellblock('Payment', '{options.order.payment_method}', 'Payment'),
                        ], 'Overview'),
                        self::text_el('h2', 'Order details', ['class' => 'w4e-thankyou__heading'], [$heading], 'Heading'),
                        self::el('ul', ['class' => 'w4e-orderitems'], [$items], [
                            self::loop('options.order.items', 'it', [
                                self::el('li', ['class' => 'w4e-orderitem'], [$itemrow], [
                                    self::el('img', ['class' => 'w4e-orderitem__img', 'src' => '{it.image}', 'alt' => '{it.name}'], [$itemimg], [], 'Img'),
                                    self::text_el('span', '{it.name} × {it.quantity}', ['class' => 'w4e-orderitem__name'], [$itemname], 'Name'),
                                    self::text_el('span', '{it.total}', ['class' => 'w4e-orderitem__total'], [$itemtot], 'Total'),
                                ], 'Item'),
                            ]),
                        ], 'Items'),
                    ], 'Order'),
                ], 'options.order.number'),
            ]),
        ], 'W4e Thank You');

        return ['block' => $block, 'styles' => self::with_base_styles($s)];
    }

    /** Append Etch's two readonly base element styles (section/container), as the clipboard format does. */
    private static function with_base_styles(array $styles) {
        return $styles + [
            'etch-section-style'   => [
                'type'       => 'element',
                'selector'   => ':where([data-etch-element="section"])',
                'collection' => 'default',
                'css'        => "inline-size: 100%;\n  display: flex;\n  flex-direction: column;\n  align-items: center;",
                'readonly'   => true,
            ],
            'etch-container-style' => [
                'type'       => 'element',
                'selector'   => ':where([data-etch-element="container"])',
                'collection' => 'default',
                'css'        => "inline-size: 100%;\n  display: flex;\n  flex-direction: column;\n  max-inline-size: var(--content-width, 1366px);\n  align-self: center; margin-inline: auto;",
                'readonly'   => true,
            ],
        ];
    }
}
