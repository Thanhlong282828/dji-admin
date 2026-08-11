# Phase 3F-9 Enterprise Observability

The platform now provides a permission-protected observability control plane built on Phase 3F-8 reliability signals. It adds HTTP histogram metrics, latency percentiles, informational error-budget burn, environment-configured alerts, safe structured correlation and redaction without a new database or business-state mutation.

## Routes and security

- `/admin/operations/observability` — `auth`, `verified`, `operations.view`.
- `/admin/operations/observability/metrics` — same middleware; aggregate JSON only.
- `/health/*` remains minimal and public. It does not expose database/Redis hosts, credentials, stack traces or business identifiers.

The dashboard contains system state, SLI/SLO, HTTP metrics, queue status, outbox lag, Integration failures, Notification/outbox signals, scheduler heartbeat, dependencies, alert summary and capacity-oriented latency data. It contains no HR/Payroll values or PII.

## Operational interpretation

Configured SLO targets and alert thresholds are environment defaults, not contractual production SLAs. The alert foundation deliberately has no external delivery adapter in this phase. Operators follow the linked Phase 3F-8 runbooks.

## Migration and production safety

Migration count added by Phase 3F-9: **0**. Production was queried only for before/after counts. No production load, telemetry, email, webhook or synthetic alert was emitted.

## Completion evidence

- Focused Operations: 12 tests / 45 assertions, pass.
- Final clean Full PHPUnit: 403 tests, 402 passed, 4,324 assertions, 1 skipped, exit code 0 (1,224.075 seconds).
- Repository `app + Modules` PHPStan level 10: 0 errors; affected tests: 0 errors.
- Pint, Blade clear/cache/clear, Vite production build and base/production-runtime Compose validation: pass.
- Browser: Chrome/playwright-core exact 1280×800 and 390×844; document overflow 0, console warning/error 0, page error 0; fixture cleanup 0.
- Database migration inventory: 61 in production, development, testing and browser testing; no Phase 3F-9 migration.
- Production before/after: users 1, employees 9, roles 14, company access 1; Payroll/IAM/Workflow/Notification/Integration/Audit baseline rows unchanged.

## Release acceptance integration

Phase 3F-10 consumes this dashboard's aggregate availability, 5xx, p95, queue, scheduler, dependency, Integration dead-letter and Notification failure signals after deployment. Production thresholds remain environment configuration; local/demo values never trigger automatic rollback.
# Phase 3F-11 certification note

Healthy candidate observability and all three health endpoints passed. The Redis-dependent liveness regression was fixed with an explicit fail-open metric-recording boundary; application exceptions remain visible. Redis-down final contract is live 200, ready/metrics-readiness 503, with safe payloads and full recovery.
# Runtime configuration signals

Phase 3F-12 defines safe aggregate signals for override/drift counts, disabled integrations and evaluation errors. The current config-only foundation has no per-user labels and emits no external alert.

Secret governance adds only aggregate missing/expired/rotation-due counts; no reference-per-request or value labels.

Data-protection observability is limited to aggregate finding, restricted-export, redaction-failure and policy-error counts. Per-user labels and protected values are prohibited.

Continuity signals are metadata-only: last backup/restore verification, last testing DR certificate, observed recovery time and gate status. Stale/failed evidence is an alert candidate; no external production alert is emitted by the exercise.
# Phase 3F-16 logging assurance

Security events retain correlation and structured fields while redaction prevents credential and restricted business data disclosure. Synthetic control characters are bounded test input only.
# Phase 3F-17 UI regression

Observability and all Operations pages remain grouped under one permission-protected navigation section and pass exact desktop/mobile overflow and console checks.
