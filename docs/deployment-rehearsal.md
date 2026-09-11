# Local staging deployment rehearsal

Date: 2026-09-09. Scope: local disposable databases only. No VPS, production service, production credential, external mail, Cloudflare write or Reverb process was contacted.

## Existing topology

The supported release model is a CI-built immutable container image, not an assumed Git checkout deployment. `Dockerfile` builds Composer vendor and Vite assets in separate stages, then runs PHP-FPM as `www-data`. Compose provides private MySQL/Redis, app, unprivileged Nginx, one default `queue:work` worker manager, scheduler and fail-open Reverb. Horizon is available only through the opt-in `horizon-only` profile so it cannot duplicate the default queue worker. GitHub Actions runs MySQL/Redis-backed quality checks, builds/scans an image and deploys an immutable digest through an environment-approved release script.

## Environment contract

| Category | Classification | Variables/evidence |
|---|---|---|
| Application | REQUIRED / environment-specific | `APP_ENV`, `APP_DEBUG`, HTTPS `APP_URL`; `APP_KEY` is SECRET |
| Database | REQUIRED / environment-specific | connection, host, port, database, username; password is SECRET; production DB must not be a local/testing name |
| Session | REQUIRED | shared database/Redis driver, encryption and secure/HTTP-only/SameSite cookie policy |
| Cache/queue/Redis | REQUIRED for distributed deployment | Redis host/port/prefix; password SECRET when enabled |
| Logging | REQUIRED | bounded production channel/level; external tracker OPTIONAL |
| Mail | environment-specific | provider configuration; credentials SECRET; delivery RUNTIME_UNVERIFIED |
| Reverb | OPTIONAL | app key/secret and proxy/process environment; core ERP remains fail-open |
| Filesystem/DMS | REQUIRED | private writable storage; S3-compatible provider OPTIONAL/SECRET |
| Cloudflare Images | OPTIONAL | account/delivery metadata plus SECRET API token |
| OAuth/Passport | environment-specific | key material/client secrets are SECRET |
| Backup | environment-specific | protected absolute target and confirmation; DB credential is process-injected |

Twenty core production settings are required or deployment-owned. Seven production-only capabilities remain missing or runtime-unverified here: production DB, HTTPS domain/TLS, encrypted shared session, Redis host runtime, mail delivery, backup target/retention execution, and deployment host/process supervision. No unsafe secret value is present in this report.

## Fresh installation evidence

- Collision check for `schema_admin_rehearsal_testing` and `schema_admin_rehearsal_restore_testing`: no pre-existing databases.
- Fresh database creation: PASS.
- `php artisan migrate --force`: PASS in 19.8 seconds.
- Migration inventory: 96/96.
- Tables: 329.
- Production-safe bootstrap rehearsal: `RolePermissionSeeder` only. It completed successfully. `DatabaseSeeder`, `AdminUserSeeder`, `EditorialSeeder`, `LocalDemoOperationalSeeder` and browser fixtures are not production deployment steps.
- Upgrade from an exact previous schema: NOT_RUN. No immutable supported previous-schema artifact was available, and synthesizing one by reversing financial migrations would be unsafe and misleading.

## Dependency/build/cache evidence

- Lockfiles exist. Deployment commands are `composer install --no-dev --no-interaction --prefer-dist --classmap-authoritative` and `npm ci`; the Docker build already performs isolated reproducible installs.
- Vite production build: PASS; manifest and referenced CSS/JS loaded during browser smoke.
- `config:cache`, `route:cache`, `view:cache`: PASS.
- Application boot with caches active: PASS; readiness returned DB/Redis/queue true.
- Cache was cleared after rehearsal so local/testing configuration was not left cached.
- Writable runtime scope is `storage/`, its private/framework/log subdirectories, and `bootstrap/cache`; no `777` is required.

## Runtime rehearsal

| Check | Result |
|---|---|
| Redis connectivity/cache/lock | PASS with isolated rehearsal prefixes |
| Queue dispatch/consume | PASS; side-effect-free `OperationsProbeJob`, bounded one-job worker |
| Failed-job visibility | PASS; one rehearsal-owned failure was visible then removed from the disposable DB |
| Financial job used as probe | NO |
| Scheduler parse | PASS; 19 entries |
| Critical overlap | PARTIAL; all listed schedules use overlap locks, while some Employee/Recruitment jobs do not use `onOneServer` and therefore require a single scheduler owner |
| Horizon | Config/command PASS; process inactive and production runtime unverified; opt-in profile prevents duplicate ownership |
| Reverb | Config boot PASS; process/proxy RUNTIME_UNVERIFIED and non-critical |
| Mail | Log mailer shape PASS; no real delivery attempted |
| DMS | PASS: private upload/version/SHA/download authorization suite, 1 test/14 assertions |
| Health | Liveness/readiness PASS; no credential or stack-trace leakage |

## HTTP and security smoke

The cached rehearsal application served 25 representative authorized pages at desktop and mobile sizes: 50 successful page checks, zero 5xx/unexpected 4xx, zero console errors/warnings, zero page errors and zero overflow. Coverage included Finance, Payroll, Procurement, Inventory, Project and Maintenance. Production-built CSS/JS loaded, including the existing bright-gold sidebar treatment. Runtime headers included nosniff, frame policy, referrer policy, permissions policy and request correlation. Unauthenticated dashboard returned redirect; missing-CSRF mutation returned 419. Permission and tenant denial remain covered by the full regression suite.

## Backup/restore

- Guarded backup validation: PASS.
- Rehearsal backup: PASS, 59,644-byte gzip artifact with SHA-256 sidecar.
- Restore target: only `schema_admin_rehearsal_restore_testing`.
- Checksum/gzip/import/connectivity: PASS.
- Restored integrity: 96 migrations, 329 tables, failed jobs 0.
- Temporary artifact removed. Both disposable databases are removed at rehearsal cleanup.
- Production backup location, storage backup and credentials: CONFIGURATION_REQUIRED.
- RPO/RTO: BUSINESS_CONFIGURATION_REQUIRED.

## CI/CD audit

CI already covers locked installs, blank MySQL migration, Redis, PHPUnit/coverage, PHPStan, Pint, Vite, API contracts, concurrency, architecture, dependency audits, secret scan, migration risk, optimized caches, readiness smoke, Docker build and Trivy. Data Quality is exercised by the full suite; an explicit standalone DQ command could improve log discoverability but is not a code blocker. Production deployment remains protected by GitHub Environment approval/secrets and immutable image digest evidence.

## Migration failure/rollback decision

The repository risk scanner intentionally returned a review-required status because historical migrations contain data backfills/raw DDL. This is not a fresh-install failure. Migrations `000094` and `000095` create new tables and indexes. Migration `000096` adds nullable source columns plus an index and unique index to three existing tables. Additive migration `000097` adds nullable Branch snapshot dimensions, an index and a restricted FK to payroll accounting journal lines. Additive migration `000098` adds nullable immutable Cost Center snapshot dimensions, an index and a restricted FK to payroll employee inputs without historical backfill. Production operators must inspect their real row counts and online-DDL/lock behavior before execution. No current local table-size claim is treated as production evidence.

- Code-only failure: redeploy the previous immutable image when DB compatibility is confirmed.
- Backward-compatible additive DDL: verify old code tolerates new nullable tables/columns, then roll code back if necessary.
- Partially committed DDL or transformed data: stop traffic/workers, inspect the exact applied migration, and choose a forward fix or verified restore. Do not blindly invoke `migrate:rollback`.
- Financial mutation: preserve journal/source evidence and use owner-domain reversal/correction; never delete history to make deployment appear successful.

Result: **READY_FOR_STAGING**, while the production classification remains **PRODUCTION_BLOCKED_EXTERNAL**.
