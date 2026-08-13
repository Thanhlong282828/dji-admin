#!/usr/bin/env bash
set -euo pipefail

root_dir="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
workflow="$root_dir/.github/workflows/ci.yml"

for environment in development testing production; do
    grep -q "environment: $environment" "$workflow" || {
        echo "Missing GitHub environment: $environment" >&2
        exit 1
    }
done

if grep -En '(password|token|secret|private_key):[[:space:]]+[A-Za-z0-9+/=_-]{12,}' "$workflow"; then
    echo 'Possible hard-coded credential in workflow.' >&2
    exit 1
fi

for required in \
    'phpstan analyse app Modules --level=10' \
    'php artisan test' \
    'pint --test' \
    'view:cache' \
    'npm run build' \
    'composer audit' \
    'npm audit' \
    'scan-secrets.sh' \
    'operations:migration-risk' \
    'github.sha' \
    'steps.build.outputs.digest'; do
    grep -q "$required" "$workflow" || { echo "Missing release gate: $required" >&2; exit 1; }
done

grep -q 'environment: production' "$workflow" || { echo 'Production environment approval boundary is missing.' >&2; exit 1; }
grep -q 'concurrency: production-deployment' "$workflow" || { echo 'Production deployment serialization is missing.' >&2; exit 1; }

docker compose -f "$root_dir/compose.yml" config --quiet
APP_KEY='base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA=' \
APP_DB_DATABASE='vps_ci_validation' \
DB_USERNAME='ci_validation' \
DB_PASSWORD='ci-validation-not-a-secret' \
MYSQL_ROOT_PASSWORD='ci-validation-not-a-secret' \
docker compose -f "$root_dir/compose.yml" -f "$root_dir/docker-compose.prod.yml" config --quiet
echo 'CI/CD static validation passed.'
