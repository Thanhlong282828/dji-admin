#!/usr/bin/env bash
set -euo pipefail

: "${DEPLOY_PATH:?DEPLOY_PATH is required}"
: "${PREVIOUS_APP_IMAGE:?PREVIOUS_APP_IMAGE is required}"
: "${DEPLOY_HEALTH_URL:?DEPLOY_HEALTH_URL is required}"

cd "$DEPLOY_PATH"
export APP_IMAGE="$PREVIOUS_APP_IMAGE"
docker compose -f compose.yml -f docker-compose.prod.yml up -d --remove-orphans
curl --fail --retry 12 --retry-delay 5 "$DEPLOY_HEALTH_URL/health/ready"
