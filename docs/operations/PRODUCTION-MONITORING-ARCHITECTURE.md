# Production Monitoring Architecture

## Canonical design

LaravelDji uses one operations control plane. `ObservabilitySnapshotService` combines bounded HTTP metrics, reliability/queue/outbox state, scheduler heartbeat, storage telemetry, security signals and threshold evaluation. Do not build a parallel dashboard or expose metrics publicly.

VPS resource metrics must be collected outside PHP request handling. The preferred topology is Node Exporter bound to a private interface, scraped by a protected Prometheus-compatible backend. CPU, load, memory, swap, filesystem space/inodes, disk pressure, network throughput/errors and service availability remain backend-owned time series. Laravel displays only safe aggregates obtained through an authenticated server-side adapter.

Monitoring event fields are: `source`, `metric`, `severity`, `timestamp`, `correlation_id`, `component`, `value`, `unit`, and bounded `safe_context`. Labels must use low-cardinality route/component classes, never raw URLs, tenant IDs, object keys, request bodies, credentials or signed URLs.

Queue runtime is either canonical `queue:work` or Horizon, never both unless the deployment explicitly separates queues. `HORIZON_ENABLED` describes expected topology; it is not health evidence. Scheduler heartbeat, Redis locks, worker liveness and queue age require runtime evidence.

Alert evaluation is local and read-only. Delivery requires a separately configured canonical channel, cooldown/deduplication and an explicitly approved end-to-end test. A configured channel is never reported as delivered.

## Security boundaries

- Dashboard and JSON metrics require `operations.view`.
- Exporter and Prometheus endpoints remain private/firewalled.
- No dashboard action executes arbitrary shell commands.
- Retention is bounded in the metrics backend; Laravel cache metrics have configured TTL.
- ClamAV and Supabase failures remain fail-closed for uploads.
