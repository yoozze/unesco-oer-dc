#! /bin/bash
# Generate cron_env.sh from the current container environment.
# Cron does not inherit Docker Compose env vars, so this must run at container start.

set -euo pipefail

OUT="${1:-/opt/drupal/scripts/cron_env.sh}"

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
