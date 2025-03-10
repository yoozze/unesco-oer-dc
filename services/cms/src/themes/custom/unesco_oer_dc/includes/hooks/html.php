<?php

/**
 * Implements theme hooks for html.
 */

function unesco_oer_dc_preprocess_html(&$variables) {
    // Get path
    $path = \Drupal::service('path.current')->getPath();

    // Get URL alias
    $alias = \Drupal::service('path_alias.manager')->getAliasByPath($path);

    // Get slug
    $path_items = explode('/', trim($alias, '/'));
    $slug = end($path_items);
    $variables['slug'] = $slug;

    // Add class
    // $variables['attributes']['class'][] = 'page';

    // Read SVG icons
    $theme_path = \Drupal::service('extension.list.theme')->getPath('unesco_oer_dc');
    $svg = file_get_contents($theme_path . '/images/icons.svg');
    $variables['svg_icons'] = $svg;
}
