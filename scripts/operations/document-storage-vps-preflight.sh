#!/usr/bin/env sh
set -eu

if [ "${APP_ENV:-}" != "production" ]; then
  echo "Document storage preflight refused: APP_ENV must be production." >&2
  exit 2
fi

php artisan documents:storage-readiness --strict-production
php artisan schedule:list >/dev/null

echo "Document storage VPS preflight: PASS"
