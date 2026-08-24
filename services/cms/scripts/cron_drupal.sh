#! /bin/bash
# Run Drupal/drush cron inside the CMS container (see services/cms/config/crontab).
# Not used for host website backups (those are configs/crontab + install_crontab.sh).

set -euo pipefail

# Ensure Drupal env vars exist even when invoked outside crontab.
. /opt/drupal/cron_env.sh

cd /opt/drupal/web
/opt/drupal/vendor/bin/drush --uri="${PROJECT_BASE_URL}" --quiet maint:status
/opt/drupal/vendor/bin/drush --uri="${PROJECT_BASE_URL}" --quiet cron
