<?php

/**
 * @file
 * Set field_enabled = 1 on existing observatory_view paragraphs with an empty value.
 *
 * Idempotent: only updates empty field_enabled values.
 *
 * Manual: drush php:script modules/custom/admin/updates/observatory/enable_views.php
 */

$storage = \Drupal::entityTypeManager()->getStorage('paragraph');
$ids = $storage->getQuery()
    ->accessCheck(FALSE)
    ->condition('type', 'observatory_view')
    ->execute();

$updated = 0;
foreach ($storage->loadMultiple($ids) as $paragraph) {
    if (!$paragraph->hasField('field_enabled')) {
        continue;
    }

    if ($paragraph->get('field_enabled')->isEmpty()) {
        $paragraph->set('field_enabled', 1);
        $paragraph->save();
        $updated++;
    }
}

$message = "Set field_enabled=1 on $updated observatory_view paragraph(s).";
print $message . PHP_EOL;

return $message;
