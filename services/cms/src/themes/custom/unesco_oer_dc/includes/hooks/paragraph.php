<?php

/**
 * @file
 * Theme hooks for paragraphs.
 */

use Drupal\node\NodeInterface;

/**
 * Implements hook_theme_suggestions_HOOK_alter() for paragraphs.
 */
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

/**
 * Implements hook_preprocess_HOOK() for paragraph.html.twig.
 */
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
 * Implements hook_preprocess_HOOK() for paragraph--hero-slide.html.twig.
 */
function unesco_oer_dc_preprocess_paragraph__hero_slide(&$variables) {
    /** @var \Drupal\paragraphs\ParagraphInterface $paragraph */
    $paragraph = $variables['paragraph'];

    // Disabled slides are filtered in preprocess_field; skip work if accessed alone.
    if (!hero_slide_is_enabled($paragraph)) {
        $variables['attributes']['hidden'] = TRUE;
        return;
    }

    $variables['aside'] = $paragraph->get('field_aside')->value ?: 'none';
    $resolved = resolve_hero_slide_content($paragraph);
    $variables['background'] = get_hero_media_data($resolved['background_media_entity'], 'hero_background');
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

    $variables['slide_title'] = $resolved['title'];
    $variables['slide_text'] = $resolved['text'];
    $variables['slide_links'] = $resolved['links'];
    $variables['slide_type'] = $resolved['type'];
    $variables['slide_type_label'] = $resolved['type_label'];
    $variables['news_source_badge'] = $resolved['news_source_badge'];

    if (
        !empty($resolved['news_source_badge'])
        && $paragraph->hasField('field_featured_content')
        && !$paragraph->get('field_featured_content')->isEmpty()
    ) {
        $featured_node = $paragraph->get('field_featured_content')->entity;
        if (
            $featured_node instanceof NodeInterface
            && $featured_node->hasField('field_news_source')
            && !$featured_node->get('field_news_source')->isEmpty()
        ) {
            $term = $featured_node->get('field_news_source')->entity;
            if ($term) {
                $variables['#cache']['tags'] = \Drupal\Core\Cache\Cache::mergeTags(
                    $variables['#cache']['tags'] ?? [],
                    $term->getCacheTags()
                );
            }
        }
    }

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
            if (!hero_slide_is_enabled($slide)) {
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
