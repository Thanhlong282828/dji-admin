#!/usr/bin/env bash
set -euo pipefail

mode="${1:---validate-only}"
artifact="${2:-}"
: "${APP_ENV:?APP_ENV is required}"
: "${DB_DATABASE:?DB_DATABASE is required}"
[[ "$APP_ENV" == "staging" ]] || { echo 'Restore rehearsal requires APP_ENV=staging.' >&2; exit 64; }
[[ "$DB_DATABASE" == *_staging_restore ]] || { echo 'Target database must end in _staging_restore.' >&2; exit 64; }
[[ "$DB_DATABASE" != schema_admin ]] || { echo 'Protected operating database refused.' >&2; exit 64; }
[[ "$DB_DATABASE" =~ ^[A-Za-z0-9_]+$ ]] || { echo 'DB_DATABASE contains unsupported characters.' >&2; exit 64; }
for executable in mysql age gzip; do command -v "$executable" >/dev/null || { echo "$executable is required." >&2; exit 69; }; done
mysql_version="$(mysql --version)"
[[ "$mysql_version" =~ [[:space:]]Ver[[:space:]]8\.4\. ]] || { echo 'Restore requires a pinned MySQL 8.4 client.' >&2; exit 69; }

if [[ "$mode" == "--validate-only" ]]; then echo "Restore guard passed for '${DB_DATABASE}'; no database connection or write occurred."; exit 0; fi
[[ "$mode" == "--execute" ]] || { echo 'Use --validate-only or --execute.' >&2; exit 64; }
[[ "${RESTORE_CONFIRM_DATABASE:-}" == "$DB_DATABASE" ]] || { echo 'RESTORE_CONFIRM_DATABASE must exactly match DB_DATABASE.' >&2; exit 64; }
[[ -f "$artifact" && -f "$artifact.sha256" ]] || { echo 'Encrypted artifact and checksum are required.' >&2; exit 66; }
: "${BACKUP_AGE_IDENTITY_FILE:?BACKUP_AGE_IDENTITY_FILE is required}"
: "${BACKUP_MANIFEST_FILE:?BACKUP_MANIFEST_FILE is required}"
: "${MYSQL_DEFAULTS_FILE:?MYSQL_DEFAULTS_FILE is required}"
: "${DB_HOST:?DB_HOST is required}"
: "${EXPECTED_STAGING_SERVER_UUID:?EXPECTED_STAGING_SERVER_UUID is required}"
: "${PRODUCTION_SERVER_UUID:?PRODUCTION_SERVER_UUID is required}"
: "${EXPECTED_SOURCE_DATABASE:?EXPECTED_SOURCE_DATABASE is required}"
[[ "${RESTORE_CONFIRM_SERVER_UUID:-}" == "$EXPECTED_STAGING_SERVER_UUID" ]] || { echo 'RESTORE_CONFIRM_SERVER_UUID must exactly match EXPECTED_STAGING_SERVER_UUID.' >&2; exit 64; }
[[ -f "$BACKUP_AGE_IDENTITY_FILE" && -f "$MYSQL_DEFAULTS_FILE" && -f "$BACKUP_MANIFEST_FILE" && -f "$BACKUP_MANIFEST_FILE.sha256" ]] || { echo 'Protected key, MySQL option and manifest files are required.' >&2; exit 66; }
if [[ "$(uname -s)" == "Darwin" ]]; then
    identity_permissions="$(stat -f '%Lp' "$BACKUP_AGE_IDENTITY_FILE")"
    mysql_permissions="$(stat -f '%Lp' "$MYSQL_DEFAULTS_FILE")"
else
    identity_permissions="$(stat -c '%a' "$BACKUP_AGE_IDENTITY_FILE")"
    mysql_permissions="$(stat -c '%a' "$MYSQL_DEFAULTS_FILE")"
fi
[[ "$identity_permissions" =~ ^(400|600)$ && "$mysql_permissions" =~ ^(400|600)$ ]] || { echo 'Identity and MySQL option files must use 0400 or 0600 permissions.' >&2; exit 77; }

db_port="${DB_PORT:-3306}"
mysql_args=(--defaults-extra-file="$MYSQL_DEFAULTS_FILE" --host="$DB_HOST" --port="$db_port" --batch --skip-column-names)
actual_database="$(mysql "${mysql_args[@]}" "$DB_DATABASE" --execute='SELECT DATABASE()')"
actual_server_uuid="$(mysql "${mysql_args[@]}" "$DB_DATABASE" --execute='SELECT @@server_uuid')"
[[ "$actual_database" == "$DB_DATABASE" ]] || { echo 'Actual restore database identity mismatch.' >&2; exit 65; }
[[ "$actual_server_uuid" == "$EXPECTED_STAGING_SERVER_UUID" ]] || { echo 'Actual staging MySQL server UUID mismatch.' >&2; exit 65; }
[[ "$actual_server_uuid" != "$PRODUCTION_SERVER_UUID" ]] || { echo 'Restore refused: target server UUID is production.' >&2; exit 65; }
target_tables="$(mysql "${mysql_args[@]}" "$DB_DATABASE" --execute="SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = '${DB_DATABASE}'")"
[[ "$target_tables" == 0 ]] || { echo "Restore target must be empty; found ${target_tables} table(s)." >&2; exit 65; }

expected_checksum="$(awk 'NR == 1 {print $1}' "$artifact.sha256")"
if command -v sha256sum >/dev/null; then actual_checksum="$(sha256sum "$artifact" | awk '{print $1}')"; else actual_checksum="$(shasum -a 256 "$artifact" | awk '{print $1}')"; fi
[[ "$actual_checksum" == "$expected_checksum" ]] || { echo 'Encrypted artifact checksum mismatch.' >&2; exit 65; }
expected_manifest_checksum="$(awk 'NR == 1 {print $1}' "$BACKUP_MANIFEST_FILE.sha256")"
if command -v sha256sum >/dev/null; then actual_manifest_checksum="$(sha256sum "$BACKUP_MANIFEST_FILE" | awk '{print $1}')"; else actual_manifest_checksum="$(shasum -a 256 "$BACKUP_MANIFEST_FILE" | awk '{print $1}')"; fi
[[ "$actual_manifest_checksum" == "$expected_manifest_checksum" ]] || { echo 'Backup manifest checksum mismatch.' >&2; exit 65; }
manifest_database="$(awk -F= '$1 == "database" {print substr($0, index($0, "=") + 1)}' "$BACKUP_MANIFEST_FILE")"
manifest_source_uuid="$(awk -F= '$1 == "source_server_uuid" {print substr($0, index($0, "=") + 1)}' "$BACKUP_MANIFEST_FILE")"
[[ -n "$manifest_database" && -n "$manifest_source_uuid" ]] || { echo 'Backup manifest identity is incomplete.' >&2; exit 65; }
[[ "$manifest_database" == "$EXPECTED_SOURCE_DATABASE" ]] || { echo 'Backup source database does not match approved source database.' >&2; exit 65; }
[[ "$manifest_source_uuid" == "$PRODUCTION_SERVER_UUID" ]] || { echo 'Backup source UUID does not match approved production UUID.' >&2; exit 65; }
age --decrypt --identity "$BACKUP_AGE_IDENTITY_FILE" "$artifact" | gzip -t
started_at="$(date +%s)"
age --decrypt --identity "$BACKUP_AGE_IDENTITY_FILE" "$artifact" | gzip -dc | mysql --defaults-extra-file="$MYSQL_DEFAULTS_FILE" --host="$DB_HOST" --port="$db_port" "$DB_DATABASE"
mysql "${mysql_args[@]}" "$DB_DATABASE" --execute='SELECT 1 AS restore_verified;'
restored_tables="$(mysql "${mysql_args[@]}" "$DB_DATABASE" --execute="SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = '${DB_DATABASE}' AND table_type = 'BASE TABLE'")"
manifest_tables="$(awk -F= '$1 == "base_tables" {print $2}' "$BACKUP_MANIFEST_FILE")"
[[ "$restored_tables" == "$manifest_tables" ]] || { echo 'Restored table count does not match backup manifest.' >&2; exit 65; }
echo "Restore completed in $(($(date +%s) - started_at)) seconds from source database ${manifest_database}. Migration rehearsal remains separately gated."
