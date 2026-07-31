<?php
/**
 * Repair etch/condition blocks whose conditionString is a display label
 * instead of the parseable expression the Etch builder round-trips.
 *
 * Background: Woo4Etch layouts generated before this fix wrote the human
 * label into conditionString. The builder re-derives the condition object
 * from that string on every save — one builder save turned the label into
 * leftHand, the data path was lost, and the condition evaluated false
 * forever (symptom: the pushed checkout page rendered blank).
 *
 * This script recomputes conditionString from the (still intact) condition
 * object for every etch/condition in pages, templates, template parts and
 * components. Blocks already mangled by a builder save (leftHand no longer
 * a data path) cannot be derived and are reported — re-push those layouts.
 *
 * Usage: wp eval-file tools/repair-condition-strings.php
 */

if (!defined('ABSPATH')) {
    exit(1);
}

function w4e_rcs_expression($left, $operator, $right) {
    $l = is_array($left) ? w4e_rcs_expression($left['leftHand'], $left['operator'], $left['rightHand']) : (string) $left;
    if ('isTruthy' === $operator) {
        return $l;
    }
    if ('isFalsy' === $operator) {
        return '!' . $l;
    }
    $r = is_array($right) ? w4e_rcs_expression($right['leftHand'], $right['operator'], $right['rightHand']) : (string) $right;
    return $l . ' ' . $operator . ' ' . $r;
}

function w4e_rcs_path_ok($left) {
    if (is_array($left)) {
        return w4e_rcs_path_ok($left['leftHand'] ?? '');
    }
    // Real data paths contain a dot (options.x, this.x, item.x, rate.x …).
    return is_string($left) && strpos($left, '.') !== false;
}

function w4e_rcs_walk(&$blocks, &$fixed, &$broken) {
    foreach ($blocks as &$b) {
        if (($b['blockName'] ?? '') === 'etch/condition') {
            $cond = $b['attrs']['condition'] ?? null;
            if (is_array($cond)) {
                if (w4e_rcs_path_ok($cond['leftHand'] ?? '')) {
                    $expr = w4e_rcs_expression($cond['leftHand'], $cond['operator'] ?? 'isTruthy', $cond['rightHand'] ?? null);
                    if ((string) ($b['attrs']['conditionString'] ?? '') !== $expr) {
                        $b['attrs']['conditionString'] = $expr;
                        $fixed++;
                    }
                } else {
                    $broken++;
                }
            }
        }
        if (!empty($b['innerBlocks'])) {
            w4e_rcs_walk($b['innerBlocks'], $fixed, $broken);
        }
    }
}

$ids = get_posts([
    'post_type'      => ['page', 'wp_template', 'wp_template_part', 'wp_block'],
    'post_status'    => 'publish',
    'posts_per_page' => -1,
    's'              => 'etch/condition',
    'fields'         => 'ids',
]);

foreach ($ids as $id) {
    $post = get_post($id);
    if (strpos($post->post_content, 'etch/condition') === false) {
        continue;
    }
    $blocks = parse_blocks($post->post_content);
    $fixed  = 0;
    $broken = 0;
    w4e_rcs_walk($blocks, $fixed, $broken);
    if ($fixed > 0) {
        wp_update_post(['ID' => $id, 'post_content' => serialize_blocks($blocks)]);
    }
    if ($fixed > 0 || $broken > 0) {
        printf(
            "%d (%s/%s): %d fixed%s\n",
            $id,
            $post->post_type,
            $post->post_name,
            $fixed,
            $broken > 0 ? ", {$broken} UNREPAIRABLE (builder-mangled — re-push this layout)" : ''
        );
    }
}
echo "done\n";
