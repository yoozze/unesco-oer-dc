<?php

function unesco_oer_dc_theme_suggestions_field_alter(&$suggestions, &$variables) {
    $element = &$variables['element'];
    $block_name = $element['#block_name'] ?? null;
    if (!empty($block_name)) {
        $suggestions[] = implode('__', [
            'field',
            $element['#entity_type'],
            $element['#field_name'],
            $element['#bundle'],
            clean_suggetion($block_name)
        ]);

        $i = 0;
        while (!empty($element[$i])) {
            $element[$i]['#block_name'] = $block_name;
            $i++;
        }
    }

    if ($element['#entity_type'] === 'node') {
        $suggestions[] = 'field__node__article';
        $suggestions[] = 'field__node__' . $element['#field_name'] . '__' . $element['#view_mode'];
    }
}

function unesco_oer_dc_preprocess_field(&$variables) {
    if (!empty($variables['element']['#is_first_slide'])) {
        $variables['is_first_slide'] = TRUE;
    } else {
        $variables['is_first_slide'] = FALSE;
    }

    // Omit disabled hero slides from the front-end slider markup.
    if (
        ($variables['element']['#field_name'] ?? '') === 'field_slides'
        && ($variables['element']['#bundle'] ?? '') === 'hero'
        && !empty($variables['items'])
    ) {
        $field_items = $variables['element']['#items'] ?? NULL;
        $filtered = [];
        foreach ($variables['items'] as $delta => $item) {
            $paragraph = $field_items[$delta]->entity ?? NULL;
            if ($paragraph && !unesco_oer_dc_hero_slide_is_enabled($paragraph)) {
                continue;
            }

            $filtered[] = $item;
        }
        $variables['items'] = $filtered;
    }

    if ($variables['element']['#entity_type'] === 'node' && in_array($variables['element']['#field_name'], ['field_image', 'field_logo'])) {
        if ($variables['element']['#view_mode'] === 'full') {
            foreach ($variables['element']['#items'] as $i => &$item) {
                $variables['items'][$i]['image'] = get_media_image(
                    $item->getValue()['target_id'],
                    $variables['element']['#field_name'] === 'field_logo' ? 'grid_item' : 'jumbo'
                );
            }
        } else if (!empty($variables['element']['#items'])) {
            $item = $variables['element']['#items'][0];
            $variables['items'][0]['image'] = get_media_image(
                $item->getValue()['target_id'],
                'grid_item'
            );
        }
    } elseif ($variables['element']['#entity_type'] === 'node' && $variables['element']['#field_name'] === 'field_links') {
        foreach ($variables['items'] as $i => &$item) {
            if (!$item['content']['#url']->isExternal()) {
                $node = \Drupal\node\Entity\Node::load($item['content']['#url']->getRouteParameters()['node']);
                if (!empty($node)) {
                    $item['title'] = $node->getTitle();
                }
            }
        }
    } elseif (
        ($variables['element']['#entity_type'] ?? '') === 'node'
        && ($variables['element']['#bundle'] ?? '') === 'news'
        && ($variables['element']['#field_name'] ?? '') === 'field_area_of_action'
        && !empty($variables['element']['#items'])
    ) {
        $links = [];
        foreach ($variables['element']['#items'] as $item) {
            $term = $item->entity;
            if (!$term) {
                continue;
            }

            $links[] = [
                'label' => $term->label(),
                'url' => \Drupal\Core\Url::fromUserInput('/news', [
                    'query' => ['field_area_of_action_target_id' => [$term->id()]],
                ])->toString(),
            ];
        }

        $variables['area_of_action_listing_links'] = $links;
    } elseif (
        ($variables['element']['#entity_type'] ?? '') === 'node'
        && ($variables['element']['#bundle'] ?? '') === 'news'
        && ($variables['element']['#field_name'] ?? '') === 'field_country'
        && !empty($variables['element']['#items'])
    ) {
        $links = [];
        foreach ($variables['element']['#items'] as $item) {
            $term = $item->entity;
            if (!$term) {
                continue;
            }

            $links[] = [
                'label' => $term->label(),
                'url' => \Drupal\Core\Url::fromUserInput('/news', [
                    'query' => ['field_country_target_id' => [$term->id()]],
                ])->toString(),
            ];
        }

        $variables['country_listing_links'] = $links;
    }
}
