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

require __DIR__ . '/../plugin/woo4etch/includes/class-woo4etch-layouts.php';

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
