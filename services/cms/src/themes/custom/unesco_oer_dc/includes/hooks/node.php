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
function unesco_oer_dc_preprocess_node(&$variables) {

    $node = $variables['node'];
    $node_type = $node->getType();

    if ($node_type === 'resource') {
        $url = $node->get('field_url')->uri;

        // If url is linking youtube video, prepare embed html
        if (!empty($url) && strpos($url, 'https://www.youtube.com') !== false) {
            $variables['video_embed'] = get_youtube_embed($url, 560 * 2, 315 * 2);
        }
    }
}
