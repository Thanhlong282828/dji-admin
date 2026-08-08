# Phase 3E-2B — Annual PIT immutable source foundation

Status: **COMPLETED (foundation only)**. This phase implements schema, draft creation, immutable source collection and canonical checksums. It does not implement the annual calculator, lifecycle after draft, UI, documents, recovery/refund, accounting posting or government filing.

## Database

Batch 34 creates eight additive tables: `payroll_annual_tax_reconciliations`, `payroll_annual_tax_sources`, `payroll_annual_tax_components`, `payroll_annual_tax_validation_issues`, `payroll_annual_tax_histories`, `payroll_annual_tax_documents`, `payroll_annual_tax_downstream_intents`, and `payroll_annual_tax_access_logs`. It also adds nullable, indexed `payroll_adjustment_requests.tax_attribution_year`; no historical value is inferred or backfilled.

Batch 35 corrects source uniqueness after integration testing proved that a regular source and its retroactive Adjustment legitimately reference the same Payroll Item. The final key is `(annual_tax_reconciliation_id, source_type, payroll_item_id)`; Adjustment run and canonical source-reference guards remain unique.

All monetary fields use signed/unsigned `BIGINT` minor units according to their semantics. Foreign keys and indexes have explicit MySQL-safe names. Production Annual Tax tables remain empty.

## Implemented flow

`CreateAnnualTaxReconciliationCommand` is handled through the shared Command Bus and `CreateAnnualTaxReconciliationHandler`. `PayrollAnnualTaxReconciliationService::create()` scopes Company/Employee, captures immutable taxpayer, Company and Tax Policy snapshots, persists a draft/history atomically, and returns the same aggregate for a repeated Company/idempotency key. Database unique keys guard idempotency and one active Company/Employee/year aggregate.

`CollectAnnualTaxSourcesCommand` delegates to `AnnualTaxSourceCollector`. Eligible regular sources are exact-version Payroll Items belonging to finalized Runs. Eligible retroactive sources are applied Adjustment Runs whose request has an explicit matching `tax_attribution_year`. The collector never recalculates from live Attendance, Leave, Overtime, profile, dependent or policy data.

Missing attribution, policy/identity data, version data, mismatched currency, absent source data and unavailable historical dependent evidence produce typed safe validation issues. A missing Adjustment attribution is blocking and is never replaced with the current or payment year.

`AnnualTaxChecksumService` canonicalizes associative keys, preserves list order after deterministic source sorting, hashes every source including component snapshots, then hashes the reconciliation scope and ordered source hashes using SHA-256 and checksum version `annual-tax-source-v1`. `verify()` compares stored per-source and aggregate checksums and detects persisted source/component tampering.

Source recollection is allowed only in `draft` or `calculated`; submitted and later aggregates are immutable at this boundary. Phase 3E-2C will own the final annual calculator/validator. Phase 3E-2D will own lifecycle, authorization/UI, document and downstream operations.

## Verification

- Foundation tests: 5 tests, 60 assertions.
- Payroll Feature regression: 57 tests, 740 assertions.
- Full Unit suite: 31 tests, 91 assertions.
- Payroll process concurrency: 23 tests, 145 assertions.
- Full PHPUnit: 213 tests, 1,838 assertions.
- PHPStan `Modules/Payroll/app` level 10: 0 errors.
- New test files PHPStan level 10: 0 errors.
- Pint: passed.

## Phase 3E-2C operational layer

Batch 36 adds calculator identity/source-checksum evidence and deterministic validation stage/fingerprint fields. `PayrollAnnualTaxCalculator` calculates resident progressive annual PIT from persisted source totals and the immutable policy snapshot using integer basis points. `PayrollAnnualTaxOperationsService`, `PayrollAnnualTaxValidator` and `PayrollAnnualTaxLifecycleService` implement calculate, validate, submit, review, approve, finalize, reject and supersede with checksum gates and maker-checker separation. Finalize creates eligibility intents only; it does not execute recovery, refund, accounting or statement artifacts.

The admin surface now provides secured list/create/detail pages, lifecycle actions, source/component/issue/history presentation and downstream placeholders. It remains employer-scoped and explicitly does not claim government filing. Private PDF/download, artifact execution, process races and final responsive browser verification remain Phase 3E-2D.
# Phase 3E-2C2 operational completion audit (2026-07-30)

Status: `PARTIAL`. Dedicated HTTP/CQRS boundaries, expanded persisted validation, bounded pagination, security tests, desktop list/create browser checks and regression gates are implemented. Completion is still withheld because the requested payable/refund/balanced/blocked HTTP fixture matrix, per-action successful/foreign Company matrix, full list/detail/audit DTO split, and a browser lifecycle mutation on an isolated testing server are not yet proven.

Desktop 1280×800: list and create pages VERIFIED; console errors 0. Detail and lifecycle mutation: NOT_VERIFIED because production fixtures are prohibited and no isolated browser fixture server was created. Browser foreign-company: NOT_VERIFIED (Feature HTTP isolation is verified). Responsive 390×844: NOT_VERIFIED — deferred to Phase 3E-2D.

- Public HTTP no longer accepts an arbitrary lifecycle action. Nine mutation endpoints dispatch dedicated Commands/Handlers; source collection reuses `CollectAnnualTaxSourcesCommand`.
- Validation stages are typed (`source_collection`, `calculation`, `pre_submit`, `pre_review`, `pre_approve`, `pre_finalize`). Persisted snapshots, sources, checksum and calculated totals are authoritative.
- Active issues use a SHA-256 fingerprint of reconciliation, nullable source, issue code, stage and field. A validation rerun resolves the stage's old active rows and reopens the deterministic row with `updateOrInsert`; resolved rows are excluded from blocking counts.
- Sources/issues/history default to 25 rows and components to 50 rows, each with an independent query key; server maximum is 100.
- This phase creates downstream eligibility intents only. It does not create recovery, refund payment, accounting journal, annual statement PDF or secure download artifacts.

Final quality rerun (2026-08-01): Full PHPUnit on `mysql/schema_admin_testing` passed 221 tests / 1,908 assertions; Payroll Feature passed 64 / 808; Payroll Unit 11 / 37; Full Feature 159 / 1,536; Full Unit 32 / 95. PHPStan Payroll and Payroll tests level 10 both returned 0 errors. Pint, Blade compile/cache/clear, Vite and Docker Compose config passed. Batch 34–36 are `Ran`. Phase remains `PARTIAL` because the explicit result-fixture, complete per-action security matrix, split typed query DTO and isolated browser mutation gates remain unproven.

## Phase 3E-2C completion gate (2026-08-01)

Status: `COMPLETED`. Payable, refund, balanced and blocked fixtures now pass through real HTTP/domain flows. Validation covers missing source, checksum mismatch, missing dependent evidence, missing policy and stale calculation. Maker-checker separation covers creator/reviewer/approver/finalizer; foreign Company IDs return 404 and missing permissions return 403. Reads use separate typed List, Detail, Source and History Queries/ViewModels. The guarded `payroll:annual-tax-browser-fixture` accepts only local/testing plus a `*_testing` database. Browser verification passed at 1280×800 and 390×844 for list/create/detail and lifecycle Validate→Submit readiness. Independent MySQL process races pass for create/create, calculate/calculate and finalize/finalize. Phase 3E-2D remains `NOT_STARTED`.

Final gates: Annual-focused matrix 11 tests/148 assertions; Annual process concurrency 1/9; Payroll Feature 69/901; Payroll Unit 11/37; Full PHPUnit 227/2,010. PHPStan Payroll and Payroll tests level 10: 0 errors. Pint, Blade compile/cache/clear, Vite build and Docker Compose config: passed. Migrations 34–36: Ran.
