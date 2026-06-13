<?php
/**
 * Test runner for the fast, service-free Woo4Etch checks (issue #10, layers 2–3).
 *
 *   php tests/php/run.php
 *
 * Loads the WordPress shim, then the consistency and layout suites. Exits
 * non-zero if any check fails, so it gates CI directly.
 *
 * @package Woo4Etch\Tests
 */

require __DIR__ . '/bootstrap.php';
require __DIR__ . '/lib.php';
require __DIR__ . '/test-consistency.php';
require __DIR__ . '/test-layouts.php';

w4e_test_consistency();
w4e_test_layouts();

exit(w4e_summary());
