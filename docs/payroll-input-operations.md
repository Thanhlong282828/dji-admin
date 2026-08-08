# Payroll Input Operations

## Business purpose

Payroll Input Snapshot is an immutable, company-scoped capture of approved Attendance, approved Leave, approved Overtime and employment context for one Payroll Period. It stores integer time quantities only; gross/net, tax, insurance, payslip, banking and accounting are outside Phase 3B-2A.

## Versioning and immutability

`PayrollInputGenerationService` locks the tenant Payroll Period before allocating the next `snapshot_version`; the database unique key on period/version is the final race guard. A company/period/idempotency-key retry returns the same snapshot. A new key captures current approved sources into a new version and supersedes the previous mutable-current version. No edit or delete HTTP route exists. Revalidation reads only persisted employee rows and never recaptures live sources.

## Readiness

A snapshot is `ready` when it contains at least one eligible employee and no employee is missing the active primary assignment or effective contract required by the existing calculation foundation. It is `blocked` otherwise. Validation issues are persisted in `payroll_input_validation_issues`; history is persisted idempotently in `payroll_input_histories`.

## CQRS flows

- List/detail/create options: Controller → Query Bus → typed Query/Handler → `PayrollInputOperationsReadRepositoryInterface` → DTO/ViewModel → Blade.
- Capture/version: FormRequest → Controller → Command Bus → Handler → `PayrollInputOperationsService` → generation service → immutable header/rows/issues/history/event.
- Create Run: Controller → Command Bus → `CreatePayrollRunFromInputSnapshotHandler` → operations service → existing `PayrollRunService`, preserving exact period/snapshot linkage.

## Security and realtime

Routes require auth, verified email, permission, company access and record Policy. Foreign records in an authorized company resolve as 404; lack of company access is 403. `WorkforcePayrollInputChanged` uses the shared private-company channel and broadcasts only company/snapshot/action/status/version/timestamp and refresh-scope identifiers. Shared Echo performs DOM-owned refresh; no payroll quantities or employee details are broadcast.

## Known limitations

Employee input pagination is bounded to the current detail read model and not yet exposed as a separate employee-detail endpoint. Overtime category splitting retains all approved minutes as weekday minutes because the current Overtime source does not persist a weekday/weekend/holiday classification. Optional Payroll Input CSV was not added. Tax, statutory insurance, payslip, bank and accounting remain unimplemented.

## Phase status and verification

Phase 3B-2A is `COMPLETED`. Focused Payroll Input: 3 tests/35 assertions with risky checks clean under `--fail-on-risky`; Payroll Feature: 15/199; Payroll concurrency: 7/46; full Unit: 25/61; full Feature: 110/927; full PHPUnit: 149/1,166. Payroll module and focused-test PHPStan level 10 returned zero errors. Pint, Vite, routes, batch-23 migration and Docker Compose validation passed. Production `schema_admin` business counts remained snapshots/rows/issues/runs = `0/0/0/0`.

Phase 3B-2B integration loads only same-company Payroll Periods in `collecting` or `review`. When none exist, the create page shows a controlled empty state, links authorized actors to Payroll Period creation and does not render a submittable capture form.

## Phase 3B-3 Run handoff

Snapshot actions are state-aware: draft/blocked can validate; blocked/ready can create a new immutable version; ready can create exactly one Run; an existing Run changes the CTA to View Payroll Run; superseded is view-only. The Run detail exposes the exact snapshot ID/version/captured timestamp. HTTP tests prove that changing live Attendance after capture does not change calculated input quantities.
# Phase 3B-4 source accuracy

Employee input paging/detail and immutable source trace are documented in `docs/payroll-input-source-accuracy.md`. Approved overtime is classified by effective work calendar and company holiday; unresolved calendar data blocks readiness rather than falling back to weekday.
