# Enterprise Observability Design

## Boundary

Observability owns no HR, Payroll, Workflow, Notification, IAM, Integration or Audit state. It reads operational aggregates and writes only expiring cache counters. It must never consume outbox rows, mark jobs processed or expose business payloads.

## Request flow

`HTTP request` → `RequestCorrelationContext` → safe request/correlation context → application response → `HttpMetricsRecorder` → TTL cache counters/histogram.

`GET /admin/operations/observability` → `auth` → `verified` → `operations.view` → `ObservabilityController` → `ObservabilitySnapshotService` → Phase 3F-8 reliability snapshot + HTTP histogram + SLO/burn calculation + alert evaluation → black-gold view.

`GET /admin/operations/observability/metrics` follows the same security chain and returns only aggregate JSON. It is not public and exposes no hostnames, credentials, identities, payloads or paths.

## Metric contract

- HTTP: total, 5xx, error rate, mean, p50, p95, p99.
- Queue: pending and failed.
- Outbox: pending, failed where supported, oldest pending age.
- Integration: failed messages and open dead letters.
- Scheduler: heartbeat age/status.
- Dependencies: boolean MySQL, Redis and queue readiness.
- SLO: target-derived allowed failures, budget consumed/remaining and informational burn status.

Counters use atomic cache increments. Latency uses cumulative fixed buckets, avoiding an unbounded request list. TTL and all thresholds are environment configuration.

## Logging and correlation

HTTP logs receive `request_id`, `correlation_id`, `component`, `environment`, route and method. The response returns the request ID. Domain/queue/outbox correlation remains propagated by existing typed event fields; this phase does not inject business payloads into logs. `SensitiveDataRedactor` recursively masks password, authorization, cookie/session, token, credential, salary, tax, bank and private-path keys.

## Alert contract

Definitions live in `config/operations.php`; each has component, signal, operator, threshold, severity, window and runbook. Evaluation is read-only. No alert automatically creates a production incident, blocks deployment or contacts an external service.

## Locking and concurrency

Metric recording uses cache-driver atomic increments per counter/bucket and has no shared business-state lock. Aggregation is read-only, so a synthetic process-race suite would not test a domain invariant. Existing Phase 3F-8 queue/outbox and dependency recovery evidence remains authoritative.
