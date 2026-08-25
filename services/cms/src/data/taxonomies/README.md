# Taxonomy seed data

Import with module: [term_trans_ex_im](https://www.drupal.org/project/term_trans_ex_im)

```bash
composer require 'drupal/term_trans_ex_im:^1.0'
drush pm:enable term_trans_ex_im
drush ttei-imp taxonomy-terms.csv
```

## Content-language taxonomy (`vid: languages`)

Document/news language, **not** GUI:

- [`languages-iso.csv`](languages-iso.csv) — Name, ISO639_1, ISO639_2  
  Used by admin update `news_ingestion/seed_news_ingestion_prerequisites.php` and by ER ingestion
  to map source language codes onto taxonomy terms (all listed languages).

## Country ISO codes

- [`countries-iso.csv`](countries-iso.csv) — Name, ISO3166_alpha2, ISO3166_alpha3

## UNESCO country → region

For later ingestion:

- [`../geo/UNESCO-world-regions-sdg.csv`](../geo/UNESCO-world-regions-sdg.csv)

## GUI languages

Portal GUI / interface languages (Drupal configurable languages) are **not**
seeded from this folder. See [`../i18n/gui-languages.csv`](../i18n/gui-languages.csv)
for the planned UNESCO UI set (Arabic, Chinese, English, French, Russian, Spanish).
Only English and French are enabled in Drupal today.
