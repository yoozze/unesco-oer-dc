<?php

/**
 * Implements theme hooks for page.
 */

function unesco_oer_dc_preprocess_page(&$variables) {
    // $classes = &get_classes($variables);

    // // Add class
    // $classes[] = 'page';

    // // Add SVG icons
    // $theme_path = \Drupal::service('extension.list.theme')->getPath('unesco_oer_dc');
    // $svg = file_get_contents($theme_path . '/images/icons.svg');
    // $variables['svg_icons'] = $svg;

    // // Add search form
    // $search_form = \Drupal::formBuilder()->getForm('Drupal\search\Form\SearchForm');
    // $variables['search_form'] = $search_form;

    // // Add language switcher
    // $language_switcher = \Drupal::formBuilder()->getForm('Drupal\language\Form\LanguageBlockForm');
    // $variables['language_switcher'] = $language_switcher;

    // // Add navigation
    // $navigation = \Drupal::service('renderer')->renderBlock(\Drupal::entityTypeManager()->getStorage('block')->load('mainnavigation'), 'main');
    // $variables['navigation'] = $navigation;

    // // Add footer
    // $footer = \Drupal::service('renderer')->renderBlock(\Drupal::entityTypeManager()->getStorage('block')->load('footer'), 'main');
    // $variables['footer'] = $footer;
    if (empty($variables['page']['footer_bottom'])) {
        $variables['page']['footer_bottom'] = array(
            '#region' => 'footer_bottom',
            '#sorted' => true,
            '#theme_wrappers' => ['region'],
            '#site_name' => \Drupal::config('system.site')->get('name')
        );
    }
}
