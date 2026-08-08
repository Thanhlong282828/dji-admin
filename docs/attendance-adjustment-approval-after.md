# Attendance Adjustment, Approval and Period Lock

Status: **COMPLETED for the internal HTTP/CQRS workflow**. Phase 3A-7 subsequently closed the shared Policy, cache and process-level concurrency blockers; Phase 3A overall is **COMPLETED**.

## Implemented

- Tenant-scoped Attendance Daily Detail query and typed view model.
- Immutable raw event timeline; adjustments never modify or delete raw events.
- Adjustment create/draft, submit, approve, reject and cancel state transitions.
- UUID database-backed create idempotency and one submitted adjustment per record.
- UTC datetime ordering, non-negative break and break-within-session validation.
- Dedicated relational adjustment and attendance approval histories.
- Optimistic concurrency on adjustment, attendance and payroll-period versions.
- Approval recalculation updates minutes, calculation version and record version while preserving unrelated anomalies.
- Attendance submit, approve and reject workflow with self-approval protection.
- Aggregate payroll-period readiness query and blocker list.
- Atomic payroll-period and attendance-record lock transaction.
- Locked records reject adjustment and approval mutations. Late raw events for locked dates are rejected by the workflow boundary; external device quarantine is NOT_CONNECTED.
- Activity logs and minimal realtime-safe `WorkforceAttendanceChanged` payloads.

## Routes

- `admin.attendance.show`
- `admin.attendance-adjustments.store`
- `admin.attendance-adjustments.transition`
- `admin.attendance.transition`
- `admin.attendance-periods.readiness`
- `admin.attendance-periods.lock`

## Security

Every controller action requires authentication, verified email, named permission, tenant access and a company-scoped repository lookup. Broadcast payloads contain identifiers/status only and exclude reason, GPS, raw device metadata, salary and session details.

## Migration

Batch 17 adds nullable lock references, adjustment idempotency and relational attendance approval history. Batch 16 was not modified. Existing rows remain compatible.

## Limitations

- Attendance Period Unlock: **NOT_STARTED**.
- Workforce notification recipient matrix and persistent delivery idempotency: **PARTIAL / NOT_CONNECTED**.
- True multi-process race testing: **NOT_VERIFIED**; row locks and optimistic versions are exercised in integration tests, but PHPUnit's transaction model is not proof of concurrent MySQL processes.
- Live two-browser WebSocket refresh: **NOT_VERIFIED**.
