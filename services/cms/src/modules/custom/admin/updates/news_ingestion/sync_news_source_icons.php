<?php

/**
 * @file
 * Sync news_sources term icons (oerdc + eventregistry).
 *
 * Idempotent. Safe to re-run after updating SVG assets.
 */

/** @var \Drupal\news_ingestion\Service\SourcePrerequisiteService $prerequisites */
$prerequisites = \Drupal::service('news_ingestion.source_prerequisites');

$messages = [];

$prerequisites->ensureSourceTerm([
    'key' => 'oerdc',
    'name' => 'OER DC',
    'description' => 'Official news published on the OER Dynamic Coalition portal.',
    'uuid' => '0375530f-1f9d-4606-a9ab-3fba1530eca5',
    'icon_module' => 'news_ingestion',
    'icon_path' => 'assets/oerdc-icon.svg',
]);
$messages[] = 'Synced oerdc source icon.';

if (\Drupal::moduleHandler()->moduleExists('eventregistry_news')) {
    \Drupal::service('eventregistry_news.source')->ensurePrerequisites();
    $messages[] = 'Synced eventregistry source term, user, and icon.';
}

return implode(' ', $messages);
