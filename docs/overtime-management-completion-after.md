# Phase 3A-4 — Overtime request and reconciliation

Status: **FUNCTIONAL** for the single-level internal workflow. Phase 3A-7 subsequently completed dedicated record Policies, exports, durable notification/idempotency, frontend partial refresh contracts and process-level concurrency verification; Phase 3A overall is **COMPLETED**.

## Implemented

- Tenant-scoped overtime policy create/update/deactivate and effective employee assignment.
- Duplicate policy code, overlapping assignment, invalid effective dates and daily/weekly limits are rejected.
- Draft → submitted → approved/rejected/cancelled workflow with optimistic versioning and UUID idempotency.
- Self approval is blocked by the employee/user relationship.
- Requested duration is derived server-side in the employee timezone and rounded with the policy enum.
- Approval sets approved minutes only. Actual/payable remain zero until reconciliation.
- Reconciliation requires an approved attendance record. Actual comes from `attendance_records.overtime_minutes`; payable is the rounded intersection of approved and actual minutes, subject to policy minimum/daily limit.
- Mutations inside a locked payroll period are rejected.
- Payroll input snapshots already aggregate only `overtime_requests.payable_minutes` for approved requests.
- Relational workflow history, Spatie activity log and tenant-private broadcast event are connected.
- CQRS read/write paths, permission-aware UI and a real sidebar route are connected.

## Verification

- Focused overtime: 4 tests, 29 assertions passed.
- Attendance PHPStan level 10: 0 errors.
- Migration `2026_07_26_000007_complete_overtime_management_workflow` ran on MySQL `schema_admin`.

## Explicit limitations

- No overtime monetary rate, gross/net payroll, tax or insurance calculation.
- Single-level approval only; no generic multi-level approval engine.
- No overtime export or notification delivery framework in this phase.
- Broadcast backend is connected; automatic frontend partial refresh is still a shared Phase 3A gap.
