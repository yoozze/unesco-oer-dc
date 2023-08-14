<?php

/**
 * Implements theme hooks for block.
 */

function unesco_oer_dc_theme_suggestions_block_alter(&$suggestions, &$variables) {
    $uuid = $variables['elements']['#derivative_plugin_id'];
    if (!empty($uuid)) {
        $block = Drupal::service('entity.repository')->loadEntityByUuid('block_content', $uuid);
        if ($block) {
            $block_type = $block->bundle();
            $suggestions[] = 'block__' . clean_suggetion($block_type);
        }
    }

    $block_name = get_block_name($uuid);
    if (!empty($block_name)) {
        $suggestions[] = 'block__' . clean_suggetion($block_name);
        $content = &$variables['elements']['content'];
        $content['#block_name'] = $block_name;
        foreach ($content as $key => &$item) {
            if (str_starts_with($key, '#')) {
                continue;
            }

            if (is_array($item)) {
                $item['#block_name'] = $block_name;

                // if ($key === 'view_build' && !empty($item['#rows'])) {
                //     foreach ($item['#rows'] as &$row) {
                //         $row['#block_name'] = $block_name;
                //         if (!empty($row['#rows'])) {
                //             foreach ($row['#rows'] as &$sub_row) {
                //                 $sub_row['#block_name'] = $block_name;
                //             }
                //         }
                //     }
                // }
            }
        }
    }
}

function unesco_oer_dc_preprocess_block(&$variables) {
    $classes = &get_classes($variables);

    // Navigation block
    if ($variables['base_plugin_id'] === 'system_menu_block') {
        $plugin_id = $variables['derivative_plugin_id'];
        $classes[] = 'c-navigation';
        $classes[] = 'c-navigation--' . str_replace('-navigation', '', $plugin_id);

        if (in_array($plugin_id, ['contact', 'follow-us', 'information', 'categories'])) {
            $classes[] = 'c-navigation--footer';
        }
    }

    // Branding block
    if ($variables['base_plugin_id'] === 'system_branding_block') {
        $classes[] = 'c-site-branding';
    }

    // Language block
    if ($variables['base_plugin_id'] === 'language_block') {
        $classes = ['contextual-region', 'c-dropdown', 'c-dropdown--language'];
        $variables['attributes']['data-options'] = json_encode(['popper' => ['placement' => 'bottom']]);
    }

    // Search block
    if ($variables['base_plugin_id'] === 'search_form_block') {
        $classes = ['c-search-form-block'];
        $variables['content']['actions']['submit']['#icon'] = 'search';
        // $variables['content']['actions']['submit']['#leading_icon'] = 'search';
        // $variables['content']['actions']['submit']['#trailing_icon'] = 'search';
        $variables['content']['keys']['#control_size'] = 'small';
    }

    // Breadcrumb
    if ($variables['base_plugin_id'] === 'system_breadcrumb_block') {
        $classes[] = 'c-page-navigation__breadcrumb';
    }

    // Local tasks
    if ($variables['base_plugin_id'] === 'local_tasks_block') {
        $classes[] = 'c-page-navigation__local-tasks c-navigation c-navigation--local-tasks';
    }

    // Custom block
    $block_name = get_block_name($variables['derivative_plugin_id']);
    if (!empty($block_name)) {
        $variables['block_name'] = $block_name;
    }
}
