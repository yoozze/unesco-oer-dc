<?php

/**
 * Implements theme hooks for image.
 */

function unesco_oer_dc_theme_suggestions_image_alter(&$suggestions, &$variables) {
    $block_name = &$variables['attributes']['data-block'] ?? null;
    if (!empty($block_name)) {
        $suggestions[] = implode('__', [
            'image',
            clean_suggetion($block_name)
        ]);
    }
}
