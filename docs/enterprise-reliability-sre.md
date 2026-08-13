# Phase 3F-8 Enterprise Reliability & SRE

## Non-critical realtime broadcast boundary

Realtime UI broadcasting through Reverb is non-critical to core HTTP and committed business state. Laravel `ShouldBroadcastNow` events are delivered through `FailOpenRealtimeBroadcaster`: only `BroadcastException` transport failures are isolated and recorded with safe component/correlation/event metadata. Domain, validation, programming and database exceptions continue to propagate. When Reverb is disabled by the existing `realtime.reverb` flag, transport delivery is skipped; Notification channel delivery retains separate retry/idempotency governance.

Local outage evidence with Reverb stopped: `/health/live`, `/health/ready` and Dashboard remained HTTP 200; Company, Employee, Organization, Attendance and Payroll mutations returned success and were applied exactly once. Recovery evidence confirmed subsequent broadcasting without fail-open warnings after Reverb restarted.

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

## Phase 3F-9 composition

Observability composes this reliability snapshot rather than duplicating its database/readiness logic. HTTP counters and cumulative histogram buckets are cache-only with bounded TTL. Alert evaluation and burn status are informational read models; they do not mutate source rows or automatically trigger external notification/incident delivery.

Phase 3F-10 release acceptance reuses live/ready contracts, queue/outbox lag and scheduler heartbeat. A degraded gate blocks promotion but does not mutate business state or automatically execute database rollback.
# Phase 3F-11 regression evidence

Production-like certification initially found Redis-dependent liveness. `HttpMetricsRecorder::recordSafely()` now isolates telemetry failure after the response, while health decisions remain explicit. Final Redis-down contract is 200/503/503; recovery is 200/200/200. The failed artifact remains historical and the corrected certificate is certified.
# Runtime configuration fallback

Cache/Redis failure falls back to environment-backed config. Display-only controls may fail open; side-effect and security-sensitive controls fail closed according to typed catalog policy, without turning a cache outage into an HTTP 500.

Secret resolution does not depend on Redis as an authority. Missing critical credentials fail closed at side-effect boundaries while core liveness remains independent.

Phase 3F-15 revalidated the dependency contract in a fresh isolated stack: Redis and MySQL loss kept live 200 and changed ready to 503; both recovered ready in 6 seconds. Queue work survived worker/Horizon interruption, scheduler heartbeat recovered in 31 seconds, and Reverb loss left core readiness healthy.

Phase 3F-18 reuses this queue/readiness foundation. Local query, export and queue-dispatch timing remains informational; robust gates are bounded query growth, bounded memory, cleanup and exact row/checksum equivalence.
