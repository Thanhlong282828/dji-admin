# Enterprise Reliability Design

## Boundary

Reliability observes runtime state; it never owns or changes HR, Payroll, IAM, Workflow, Notification, Integration or Audit business state. Public health endpoints return a safe status contract. Detailed signals require `auth`, verified email and `operations.view`.

## Signals

- HTTP SLI: request total, 5xx count and latency percentiles when an external metrics backend supplies counters.
- Queue: pending and failed jobs.
- Outboxes: pending, failed when supported, and oldest pending age for Workflow, Notification, IAM, Integration and Audit.
- Integration: failed outbound messages and open dead letters.
- Dependencies: MySQL and Redis readiness.
- Scheduler: Redis/cache heartbeat, stale after a configurable interval.

SLO targets are environment configuration, not hard-coded contractual production SLAs. Error budget is informational and does not automatically block deployment.

## Failure contract

`/health/live` proves the HTTP process can answer and remains 200 during dependency failure. `/health/ready` returns 503 when MySQL, Redis or durable queue readiness fails. Recovery returns readiness to 200. Reverb failure does not take down core HTTP and realtime remains degraded until independently verified.

## Safety

Failure injection is allowed only in an isolated Docker project using `APP_ENV=testing` and `schema_admin_testing`. No external telemetry/email/provider call is used. No production business row is written.
# Test process isolation closing note

Process-race workers share the parent test schema by design so they can race on the exact committed fixture. `MysqlProcessRaceRunner` owns its two workers, waits for both terminal results and stops them in `finally`. Suite-level serialization is therefore the correct isolation boundary. Generated names matching `schema_admin_testing_<scope>` are accepted by `EnvironmentSafetyGuard` for future dedicated controllers, while production and development names remain blocked in testing.

Canonical schema verification compares logical index column order/uniqueness and FK source/target/rules, not engine-generated names. Additive repair migrations inspect existing logical indexes and skip environments already satisfying the contract.
