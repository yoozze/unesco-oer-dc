# Admin update scripts

Idempotent data migrations for the `admin` module.

## Deploy order

`drush config:import` then `drush updatedb` (see repo `run.sh --setup`).
Scripts assume required fields/bundles already exist from config.

## Which hook file?

| File                                  | Use when                                                                                 |
| ------------------------------------- | ---------------------------------------------------------------------------------------- |
| **`admin.post_update.php`**           | **Default.** Content / entity data migrations. Named hooks, easy to read.                |
| **`admin.install` (`hook_update_N`)** | Schema-only or rare changes that must run in strict numeric order _before_ post-updates. |

Keep hooks thin. Put real work in `updates/<feature>/*.php` and call:

```php
return admin_run_update_script('feature/do_the_thing.php');
```

## Layout

```text
updates/
  README.md
  hero/
    migrate_slides.php
    enable_slides.php
  news_ingestion/
    seed_news_ingestion_prerequisites.php
```

One concern per file. Group by feature (`hero/`, …).

## Script conventions

1. **Idempotent** — safe to re-run; skip when work is already done.
2. **Fail loud** — throw `\RuntimeException` when required config/fields are missing.
3. **Return a string** — short status for Drush / update UI.
4. **No update IDs in scripts** — hooks call scripts via `admin_run_update_script()`.

## Examples

```php
// admin.post_update.php (preferred)
function admin_post_update_example_backfill(&$sandbox = NULL) {
  return admin_run_update_script('example/backfill.php');
}
```

```php
// admin.install — only if you truly need ordered schema updates
function admin_update_10001() {
  return admin_run_update_script('example/schema_fix.php');
}
```

## Manual run

```bash
drush php:script modules/custom/admin/updates/hero/migrate_slides.php
drush php:script modules/custom/admin/updates/hero/enable_slides.php
```
