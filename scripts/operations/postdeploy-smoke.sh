#!/usr/bin/env sh
set -eu

base_url="${1:-}"
if [ -z "$base_url" ]; then
  echo "Usage: postdeploy-smoke.sh https://deployment.example" >&2
  exit 2
fi
case "$base_url" in
  https://*) ;;
  *) echo "HTTPS base URL required." >&2; exit 2 ;;
esac

# Anonymous, read-only probes only. Authenticated routes are expected to redirect to login.
curl --fail --silent --show-error --max-time 10 "$base_url/health/live" >/dev/null
curl --fail --silent --show-error --max-time 10 "$base_url/health/ready" >/dev/null
curl --fail --silent --show-error --max-time 10 --location "$base_url/login" >/dev/null
echo "Read-only post-deploy smoke passed."
