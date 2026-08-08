# Payroll Period Operations

Phase 3B-2B completes the company-scoped lifecycle used before Payroll Input capture. Existing statuses remain authoritative: `draft` = editable, `collecting` = open, `review` = under review, `locked` = locked and `processed` = closed. No production Payroll Period is seeded.

Flow: `Create Period → Open Period → Capture Payroll Input → Validate Snapshot → Create Payroll Run → Lock Period → Close Period`.

Only a draft without snapshots/runs can be edited or deleted. Opening checks overlapping active periods inside the same Company. Input options contain only same-company `collecting`/`review` periods. Closed periods cannot reopen.

Routes use auth, verified middleware, granular permissions and record Policies. Controllers dispatch typed Commands/Queries through shared buses. Writes use transactions, idempotency, state-machine guards, activity log, relational history and a safe `PayrollPeriodChanged` event. Reads use aggregate counts, stable pagination and `PayrollPeriodViewModel`; Controller and Blade contain no persistence queries.

Batch 24 additively adds `created_by`, `idempotency_key`, `note`, operational indexes and `payroll_period_histories`. Existing company/code uniqueness and status semantics are preserved.

## Verification and status

Phase 3B-2B is `COMPLETED`. Focused Payroll Period tests pass 3/38 without risky tests; Payroll Input regression passes 3/35; Payroll Feature passes 18/237; full Unit passes 25/61; full Feature passes 113/965; full PHPUnit passes 152/1,204. Payroll PHPStan level 10 reports zero errors. Pint, Vite, Docker Compose validation, routes and migration batch 24 pass. Production MySQL `schema_admin` remains `payroll_periods/input_snapshots/employee_inputs/runs = 0/0/0/0`.

Phase 3A: `COMPLETED`; Phase 3B-1: `COMPLETED`; Phase 3B-2A: `COMPLETED`; Phase 3B-2B: `COMPLETED`. Full Payroll Engine remains `PARTIAL`; Tax and Insurance are `NOT_STARTED`; Payslip is `NOT_STARTED`; Bank Payment and Accounting Integration are `NOT_CONNECTED`.

## Phase 3B-3 integration

The detail action matrix now connects `draft → Open`, `collecting → Create/View Payroll Inputs`, and all periods with runs to the real Run list. `review` remains a real lifecycle status between collection and lock; `processed` is view-only. A browser-admin completed create/open/capture through this page on `schema_admin_testing` without Tinker or direct database mutation.
