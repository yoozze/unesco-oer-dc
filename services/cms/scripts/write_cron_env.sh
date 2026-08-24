#! /bin/bash
# Generate cron env from the current container environment.
# Used by the CMS container crontab (services/cms/config/crontab), not host backups.
# Cron does not inherit Docker Compose env vars, so this must run at container start.
#
# Writes outside scripts/ so a bind-mount of scripts/ cannot spill secrets into git.

set -euo pipefail

OUT="${1:-/opt/drupal/cron_env.sh}"

vars=(
    ENV
    PROJECT_BASE_URL
    DRUPAL_HASH_SALT
    DB_NAME
    DB_USER
    DB_PASSWORD
    DB_PORT
    ACCOUNT_MAIL
    SMTP_HOST
    SMTP_PORT
    SMTP_PROTOCOL
    SMTP_AUTOTLS
    SMTP_USERNAME
    SMTP_PASSWORD
    SMTP_FROM
    SMTP_FROMNAME
    EVENTREGISTRY_API_KEY
)

{
    echo '#! /bin/bash'
    echo '# Auto-generated at container start — do not edit.'
    for var in "${vars[@]}"; do
        # Expand indirectly; empty if unset.
        printf 'export %s=%q\n' "$var" "${!var-}"
    done
    echo 'export PATH=/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin'
    echo 'export COLUMNS=72'
} > "$OUT"

chmod +x "$OUT"
