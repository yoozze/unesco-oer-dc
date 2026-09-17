<?php

/**
 * @file
 * Shared theme helper functions.
 */

use Drupal\file\Entity\File;
use Drupal\media\Entity\Media;

/**
 * Map a node ID to a named page slug used for theme suggestions.
 */
function get_node_name($key) {
    $node_map = [
        '223' => 'home'
    ];
    if (array_key_exists($key, $node_map)) {
        return $node_map[$key];
    }

    return null;
}

/**
 * Map a block plugin/UUID to a named block slug used for theme suggestions.
 */
function get_block_name($key) {
    $block_map = [
        'latest_news-latest_news_view' => 'latest-news-view',
        'upcoming_events-upcoming_events_view' => 'upcoming-events-view',
        'cef61108-2e3e-4661-bc31-8b08861eaaca' => 'about',
        '96334729-bbe9-4da2-ad63-adc94c0fd743' => 'who-we-are',
        'e03388f9-471e-4f18-be66-939039bab16f' => 'what-we-do',
        'b6ee0fe2-3b9a-42d8-8ab2-661aa778aa0b' => 'dc-areas-of-action',
        '351dd98b-3fde-4ef7-9d3a-a5d5b6b7f875' => 'latest-news',
        '0fa60256-5732-40bc-b932-64541cc70be2' => 'upcoming-events',
    ];
    if (array_key_exists($key, $block_map)) {
        return $block_map[$key];
    }

    return null;
}

/**
 * Ensure $variables['attributes']['class'] exists and return it by reference.
 */
function &get_classes(&$variables) {
    if (empty($variables['attributes']['class'])) {
        $variables['attributes']['class'] = [];
    }

    return $variables['attributes']['class'];
}

/**
 * Convert a kebab-case suggestion fragment to underscore form.
 */
function clean_suggetion($suggestion) {
    return str_replace('-', '_', $suggestion);
}

/**
 * Load media image metadata, optionally with an image style derivative.
 */
function get_media_image($id, $image_style = '') {
    $media = Media::load($id);
    $file = File::load($media->get('field_media_image')->target_id);
    $media_info = [
        'caption' => $media->field_media_caption->value,
        'alt' => $media->field_media_image->alt,
        'title' => $media->field_media_image->title,
        'width' => intval($media->field_media_image->width),
        'height' => intval($media->field_media_image->height),
        'url' => $file->createFileUrl(),
    ];

    if ($image_style !== '') {
        $style = \Drupal::entityTypeManager()->getStorage('image_style')->load($image_style);
        $original_image = $file->getFileUri();
        $styled_image = $style->buildUri($original_image);
        if (!file_exists($styled_image)) {
            $style->createDerivative($original_image, $styled_image);
        }

        $image_factory = \Drupal::service('image.factory')->get($styled_image);
        $media_info['thumb'] = [
            'url' => \Drupal::service('file_url_generator')->generateString($styled_image),
            'width' => $image_factory->getToolkit()->getWidth(),
            'height' => $image_factory->getToolkit()->getHeight(),
        ];
    }

    return $media_info;
}

/**
 * UNESCO membership survey link for the current language.
 */
function get_join_link() {
    $language_id = \Drupal::languageManager()->getCurrentLanguage()->getId();
    $link = 'https://surveys.unesco.org/163625?lang=' . $language_id;
    return $link;
}

/**
 * Get YouTube embed code.
 *
 * @param string $url
 * @param int $width
 * @param int $height
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
