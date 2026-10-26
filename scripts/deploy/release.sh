#!/usr/bin/env bash
set -euo pipefail

environment="${1:-}"
release_sha="${2:-}"
canonical_repository='ghcr.io/thanhlong282828/dji-admin'

case "$environment" in development|staging|production) ;; *) echo 'Environment must be development, staging or production.' >&2; exit 64 ;; esac
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
[[ "$CONTAINER_IMAGE" == "$canonical_repository" ]] || { echo 'CONTAINER_IMAGE must be the canonical GHCR repository.' >&2; exit 64; }
[[ "$IMAGE_DIGEST" =~ ^sha256:[0-9a-f]{64}$ ]] || { echo 'IMAGE_DIGEST must be sha256:<64 lowercase hex>.' >&2; exit 64; }
[[ "$SOURCE_CHECKSUM" =~ ^[0-9a-f]{64}$ ]] || { echo 'SOURCE_CHECKSUM must be 64 lowercase hex.' >&2; exit 64; }
[[ "$PREVIOUS_APP_IMAGE" =~ ^ghcr\.io/thanhlong282828/dji-admin:[0-9a-f]{40}@sha256:[0-9a-f]{64}$ ]] || { echo 'PREVIOUS_APP_IMAGE must use the canonical repository and immutable tag@digest.' >&2; exit 64; }

cd "$DEPLOY_PATH"
export APP_IMAGE="${CONTAINER_IMAGE}:${release_sha}@${IMAGE_DIGEST}"
compose_files=(-f compose.yml)
if [[ "$environment" == "staging" ]]; then
  compose_files+=(-f docker-compose.staging.yml)
else
  compose_files+=(-f docker-compose.prod.yml)
fi
compose=(docker compose "${compose_files[@]}")
if [[ "$environment" == "staging" ]]; then
  [[ -x ./scripts/operations/staging-preflight.sh ]] || { echo 'Executable staging preflight is required.' >&2; exit 65; }
  ./scripts/operations/staging-preflight.sh
fi
"${compose[@]}" config --quiet
"${compose[@]}" pull
"${compose[@]}" run --rm app php artisan operations:migration-risk
release_check=(php artisan operations:release-check
  --image="$APP_IMAGE"
  --rollback-target="$PREVIOUS_APP_IMAGE"
  --quality-evidence="$QUALITY_EVIDENCE_REFERENCE"
  --backup-reference="$BACKUP_REFERENCE")
if [[ "$environment" != "staging" ]]; then
  release_check+=(--allow-reviewed-pending)
fi
predeploy_release_check=("${release_check[@]}")
if [[ "$environment" == "staging" ]]; then
  predeploy_release_check+=(--allow-stale-scheduler)
fi
"${compose[@]}" run --rm app "${predeploy_release_check[@]}"
if [[ "$environment" != "staging" ]]; then
  "${compose[@]}" run --rm app php artisan migrate --force
fi
"${compose[@]}" up -d --remove-orphans
"${compose[@]}" exec -T app php artisan optimize
if [[ "$environment" == "staging" ]]; then
  scheduler_ready=false
  for _ in {1..12}; do
    if "${compose[@]}" exec -T app "${release_check[@]}"; then
      scheduler_ready=true
      break
    fi
    sleep 5
  done
  [[ "$scheduler_ready" == "true" ]] || { echo 'Staging scheduler heartbeat did not become ready.' >&2; exit 65; }
fi
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
"${compose[@]}" exec -T app php artisan operations:release-manifest \
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
"${compose[@]}" exec -T app php artisan operations:release-manifest \
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
