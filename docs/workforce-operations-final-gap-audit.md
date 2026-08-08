# Phase 3A — Workforce Operations Final Gap Audit

Audit: 2026-07-26. Database: MySQL `schema_admin`. Batch 16 and existing data were preserved.

| Capability | Status | Evidence / remaining gap |
|---|---|---|
| Attendance capture/calculation | FUNCTIONAL | Immutable events, deduplication, pairing, anomaly jobs and regression tests. |
| Work Calendar | FUNCTIONAL | CQRS-backed list/create/update/delete UI; referenced calendar deletion blocked. Calendar-day editor remains MISSING. |
| Holiday | FUNCTIONAL | Manual tenant-scoped management with duplicate-date and calendar ownership validation. External provider NOT_CONNECTED. |
| Shift / assignment | COMPLETED | Tenant-scoped management, effective dates, break-rule editor and process overlap verification. Rotation remains deferred scope. |
| Attendance adjustment/approval | COMPLETED | CQRS detail, draft/submit/approve/reject/cancel, relational history, recalculation, optimistic concurrency and UI are connected. |
| Attendance period readiness/lock | COMPLETED | Aggregate blockers and atomic payroll-period/record locking are connected; unlock remains NOT_STARTED. |
| Leave request/ledger | FUNCTIONAL | Duration, overlap protection and reservation/release/usage flow. |
| Leave policy/assignment/ledger | FUNCTIONAL | CQRS management, effective assignment, immutable integer-minute ledger, opening/manual/accrual/carry/expiry actions and idempotency are connected. |
| Leave automated accrual/carry/expiry | COMPLETED | Domain actions, scheduler orchestration, idempotency and accrual race proof are connected. |
| Private leave attachment | COMPLETED | Private lifecycle and safe scanner abstraction are connected. ClamAV runtime remains NOT_CONNECTED. |
| Overtime | FUNCTIONAL | Phase 3A-4 adds policy assignment, request/approval CQRS UI, approved-attendance reconciliation and payroll payable-minute integration. Export/notification/frontend refresh remain shared gaps. |
| Payroll input foundation | FUNCTIONAL | Approved-only snapshots and locking. Full payroll calculation NOT_STARTED. |
| CSV / notifications / realtime UI | COMPLETED | Streaming exports, durable channel delivery and partial DOM refresh/fallback are connected; live two-browser remains NOT_VERIFIED. |

Phase 3A mandatory internal workflow gates are **COMPLETED** as of Phase 3A-7. Full Payroll/Tax/Insurance/Bank/Payslip and other explicitly deferred scope remain outside this phase.
