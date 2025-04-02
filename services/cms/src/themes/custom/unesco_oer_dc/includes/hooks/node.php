<?php

/**
 * Implements theme hooks for node.
 */

function get_youtube_embed($url, $width = 560, $height = 315) {
    $url = str_replace('watch?v=', 'embed/', $url);
    $url = str_replace('live/', 'embed/', $url);
    $embed = '<iframe';
    $embed .= ' width="' . $width . '"';
    $embed .= ' height="' . $height . '"';
    $embed .= ' src="' . $url . '"';
    $embed .= ' frameborder="0"';
    $embed .= ' allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"';
    $embed .= ' allowfullscreen';
    $embed .= '></iframe>';
    return $embed;
}

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
