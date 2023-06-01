<?php

/**
 * Implements theme hooks for breadcrumb.
 */

function unesco_oer_dc_preprocess_breadcrumb(&$variables) {
    $breadcrumb = &$variables['breadcrumb'];

    if (empty($breadcrumb)) {
        return;
    }

    if (count($breadcrumb) === 1) {
        $breadcrumb = [];
        return;
    }

    if (count($breadcrumb) >= 2 && $breadcrumb[1]['url'] === '/search') {
        $breadcrumb = [
            $breadcrumb[0],
            [
                'text' => $breadcrumb[1]['text'],
                'url' => '',
            ],
        ];
        return;
    }
}
