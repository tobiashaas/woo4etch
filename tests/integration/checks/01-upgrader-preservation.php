<?php
/**
 * Layer 4 — upgrader preservation of includes/customizations.php (issue #12).
 *
 * The `upgrader_pre_install` / `upgrader_post_install` filters in woo4etch.php
 * must carry a *user-edited* customizations.php across a plugin update, and
 * must NOT resurrect an untouched skeleton (so shipped skeleton improvements
 * arrive). Both cases run against temp directories standing in for the
 * freshly installed plugin — the live install is only touched for the
 * "edited" case (its customizations.php is swapped for a valid no-op marker
 * file for a few milliseconds, then restored — byte-identical — in `finally`).
 *
 * Non-destructive; safe on a live staging site.
 *
 * @package Woo4Etch\Tests\Integration
 */

require __DIR__ . '/_lib.php';

echo "01 upgrader preservation\n";

$plugin_dir  = WP_PLUGIN_DIR . '/woo4etch';
$plugin_id   = 'woo4etch/woo4etch.php';
$custom_file = $plugin_dir . '/includes/customizations.php';
$backup_file = get_temp_dir() . 'woo4etch-customizations.preserved.php';

w4e_it(defined('WOO4ETCH_SKELETON_MD5'), 'WOO4ETCH_SKELETON_MD5 is defined');
w4e_it(file_exists($custom_file), 'live includes/customizations.php exists');
if (!defined('WOO4ETCH_SKELETON_MD5') || !file_exists($custom_file)) {
    w4e_it_done();
}

/** Fake "freshly installed" plugin dir whose skeleton has the given content. */
$make_destination = static function ($skeleton_content) {
    $dest = get_temp_dir() . 'w4e-it-upgrader-' . wp_generate_password(8, false);
    wp_mkdir_p($dest . '/includes');
    file_put_contents($dest . '/includes/customizations.php', $skeleton_content);
    return $dest;
};
$rm_destination = static function ($dest) {
    @unlink($dest . '/includes/customizations.php');
    @rmdir($dest . '/includes');
    @rmdir($dest);
};

@unlink($backup_file); // stale state from an aborted earlier run

/* ---- Case A: user edited the file → the edit must survive the update ---- */

$original  = file_get_contents($custom_file);
$user_edit = "<?php\n// w4e integration-test marker: user edit " . wp_generate_password(8, false) . "\n";

try {
    file_put_contents($custom_file, $user_edit);

    apply_filters('upgrader_pre_install', true, ['plugin' => $plugin_id]);
    w4e_it(file_exists($backup_file), 'edited file: pre_install creates the backup');

    $dest = $make_destination("<?php\n// freshly shipped skeleton\n");
    apply_filters('upgrader_post_install', true, ['plugin' => $plugin_id], ['destination' => $dest]);

    w4e_it_equals($user_edit, file_get_contents($dest . '/includes/customizations.php'), 'edited file: post_install restores the user edit into the new install');
    w4e_it(!file_exists($backup_file), 'edited file: backup is cleaned up');
    $rm_destination($dest);
} finally {
    file_put_contents($custom_file, $original);
}
w4e_it_equals($original, file_get_contents($custom_file), 'live customizations.php restored byte-identical');

/* ---- Case B: untouched skeleton → shipped improvements must NOT be clobbered ---- */

if (md5_file($custom_file) !== WOO4ETCH_SKELETON_MD5) {
    // This site's customizations.php carries real user snippets — the
    // untouched-skeleton case can't be exercised without overwriting them
    // meaningfully, so it is skipped here (it runs on pristine installs/CI).
    w4e_it_skip('untouched skeleton: live file is user-edited on this site');
} else {
    apply_filters('upgrader_pre_install', true, ['plugin' => $plugin_id]);
    w4e_it(!file_exists($backup_file), 'untouched skeleton: pre_install creates NO backup');

    $improved = "<?php\n// improved skeleton shipped by the update\n";
    $dest     = $make_destination($improved);
    apply_filters('upgrader_post_install', true, ['plugin' => $plugin_id], ['destination' => $dest]);

    w4e_it_equals($improved, file_get_contents($dest . '/includes/customizations.php'), 'untouched skeleton: the improved shipped skeleton survives');
    $rm_destination($dest);
}

@unlink($backup_file);
w4e_it_done();
