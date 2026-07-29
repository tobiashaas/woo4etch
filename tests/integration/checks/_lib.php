<?php
/**
 * Shared assertion helpers for the integration checks. Each check file is a
 * standalone `wp eval-file` script: it requires this file, runs its
 * assertions, then calls w4e_it_done() — which prints the summary and exits
 * non-zero on failure (the exit code propagates through wp-cli to the
 * runner).
 *
 * @package Woo4Etch\Tests\Integration
 */

$GLOBALS['w4e_it'] = ['pass' => 0, 'fail' => 0, 'skip' => 0];

/** Assert $ok; prints TAP-ish ok/not-ok lines. */
function w4e_it($ok, $label) {
    if ($ok) {
        $GLOBALS['w4e_it']['pass']++;
        echo "  ok   {$label}\n";
    } else {
        $GLOBALS['w4e_it']['fail']++;
        echo "  FAIL {$label}\n";
    }
    return (bool) $ok;
}

/** Assert equality with values in the failure output. */
function w4e_it_equals($expected, $actual, $label) {
    return w4e_it(
        $expected === $actual,
        $label . ($expected === $actual ? '' : sprintf(' (expected %s, got %s)', var_export($expected, true), var_export($actual, true)))
    );
}

/** Record an intentionally skipped assertion (environment not applicable). */
function w4e_it_skip($label) {
    $GLOBALS['w4e_it']['skip']++;
    echo "  skip {$label}\n";
}

/** Print the summary and exit non-zero when anything failed. */
function w4e_it_done() {
    $t = $GLOBALS['w4e_it'];
    printf("  -- %d passed, %d failed, %d skipped\n", $t['pass'], $t['fail'], $t['skip']);
    exit($t['fail'] > 0 ? 1 : 0);
}
