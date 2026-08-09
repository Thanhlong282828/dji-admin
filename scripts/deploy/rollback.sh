#!/usr/bin/env bash
set -euo pipefail

: "${DEPLOY_PATH:?DEPLOY_PATH is required}"
: "${PREVIOUS_APP_IMAGE:?PREVIOUS_APP_IMAGE is required}"
: "${DEPLOY_HEALTH_URL:?DEPLOY_HEALTH_URL is required}"
[[ "$PREVIOUS_APP_IMAGE" =~ ^[A-Za-z0-9._/-]+:[0-9a-f]{40}@sha256:[0-9a-f]{64}$ ]] || { echo 'PREVIOUS_APP_IMAGE must be immutable tag@digest.' >&2; exit 64; }
[[ "${MIGRATION_COMPATIBLE:-false}" == "true" ]] || { echo 'Database rollback is blocked; use a reviewed forward-fix.' >&2; exit 65; }
[[ "${CONFIG_COMPATIBLE:-false}" == "true" ]] || { echo 'Rollback config compatibility is not proven.' >&2; exit 65; }
[[ "${DATABASE_BACKWARD_COMPATIBLE:-false}" == "true" ]] || { echo 'Database backward compatibility is not proven; use forward-fix.' >&2; exit 65; }

cd "$DEPLOY_PATH"
export APP_IMAGE="$PREVIOUS_APP_IMAGE"
docker compose -f compose.yml -f docker-compose.prod.yml up -d --remove-orphans
curl --fail --retry 12 --retry-delay 5 "$DEPLOY_HEALTH_URL/health/ready"
curl --fail --retry 3 --retry-delay 2 "$DEPLOY_HEALTH_URL/health/live"
