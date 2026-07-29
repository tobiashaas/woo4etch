<?php
/**
 * Layer 5 — frontend smoke (issue #12): the pages WooCommerce is configured
 * to use respond with 200 and, where a Woo4Etch layout is present at its
 * target (Woo4Etch_Health::push_status), the rendered HTML carries the layout
 * markers. A single product page is additionally checked for the WooCommerce
 * add-to-cart contract (form.cart / variations form). Read-only: server-side
 * HTTP requests against the site's own URLs.
 *
 * Layouts that are simply not installed are skipped, not failed — this suite
 * must be honest on any site, not only fully-scaffolded ones.
 *
 * @package Woo4Etch\Tests\Integration
 */

require __DIR__ . '/_lib.php';

echo "04 frontend smoke\n";

w4e_it(class_exists('WooCommerce'), 'WooCommerce is active');
w4e_it(class_exists('Woo4Etch_Health'), 'Woo4Etch_Health class loaded');
if (!class_exists('WooCommerce') || !class_exists('Woo4Etch_Health')) {
    w4e_it_done();
}

/**
 * GET a URL server-side; returns [status, body].
 *
 * W4E_IT_HTTP_BASE (env) rewrites scheme+host for environments where the
 * site URL isn't reachable from the CLI process — in wp-env the site URL is
 * the HOST-mapped port (localhost:9100), so the runner sets
 * W4E_IT_HTTP_BASE=http://wordpress and the original host rides along as the
 * Host header (keeps WP's routing + canonical redirects happy).
 */
$fetch = static function ($url) {
    $base = getenv('W4E_IT_HTTP_BASE');
    // Redirects (e.g. Woo's empty-cart checkout → cart) point at the public
    // host, which W4E_IT_HTTP_BASE environments can't reach — follow them
    // manually so each hop goes through the same rewrite.
    for ($hop = 0; $hop < 4; $hop++) {
        $args = ['timeout' => 20, 'sslverify' => false, 'redirection' => 0];
        $req  = $url;
        if ($base) {
            $host = wp_parse_url($url, PHP_URL_HOST);
            $port = wp_parse_url($url, PHP_URL_PORT);
            $path = (string) wp_parse_url($url, PHP_URL_PATH);
            $args['headers'] = ['Host' => $host . ($port ? ':' . $port : '')];
            $req  = untrailingslashit($base) . ($path !== '' ? $path : '/');
        }
        $res = wp_remote_get($req, $args);
        if (is_wp_error($res)) {
            return [0, ''];
        }
        $status   = (int) wp_remote_retrieve_response_code($res);
        $location = (string) wp_remote_retrieve_header($res, 'location');
        if ($status >= 300 && $status < 400 && $location !== '') {
            $url = (strpos($location, 'http') === 0) ? $location : home_url($location);
            continue;
        }
        return [$status, (string) wp_remote_retrieve_body($res)];
    }
    return [0, ''];
};

/* ---- WooCommerce-assigned pages respond ---- */

$pages = [
    'shop'      => function_exists('wc_get_page_permalink') ? wc_get_page_permalink('shop') : '',
    'cart'      => function_exists('wc_get_page_permalink') ? wc_get_page_permalink('cart') : '',
    'checkout'  => function_exists('wc_get_page_permalink') ? wc_get_page_permalink('checkout') : '',
    'myaccount' => function_exists('wc_get_page_permalink') ? wc_get_page_permalink('myaccount') : '',
];
$bodies = [];
foreach ($pages as $key => $url) {
    if (!$url) {
        w4e_it_skip("{$key}: no page assigned");
        continue;
    }
    list($status, $body) = $fetch($url);
    w4e_it_equals(200, $status, "{$key} page responds 200 ({$url})");
    $bodies[$key] = $body;
}

/* ---- Layout markers appear in the rendered output where installed ---- */

// push_status() counts native Woo markup (cart block/shortcode) as "present"
// too — for the render assertion only the w4e layout counts, so probe the
// target's CONTENT for the w4e marker first and skip when the area is covered
// natively (fresh installs ship Woo's own cart/account blocks).
$marker_map = [
    'cart'         => ['page' => 'cart', 'marker' => 'w4e-cart'],
    'account'      => ['page' => 'myaccount', 'marker' => 'w4e-account'],
    'product-grid' => ['page' => 'shop', 'marker' => 'w4e-shop'],
];
foreach ($marker_map as $slug => $spec) {
    $target  = Woo4Etch_Health::push_target($slug);
    $content = '';
    if ($target && 'page' === $target['kind']) {
        $post    = $target['page_id'] > 0 ? get_post($target['page_id']) : null;
        $content = $post ? (string) $post->post_content : '';
    } elseif ($target) {
        $post    = Woo4Etch_Health::find_template($target['template_slug']);
        $content = $post ? (string) $post->post_content : '';
    }
    if ($content === '' || strpos($content, $spec['marker']) === false) {
        w4e_it_skip("{$slug}: w4e layout not at its target on this site (native Woo markup or empty)");
        continue;
    }
    $body = isset($bodies[$spec['page']]) ? $bodies[$spec['page']] : '';
    w4e_it(
        $body !== '' && strpos($body, $spec['marker']) !== false,
        "{$slug}: layout marker renders on the {$spec['page']} page"
    );
}

/* ---- Single product: the Woo add-to-cart contract renders ---- */

$products = get_posts(['post_type' => 'product', 'post_status' => 'publish', 'posts_per_page' => 1, 'fields' => 'ids']);
if (!$products) {
    w4e_it_skip('single product contract: no published products');
} else {
    list($status, $body) = $fetch(get_permalink($products[0]));
    w4e_it_equals(200, $status, 'single product page responds 200');
    w4e_it(
        (bool) preg_match('/<form[^>]*class="[^"]*\bcart\b[^"]*"/', $body),
        'single product renders a <form class="...cart...">'
    );
    w4e_it(
        strpos($body, 'single_add_to_cart_button') !== false || strpos($body, 'name="add-to-cart"') !== false,
        'single product renders the add-to-cart control'
    );
}

w4e_it_done();
