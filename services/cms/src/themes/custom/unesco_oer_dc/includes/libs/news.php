<?php

/**
 * @file
 * News-related theme helper functions.
 */

use Drupal\image\Entity\ImageStyle;
use Drupal\node\NodeInterface;
use Drupal\taxonomy\TermInterface;

/**
 * Build news source badge data for teasers / hero / article chrome.
 *
 * Official (oerdc) uses the term icon. All other sources use the theme
 * newspaper sprite so vendor logos (e.g. EventRegistry) stay off the UI.
 *
 * @return array{label: string, icon_url: ?string, icon: ?string, source_key: ?string}|null
 */
function get_news_source_badge(NodeInterface $node): ?array {
    if (!$node->hasField('field_news_source') || $node->get('field_news_source')->isEmpty()) {
        return NULL;
    }

    $term = $node->get('field_news_source')->entity;
    if (!$term instanceof TermInterface) {
        return NULL;
    }

    $source_key = $term->hasField('field_source_key')
        ? (string) $term->get('field_source_key')->value
        : NULL;

    $badge = [
        'label' => $term->label(),
        'icon_url' => NULL,
        'icon' => NULL,
        'source_key' => $source_key,
    ];

    if ($source_key === 'oerdc') {
        if ($term->hasField('field_icon') && !$term->get('field_icon')->isEmpty()) {
            $file = $term->get('field_icon')->entity;
            if ($file) {
                $uri = $file->getFileUri();
                $badge['icon_url'] = str_ends_with(strtolower($uri), '.svg')
                    ? \Drupal::service('file_url_generator')->generateAbsoluteString($uri)
                    : ImageStyle::load('thumbnail')->buildUrl($uri);
            }
        }
    } else {
        $badge['icon'] = 'newsmode';
    }

    return $badge;
}
