#!/usr/bin/env bash
set -euo pipefail

: "${APP_ENV:?APP_ENV is required}"
: "${DB_DATABASE:?DB_DATABASE is required}"

[[ "$APP_ENV" == "testing" ]] || { echo 'DR exercise requires APP_ENV=testing.' >&2; exit 64; }
[[ "$DB_DATABASE" =~ ^schema_admin_testing_[a-z0-9_]*dr$ ]] || { echo 'DR exercise requires an isolated schema_admin_testing_*dr database.' >&2; exit 64; }
[[ "$DB_DATABASE" != "schema_admin" ]] || { echo 'Protected database refused.' >&2; exit 64; }

mode="${1:---validate-only}"
if [[ "$mode" == "--validate-only" ]]; then
    bash -n scripts/operations/backup-database.sh
    bash -n scripts/operations/restore-verify.sh
    docker compose -f compose.yml -f docker-compose.prod.yml -f docker-compose.dr.yml config --quiet
    echo "DR orchestration guard passed for ${DB_DATABASE}; no dependency or database was changed."
    exit 0
fi

[[ "$mode" == "--execute" ]] || { echo 'Use --validate-only or --execute.' >&2; exit 64; }
: "${DR_EXERCISE_CONFIRM_DATABASE:?DR_EXERCISE_CONFIRM_DATABASE is required}"
[[ "$DR_EXERCISE_CONFIRM_DATABASE" == "$DB_DATABASE" ]] || { echo 'DR exercise confirmation must exactly match DB_DATABASE.' >&2; exit 64; }

echo 'Execution is intentionally orchestrated by the operator runbook so each destructive testing-only step has captured evidence.'
