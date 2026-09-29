<?php
/**
 * Layer 2/3 — stock Woo detection for push() (issue #36).
 *
 * On a fresh block shop, push() must replace WooCommerce's own defaults
 * rather than append below the footer or refuse as "already present". These
 * checks cover the pure detection helpers (no WordPress runtime).
 *
 * @package Woo4Etch\Tests
 */

function w4e_test_push_stock() {
    w4e_section('push() stock Woo detection (issue #36)');

    if (!class_exists('Woo4Etch_Health')) {
        w4e_check(false, 'Woo4Etch_Health class available');
        return;
    }

    /* ---- Page: sole stock shortcode / block is replaceable ---- */

    w4e_check(
        Woo4Etch_Health::is_replaceable_stock_page('[woocommerce_cart]', 'cart'),
        'sole [woocommerce_cart] shortcode is replaceable'
    );
    w4e_check(
        Woo4Etch_Health::is_replaceable_stock_page('[woocommerce_checkout]', 'checkout'),
        'sole [woocommerce_checkout] shortcode is replaceable'
    );
    w4e_check(
        Woo4Etch_Health::is_replaceable_stock_page('[woocommerce_my_account]', 'account'),
        'sole [woocommerce_my_account] shortcode is replaceable'
    );

    $cart_block = "<!-- wp:woocommerce/cart -->\n<div class=\"wp-block-woocommerce-cart\"></div>\n<!-- /wp:woocommerce/cart -->";
    w4e_check(
        Woo4Etch_Health::is_replaceable_stock_page($cart_block, 'cart'),
        'sole wp:woocommerce/cart block is replaceable'
    );

    $checkout_block = "<!-- wp:woocommerce/checkout -->\n<div class=\"wp-block-woocommerce-checkout\"></div>\n<!-- /wp:woocommerce/checkout -->";
    w4e_check(
        Woo4Etch_Health::is_replaceable_stock_page($checkout_block, 'checkout'),
        'sole wp:woocommerce/checkout block is replaceable'
    );

    $account_block = "<!-- wp:shortcode -->\n[woocommerce_my_account]\n<!-- /wp:shortcode -->";
    w4e_check(
        Woo4Etch_Health::is_replaceable_stock_page($account_block, 'account'),
        'sole core/shortcode My Account block is replaceable'
    );

    /* ---- Page: our layout / mixed content is NOT replaceable ---- */

    w4e_check(
        !Woo4Etch_Health::is_replaceable_stock_page(
            '<!-- wp:group --><div class="w4e-cart"></div><!-- /wp:group -->',
            'cart'
        ),
        'w4e-cart content is not treated as stock'
    );
    w4e_check(
        !Woo4Etch_Health::is_replaceable_stock_page(
            $cart_block . "\n\n<!-- wp:paragraph --><p>Note</p><!-- /wp:paragraph -->",
            'cart'
        ),
        'stock cart + extra paragraph is not replaceable (would risk other content)'
    );
    w4e_check(
        !Woo4Etch_Health::is_replaceable_stock_page('', 'cart'),
        'empty page content is not replaceable stock'
    );
    w4e_check(
        !Woo4Etch_Health::is_replaceable_stock_page($cart_block, 'checkout'),
        'cart block is not replaceable when pushing checkout'
    );

    /* ---- Template: structural stock shape (array trees) ---- */

    $header = ['blockName' => 'core/template-part', 'attrs' => ['slug' => 'header'], 'innerBlocks' => []];
    $footer = ['blockName' => 'core/template-part', 'attrs' => ['slug' => 'footer'], 'innerBlocks' => []];
    $woo_main = [
        'blockName'   => 'core/group',
        'attrs'       => ['tagName' => 'main'],
        'innerBlocks' => [
            ['blockName' => 'woocommerce/breadcrumbs', 'attrs' => [], 'innerBlocks' => []],
            ['blockName' => 'woocommerce/product-image-gallery', 'attrs' => [], 'innerBlocks' => []],
            [
                'blockName'   => 'core/post-title',
                'attrs'       => ['__woocommerceNamespace' => 'woocommerce/product-query/product-title'],
                'innerBlocks' => [],
            ],
        ],
    ];

    w4e_check(
        Woo4Etch_Health::named_blocks_are_stock_woo_template([$header, $woo_main, $footer]),
        'header + Woo group + footer is stock template shape'
    );
    w4e_check(
        !Woo4Etch_Health::named_blocks_are_stock_woo_template([$header, $footer]),
        'header + footer alone is not enough (no middle)'
    );
    w4e_check(
        !Woo4Etch_Health::named_blocks_are_stock_woo_template([
            $header,
            ['blockName' => 'core/paragraph', 'attrs' => [], 'innerBlocks' => []],
            $footer,
        ]),
        'custom paragraph between template parts is not stock'
    );
    w4e_check(
        !Woo4Etch_Health::named_blocks_are_stock_woo_template([
            $header,
            [
                'blockName'   => 'core/group',
                'attrs'       => [],
                'innerBlocks' => [
                    ['blockName' => 'woocommerce/breadcrumbs', 'attrs' => [], 'innerBlocks' => []],
                    ['blockName' => 'core/heading', 'attrs' => [], 'innerBlocks' => []],
                ],
            ],
            $footer,
        ]),
        'Woo block mixed with a custom heading is not stock'
    );

    w4e_check(
        Woo4Etch_Health::block_is_woo_stock_shaped(['blockName' => 'woocommerce/cart', 'attrs' => [], 'innerBlocks' => []]),
        'woocommerce/* blocks are stock-shaped'
    );
    w4e_check(
        Woo4Etch_Health::block_is_woo_stock_shaped([
            'blockName' => 'core/pattern',
            'attrs'     => ['slug' => 'woocommerce-blocks/related-products'],
            'innerBlocks' => [],
        ]),
        'woocommerce pattern slugs are stock-shaped'
    );
    w4e_check(
        !Woo4Etch_Health::block_is_woo_stock_shaped(['blockName' => 'core/paragraph', 'attrs' => [], 'innerBlocks' => []]),
        'core/paragraph is not stock-shaped'
    );

    /* ---- Template: string-level detector (no parse_blocks in CLI) ---- */

    $stock_single = <<<'HTML'
<!-- wp:template-part {"slug":"header"} /-->

<!-- wp:group {"tagName":"main"} -->
<main class="wp-block-group">
	<!-- wp:woocommerce/breadcrumbs /-->
	<!-- wp:woocommerce/product-image-gallery /-->
</main>
<!-- /wp:group -->

<!-- wp:template-part {"slug":"footer"} /-->
HTML;
    w4e_check(
        Woo4Etch_Health::is_stock_woo_block_template($stock_single),
        'stock single-product-shaped markup is detected'
    );

    $with_w4e = str_replace('wp-block-group', 'w4e-product wp-block-group', $stock_single);
    w4e_check(
        !Woo4Etch_Health::is_stock_woo_block_template($with_w4e),
        'markup carrying a w4e-* class is not stock'
    );

    $customized = str_replace(
        '<!-- wp:woocommerce/breadcrumbs /-->',
        "<!-- wp:paragraph -->\n<p>Hello</p>\n<!-- /wp:paragraph -->\n<!-- wp:woocommerce/breadcrumbs /-->",
        $stock_single
    );
    w4e_check(
        !Woo4Etch_Health::is_stock_woo_block_template($customized),
        'stock template with an added paragraph is not stock'
    );

    /* ---- push_target markers: our layout only (not stock Woo) ---- */

    $cart_target = Woo4Etch_Health::push_target('cart');
    w4e_check(is_array($cart_target), 'cart push_target resolves');
    if (is_array($cart_target)) {
        w4e_check(
            in_array('w4e-cart', $cart_target['markers'], true),
            'cart markers include w4e-cart'
        );
        w4e_check(
            !in_array('wp:woocommerce/cart', $cart_target['markers'], true),
            'cart markers no longer treat stock cart block as already-present'
        );
    }

    $checkout_target = Woo4Etch_Health::push_target('checkout');
    if (is_array($checkout_target)) {
        w4e_check(
            !in_array('wp:woocommerce/checkout', $checkout_target['markers'], true),
            'checkout markers no longer treat stock checkout block as already-present'
        );
        w4e_check(
            in_array('[woo_checkout_block', $checkout_target['markers'], true),
            'checkout still refuses when [woo_checkout_block] fallback is present'
        );
    }

    $account_target = Woo4Etch_Health::push_target('account');
    if (is_array($account_target)) {
        w4e_check(
            !in_array('[woocommerce_my_account', $account_target['markers'], true),
            'account markers no longer treat stock shortcode as already-present'
        );
    }
}
