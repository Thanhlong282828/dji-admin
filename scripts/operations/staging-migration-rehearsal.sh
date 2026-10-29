#!/usr/bin/env bash
set -euo pipefail

mode="${1:---validate-only}"
: "${APP_ENV:?APP_ENV is required}"
: "${DB_DATABASE:?DB_DATABASE is required}"
: "${RELEASE_SHA:?RELEASE_SHA is required}"

[[ "$APP_ENV" == staging ]] || { echo 'Migration rehearsal requires APP_ENV=staging.' >&2; exit 64; }
[[ "$DB_DATABASE" == *_staging_restore ]] || { echo 'Target database must end in _staging_restore.' >&2; exit 64; }
[[ "$RELEASE_SHA" =~ ^[0-9a-f]{40}$ ]] || { echo 'RELEASE_SHA must be a full lowercase Git SHA.' >&2; exit 64; }
[[ "$(git rev-parse HEAD)" == "$RELEASE_SHA" ]] || { echo 'Checked-out source does not match RELEASE_SHA.' >&2; exit 65; }

expected_first='2026_09_20_000105_create_procurement_goods_return_runtime'
expected_last='2026_10_12_000127_create_maintenance_work_order_closeout_runtime'
pending=()
while IFS= read -r migration; do
    pending+=("$migration")
done < <(php artisan migrate:status --pending --no-ansi | grep -oE '2026_[0-9_]+[a-z0-9_]+')

[[ "${#pending[@]}" -eq 23 ]] || { echo "Expected 23 pending migrations; found ${#pending[@]}." >&2; exit 65; }
[[ "${pending[0]}" == "$expected_first" && "${pending[22]}" == "$expected_last" ]] || { echo 'Pending migration boundary does not match 105-127.' >&2; exit 65; }

if [[ "$mode" == --validate-only ]]; then
    echo "Staging migration rehearsal guard passed for 23 migrations (${expected_first} through ${expected_last}); no migration ran."
    exit 0
fi

[[ "$mode" == --execute ]] || { echo 'Use --validate-only or --execute.' >&2; exit 64; }
[[ "${MIGRATION_REHEARSAL_CONFIRM_DATABASE:-}" == "$DB_DATABASE" ]] || { echo 'MIGRATION_REHEARSAL_CONFIRM_DATABASE must exactly match DB_DATABASE.' >&2; exit 64; }
[[ "${MIGRATION_REHEARSAL_CONFIRM_SHA:-}" == "$RELEASE_SHA" ]] || { echo 'MIGRATION_REHEARSAL_CONFIRM_SHA must exactly match RELEASE_SHA.' >&2; exit 64; }
: "${MIGRATION_REHEARSAL_EVIDENCE_DIRECTORY:?MIGRATION_REHEARSAL_EVIDENCE_DIRECTORY is required}"
[[ "$MIGRATION_REHEARSAL_EVIDENCE_DIRECTORY" = /* ]] || { echo 'Evidence directory must be absolute.' >&2; exit 64; }

umask 077
mkdir -p "$MIGRATION_REHEARSAL_EVIDENCE_DIRECTORY"
timestamp="$(date -u +%Y%m%dT%H%M%SZ)"
before="$MIGRATION_REHEARSAL_EVIDENCE_DIRECTORY/${timestamp}-before.txt"
after="$MIGRATION_REHEARSAL_EVIDENCE_DIRECTORY/${timestamp}-after.txt"
php artisan migrate:status --no-ansi > "$before"
started_at="$(date +%s)"
php artisan migrate --force --no-interaction
duration="$(( $(date +%s) - started_at ))"
php artisan migrate:status --no-ansi > "$after"
remaining="$(php artisan migrate:status --pending --no-ansi | grep -cE '2026_[0-9_]+[a-z0-9_]+' || true)"
[[ "$remaining" -eq 0 ]] || { echo "Migration rehearsal left ${remaining} pending migrations." >&2; exit 65; }
printf 'release_sha=%s\ndatabase=%s\nduration_seconds=%s\npending_after=%s\n' "$RELEASE_SHA" "$DB_DATABASE" "$duration" "$remaining" > "$MIGRATION_REHEARSAL_EVIDENCE_DIRECTORY/${timestamp}-summary.txt"
echo "Staging migration rehearsal completed in ${duration} seconds. Application/data validation is still required."
