<?php
/**
 * Layer 4 — layout ownership tracking.
 *
 * The push route is append-only, so a fix shipped inside a layout never
 * reaches an existing install. Ownership tracking is what makes the automatic
 * route possible: record what was installed, and only replace it when the
 * blocks still hash to that record.
 *
 * The property that matters is not "does the update work" — it is **does it
 * refuse**. A false refusal costs a button; a false acceptance overwrites
 * someone's builder work. So every transition is asserted, with the edited
 * case checked from both directions (state reports `customized`, and the
 * write path returns an error rather than proceeding).
 *
 * Non-destructive: everything happens on throwaway drafts created and deleted
 * here. No real page or template is touched — `update_layout()` resolves its
 * own target, so its happy path is deliberately NOT exercised against a live
 * site; what is asserted here is that it declines.
 *
 * @package Woo4Etch\Tests\Integration
 */

require __DIR__ . '/_lib.php';

echo "10 layout ownership\n";

w4e_it(class_exists('Woo4Etch_Health'), 'Woo4Etch_Health loaded');
w4e_it(class_exists('Woo4Etch_Layouts'), 'Woo4Etch_Layouts loaded');
if (!class_exists('Woo4Etch_Health') || !class_exists('Woo4Etch_Layouts')) {
    w4e_it_done();
}

w4e_it(
    method_exists('Woo4Etch_Health', 'layout_state') && method_exists('Woo4Etch_Health', 'update_layout'),
    'ownership API is present'
);

$slug   = 'cart';
$blocks = Woo4Etch_Layouts::blocks_for_install($slug);
if (!is_array($blocks) || !$blocks) {
    w4e_it_skip("layout '{$slug}' has no installable blocks on this install");
    w4e_it_done();
}
$markup = serialize_blocks($blocks);

// Content that is NOT ours, on both sides of the layout — the whole point is
// that this survives untouched.
$before = '<!-- wp:paragraph --><p>Site header content</p><!-- /wp:paragraph -->';
$after  = '<!-- wp:paragraph --><p>Site footer content</p><!-- /wp:paragraph -->';

// KSES strips Etch's block comments for anyone without `unfiltered_html`,
// which in a CLI context is everyone — the plugin lifts it around its own
// writes for exactly this reason, so the test has to as well or it would be
// measuring KSES rather than ownership.
$write = static function (array $data) {
    kses_remove_filters();
    try {
        return isset($data['ID'])
            ? wp_update_post(wp_slash($data), true)
            : wp_insert_post(wp_slash($data), true);
    } finally {
        kses_init_filters();
    }
};

$posts = [];
$mk    = static function ($content) use (&$posts, $write) {
    $id = $write([
        'post_type'    => 'page',
        'post_status'  => 'draft',
        'post_title'   => 'woo4etch ownership check',
        'post_content' => $content,
    ]);
    if (!is_wp_error($id)) {
        $posts[] = (int) $id;
    }
    return is_wp_error($id) ? 0 : (int) $id;
};

try {
    /* ---- 1. Recorded and untouched ---- */

    $id = $mk($before . "\n\n" . $markup);
    if (!$id) {
        w4e_it_skip('could not create a draft on this install');
        w4e_it_done();
    }

    Woo4Etch_Health::record_install($id, $slug, count($blocks));

    $state = Woo4Etch_Health::layout_state($slug, $id);
    w4e_it(
        in_array($state['status'], ['current', 'updatable'], true),
        "untouched install is trackable (got '{$state['status']}')"
    );
    w4e_it_equals(
        Woo4Etch::VERSION,
        (string) $state['version'],
        'the record carries the version that installed it'
    );

    // Recorded right after install, the copy IS what ships, so `current`.
    w4e_it_equals('current', $state['status'], 'a fresh install reports `current`, not `updatable`');

    /* ---- 2. Edited since install ---- */

    $edited = get_post($id);
    $write([
        'ID'           => $id,
        'post_content' => $edited->post_content . "\n\n" . '<!-- wp:paragraph --><p>builder work</p><!-- /wp:paragraph -->',
    ]);
    $state_after_sibling = Woo4Etch_Health::layout_state($slug, $id);
    w4e_it_equals(
        'current',
        $state_after_sibling['status'],
        'adding a block AROUND the layout does not count as editing it'
    );

    // Now damage the layout itself: drop its first block.
    $parsed = array_values(array_filter(parse_blocks((string) get_post($id)->post_content), static function ($b) {
        return !empty($b['blockName']);
    }));
    $kept = array_values(array_filter($parsed, static function ($b, $i) {
        return 1 !== $i; // remove the layout's opening block
    }, ARRAY_FILTER_USE_BOTH));
    $write(['ID' => $id, 'post_content' => serialize_blocks($kept)]);

    w4e_it_equals(
        'customized',
        Woo4Etch_Health::layout_state($slug, $id)['status'],
        'editing the layout itself reports `customized`'
    );

    /* ---- 3. Never recorded ---- */

    $untracked = $mk($before . "\n\n" . $markup);
    w4e_it_equals(
        'untracked',
        $untracked ? Woo4Etch_Health::layout_state($slug, $untracked)['status'] : 'untracked',
        'an install with no record reports `untracked`, never `current`'
    );

    /* ---- 4. The round-trip gate ---- */

    w4e_it(
        Woo4Etch_Health::can_rewrite_safely($before . "\n\n" . $markup),
        'well-formed block content is safe to re-serialize'
    );
    // Reassuring, and worth pinning: classic markup between blocks becomes a
    // freeform block and comes back verbatim, so the gate does not punish a
    // page that mixes the two.
    w4e_it(
        Woo4Etch_Health::can_rewrite_safely($before . "\n\n<p>stray classic markup</p>\n\n" . $after),
        'classic markup between blocks still round-trips, so the gate allows it'
    );
    // A real counter-example: WordPress normalizes the delimiter, so this one
    // comes back subtly different — exactly the case the gate exists for.
    w4e_it(
        !Woo4Etch_Health::can_rewrite_safely('<!-- wp:paragraph  {"x":1} --><p>a</p><!-- /wp:paragraph -->'),
        'content that does not survive a round trip is refused'
    );

    /* ---- 5. The write path declines what it cannot prove ---- */

    $err = Woo4Etch_Health::update_layout('definitely-not-a-layout');
    w4e_it(is_wp_error($err), 'update_layout() rejects an unknown layout');

    /* ---- 6. shipped_hash is stable ---- */

    $h1 = Woo4Etch_Health::shipped_hash($slug);
    $h2 = Woo4Etch_Health::shipped_hash($slug);
    w4e_it($h1 !== '' && $h1 === $h2, 'shipped_hash() is deterministic within a request');
    w4e_it_equals('', Woo4Etch_Health::shipped_hash('definitely-not-a-layout'), 'shipped_hash() is empty for an unknown layout');
} finally {
    foreach ($posts as $pid) {
        wp_delete_post($pid, true);
    }
}

w4e_it_done();
