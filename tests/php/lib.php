<?php
/**
 * Tiny zero-dependency assertion harness for the Woo4Etch CLI tests.
 * No PHPUnit, no composer — just pass/fail counters and a non-zero exit on
 * any failure, which is all GitHub Actions needs to gate a PR.
 *
 * @package Woo4Etch\Tests
 */

$GLOBALS['__w4e_test'] = ['pass' => 0, 'fail' => 0, 'fails' => []];

/** Print a section header. */
function w4e_section($title) {
    echo "\n# {$title}\n";
}

/** Record a check: pass when $cond is truthy, fail otherwise. */
function w4e_check($cond, $msg) {
    if ($cond) {
        $GLOBALS['__w4e_test']['pass']++;
        echo "  ok   {$msg}\n";
    } else {
        $GLOBALS['__w4e_test']['fail']++;
        $GLOBALS['__w4e_test']['fails'][] = $msg;
        echo "  FAIL {$msg}\n";
    }
}

/** Assert two values are equal (strict for scalars), with a helpful message. */
function w4e_equals($expected, $actual, $msg) {
    $ok = ($expected === $actual);
    if (!$ok) {
        $msg .= sprintf(' (expected %s, got %s)', var_export($expected, true), var_export($actual, true));
    }
    w4e_check($ok, $msg);
}

/** Print the summary and return the process exit code (0 = all passed). */
function w4e_summary() {
    $t = $GLOBALS['__w4e_test'];
    echo "\n";
    echo str_repeat('-', 60) . "\n";
    if ($t['fail'] === 0) {
        echo "PASS — {$t['pass']} checks passed\n";
        return 0;
    }
    echo "FAIL — {$t['fail']} of " . ($t['pass'] + $t['fail']) . " checks failed:\n";
    foreach ($t['fails'] as $f) {
        echo "  - {$f}\n";
    }
    return 1;
}

/**
 * Recursively walk an Etch block tree, invoking $visitor($block) on every
 * node that has a blockName. Returns nothing; the visitor collects.
 *
 * @param array    $block   parse_blocks()-shaped node.
 * @param callable $visitor fn(array $block): void
 */
function w4e_walk_blocks($block, callable $visitor) {
    if (!is_array($block)) {
        return;
    }
    if (isset($block['blockName'])) {
        $visitor($block);
    }
    if (!empty($block['innerBlocks']) && is_array($block['innerBlocks'])) {
        foreach ($block['innerBlocks'] as $child) {
            w4e_walk_blocks($child, $visitor);
        }
    }
}
