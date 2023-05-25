<?php

/**
 * Implements theme hooks for node.
 */

function unesco_oer_dc_theme_suggestions_node_alter(&$suggestions, &$variables) {
    $node_name = get_node_name($variables['elements']['#attributes']['data-history-node-id']);
    if (!empty($node_name)) {
        $suggestions[] = 'node__' . clean_suggetion($node_name);
    }
}

/**
 * Implements hook_preprocess_HOOK() for node.html.twig.
 */
// function unesco_oer_dc_preprocess_node(&$variables) {
//     $classes = &get_classes($variables);

//     $node_name = get_node_name($variables['elements']['#attributes']['data-history-node-id']);
//     if (!empty($node_name)) {
//         $classes[] = 'c-node--' . $node_name;
//         $variables['theme_hook_suggestions'][] = 'node__' . $node_name;
//     }
// }
