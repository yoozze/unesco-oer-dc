<?php

use Drupal\Core\Url;
use Drupal\Core\Routing\RouteMatchInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Implements theme hooks for page.
 */

function unesco_oer_dc_theme_suggestions_page_alter(&$suggestions, &$variables) {
    // Get path
    $path = \Drupal::service('path.current')->getPath();

    // Get URL alias
    $alias = \Drupal::service('path_alias.manager')->getAliasByPath($path);

    // Get slug
    $path_items = explode('/', trim($alias, '/'));
    $slug = end($path_items);
    $variables['slug'] = $slug;

    // Add page suggestion
    $suggestions[] = 'page__' . clean_suggetion($slug);
}

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
        $variables['page']['footer_bottom'] = [
            '#region' => 'footer_bottom',
            '#sorted' => true,
            '#theme_wrappers' => ['region'],
            '#site_name' => \Drupal::config('system.site')->get('name')
        ];
    }

    // Get path
    $path = \Drupal::service('path.current')->getPath();

    // Get URL alias
    $alias = \Drupal::service('path_alias.manager')->getAliasByPath($path);

    // Get slug
    $path_items = explode('/', trim($alias, '/'));
    $slug = end($path_items);
    $variables['slug'] = $slug;

    // Get additional data for specific pages
    if ($slug === 'observatory') {
        // Get name of areas_of_action vocabulary
        $vocabulary = \Drupal::entityTypeManager()->getStorage('taxonomy_vocabulary')->load('areas_of_action');

        // Get all items of areas_of_action taxonomy
        $terms = \Drupal::entityTypeManager()->getStorage('taxonomy_term')->loadTree('areas_of_action');
        $variables['areas_of_action'] = [
            'vocabulary' => $vocabulary,
            'terms' => $terms
        ];

        // Get area and view from query string
        $area = max(1, min(5, intval(\Drupal::request()->query->get('area') ?? '1')));
        $view = \Drupal::request()->query->get('view');
        if (!in_array($view, ['news', 'dashboard'])) {
            $view = 'news';
        }
        $variables['get'] = [
            'area' => $area,
            'view' => $view
        ];

        // Invalidate cache when query string changes
        $variables['page']['#cache']['contexts'][] = 'url.query_args';
        $variables['page']['#cache']['contexts'][] = 'url.query_args:area';
        $variables['page']['#cache']['contexts'][] = 'url.query_args:view';
    }
}
