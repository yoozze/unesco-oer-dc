#! /bin/bash
# Placeholder only — committed to the repo; must never contain real secrets.
#
# At container start, write_cron_env.sh generates /opt/drupal/cron_env.sh
# (outside this scripts/ tree) from Compose/.env. Cron sources that file.
# Kept here so the image has a harmless file if generation has not run yet.

export PATH=/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin
export COLUMNS=72
