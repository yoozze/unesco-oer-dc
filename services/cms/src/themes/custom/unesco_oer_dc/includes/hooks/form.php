<?php

function unesco_oer_dc_theme_suggestions_form_alter(&$suggestions, &$variables) {
    $element = &$variables['element'];
    $suggestions[] = implode('__', [
        'form',
        $element['#form_id'],
        // $element['#bundle'],
        // $element['#view_mode']
    ]);
}

function unesco_oer_dc_preprocess_form(&$variables) {
    $element = &$variables['element'];
    // $variables['test'] = 'test';
}
