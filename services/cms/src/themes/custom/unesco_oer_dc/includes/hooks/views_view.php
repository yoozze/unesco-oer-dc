<?php

/**
 * Implements theme hooks for views_view.
 */

function unesco_oer_dc_theme_suggestions_views_view_alter(&$suggestions, &$variables) {
    // if (!empty($variables['rows']) && !empty($variables['rows'][0]['#block_name'])) {
    //     $suggestions[] = 'views_view__' . clean_suggetion($variables['rows'][0]['#block_name']);
    // }
    // $suggestions[] = 'views_view__' . clean_suggetion($variables['view']->current_display);
}

function unesco_oer_dc_theme_suggestions_views_view_unformatted_alter(&$suggestions, &$variables) {
    // if (
    //     !empty($variables['rows']) &&
    //     !empty($variables['rows'][0]) &&
    //     !empty($variables['rows'][0]['#block_name'])
    // ) {
    //     $suggestions[] = 'views_view_unformatted__' . clean_suggetion($variables['rows'][0]['#block_name']);
    // }
}

function unesco_oer_dc_theme_suggestions_views_view_fields_alter(&$suggestions, &$variables) {
    // if (
    //     !empty($variables['rows']) &&
    //     !empty($variables['rows'][0]) &&
    //     !empty($variables['rows'][0]['#block_name'])
    // ) {
    //     $suggestions[] = 'views_view_unformatted__' . clean_suggetion($variables['rows'][0]['#block_name']);
    // }

    // $suggestions[] = 'views_view_fields__' . clean_suggetion($variables['view']->current_display);
}

function unesco_oer_dc_theme_suggestions_views_view_field_alter(&$suggestions, &$variables) {
    if (
        !empty($variables['view']) &&
        !empty($variables['field'])
    ) {
        $suggestions[] = 'views_view_field__' . clean_suggetion($variables['field']->field) . '__' . clean_suggetion($variables['view']->current_display);
    }
}
