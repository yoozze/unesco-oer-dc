<?php

/**
 * @file
 * Migrate legacy hero block fields into hero_slide paragraphs.
 *
 * Run: drush php:script modules/custom/admin/migrate_hero_slides.php
 */

use Drupal\file\Entity\File;
use Drupal\media\Entity\Media;
use Drupal\paragraphs\Entity\Paragraph;

const HERO_BLOCK_UUID = '063a9069-be33-420e-986b-a69c43aabf2a';
const HERO_BG_MEDIA_UUID = 'a1b2c3d4-e5f6-7890-abcd-ef1234567890';

$messenger = static function (string $message): void {
    print $message . PHP_EOL;
};

$storage = \Drupal::entityTypeManager()->getStorage('block_content');
$blocks = $storage->loadByProperties(['uuid' => HERO_BLOCK_UUID]);
$block = reset($blocks);
if (!$block) {
    $messenger('Hero block not found.');
    return;
}

if (!$block->hasField('field_slides')) {
    $messenger('field_slides missing — run setup first.');
    return;
}

if (!$block->get('field_slides')->isEmpty()) {
    $messenger('Hero already has slides — skipping migration.');
    return;
}

// Ensure background media exists (from theme asset).
$media_storage = \Drupal::entityTypeManager()->getStorage('media');
$existing = $media_storage->loadByProperties(['uuid' => HERO_BG_MEDIA_UUID]);
$background_media = reset($existing);

if (!$background_media) {
    $source = DRUPAL_ROOT . '/themes/custom/unesco_oer_dc/images/background.jpg';
    if (!is_readable($source)) {
        $messenger("Background file not readable: $source");
        return;
    }

    $directory = 'public://hero';
    \Drupal::service('file_system')->prepareDirectory($directory, \Drupal\Core\File\FileSystemInterface::CREATE_DIRECTORY | \Drupal\Core\File\FileSystemInterface::MODIFY_PERMISSIONS);
    $destination = $directory . '/background.jpg';
    $data = file_get_contents($source);
    $file = \Drupal::service('file.repository')->writeData($data, $destination, \Drupal\Core\File\FileSystemInterface::EXISTS_REPLACE);
    $file->setPermanent();
    $file->save();

    $background_media = Media::create([
        'bundle' => 'image',
        'uuid' => HERO_BG_MEDIA_UUID,
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

$paragraph = Paragraph::create([
    'type' => 'hero_slide',
    'langcode' => 'en',
    'field_title' => $block->get('field_title')->value,
    'field_text' => [
        'value' => $block->get('body')->value,
        'format' => $block->get('body')->format ?: 'full_html',
    ],
    'field_links' => $build_links($block),
    'field_background' => ['target_id' => $background_media->id()],
    'field_aside' => 'oer_branding',
]);
$paragraph->save();
$messenger('Created EN hero_slide id=' . $paragraph->id());

if ($block->hasTranslation('fr')) {
    $fr_block = $block->getTranslation('fr');
    if (!$paragraph->hasTranslation('fr')) {
        $paragraph_fr = $paragraph->addTranslation('fr', [
            'field_title' => $fr_block->get('field_title')->value,
            'field_text' => [
                'value' => $fr_block->get('body')->value,
                'format' => $fr_block->get('body')->format ?: 'full_html',
            ],
            'field_links' => $build_links($fr_block),
        ]);
        $paragraph_fr->save();
        $messenger('Created FR translation for hero_slide.');
    }
}

$block->set('field_slides', [
    [
        'target_id' => $paragraph->id(),
        'target_revision_id' => $paragraph->getRevisionId(),
    ],
]);
$block->set('field_autoplay_delay', 5000);
$block->set('field_pause_on_hover', 1);

// Clear legacy fields so editors use slides only.
$block->set('field_title', NULL);
$block->set('body', NULL);
$block->set('field_link', []);

if ($block->hasTranslation('fr')) {
    $fr = $block->getTranslation('fr');
    $fr->set('field_title', NULL);
    $fr->set('body', NULL);
    $fr->set('field_link', []);
}

$block->save();
$messenger('Attached slide to hero block and cleared legacy fields.');
$messenger('Migration complete.');
