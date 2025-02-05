#! /bin/bash

cd /opt/drupal/web && /opt/drupal/vendor/bin/drush --uri=${PROJECT_BASE_URL} --quiet maint:status && /opt/drupal/vendor/bin/drush --uri=${PROJECT_BASE_URL} --quiet cron
