#!/bin/bash
# install_crontab.sh
#
# Install or update the HOST backup crontab from configs/crontab.
#
# This manages only the marked block between:
#   # BEGIN unesco-oer-dc
#   # END unesco-oer-dc
# Other jobs in the current user's crontab are left untouched.
#
# This is NOT for Drupal cron. Drupal cron lives in services/cms/config/crontab
# and is baked into the CMS container image.
#
# Usage:
#   scripts/install_crontab.sh              # install / update
#   scripts/install_crontab.sh --print      # show rendered block, do not install
#   scripts/install_crontab.sh --uninstall  # remove managed block only
#   LOG_DIR=/var/log/unesco-oer-dc scripts/install_crontab.sh

set -euo pipefail

SCRIPT_DIR=$(cd "$(dirname "$(readlink -f "$0")")" && pwd)
APP_ROOT=$(cd "$SCRIPT_DIR/.." && pwd)
TEMPLATE="$APP_ROOT/configs/crontab"
MARKER_BEGIN="# BEGIN unesco-oer-dc"
MARKER_END="# END unesco-oer-dc"
LOG_DIR="${LOG_DIR:-/var/log/unesco-oer-dc}"

MODE="install"
case "${1:-}" in
    --print) MODE="print" ;;
    --uninstall) MODE="uninstall" ;;
    "") ;;
    -h|--help)
        sed -n '2,20p' "$0"
        exit 0
        ;;
    *)
        echo "Unknown option: $1" >&2
        echo "Usage: $0 [--print|--uninstall|--help]" >&2
        exit 1
        ;;
esac

if [ ! -f "$TEMPLATE" ]; then
    echo "Template not found: $TEMPLATE" >&2
    exit 1
fi

render_block() {
    # Emit only the managed block from the template, with paths substituted.
    sed \
        -e "s|/path/to/app|$APP_ROOT|g" \
        -e "s|/path/to/log|$LOG_DIR|g" \
        "$TEMPLATE" \
    | awk -v begin="$MARKER_BEGIN" -v end="$MARKER_END" '
        $0 == begin { printing = 1 }
        printing { print }
        $0 == end { printing = 0 }
    '
}

current_crontab() {
    crontab -l 2>/dev/null || true
}

strip_managed_block() {
    awk -v begin="$MARKER_BEGIN" -v end="$MARKER_END" '
        $0 == begin { skipping = 1; next }
        $0 == end { skipping = 0; next }
        !skipping { print }
    '
}

case "$MODE" in
    print)
        render_block
        exit 0
        ;;
    uninstall)
        updated=$(current_crontab | strip_managed_block)
        # Drop trailing blank lines for a tidy crontab.
        updated=$(printf '%s\n' "$updated" | sed -e :a -e '/^\n*$/{$d;N;ba' -e '}')
        if [ -z "${updated//[[:space:]]/}" ]; then
            crontab -r 2>/dev/null || true
            echo "Removed managed block; user crontab is now empty."
        else
            printf '%s\n' "$updated" | crontab -
            echo "Removed managed block from user crontab."
        fi
        exit 0
        ;;
    install)
        mkdir -p "$LOG_DIR"

        block=$(render_block)
        if ! printf '%s\n' "$block" | grep -qxF "$MARKER_BEGIN"; then
            echo "Template is missing marker: $MARKER_BEGIN" >&2
            exit 1
        fi
        if ! printf '%s\n' "$block" | grep -qxF "$MARKER_END"; then
            echo "Template is missing marker: $MARKER_END" >&2
            exit 1
        fi

        existing=$(current_crontab | strip_managed_block)
        # Ensure a blank line between existing jobs and our block when needed.
        if [ -n "${existing//[[:space:]]/}" ]; then
            existing=$(printf '%s\n' "$existing" | sed -e :a -e '/^\n*$/{$d;N;ba' -e '}')
            printf '%s\n\n%s\n' "$existing" "$block" | crontab -
        else
            printf '%s\n' "$block" | crontab -
        fi

        echo "Installed host backup crontab (APP_ROOT=$APP_ROOT, LOG_DIR=$LOG_DIR)."
        echo "Drupal container cron is separate: services/cms/config/crontab"
        ;;
esac
