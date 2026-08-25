<?php

/**
 * @file
 * Post-update hooks for the Admin module.
 *
 * Preferred place for data / content migrations. These run during
 * `drush updatedb` after any hook_update_N. With deploy order
 * config:import → updatedb, required fields and bundles already exist.
 *
 * Keep hooks thin: call admin_run_update_script() and put logic in
 * updates/<feature>/*.php (idempotent, throw on missing config).
 *
 * Do NOT use admin.install hook_update_N for this kind of work unless you
 * need ordered schema changes.
 *
 * @see updates/README.md
 * @see admin_run_update_script()
 * @see admin.install
 */

/**
 * Migrate homepage hero block content into hero_slide paragraphs.
 */
function admin_post_update_migrate_hero_slides(&$sandbox = NULL) {
    return admin_run_update_script('hero/migrate_slides.php');
}

/**
 * Set field_enabled = 1 on existing hero_slide paragraphs with empty values.
 */
function admin_post_update_enable_hero_slides(&$sandbox = NULL) {
    return admin_run_update_script('hero/enable_slides.php');
}

/**
 * Seed shared news-ingestion prerequisites (oerdc term, ISO codes, backfill).
 *
 * Per-source terms/users are created by each source module, not this update.
 */
function admin_post_update_seed_news_ingestion_prerequisites(&$sandbox = NULL) {
  return admin_run_update_script('news_ingestion/seed_news_ingestion_prerequisites.php');
}
