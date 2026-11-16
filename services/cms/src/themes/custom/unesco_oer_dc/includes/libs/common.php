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
        'latest_resources-latest_resources_view' => 'latest-resources-view',
        'cef61108-2e3e-4661-bc31-8b08861eaaca' => 'about',
        '96334729-bbe9-4da2-ad63-adc94c0fd743' => 'who-we-are',
        'e03388f9-471e-4f18-be66-939039bab16f' => 'what-we-do',
        'b6ee0fe2-3b9a-42d8-8ab2-661aa778aa0b' => 'dc-areas-of-action',
        '351dd98b-3fde-4ef7-9d3a-a5d5b6b7f875' => 'latest-news',
        '0fa60256-5732-40bc-b932-64541cc70be2' => 'upcoming-events',
        '35c8cfa4-35bc-4b00-8a02-78be6bf5b2fc' => 'latest-resources',
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
 *
 * Returns NULL when the media/file is missing or not a usable raster image,
 * so callers can fall back to placeholders instead of broken markup.
 *
 * @return array{
 *   caption: mixed,
 *   alt: mixed,
 *   title: mixed,
 *   width: int,
 *   height: int,
 *   url: string,
 *   thumb?: array{url: string, width: int, height: int}
 * }|null
 */
function get_media_image($id, $image_style = '') {
    if (empty($id)) {
        return NULL;
    }

    $media = Media::load($id);
    if (!$media || !$media->hasField('field_media_image') || $media->get('field_media_image')->isEmpty()) {
        return NULL;
    }

    $image_item = $media->get('field_media_image')->first();
    $file = File::load($image_item->target_id);
    if (!$file) {
        return NULL;
    }

    $original_uri = $file->getFileUri();
    $image_factory = \Drupal::service('image.factory');
    $original_image = $image_factory->get($original_uri);
    if (!$original_image->isValid()) {
        return NULL;
    }

    $width = (int) ($image_item->width ?: $original_image->getWidth());
    $height = (int) ($image_item->height ?: $original_image->getHeight());
    if ($width < 1 || $height < 1) {
        return NULL;
    }

    $media_info = [
        'caption' => $media->hasField('field_media_caption') ? $media->field_media_caption->value : NULL,
        'alt' => $image_item->alt,
        'title' => $image_item->title,
        'width' => $width,
        'height' => $height,
        'url' => $file->createFileUrl(),
    ];

    if ($image_style === '') {
        return $media_info;
    }

    $style = \Drupal::entityTypeManager()->getStorage('image_style')->load($image_style);
    if (!$style) {
        $media_info['thumb'] = [
            'url' => $media_info['url'],
            'width' => $width,
            'height' => $height,
        ];
        return $media_info;
    }

    $styled_uri = $style->buildUri($original_uri);
    if (!file_exists($styled_uri)) {
        try {
            $style->createDerivative($original_uri, $styled_uri);
        } catch (\Throwable $e) {
            \Drupal::logger('unesco_oer_dc')->warning('Image style @style failed for @uri: @message', [
                '@style' => $image_style,
                '@uri' => $original_uri,
                '@message' => $e->getMessage(),
            ]);
        }
    }

    $styled_image = $image_factory->get($styled_uri);
    if ($styled_image->isValid() && $styled_image->getWidth() && $styled_image->getHeight()) {
        $media_info['thumb'] = [
            'url' => \Drupal::service('file_url_generator')->generateString($styled_uri),
            'width' => (int) $styled_image->getWidth(),
            'height' => (int) $styled_image->getHeight(),
        ];
    } else {
        // Derivative missing/unreadable — keep layout stable with the original.
        $media_info['thumb'] = [
            'url' => $media_info['url'],
            'width' => $width,
            'height' => $height,
        ];
    }

    return $media_info;
}

/**
 * Whether a URL points to YouTube.
 *
 * @param string $url
 *
 * @return bool
 */
function is_youtube_url($url) {
    return (bool) preg_match('#(?:https?://)?(?:www\.)?(?:youtube\.com|youtu\.be)/#i', $url);
}

/**
 * Whether a URL points to a direct video file.
 *
 * @param string $url
 *
 * @return bool
 */
function is_video_file_url($url) {
    return (bool) preg_match('/\.(mp4|webm|ogg|ogv)(\?|#|$)/i', $url);
}

/**
 * Build embed HTML for a YouTube or direct video file URL.
 *
 * @param string $url
 * @param int $width
 * @param int $height
 *
 * @return string|null
 */
function get_video_embed($url, $width = 560, $height = 315) {
    if (empty($url)) {
        return NULL;
    }

    if (is_youtube_url($url)) {
        return get_youtube_embed($url, $width, $height);
    }

    if (is_video_file_url($url)) {
        return get_video_file_embed($url, $width, $height);
    }

    return NULL;
}

/**
 * Get YouTube embed code.
 *
 * @param string $url
 * @param int $width
 * @param int $height
 */
function get_youtube_embed($url, $width = 560, $height = 315) {
    if (preg_match('#youtu\.be/([a-zA-Z0-9_-]+)#', $url, $matches)) {
        $url = 'https://www.youtube.com/embed/' . $matches[1];
    } else {
        $url = str_replace('watch?v=', 'embed/', $url);
        $url = str_replace('live/', 'embed/', $url);
    }

    $url = htmlspecialchars($url, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $embed = '<iframe';
    $embed .= ' width="' . (int) $width . '"';
    $embed .= ' height="' . (int) $height . '"';
    $embed .= ' src="' . $url . '"';
    $embed .= ' frameborder="0"';
    $embed .= ' allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"';
    $embed .= ' allowfullscreen';
    $embed .= '></iframe>';
    return $embed;
}

/**
 * Get HTML5 video embed code for a direct video file URL.
 *
 * @param string $url
 * @param int $width
 * @param int $height
 */
function get_video_file_embed($url, $width = 560, $height = 315) {
    $url = htmlspecialchars($url, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $embed = '<video';
    $embed .= ' width="' . (int) $width . '"';
    $embed .= ' height="' . (int) $height . '"';
    $embed .= ' controls';
    $embed .= ' playsinline';
    $embed .= '>';
    $embed .= '<source src="' . $url . '">';
    $embed .= '</video>';
    return $embed;
}
