<?php

/**
 * Implements theme hooks for container.
 */

function unesco_oer_dc_theme_suggestions_container_alter(&$suggestions, &$variables) {
    // $node_name = get_node_name($variables['elements']['#attributes']['data-history-node-id']);
    // if (!empty($node_name)) {
    //     $suggestions[] = 'node__' . clean_suggetion($node_name);
    // }
    if ($variables['element']['#type'] !== 'container') {
        $suggestions[] = 'container__' . clean_suggetion($variables['element']['#type']);
    }

    if (!empty($variables['element']['#block_name'])) {
        $suggestions[] = 'container__' . clean_suggetion($variables['element']['#block_name']);
    }
}

// function unesco_oer_dc_preprocess_container(&$variables) {
//     // $node_name = get_node_name($variables['elements']['#attributes']['data-history-node-id']);
//     // if (!empty($node_name)) {
//     //     $suggestions[] = 'node__' . clean_suggetion($node_name);
//     // }
// }
