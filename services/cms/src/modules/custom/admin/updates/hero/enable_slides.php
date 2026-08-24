<?php

/**
 * @file
 * Set field_enabled = 1 on existing hero_slide paragraphs with an empty value.
 *
 * Idempotent: only updates empty field_enabled values.
 *
 * Manual: drush php:script modules/custom/admin/updates/hero/enable_slides.php
 */

$storage = \Drupal::entityTypeManager()->getStorage('paragraph');
$ids = $storage->getQuery()
    ->accessCheck(FALSE)
    ->condition('type', 'hero_slide')
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

$message = "Set field_enabled=1 on $updated hero_slide paragraph(s).";
print $message . PHP_EOL;

return $message;
