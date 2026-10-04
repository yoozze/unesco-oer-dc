<?php

/**
 * @file
 * Apply installed schema update so menu link URLs can vary by language.
 *
 * Runtime definition is altered in admin_entity_base_field_info_alter().
 * This script updates the last-installed field storage definition and ensures
 * a base field override has translatable = TRUE (idempotent).
 */

use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Field\Entity\BaseFieldOverride;

$entity_type_id = 'menu_link_content';
$field_name = 'link';
$bundle = 'menu_link_content';

/** @var \Drupal\Core\Entity\EntityFieldManagerInterface $entity_field_manager */
$entity_field_manager = \Drupal::service('entity_field.manager');
$entity_field_manager->clearCachedFieldDefinitions();

$storage_definitions = $entity_field_manager->getFieldStorageDefinitions($entity_type_id);
if (!isset($storage_definitions[$field_name])) {
    throw new \RuntimeException('menu_link_content.link field storage definition is missing.');
}

$storage_definition = $storage_definitions[$field_name];
if (!$storage_definition->isTranslatable()) {
    throw new \RuntimeException('menu_link_content.link is still not marked translatable; ensure admin_entity_base_field_info_alter() is active and caches are cleared.');
}

/** @var \Drupal\Core\Entity\EntityDefinitionUpdateManagerInterface $definition_update_manager */
$definition_update_manager = \Drupal::entityDefinitionUpdateManager();
$installed = $definition_update_manager->getFieldStorageDefinition($field_name, $entity_type_id);

if (!$installed instanceof BaseFieldDefinition || !$installed->isTranslatable()) {
    $definition_update_manager->updateFieldStorageDefinition($storage_definition);
}

$field_definitions = $entity_field_manager->getFieldDefinitions($entity_type_id, $bundle);
if (!isset($field_definitions[$field_name])) {
    throw new \RuntimeException('menu_link_content.link field definition is missing.');
}

$override = BaseFieldOverride::loadByName($entity_type_id, $bundle, $field_name);
if (!$override) {
    $override = $field_definitions[$field_name]->getConfig($bundle);
}

// Persist an override so config export captures Link as translatable
// (storage may already report TRUE via admin_entity_base_field_info_alter()).
if ($override->isNew() || !$override->isTranslatable()) {
    $override->setTranslatable(TRUE)->save();
}

$entity_field_manager->clearCachedFieldDefinitions();
\Drupal::service('entity_type.manager')->clearCachedDefinitions();

return (string) t('Custom menu link Link field is now translatable.');
