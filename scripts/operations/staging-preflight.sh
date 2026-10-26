#!/bin/sh
set -eu

: "${APP_IMAGE:?APP_IMAGE is required}"
: "${RELEASE_SHA:?RELEASE_SHA is required}"
: "${STAGING_APP_URL:?STAGING_APP_URL is required}"
: "${STAGING_APP_KEY:?STAGING_APP_KEY is required}"
: "${STAGING_DB_USERNAME:?STAGING_DB_USERNAME is required}"
: "${STAGING_DB_PASSWORD:?STAGING_DB_PASSWORD is required}"
: "${STAGING_MYSQL_ROOT_PASSWORD:?STAGING_MYSQL_ROOT_PASSWORD is required}"
: "${STAGING_PROTECTED_DATABASE_NAMES:?STAGING_PROTECTED_DATABASE_NAMES is required}"
: "${VPS_PRODUCTION_DATABASE:?VPS_PRODUCTION_DATABASE is required}"

contains_database_name() {
  local candidate="$1"
  local normalized

  normalized="$(printf '%s' "$STAGING_PROTECTED_DATABASE_NAMES" | tr -d '[:space:]')"
  case ",$normalized," in
    *",$candidate,"*) return 0 ;;
    *) return 1 ;;
  esac
}

printf '%s' "$RELEASE_SHA" | grep -Eq '^[0-9a-f]{40}$' || { echo 'RELEASE_SHA must be a full lowercase Git SHA.' >&2; exit 64; }
printf '%s' "$APP_IMAGE" | grep -Eq '^ghcr\.io/thanhlong282828/dji-admin:[0-9a-f]{40}@sha256:[0-9a-f]{64}$' || { echo 'APP_IMAGE must be the approved immutable GHCR tag@digest.' >&2; exit 64; }
case "$APP_IMAGE" in *":${RELEASE_SHA}@"*) ;; *) echo 'APP_IMAGE tag must match RELEASE_SHA.' >&2; exit 65 ;; esac
[ "${STAGING_DB_DATABASE:-}" = "laraveldji_staging_restore" ] || { echo 'STAGING_DB_DATABASE must be laraveldji_staging_restore.' >&2; exit 65; }
contains_database_name "$VPS_PRODUCTION_DATABASE" || { echo 'Production database must be an exact protected database entry.' >&2; exit 65; }
case "$STAGING_APP_URL" in https://*) ;; *) echo 'Full browser acceptance requires an HTTPS staging origin.' >&2; exit 65 ;; esac

docker compose -f compose.yml -f docker-compose.staging.yml config --quiet
docker compose -f compose.yml -f docker-compose.staging.yml config \
  | grep -F 'APP_ENV: staging' >/dev/null
docker compose -f compose.yml -f docker-compose.staging.yml config \
  | grep -F 'APP_DEBUG: "false"' >/dev/null

echo 'Staging static preflight passed. No image pull, migration, service start or database write was performed.'
