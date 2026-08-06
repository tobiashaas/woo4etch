<?php
/**
 * Layer 4 — secondary-query page-size sync: Etch's main-query loop re-runs
 * the request as a secondary WP_Query that WooCommerce's loop_shop_per_page
 * never reaches, so without the sync it paginates at the blog reading
 * setting while [woo_pagination] counts pages from Woo's per-page — tail
 * products become unreachable. The plugin syncs the main query's per-page
 * onto such loops (pre_get_posts, Woo4Etch::sync_per_page_on_secondary_queries).
 *
 * Simulated in memory: a product-archive main query is installed as the
 * globals, secondary queries are run against it, then the globals are
 * restored. Read-only — nothing is written.
 *
 * @package Woo4Etch\Tests\Integration
 */

require __DIR__ . '/_lib.php';

echo "08 secondary per-page sync\n";

w4e_it(class_exists('WooCommerce'), 'WooCommerce is active');
w4e_it(class_exists('Woo4Etch'), 'Woo4Etch class loaded');
if (!class_exists('WooCommerce') || !class_exists('Woo4Etch')) {
    w4e_it_done();
}

w4e_it(
    has_action('pre_get_posts', ['Woo4Etch', 'sync_per_page_on_secondary_queries']) !== false,
    'sync_per_page_on_secondary_queries is hooked on pre_get_posts'
);

$saved_wp_query     = isset($GLOBALS['wp_query']) ? $GLOBALS['wp_query'] : null;
$saved_wp_the_query = isset($GLOBALS['wp_the_query']) ? $GLOBALS['wp_the_query'] : null;

// A main query the way WooCommerce leaves it on /shop: product archive,
// per-page set by WC_Query::product_query() (here: an uncommon literal so
// the assertion can't pass by accident via a site default).
$main = new WP_Query([
    'post_type'      => 'product',
    'post_status'    => 'publish',
    'posts_per_page' => 13,
]);
$main->is_post_type_archive = true;
$main->is_archive           = true;

$GLOBALS['wp_query']     = $main;
$GLOBALS['wp_the_query'] = $main;

// Etch's main-query loop: cloned request vars carry no posts_per_page.
$loop = new WP_Query(['post_type' => 'product', 'post_status' => 'publish']);
$synced = (int) $loop->get('posts_per_page');

// A custom loop with its own page size must keep it.
$custom = new WP_Query(['post_type' => 'product', 'post_status' => 'publish', 'posts_per_page' => 4]);
$kept   = (int) $custom->get('posts_per_page');

// Kill-switch honored.
add_filter('woo4etch/sync_secondary_per_page', '__return_false');
$opted_out = new WP_Query(['post_type' => 'product', 'post_status' => 'publish']);
$unsynced  = (int) $opted_out->get('posts_per_page');
remove_filter('woo4etch/sync_secondary_per_page', '__return_false');

$GLOBALS['wp_query']     = $saved_wp_query;
$GLOBALS['wp_the_query'] = $saved_wp_the_query;

w4e_it_equals(13, $synced, 'main-query-style loop inherits the main query per-page');
w4e_it_equals(4, $kept, 'explicit posts_per_page on a custom loop is preserved');
w4e_it_equals((int) get_option('posts_per_page'), $unsynced, 'woo4etch/sync_secondary_per_page opt-out restores the default');

w4e_it_done();
