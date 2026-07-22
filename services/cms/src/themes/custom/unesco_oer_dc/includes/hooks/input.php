<?php

/**
 * Implements theme hooks for links.
 */

function unesco_oer_dc_preprocess_input__submit(&$variables) {
    $element = &$variables['element'];
    $id = $element['#id'] ?? '';
    $name = $element['#name'] ?? '';
    $classes = $element['#attributes']['class'] ?? [];

    if (
        // str_starts_with($id, 'edit-submit') ||
        str_starts_with($id, 'edit-submit-news') ||
        str_starts_with($id, 'edit-submit-events') ||
        str_starts_with($id, 'edit-submit-activities') ||
        str_starts_with($id, 'edit-submit-resources')
    ) {
        $element['#leading_icon'] = 'search';
    }

    // Secondary actions in multi-value field widgets.
    if (
        in_array('field-add-more-submit', $classes, TRUE)
        || str_ends_with($name, '_remove_button')
    ) {
        $element['#button_variant'] = 'outlined';
    }
}

function unesco_oer_dc_preprocess_input__textfield(&$variables) {
    $id = $variables['element']['#id'];
    if (in_array($id, ['edit-keys', 'edit-search'])) {
        $variables['element']['#control_size'] = 'small';
    }
}
