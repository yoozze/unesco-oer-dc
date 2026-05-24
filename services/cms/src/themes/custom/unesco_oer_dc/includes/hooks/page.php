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

    $view_id = $variables['page']['content']['system_main']['#view_id'] ?? '';
    if ($view_id) {
        $suggestions[] = 'page__view';
        $suggestions[] = 'page__view__' . clean_suggetion($view_id);
        $variables['view_id'] = $view_id;
    }
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
    if ($slug === 'oer-observatory') {
        $vocabulary = \Drupal::entityTypeManager()->getStorage('taxonomy_vocabulary')->load('areas_of_action');
        $variables['areas_of_action'] = [
            'vocabulary' => $vocabulary,
        ];

        $observatory = unesco_oer_dc_observatory_config();
        $selection = unesco_oer_dc_observatory_resolve_selection($observatory);

        $variables['observatory'] = $observatory;
        $variables['observatory_js'] = unesco_oer_dc_observatory_js_config($observatory);
        $variables['observatory_current'] = [
            'view' => $selection['view'],
            'area' => $selection['area'],
            'areas' => $observatory['views'][$selection['view']]['areas'] ?? [],
            'iframe' => unesco_oer_dc_observatory_iframe_for_selection(
                $observatory,
                $selection['view'],
                $selection['area']
            ),
        ];
        $variables['get'] = [
            'area' => $selection['area'],
            'view' => $selection['view'],
        ];

        $node = unesco_oer_dc_observatory_get_node();
        if ($node) {
            $variables['page']['#cache']['tags'][] = 'node:' . $node->id();
        }

        // Invalidate cache when query string changes
        $variables['page']['#cache']['contexts'][] = 'url.query_args';
        $variables['page']['#cache']['contexts'][] = 'url.query_args:area';
        $variables['page']['#cache']['contexts'][] = 'url.query_args:view';
    }
}
