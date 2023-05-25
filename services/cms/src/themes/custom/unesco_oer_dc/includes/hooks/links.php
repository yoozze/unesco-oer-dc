<?php

/**
 * Implements theme hooks for links.
 */

function unesco_oer_dc_preprocess_links__language_block(&$variables) {
    foreach ($variables['links'] as &$link) {
        $link['link']['#options']['attributes']['class'] = array('c-menu__link');
    }

    $language =  \Drupal::languageManager()->getCurrentLanguage()->getName();
    $variables['language'] = $language;
}
