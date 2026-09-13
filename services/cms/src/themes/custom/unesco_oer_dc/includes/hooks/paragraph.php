<?php

/**
 * Implements theme hooks for paragraph.
 */

use Drupal\node\NodeInterface;
use Drupal\paragraphs\ParagraphInterface;

function unesco_oer_dc_theme_suggestions_paragraph_alter(&$suggestions, &$variables) {
    $elements = &$variables['elements'];
    $paragraph = &$elements['#paragraph'];
    $block_name = $elements['#block_name'] ?? null;
    if (!empty($block_name)) {
        $type = reset($paragraph->toArray()['type'])['target_id'];
        $suggestions[] = implode('__', [
            'paragraph',
            $type,
            clean_suggetion($block_name)
        ]);

        foreach ($elements as $key => &$element) {
            if (str_starts_with($key, '#') || !is_array($element)) {
                continue;
            }

            $element['#block_name'] = $block_name;
            $field_type = $element['#field_type'] ?? null;
            if ($field_type === 'image') {
                $i = 0;
                while (!empty($element[$i])) {
                    $element[$i]['#item_attributes']['data-block'] = $block_name;
                    $i++;
                }
            }
        }
    }
}

function unesco_oer_dc_preprocess_paragraph(&$variables) {
    $elements = &$variables['elements'];
    foreach ($elements as $key => &$element) {
        if (str_starts_with($key, '#') || !is_array($element)) {
            continue;
        }

        $field_type = $element['#field_type'] ?? null;
        if ($field_type === 'image') {
            $image_item = $element[0]['#item'];
            $variables['image_url'] = $image_item->entity->getFileUri();
        } else if ($field_type === 'link') {
            $url = $element[0]['#url'];
            $variables['link_url'] = $url->toString();
            $variables['link_external'] = $url->isExternal();
        }
    }
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
function unesco_oer_dc_hero_slide_is_enabled($paragraph) {
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
function unesco_oer_dc_hero_node_cta(NodeInterface $node): ?array {
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
 * Resolve hero slide title, text, links, and featured media (node + overrides).
 *
 * @return array{
 *   title: ?string,
 *   text: array|null,
 *   links: array<int, array{url: string, title: string, external: bool}>,
 *   featured_media_entity: mixed
 * }
 */
function unesco_oer_dc_hero_slide_resolve_content(ParagraphInterface $paragraph): array {
    $node = NULL;
    if ($paragraph->hasField('field_featured_content') && !$paragraph->get('field_featured_content')->isEmpty()) {
        $entity = $paragraph->get('field_featured_content')->entity;
        if ($entity instanceof NodeInterface && $entity->access('view')) {
            $node = $entity;
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
        $cta = unesco_oer_dc_hero_node_cta($node);
        if ($cta) {
            $links[] = $cta;
        }
    }

    $featured_media_entity = $paragraph->get('field_media')->entity;
    if (!$featured_media_entity && $node && $node->hasField('field_image') && !$node->get('field_image')->isEmpty()) {
        $featured_media_entity = $node->get('field_image')->entity;
    }

    return [
        'title' => $title,
        'text' => $text,
        'links' => $links,
        'featured_media_entity' => $featured_media_entity,
    ];
}

/**
 * Implements hook_preprocess_HOOK() for paragraph--hero-slide.html.twig.
 */
function unesco_oer_dc_preprocess_paragraph__hero_slide(&$variables) {
    /** @var \Drupal\paragraphs\ParagraphInterface $paragraph */
    $paragraph = $variables['paragraph'];

    // Disabled slides are filtered in preprocess_field; skip work if accessed alone.
    if (!unesco_oer_dc_hero_slide_is_enabled($paragraph)) {
        $variables['attributes']['hidden'] = TRUE;
        return;
    }

    $variables['aside'] = $paragraph->get('field_aside')->value ?: 'none';
    $variables['background'] = get_hero_media_data($paragraph->get('field_background')->entity, 'hero_background');
    $variables['background_opacity'] = 0.25;
    if ($paragraph->hasField('field_background_opacity') && !$paragraph->get('field_background_opacity')->isEmpty()) {
        $opacity = (float) $paragraph->get('field_background_opacity')->value;
        if ($opacity < 0) {
            $opacity = 0.0;
        } elseif ($opacity > 1) {
            $opacity = 1.0;
        }

        $variables['background_opacity'] = $opacity;
    }

    $variables['background_fit'] = 'cover';
    if ($paragraph->hasField('field_background_fit') && !$paragraph->get('field_background_fit')->isEmpty()) {
        $fit = $paragraph->get('field_background_fit')->value;
        if (in_array($fit, ['cover', 'contain'], TRUE)) {
            $variables['background_fit'] = $fit;
        }
    }

    $variables['aside_media'] = NULL;
    $variables['aside_media_render'] = NULL;
    $variables['featured_media'] = NULL;
    $variables['featured_media_render'] = NULL;

    $aside_media_entity = $paragraph->get('field_aside_media')->entity;
    if ($aside_media_entity) {
        if ($aside_media_entity->bundle() === 'remote_video') {
            $variables['aside_media_render'] = \Drupal::entityTypeManager()
                ->getViewBuilder('media')
                ->view($aside_media_entity, 'default');
        } else {
            $variables['aside_media'] = get_hero_media_data($aside_media_entity);
        }
    }

    $resolved = unesco_oer_dc_hero_slide_resolve_content($paragraph);
    $variables['slide_title'] = $resolved['title'];
    $variables['slide_text'] = $resolved['text'];
    $variables['slide_links'] = $resolved['links'];

    $featured_media_entity = $resolved['featured_media_entity'];
    if ($featured_media_entity) {
        if ($featured_media_entity->bundle() === 'remote_video') {
            $variables['featured_media_render'] = \Drupal::entityTypeManager()
                ->getViewBuilder('media')
                ->view($featured_media_entity, 'default');
        } else {
            $variables['featured_media'] = get_hero_media_data($featured_media_entity, 'hero_featured');
        }
    }

    $variables['is_first_slide'] = FALSE;
    $parent = $paragraph->getParentEntity();
    if ($parent && $parent->hasField('field_slides') && !$parent->get('field_slides')->isEmpty()) {
        foreach ($parent->get('field_slides')->referencedEntities() as $slide) {
            if (!unesco_oer_dc_hero_slide_is_enabled($slide)) {
                continue;
            }

            $variables['is_first_slide'] = ((int) $slide->id() === (int) $paragraph->id());
            break;
        }
    }

    $variables['has_featured_media'] = (bool) $featured_media_entity;
    $variables['featured_autoplay'] = FALSE;
    if ($paragraph->hasField('field_featured_autoplay') && !$paragraph->get('field_featured_autoplay')->isEmpty()) {
        $variables['featured_autoplay'] = (bool) $paragraph->get('field_featured_autoplay')->value;
    }

    // Video-only slide (no aside, title, body, or links): cinematic layout.
    $featured_is_video = FALSE;
    $featured_is_split_media = FALSE;
    if ($featured_media_entity) {
        $bundle = $featured_media_entity->bundle();
        $featured_is_video = in_array($bundle, ['video', 'remote_video'], TRUE);
        $featured_is_split_media = $featured_is_video || $bundle === 'image';
    }

    $has_title = $variables['slide_title'] !== NULL && trim((string) $variables['slide_title']) !== '';
    $has_text = FALSE;
    if (!empty($variables['slide_text']['#text'])) {
        $has_text = trim(strip_tags((string) $variables['slide_text']['#text'])) !== '';
    }
    $has_links = !empty($variables['slide_links']);
    $has_copy = $has_title || $has_text || $has_links;

    $variables['featured_video_focus'] = (
        $variables['aside'] === 'none'
        && $featured_is_video
        && !$has_copy
    );

    // No aside + featured image/video + copy: side-by-side on wide screens.
    $variables['featured_split'] = (
        $variables['aside'] === 'none'
        && $featured_is_split_media
        && $has_copy
    );
}
