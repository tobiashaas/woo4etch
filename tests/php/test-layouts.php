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

function w4e_test_layouts() {
    w4e_check(class_exists('Woo4Etch_Layouts'), 'Woo4Etch_Layouts class loaded');
    $slugs = array_keys(Woo4Etch_Layouts::catalog());

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

        // The headline loop invariant.
        $loops = w4e_collect_blocks($root, 'etch/loop');
        foreach ($loops as $i => $loop) {
            w4e_assert_loop_valid($loop, "{$slug} loop #" . ($i + 1));
        }
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
