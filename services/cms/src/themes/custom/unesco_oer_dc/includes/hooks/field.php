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
    }
}

function unesco_oer_dc_preprocess_field(&$variables) {
    if ($variables['element']['#entity_type'] === 'node' && $variables['element']['#field_name'] === 'field_image') {
        $variables['#test'] = 'test';
        foreach ($variables['element']['#items'] as $i => &$item) {
            $variables['items'][$i]['image'] = get_media_image(
                $item->getValue()['target_id'],
                'jumbo'
            );
        }
    } elseif ($variables['element']['#entity_type'] === 'node' && $variables['element']['#field_name'] === 'field_links') {
        foreach ($variables['items'] as $i => &$item) {
            if (!$item['content']['#url']->isExternal()) {
                $node = \Drupal\node\Entity\Node::load($item['content']['#url']->getRouteParameters()['node']);
                $variables['items'][$i]['title'] = $node->getTitle();
            }
        }
    }
}
