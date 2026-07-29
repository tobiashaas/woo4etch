<?php
/**
 * Generates templates/etch-copy/*.json (Etch clipboard format) from the layout
 * definitions in the plugin, so the copy/paste files and the one-click installer
 * share a single source of truth.
 *
 * Usage: php tools/generate-etch-copy.php
 * Run from the repo root after changing layout definitions; commit the output.
 */

if (PHP_SAPI !== 'cli') {
    exit(1);
}

// The WordPress shim (constants + stub functions) lets the layout builder run
// outside WordPress — product-grid's loop preset uses get_option/update_option,
// which would otherwise be undefined here and in CI.
require __DIR__ . '/../tests/php/bootstrap.php';

/*
 * The paste artifacts are static: any loopId they carry must exist on the
 * TARGET site, not just here at generation time (issue #13). In a clean
 * environment ensure_main_query_loop() would fall back to minting
 * `w4e_main_query` — an id that only exists after the one-click installer
 * ran, so a manual paste would bind the shop-archive loop to a missing
 * preset. Seed the option store with Etch's own seeded main-query preset
 * (id `etch_main_query`, present on Etch installs) so the resolver reuses
 * that id and the artifact stays portable. Shape mirrors Etch's default.
 */
update_option('etch_loops', [
    'etch_main_query' => [
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
    ],
]);

$out_dir = __DIR__ . '/../templates/etch-copy';
$written = [];

foreach (array_keys(Woo4Etch_Layouts::catalog()) as $slug) {
    if ($slug === 'cart') {
        continue; // cart.json is the hand-tuned original; the plugin bundles a copy.
    }
    $json = Woo4Etch_Layouts::clipboard_json($slug);
    if ($json === '') {
        fwrite(STDERR, "FAILED: {$slug}\n");
        exit(1);
    }
    // Sanity: must decode back and contain a root block.
    $decoded = json_decode($json, true);
    if (!is_array($decoded) || empty($decoded['gutenbergBlock']['blockName'])) {
        fwrite(STDERR, "INVALID JSON: {$slug}\n");
        exit(1);
    }
    file_put_contents($out_dir . '/' . $slug . '.json', $json . "\n");
    $written[] = $slug . '.json (' . strlen($json) . ' bytes, ' . substr_count($json, '"blockName"') . ' blocks)';
}

echo implode("\n", $written) . "\n";
