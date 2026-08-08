# Phase 3A-3 — Leave Management

Status: **PARTIAL**. Internal policy, assignment and ledger operations are functional; private attachment runtime and automated schedulers are not yet connected.

## Functional

- Tenant-scoped Leave Policy create/update/deactivate service with version checks and company-unique codes.
- Employee Policy Assignment with effective-date overlap protection and database-backed UUID idempotency.
- Opening balance, manual adjustment, annual/monthly accrual, carry-forward and expiry ledger action types.
- Ledger entries are immutable integer minutes with before/after values and unique idempotency keys.
- Ledger aggregate is the balance source of truth; no client-supplied final balance.
- Request creation has UUID idempotency, calendar/holiday duration calculation, overlap protection and locked-payroll-period checks.
- Draft/submit/approve/reject/cancel/withdraw retain Model A reservation/release/usage behavior and now write relational request history.
- Realtime event payload excludes reason, medical data, storage paths and private metadata.

## Partial or not connected

- FTE proration: PARTIAL; FTE is stored in work profile but automated grant calculation is not scheduled.
- Partial-day sessions: PARTIAL; duration follows schedule minutes but AM/PM session UI is not connected.
- Accrual/carry-forward/expiry scheduler: NOT_CONNECTED.
- Attachment schema, private keys, SHA-256 and quarantine metadata: READY; upload/replace/remove/download services are NOT_CONNECTED.
- Malware scanner abstraction and ClamAV runtime: NOT_CONNECTED. No file is labeled clean.
- Dedicated Leave record policies and notification delivery idempotency: NOT_CONNECTED.
- Process-level MySQL concurrency: NOT_VERIFIED; unique indexes, row locks and optimistic versions are present.
- Single-level Leave Approval: FUNCTIONAL. Multi-level workflow: NOT_STARTED.

## Migration

`2026_07_26_000006_complete_leave_management_workflow`, batch 18. Batch 16 and 17 were not modified.
