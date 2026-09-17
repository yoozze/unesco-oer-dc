<?php

/**
 * @file
 * Hero slide helper functions.
 */

use Drupal\image\Entity\ImageStyle;
use Drupal\node\NodeInterface;
use Drupal\paragraphs\ParagraphInterface;

/**
 * Build render data for a hero media entity.
 *
 * @param \Drupal\media\MediaInterface|null $media
 *   Media entity.
 * @param string $image_style
 *   Optional image style machine name for image bundles.
 *
 * @return array|null
 *   Bundle-specific media data, or NULL.
 */
function get_hero_media_data($media, $image_style = '') {
    if (empty($media)) {
        return NULL;
    }

    $bundle = $media->bundle();
    $data = [
        'bundle' => $bundle,
        'name' => $media->label(),
    ];

    if ($bundle === 'image' && $media->hasField('field_media_image') && !$media->get('field_media_image')->isEmpty()) {
        $file = $media->get('field_media_image')->entity;
        if ($file) {
            $data['url'] = $file->createFileUrl();
            $data['alt'] = $media->get('field_media_image')->alt ?: '';
            $data['title'] = $media->get('field_media_image')->title ?: '';
            $data['width'] = (int) $media->get('field_media_image')->width;
            $data['height'] = (int) $media->get('field_media_image')->height;

            if ($image_style !== '') {
                $style = ImageStyle::load($image_style);
                if ($style) {
                    $uri = $file->getFileUri();
                    $data['url'] = $style->buildUrl($uri);
                    $dimensions = [
                        'width' => $data['width'],
                        'height' => $data['height'],
                    ];
                    $style->transformDimensions($dimensions, $uri);
                    $data['width'] = (int) ($dimensions['width'] ?? 0);
                    $data['height'] = (int) ($dimensions['height'] ?? 0);
                }
            }
        }
    } elseif ($bundle === 'video' && $media->hasField('field_media_video_file') && !$media->get('field_media_video_file')->isEmpty()) {
        $file = $media->get('field_media_video_file')->entity;
        if ($file) {
            $data['url'] = $file->createFileUrl();
            $data['mime'] = $file->getMimeType();
        }
    } elseif ($bundle === 'remote_video' && $media->hasField('field_media_oembed_video') && !$media->get('field_media_oembed_video')->isEmpty()) {
        $data['url'] = $media->get('field_media_oembed_video')->value;
    }

    return !empty($data['url']) ? $data : NULL;
}

/**
 * Whether a hero slide paragraph should render on the front end.
 *
 * Missing / empty field_enabled counts as enabled (backward compatible).
 *
 * @param \Drupal\paragraphs\ParagraphInterface $paragraph
 *   Hero slide paragraph.
 *
 * @return bool
 *   TRUE if the slide should be shown.
 */
function hero_slide_is_enabled($paragraph) {
    if (!$paragraph->hasField('field_enabled') || $paragraph->get('field_enabled')->isEmpty()) {
        return TRUE;
    }

    return (bool) $paragraph->get('field_enabled')->value;
}

/**
 * Build a portal CTA for a featured news/event/resource node.
 *
 * Always links to the node page with "Read more" so the slider keeps
 * engagement on-site rather than jumping to external resources.
 *
 * @return array{url: string, title: string, external: bool}|null
 */
function get_hero_node_cta(NodeInterface $node): ?array {
    try {
        $url = $node->toUrl('canonical');
    } catch (\Exception $e) {
        return NULL;
    }

    return [
        'url' => $url->toString(),
        'title' => (string) t('Read more'),
        'external' => $url->isExternal(),
    ];
}

/**
 * Resolve hero slide title, text, links, and media (node + overrides).
 *
 * @return array{
 *   title: ?string,
 *   text: array|null,
 *   links: array<int, array{url: string, title: string, external: bool}>,
 *   featured_media_entity: mixed,
 *   background_media_entity: mixed,
 *   type: ?string,
 *   type_label: ?string,
 *   news_source_badge: ?array
 * }
 */
function resolve_hero_slide_content(ParagraphInterface $paragraph): array {
    $node = NULL;
    if ($paragraph->hasField('field_featured_content') && !$paragraph->get('field_featured_content')->isEmpty()) {
        $entity = $paragraph->get('field_featured_content')->entity;
        if ($entity instanceof NodeInterface && $entity->access('view')) {
            $node = $entity;
        }
    }

    $type = NULL;
    $type_label = NULL;
    $news_source_badge = NULL;
    if ($node) {
        $type = $node->bundle();
        $type_entity = $node->type->entity ?? NULL;
        $type_label = $type_entity ? $type_entity->label() : $type;
        if ($type === 'news') {
            $news_source_badge = get_news_source_badge($node);
        }
    }

    $title = NULL;
    if (!$paragraph->get('field_title')->isEmpty()) {
        $title = $paragraph->get('field_title')->value;
    } elseif ($node) {
        $title = $node->label();
    }

    $text = NULL;
    if (!$paragraph->get('field_text')->isEmpty()) {
        $text = [
            '#type' => 'processed_text',
            '#text' => $paragraph->get('field_text')->value,
            '#format' => $paragraph->get('field_text')->format,
        ];
    } elseif ($node && $node->hasField('field_description') && !$node->get('field_description')->isEmpty()) {
        $item = $node->get('field_description')->first();
        $summary = trim((string) ($item->summary ?? ''));
        if ($summary !== '') {
            $text = [
                '#type' => 'processed_text',
                '#text' => $summary,
                '#format' => 'plain_text',
            ];
        } else {
            $value = (string) $item->value;
            $format = $item->format ?: 'basic_html';
            $trimmed = function_exists('text_summary') ? text_summary($value, $format) : $value;
            $text = [
                '#type' => 'processed_text',
                '#text' => $trimmed,
                '#format' => $format,
            ];
        }
    }

    $links = [];
    if (!$paragraph->get('field_links')->isEmpty()) {
        foreach ($paragraph->get('field_links') as $link_item) {
            /** @var \Drupal\link\LinkItemInterface $link_item */
            $url = $link_item->getUrl();
            $links[] = [
                'url' => $url->toString(),
                'title' => $link_item->title ?: $url->toString(),
                'external' => $url->isExternal(),
            ];
        }
    } elseif ($node) {
        $cta = get_hero_node_cta($node);
        if ($cta) {
            $links[] = $cta;
        }
    }

    $media_mode = 'background';
    if ($paragraph->hasField('field_featured_content_media') && !$paragraph->get('field_featured_content_media')->isEmpty()) {
        $mode = $paragraph->get('field_featured_content_media')->value;
        if (in_array($mode, ['none', 'featured', 'background'], TRUE)) {
            $media_mode = $mode;
        }
    }

    $node_image = NULL;
    if ($node && $node->hasField('field_image') && !$node->get('field_image')->isEmpty()) {
        $node_image = $node->get('field_image')->entity;
    }

    $featured_media_entity = $paragraph->get('field_media')->entity;
    if (!$featured_media_entity && $node_image && $media_mode === 'featured') {
        $featured_media_entity = $node_image;
    }

    $background_media_entity = $paragraph->get('field_background')->entity;
    if (!$background_media_entity && $node_image && $media_mode === 'background') {
        $background_media_entity = $node_image;
    }

    return [
        'title' => $title,
        'text' => $text,
        'links' => $links,
        'featured_media_entity' => $featured_media_entity,
        'background_media_entity' => $background_media_entity,
        'type' => $type,
        'type_label' => $type_label,
        'news_source_badge' => $news_source_badge,
    ];
}
