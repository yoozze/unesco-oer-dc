#! /bin/bash
# Container entrypoint: materialize cron env from runtime, start cron, then PHP/Apache.

set -euo pipefail

bash /opt/drupal/scripts/write_cron_env.sh /opt/drupal/scripts/cron_env.sh

cron

exec docker-php-entrypoint "$@"
