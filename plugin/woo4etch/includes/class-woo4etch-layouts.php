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

    /** Option mapping layout slug => installed wp_block post ID. */
    const INSTALLED_OPTION = 'woo4etch_installed_layouts';

    /** Etch global styles option (same one Etch's StylesRoutes writes). */
    const ETCH_STYLES_OPTION = 'etch_styles';

    /** Pattern category shown in Etch's pattern library. */
    const PATTERN_CATEGORY = 'Woo4Etch';

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
            'product-grid' => [
                'name'        => 'Shop archive — product grid',
                'description' => 'Product cards over the main archive query: image, sale badge, title, price, AJAX add-to-cart button. Uses {item.*} product keys.',
                'area'        => 'Archive',
            ],
            'mini-cart' => [
                'name'        => 'Header mini-cart link',
                'description' => 'Cart link with live item count ({options.cart_count}); the count span carries the mini-cart-count class used by the fragment snippet.',
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
                'description' => 'Queued WooCommerce feedback ("Cart updated.", coupon/form/security errors) as styleable .w4e-notice markup via [woo_notices format="plain"]. Already included in the cart, single-product and account layouts; insert this standalone version near the top of any other page layout. Tip: select it in Etch and save it as a component to manage the notices region globally.',
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
            return [
                'name'        => $meta['name'],
                'description' => $meta['description'],
                'block'       => $data['gutenbergBlock'],
                'styles'      => isset($data['styles']) && is_array($data['styles']) ? $data['styles'] : [],
            ];
        }

        $method = 'layout_' . str_replace('-', '_', $slug);
        if (!method_exists(__CLASS__, $method)) {
            return null;
        }
        $built = self::$method();
        return [
            'name'        => $meta['name'],
            'description' => $meta['description'],
            'block'       => $built['block'],
            'styles'      => $built['styles'],
        ];
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
       Installer
       ============================================================ */

    /**
     * Install (or refresh) a layout as an Etch pattern.
     *
     * Mirrors Etch's PatternsRoutes::create_pattern(): wp_block post, serialized
     * blocks, unsynced (inserts as a detached copy the user can edit freely),
     * categorized for the pattern library. Styles are merged into the
     * `etch_styles` option; styles whose selector already exists are NOT
     * overwritten — block references are remapped to the existing style instead
     * (same strategy as Etch's paste flow).
     *
     * @param string $slug Catalog key.
     * @return int|WP_Error Pattern post ID.
     */
    public static function install($slug) {
        $layout = self::get($slug);
        if ($layout === null) {
            return new WP_Error('woo4etch_unknown_layout', __('Unknown layout.', 'woo4etch'));
        }

        $map    = self::merge_styles($layout['styles']);
        $blocks = [self::remap_style_ids($layout['block'], $map)];

        $installed = (array) get_option(self::INSTALLED_OPTION, []);
        $post_id   = isset($installed[$slug]) ? (int) $installed[$slug] : 0;
        $existing  = $post_id ? get_post($post_id) : null;

        $post_data = [
            'post_type'    => 'wp_block',
            'post_title'   => sanitize_text_field('Woo4Etch — ' . $layout['name']),
            'post_content' => wp_slash(serialize_blocks($blocks)),
            'post_excerpt' => sanitize_text_field($layout['description']),
            'post_status'  => 'publish',
        ];

        if ($existing && 'wp_block' === $existing->post_type) {
            $post_data['ID'] = $post_id;
            $result = wp_update_post($post_data, true);
        } else {
            $result = wp_insert_post($post_data, true);
        }
        if (is_wp_error($result)) {
            return $result;
        }
        $post_id = (int) $result;

        // Unsynced → Etch inserts a detached copy, not a synced instance.
        update_post_meta($post_id, 'wp_pattern_sync_status', 'unsynced');
        wp_set_object_terms($post_id, [self::PATTERN_CATEGORY], 'wp_pattern_category');

        $installed[$slug] = $post_id;
        update_option(self::INSTALLED_OPTION, $installed);

        return $post_id;
    }

    /**
     * Installed-state lookup for the admin UI.
     *
     * @param string $slug Catalog key.
     * @return int Pattern post ID, 0 when not installed.
     */
    public static function installed_post_id($slug) {
        $installed = (array) get_option(self::INSTALLED_OPTION, []);
        $post_id   = isset($installed[$slug]) ? (int) $installed[$slug] : 0;
        if (!$post_id) {
            return 0;
        }
        $post = get_post($post_id);
        return ($post && 'wp_block' === $post->post_type && 'publish' === $post->post_status) ? $post_id : 0;
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

            // A style for this selector already exists → reuse it, never overwrite.
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
     * @param array<string,mixed>|string $left     Data path, or nested condition array.
     * @param string                     $operator isTruthy|isFalsy|===|!==|==|!=|>|<|>=|<=|&&|\|\|.
     * @param mixed                      $right    Literal ('orders' must be passed quoted: "'orders'"), path, or nested condition.
     * @param array<int,array>           $children Child blocks.
     * @param string                     $label    Human-readable conditionString for the builder UI.
     * @return array<string,mixed>
     */
    private static function cond($left, $operator, $right, array $children, $label) {
        return self::block(
            'etch/condition',
            [
                'condition'       => ['leftHand' => $left, 'operator' => $operator, 'rightHand' => $right],
                'conditionString' => $label,
            ],
            $children
        );
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
        return self::cls($styles, 'button', 'display: inline-block; background: #111827; color: #fff; border: 0; padding: 13px 22px; border-radius: 10px; font-weight: 600; font-size: 15px; cursor: pointer; text-align: center;');
    }

    /** Shared sale badge style. */
    private static function badge_style(array &$styles) {
        return self::cls($styles, 'w4e-badge', 'display: inline-block; align-self: flex-start; background: #ff4d2d; color: #fff; font-size: 11px; font-weight: 700; padding: 2px 8px; border-radius: 999px;');
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
            self::cls($styles, 'w4e-notices', 'display: flex; flex-direction: column; gap: 10px; margin-block-end: 20px;'),
            self::cls($styles, 'w4e-notice', 'padding: 12px 16px; border-radius: 8px; border: 1px solid #e5e5e5; background: #fafafa; font-size: 14px; line-height: 1.5;'),
            self::cls($styles, 'w4e-notice--error', 'background: #fef2f2; border-color: #fecaca; color: #b91c1c;'),
            self::cls($styles, 'w4e-notice--success', 'background: #f0fdf4; border-color: #bbf7d0; color: #166534;'),
            self::cls($styles, 'w4e-notice--notice', 'background: #eff6ff; border-color: #bfdbfe; color: #1e40af;'),
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
        $layout   = self::cls($s, 'w4e-product-layout', 'display: grid; grid-template-columns: minmax(0, 1.1fr) minmax(0, 1fr); gap: 40px; align-items: start; padding-block: 24px 48px;');
        // Gallery: Woo's gallery classes/attributes so WooCommerce's own
        // zoom/lightbox/slider scripts initialise on it when enabled
        // (Woo4Etch → Settings). Without the scripts the nested CSS lays the
        // same markup out as featured image + thumbnail grid; the .flex-*
        // rules style the FlexSlider DOM (thumbnails, viewport) once active.
        $gallery = self::cls(
            $s,
            'w4e-gal',
            'position: relative;'
            . ' & .w4e-gal-wrap { display: grid; grid-template-columns: repeat(4, 1fr); gap: 10px; margin: 0; }'
            . ' & .flex-viewport .w4e-gal-wrap { display: block; }'
            . ' & .w4e-gal-item--featured { grid-column: 1 / -1; }'
            . ' & .w4e-gal-item a { display: block; }'
            . ' & .w4e-gal-item img:not(.zoomImg) { width: 100%; aspect-ratio: 1; object-fit: cover; border-radius: 14px; background: #f3f4f6; display: block; }'
            . ' & .flex-viewport { border-radius: 14px; }'
            . ' & .flex-control-thumbs { display: flex; gap: 10px; margin: 10px 0 0; padding: 0; list-style: none; }'
            . ' & .flex-control-thumbs li { flex: 1; cursor: pointer; }'
            . ' & .flex-control-thumbs img { width: 100%; aspect-ratio: 1; object-fit: cover; border-radius: 10px; opacity: .6; }'
            . ' & .flex-control-thumbs img.flex-active, & .flex-control-thumbs img:hover { opacity: 1; }'
            . ' & .woocommerce-product-gallery__trigger { position: absolute; top: 12px; right: 12px; z-index: 9; display: grid; place-items: center; width: 36px; height: 36px; background: #fff; border-radius: 999px; }'
        );
        $info     = self::cls($s, 'w4e-product-info', 'display: flex; flex-direction: column; gap: 14px;');
        $title    = self::cls($s, 'w4e-product__title', 'font-size: 32px; letter-spacing: -.02em; margin: 0;');
        $pricerow = self::cls($s, 'w4e-product__pricerow', 'display: flex; align-items: center; gap: 12px;');
        $price    = self::cls($s, 'w4e-product__price', 'font-size: 24px; font-weight: 800;');
        $excerpt  = self::cls($s, 'w4e-product__excerpt', 'color: #6b7280; margin: 0;');
        $stock    = self::cls($s, 'w4e-product__stock', 'font-size: 14px; color: #15803d; margin: 0;');
        $form     = self::cls($s, 'w4e-product__form', 'display: flex; gap: 10px; align-items: stretch; margin-top: 4px;');
        // The top price row syncs to the chosen variation (swatches.js), so the
        // duplicate price Woo renders inside the form is hidden here.
        $nativecart = self::cls($s, 'w4e-native-cart', '& .woocommerce-variation-price { display: none; }');
        $qty      = self::cls($s, 'w4e-product__qty', 'width: 84px; padding: 11px; border: 1px solid #e6e7eb; border-radius: 10px; font-size: 15px;');
        $meta     = self::cls($s, 'w4e-product__meta', 'color: #6b7280; font-size: 13px; border-top: 1px solid #e6e7eb; padding-top: 14px; margin-top: 6px;');
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
                                self::el('input', ['class' => 'w4e-product__qty', 'type' => 'number', 'name' => 'quantity', 'value' => '1', 'min' => '1', 'step' => '1'], [$qty], [], 'Qty'),
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

    /** Shop archive: product grid over the main query. */
    private static function layout_product_grid() {
        $s = [];

        $section = self::cls($s, 'w4e-shop', '');
        $grid    = self::cls($s, 'w4e-shopgrid', 'display: grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap: 24px; padding-block: 24px 48px;');
        $card    = self::cls($s, 'w4e-card', 'position: relative; background: #fff; border: 1px solid #e6e7eb; border-radius: 14px; overflow: hidden; display: flex; flex-direction: column;');
        $link    = self::cls($s, 'w4e-card__link', 'display: block; color: inherit;');
        $img     = self::cls($s, 'w4e-card__img', 'aspect-ratio: 1; width: 100%; object-fit: cover; background: #f3f4f6;');
        $cardbdg = self::cls($s, 'w4e-card__badge', 'position: absolute; top: 12px; left: 12px;');
        $name    = self::cls($s, 'w4e-card__name', 'font-size: 15px; font-weight: 600; margin: 12px 14px 4px;');
        $price   = self::cls($s, 'w4e-card__price', 'margin: 0 14px 12px; font-weight: 700;');
        $btn     = self::cls($s, 'w4e-card__btn', 'margin: auto 14px 14px; text-align: center;');
        $badge   = self::badge_style($s);
        $button  = self::button_style($s);

        $block = self::el('section', ['data-etch-element' => 'section', 'class' => 'w4e-shop'], ['etch-section-style', $section], [
            self::el('div', ['data-etch-element' => 'container'], ['etch-container-style'], [
                self::el('div', ['class' => 'w4e-shopgrid products'], [$grid], [
                    self::preset_loop(self::ensure_main_query_loop(), 'item', [
                        self::el('article', ['class' => 'w4e-card product'], [$card], [
                            self::el('div', ['class' => 'w4e-card__badge'], [$cardbdg], [
                                self::cond('item.is_on_sale', 'isTruthy', null, [
                                    self::text_el('span', '-{item.sale_percentage}%', ['class' => 'w4e-badge onsale'], [$badge], 'Sale badge'),
                                ], 'item.is_on_sale'),
                            ], 'Badge'),
                            self::el('a', ['class' => 'w4e-card__link', 'href' => '{item.permalink.relative}'], [$link], [
                                self::el('img', ['class' => 'w4e-card__img', 'src' => '{item.image.url}', 'alt' => '{item.title}'], [$img], [], 'Img'),
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
            ]),
        ], 'W4e Shop Grid');

        return ['block' => $block, 'styles' => self::with_base_styles($s)];
    }

    /** Header mini-cart link with live count span. */
    private static function layout_mini_cart() {
        $s = [];

        $wrap  = self::cls($s, 'w4e-minicart', 'display: inline-flex;');
        $link  = self::cls($s, 'w4e-minicart__link', 'display: inline-flex; align-items: center; gap: 8px; padding: 9px 16px; border: 1px solid #e6e7eb; border-radius: 999px; color: inherit; font-weight: 600; font-size: 14px;');
        $count = self::cls($s, 'w4e-minicart__count', 'display: inline-grid; place-items: center; min-width: 22px; height: 22px; padding: 0 6px; border-radius: 999px; background: #111827; color: #fff; font-size: 12px; font-weight: 700;');

        $block = self::el('div', ['class' => 'w4e-minicart'], [$wrap], [
            self::el('a', ['class' => 'w4e-minicart__link', 'href' => '{options.cart_url}'], [$link], [
                self::txt('Cart'),
                self::text_el('span', '{options.cart_count}', ['class' => 'w4e-minicart__count mini-cart-count'], [$count], 'Count'),
            ], 'Cart link'),
        ], 'W4e Mini-cart');

        return ['block' => $block, 'styles' => $s];
    }

    /** My Account: navigation + endpoint views in one layout. */
    private static function layout_account() {
        $s = [];

        $section = self::cls($s, 'w4e-account', '');
        $layout  = self::cls($s, 'w4e-account-layout', 'display: grid; grid-template-columns: 240px minmax(0, 1fr); gap: 32px; align-items: start; padding-block: 24px 48px;');
        $nav     = self::cls($s, 'w4e-account-nav', 'display: flex; flex-direction: column; gap: 2px; background: #fff; border: 1px solid #e6e7eb; border-radius: 14px; padding: 10px; position: sticky; top: 88px;');
        $navlink = self::cls($s, 'w4e-account-nav__link', 'padding: 10px 12px; border-radius: 8px; color: #16181d; font-weight: 500; text-decoration: none;');
        $content = self::cls($s, 'w4e-account-content', 'background: #fff; border: 1px solid #e6e7eb; border-radius: 14px; padding: 24px; display: flex; flex-direction: column; gap: 14px;');
        $heading = self::cls($s, 'w4e-account__heading', 'margin: 0 0 6px; font-size: 22px; letter-spacing: -.01em;');
        $orders  = self::cls($s, 'w4e-orders', 'list-style: none; margin: 0; padding: 0; display: flex; flex-direction: column;');
        $row     = self::cls($s, 'w4e-orderrow', 'display: grid; grid-template-columns: auto 1fr auto auto; gap: 16px; align-items: center; padding: 12px 0; border-bottom: 1px solid #e6e7eb;');
        $number  = self::cls($s, 'w4e-orderrow__number', 'font-weight: 700; color: inherit;');
        $status  = self::cls($s, 'w4e-orderrow__status', 'font-size: 13px; color: #6b7280;');

        $ep = 'options.account_endpoint';

        $block = self::el('section', ['data-etch-element' => 'section', 'class' => 'w4e-account woocommerce-account'], ['etch-section-style', $section], [
            self::el('div', ['data-etch-element' => 'container'], ['etch-container-style'], [
                // Login/register errors, "address saved", password changes —
                // all account feedback arrives as Woo notices.
                self::notices_block($s),
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
            ]),
        ], 'W4e Account');

        return ['block' => $block, 'styles' => self::with_base_styles($s)];
    }

    /** Thank-you / order received. */
    private static function layout_thank_you() {
        $s = [];

        $section  = self::cls($s, 'w4e-thankyou', '');
        $wrap     = self::cls($s, 'w4e-order', 'display: flex; flex-direction: column; gap: 18px; padding-block: 24px 48px; max-width: 720px;');
        $notice   = self::cls($s, 'w4e-thankyou__notice', 'background: #ecfdf5; border: 1px solid #a7f3d0; color: #065f46; padding: 14px 18px; border-radius: 12px; font-weight: 600; margin: 0;');
        $overview = self::cls($s, 'w4e-order-overview', 'list-style: none; margin: 0; padding: 0; display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 14px;');
        $cell     = self::cls($s, 'w4e-order-overview__item', 'background: #fff; border: 1px solid #e6e7eb; border-radius: 12px; padding: 12px 14px; display: flex; flex-direction: column; gap: 4px;');
        $celllab  = self::cls($s, 'w4e-order-overview__label', 'font-size: 12px; color: #6b7280; text-transform: uppercase; letter-spacing: .04em;');
        $cellval  = self::cls($s, 'w4e-order-overview__value', 'font-weight: 700;');
        $heading  = self::cls($s, 'w4e-thankyou__heading', 'margin: 8px 0 0; font-size: 20px; letter-spacing: -.01em;');
        $items    = self::cls($s, 'w4e-orderitems', 'list-style: none; margin: 0; padding: 0; border: 1px solid #e6e7eb; border-radius: 14px; background: #fff; overflow: hidden;');
        $itemrow  = self::cls($s, 'w4e-orderitem', 'display: grid; grid-template-columns: 56px 1fr auto; gap: 14px; align-items: center; padding: 12px 16px; border-bottom: 1px solid #e6e7eb;');
        $itemimg  = self::cls($s, 'w4e-orderitem__img', 'width: 56px; height: 56px; object-fit: cover; border-radius: 8px; background: #f3f4f6;');
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
