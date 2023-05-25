<?php

/**
 * Implements theme hooks for layout.
 */

function unesco_oer_dc_preprocess_layout__onecol(&$variables) {
    $classes = &get_classes($variables);

    // Custom sections
    foreach ($variables['content']['content'] as $key => $content) {
        if (is_array($content) && array_key_exists('#derivative_plugin_id', $content)) {
            $block_name = get_block_name($content['#derivative_plugin_id']);
            if (!empty($block_name)) {
                $classes[] = 'c-section--' . $block_name;
                $variables['block_names'][] = $block_name;
            }
        }
    }
}
