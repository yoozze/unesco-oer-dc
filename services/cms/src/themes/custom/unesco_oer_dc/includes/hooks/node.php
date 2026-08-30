<?php

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Url;
use Drupal\image\Entity\ImageStyle;
use Drupal\node\NodeInterface;
use Drupal\taxonomy\TermInterface;

/**
 * Implements theme hooks for node.
 */

/**
 * Implements hook_entity_view_alter().
 */
function unesco_oer_dc_entity_view_alter(array &$build, EntityInterface $entity, $display) {
    if ($entity->getEntityTypeId() !== 'node' || $entity->bundle() !== 'oer_observatory') {
        return;
    }

    $build['#cache']['contexts'][] = 'url.query_args:view';
    $build['#cache']['contexts'][] = 'url.query_args:area';
}

function unesco_oer_dc_theme_suggestions_node_alter(&$suggestions, &$variables) {
    $node_name = get_node_name($variables['elements']['#attributes']['data-history-node-id']);
    if (!empty($node_name)) {
        $suggestions[] = 'node__' . clean_suggetion($node_name);
    }
}

/**
 * Builds source badge data for news listing cards.
 *
 * @return array{label: string, icon_url: ?string, source_key: ?string}|null
 */
function unesco_oer_dc_news_source_badge(NodeInterface $node): ?array {
    if (!$node->hasField('field_news_source') || $node->get('field_news_source')->isEmpty()) {
        return NULL;
    }

    $term = $node->get('field_news_source')->entity;
    if (!$term instanceof TermInterface) {
        return NULL;
    }

    $badge = [
        'label' => $term->label(),
        'icon_url' => NULL,
        'source_key' => $term->hasField('field_source_key')
            ? (string) $term->get('field_source_key')->value
            : NULL,
    ];

    if ($term->hasField('field_icon') && !$term->get('field_icon')->isEmpty()) {
        $file = $term->get('field_icon')->entity;
        if ($file) {
            $uri = $file->getFileUri();
            $badge['icon_url'] = str_ends_with(strtolower($uri), '.svg')
                ? \Drupal::service('file_url_generator')->generateAbsoluteString($uri)
                : ImageStyle::load('thumbnail')->buildUrl($uri);
        }
    }

    return $badge;
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

    if ($node_type === 'news') {
        $badge = unesco_oer_dc_news_source_badge($node);
        if ($badge) {
            $variables['news_source_badge'] = $badge;
            $term = $node->get('field_news_source')->entity;
            if ($term) {
                $variables['#cache']['tags'] = \Drupal\Core\Cache\Cache::mergeTags(
                    $variables['#cache']['tags'] ?? [],
                    $term->getCacheTags()
                );
            }
        }
    }

    if (in_array($node_type, ['event', 'resource', 'news'])) {
        $author = $node->getOwner();
        if ($author) {
            $variables['#cache']['tags'] = \Drupal\Core\Cache\Cache::mergeTags(
                $variables['#cache']['tags'] ?? [],
                $author->getCacheTags()
            );
            $variables['author_display_name'] = get_user_display_name($author);

            // Only link when the author opts into a public profile (field_share_profile).
            // System source accounts (e.g. eventregistry) keep share off and stay unlinked.
            $share_profile = $author->hasField('field_share_profile')
                && (bool) $author->get('field_share_profile')->value;
            if (!empty($variables['author_name']) && $share_profile) {
                $variables['author_url'] = Url::fromRoute('entity.user.canonical', ['user' => $author->id()])->toString();
            } else {
                $variables['author_name'] = NULL;
            }

            $picture = $author->get('user_picture')->entity;
            $picture_classes = ['c-user__picture'];
            if ($picture) {
                $file_uri = $picture->getFileUri();
                $is_svg = str_ends_with(strtolower($file_uri), '.svg');
                if ($is_svg) {
                    $picture_classes[] = 'c-user__picture--logo';
                }

                $uri = $is_svg
                    ? \Drupal::service('file_url_generator')->generateAbsoluteString($file_uri)
                    : ImageStyle::load('thumbnail')->buildUrl($file_uri);
            } else {
                $uri = generate_avatar($author, 100);
            }

            $variables['author_picture'] = [
                '#theme' => 'image',
                '#uri' => $uri,
                '#alt' => $variables['author_display_name'],
                '#attributes' => [
                    'class' => $picture_classes,
                ],
            ];
        }
    }
}
