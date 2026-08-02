#! /bin/bash
# Container entrypoint: materialize cron env from runtime, start cron, then PHP/Apache.
#
# The `cron` started here uses services/cms/config/crontab (Drupal/drush only).
# Host website backups use configs/crontab via scripts/install_crontab.sh — not this.

set -euo pipefail

bash /opt/drupal/scripts/write_cron_env.sh /opt/drupal/scripts/cron_env.sh

cron

exec docker-php-entrypoint "$@"
