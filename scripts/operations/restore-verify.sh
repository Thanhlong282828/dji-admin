#!/usr/bin/env bash
set -euo pipefail

artifact="${1:-}"
: "${DB_DATABASE:?DB_DATABASE is required}"
[[ "$DB_DATABASE" == *_restore_testing ]] || { echo 'Restore verification requires a *_restore_testing database.' >&2; exit 64; }
[[ -f "$artifact" && -f "$artifact.sha256" ]] || { echo 'Backup and checksum files are required.' >&2; exit 66; }
: "${DB_HOST:?DB_HOST is required}"
: "${DB_USERNAME:?DB_USERNAME is required}"
: "${MYSQL_PWD:?MYSQL_PWD is required}"

if command -v shasum >/dev/null; then
    shasum -a 256 -c "$artifact.sha256"
elif command -v sha256sum >/dev/null; then
    sha256sum -c "$artifact.sha256"
else
    echo 'A SHA-256 checksum tool is required.' >&2
    exit 69
fi
gzip -t "$artifact"
gzip -dc "$artifact" | mysql --host="$DB_HOST" --user="$DB_USERNAME" "$DB_DATABASE"
mysql --host="$DB_HOST" --user="$DB_USERNAME" "$DB_DATABASE" -e 'SELECT 1 AS restore_verified;'
echo 'Restore verification passed.'
