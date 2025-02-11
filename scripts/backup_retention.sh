#!/bin/bash
# backup_retention.sh
#
# This script retains daily backups for the last seven days.
# For backups older than 7 days, it only keeps those created on Sunday (weekly backup)
# and deletes all other backups.
#
# The expected backup filename format is: YYYYMMddThhmmss.tar.gz
#
# Adjust BACKUP_DIR below to point to your backup folder.


# Get the directory where the script is located
SCRIPT_DIR=$(dirname "$(readlink -f "$0")")

# Set the backup directory
BACKUP_DIR="$SCRIPT_DIR/../archive"

# Set retention period (in days)
RETENTION_DAYS=7

# Ensure the backup directory exists.
if [ ! -d "$BACKUP_DIR" ]; then
    echo "Backup directory '$BACKUP_DIR' does not exist!"
    exit 1
fi

# Get the current time in epoch seconds.
current_epoch=$(date +%s)

# Enable nullglob so that the for loop is skipped if no file matches.
shopt -s nullglob

# Loop through backup files matching the naming convention.
for backup_file in "$BACKUP_DIR"/*.tar.gz; do
    filename=$(basename "$backup_file")

    # Expecting filename of form: YYYYMMddThhmmss.tar.gz
    # Extract the date part (first 8 characters: YYYYMMdd).
    backup_date=${filename%%T*}

    # Verify that the extracted date is exactly 8 digits.
    if [[ ! $backup_date =~ ^[0-9]{8}$ ]]; then
        echo "Skipping file with unexpected name format: $filename"
        continue
    fi

    # Convert the backup date to epoch seconds.
    # (Using only the date part is sufficient for our retention calculation.)
    backup_epoch=$(date -d "${backup_date}" +%s 2>/dev/null)
    if [ $? -ne 0 ]; then
        echo "Skipping file with invalid date: $filename"
        continue
    fi

    # Calculate the age in days.
    diff_days=$(( (current_epoch - backup_epoch) / 86400 ))

    if [ $diff_days -lt $RETENTION_DAYS ]; then
        # Keep recent backups (last 7 days).
        echo "Keeping recent backup: $filename"
        continue
    fi

    # For backups older than 7 days, check if it was created on Sunday.
    # Using GNU date: %u returns day of week (1=Monday, ..., 7=Sunday)
    backup_day_of_week=$(date -d "${backup_date}" +%u)

    if [ "$backup_day_of_week" -eq 7 ]; then
        echo "Keeping weekly backup (Sunday): $filename"
        continue
    fi

    # Otherwise, delete the backup.
    echo "Deleting old backup: $filename"
    rm -f "$backup_file"
done

# Disable nullglob when done.
shopt -u nullglob
