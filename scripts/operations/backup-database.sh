#!/usr/bin/env bash
set -euo pipefail

mode="${1:---validate-only}"
: "${DB_DATABASE:?DB_DATABASE is required}"
: "${BACKUP_DIRECTORY:?BACKUP_DIRECTORY is required}"

command -v mysqldump >/dev/null || { echo 'mysqldump is required.' >&2; exit 69; }
[[ "$BACKUP_DIRECTORY" = /* ]] || { echo 'BACKUP_DIRECTORY must be absolute.' >&2; exit 64; }

if [[ "$mode" == "--validate-only" ]]; then
    echo "Backup configuration valid for database name '${DB_DATABASE}' (no connection made)."
    exit 0
fi

[[ "$mode" == "--execute" ]] || { echo 'Use --validate-only or --execute.' >&2; exit 64; }
[[ "${BACKUP_CONFIRM_DATABASE:-}" == "$DB_DATABASE" ]] || { echo 'BACKUP_CONFIRM_DATABASE must exactly match DB_DATABASE.' >&2; exit 64; }
: "${DB_HOST:?DB_HOST is required}"
: "${DB_USERNAME:?DB_USERNAME is required}"
: "${MYSQL_PWD:?MYSQL_PWD must be provided through the process environment}"

umask 077
mkdir -p "$BACKUP_DIRECTORY"
timestamp="$(date -u +%Y%m%dT%H%M%SZ)"
target="$BACKUP_DIRECTORY/${DB_DATABASE}-${timestamp}.sql.gz"
mysqldump --host="$DB_HOST" --user="$DB_USERNAME" --single-transaction --quick --routines --events --set-gtid-purged=OFF "$DB_DATABASE" | gzip -9 > "$target"
shasum -a 256 "$target" > "$target.sha256"
echo "Backup artifact created: $(basename "$target")"
