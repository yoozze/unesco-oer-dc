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
    }
}
