# Phase 3F-7 — Enterprise Deployment & Operations

## Scope

This phase adds operational controls only. It introduces no business module, migration, production seed, or production write. The canonical environments are `development`, `testing`, and `production`.

## CI/CD

- Pull requests run in the GitHub `testing` environment with MySQL/Redis, dependency audits, secret scan, PHPUnit coverage, PHPStan level 10, Pint, Vite, Compose validation, and a readiness smoke test.
- `develop` deploys through the protected `development` environment. `main` deploys through the protected `production` environment.
- Images are immutable and tagged by full commit SHA. Production deployment is serialized. GitHub Environment approvals and environment-scoped secrets remain repository administration responsibilities.
- Deployment validates Compose, migrates with `--force`, optimizes Laravel, and requires `/health/ready` to pass. Rollback is an explicit script using `PREVIOUS_APP_IMAGE`; it is not hidden behind a shell `||`.

## Container hardening

- PHP-FPM runs as `www-data`; containers drop Linux capabilities where compatible and enable `no-new-privileges`.
- Runtime health checks cover MySQL, Redis, and a supported durable queue driver.
- Production resources have CPU/memory limits. MySQL, Redis, Reverb and Mailpit are not published by the production override; Mailpit is disabled unless its development profile is explicitly enabled.
- Nginx removes version disclosure, sets security headers, limits login/API request rates, blocks dotfiles, and caps request body size.

## Observability

- `RequestCorrelationContext` accepts only a bounded safe `X-Request-ID` or generates a UUID, adds it to logs, and returns it in the response.
- Production logs use stderr plus rotating daily files. Payloads and credentials are not logged.
- `ErrorTrackerInterface` is bound to `NullErrorTracker`; external error delivery is disabled until an approved provider is configured.
- `/health/live`, `/health/ready`, and `/health/metrics-readiness` expose status only, never hosts, credentials, SQL, queue payloads, or exception details.
- Horizon remains the queue monitoring control plane. Metrics integration is readiness-only and sends nothing externally.

## Validation evidence

Completed on 2026-08-09:

- Focused operations tests: 2 tests, 11 assertions, exit 0.
- Full PHPUnit: 378 tests, 4,242 assertions; 377 passed, one production-only safety test skipped; exit 0.
- PHPStan entire configured scope: 0 errors. Pint, Blade compile/cache/clear, and Vite production build passed.
- Composer audit: 0 advisories after targeted upgrades to Guzzle 7.15.3, Guzzle Promises 2.5.2, and CommonMark 2.9.0. npm runtime audit: 0 vulnerabilities.
- Tracked secret scan, workflow YAML parse, CI static validation, development Compose config, and production Compose config passed.
- Production render proves all PHP services use `production`, `schema_admin`, encrypted sessions, warning-level operational logging, and no published MySQL/Redis/Reverb ports. Production credentials are mandatory interpolation values; no secret is committed.
- All 55 migrations are `Ran`; no migration was created.
- Testing-only baseline: 100 database probes in 18.88 ms; 100 queue dispatches in 59.35 ms; peak PHP memory 42.5 MiB. These are observational baselines, not an SLA.
- Docker runtime verification completed with Docker Engine 29.5.3 / Docker Desktop 4.78.0 on `linux/arm64`. Image `laraveldji-admin:phase-3f-7-runtime-verify` built successfully as immutable image ID `sha256:350eb365...`; its build context was 331 KB and contained no `.env`, private storage artifact, key, test suite, or debugbar payload.
- The isolated production-like stack used only `schema_admin_testing`. PHP-FPM, queue, scheduler, Horizon and Reverb ran as UID 82; Nginx ran as UID 101. MySQL and Redis had no host-published port; only the test Nginx ingress `18081` was published.
- `/health/live`, `/health/ready`, and `/health/metrics-readiness` returned their safe contracts through Nginx. A Redis outage kept liveness at 200, changed readiness to 503, and recovery restored readiness to 200. Security headers and response correlation were present without credential disclosure.
- Runtime inspection confirmed `no-new-privileges`, `cap_drop: ALL`, non-privileged mode, CPU/memory limits, restart policies, no Docker socket mount, and writable `storage`/`bootstrap/cache` without `0777`. A guarded `OperationsProbeJob` was consumed once and the scheduler process plus schedule inventory were verified.
- The runtime verification exposed and fixed two real health defects: Predis `PING` status objects are now accepted, and health routes no longer depend on Redis-backed session/rate-limit middleware. Backup/restore guards and deploy/rollback script syntax were re-verified. The isolated stack and its named test volumes were removed after verification.
- Production read-only counts before and after: employees 9; payroll runs, IAM requests, workflows, notification deliveries, integration outbound messages, and all audited Enterprise Audit tables 0.

No production backup, load test, external telemetry call, seed, migration, or database mutation was performed.

## Final status

- Phase 3F-7B Docker Production Hardening: **COMPLETED**.
- Phase 3F-7 Enterprise Deployment & Operations: **COMPLETED**.
# Phase 3F-8 follow-on

Phase 3F-8 adds reliability signals and failure-recovery evidence without changing the Phase 3F-7 deployment topology. The verified production-like stack remains the authoritative Docker boundary; reliability injection uses a separate Compose project and `schema_admin_testing` only.
