<?php

/**
 * Implements theme hooks for menu.
 */

function unesco_oer_dc_preprocess_menu__account(&$variables) {
    if (\Drupal::currentUser()->isAnonymous()) {
        $variables['show_register_link'] = \Drupal::config('user.settings')->get('register') !== 'admin_only';
    }
}
