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

docker compose -f "$root_dir/compose.yml" config --quiet
APP_KEY='base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA=' \
DB_USERNAME='ci_validation' \
DB_PASSWORD='ci-validation-not-a-secret' \
MYSQL_ROOT_PASSWORD='ci-validation-not-a-secret' \
docker compose -f "$root_dir/compose.yml" -f "$root_dir/docker-compose.prod.yml" config --quiet
echo 'CI/CD static validation passed.'
