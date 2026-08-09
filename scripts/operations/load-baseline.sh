#!/usr/bin/env bash
set -euo pipefail

[[ "${APP_ENV:-}" == "testing" ]] || { echo 'APP_ENV must be testing.' >&2; exit 64; }
[[ "${DB_DATABASE:-}" == *_testing ]] || { echo 'DB_DATABASE must end in _testing.' >&2; exit 64; }
: "${BASELINE_URL:?BASELINE_URL is required}"
requests="${BASELINE_REQUESTS:-100}"

start="$(date +%s)"
seq 1 "$requests" | xargs -P "${BASELINE_CONCURRENCY:-10}" -I {} curl --fail --silent --output /dev/null "$BASELINE_URL/health/live"
elapsed="$(( $(date +%s) - start ))"
php artisan operations:baseline --iterations="$requests"
echo "API requests=$requests elapsed_seconds=$elapsed"
