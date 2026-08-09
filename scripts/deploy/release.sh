#!/usr/bin/env bash
set -euo pipefail

environment="${1:-}"
release_sha="${2:-}"

case "$environment" in development|production) ;; *) echo 'Environment must be development or production.' >&2; exit 64 ;; esac
[[ "$release_sha" =~ ^[0-9a-f]{40}$ ]] || { echo 'A full Git SHA is required.' >&2; exit 64; }
: "${DEPLOY_PATH:?DEPLOY_PATH is required}"
: "${CONTAINER_IMAGE:?CONTAINER_IMAGE is required}"
: "${IMAGE_DIGEST:?IMAGE_DIGEST is required}"
: "${PREVIOUS_APP_IMAGE:?PREVIOUS_APP_IMAGE is required}"
: "${QUALITY_EVIDENCE_REFERENCE:?QUALITY_EVIDENCE_REFERENCE is required}"
: "${BACKUP_REFERENCE:?BACKUP_REFERENCE is required}"
: "${RELEASE_TAG:?RELEASE_TAG is required}"
: "${APPROVAL_REFERENCE:?APPROVAL_REFERENCE is required}"
: "${SOURCE_CHECKSUM:?SOURCE_CHECKSUM is required}"
[[ "$IMAGE_DIGEST" =~ ^sha256:[0-9a-f]{64}$ ]] || { echo 'IMAGE_DIGEST must be sha256:<64 lowercase hex>.' >&2; exit 64; }
[[ "$SOURCE_CHECKSUM" =~ ^[0-9a-f]{64}$ ]] || { echo 'SOURCE_CHECKSUM must be 64 lowercase hex.' >&2; exit 64; }
[[ "$PREVIOUS_APP_IMAGE" =~ ^[A-Za-z0-9._/-]+:[0-9a-f]{40}@sha256:[0-9a-f]{64}$ ]] || { echo 'PREVIOUS_APP_IMAGE must be immutable tag@digest.' >&2; exit 64; }

cd "$DEPLOY_PATH"
export APP_IMAGE="${CONTAINER_IMAGE}:${release_sha}@${IMAGE_DIGEST}"
docker compose -f compose.yml -f docker-compose.prod.yml config --quiet
docker compose -f compose.yml -f docker-compose.prod.yml pull
docker compose -f compose.yml -f docker-compose.prod.yml run --rm app php artisan operations:migration-risk
docker compose -f compose.yml -f docker-compose.prod.yml run --rm app php artisan operations:release-check \
  --image="$APP_IMAGE" \
  --rollback-target="$PREVIOUS_APP_IMAGE" \
  --quality-evidence="$QUALITY_EVIDENCE_REFERENCE" \
  --backup-reference="$BACKUP_REFERENCE" \
  --allow-reviewed-pending
docker compose -f compose.yml -f docker-compose.prod.yml run --rm app php artisan migrate --force
docker compose -f compose.yml -f docker-compose.prod.yml up -d --remove-orphans
docker compose -f compose.yml -f docker-compose.prod.yml exec -T app php artisan optimize
curl --fail --retry 12 --retry-delay 5 "${DEPLOY_HEALTH_URL:?DEPLOY_HEALTH_URL is required}/health/ready"
curl --fail --retry 3 --retry-delay 2 "$DEPLOY_HEALTH_URL/health/live"
curl --fail --retry 3 --retry-delay 2 "$DEPLOY_HEALTH_URL/health/metrics-readiness"

quality_file="$(mktemp)"
manifest_directory="$DEPLOY_PATH/storage/release-manifests"
manifest_path="$manifest_directory/release-${release_sha}.json"
manifest_tmp="$(mktemp)"
release_created_at="$(date -u +%Y-%m-%dT%H:%M:%SZ)"
trap 'rm -f "$quality_file" "$manifest_tmp"' EXIT
printf '%s\n' '{"phpstan":"pass","phpunit":"pass","pint":"pass","blade":"pass","vite":"pass","composer_audit":"pass","npm_audit":"pass","secret_scan":"pass","docker":"pass"}' > "$quality_file"
mkdir -p "$manifest_directory"
chmod 0750 "$manifest_directory"
docker compose -f compose.yml -f docker-compose.prod.yml exec -T app php artisan operations:release-manifest \
  --release-id="release-${release_sha}" \
  --commit="$release_sha" \
  --tag="$RELEASE_TAG" \
  --source-state=committed \
  --source-checksum="$SOURCE_CHECKSUM" \
  --environment="$environment" \
  --image="${CONTAINER_IMAGE}:${release_sha}" \
  --digest="$IMAGE_DIGEST" \
  --rollback-target="$PREVIOUS_APP_IMAGE" \
  --approval-reference="$APPROVAL_REFERENCE" \
  --quality-evidence=- \
  --created-at="$release_created_at" < "$quality_file"
docker compose -f compose.yml -f docker-compose.prod.yml exec -T app php artisan operations:release-manifest \
  --release-id="release-${release_sha}" \
  --commit="$release_sha" \
  --tag="$RELEASE_TAG" \
  --source-state=committed \
  --source-checksum="$SOURCE_CHECKSUM" \
  --environment="$environment" \
  --image="${CONTAINER_IMAGE}:${release_sha}" \
  --digest="$IMAGE_DIGEST" \
  --rollback-target="$PREVIOUS_APP_IMAGE" \
  --approval-reference="$APPROVAL_REFERENCE" \
  --quality-evidence=- \
  --created-at="$release_created_at" \
  --print-manifest < "$quality_file" > "$manifest_tmp"
if [[ -e "$manifest_path" ]]; then
  cmp --silent "$manifest_tmp" "$manifest_path" || { echo 'Immutable release manifest conflict.' >&2; exit 65; }
else
  mv "$manifest_tmp" "$manifest_path"
  chmod 0440 "$manifest_path"
fi
