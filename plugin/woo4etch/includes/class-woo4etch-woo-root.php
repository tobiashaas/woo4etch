<?php
/**
 * Experimental: a namespaced `{woo.*}` dynamic-data root.
 *
 * Mirrors the documented `{options.*}` shop data under a root that makes
 * ownership obvious and cannot collide with other plugins' option keys:
 *
 *   {woo.cart.items} {woo.cart.count} {woo.cart.subtotal} {woo.cart.total}
 *   {woo.cart.is_empty} {woo.cart.url} {woo.cart.nonce} {woo.cart.cross_sells}
 *   {woo.checkout.url}
 *   {woo.account.menu} {woo.account.endpoint} {woo.account.orders}
 *   {woo.order}
 *
 * EXPERIMENTAL — this rides on an Etch internal: there is no public API for
 * registering dynamic-data roots yet, so the root is registered through
 * Etch's DynamicContentRegistry::enqueue() (undocumented; see
 * ETCH-FEATURE-REQUESTS.md #3). If Etch renames or refactors that class the
 * `woo` root silently disappears — `{options.*}` keeps working either way,
 * which is why it remains the documented, guaranteed spelling. When Etch
 * ships a public registration API the mechanism is swapped out here and the
 * `{woo.*}` keys stay exactly the same.
 *
 * Disable: add_filter('woo4etch/enable_woo_root', '__return_false');
 * Reshape: add_filter('woo4etch/woo_root_data', fn($d) => $d);
 *
 * @package Woo4Etch
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Registers the `woo` dynamic-data root with Etch.
 */
final class Woo4Etch_Woo_Root {

    /** Etch internal — guarded with class_exists/is_callable everywhere. */
    const REGISTRY_CLASS = 'Etch\\Blocks\\Global\\DynamicContent\\DynamicContentRegistry';

    /** @var bool Whether registration ran (or was attempted) this request. */
    private static $registered = false;

    /**
     * Hook registration. Runs lazily during block rendering (the same timing
     * proven by third-party integrations using this seam): the root is only
     * built and enqueued when a rendered block actually references `woo.` —
     * pages without `{woo.*}` keys never pay for the data assembly.
     */
    public static function init() {
        if (!apply_filters('woo4etch/enable_woo_root', true)) {
            return;
        }
        add_filter('render_block_data', [__CLASS__, 'maybe_register'], 1);
    }

    /**
     * render_block_data: register the root right before the first top-level
     * block that references it renders. render_block_data only fires for
     * top-level blocks, so the reference check scans the whole block tree
     * (synced patterns resolve through their own do_blocks pass and get
     * their own top-level filter call).
     *
     * @param array $parsed_block Parsed block (parse_blocks shape).
     * @return array Unchanged block.
     */
    public static function maybe_register($parsed_block) {
        if (self::$registered || !is_array($parsed_block)) {
            return $parsed_block;
        }
        if (!self::references_woo_root($parsed_block)) {
            return $parsed_block;
        }

        // One attempt per request — also when Etch's registry is absent, so a
        // page full of {woo.*} keys doesn't re-probe on every block.
        self::$registered = true;

        if (!class_exists(self::REGISTRY_CLASS)) {
            return $parsed_block;
        }
        $enqueue = [self::REGISTRY_CLASS, 'enqueue'];
        if (!is_callable($enqueue)) {
            return $parsed_block;
        }

        call_user_func($enqueue, 'woo', self::build_data());

        return $parsed_block;
    }

    /**
     * Does this block tree reference the `woo` root anywhere?
     *
     * Matches every way Etch spells a data path: `{woo.cart.count}` in text
     * content and attribute values, `"target":"woo.cart.items"` on loops,
     * `"leftHand":"woo.cart.is_empty"` in conditions.
     *
     * @param array $block Parsed block incl. innerBlocks.
     * @return bool
     */
    private static function references_woo_root(array $block) {
        $json = wp_json_encode($block);
        if (!is_string($json)) {
            return false;
        }
        return strpos($json, '{woo.') !== false || strpos($json, '"woo.') !== false;
    }

    /**
     * Assemble the `woo` root from the same builders that feed `{options.*}` —
     * identical values, identical builder-canvas sample data, just nested
     * under a namespace that says where it comes from.
     *
     * @return array<string,mixed>
     */
    public static function build_data() {
        $cart    = Woo4Etch::expose_cart_data([]);
        $account = Woo4Etch::expose_account_order_data([]);

        $data = [
            'cart' => [
                'items'       => isset($cart['cart_items']) ? $cart['cart_items'] : [],
                'count'       => isset($cart['cart_count']) ? $cart['cart_count'] : 0,
                'subtotal'    => isset($cart['cart_subtotal']) ? $cart['cart_subtotal'] : '',
                'total'       => isset($cart['cart_total']) ? $cart['cart_total'] : '',
                'is_empty'    => isset($cart['cart_is_empty']) ? $cart['cart_is_empty'] : true,
                'url'         => isset($cart['cart_url']) ? $cart['cart_url'] : '',
                'nonce'       => isset($cart['cart_nonce']) ? $cart['cart_nonce'] : '',
                'cross_sells' => isset($cart['cross_sells']) ? $cart['cross_sells'] : [],
            ],
            'checkout' => [
                'url' => isset($cart['checkout_url']) ? $cart['checkout_url'] : '',
            ],
            'shop' => [
                'url' => isset($cart['shop_url']) ? $cart['shop_url'] : '',
            ],
            'account' => [
                'is_logged_in' => isset($account['is_logged_in']) ? $account['is_logged_in'] : false,
                'menu'         => isset($account['account_menu']) ? $account['account_menu'] : [],
                'endpoint'     => isset($account['account_endpoint']) ? $account['account_endpoint'] : '',
                'orders'       => isset($account['account_orders']) ? $account['account_orders'] : [],
            ],
            'order' => isset($account['order']) ? $account['order'] : null,
        ];

        return apply_filters('woo4etch/woo_root_data', $data);
    }
}
