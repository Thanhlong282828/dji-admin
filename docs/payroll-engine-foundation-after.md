# Phase 3B-1 Payroll Engine Foundation — after implementation pass

Status: **PARTIAL** (2026-07-26). Phase 3A remains **COMPLETED**.

Completed and executable: additive batch-22 schema, integer-minor Money value object, fixed/percentage/quantity-rate calculators, generic earning/deduction/employer-contribution aggregation, immutable employee/component snapshots, validation issues, relational history, create/calculate/recalculate/submit/approve/reject/finalize service lifecycle, optimistic versions, UUID idempotency markers, snapshot linkage, activity events, company-scoped cache invalidation and dedicated Payroll Run Policy foundation.

Verified deterministic fixture: gross 5,700; deduction 100; generic employer contribution 300; net 5,600; one item and five immutable component lines. Missing rate and negative net produce validation failure rather than silent zero/clamping.

Phase 3B-1B progress: Salary Component and Assignment domain validation, transactional overlap protection, effective ending, typed Commands/Handlers, dedicated Policies and `PayrollConfigurationChanged` cache event are implemented. Remaining blockers are HTTP controllers/requests/routes/UI, Query Bus repositories/DTOs, Payroll export, durable Payroll notification recipient listener, realtime DOM scopes, benchmark, and the seven new process-level Payroll races. Therefore Phase 3B-1 cannot yet be marked completed.

Truthful deferred scope: Full Payroll PARTIAL; Tax NOT_STARTED; Statutory Insurance NOT_STARTED; Bank Payment NOT_CONNECTED; Payslip NOT_STARTED; Accounting Integration NOT_CONNECTED.

Quality evidence: Payroll Unit/Feature `8 tests / 29 assertions`; Policy Matrix `3 / 263`; existing Phase 3A MySQL concurrency `7 / 132`; last full PHPUnit before the Phase 3B-1B configuration additions `129 / 931`; Pint passed; all seven module PHPStan level-10 runs returned zero errors. Migrations remain valid through batch 22 and production Payroll business tables remain at zero rows.
# Phase 3B-1D progress — 2026-07-28

- Added tenant-scoped streaming employee-payroll CSV export.
- Added tenant-scoped streaming component-breakdown CSV export.
- Both exports use Query Bus, dedicated export read repository, cursor ordering, UTF-8 BOM and the shared formula-injection sanitizer.
- Added queued `SendPayrollRunNotifications`; safe payload excludes salary amounts and delivery remains idempotent per channel through `notification_deliveries`.
- Stabilized process race harness and verified the existing Workforce concurrency suite.
- Phase remains **PARTIAL**: Payroll-specific process scenarios, export HTTP/content tests, realtime authorized fragments, performance benchmarks and a normally completed full Feature suite are still required.
- Phase 3B-1F closes the primary Payroll calculation/transition process races and notification delivery race.
  Remaining closure blockers are partial-channel retry, export HTTP/security tests, realtime fragments,
  performance benchmarks and full Feature/Full PHPUnit completion.
## Phase 3B-1G verification

Notification partial-channel retry, both Payroll CSV HTTP contracts, export authorization/tenant isolation, six authorized Run fragments, realtime partial-refresh source contract, the 1,000-employee calculation benchmark and 5,000-row export benchmark are implemented and verified. Full Unit (25/61), Feature (107/892) and PHPUnit (146/1,131) returned exit code 0. Phase 3B-1 is `COMPLETED`; no deferred Phase 3B-2 capability is represented as complete.
## Phase 3B-2A Payroll Input Operations

Operational list/create/detail, approved-source capture, persisted readiness issues/history, immutable versioning and exact-snapshot Payroll Run creation are implemented on additive batch 23. Phase 3B-1 semantics remain unchanged. Full Payroll remains partial because statutory and delivery engines are deferred.

Status: `Phase 3B-2A — COMPLETED` after full PHPUnit 149 tests/1,166 assertions returned exit code 0.

## Phase 3B-2B Payroll Period Operations

Operational Period list/create/detail/edit and lifecycle actions are implemented through CQRS. Existing lifecycle semantics are retained (`collecting` is open and `processed` is closed). Payroll Input create options now include only open same-company periods and expose a controlled no-period path. Phase 3B-2B is completed independently of Tax, Insurance, Payslip, Bank and Accounting engines.

Phase 3C-2 completes the configuration-driven Personal Income Tax foundation: effective/versioned policies, progressive/flat brackets, configured deductions, employee profiles, approved dependents, component tax classification, batch resolution, immutable calculation-version breakdown and net-after-tax. No legal-compliance claim or government filing is included.

Final Phase 3B-2B gate: Payroll Period 3 tests/38 assertions; Payroll Feature 18/237; full Unit 25/61; full Feature 113/965; full PHPUnit 152/1,204; Payroll PHPStan level 10 zero errors; Pint/Vite/Docker config/routes/migrations passed. Production Payroll business counts remain zero.

## Phase 3B-3 operational closure

Payroll Run list/detail/item detail and state-aware actions are connected to the existing engine. Calculate is exact-snapshot-only; submit/finalize enforce current-version aggregate consistency; approve retains maker-checker; reject requires a reason; finalized runs are immutable. Additive batch 25 stores `finalized_by`. Phase 3B-3 may be completed independently, while the Full Payroll Engine remains `PARTIAL` because statutory/tax/payslip/payment/accounting scope is deferred.

Final Phase 3B-3 gate: manual UI workflow completed; E2E 3/48; Payroll Feature 21/285; Payroll concurrency 7/46; Unit 25/61; Feature 116/1,013; full PHPUnit 155/1,252. Payroll and focused-test PHPStan level 10 returned zero errors; Pint, Vite and Docker Compose config passed. Status: `Phase 3B-3 — COMPLETED`; live two-browser Reverb refresh remains `NOT_VERIFIED`.
# Phase 3B-4

Calculation input now preserves the three approved overtime categories. Category-specific statutory rates remain deliberately outside the calculation foundation; Tax, Insurance and Payslip remain not started.
# Phase 3C-1

Configurable Insurance Foundation is integrated into current-version Payroll Items and Run aggregates. It is not statutory compliance certification. Personal Income Tax remains not started.
## Downstream Bank Payment Batch

Phase 3D-2 consumes the finalized net-after-PIT field without recalculating Payroll. Immutable batch items preserve source Payroll Item/version, beneficiary/bank/account ciphertext snapshots, integer minor-unit amount and reference. Direct bank integration and reconciliation remain outside the calculation engine.
# Phase 3D-3 status

Payroll Accounting Posting Foundation is implemented as a downstream immutable read of finalized Payroll results. It adds balanced internal journals, source trace, private generic CSV and reversal controls. It does not turn the system into an external accounting/ERP integration.
# Phase 3E-1 status

Retroactive Adjustment Foundation adds delta-only, cumulative, immutable correction processing. Full Payroll Platform remains PARTIAL; annual reconciliation and external government/bank/ERP connectivity remain outside this phase.
