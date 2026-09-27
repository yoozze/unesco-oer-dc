<?php

/**
 * @file
 * Ensure homepage Latest Resources section block + Layout Builder placement.
 *
 * Creates the reusable section block (stable UUID used by the theme) with EN/FR
 * labels, then appends a layout_onecol section on the front page after existing
 * sections when the views block is not already present.
 *
 * Idempotent. Requires config import first (views.view.latest_resources, section
 * block type).
 *
 * Manual: drush php:script modules/custom/admin/updates/homepage/add_latest_resources_section.php
 */

use Drupal\block_content\Entity\BlockContent;
use Drupal\layout_builder\Section;
use Drupal\layout_builder\SectionComponent;
use Drupal\node\NodeInterface;
use Drupal\views\Views;

const ADMIN_LATEST_RESOURCES_SECTION_UUID = '35c8cfa4-35bc-4b00-8a02-78be6bf5b2fc';
const ADMIN_LATEST_RESOURCES_VIEW_PLUGIN = 'views_block:latest_resources-latest_resources_view';
const ADMIN_LATEST_RESOURCES_HOME_NID = 223;

$messages = [];

if (!\Drupal::entityTypeManager()->getStorage('block_content_type')->load('section')) {
    throw new \RuntimeException('Block type "section" is missing. Run config:import first.');
}

if (!Views::getView('latest_resources')) {
    throw new \RuntimeException('View latest_resources is missing. Run config:import first.');
}

$entity_repository = \Drupal::service('entity.repository');

/** @var \Drupal\block_content\BlockContentInterface|null $block */
$block = $entity_repository->loadEntityByUuid('block_content', ADMIN_LATEST_RESOURCES_SECTION_UUID);
if ($block) {
    $messages[] = 'Latest Resources section block already exists.';
} else {
    $block = BlockContent::create([
        'type' => 'section',
        'info' => 'Latest Resources',
        'uuid' => ADMIN_LATEST_RESOURCES_SECTION_UUID,
        'langcode' => 'en',
        'status' => 1,
        'reusable' => TRUE,
        'field_name' => ['value' => 'Latest'],
        'field_title' => ['value' => 'Resources'],
    ]);
    $block->save();
    $messages[] = 'Created Latest Resources section block.';
}

// Keep EN labels in sync if the block already existed with empty/different values.
$block = $block->getUntranslated();
if ($block->hasField('field_name') && $block->get('field_name')->value !== 'Latest') {
    $block->set('field_name', 'Latest');
}

if ($block->hasField('field_title') && $block->get('field_title')->value !== 'Resources') {
    $block->set('field_title', 'Resources');
}

if ($block->label() !== 'Latest Resources') {
    $block->setInfo('Latest Resources');
}

$block->save();

if ($block->hasTranslation('fr')) {
    $fr = $block->getTranslation('fr');
} else {
    $fr = $block->addTranslation('fr', [
        'info' => 'Dernières Ressources',
    ]);
    $messages[] = 'Added FR translation for Latest Resources section block.';
}

$fr->set('field_name', 'Dernières');
$fr->set('field_title', 'Ressources');
if ($fr->label() !== 'Dernières Ressources') {
    $fr->setInfo('Dernières Ressources');
}

$fr->save();

$node = \Drupal::entityTypeManager()->getStorage('node')->load(ADMIN_LATEST_RESOURCES_HOME_NID);
if (!$node instanceof NodeInterface) {
    throw new \RuntimeException('Home node ' . ADMIN_LATEST_RESOURCES_HOME_NID . ' is missing.');
}

if (!$node->hasField('layout_builder__layout')) {
    throw new \RuntimeException('Home node is missing layout_builder__layout. Check Layout Builder config.');
}

$list = $node->get('layout_builder__layout');
$sections = $list->getSections();
$already = FALSE;
foreach ($sections as $section) {
    foreach ($section->getComponents() as $component) {
        if ($component->getPluginId() === ADMIN_LATEST_RESOURCES_VIEW_PLUGIN) {
            $already = TRUE;
            break 2;
        }
    }
}

if ($already) {
    $messages[] = 'Homepage Layout Builder already includes Latest Resources.';
} else {
    $uuid_service = \Drupal::service('uuid');
    $section = new Section('layout_onecol');

    $head = new SectionComponent($uuid_service->generate(), 'content', [
        'id' => 'block_content:' . ADMIN_LATEST_RESOURCES_SECTION_UUID,
        'label' => 'Latest Resources',
        'label_display' => '0',
        'provider' => 'block_content',
        'view_mode' => 'full',
        'status' => TRUE,
        'context_mapping' => [],
    ]);
    $head->setWeight(0);
    $section->appendComponent($head);

    $view = new SectionComponent($uuid_service->generate(), 'content', [
        'id' => ADMIN_LATEST_RESOURCES_VIEW_PLUGIN,
        'label' => 'Latest Resources',
        'label_display' => '0',
        'provider' => 'views',
        'views_label' => '',
        'items_per_page' => 'none',
        'context_mapping' => [],
    ]);
    $view->setWeight(1);
    $section->appendComponent($view);

    $list->appendSection($section);
    $node->set('layout_builder__layout', $list->getSections());
    $node->save();
    $messages[] = 'Appended Latest Resources section to homepage Layout Builder.';
}

$message = implode(' ', $messages);
print $message . PHP_EOL;

return $message;
