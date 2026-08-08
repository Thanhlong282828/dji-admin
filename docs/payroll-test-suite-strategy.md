# Payroll test-suite strategy

Ordinary Feature tests use Laravel `RefreshDatabase` transactions after a single schema migration per PHPUnit process. Process concurrency tests own their fixtures, guard `schema_admin_testing`, synchronize workers with filesystem barriers and clean up in `finally`.

Required independent jobs:

1. Unit and focused Payroll Feature tests.
2. Payroll and Workforce MySQL process concurrency.
3. `payroll-performance` and `payroll-large-export` benchmarks.
4. Full Unit, Feature and default PHPUnit suites.
5. Pint, PHPStan level 10, Vite and Docker Compose validation.

No suite result is inferred from another job; a suite is marked passed only when its command exits 0.

Phase 3B-3 adds `PayrollEndToEndOperationalFlowTest`: a non-mocked HTTP path through Period creation/open, capture/validation, Run creation/calculation, item inspection, submit, maker-checker approve, finalize, immutable terminal behavior, history, notifications and exact-snapshot proof. Negative cases cover tenant/permission and invalid state transitions. `PayrollBrowserFixtureSeeder` is guarded to databases ending `_testing` and exists only for manual UI verification.
# Phase 3B-4 coverage

`PayrollInputOperationsTest` now verifies effective-calendar classification, rest-day classification, company-holiday precedence, cross-midnight splitting, immutable source trace, employee list/detail HTTP access and exact snapshot behavior.
# Phase 3C-1

Coverage includes policy/profile authorization, company scoping, overlap rejection, masked identifier, integer rounding, cap/floor, employee/employer separation, calculation integration and immutable persisted rate/base.
## Bank Payment Batch coverage

`PayrollPaymentBatchFoundationTest` performs HTTP integration against MySQL test fixtures for encryption/masking, effective verification, finalized amount/version, immutable instruction snapshot, maker-checker, idempotency, private CSV/checksum, formula-injection defense, secure download and tenant authorization. Payroll Feature, MySQL concurrency, Unit, full PHPUnit, PHPStan level 10, Pint, Blade and Vite remain required regression gates.

Payment Batch concurrency is not simulated in-process: `MysqlProcessRaceRunner` starts two isolated PHP workers against `schema_admin_testing` for Create/Create, Approve/Reject, duplicate Approve, duplicate Release and Release/Reject.
# Phase 3D-3 focused coverage

`PayrollAccountingPostingFoundationTest` covers HTTP authentication, permission, tenant scope, operational Account/Policy/Mapping configuration, overlap, finalized-only source, missing/inactive mapping, exact persisted calculation, source trace totals, balance, duplicate active intent, maker-checker, private CSV/checksum/download, formula injection, immutability and reversal. `PayrollProcessConcurrencyTest` adds MySQL process races for create/create and approve/reject.
# Adjustment test strategy

`PayrollAdjustmentLifecycleTest` exercises the HTTP boundary against MySQL: auth/permission/company scope, finalized-only source, idempotency, exact calculation version, positive and negative deltas, cumulative applied delta, maker-checker, recovery semantics, separate statement/downstream intent and historical Payroll immutability.
