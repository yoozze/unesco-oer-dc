<?php

/**
 * Implements theme hooks for views_view.
 */

function unesco_oer_dc_theme_suggestions_views_view_alter(&$suggestions, &$variables) {
    // global $content_type_views;
    $content_type_views = [
        'news_view',
        'events_view',
        'resources_view',
        'activities_view',
        'updates_view'
    ];
    $current_display = $variables['view']->current_display;
    if (in_array($current_display, $content_type_views)) {
        $suggestions[] = 'views_view__content_listing';
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
