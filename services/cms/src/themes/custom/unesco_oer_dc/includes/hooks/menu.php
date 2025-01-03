<?php

/**
 * Implements theme hooks for menu.
 */

function unesco_oer_dc_preprocess_menu__account(&$variables) {
    if (\Drupal::currentUser()->isAnonymous()) {
        $variables['join_link'] = get_join_link();
    }
}
