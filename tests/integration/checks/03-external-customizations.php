<?php
/**
 * Layer 4 — the external customizations file is loaded when present (issue
 * #12): wp-content/woo4etch-customizations.php is the update-safe alternative
 * to includes/customizations.php. The check writes a marker file, boots a
 * fresh WP process (WP_CLI::runcommand with launch) to see the marker take
 * effect, and removes the file again in `finally`.
 *
 * Skips (rather than clobbers) when the site already has a real external
 * customizations file.
 *
 * @package Woo4Etch\Tests\Integration
 */

require __DIR__ . '/_lib.php';

echo "03 external customizations file\n";

$file = WP_CONTENT_DIR . '/woo4etch-customizations.php';

if (file_exists($file)) {
    // A real external file exists on this site: loading is already proven if
    // its side effects are live; we only assert the loader picked it up via
    // a fresh process probing for the include.
    $loaded = WP_CLI::runcommand(
        'eval "echo in_array(WP_CONTENT_DIR . \'/woo4etch-customizations.php\', get_included_files(), true) ? \'yes\' : \'no\';"',
        ['launch' => true, 'return' => true, 'exit_error' => false]
    );
    w4e_it_equals('yes', trim((string) $loaded), 'existing external customizations file is loaded on boot');
    w4e_it_done();
}

$marker = 'W4E_IT_EXTERNAL_' . strtoupper(wp_generate_password(8, false, false));

try {
    file_put_contents($file, "<?php\ndefine('{$marker}', true);\n");

    $out = WP_CLI::runcommand(
        'eval "echo defined(\'' . $marker . '\') ? \'defined\' : \'missing\';"',
        ['launch' => true, 'return' => true, 'exit_error' => false]
    );
    w4e_it_equals('defined', trim((string) $out), 'marker constant from the external file is defined in a fresh WP boot');
} finally {
    @unlink($file);
}
w4e_it(!file_exists($file), 'marker file cleaned up');

$out = WP_CLI::runcommand(
    'eval "echo defined(\'' . $marker . '\') ? \'defined\' : \'missing\';"',
    ['launch' => true, 'return' => true, 'exit_error' => false]
);
w4e_it_equals('missing', trim((string) $out), 'after cleanup a fresh boot no longer defines the marker');

w4e_it_done();
