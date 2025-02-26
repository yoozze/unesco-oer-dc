<?php

/**
 * Implements theme hooks for region.
 */

// function unesco_oer_dc_theme_suggestions_region_alter(&$suggestions, &$variables) {
// }

function unesco_oer_dc_preprocess_region(&$variables) {
    $classes = &get_classes($variables);
    $classes[] = 'o-region';

    // Get the current route name
    $route = \Drupal::routeMatch()->getRouteName();
    $variables['route'] = $route;
}
