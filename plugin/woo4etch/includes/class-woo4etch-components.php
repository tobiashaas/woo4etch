<?php
/**
 * "Woo Notices" Etch component installer — server-side.
 *
 * An Etch component is a `wp_block` post whose content is serialized Etch
 * block markup, distinguished from patterns by the `etch_component_html_key`
 * meta (PascalCase reference key) plus `etch_component_properties` (the
 * property schema array). Writing the post + meta directly is the same
 * approach the Bricks2Etch migrator uses in production for its bundled
 * system components (upsert by key, kses filters lifted around the insert so
 * Etch's block comments survive) — no builder round-trip needed. Etch's
 * public scripting API (etch.components — docs.etchwp.com/public-api) covers
 * the same operation but only runs inside the builder; a supported
 * server-side entry point remains on the wishlist (ETCH-FEATURE-REQUESTS.md).
 *
 * The component wraps [woo_notices format="plain"] in the same .w4e-notices
 * element the ready-made layouts inline — one globally editable notices
 * region. Its styles are merged into Etch's style system exactly like the
 * pattern installer does (existing selectors reused, never overwritten).
 *
 * @package Woo4Etch
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Installs the "Woo Notices" component.
 */
final class Woo4Etch_Components {

    /** Component reference key (PascalCase — Etch requires /^[A-Z][A-Za-z0-9]*$/). */
    const KEY = 'WooNotices';

    /** Component display name. */
    const NAME = 'Woo Notices';

    /**
     * Look up the installed component by its stable Etch key. The meta is the
     * source of truth — patterns and components share the wp_block post type,
     * so neither slug nor title are reliable.
     *
     * @return int Component post ID, 0 when not installed.
     */
    public static function installed_post_id() {
        $matches = get_posts([
            'post_type'      => 'wp_block',
            'post_status'    => 'any',
            'posts_per_page' => 1,
            'fields'         => 'ids',
            'meta_key'       => 'etch_component_html_key',
            'meta_value'     => self::KEY,
        ]);
        return !empty($matches) ? (int) $matches[0] : 0;
    }

    /**
     * Create (or refresh) the component. Idempotent: an existing component
     * with the key is updated in place, so builder-made tweaks to name or
     * properties survive only until a reinstall — same semantics as the
     * pattern installer.
     *
     * @return int|WP_Error Component post ID.
     */
    public static function install() {
        $blocks = Woo4Etch_Layouts::blocks_for_install('notices');
        if ($blocks === null) {
            return new WP_Error('woo4etch_unknown_layout', __('Unknown layout.', 'woo4etch'));
        }

        $postarr = [
            'post_title'   => self::NAME,
            'post_name'    => sanitize_title(self::NAME),
            'post_type'    => 'wp_block',
            'post_status'  => 'publish',
            'post_content' => serialize_blocks($blocks),
        ];

        $existing = self::installed_post_id();
        if ($existing > 0) {
            $postarr['ID'] = $existing;
        }

        // Etch block comments would be stripped by KSES for non-unfiltered
        // users; try/finally guarantees the filters come back on.
        kses_remove_filters();
        try {
            $post_id = wp_insert_post(wp_slash($postarr), true);
        } finally {
            kses_init_filters();
        }

        if (is_wp_error($post_id)) {
            return $post_id;
        }

        update_post_meta($post_id, 'etch_component_html_key', self::KEY);
        update_post_meta($post_id, 'etch_component_properties', []);

        return (int) $post_id;
    }
}
