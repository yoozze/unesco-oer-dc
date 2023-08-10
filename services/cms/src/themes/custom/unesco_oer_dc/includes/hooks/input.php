<?php

/**
 * Implements theme hooks for links.
 */

function unesco_oer_dc_preprocess_input__submit(&$variables) {
    $id = $variables['element']['#id'];
    if (
        // str_starts_with($id, 'edit-submit') ||
        str_starts_with($id, 'edit-submit-news') ||
        str_starts_with($id, 'edit-submit-events') ||
        str_starts_with($id, 'edit-submit-activities') ||
        str_starts_with($id, 'edit-submit-resources')
    ) {
        $variables['element']['#leading_icon'] = 'search';
    }
}

function unesco_oer_dc_preprocess_input__textfield(&$variables) {
    $id = $variables['element']['#id'];
    if (in_array($id, ['edit-keys', 'edit-search'])) {
        $variables['element']['#control_size'] = 'small';
    }
}
