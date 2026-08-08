# Phase 3B-3 — Payroll end-to-end operational flow

## Verified operational path

`Create Payroll Period → Open (collecting) → Capture immutable Payroll Input Snapshot → Validate persisted Snapshot → Create Payroll Run → Calculate → Review Items → Submit → Approve/Reject → Finalize`.

Every write is `Route → Payroll*Request → Controller authorization → shared CommandBus → typed Command/Handler → PayrollRunService/PayrollInputOperationsService → transactional model/history → safe event/notification → redirect + flash`. Reads are `Route → Controller authorization → shared QueryBus → typed Query/Handler → tenant-scoped read repository → typed ViewModel → Blade`.

The Run stores `payroll_input_snapshot_id`. Calculation reads only `payroll_employee_inputs` belonging to that exact ID; it never re-reads live Attendance, Leave or Overtime. `calculation_version` identifies the complete current item/component set. Submit and Finalize verify employee count, current-version item count and integer minor-unit aggregates. Finalize records `finalized_by/finalized_at` and leaves no mutation action.

## State/action matrix

| From | Action | To | Permission | Blocking condition |
|---|---|---|---|---|
| draft | calculate | calculated | `payroll.run.calculate` | snapshot not ready, stale version |
| calculated | recalculate | calculated | `payroll.run.calculate` | submitted/finalized or stale version |
| calculated | submit | submitted | `payroll.run.submit` | zero employees, issue or inconsistent aggregate |
| submitted | approve | approved | `payroll.run.approve` | maker-checker, stale version |
| submitted | reject | rejected | `payroll.run.reject` | missing reason, stale version |
| rejected/failed/validation_failed | recalculate | calculated | `payroll.run.calculate` | invalid/stale snapshot or version |
| approved | finalize | finalized | `payroll.run.finalize` | issue, aggregate/version inconsistency |

Invalid transitions are controlled domain validation/conflict responses; no Controller changes status directly. The typed action ViewModel also checks Policy, tenant access, status and maker-checker restrictions, while backend authorization remains authoritative.

## Manual browser evidence

On MySQL `schema_admin_testing`, two isolated authenticated sessions completed Period `2027-03-BROWSER`, Snapshot `#5/V1`, Run `RUN-2027-03-BROWSER-V1`, two items, foundation gross/net `10,000,000 VND`, calculation V1, Submit by maker, Approve and Finalize by approver. The final UI displayed five history entries and no mutate action. Browser console errors: none. Production was not seeded. Live two-browser Reverb refresh: `NOT_VERIFIED` because broadcast was deliberately disabled on the isolated test server.

## Deferred scope

Full Payroll Engine remains `PARTIAL`. Tax and statutory insurance are `NOT_STARTED`; Payslip is `NOT_STARTED`; Bank Payment and Accounting Integration are `NOT_CONNECTED`; multi-currency is foundation only. Off-cycle, adjustment/reopen and accounting posting are not part of Phase 3B-3.

## Final verification

- End-to-end HTTP: 3 tests / 48 assertions.
- Payroll Feature: 21 / 285; Payroll concurrency: 7 / 46.
- Full Unit: 25 / 61; Full Feature: 116 / 1,013; Full PHPUnit: 155 / 1,252.
- Payroll PHPStan level 10 and Payroll Feature-test PHPStan: 0 errors.
- Pint, Vite build, Docker Compose config, 35 Payroll routes and production migration status through batch 25 passed.
- Final benchmark: 1,000 items, 5,000 component rows, 885.25 ms calculation, 10 MiB peak delta, calculation V1.
- Production Period/Snapshot/EmployeeInput/InputIssue/Run/Item/Component counts stayed `1/0/0/0/0/0/0`.

`Phase 3B-3 — COMPLETED`. Full Payroll Engine remains `PARTIAL`.
# Phase 3B-4 input contract

Payroll Run calculation continues to read the exact persisted snapshot. Weekday, rest-day and holiday overtime minutes are now preserved from `payroll_employee_inputs` into the current-version `payroll_items`; live source changes cannot alter an existing calculation input.
# Phase 3C-1 insurance step

Calculate now resolves effective Insurance Policy/Profile in batch, persists employee/employer contribution lines and exposes net before personal income tax. Runs without an active policy retain legacy foundation behavior; an active policy makes incomplete insurance configuration blocking.

Phase 3C-2 adds an optional configured Tax layer after Insurance. When an active Tax Policy exists, Calculate resolves the exact employee Tax Profile, effective approved dependents, salary-component tax classification, configured deductions and marginal/flat brackets at Payroll Period end. It persists Tax Policy/version, deduction snapshots, bracket lines, withholding and net-after-personal-income-tax in the same calculation version. Missing/ambiguous configuration is blocking. When no Tax Policy exists, the pre-existing Payroll foundation remains operable and does not pretend that tax was calculated.
## Phase 3D-2 payment instruction boundary

After a Payroll Run is finalized, Bank Payment Batch reads only the exact finalized `payroll_items.net_after_personal_income_tax_minor` and `calculation_version`. It snapshots verified effective bank instructions, then follows `draft → validated → reviewed → approved → released`. Released means a checksum-protected private CSV instruction exists; it does not mean paid or accepted by a bank.
# Phase 3D-3 extension

After Payroll Run `finalized`, Finance can configure Chart of Accounts/Policy/Mapping and execute `Create Journal → Validate → Review → Approve → Internally Post → Export private CSV`. This is an optional downstream accounting foundation and does not change finalized Payroll Items. External ERP posting remains not connected.
# Retroactive extension

Finalized Run → immutable Adjustment Request snapshot → correction snapshot → cumulative delta → validate → submit → review → checker approve → apply → separate statement → positive payment eligibility or negative recovery → delta accounting eligibility. Historical operational flow is never reopened.

## Phase 3E-1C2 downstream flow

`Applied Adjustment → typed intent → dedicated Command/Handler → Downstream coordinator → Payment|Accounting|Statement service → artifact/reference → completed intent`. Payment uses persisted positive net delta only; Accounting uses signed persisted component deltas; Statement renders an immutable private PDF. Each artifact is independently retryable and does not mutate the historical Run, Item, Payslip, Payment Batch or Journal.

## Phase 3E-1C3 final operational proof

The guarded browser fixture executed `finalized historical Run → Adjustment create → calculate → validate → submit → review → checker approve → apply → Payment/Accounting/Statement`. Desktop detail showed exact snapshot/version trace, +250,000 VND correction, a draft Payment instruction, balanced draft Journal and checksum-protected PDF. The 390×844 view preserves actions and download access with no page-level horizontal overflow. Process races prove duplicate execution cannot create duplicate artifacts. Phase 3E-1 is complete; Annual Tax Reconciliation, Direct Bank, external ERP and government filing remain outside this phase.

## Phase 3E-2A annual PIT design (not implemented)

Designed future flow: `finalized Payroll Items + exact component/version trace + applied Adjustment PIT deltas → AnnualTaxSourceCollector → canonical source checksum → calculate annual liability → validate → submit → independent review → approve → finalize`. The scope is employer/company-specific, not full taxpayer reconciliation across employers. Submitted sources are immutable; later sources require draft recalculation or a superseding version. No route, migration, annual row, document or downstream action exists in 3E-2A.

## Phase 3E-2B implemented boundary

The first half of that flow now exists internally: `typed Create Command → idempotent draft/snapshots → typed Collect Command → finalized regular sources + explicitly attributed applied Adjustment sources → source/component persistence → validation issues → canonical per-source and aggregate checksum`. There is still no public HTTP/UI path and no annual calculation or state transition beyond collection; the monthly Payroll/Adjustment end-to-end flows remain unchanged.

Phase 3E-2C extends this to `Calculate → Validate → Submit → distinct Review → distinct Approve → distinct Finalize`, followed by eligibility intents only. Reject is controlled and Finalized may be superseded to a new empty-source draft version. No downstream artifact is executed.
# Annual PIT Phase 3E-2C2 status

Annual PIT public lifecycle routes now use dedicated Commands/Handlers and action permissions. This does not change the existing monthly Payroll E2E flow. Annual PIT remains `PARTIAL` until the complete result-fixture HTTP matrix and isolated browser lifecycle mutation are verified; Phase 3E-2D has not started.
