<?php
/**
 * Layer 4 — classic-checkout rate limiting (issue #24). Exercises the
 * sliding-window counter and the validation hook directly with a synthetic
 * client fingerprint. Non-destructive: settings restored, transients removed.
 *
 * @package Woo4Etch\Tests\Integration
 */

require __DIR__ . '/_lib.php';

echo "05 checkout rate limit\n";

w4e_it(class_exists('Woo4Etch'), 'Woo4Etch class loaded');
w4e_it(method_exists('Woo4Etch', 'checkout_rate_limit_exceeded'), 'rate-limit counter exists');
if (!method_exists('Woo4Etch', 'checkout_rate_limit_exceeded')) {
    w4e_it_done();
}

// Synthetic fingerprint so the check never collides with real traffic.
$_SERVER['REMOTE_ADDR']          = '203.0.113.77'; // TEST-NET-3
$_SERVER['HTTP_USER_AGENT']      = 'w4e-integration-check';
$_SERVER['HTTP_ACCEPT_LANGUAGE'] = 'x-w4e';
$fingerprint = md5('203.0.113.77|w4e-integration-check|x-w4e');
delete_transient('w4e_rl_' . $fingerprint);

/* ---- Window semantics: 3 allowed, 4th blocked, expiry frees it again ---- */

$window = 3; // short window so the expiry assertion stays fast
$r      = [];
for ($i = 1; $i <= 4; $i++) {
    $r[$i] = Woo4Etch::checkout_rate_limit_exceeded(3, $window);
}
w4e_it(!$r[1] && !$r[2] && !$r[3], 'first three attempts pass');
w4e_it(true === $r[4], 'fourth attempt inside the window is blocked');
w4e_it(true === Woo4Etch::checkout_rate_limit_exceeded(3, $window), 'still blocked while the window is hot');
sleep($window + 1);
w4e_it(false === Woo4Etch::checkout_rate_limit_exceeded(3, $window), 'window expiry frees the client again');

/* ---- Hook behaviour: disabled = no-op, enabled = checkout error ---- */

delete_transient('w4e_rl_' . $fingerprint);
$saved    = (array) get_option('woo4etch_settings', []);
$settings = $saved;

$settings['checkout_rate_limit'] = false;
update_option('woo4etch_settings', $settings);
$errors = new WP_Error();
for ($i = 0; $i < 5; $i++) {
    Woo4Etch::checkout_rate_limit([], $errors);
}
w4e_it(!$errors->has_errors(), 'disabled: five submits produce no checkout error');

$settings['checkout_rate_limit'] = true;
update_option('woo4etch_settings', $settings);
delete_transient('w4e_rl_' . $fingerprint);
$errors = new WP_Error();
for ($i = 0; $i < 4; $i++) {
    Woo4Etch::checkout_rate_limit([], $errors);
}
w4e_it($errors->has_errors() && in_array('woo4etch_rate_limited', $errors->get_error_codes(), true), 'enabled: fourth submit is rejected with woo4etch_rate_limited');

$errors = new WP_Error();
Woo4Etch::checkout_rate_limit([], $errors);
w4e_it($errors->has_errors(), 'enabled: subsequent submits stay rejected inside the window');

// Cleanup.
update_option('woo4etch_settings', $saved);
delete_transient('w4e_rl_' . $fingerprint);

w4e_it_done();
