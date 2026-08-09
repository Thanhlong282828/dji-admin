# Phase 3F-9 Enterprise Observability Audit

Date: 2026-08-09. This audit preceded implementation. Production `schema_admin` was read-only.

## Existing evidence

- `RequestCorrelationContext` created and returned a safe request ID, but did not record HTTP count/status/latency and did not add explicit component/environment context.
- `/health/live`, `/health/ready` and `/health/metrics-readiness` already had minimal public contracts. MySQL and Redis are readiness dependencies; Reverb is non-critical to core HTTP.
- `ReliabilitySnapshotService` already composed queue/failed-job counts, five enterprise outboxes, Integration dead letters, scheduler heartbeat and Phase 3F-8 SLO budget.
- Horizon, worker, scheduler, Redis and Reverb are separate production Compose services. Phase 3F-8 already proved isolated dependency recovery.
- Workflow, Notification, IAM, Integration and Audit keep separate outbox/checkpoint contracts. Metrics must observe them without marking or consuming rows.
- There was no Prometheus/Grafana service in the Compose topology, no typed alert evaluator, no HTTP histogram recorder, and no shared sensitive-context redactor.

## Gaps and decisions

| Gap | Decision |
|---|---|
| HTTP metrics were always zero unless injected externally | Record bounded-TTL counters and cumulative latency buckets after each response |
| No p50/p95/p99 read model | Deterministic histogram percentile calculator |
| No alert summary | Environment-configured definitions with component, signal, operator, threshold, window, severity and runbook |
| Error budget lacked burn status | Reuse Phase 3F-8 SLO target and calculate informational burn rate |
| No safe metrics read endpoint | Permission-protected JSON endpoint under `/admin/operations`; no public infrastructure details |
| Logging context incomplete | Add correlation ID, component and environment; keep payloads out of context |
| Redaction not reusable | Recursive key-based redactor with unit coverage |

No database migration is justified. Cache telemetry has bounded TTL and all other signals come from existing read models/tables. No external telemetry, alert provider, email or webhook is configured or invoked.

## Risks retained

- Cache counters are single-runtime operational foundations, not a replacement for Prometheus/OpenTelemetry in a horizontally scaled fleet.
- Histogram buckets provide bounded approximations, not raw trace-level latency.
- Threshold defaults are safe development examples and are not official production SLOs.
- Queue oldest-job age is not available from every driver without driver-specific inspection; current dashboard reports durable backlog and failed jobs.

## Closing evidence

All implementation and final gates passed. Browser testing exposed that the exception-report callback could resolve `ErrorTrackerInterface` before providers were registered when bootstrap failed; `bootstrap/app.php` now checks the container binding before capture, preserving the original exception and avoiding recursive reporting failure.
