<?php

/**
 * Implements theme hooks for html.
 */

function unesco_oer_dc_preprocess_html(&$variables) {
    // Add class
    // $variables['attributes']['class'][] = 'page';

    // Read SVG icons
    $theme_path = \Drupal::service('extension.list.theme')->getPath('unesco_oer_dc');
    $svg = file_get_contents($theme_path . '/images/icons.svg');
    $variables['svg_icons'] = $svg;
}
