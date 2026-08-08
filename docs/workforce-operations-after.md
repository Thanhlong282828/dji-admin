# Phase 3 Workforce Operations Foundation — implementation status

Status: **PHASE 3A — COMPLETED** after the Phase 3A-7 closure gate. This file preserves early implementation history; the final current evidence is in `workforce-operations-phase-3a-final.md`.

| Area | Status | Notes |
|---|---|---|
| Employee Work Profile | FUNCTIONAL | Effective-dated timezone, calendar/default shift, minutes, FTE basis points and eligibility flags. |
| Work/Holiday Calendar | COMPLETED | Schema, calendar-aware duration/generation and admin CQRS editor are connected. External provider NOT_CONNECTED. |
| Shift / breaks / assignment | COMPLETED | Typed schema, precedence, assignment and break-rule editors are connected. |
| Attendance events | FUNCTIONAL | Immutable UTC check-in/out, server timestamp, tenant ownership, deduplication, source and after-commit safe broadcast. |
| Attendance calculation | FUNCTIONAL | Integer-minute calculator, pairing, breaks, late/early/overtime, rounding and anomaly primitives. Scheduled recalculation is active. |
| Attendance adjustment/approval | COMPLETED | Audit/version, command workflow, Policy, UI and export are connected. |
| Leave policy/ledger/request | FUNCTIONAL foundation | Calendar/holiday duration, overlap, state machine, Model A reservation ledger and approval transitions. Policy CRUD/accrual/attachment UI remain. |
| Leave accrual | COMPLETED | Idempotent accrual/carry/expiry schedulers and process race verification are connected. |
| Overtime | FUNCTIONAL | Policy assignment, request approval, approved-attendance reconciliation, payable minutes, CQRS UI, audit and tests are connected. Shared export/notification/frontend refresh gaps remain. |
| Payroll Period/Input Snapshot | FUNCTIONAL foundation | Readiness checks, approved-only input snapshot and irreversible lock through CQRS. No salary or net-pay calculation. |
| CQRS | FUNCTIONAL | Attendance capture, Leave create/transition and Payroll generate/lock use Command Bus. Dashboard reads use Query Bus. |
| Tenant isolation | FUNCTIONAL on active routes | Auth, verified, permission and company access; database services re-scope every employee/entity. Remaining admin workflows need policies. |
| Realtime | FUNCTIONAL backend | Safe IDs/status/version payloads. Live two-browser verification NOT_VERIFIED. |
| Schedulers | FUNCTIONAL attendance | Daily generation, 10-minute recalculation and hourly anomaly detection, `withoutOverlapping` + `onOneServer`. |
| Exports | COMPLETED | Filter-aware streaming CSV with formula protection and large-volume verification. |
| External biometric device | NOT_CONNECTED | Null provider only. |
| Mobile attendance | NOT_STARTED | No fake mobile integration. |
| Full payroll/tax/insurance/bank/payslip | NOT_STARTED / NOT_CONNECTED | Explicitly outside input foundation. |

## Quality result

Organization, Employee, Recruitment, Onboarding, Attendance, Leave and Payroll PHPStan level 10: 0 errors. Pint passed. Phase 3 tests: 9 tests, 30 assertions. Vite build passed. Migration batch 16 ran.

## Remaining closure work

Calendar/shift CRUD and policies; effective schedule screens; attendance adjustment/approval/locking UI; leave accrual and attachment pipeline; overtime request approval; streaming exports; complete notification recipients; frontend realtime refresh; comprehensive security/concurrency test matrix.
