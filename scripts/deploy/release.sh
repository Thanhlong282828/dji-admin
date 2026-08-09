#!/usr/bin/env bash
set -euo pipefail

environment="${1:-}"
release_sha="${2:-}"

case "$environment" in development|production) ;; *) echo 'Environment must be development or production.' >&2; exit 64 ;; esac
[[ "$release_sha" =~ ^[0-9a-f]{40}$ ]] || { echo 'A full Git SHA is required.' >&2; exit 64; }
: "${DEPLOY_PATH:?DEPLOY_PATH is required}"
: "${CONTAINER_IMAGE:?CONTAINER_IMAGE is required}"

cd "$DEPLOY_PATH"
export APP_IMAGE="${CONTAINER_IMAGE}:${release_sha}"
docker compose -f compose.yml -f docker-compose.prod.yml config --quiet
docker compose -f compose.yml -f docker-compose.prod.yml pull
docker compose -f compose.yml -f docker-compose.prod.yml up -d --remove-orphans
docker compose -f compose.yml -f docker-compose.prod.yml exec -T app php artisan migrate --force
docker compose -f compose.yml -f docker-compose.prod.yml exec -T app php artisan optimize
curl --fail --retry 12 --retry-delay 5 "${DEPLOY_HEALTH_URL:?DEPLOY_HEALTH_URL is required}/health/ready"
