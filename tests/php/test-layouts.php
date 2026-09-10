<?php
/**
 * Layer 3 — layout DSL invariants (issue #10). These run against the live
 * block trees built by Woo4Etch_Layouts (the source of truth) and against the
 * committed templates/etch-copy/*.json artifacts (the shipped copies).
 *
 * The headline invariant — every etch/loop binds to a data-path target or a
 * loopId, never a bare query key like "mainQuery" — is exactly the bug that
 * silently rendered an empty shop archive for ~2 betas.
 *
 * @package Woo4Etch\Tests
 */

/** Loop targets that render nothing on their own (query loops need a loopId preset). */
const W4E_FORBIDDEN_LOOP_TARGETS = ['mainQuery', 'wpQuery', 'main-query', 'wp-query', 'wpTerms', 'wp-terms', 'wpUsers', 'wp-users'];

/** Assert one etch/loop block is bound to something that actually loops. */
function w4e_assert_loop_valid($block, $where) {
    $attrs   = $block['attrs'] ?? [];
    $loop_id = $attrs['loopId'] ?? '';
    $target  = $attrs['target'] ?? '';

    if ($loop_id !== '') {
        w4e_check(true, "{$where}: loop bound via loopId '{$loop_id}'");
        return;
    }

    w4e_check($target !== '', "{$where}: loop has a target or loopId");
    if ($target === '') {
        return;
    }
    w4e_check(
        !in_array($target, W4E_FORBIDDEN_LOOP_TARGETS, true),
        "{$where}: loop target '{$target}' is a data path, not a bare query key"
    );
    w4e_check(
        strpos($target, '.') !== false,
        "{$where}: loop target '{$target}' is a data path (contains a key path)"
    );
}

/** Collect every block of a given blockName from a tree. */
function w4e_collect_blocks($root, $block_name) {
    $found = [];
    w4e_walk_blocks($root, static function ($b) use (&$found, $block_name) {
        if (($b['blockName'] ?? '') === $block_name) {
            $found[] = $b;
        }
    });
    return $found;
}

/**
 * Assert every literal class in every element's class attribute is backed by a
 * referenced style record (selector ".class") — issue #21: Etch's save
 * reconciliation strips classes without a matching referenced record on the
 * first builder save, silently breaking the Woo contract (form.cart,
 * .single_add_to_cart_button, …). Dynamic classes ({this.*}) are exempt.
 *
 * @param array  $root   Block tree.
 * @param array  $styles Style map (id => definition).
 * @param string $where  Label for messages.
 */
function w4e_assert_classes_bound($root, $styles, $where) {
    $unbound = [];
    w4e_walk_blocks($root, static function ($b) use (&$unbound, $styles) {
        $class_attr = $b['attrs']['attributes']['class'] ?? '';
        if (!is_string($class_attr) || trim($class_attr) === '') {
            return;
        }
        $refs          = $b['attrs']['styles'] ?? [];
        $ref_selectors = [];
        foreach ((array) $refs as $ref) {
            if (isset($styles[$ref]['selector'])) {
                $ref_selectors[(string) $styles[$ref]['selector']] = true;
            }
        }
        foreach (preg_split('/\s+/', trim($class_attr)) as $class) {
            if ($class === '' || strpos($class, '{') !== false) {
                continue;
            }
            if (!isset($ref_selectors['.' . $class])) {
                $unbound[] = $class;
            }
        }
    });
    w4e_check(
        $unbound === [],
        "{$where}: every literal class has a referenced style record"
        . ($unbound ? ' (unbound: ' . implode(', ', array_unique($unbound)) . ')' : '')
    );
}

/** True when any etch/loop in the tree targets the given data path. */
function w4e_any_loop_target($root, $target) {
    foreach (w4e_collect_blocks($root, 'etch/loop') as $loop) {
        if ((string) ($loop['attrs']['target'] ?? '') === $target) {
            return true;
        }
    }
    return false;
}

/** True when any etch/element in the tree has an attribute matching a predicate. */
function w4e_any_element($root, callable $pred) {
    $hit = false;
    w4e_walk_blocks($root, static function ($b) use (&$hit, $pred) {
        if ($hit || ($b['blockName'] ?? '') !== 'etch/element') {
            return;
        }
        $attrs = $b['attrs']['attributes'] ?? [];
        if (is_array($attrs) && $pred($attrs, $b)) {
            $hit = true;
        }
    });
    return $hit;
}

// Stand-in for WooCommerce core's own thank-you callback, so the
// skip-defaults assertion below has something real to suppress.
if (!function_exists('woocommerce_order_details_table')) {
    function woocommerce_order_details_table($order_id = 0) {
        echo '<table class="CORE-ORDER-TABLE" data-order="' . (int) $order_id . '"></table>';
    }
}

function w4e_test_layouts() {
    w4e_check(class_exists('Woo4Etch_Layouts'), 'Woo4Etch_Layouts class loaded');
    $catalog = Woo4Etch_Layouts::catalog();
    $slugs   = array_keys($catalog);

    /* ---- Every layout states where it stops ---- */
    // The layouts each drop something WooCommerce's own template renders, and
    // the omission is silent. The `limits` note is what the admin table and
    // the templates/*.md callouts render, so an entry without one ships a
    // trap instead of a trade-off.
    w4e_section('Catalog: every layout carries a "limits" note');
    foreach ($catalog as $slug => $meta) {
        w4e_check(
            isset($meta['limits']) && is_string($meta['limits']) && strlen(trim($meta['limits'])) >= 40,
            "{$slug}: catalog entry has a non-trivial 'limits' note"
        );
    }

    /* ---- Generic invariants for every live layout ---- */
    foreach ($slugs as $slug) {
        w4e_section("Layout '{$slug}' (live block tree)");
        $layout = Woo4Etch_Layouts::get($slug);

        w4e_check(is_array($layout) && isset($layout['block']['blockName']), "{$slug}: get() returns a block with a blockName");
        if (!is_array($layout) || !isset($layout['block'])) {
            continue;
        }
        $root = $layout['block'];

        // Well-formedness: every node carries the parse_blocks() shape.
        $malformed = 0;
        $bad_attributes = 0;
        w4e_walk_blocks($root, static function ($b) use (&$malformed, &$bad_attributes) {
            if (!isset($b['blockName']) || !array_key_exists('attrs', $b) || !array_key_exists('innerBlocks', $b)) {
                $malformed++;
                return;
            }
            // etch/element attributes must be an associative map (an empty list
            // would JSON-encode as [] and break Etch's element parser).
            if (($b['blockName'] ?? '') === 'etch/element') {
                $a = $b['attrs']['attributes'] ?? null;
                if (!is_array($a) || $a === [] || array_is_list($a)) {
                    $bad_attributes++;
                }
            }
        });
        w4e_equals(0, $malformed, "{$slug}: every block has blockName/attrs/innerBlocks");
        w4e_equals(0, $bad_attributes, "{$slug}: every etch/element has a non-empty attributes map");

        // serialize_block() emits exactly one child per null slot in
        // innerContent — a missing slot silently DROPS the trailing child on
        // install (real bug: the cart aside lost its checkout button when the
        // coupon loop was added without widening innerContent).
        $slot_mismatches = [];
        w4e_walk_blocks($root, static function ($b) use (&$slot_mismatches) {
            $children = is_array($b['innerBlocks'] ?? null) ? count($b['innerBlocks']) : 0;
            $content  = $b['innerContent'] ?? null;
            if ($children > 0 && is_array($content)) {
                $nulls = count(array_filter($content, static function ($c) {
                    return $c === null;
                }));
                if ($nulls !== $children) {
                    $slot_mismatches[] = ($b['blockName'] ?? '?') . " ({$nulls} slots for {$children} children)";
                }
            }
        });
        w4e_equals([], $slot_mismatches, "{$slug}: innerContent null slots match child count" . ($slot_mismatches ? ' (' . implode('; ', $slot_mismatches) . ')' : ''));

        // The headline loop invariant.
        $loops = w4e_collect_blocks($root, 'etch/loop');
        foreach ($loops as $i => $loop) {
            w4e_assert_loop_valid($loop, "{$slug} loop #" . ($i + 1));
        }

        // Issue #21: classes survive builder saves only when record-backed.
        w4e_assert_classes_bound($root, $layout['styles'] ?? [], $slug);

        // The Etch builder re-derives every condition from conditionString on
        // save — a label there destroys the condition after one round-trip
        // (real bug: pushed checkout page went blank). Assert the string is a
        // parseable expression consistent with the condition object.
        $bad_conditions = [];
        w4e_walk_blocks($root, static function ($b) use (&$bad_conditions) {
            if (($b['blockName'] ?? '') !== 'etch/condition') {
                return;
            }
            $cond = $b['attrs']['condition'] ?? null;
            $str  = (string) ($b['attrs']['conditionString'] ?? '');
            if (!is_array($cond) || $str === '') {
                $bad_conditions[] = 'missing condition/conditionString';
                return;
            }
            $left = $cond['leftHand'];
            // Nested conditions: just require an operator in the string.
            if (is_array($left)) {
                if (!preg_match('/(&&|\|\||===|!==|==|!=|>=|<=|>|<)/', $str)) {
                    $bad_conditions[] = "nested condition without operator in '{$str}'";
                }
                return;
            }
            // The left-hand data path must appear verbatim in the string, and
            // the string must not be a bare label (spaces without operators).
            if (strpos($str, (string) $left) === false) {
                $bad_conditions[] = "conditionString '{$str}' does not contain path '{$left}'";
            } elseif (strpos((string) $left, '.') === false) {
                $bad_conditions[] = "leftHand '{$left}' is not a data path";
            }
        });
        w4e_equals([], $bad_conditions, "{$slug}: conditionStrings are builder-safe expressions" . ($bad_conditions ? ' (' . implode('; ', array_slice($bad_conditions, 0, 3)) . ')' : ''));

        // Issue #22: shipped CSS uses ACSS tokens WITH plain fallbacks — a
        // bare var(--token) would render as nothing on non-ACSS sites.
        $bare = [];
        foreach (($layout['styles'] ?? []) as $id => $record) {
            $css = (string) ($record['css'] ?? '');
            if (preg_match_all('/var\(--[a-zA-Z0-9_-]+\)/', $css, $m)) {
                foreach ($m[0] as $hit) {
                    $bare[] = ($record['selector'] ?? $id) . ': ' . $hit;
                }
            }
        }
        w4e_equals([], $bare, "{$slug}: no bare ACSS var() without fallback" . ($bare ? ' (' . implode('; ', array_slice($bare, 0, 5)) . ')' : ''));
    }

    /* ---- Single-product Woo contract (server logic keys off these) ---- */
    w4e_section("Layout 'product-single' WooCommerce contract");
    $ps = Woo4Etch_Layouts::get('product-single');
    if (is_array($ps) && isset($ps['block'])) {
        $root = $ps['block'];

        w4e_check(
            w4e_any_element($root, static function ($a, $b) {
                return ($b['attrs']['tag'] ?? '') === 'form'
                    && isset($a['class']) && preg_match('/\bcart\b/', $a['class']);
            }),
            'has a <form class="...cart..."> (Woo add-to-cart form)'
        );
        w4e_check(
            w4e_any_element($root, static function ($a) {
                return isset($a['name']) && $a['name'] === 'add-to-cart';
            }),
            'has an element with name="add-to-cart"'
        );
        w4e_check(
            w4e_any_element($root, static function ($a) {
                return isset($a['class']) && strpos($a['class'], 'single_add_to_cart_button') !== false;
            }),
            'has the .single_add_to_cart_button class'
        );
        w4e_check(
            w4e_any_element($root, static function ($a) {
                return isset($a['class']) && strpos($a['class'], 'woocommerce-product-gallery__image') !== false;
            }),
            'gallery uses .woocommerce-product-gallery__image'
        );
        w4e_check(
            w4e_any_element($root, static function ($a) {
                return array_key_exists('data-large_image', $a);
            }),
            'gallery image carries data-large_image (Woo zoom/lightbox)'
        );

        // Short description must be raw-html (etch/text would escape its HTML).
        $excerpt_raw = false;
        foreach (w4e_collect_blocks($root, 'etch/raw-html') as $raw) {
            if (strpos($raw['attrs']['content'] ?? '', '{this.excerpt}') !== false) {
                $excerpt_raw = true;
            }
        }
        w4e_check($excerpt_raw, 'short description rendered via etch/raw-html ({this.excerpt})');
    }

    /* ---- Outdated-install detection points at markers that exist ---- */
    // The Layouts tab warns when the blocks already on a page predate a fix
    // (updating the plugin does not rewrite installed blocks). A marker that
    // no longer appears in the shipped layout would flag EVERY install,
    // including fresh ones, as outdated — worse than not warning at all.
    w4e_section('Outdated-layout markers exist in the layouts they describe');
    if (class_exists('Woo4Etch_Health')) {
        foreach (Woo4Etch_Health::layout_revisions() as $slug => $revision) {
            $layout = Woo4Etch_Layouts::get($slug);
            $marker = (string) ($revision['marker'] ?? '');
            w4e_check($marker !== '', "{$slug}: revision entry names a marker");
            w4e_check(
                trim((string) ($revision['note'] ?? '')) !== '',
                "{$slug}: revision entry explains what an old install is missing"
            );
            w4e_check(
                is_array($layout) && $marker !== ''
                    && strpos((string) json_encode($layout['block']), $marker) !== false,
                "{$slug}: marker '{$marker}' is present in the layout as shipped"
            );
        }
    }

    /* ---- Checkout: the address fields Woo validates against ---- */
    // WooCommerce's locale overrides for AU, US, CA, ES, IN, JP … rename
    // `state` without dropping `required`, so a form that omits the field
    // fails validation on both the classic and the Store API path — silently,
    // and only for customers in those countries.
    w4e_section("Layout 'checkout' carries the locale-dependent address fields");
    $co = Woo4Etch_Layouts::get('checkout');
    if (is_array($co) && isset($co['block'])) {
        $root = $co['block'];
        foreach (['billing_state', 'billing_address_2'] as $name) {
            w4e_check(
                w4e_any_element($root, static function ($a) use ($name) {
                    return isset($a['name']) && $a['name'] === $name;
                }),
                "has a {$name} field"
            );
        }
        // Both shapes must exist: a select where the country has a state list
        // (AU, US …) and a free-text input where it does not.
        w4e_check(
            w4e_any_element($root, static function ($a, $b) {
                return ($b['attrs']['tag'] ?? '') === 'select'
                    && isset($a['name']) && $a['name'] === 'billing_state';
            }),
            'renders billing_state as a <select> for countries with a state list'
        );
        w4e_check(
            w4e_any_element($root, static function ($a, $b) {
                return ($b['attrs']['tag'] ?? '') === 'input'
                    && isset($a['name']) && $a['name'] === 'billing_state';
            }),
            'renders billing_state as a free-text <input> for countries without one'
        );
        // The state list depends on the chosen country, so the field has to
        // re-render server-side after update-customer — that needs a region.
        w4e_check(
            w4e_any_element($root, static function ($a) {
                return isset($a['data-w4e-checkout-region']) && $a['data-w4e-checkout-region'] === 'billing-state';
            }),
            'the state field sits in its own checkout region (re-renders on country change)'
        );
        w4e_check(
            !empty(w4e_collect_blocks($root, 'etch/loop')) && w4e_any_loop_target($root, 'options.checkout.states'),
            'the state select loops {options.checkout.states}'
        );
    }

    /* ---- Cart: the summary discloses shipping ---- */
    // Subtotal → total with nothing between them tells the customer a flat
    // rate or a free-shipping threshold does not exist.
    w4e_section("Layout 'cart' summary discloses shipping");
    $ca = Woo4Etch_Layouts::get('cart');
    if (is_array($ca) && isset($ca['block'])) {
        $texts = [];
        w4e_walk_blocks($ca['block'], static function ($b) use (&$texts) {
            if (($b['blockName'] ?? '') === 'etch/text') {
                $texts[] = (string) ($b['attrs']['content'] ?? '');
            }
        });
        w4e_check(in_array('{options.cart_shipping_total}', $texts, true), 'renders {options.cart_shipping_total}');
        w4e_check(in_array('{options.cart_shipping_notice}', $texts, true), 'renders {options.cart_shipping_notice} for the not-yet-known case');

        $conditions = [];
        w4e_walk_blocks($ca['block'], static function ($b) use (&$conditions) {
            if (($b['blockName'] ?? '') === 'etch/condition') {
                $conditions[] = (string) ($b['attrs']['conditionString'] ?? '');
            }
        });
        w4e_check(
            in_array('options.cart_show_shipping', $conditions, true),
            'the shipping row is gated on cart_show_shipping (Woo hides costs until an address is known)'
        );
    }

    /* ---- Thank-you: the payment-instruction hooks actually fire ---- */
    // The whole failure mode here is silence: a marker whose markup the
    // placeholder renderer's regex does not match renders an empty div, and
    // an offline gateway's bank details are simply gone with no error. So
    // assert against the RENDERED markup and the real renderer, not the
    // block tree.
    w4e_section("Layout 'thank-you' fires the payment-instruction hooks");
    $ty = Woo4Etch_Layouts::get('thank-you');
    if (is_array($ty) && isset($ty['block'])) {
        $markers = [];
        w4e_walk_blocks($ty['block'], static function ($b) use (&$markers) {
            $a = $b['attrs']['attributes'] ?? [];
            if (is_array($a) && isset($a['data-w4e-hook'])) {
                $markers[(string) $a['data-w4e-hook']] = $a;
            }
        });

        w4e_check(isset($markers['woocommerce_thankyou']), 'has a woocommerce_thankyou marker');
        w4e_check(isset($markers['woocommerce_before_thankyou']), 'has a woocommerce_before_thankyou marker');
        w4e_check(
            isset($markers['woocommerce_thankyou_{options.order.payment_method_id}']),
            'has a gateway-specific woocommerce_thankyou_{payment_method} marker'
        );

        // {this.id} is the checkout PAGE's id on this endpoint — passing it
        // hands the callbacks the wrong order, which is worse than passing
        // none at all.
        $args = [];
        foreach ($markers as $hook => $a) {
            $args[$hook] = (string) ($a['data-w4e-args'] ?? '');
        }
        w4e_equals(
            [
                'woocommerce_before_thankyou'                           => '{options.order.id}',
                'woocommerce_thankyou_{options.order.payment_method_id}' => '{options.order.id}',
                'woocommerce_thankyou'                                  => '{options.order.id}',
            ],
            $args,
            'every marker passes {options.order.id}, never {this.id}'
        );

        // Woo's own order table must be suppressed on the generic hook (the
        // layout already renders the order) but NOT on the gateway-specific
        // one, whose only callback is the gateway's own instructions.
        w4e_check(
            isset($markers['woocommerce_thankyou']['data-w4e-skip-defaults']),
            'the generic hook skips core defaults (no duplicated order table)'
        );
        w4e_check(
            !isset($markers['woocommerce_thankyou_{options.order.payment_method_id}']['data-w4e-skip-defaults']),
            'the gateway-specific hook keeps its callbacks'
        );

        // End-to-end through the real renderer, on markup shaped exactly like
        // the marker element with its dynamic keys already resolved: the
        // failure mode is an empty div and total silence, so a structural
        // assertion alone would not catch a regex that stopped matching.
        $html = '';
        foreach (['woocommerce_thankyou_{options.order.payment_method_id}', 'woocommerce_thankyou'] as $hook) {
            $attributes = $markers[$hook] ?? [];
            $html .= '<div';
            foreach ($attributes as $name => $value) {
                $html .= ' ' . $name . '="' . htmlspecialchars((string) $value, ENT_QUOTES) . '"';
            }
            $html .= '></div>';
        }
        $html = str_replace(
            ['{options.order.payment_method_id}', '{options.order.id}'],
            ['bacs', '1042'],
            $html
        );

        $seen = [];
        add_action('woocommerce_thankyou', static function ($order_id) use (&$seen) {
            $seen[] = $order_id;
            echo '<p class="bank-details">IBAN</p>';
        }, 10);
        add_action('woocommerce_thankyou', 'woocommerce_order_details_table', 10);
        add_action('woocommerce_thankyou_bacs', static function ($order_id) use (&$seen) {
            $seen[] = 'bacs:' . $order_id;
            echo '<h2>Our bank details</h2>';
        }, 10);

        $out = Woo4Etch::render_etch_placeholders($html);

        w4e_check(strpos($out, '<p class="bank-details">IBAN</p>') !== false, 'woocommerce_thankyou output lands inside the marker');
        w4e_check(strpos($out, '<h2>Our bank details</h2>') !== false, 'gateway-specific output lands inside its marker');
        w4e_equals(['bacs:1042', 1042], $seen, 'both callbacks receive the order id, gateway hook first');
        w4e_check(strpos($out, 'CORE-ORDER-TABLE') === false, 'core\'s order-details callback is suppressed');
        w4e_check(
            has_action('woocommerce_thankyou', 'woocommerce_order_details_table') !== false,
            'core defaults are rehooked after the marker rendered'
        );
    }

    /* ---- Shipped copy/paste artifacts: same loop invariant ---- */
    w4e_section('Committed etch-copy/*.json artifacts');
    $files = glob(WOO4ETCH_REPO_ROOT . '/templates/etch-copy/*.json');
    w4e_check(!empty($files), 'etch-copy JSON files are present');
    foreach ($files as $file) {
        $name = basename($file);
        $data = json_decode((string) file_get_contents($file), true);
        $root = is_array($data) && isset($data['gutenbergBlock']) ? $data['gutenbergBlock'] : null;
        w4e_check(is_array($root) && isset($root['blockName']), "{$name}: decodes to a block tree");
        if (!is_array($root)) {
            continue;
        }
        w4e_assert_classes_bound($root, isset($data['styles']) && is_array($data['styles']) ? $data['styles'] : [], $name);
        foreach (w4e_collect_blocks($root, 'etch/loop') as $i => $loop) {
            $where = "{$name} loop #" . ($i + 1);
            w4e_assert_loop_valid($loop, $where);
            // Portability (issue #13): the paste artifacts run on sites the
            // one-click installer never touched, so a loopId minted by the
            // installer (w4e_*) won't exist there. Artifacts must reference
            // Etch's seeded preset ids (e.g. etch_main_query) instead. Live
            // trees are exempt — the installer resolves presets at runtime.
            $loop_id = (string) ($loop['attrs']['loopId'] ?? '');
            if ($loop_id !== '') {
                w4e_check(
                    strpos($loop_id, 'w4e_') !== 0,
                    "{$where}: loopId '{$loop_id}' is portable (not an installer-minted w4e_* id)"
                );
            }
        }
    }
}
