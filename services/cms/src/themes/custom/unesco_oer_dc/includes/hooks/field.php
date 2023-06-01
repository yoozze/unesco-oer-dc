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

// function unesco_oer_dc_theme_preprocess_field(&$variables) {}

