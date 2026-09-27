#!/usr/bin/env bash
#
# Pull the production database dump and files into this checkout.
#
# The dump is written to ~/backups/elaintehtaat on the server, outside the
# web-accessible ~/public_html tree, then imported into the local Docker site.
# The local database and files are REPLACED with the production copies.
#
# Usage: scripts/sync-prod.sh [--db-only | --files-only]

set -euo pipefail

SSH_USER="${SSH_USER:-}"
SSH_HOST="${SSH_HOST:-}"
SSH_PORT="${SSH_PORT:-22}"

REMOTE_ROOT='~/public_html'
REMOTE_BACKUPS='~/backups/elaintehtaat'
REMOTE_DUMP="$REMOTE_BACKUPS/sync.sql.gz"
REMOTE_DRUSH="/usr/bin/php85 $REMOTE_ROOT/current/vendor/drush/drush/drush.php --root=$REMOTE_ROOT/current/web"

LOCAL_ROOT="$(cd "$(dirname "$0")/.." && pwd)"
LOCAL_DUMP="$LOCAL_ROOT/dumps/prod.sql.gz"

sync_db=1
sync_files=1
case "${1:-}" in
  --db-only) sync_files=0 ;;
  --files-only) sync_db=0 ;;
  '') ;;
  *) echo "Usage: $0 [--db-only | --files-only]" >&2; exit 1 ;;
esac

remote() {
  ssh -p "$SSH_PORT" "$SSH_USER@$SSH_HOST" "$@"
}

rsync_pull() {
  rsync -az -e "ssh -p $SSH_PORT" "$@"
}

if (( sync_db )); then
  echo "Dumping production database..."
  remote "mkdir -p $REMOTE_BACKUPS && umask 077 && $REMOTE_DRUSH sql:dump --gzip --structure-tables-key=common --result-file=${REMOTE_DUMP%.gz}"

  echo "Downloading database dump..."
  mkdir -p "$(dirname "$LOCAL_DUMP")"
  rsync_pull "$SSH_USER@$SSH_HOST:$REMOTE_DUMP" "$LOCAL_DUMP"
  remote "rm -f $REMOTE_DUMP"

  echo "Replacing local database..."
  (cd "$LOCAL_ROOT" && docker compose exec -T app drush sql:drop -y)
  gunzip -c "$LOCAL_DUMP" | (cd "$LOCAL_ROOT" && docker compose exec -T app drush sql:cli)
  (cd "$LOCAL_ROOT" && docker compose exec -T app drush cache:rebuild)
fi

if (( sync_files )); then
  # Generated assets are rebuilt locally, so skip them.
  echo "Downloading public files..."
  rsync_pull --delete --delete-excluded \
    --exclude=/css --exclude=/js --exclude=/php --exclude=/styles \
    "$SSH_USER@$SSH_HOST:$REMOTE_ROOT/shared/web/sites/default/files/" \
    "$LOCAL_ROOT/web/sites/default/files/"

  echo "Downloading private files..."
  rsync_pull --delete \
    "$SSH_USER@$SSH_HOST:$REMOTE_ROOT/shared/private/" \
    "$LOCAL_ROOT/private/"
fi
