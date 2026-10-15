# ClamAV Immutable Image Verification — 2026-10-15

## Scope and safety

This evidence covers the local Docker/ClamAV signature lifecycle repair and its approved source commit/push gate only. No production VPS, production database, Supabase resource, staging resource, migration or deployment was performed.

## Root cause

The Alpine runtime installed `clamav-scanner` plus `clamav-db` and then expected a non-empty `.cvd`/`.cld` database at build time. The package combination did not materialize a usable signature database, so the immutable release build failed before it could be used for staging.

The first repaired build exposed a second independent release-build defect: `php artisan package:discover` booted with the default production identity while `VPS_PRODUCTION_DATABASE` was intentionally unavailable in the image build context. Package discovery now runs with a non-production testing identity. This does not weaken the production runtime guard or fabricate production credentials.

## Implemented lifecycle

- The runtime image installs `clamav-scanner` and `freshclam`; it does not install or run `clamd`.
- `docker/clamav/update-signatures.sh` runs during the immutable image build.
- The updater accepts only a bounded timeout of 30–900 seconds (default 300 seconds).
- FreshClam downloads official databases into the configured directory. Empty or missing `.cvd`/`.cld` files fail the build.
- A temporary FreshClam configuration removes only `NotifyClamd`, because the approved low-memory topology intentionally has no resident daemon. Mirror and proxy configuration remains inherited from the packaged configuration.
- `clamscan --database=<directory> --version` must successfully load the database before the image build can pass.
- Laravel runtime readiness now requires the scanner executable, a present signature file, and a successful bounded database-load probe. Freshness remains a separate mandatory check.
- Upload scanning remains fail-closed. No placeholder signature, bypass or test-only production flag was added.

Signature refresh is not performed inside HTTP request handling. Refresh requires an immutable image rebuild, a separately governed read-only signature volume, or a separately approved remote scanner. The 964 MiB application VPS is not required to host ClamD.

## Docker evidence

- Target platform: `linux/amd64` (matches the verified x86_64 VPS).
- Tag: `laraveldji-local:clamav-fix-amd64` (local only; not pushed).
- Image ID / local content digest: `sha256:875324a4d047501463397948d2de2b3b7d1a121acf908632fd51e81f43ffce92`.
- Size: `210568486` bytes.
- ClamAV: `1.4.6`.
- Signature version: `28154`, published `2026-10-15 06:24:22 UTC` as reported by ClamAV.
- Non-empty signature files: `3` (`daily`, `main`, `bytecode`).
- Runtime user: `www-data`.
- ClamD: absent.
- EICAR detection inside an ephemeral container: PASS (ClamAV exit `1`, `FOUND`; container discarded).
- Build exit: `0`.
- Build log: `/private/tmp/laraveldji-clamav-docker-build-final-clean.log` (temporary local evidence).

This is a local image ID, not a registry digest. A registry digest can only be reported after a separately approved image push.

## Verification results

| Gate | Result |
|---|---|
| Shell syntax | PASS |
| Focused PHPUnit | PASS — 11 tests, 131 assertions |
| Full PHPUnit | PASS — 939 tests, 938 passed, 1 skipped, 34,245 assertions, exit 0, 1,640.727 seconds |
| Missing-signature readiness | PASS — command returns non-ready and reports signature/database-load checks false |
| PHPStan | PASS — 0 errors |
| Pint | PASS |
| Blade compile/cache | PASS |
| Vite production build | PASS |
| Routes | PASS — 708 routes |
| Composer audit | PASS — 0 advisories after `symfony/yaml` `8.1.1` → `8.1.9` |
| npm production audit | PASS — 0 vulnerabilities |
| `git diff --check` | PASS |

The authoritative full suite ran with `APP_ENV=testing`, configured database
`schema_admin_testing`, and actual PDO database `schema_admin_testing`. Its
temporary local log is
`/private/tmp/laraveldji-clamav-release-full-20261015.log`. No test was run
against `schema_admin`.

## Files changed for this repair

- `Dockerfile`
- `docker/clamav/update-signatures.sh`
- `Modules/Document/app/Console/Commands/StorageRuntimeReadinessCommand.php`
- `tests/Unit/Storage/DocumentStorageHardeningTest.php`
- `tests/Unit/Operations/RuntimeResourceHardeningTest.php` (ClamAV assertions only; the file also contains preserved earlier work)
- `composer.lock`
- `docs/runbooks/supabase-storage-failure.md`
- `docs/operations/CLAMAV-IMMUTABLE-IMAGE-VERIFICATION-2026-10-15.md`

Other modified/untracked files in the working tree predate this repair and were preserved.

## Remaining approval boundary

The source passed the approved immutable commit/push gates. Registry push, staging use, VPS changes and production deployment remain unperformed and require separate approval.
