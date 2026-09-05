<?php

/**
 * @file
 * Backfill field_update_date on update nodes from the authored-on (created) date.
 *
 * Idempotent: only fills empty field_update_date values.
 * Uses the site timezone so year/month match the previous Updates listing.
 *
 * Manual: drush php:script modules/custom/admin/updates/update_content/migrate_update_dates.php
 */

use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\node\NodeInterface;

if (!\Drupal::entityTypeManager()->getStorage('field_storage_config')->load('node.field_update_date')) {
    throw new \RuntimeException('Missing field storage node.field_update_date. Import config first.');
}

$storage = \Drupal::entityTypeManager()->getStorage('node');
$ids = $storage->getQuery()
    ->accessCheck(FALSE)
    ->condition('type', 'update')
    ->execute();

$updated = 0;
$skipped = 0;

foreach ($storage->loadMultiple($ids) as $node) {
    if (!$node instanceof NodeInterface || !$node->hasField('field_update_date')) {
        continue;
    }

    if (!$node->get('field_update_date')->isEmpty()) {
        $skipped++;
        continue;
    }

    $date = DrupalDateTime::createFromTimestamp((int) $node->getCreatedTime());
    $node->set('field_update_date', $date->format('Y-m-d'));
    $node->save();
    $updated++;
}

$message = "Migrated field_update_date on $updated update node(s); skipped $skipped already set.";
print $message . PHP_EOL;

return $message;
