#!/usr/bin/env bash
set -euo pipefail

mode="${1:---validate-only}"
: "${APP_ENV:?APP_ENV is required}"
: "${DB_DATABASE:?DB_DATABASE is required}"
: "${BACKUP_DIRECTORY:?BACKUP_DIRECTORY is required}"
: "${BACKUP_AGE_RECIPIENT:?BACKUP_AGE_RECIPIENT is required}"

[[ "$APP_ENV" == "production" ]] || { echo 'Encrypted production backup requires APP_ENV=production.' >&2; exit 64; }
[[ "$DB_DATABASE" =~ ^[A-Za-z0-9_]+$ ]] || { echo 'DB_DATABASE contains unsupported characters.' >&2; exit 64; }
[[ "$BACKUP_DIRECTORY" = /* ]] || { echo 'BACKUP_DIRECTORY must be absolute.' >&2; exit 64; }
[[ "$BACKUP_DIRECTORY" != /tmp && "$BACKUP_DIRECTORY" != /var/tmp ]] || { echo 'BACKUP_DIRECTORY must be a protected persistent path.' >&2; exit 64; }

for executable in mysql mysqldump gzip age; do
    command -v "$executable" >/dev/null || { echo "$executable is required." >&2; exit 69; }
done

dump_version="$(mysqldump --version)"
[[ "$dump_version" != *MariaDB* ]] || { echo 'MariaDB mysqldump is not accepted for the MySQL 8.4 production server; install/use a compatible MySQL client.' >&2; exit 69; }
[[ "$dump_version" =~ [[:space:]]Ver[[:space:]]8\.4\. ]] || { echo 'Backup requires a pinned MySQL 8.4 mysqldump client.' >&2; exit 69; }
mysql_version="$(mysql --version)"
[[ "$mysql_version" =~ [[:space:]]Ver[[:space:]]8\.4\. ]] || { echo 'Backup requires a pinned MySQL 8.4 mysql client.' >&2; exit 69; }

if command -v sha256sum >/dev/null; then checksum_command=(sha256sum); elif command -v shasum >/dev/null; then checksum_command=(shasum -a 256); else echo 'A SHA-256 tool is required.' >&2; exit 69; fi

if [[ "$mode" == "--validate-only" ]]; then
    echo "Encrypted backup prerequisites are present for '${DB_DATABASE}'; no connection or write occurred."
    exit 0
fi

[[ "$mode" == "--execute" ]] || { echo 'Use --validate-only or --execute.' >&2; exit 64; }
[[ "${BACKUP_CONFIRM_ENVIRONMENT:-}" == "production" ]] || { echo 'BACKUP_CONFIRM_ENVIRONMENT must be production.' >&2; exit 64; }
[[ "${BACKUP_CONFIRM_DATABASE:-}" == "$DB_DATABASE" ]] || { echo 'BACKUP_CONFIRM_DATABASE must exactly match DB_DATABASE.' >&2; exit 64; }
: "${DB_HOST:?DB_HOST is required}"
: "${MYSQL_DEFAULTS_FILE:?MYSQL_DEFAULTS_FILE is required}"
: "${EXPECTED_SOURCE_SERVER_UUID:?EXPECTED_SOURCE_SERVER_UUID is required}"
[[ "${BACKUP_CONFIRM_SOURCE_SERVER_UUID:-}" == "$EXPECTED_SOURCE_SERVER_UUID" ]] || { echo 'BACKUP_CONFIRM_SOURCE_SERVER_UUID must exactly match EXPECTED_SOURCE_SERVER_UUID.' >&2; exit 64; }
case "${BACKUP_CONFIRM_WRITE_POLICY:-}" in approved-live-innodb|approved-write-freeze) ;; *) echo 'BACKUP_CONFIRM_WRITE_POLICY must be approved-live-innodb or approved-write-freeze.' >&2; exit 64 ;; esac
[[ -f "$MYSQL_DEFAULTS_FILE" ]] || { echo 'MYSQL_DEFAULTS_FILE must identify a protected readable file.' >&2; exit 66; }
permissions="$(stat -f '%Lp' "$MYSQL_DEFAULTS_FILE" 2>/dev/null || stat -c '%a' "$MYSQL_DEFAULTS_FILE")"
[[ "$permissions" =~ ^(400|600)$ ]] || { echo 'MYSQL_DEFAULTS_FILE permissions must be 0400 or 0600.' >&2; exit 77; }

db_port="${DB_PORT:-3306}"
mysql_args=(--defaults-extra-file="$MYSQL_DEFAULTS_FILE" --host="$DB_HOST" --port="$db_port" --batch --skip-column-names)
actual_database="$(mysql "${mysql_args[@]}" "$DB_DATABASE" --execute='SELECT DATABASE()')"
actual_server_uuid="$(mysql "${mysql_args[@]}" "$DB_DATABASE" --execute='SELECT @@server_uuid')"
source_server_version="$(mysql "${mysql_args[@]}" "$DB_DATABASE" --execute='SELECT VERSION()')"
[[ "$actual_database" == "$DB_DATABASE" ]] || { echo 'Actual database identity mismatch.' >&2; exit 65; }
[[ "$actual_server_uuid" == "$EXPECTED_SOURCE_SERVER_UUID" ]] || { echo 'Actual MySQL server UUID mismatch.' >&2; exit 65; }
non_innodb_tables="$(mysql "${mysql_args[@]}" "$DB_DATABASE" --execute="SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = '${DB_DATABASE}' AND table_type = 'BASE TABLE' AND engine <> 'InnoDB'")"
[[ "$non_innodb_tables" == 0 ]] || { echo "Transaction-consistent backup refused: ${non_innodb_tables} non-InnoDB base table(s) found." >&2; exit 65; }
before_table_count="$(mysql "${mysql_args[@]}" "$DB_DATABASE" --execute="SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = '${DB_DATABASE}' AND table_type = 'BASE TABLE'")"
before_view_count="$(mysql "${mysql_args[@]}" "$DB_DATABASE" --execute="SELECT COUNT(*) FROM information_schema.views WHERE table_schema = '${DB_DATABASE}'")"
before_trigger_count="$(mysql "${mysql_args[@]}" "$DB_DATABASE" --execute="SELECT COUNT(*) FROM information_schema.triggers WHERE trigger_schema = '${DB_DATABASE}'")"
before_routine_count="$(mysql "${mysql_args[@]}" "$DB_DATABASE" --execute="SELECT COUNT(*) FROM information_schema.routines WHERE routine_schema = '${DB_DATABASE}'")"
before_event_count="$(mysql "${mysql_args[@]}" "$DB_DATABASE" --execute="SELECT COUNT(*) FROM information_schema.events WHERE event_schema = '${DB_DATABASE}'")"
before_migration_count="$(mysql "${mysql_args[@]}" "$DB_DATABASE" --execute='SELECT COUNT(*) FROM migrations')"
before_migration_head="$(mysql "${mysql_args[@]}" "$DB_DATABASE" --execute="SELECT COALESCE(MAX(migration), '') FROM migrations")"

umask 077
mkdir -p "$BACKUP_DIRECTORY"
timestamp="$(date -u +%Y%m%dT%H%M%SZ)"
base="$BACKUP_DIRECTORY/${DB_DATABASE}-${timestamp}"
artifact="$base.sql.gz.age"
partial="$artifact.partial"
checksum_path="$artifact.sha256"
manifest_path="$base.manifest"
manifest_checksum_path="$manifest_path.sha256"
completed=false
trap 'if [[ "$completed" != true ]]; then rm -f "$partial" "$artifact" "$checksum_path" "$manifest_path" "$manifest_checksum_path"; fi' EXIT

mysqldump --defaults-extra-file="$MYSQL_DEFAULTS_FILE" --host="$DB_HOST" --port="$db_port" \
    --single-transaction --quick --routines --triggers --events --hex-blob \
    --default-character-set=utf8mb4 --set-gtid-purged=OFF --no-tablespaces "$DB_DATABASE" \
    | gzip -9 \
    | age --recipient "$BACKUP_AGE_RECIPIENT" --output "$partial"

mv "$partial" "$artifact"
artifact_checksum="$("${checksum_command[@]}" "$artifact" | awk '{print $1}')"
printf '%s  %s\n' "$artifact_checksum" "$(basename "$artifact")" > "$checksum_path"
after_table_count="$(mysql "${mysql_args[@]}" "$DB_DATABASE" --execute="SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = '${DB_DATABASE}' AND table_type = 'BASE TABLE'")"
after_view_count="$(mysql "${mysql_args[@]}" "$DB_DATABASE" --execute="SELECT COUNT(*) FROM information_schema.views WHERE table_schema = '${DB_DATABASE}'")"
after_trigger_count="$(mysql "${mysql_args[@]}" "$DB_DATABASE" --execute="SELECT COUNT(*) FROM information_schema.triggers WHERE trigger_schema = '${DB_DATABASE}'")"
after_routine_count="$(mysql "${mysql_args[@]}" "$DB_DATABASE" --execute="SELECT COUNT(*) FROM information_schema.routines WHERE routine_schema = '${DB_DATABASE}'")"
after_event_count="$(mysql "${mysql_args[@]}" "$DB_DATABASE" --execute="SELECT COUNT(*) FROM information_schema.events WHERE event_schema = '${DB_DATABASE}'")"
after_migration_count="$(mysql "${mysql_args[@]}" "$DB_DATABASE" --execute='SELECT COUNT(*) FROM migrations')"
after_migration_head="$(mysql "${mysql_args[@]}" "$DB_DATABASE" --execute="SELECT COALESCE(MAX(migration), '') FROM migrations")"
[[ "$before_table_count|$before_view_count|$before_trigger_count|$before_routine_count|$before_event_count|$before_migration_count|$before_migration_head" == "$after_table_count|$after_view_count|$after_trigger_count|$after_routine_count|$after_event_count|$after_migration_count|$after_migration_head" ]] || {
    echo 'Source structural inventory changed during backup; artifact rejected.' >&2
    exit 65
}
{
    printf 'created_at_utc=%s\n' "$timestamp"
    printf 'database=%s\n' "$DB_DATABASE"
    printf 'host=%s\n' "$DB_HOST"
    printf 'artifact=%s\n' "$(basename "$artifact")"
    printf 'bytes=%s\n' "$(wc -c < "$artifact" | tr -d ' ')"
    printf 'mysqldump_version=%s\n' "$(printf '%s' "$dump_version" | tr '\n' ' ')"
    printf 'content=schema,data,triggers,routines,events\n'
    printf 'encryption=age-recipient\n'
    printf 'off_host_copy=NOT_PERFORMED\n'
    printf 'source_server_uuid=%s\n' "$actual_server_uuid"
    printf 'source_server_version=%s\n' "$source_server_version"
    printf 'write_policy=%s\n' "$BACKUP_CONFIRM_WRITE_POLICY"
    printf 'base_tables=%s\n' "$after_table_count"
    printf 'views=%s\n' "$after_view_count"
    printf 'triggers=%s\n' "$after_trigger_count"
    printf 'routines=%s\n' "$after_routine_count"
    printf 'events=%s\n' "$after_event_count"
    printf 'migration_count=%s\n' "$after_migration_count"
    printf 'migration_head=%s\n' "$after_migration_head"
} > "$manifest_path"
manifest_checksum="$("${checksum_command[@]}" "$manifest_path" | awk '{print $1}')"
printf '%s  %s\n' "$manifest_checksum" "$(basename "$manifest_path")" > "$manifest_checksum_path"
chmod 0400 "$artifact" "$checksum_path" "$manifest_path" "$manifest_checksum_path"
completed=true
echo "Encrypted backup artifact created: $(basename "$artifact")"
echo 'Off-host copy was not performed; it requires separate approval and checksum verification.'
