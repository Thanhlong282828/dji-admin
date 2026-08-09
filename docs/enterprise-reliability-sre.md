# Phase 3F-8 Enterprise Reliability & SRE

## Operations flow

`GET /admin/operations/reliability` → authentication → verified email → `operations.view` → `ReliabilityController` → `ReliabilitySnapshotService` → safe dependency/queue/outbox read model → Blade dashboard.

`schedule:work` → `operations:heartbeat` every minute → cache heartbeat → stale-age calculation. This changes no business state.

## Current SLO foundation

The HTTP availability target and window are environment-configurable (`SLO_HTTP_AVAILABILITY_TARGET`, `SLO_WINDOW_MINUTES`). `SloCalculator` returns allowed failures plus consumed and remaining error budget. No fictional production SLA is asserted.

## Operational limits

Metrics are readiness foundations, not a Prometheus/APM replacement. Live two-browser realtime and external provider reliability are not inferred from core health. Capacity measurements must state the testing hardware/runtime and must never target production.

## Completion evidence

- Focused Operations: 6 tests / 23 assertions initially; final route/security set 10 tests / 1,237 assertions.
- Full PHPUnit after the route fix: 387 tests / 4,272 assertions, one skipped, exit 0.
- Integration/Notification safe failure regression: 14 tests / 48 assertions. HTTP failure modes are local mocks for timeout, 503 and invalid response; no network call. Notification database/broadcast channel retry remains idempotent.
- Phase 3F-8 PHPStan level 10: 0 errors. Repository-wide `app Modules` level 10: 418 legacy errors, so the overall phase remains PARTIAL.
- Pint, Blade compile, Vite build and Compose base/production-runtime config: PASS.
- Redis failure: live 200, ready 503; restored ready 200.
- MySQL failure: live 200, ready 503; restored ready 200.
- Reverb failure: live 200 and ready 200, proving realtime is non-critical to core HTTP readiness.
- Worker recovery: the side-effect-free `OperationsProbeJob` ran once after workers returned; queue returned to zero.
- Exact browser: 1280×800 and 390×844, client width equals document scroll width, zero console problem.
- Capacity on local Docker Engine 29.5.3: 100 DB probes 8.77 ms; 100 queue dispatches 3.47 ms; peak 38.5 MiB. These are not production SLAs.
- Production read-only baseline: users 1, employees 9, roles 14, company access 1; Payroll/IAM/Workflow/Notification/Integration/Audit business counts remain zero; migrations 60.
- All `phase3f8` containers and volumes were removed.
# Completion hardening evidence

Repository-wide static analysis now passes at PHPStan level 10 without a baseline or suppression. Environment bootstrap tooling is guarded and idempotent. See `docs/development-data-baseline.md` for database ownership, row coverage and the remaining physical-schema/test-isolation blockers.

Full PHPUnit and the complete process-concurrency suite now pass on a clean single-controller run. If a test controller is forcibly interrupted, operators must terminate orphaned `tests/Support/workforce_concurrency_worker.php` processes and run guarded fixture cleanup before retrying; running multiple controllers against the same schema is unsupported.

Final evidence after canonical index repair: full PHPUnit 397 tests / 4,291 assertions / 1 skipped / exit 0; concurrency 60 tests / 486 assertions / exit 0; repository PHPStan level 10 has zero errors; Pint, Blade, Vite, Compose and browser smoke pass.
