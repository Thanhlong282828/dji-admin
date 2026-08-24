#!/usr/bin/env sh
set -eu

# Read-only pre-deploy checks. This script never migrates, seeds, restarts or contacts a VPS.
composer validate --strict
php artisan operations:production-readiness --json
php artisan operations:config-audit --json
php artisan operations:secrets-audit --json
php artisan operations:architecture-audit --json

