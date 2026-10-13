#!/usr/bin/env bash
#
# Nightly production database backup, run from cron on the server.
#
# Dumps to ~/backups/elaintehtaat/nightly-<date>.sql.gz
# keeps the most recent $KEEP nightly dumps.
#
# Cron entry:
#   15 3 * * * ~/public_html/current/scripts/backup-db.sh

set -euo pipefail

KEEP=14
BACKUPS="$HOME/backups/elaintehtaat"
ROOT="$HOME/public_html/current"
DRUSH=(/usr/bin/php85 "$ROOT/vendor/drush/drush/drush.php" "--root=$ROOT/web")

umask 077
mkdir -p "$BACKUPS"

target="$BACKUPS/nightly-$(date +%F).sql.gz"
partial="$target.partial"
trap 'rm -f "$partial"' EXIT

# Write to a temporary file so a failed dump never replaces a good one or
# counts towards the retained dumps.
"${DRUSH[@]}" sql:dump --structure-tables-key=common | gzip > "$partial"
mv "$partial" "$target"

ls -1t "$BACKUPS"/nightly-*.sql.gz | tail -n +$((KEEP + 1)) | xargs -r rm -f
