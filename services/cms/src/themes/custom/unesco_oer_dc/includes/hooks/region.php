<?php

/**
 * Implements theme hooks for region.
 */

function unesco_oer_dc_preprocess_region(&$variables) {
    $classes = &get_classes($variables);
    $classes[] = 'o-region';
}
