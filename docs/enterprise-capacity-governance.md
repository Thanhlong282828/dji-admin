# Enterprise Capacity Governance

Capacity measurements are allowed only against local/testing infrastructure. They must state host/runtime, request count and concurrency and must never be represented as production capacity.

Current runtime inventory: Apple local development host; PHP 8.4.23; Laravel 13.21.1; MySQL client 9.6.0; Redis 8.8.0; Docker Client/Server 29.5.3. The existing guarded `scripts/operations/load-baseline.sh` requires `APP_ENV=testing`, a database ending in `_testing`, and an explicit URL.

Governed signals are request throughput and p50/p95/p99, queue dispatch/processing throughput, database probe latency and PHP peak memory. Redis latency may be recorded only through an isolated safe probe. Baselines are diagnostic comparisons, not an SLA or automatic deployment gate.

## Phase 3F-9 local testing baseline

- 100 `/health/live` requests, configured concurrency 10: 13 seconds (about 7.69 requests/second through PHP's local development server).
- Observed cache histogram after a clean run: total 100, 5xx 0, average application duration 1.2 ms, p50/p95/p99 bucket all ≤50 ms.
- 100 database probes: 20.85 ms aggregate.
- 100 queue dispatches: 10.12 ms aggregate.
- Peak PHP memory: 42.5 MiB.

The wall-clock HTTP number includes local single-process development-server behavior and is not comparable to Nginx/PHP-FPM production capacity without a controlled production-like test environment.
