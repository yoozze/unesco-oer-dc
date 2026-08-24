<?php

/**
 * @file
 * Migrate legacy hero block fields into hero_slide paragraphs.
 *
 * Idempotent: skips when field_slides already has items.
 * Requires field_slides / hero_slide config (run config:import first).
 *
 * Manual: drush php:script modules/custom/admin/updates/hero/migrate_slides.php
 */

use Drupal\media\Entity\Media;
use Drupal\paragraphs\Entity\Paragraph;

// Placed "Home page hero" block (see block.block.home_page_hero.yml).
$hero_block_uuid = '063a9069-be33-420e-986b-a69c43aabf2a';
// Stable UUID so re-runs reuse the same background media entity.
$hero_bg_media_uuid = 'a1b2c3d4-e5f6-7890-abcd-ef1234567890';

$messenger = static function (string $message): void {
    print $message . PHP_EOL;
};

// --- Guards: block + field_slides must exist; skip if already migrated. ---
$storage = \Drupal::entityTypeManager()->getStorage('block_content');
$blocks = $storage->loadByProperties(['uuid' => $hero_block_uuid]);
$block = reset($blocks);
if (!$block) {
    throw new \RuntimeException('Hero block not found.');
}

if (!$block->hasField('field_slides')) {
    throw new \RuntimeException('field_slides missing — run config:import before this migration.');
}

if (!$block->get('field_slides')->isEmpty()) {
    $messenger('Hero already has slides — skipping migration.');
    return 'Hero already has slides — skipped.';
}

// --- Background media: reuse by UUID, or import theme background.jpg. ---
$media_storage = \Drupal::entityTypeManager()->getStorage('media');
$existing = $media_storage->loadByProperties(['uuid' => $hero_bg_media_uuid]);
$background_media = reset($existing);

if (!$background_media) {
    $source = DRUPAL_ROOT . '/themes/custom/unesco_oer_dc/images/background.jpg';
    if (!is_readable($source)) {
        throw new \RuntimeException("Background file not readable: $source");
    }

    $directory = 'public://hero';
    \Drupal::service('file_system')->prepareDirectory(
        $directory,
        \Drupal\Core\File\FileSystemInterface::CREATE_DIRECTORY | \Drupal\Core\File\FileSystemInterface::MODIFY_PERMISSIONS
    );
    $destination = $directory . '/background.jpg';
    $data = file_get_contents($source);
    $file = \Drupal::service('file.repository')->writeData(
        $data,
        $destination,
        \Drupal\Core\File\FileSystemInterface::EXISTS_REPLACE
    );
    $file->setPermanent();
    $file->save();

    $background_media = Media::create([
        'bundle' => 'image',
        'uuid' => $hero_bg_media_uuid,
        'name' => 'Hero background',
        'status' => 1,
        'field_media_image' => [
            'target_id' => $file->id(),
            'alt' => 'Hero background',
            'title' => 'Hero background',
        ],
    ]);
    $background_media->save();
    $messenger('Created hero background media mid=' . $background_media->id());
} else {
    $messenger('Reusing hero background media mid=' . $background_media->id());
}

// --- Read legacy block fields when still present (may be gone after cim). ---
$read_text = static function ($entity, string $field_name): ?array {
    if (!$entity->hasField($field_name) || $entity->get($field_name)->isEmpty()) {
        return NULL;
    }

    $item = $entity->get($field_name)->first();
    if ($field_name === 'body' || $field_name === 'field_text') {
        return [
            'value' => $item->value,
            'format' => $item->format ?: 'full_html',
        ];
    }

    return ['value' => $item->value];
};

$build_links = static function ($entity): array {
    $links = [];
    if (!$entity->hasField('field_link') || $entity->get('field_link')->isEmpty()) {
        return $links;
    }

    foreach ($entity->get('field_link') as $item) {
        $links[] = [
            'uri' => $item->uri,
            'title' => $item->title,
            'options' => $item->options ?? [],
        ];
    }

    return $links;
};

$title = $read_text($block, 'field_title');
$text = $read_text($block, 'body');
$links = $build_links($block);
$has_legacy = $title || $text || $links;
if (!$has_legacy) {
    $messenger('Legacy hero fields missing or empty — creating placeholder slide (editors should update copy).');
}

// --- Create EN hero_slide (background + OER branding; copy when available). ---
$paragraph_values = [
    'type' => 'hero_slide',
    'langcode' => 'en',
    'field_enabled' => 1,
    'field_background' => ['target_id' => $background_media->id()],
    'field_aside' => 'oer_branding',
];
if ($title) {
    $paragraph_values['field_title'] = $title['value'];
}
if ($text) {
    $paragraph_values['field_text'] = $text;
}
if ($links) {
    $paragraph_values['field_links'] = $links;
}

$paragraph = Paragraph::create($paragraph_values);
$paragraph->save();
$messenger('Created EN hero_slide id=' . $paragraph->id());

// --- Optional FR translation from legacy FR block fields. ---
if ($block->hasTranslation('fr')) {
    $fr_block = $block->getTranslation('fr');
    if (!$paragraph->hasTranslation('fr')) {
        $fr_values = [];
        $fr_title = $read_text($fr_block, 'field_title');
        $fr_text = $read_text($fr_block, 'body');
        $fr_links = $build_links($fr_block);
        if ($fr_title) {
            $fr_values['field_title'] = $fr_title['value'];
        }
        if ($fr_text) {
            $fr_values['field_text'] = $fr_text;
        }
        if ($fr_links) {
            $fr_values['field_links'] = $fr_links;
        }
        if ($fr_values) {
            $paragraph_fr = $paragraph->addTranslation('fr', $fr_values);
            $paragraph_fr->save();
            $messenger('Created FR translation for hero_slide.');
        }
    }
}

// --- Attach slide; set autoplay defaults only when empty. ---
$block->set('field_slides', [
    [
        'target_id' => $paragraph->id(),
        'target_revision_id' => $paragraph->getRevisionId(),
    ],
]);

if ($block->hasField('field_autoplay_delay') && $block->get('field_autoplay_delay')->isEmpty()) {
    $block->set('field_autoplay_delay', 5000);
}
if ($block->hasField('field_pause_on_hover') && $block->get('field_pause_on_hover')->isEmpty()) {
    $block->set('field_pause_on_hover', 1);
}

// --- Clear legacy fields when they still exist on the entity. ---
foreach (['field_title', 'body', 'field_link'] as $legacy_field) {
    if (!$block->hasField($legacy_field)) {
        continue;
    }

    $block->set($legacy_field, $legacy_field === 'field_link' ? [] : NULL);
    if ($block->hasTranslation('fr')) {
        $fr = $block->getTranslation('fr');
        if ($fr->hasField($legacy_field)) {
            $fr->set($legacy_field, $legacy_field === 'field_link' ? [] : NULL);
        }
    }
}

$block->save();
$messenger('Attached slide to hero block.');
$messenger('Migration complete.');

return 'Migrated homepage hero into hero_slide paragraphs.';
