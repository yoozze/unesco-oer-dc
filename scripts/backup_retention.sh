#!/bin/bash
# backup_retention.sh
#
# Host-side retention for archives created by: run.sh --archive-dump
# Scheduled from the HOST crontab (configs/crontab), not the CMS container crontab.
#
# Retention policy:
# - Keep all daily backups from the last 7 days
# - For older backups, keep one weekly backup per week (Sunday)
#   indefinitely, and delete everything else
#
# Expected backup filename format: YYYYMMddThhmmss.tar.gz

set -euo pipefail

SCRIPT_DIR=$(cd "$(dirname "$(readlink -f "$0")")" && pwd)
BACKUP_DIR=$(readlink -f "$SCRIPT_DIR/../archive")
RETENTION_DAYS=7

if [ ! -d "$BACKUP_DIR" ]; then
    echo "Backup directory '$BACKUP_DIR' does not exist!"
    exit 1
fi

current_epoch=$(date +%s)

# Parse YYYYMMdd from a backup filename into epoch seconds.
# Prints epoch on success; returns non-zero on failure.
backup_date_to_epoch() {
    local filename="$1"
    local backup_date=${filename%%T*}

    if [[ ! $backup_date =~ ^[0-9]{8}$ ]]; then
        return 1
    fi

    date -d "${backup_date:0:4}-${backup_date:4:2}-${backup_date:6:2}" +%s 2>/dev/null
}

shopt -s nullglob

for backup_file in "$BACKUP_DIR"/*.tar.gz; do
    filename=$(basename "$backup_file")

    if ! backup_epoch=$(backup_date_to_epoch "$filename"); then
        echo "Skipping file with unexpected name format: $filename"
        continue
    fi

    diff_days=$(( (current_epoch - backup_epoch) / 86400 ))

    if [ "$diff_days" -lt "$RETENTION_DAYS" ]; then
        echo "Keeping recent backup: $filename"
        continue
    fi

    # Older than 7 days: keep only Sunday backups (one per week, long-term).
    backup_day_of_week=$(date -d "@${backup_epoch}" +%u)

    if [ "$backup_day_of_week" -eq 7 ]; then
        echo "Keeping weekly backup (Sunday): $filename"
        continue
    fi

    echo "Deleting old backup: $filename"
    rm -f "$backup_file"
done

shopt -u nullglob
