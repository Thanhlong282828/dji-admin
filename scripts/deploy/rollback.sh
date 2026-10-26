#!/usr/bin/env bash
set -euo pipefail

environment="${1:-}"

case "$environment" in staging|production) ;; *) echo 'Explicit rollback environment must be staging or production.' >&2; exit 64 ;; esac
: "${DEPLOY_PATH:?DEPLOY_PATH is required}"
: "${PREVIOUS_APP_IMAGE:?PREVIOUS_APP_IMAGE is required}"
: "${CURRENT_APP_IMAGE:?CURRENT_APP_IMAGE is required}"
: "${ROLLBACK_MANIFEST_PATH:?ROLLBACK_MANIFEST_PATH is required}"
: "${ROLLBACK_MANIFEST_SHA256:?ROLLBACK_MANIFEST_SHA256 is required from the independent approval channel}"
: "${DEPLOY_HEALTH_URL:?DEPLOY_HEALTH_URL is required}"
[[ "$PREVIOUS_APP_IMAGE" =~ ^ghcr\.io/thanhlong282828/dji-admin:[0-9a-f]{40}@sha256:[0-9a-f]{64}$ ]] || { echo 'PREVIOUS_APP_IMAGE must use the canonical repository and immutable tag@digest.' >&2; exit 64; }
[[ "$CURRENT_APP_IMAGE" =~ ^ghcr\.io/thanhlong282828/dji-admin:[0-9a-f]{40}@sha256:[0-9a-f]{64}$ ]] || { echo 'CURRENT_APP_IMAGE must use the canonical repository and immutable tag@digest.' >&2; exit 64; }
[[ "$PREVIOUS_APP_IMAGE" != "$CURRENT_APP_IMAGE" ]] || { echo 'Rollback target must differ from the current image.' >&2; exit 65; }
[[ "$ROLLBACK_MANIFEST_PATH" == /* ]] || { echo 'ROLLBACK_MANIFEST_PATH must be absolute.' >&2; exit 64; }
[[ -f "$ROLLBACK_MANIFEST_PATH" && -r "$ROLLBACK_MANIFEST_PATH" ]] || { echo 'A readable approved release manifest is required.' >&2; exit 65; }
[[ "$ROLLBACK_MANIFEST_SHA256" =~ ^[0-9a-f]{64}$ ]] || { echo 'ROLLBACK_MANIFEST_SHA256 must be 64 lowercase hex from the independent approval channel.' >&2; exit 64; }
[[ "${MIGRATION_COMPATIBLE:-false}" == "true" ]] || { echo 'Database rollback is blocked; use a reviewed forward-fix.' >&2; exit 65; }
[[ "${CONFIG_COMPATIBLE:-false}" == "true" ]] || { echo 'Rollback config compatibility is not proven.' >&2; exit 65; }
[[ "${DATABASE_BACKWARD_COMPATIBLE:-false}" == "true" ]] || { echo 'Database backward compatibility is not proven; use forward-fix.' >&2; exit 65; }

cd "$DEPLOY_PATH"
umask 077
manifest_snapshot_directory="$(mktemp -d "${TMPDIR:-/tmp}/laraveldji-rollback-manifest.XXXXXX")"
chmod 0700 "$manifest_snapshot_directory"
manifest_snapshot="$manifest_snapshot_directory/manifest.json"
trap 'rm -f "$manifest_snapshot"; rmdir "$manifest_snapshot_directory"' EXIT
cp -- "$ROLLBACK_MANIFEST_PATH" "$manifest_snapshot"
[[ -f "$manifest_snapshot" && ! -L "$manifest_snapshot" ]] || { echo 'Rollback manifest snapshot must be a regular file.' >&2; exit 65; }
chmod 0400 "$manifest_snapshot"

if command -v sha256sum >/dev/null 2>&1; then
  manifest_sha256="$(sha256sum "$manifest_snapshot" | awk '{print $1}')"
elif command -v shasum >/dev/null 2>&1; then
  manifest_sha256="$(shasum -a 256 "$manifest_snapshot" | awk '{print $1}')"
else
  echo 'A SHA-256 utility is required to verify the approved rollback manifest.' >&2
  exit 69
fi
[[ "$manifest_sha256" == "$ROLLBACK_MANIFEST_SHA256" ]] || { echo 'Rollback manifest does not match the independently approved SHA-256.' >&2; exit 65; }
command -v jq >/dev/null 2>&1 || { echo 'jq is required to validate the rollback manifest before Docker actions.' >&2; exit 69; }
jq -e 'type == "object"' "$manifest_snapshot" >/dev/null 2>&1 || { echo 'Rollback manifest is malformed.' >&2; exit 65; }
manifest_environment="$(jq -er '.environment | select(type == "string")' "$manifest_snapshot")" || { echo 'Rollback manifest environment is missing.' >&2; exit 65; }
manifest_image_reference="$(jq -er '.image.reference | select(type == "string")' "$manifest_snapshot")" || { echo 'Rollback manifest image reference is missing.' >&2; exit 65; }
manifest_image_digest="$(jq -er '.image.digest | select(type == "string")' "$manifest_snapshot")" || { echo 'Rollback manifest image digest is missing.' >&2; exit 65; }
manifest_rollback_target="$(jq -er '.rollback_target | select(type == "string")' "$manifest_snapshot")" || { echo 'Rollback manifest target is missing.' >&2; exit 65; }
[[ "$manifest_environment" == "$environment" ]] || { echo 'Rollback manifest environment mismatch.' >&2; exit 65; }
[[ "${manifest_image_reference}@${manifest_image_digest}" == "$CURRENT_APP_IMAGE" ]] || { echo 'Rollback manifest current image mismatch.' >&2; exit 65; }
[[ "$manifest_rollback_target" == "$PREVIOUS_APP_IMAGE" ]] || { echo 'Rollback manifest target mismatch.' >&2; exit 65; }

export APP_IMAGE="$PREVIOUS_APP_IMAGE"
compose_files=(-f compose.yml)
if [[ "$environment" == "staging" ]]; then
  [[ -x ./scripts/operations/staging-preflight.sh ]] || { echo 'Executable staging preflight is required.' >&2; exit 65; }
  RELEASE_SHA="${PREVIOUS_APP_IMAGE#*:}"
  RELEASE_SHA="${RELEASE_SHA%%@*}"
  export RELEASE_SHA
  ./scripts/operations/staging-preflight.sh
  compose_files+=(-f docker-compose.staging.yml)
else
  compose_files+=(-f docker-compose.prod.yml)
fi

docker run --rm --network none --read-only --cap-drop ALL \
  --volume "$manifest_snapshot:/manifest.json:ro" \
  --entrypoint php "$CURRENT_APP_IMAGE" -r '
    $contents = (string) file_get_contents("/manifest.json");
    [$trustedChecksum, $environment, $currentImage, $targetImage] = array_slice($argv, 1);
    if (! hash_equals($trustedChecksum, hash("sha256", $contents))) { throw new RuntimeException("Rollback manifest trusted checksum mismatch."); }
    $manifest = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
    if (! is_array($manifest)) { throw new RuntimeException("Invalid rollback manifest."); }
    $checksum = $manifest["checksum"] ?? null;
    unset($manifest["checksum"]);
    $actual = hash("sha256", json_encode($manifest, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
    if (! is_string($checksum) || ! hash_equals($checksum, $actual)) { throw new RuntimeException("Rollback manifest checksum mismatch."); }
    $image = is_array($manifest["image"] ?? null) ? $manifest["image"] : [];
    $manifestImage = ($image["reference"] ?? "")."@".($image["digest"] ?? "");
    if (($manifest["environment"] ?? null) !== $environment) { throw new RuntimeException("Rollback manifest environment mismatch."); }
    if ($manifestImage !== $currentImage) { throw new RuntimeException("Rollback manifest current image mismatch."); }
    if (($manifest["rollback_target"] ?? null) !== $targetImage) { throw new RuntimeException("Rollback manifest target mismatch."); }
  ' -- "$ROLLBACK_MANIFEST_SHA256" "$environment" "$CURRENT_APP_IMAGE" "$PREVIOUS_APP_IMAGE"
docker compose "${compose_files[@]}" up -d --remove-orphans
curl --fail --retry 12 --retry-delay 5 "$DEPLOY_HEALTH_URL/health/ready"
curl --fail --retry 3 --retry-delay 2 "$DEPLOY_HEALTH_URL/health/live"
