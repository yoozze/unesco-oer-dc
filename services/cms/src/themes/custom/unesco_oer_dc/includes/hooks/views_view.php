<?php

/**
 * Implements theme hooks for views_view.
 */

use Drupal\taxonomy\Entity\Term;

function unesco_oer_dc_theme_suggestions_views_view_alter(&$suggestions, &$variables) {
    // global $content_type_views;
    $content_type_views = [
        'news_view',
        'events_view',
        'resources_view',
        'activities_view',
        'updates_view',
        'users_view'
    ];
    $current_display = $variables['view']->current_display;
    if (in_array($current_display, $content_type_views)) {
        $suggestions[] = 'views_view__content_listing';
    }
}

function unesco_oer_dc_preprocess_views_view(&$variables) {
    $current_display = $variables['view']->current_display;
    $variables['content_type'] = str_replace('_view', '', $current_display);

    if ($current_display === 'dubai_declaration_view') {
        $terms = \Drupal::entityTypeManager()->getStorage('taxonomy_term')->loadTree('resource_category');
        $active_term = \Drupal::routeMatch()->getParameter('taxonomy_term');
        $active_term_id = $active_term ? $active_term->id() : null;
        $terms_map = [];
        foreach ($terms as $term) {
            $term_id = $term->tid;
            $term_name = $term->name;
            $term_depth = $term->depth;
            $term_parent = $term->parents[0] ?? null;
            $term_description = null;
            if ($term_id === $active_term_id || $term_depth === 0) {
                $term_data = Term::load($term_id);
                $term_description = $term_data->getDescription();
            }
            $term_url = \Drupal::service('path_alias.manager')->getAliasByPath('/taxonomy/term/' . $term_id);
            $term_value = [
                'name' => $term_name,
                'description' => $term_description,
                'url' => $term_url,
                'active' => $term_id === $active_term_id,
                'depth' => $term_depth,
                'parent' => $term_parent,
                'children' => []
            ];
            $terms_map[$term_id] = $term_value;
        }

        foreach ($terms_map as $term_id => &$term) {
            if (!empty($term['parent']) && !empty($terms_map[$term['parent']])) {
                $parent = &$terms_map[$term['parent']];
                if (empty($parent['children'])) {
                    $parent['children'] = [];

                    // If first child, set parent's url to first child's url
                    if ($term['depth'] > 1) {
                        $parent['url'] = $term['url'];
                    }
                }

                $parent['children'][] = $term_id;

                // If child is active, set parent as active
                if ($term['active']) {
                    $parent['active'] = true;
                    $term_data = Term::load($term['parent']);
                    $parent['description'] = $term_data->getDescription();
                }
            }
        }

        $variables['terms'] = $terms_map;
        $variables['active_term_id'] = $active_term_id;

        // Invalidate cache if taxonomy term changes
        $variables['view']->element['#cache']['tags'][] = 'taxonomy_term_list';
        $variables['#cache']['tags'][] = 'taxonomy_term_list';
    }
}

function unesco_oer_dc_theme_suggestions_views_view_unformatted_alter(&$suggestions, &$variables) {
    // if (
    //     !empty($variables['rows']) &&
    //     !empty($variables['rows'][0]) &&
    //     !empty($variables['rows'][0]['#block_name'])
    // ) {
    //     $suggestions[] = 'views_view_unformatted__' . clean_suggetion($variables['rows'][0]['#block_name']);
    // }

    // $current_display = $variables['view']->current_display;
    // if (in_array($current_display, ['news_view', 'events_view', 'resources_view', 'activities_view'])) {
    //     $suggestions[] = 'views_view_unformatted__content_listing__card';
    // }
}

function unesco_oer_dc_preprocess_views_view_unformatted(&$variables) {
    $current_display = $variables['view']->current_display;

    if ($current_display === 'updates_view') {
        $rows = $variables['rows'];
        $groups = [];
        foreach ($rows as $row) {
            $node = $row['content']['#node'];
            $values = $node->toArray();
            $created = $values['created'][0]['value'];
            $year = date('Y', $created);
            if (empty($groups[$year])) {
                $groups[$year] = [];
            }

            $groups[$year][] = [
                'created' => $created,
                'row' => $row
            ];
        }

        // Sort the groups by year descending
        krsort($groups);

        // Sort the items by created date ascending
        foreach ($groups as $year => &$items) {
            usort($items, function ($a, $b) {
                return $a['created'] - $b['created'];
            });
        }

        $variables['groups'] = $groups;
    }
}

function unesco_oer_dc_theme_suggestions_views_view_fields_alter(&$suggestions, &$variables) {
    $current_display = $variables['view']->current_display;
    if (in_array($current_display, ['latest_news_view', 'news_view'])) {
        $suggestions[] = 'views_view_fields__content_item__news_card';
    }

    if (in_array($current_display, ['upcoming_events_view', 'events_view'])) {
        $suggestions[] = 'views_view_fields__content_item__event_card';
    }

    if (in_array($current_display, ['resources_view'])) {
        $suggestions[] = 'views_view_fields__content_item__resource_card';
    }

    if (in_array($current_display, ['activities_view'])) {
        $suggestions[] = 'views_view_fields__content_item__activity_card';
    }

    // if (in_array($current_display, ['dubai_declaration_view'])) {
    //     $suggestions[] = 'views_view_fields__dubai_declaration';
    // }
}

function unesco_oer_dc_preprocess_views_view_fields(&$variables) {
    // $view_id = $variables['view']->id();
    // if ($view_id === 'dubai_declaration') {

    //     $variables['title'] = $variables['row']->_entity->get('title')->value;

    //     $content_type = $variables['row']->_entity->get('field_media_type')->entity->name->value;
    //     $variables['content_type'] = $content_type;

    //     $file = $variables['row']->_entity->get('field_file')->entity;
    //     $variables['file'] = $file;

    //     $url = $variables['row']->_entity->get('field_url')->uri;
    //     $variables['url'] = $url;

    //     // $variables['raw_value'] = $variables['row']->_entity->get('field_name')->value;
    //     $variables['raw_value'] = 'test';
    // }
}

function unesco_oer_dc_theme_suggestions_views_view_field_alter(&$suggestions, &$variables) {
    $field = $variables['field']->field;
    $current_display = $variables['view']->current_display;
    $suggestion = 'views_view_field__' . clean_suggetion($field);

    if (in_array($current_display, [
        'latest_news_view',
        'news_view',
        'upcoming_events_view',
        'events_view',
        'resources_view',
        'activities_view'
    ])) {
        $suggestion .= '__content_item__card';
    }

    $suggestions[] = $suggestion;
}

function unesco_oer_dc_preprocess_views_view_field(&$variables) {
    if (in_array($variables['field']->field, ['field_image', 'field_logo']) && !empty($variables['output'])) {
        $variables['image'] = get_media_image(
            $variables['output']->__toString(),
            'grid_item'
        );
    }
}
