<?php

/**
 * @file
 * Seed shared prerequisites for external news ingestion.
 *
 * - Ensures the official OER DC news_sources term (stable UUID).
 * - Backfills field_news_source = oerdc on existing news.
 * - Ensures language terms + ISO 639 codes from languages-iso.csv.
 * - Sets country ISO 3166 codes from countries-iso.csv.
 *
 * Per-source taxonomy terms and author users (e.g. EventRegistry) are created
 * by each source module/plugin, not here.
 *
 * Idempotent. Requires config import first (fields / vocabularies).
 */

use Drupal\field\Entity\FieldConfig;
use Drupal\taxonomy\Entity\Term;

// Stable UUID so field defaults and re-runs resolve the same official term.
const NEWS_INGESTION_OERDC_UUID = '0375530f-1f9d-4606-a9ab-3fba1530eca5';

// Seed CSVs live under web/data (mounted from services/cms/src/data).
$data_dir = DRUPAL_ROOT . '/data/taxonomies';
$messages = [];

// ---------------------------------------------------------------------------
// 1. Official news source term (OER DC). External sources register themselves.
// ---------------------------------------------------------------------------

if (!\Drupal::entityTypeManager()->getStorage('taxonomy_vocabulary')->load('news_sources')) {
    throw new \RuntimeException('Vocabulary news_sources is missing. Run config:import first.');
}

$oerdc_info = [
    'name' => 'OER DC',
    'key' => 'oerdc',
    'description' => 'Official news published on the OER Dynamic Coalition portal.',
];

// Prefer UUID (stable across environments); fall back to field_source_key.
$oerdc = \Drupal::service('entity.repository')->loadEntityByUuid('taxonomy_term', NEWS_INGESTION_OERDC_UUID);
if (!$oerdc) {
    $existing = \Drupal::entityTypeManager()->getStorage('taxonomy_term')->loadByProperties([
        'vid' => 'news_sources',
        'field_source_key' => $oerdc_info['key'],
    ]);
    $oerdc = $existing ? reset($existing) : NULL;
}

if (!$oerdc) {
    $oerdc = Term::create([
        'uuid' => NEWS_INGESTION_OERDC_UUID,
        'vid' => 'news_sources',
        'name' => $oerdc_info['name'],
        'description' => $oerdc_info['description'],
        'field_source_key' => $oerdc_info['key'],
        'status' => 1,
    ]);
    $oerdc->save();
    $messages[] = 'Created OER DC news source term.';
} else {
    if ($oerdc->bundle() !== 'news_sources') {
        throw new \RuntimeException('OER DC UUID exists but is not a news_sources term.');
    }
    // Keep name/key in sync if someone renamed the term in the UI.
    $dirty = FALSE;
    if ($oerdc->get('field_source_key')->value !== $oerdc_info['key']) {
        $oerdc->set('field_source_key', $oerdc_info['key']);
        $dirty = TRUE;
    }

    if ($oerdc->label() !== $oerdc_info['name']) {
        $oerdc->setName($oerdc_info['name']);
        $dirty = TRUE;
    }

    if ($dirty) {
        $oerdc->save();
        $messages[] = 'Updated OER DC news source term.';
    } else {
        $messages[] = 'OER DC news source term already exists.';
    }
}

// New manual news nodes should default to official OER DC.
$field = FieldConfig::loadByName('node', 'news', 'field_news_source');
if (!$field) {
    throw new \RuntimeException('field_news_source is missing on news. Run config:import first.');
}
$default = $field->getDefaultValueLiteral();
$default_uuid = $default[0]['target_uuid'] ?? NULL;
if ($default_uuid !== NEWS_INGESTION_OERDC_UUID) {
    $field->setDefaultValue([['target_uuid' => NEWS_INGESTION_OERDC_UUID]]);
    $field->save();
    $messages[] = 'Set field_news_source default to oerdc.';
}

// ---------------------------------------------------------------------------
// 2. Backfill existing news as official (pre-ingestion content).
// ---------------------------------------------------------------------------

$all_nids = \Drupal::entityQuery('node')
    ->accessCheck(FALSE)
    ->condition('type', 'news')
    ->execute();
$backfilled = 0;
if ($all_nids) {
    /** @var \Drupal\node\NodeInterface[] $nodes */
    $nodes = \Drupal::entityTypeManager()->getStorage('node')->loadMultiple($all_nids);
    foreach ($nodes as $node) {
        if (!$node->hasField('field_news_source') || !$node->get('field_news_source')->isEmpty()) {
            continue;
        }

        $node->set('field_news_source', ['target_id' => $oerdc->id()]);
        $node->save();
        $backfilled++;
    }
}

$messages[] = "Backfilled news source on $backfilled news node(s).";

// ---------------------------------------------------------------------------
// 3. Content languages + ISO codes (for source lang → field_language mapping).
//    Not GUI languages — see data/i18n/gui-languages.csv for those.
// ---------------------------------------------------------------------------

$lang_csv = $data_dir . '/languages-iso.csv';
if (!is_readable($lang_csv)) {
    throw new \RuntimeException("Missing $lang_csv");
}
$lang_created = 0;
$lang_updated = 0;
$fh = fopen($lang_csv, 'r');
// Skip CSV header row.
fgetcsv($fh);
while (($row = fgetcsv($fh)) !== FALSE) {
    if (count($row) < 3) {
        continue;
    }

    [$name, $iso1, $iso2] = $row;
    $name = trim($name);
    if ($name === '') {
        continue;
    }

    $existing = \Drupal::entityTypeManager()->getStorage('taxonomy_term')->loadByProperties([
        'vid' => 'languages',
        'name' => $name,
    ]);
    $term = $existing ? reset($existing) : NULL;
    if (!$term) {
        Term::create([
            'vid' => 'languages',
            'name' => $name,
            'status' => 1,
            'field_iso639_1' => $iso1,
            'field_iso639_2' => $iso2,
        ])->save();
        $lang_created++;
        continue;
    }

    $dirty = FALSE;
    if ($term->get('field_iso639_1')->value !== $iso1) {
        $term->set('field_iso639_1', $iso1);
        $dirty = TRUE;
    }
    if ($term->get('field_iso639_2')->value !== $iso2) {
        $term->set('field_iso639_2', $iso2);
        $dirty = TRUE;
    }
    if ($dirty) {
        $term->save();
        $lang_updated++;
    }
}
fclose($fh);
$messages[] = "Languages: created $lang_created, updated ISO on $lang_updated.";

// ---------------------------------------------------------------------------
// 4. Country ISO codes (for loc concepts → field_country matching).
// ---------------------------------------------------------------------------

$country_csv = $data_dir . '/countries-iso.csv';
if (!is_readable($country_csv)) {
    throw new \RuntimeException("Missing $country_csv");
}
$country_updated = 0;
$country_missing = 0;
$fh = fopen($country_csv, 'r');
fgetcsv($fh);
while (($row = fgetcsv($fh)) !== FALSE) {
    if (count($row) < 3) {
        continue;
    }
    [$name, $iso2, $iso3] = $row;
    $name = trim($name);
    // Skip rows with no code; do not create new country terms here.
    if ($name === '' || $iso2 === '') {
        continue;
    }
    $existing = \Drupal::entityTypeManager()->getStorage('taxonomy_term')->loadByProperties([
        'vid' => 'countries',
        'name' => $name,
    ]);
    $term = $existing ? reset($existing) : NULL;
    if (!$term) {
        // CSV name must match an existing countries taxonomy term.
        $country_missing++;
        continue;
    }
    $dirty = FALSE;
    if ($term->get('field_iso3166_alpha2')->value !== $iso2) {
        $term->set('field_iso3166_alpha2', $iso2);
        $dirty = TRUE;
    }
    if ($term->get('field_iso3166_alpha3')->value !== $iso3) {
        $term->set('field_iso3166_alpha3', $iso3);
        $dirty = TRUE;
    }
    if ($dirty) {
        $term->save();
        $country_updated++;
    }
}
fclose($fh);
$messages[] = "Countries: updated ISO on $country_updated term(s); unmatched CSV rows: $country_missing.";

return implode(' ', $messages);
