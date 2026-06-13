<?php
/**
 * Layer 2 — pure-PHP consistency checks (issue #10). No WordPress runtime
 * needed: the shortcode catalog is plain metadata, the version markers are
 * plain text. These guard the release invariants that have bitten before
 * (mismatched version markers; a catalog entry pointing at a method that
 * doesn't exist; an undocumented shortcode).
 *
 * @package Woo4Etch\Tests
 */

function w4e_test_consistency() {
    $plugin_file = WOO4ETCH_PLUGIN_DIR . '/woo4etch.php';
    $readme_file = WOO4ETCH_PLUGIN_DIR . '/readme.txt';

    /* ---- Version markers must agree ---- */
    w4e_section('Version markers in sync');

    $plugin_src = file_get_contents($plugin_file);
    $readme_src = file_get_contents($readme_file);

    preg_match('/^[ \t]*\*[ \t]*Version:[ \t]*(\S+)/m', $plugin_src, $hm);
    $header_version = $hm[1] ?? '(none)';

    $const_version = defined('Woo4Etch::VERSION') ? Woo4Etch::VERSION : (class_exists('Woo4Etch') ? Woo4Etch::VERSION : '(none)');

    preg_match('/^Stable tag:[ \t]*(\S+)/m', $readme_src, $sm);
    $stable_tag = $sm[1] ?? '(none)';

    w4e_equals($header_version, $const_version, 'woo4etch.php header Version === Woo4Etch::VERSION');
    w4e_equals($header_version, $stable_tag, 'woo4etch.php header Version === readme.txt Stable tag');

    /* ---- Shortcode catalog integrity ---- */
    w4e_section('Shortcode catalog integrity');

    w4e_check(class_exists('Woo4Etch'), 'Woo4Etch class loaded');
    $catalog = Woo4Etch::get_shortcode_catalog();
    w4e_check(is_array($catalog) && $catalog !== [], 'catalog is a non-empty array');

    $required_keys = ['category', 'attributes', 'description', 'example'];
    $registered = [];   // non-native tags that should be add_shortcode()'d
    foreach ($catalog as $tag => $entry) {
        foreach ($required_keys as $k) {
            w4e_check(isset($entry[$k]) && $entry[$k] !== '', "[{$tag}] has non-empty '{$k}'");
        }

        $is_native = !empty($entry['native']);
        if ($is_native) {
            w4e_check(empty($entry['method']), "[{$tag}] native entry has no method (Woo core owns the tag)");
        } else {
            $method = $entry['method'] ?? '';
            w4e_check($method !== '', "[{$tag}] non-native entry declares a method");
            w4e_check(
                $method !== '' && method_exists('Woo4Etch', $method),
                "[{$tag}] method Woo4Etch::{$method}() exists"
            );
            $registered[$tag] = $method;
        }
    }

    /* ---- Every registered shortcode is documented in readme.txt ---- */
    w4e_section('Every shortcode documented in readme.txt');
    foreach (array_keys($registered) as $tag) {
        // readme.txt lists shortcodes as `[woo_title ...]` / `[do_action ...]`.
        w4e_check(
            strpos($readme_src, '`[' . $tag) !== false || strpos($readme_src, '[' . $tag . ']') !== false || strpos($readme_src, '[' . $tag . ' ') !== false,
            "readme.txt documents [{$tag}]"
        );
    }

    /* ---- No orphan shortcode_* methods (every one is in the catalog) ---- */
    w4e_section('No orphan shortcode_* methods');
    $catalog_methods = array_filter(array_map(
        static function ($e) { return $e['method'] ?? ''; },
        $catalog
    ));
    $catalog_methods = array_flip($catalog_methods);
    $ref = new ReflectionClass('Woo4Etch');
    foreach ($ref->getMethods(ReflectionMethod::IS_PUBLIC | ReflectionMethod::IS_STATIC) as $m) {
        if (strpos($m->name, 'shortcode_') !== 0) {
            continue;
        }
        w4e_check(
            isset($catalog_methods[$m->name]),
            "Woo4Etch::{$m->name}() is referenced by a catalog entry"
        );
    }
}
