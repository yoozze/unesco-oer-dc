<?php

/**
 * Implements theme hooks for formatter.
 */

function unesco_oer_dc_theme_suggestions_image_formatter_alter(&$suggestions, &$variables) {
    $block_name = &$variables['item_attributes']['data-block'] ?? null;
    if (!empty($block_name)) {
        $suggestions[] = implode('__', [
            'image_formatter',
            clean_suggetion($block_name)
        ]);
    }
}
