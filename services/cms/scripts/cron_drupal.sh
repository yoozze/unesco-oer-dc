#! /bin/bash
set -euo pipefail

# Ensure Drupal env vars exist even when invoked outside crontab.
. /opt/drupal/scripts/cron_env.sh

cd /opt/drupal/web
/opt/drupal/vendor/bin/drush --uri="${PROJECT_BASE_URL}" --quiet maint:status
/opt/drupal/vendor/bin/drush --uri="${PROJECT_BASE_URL}" --quiet cron
