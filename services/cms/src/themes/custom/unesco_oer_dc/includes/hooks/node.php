<?php

use Drupal\Core\Url;
use Drupal\image\Entity\ImageStyle;

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

    if (in_array($node_type, ['event', 'resource', 'news'])) {
        $author = $node->getOwner();
        if ($author) {
            $variables['author_display_name'] = get_user_display_name($author);
            if (!empty($variables['author_name'])) {
                $variables['author_url'] = Url::fromRoute('entity.user.canonical', ['user' => $author->id()])->toString();
            }

            $picture = $author->get('user_picture')->entity;
            $uri = $picture
                ? ImageStyle::load('thumbnail')->buildUrl($picture->getFileUri())
                : generate_avatar($author, 100);
            $variables['author_picture'] = [
                '#theme' => 'image',
                '#uri' => $uri,
                '#alt' => $variables['author_display_name'],
                '#attributes' => [
                    'class' => ['c-user__picture'],
                ],
            ];
        }
    }
}
