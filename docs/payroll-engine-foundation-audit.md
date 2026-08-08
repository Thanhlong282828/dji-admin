# Phase 3B-1 Payroll Engine Foundation Audit

Audit date: 2026-07-26. Status: **AUDIT COMPLETED — Phase 3B-1 implementation remains PARTIAL**.

## Environment and safety

- Project: `/Users/lahoathanh/Desktop/laraveldji/admin`.
- Runtime database: MySQL `schema_admin`, MySQL 9.6.0, isolation `REPEATABLE-READ`.
- Migrations are valid through batch 21; no existing migration was edited.
- All five Payroll business tables currently contain zero rows. No production seed, reset, wipe, rollback or destructive cleanup was executed.
- The Git index has no usable tracked baseline: the existing project appears as 45 untracked top-level entries. The working tree was preserved.

## Existing Payroll schema

### Payroll input foundation

`payroll_periods` contains company-scoped code/date lifecycle data, `status`, optimistic `version`, and lock actor/reason/timestamps. It has unique `(company_id, code)`, period-range and status indexes, plus company/user foreign keys.

`payroll_input_snapshots` provides versioned immutable input headers through unique `(payroll_period_id, snapshot_version)`, generated actor/time, status and lock time.

`payroll_employee_inputs` captures one employee per snapshot and integer-minute attendance, paid/unpaid leave, approved overtime, absence, FTE basis points, contract/assignment references and readiness issues. Unique `(payroll_input_snapshot_id, employee_id)` prevents duplicate membership.

### Legacy payroll output tables

`payroll_runs` exists with company, globally unique code, date range, lifecycle enum, decimal aggregate totals, formula JSON and lock time. It is not connected to `payroll_periods`, input snapshots, actor/version/idempotency or CQRS.

`payroll_items` exists with run/employee uniqueness, gross, insurance, tax, deductions, net, JSON breakdown and a payslip URL. It lacks company/input snapshot/calculation version, immutable component lines and approval/audit metadata. The tax/insurance columns are legacy placeholders and must not be interpreted as implemented statutory engines.

`employment_contracts.base_salary` is decimal(18,2) with currency; active contract selection is already used by the input generator. No salary-component catalog or effective-dated component assignment exists.

## Existing models

- `PayrollPeriod`: guarded Eloquent model with typed period/date/status casts.
- `PayrollInputSnapshot`: guarded Eloquent model with generated/locked datetime casts.
- No model exists for Payroll Run, employee run membership, salary component, component assignment, calculation result or calculation line.

## Existing routes and UI

There are 234 application routes, of which four are Payroll-related:

1. `admin.payroll-input.index`
2. `admin.payroll-input.export`
3. `admin.payroll-input.generate`
4. `admin.payroll-period.lock`

The current Blade page truthfully identifies itself as Payroll Input Foundation and explicitly says gross/net, tax, insurance, payslip and bank payment are not implemented. There is no fake Payroll Run UI.

## Existing immutable Payroll input snapshot

`PayrollInputGenerationService` locks the tenant-scoped period, validates optimistic version and lifecycle, rechecks attendance/leave/overtime readiness, allocates the next snapshot version under the period lock, then streams eligible employees into snapshot rows. It stores only integer minutes and basis points, never floating-point money. Generation and period lock use three bounded database transaction attempts and emit after-commit-safe `WorkforcePayrollInputChanged` events.

Current readiness blocks pending attendance adjustments, submitted leave, draft/submitted overtime and attendance anomalies. Snapshot references contract, assignment and work profile data effective during the period. Missing contract/assignment is recorded as a readiness issue rather than silently invented.

## Existing period lock

The period state machine supports draft → collecting → review → locked → processed and cancellation paths. Lock obtains row locks for the period/latest snapshot, locks the snapshot and related attendance rows, records actor/reason/version, and broadcasts a safe event. Attendance and Overtime services already coordinate with locked periods.

## Existing permissions and Policies

Existing permissions include `payroll-period.view/manage/lock/unlock`, `payroll-input.generate/export`, and generic payroll view/create/update/delete/calculate/approve/reject/publish/export/import/audit permissions.

`PayrollInputPolicy` covers view, readiness, generate, snapshot view and export. `PayrollPeriodPolicy` covers view and lock. Both use permission plus company-scoped record checks. Dedicated Payroll Run, configuration, salary component, calculation and approval Policies do not exist.

## Existing services, CQRS and events

- Commands/handlers: Generate Payroll Inputs and Lock Payroll Period.
- Services: `PayrollInputGenerationService`, `PayrollPeriodStateMachine`.
- Event: `WorkforcePayrollInputChanged`, private company channel, safe identifier/status/version payload.
- Cache: event is connected once to `InvalidateWorkforceReadModels` under the company-scoped payroll-input scope.
- Controller dispatches Command Bus for writes, but still performs inline request validation. No Query Bus/read repository or typed dashboard ViewModel exists in Payroll.
- There are no dedicated Payroll Run commands, queries, repositories, calculation engine, approval service, export writer or notification listener.

## Existing tests

Payroll input generation/locking is exercised indirectly by `WorkforceOperationsFoundationTest`, the Workforce Policy Matrix, Attendance period-lock tests and critical Workforce process races. There is no `tests/Feature/Payroll` or `tests/Unit/Payroll` suite, and no Payroll Run calculation/concurrency test.

Phase 3A baseline before this audit remains 123 tests / 908 assertions, seven scoped PHPStan level-10 modules at zero errors, Pint/Vite passed, 234 routes, batch 21 and valid Docker Compose.

## Schema gaps for Phase 3B-1

1. Company-scoped payroll configuration and explicit currency/minor-unit/rounding settings.
2. Salary component catalog with typed earning, pre-tax deduction, post-tax deduction and employer-contribution categories.
3. Effective-dated employee component assignments with fixed integer-minor-unit or basis-point configuration.
4. Payroll Run linked to company, Payroll Period and the exact immutable input snapshot.
5. Run idempotency key, optimistic version, calculation version, created/calculated/submitted/approved/locked actors and timestamps.
6. Immutable employee membership/snapshot of contract, currency and base salary.
7. Employee calculation results using integer minor units for gross, deductions, employer contribution and net.
8. Immutable calculation breakdown lines carrying component/formula/input/result versions.
9. Tenant-compound uniqueness/indexes and safe FK deletion behavior for all new tables.
10. Legacy `payroll_runs.code` is globally unique rather than company-scoped; legacy run/items cannot safely satisfy the target without additive schema alignment or replacement read model.

## Business and application gaps

- No Payroll Configuration lifecycle or validation.
- No create/collect/calculate/recalculate/submit/approve/reject/lock Payroll Run lifecycle.
- No deterministic gross earning/deduction/contribution calculator.
- No effective-dated salary-component resolution or calculation ordering.
- No immutable calculation result/breakdown or stale calculation protection.
- No run readiness comparing period, snapshot and employee/component coverage.
- No separation-of-duties rule for submitter versus approver.
- No Payroll Run Policy matrix, IDOR tests or denied-mutation invariants.
- No run list/detail Query Bus/read repository/cache integration.
- No Payroll Run activity/event/notification contract.
- No tenant-scoped streaming calculation export.
- No process-level calculate/calculate, approve/reject or lock/calculate MySQL race proof.
- No dedicated Feature, Unit, repository, Policy, validation, cache or concurrency suite.

## Required implementation decision

The legacy `payroll_runs` and `payroll_items` tables must not be treated as a completed engine. Phase 3B-1 should use additive migrations only, preserve their zero-row state, use integer minor units for every new monetary calculation, link every run to an immutable input snapshot, and keep statutory tax/insurance values explicitly outside the engine. Before migration, the implementation must audit orphan rows and incompatible lifecycle values even though the current count is zero.

## Scope classification after audit

- Phase 3A: **COMPLETED**, unchanged.
- Phase 3B-1: **PARTIAL — core schema/calculation/lifecycle implemented after this audit; HTTP CRUD, read-side, export and Payroll process races remain**.
- Full Payroll Engine: **PARTIAL**.
- Tax Engine: **NOT_STARTED**.
- Insurance Engine: **FOUNDATION_ONLY** only when generic employer-contribution components are added; currently not implemented.
- Bank Payment: **NOT_CONNECTED**.
- Payslip: **NOT_STARTED**.
- Accounting Integration: **NOT_CONNECTED**.
