<?php

/**
 * @file
 * Update Join banner CTA URL to the KoboToolbox membership form.
 *
 * Idempotent. Updates EN and FR translations of the reusable banner block.
 *
 * Manual: drush php:script modules/custom/admin/updates/join_banner/update_join_link.php
 */

const ADMIN_JOIN_BANNER_UUID = '63441fa7-a1ec-4ddb-8dae-898bae0335ab';
const ADMIN_JOIN_BANNER_URL = 'https://ee.kobotoolbox.org/x/TMCEsHH7';

$entity_repository = \Drupal::service('entity.repository');

/** @var \Drupal\block_content\BlockContentInterface|null $block */
$block = $entity_repository->loadEntityByUuid('block_content', ADMIN_JOIN_BANNER_UUID);
if (!$block) {
    throw new \RuntimeException('Join banner block content is missing (uuid ' . ADMIN_JOIN_BANNER_UUID . ').');
}

if (!$block->hasField('field_link')) {
    throw new \RuntimeException('Join banner is missing field_link. Run config:import first.');
}

$messages = [];
$languages = array_merge(['en'], array_keys($block->getTranslationLanguages(FALSE)));

foreach (array_unique($languages) as $langcode) {
    $translation = $langcode === $block->language()->getId()
        ? $block
        : ($block->hasTranslation($langcode) ? $block->getTranslation($langcode) : NULL);

    if (!$translation) {
        continue;
    }

    $current = $translation->get('field_link')->getValue();
    $title = $current[0]['title'] ?? 'Join';
    $uri = $current[0]['uri'] ?? '';

    if ($uri === ADMIN_JOIN_BANNER_URL) {
        $messages[] = "Join banner link already up to date ($langcode).";
        continue;
    }

    $translation->set('field_link', [
        'uri' => ADMIN_JOIN_BANNER_URL,
        'title' => $title,
        'options' => $current[0]['options'] ?? [],
    ]);
    $translation->save();
    $messages[] = "Updated Join banner link ($langcode).";
}

return implode(' ', $messages);
