<?php

/**
 * Implements theme hooks for paragraph.
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
    $variables['background'] = get_hero_media_data($paragraph->get('field_background')->entity);
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

    $featured_media_entity = $paragraph->get('field_media')->entity;
    if ($featured_media_entity) {
        if ($featured_media_entity->bundle() === 'remote_video') {
            $variables['featured_media_render'] = \Drupal::entityTypeManager()
                ->getViewBuilder('media')
                ->view($featured_media_entity, 'default');
        } else {
            $variables['featured_media'] = get_hero_media_data($featured_media_entity);
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

    $variables['has_featured_media'] = !$paragraph->get('field_media')->isEmpty();
    $variables['featured_autoplay'] = FALSE;
    if ($paragraph->hasField('field_featured_autoplay') && !$paragraph->get('field_featured_autoplay')->isEmpty()) {
        $variables['featured_autoplay'] = (bool) $paragraph->get('field_featured_autoplay')->value;
    }

    if (!empty($variables['content']['field_title']) && is_array($variables['content']['field_title'])) {
        $variables['content']['field_title']['#is_first_slide'] = $variables['is_first_slide'];
    }
}
