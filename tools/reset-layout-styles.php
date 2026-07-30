<?php
/**
 * Reset installed Woo4Etch layout style records to the shipped CSS.
 *
 * The layout installer's style merger deliberately never overwrites existing
 * records — so sites that installed layouts before a styling update (e.g. the
 * ACSS-token pass, issue #22) keep their old CSS forever. This helper
 * upgrades them in place: every etch_styles record whose selector matches a
 * shipped layout selector gets the current shipped CSS. Record IDs and block
 * references stay untouched, so nothing moves in the builder.
 *
 * WARNING: this RESETS those records — any CSS you customized on a matching
 * record (w4e-*, .button, …) in the builder is replaced with the shipped
 * version. Skip this and re-style manually if you've made your own edits.
 *
 * Usage: wp eval-file tools/reset-layout-styles.php
 */

if (!defined('ABSPATH')) {
    exit(1);
}

$shipped = [];
foreach (array_keys(Woo4Etch_Layouts::catalog()) as $slug) {
    $layout = Woo4Etch_Layouts::get($slug);
    foreach (($layout['styles'] ?? []) as $rec) {
        if (!empty($rec['selector']) && isset($rec['css'])) {
            $shipped[$rec['selector']] = $rec['css'];
        }
    }
}

$styles  = get_option('etch_styles', []);
$updated = 0;
foreach ($styles as $id => $rec) {
    $sel = $rec['selector'] ?? '';
    if (isset($shipped[$sel]) && ($rec['css'] ?? '') !== $shipped[$sel] && empty($rec['readonly'])) {
        $styles[$id]['css'] = $shipped[$sel];
        $updated++;
    }
}
update_option('etch_styles', $styles);
echo "updated {$updated} records to shipped CSS\n";
