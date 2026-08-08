# Annual PIT reconciliation domain design

Status labels used here: `DESIGNED`, not `IMPLEMENTED`. Phase 3E-2A creates documentation only.

## Aggregate and invariants

Aggregate key: Company + Employee + tax year + jurisdiction + currency. Only one active reconciliation version may exist. A nullable database `active_intent_key` is set to `company:employee:year:jurisdiction:currency` for active states and cleared on supersede/cancel; a unique `(company_id, active_intent_key)` constraint provides database enforcement. `idempotency_key` is separately unique per Company.

All money is signed/unsigned BIGINT minor units; rates use basis points. Never use float/decimal arithmetic in the annual engine.

Result types: `additional_tax_payable`, `tax_refund`, `balanced`, `blocked`. Invariant:

`signed_difference = final_tax_liability - tax_withheld - prior_additional_collections + prior_refunds`

- positive difference => payable equals difference; refund is zero;
- negative difference => refund equals absolute difference; payable is zero;
- zero => balanced; both are zero;
- blocking issues => blocked and cannot finalize.

## Lifecycle and versioning

`draft → calculated → validated → submitted → reviewed → approved → finalized`.

Additional transitions: calculated/validated/submitted/reviewed `→ rejected`; draft/rejected `→ calculated`; any non-final active version may be `cancelled`; a finalized or submitted immutable version can only be replaced through a new version that declares `supersedes_reconciliation_id`, eventually marking the old version `superseded`.

Source rows, source checksum, calculation version and identity/policy snapshots become immutable at Submit. New Payroll/Adjustment sources never silently alter a submitted version. Draft/rejected may recalculate atomically by replacing all current calculation-version rows. Finalized cannot recalculate.

Maker-checker: creator cannot review or approve; reviewer cannot approve; finalizer needs a separate permission. Every transition locks the reconciliation, validates expected version/status and writes one history row.

## Proposed additive tables

### `payroll_annual_tax_reconciliations`

Identity: uuid, company_id, employee_id, tax_year, jurisdiction, currency, reconciliation_number, version, active_intent_key, idempotency_key, supersedes/superseded-by IDs.

Snapshots/references: taxpayer_snapshot JSON + checksum, company_tax_identity_snapshot JSON + checksum, calculation_version, source_checksum, annual_policy_reference/version and policy snapshot/checksum.

Totals: gross, regular taxable, retroactive taxable, non-taxable, employee insurance, personal deduction, dependent deduction, other deductions, total deductions, final taxable income, final liability, withheld, prior refunds, prior collections, additional payable and refund—all integer minor units.

Workflow: status, result_type; created/submitted/reviewed/approved/finalized/rejected actors and timestamps; rejection reason; timestamps. Index list/filter by company/year/status/employee and unique active/idempotency/number keys.

### `payroll_annual_tax_sources`

One trace row per counted source: reconciliation/version, source_type, source_id, payroll period/run/item, Payslip, Adjustment request/run nullable references, source period start/end, payment date/year, accounting year nullable, explicit `tax_attribution_year`, source calculation/policy versions, currency, gross/taxable/non-taxable/deduction/PIT-withheld/prior-settlement amounts, source payload/checksum and timestamps.

Source types: `regular_payroll`, `retroactive_adjustment`, `tax_refund`, `additional_tax_collection`, and permission-gated `manual_authorized_correction`. Unique `(reconciliation_id, calculation_version, source_type, source_id)` plus source-company validation prevents duplicates.

### `payroll_annual_tax_components`

Typed annual aggregation/trace: reconciliation, source row nullable, component code/name, category (`regular_income`, `retroactive_income`, `taxable_benefit`, `non_taxable_income`, `employee_insurance`, `personal_deduction`, `dependent_deduction`, `other_deduction`, `pit_withheld`, `settlement`), amount, currency, calculation order, metadata/checksum and calculation version.

### Issues/history/downstream/documents/access

- validation issues: severity warning/blocking, code, safe message, non-sensitive metadata, resolved actor/time;
- histories: action, from/to, actor, safe reason, version before/after and timestamp;
- downstream intents: `additional_tax_recovery`, `tax_refund_payment`, `annual_accounting_journal`, `annual_statement`; status/idempotency/retry/artifact reference, but no execution in 3E-2A;
- documents: private disk/path, filename, MIME/size/SHA-256, source checksum, template/document version and generation metadata; label it an employer annual reconciliation statement, not an official government certificate;
- access logs: company/reconciliation/document, actor/action/time and privacy-preserving request metadata.

## Source checksum

Collector produces canonical, key-sorted, versioned data. Each source row has a SHA-256 payload checksum. The header checksum is SHA-256 over schema version, aggregate scope, ordered `(source_type, source_id, source_calculation_version, tax_attribution_year, currency, source_checksum)` tuples and identity/policy snapshot checksums. Submitted and later states reverify it before transition/document/download.

## Services and DTOs

Designed services:

- `PayrollAnnualTaxReconciliationService` aggregate coordinator;
- `AnnualTaxSourceCollector` finalized/applied, tenant-scoped immutable source reads;
- `AnnualTaxCalculator` annual rules and signed result;
- `AnnualTaxValidator` eligibility/invariant validation;
- `AnnualTaxChecksumService` canonical payload and SHA-256;
- `AnnualTaxLifecycleService` locked state/maker-checker/version transitions.

Immutable DTOs: `AnnualTaxReconciliationData`, `AnnualTaxSourceData`, `AnnualTaxComponentData`, `AnnualTaxCalculationResult`, `AnnualTaxValidationResult`, `AnnualTaxLifecycleResult`. DTOs are `final readonly`, contain typed properties/list shapes, and never contain Request, Builder, Model or Closure.

## CQRS contract

Commands: Create, Calculate, Validate, Submit, Review, Approve, Finalize, Reject and Supersede Annual Tax Reconciliation. Handlers call the aggregate/services through the existing Command Bus. Commands carry IDs, expected version, idempotency key and actor—not HTTP requests.

Queries: list, detail, audit and document download through the existing Query Bus, company-scoped read repository and typed ViewModels/resources. Controllers only validate, authorize, dispatch and return responses.

## Validation model

Blocking by default: `missing_tax_identifier`, `missing_tax_residency`, `missing_tax_policy_snapshot`, `missing_personal_deduction_snapshot`, `missing_dependent_evidence`, `unsupported_multiple_employer_case`, `source_calculation_version_missing`, `source_checksum_mismatch`, `currency_mismatch`, `tax_year_mismatch`, `unfinalized_payroll_source`, `unapplied_adjustment_source`, `duplicate_source`. Jurisdiction policy may downgrade only explicitly safe conditions to warning; blocking issues prevent Submit/Finalize.

## Permissions and tenant security

Designed permissions: `payroll.annual_tax.view/create/calculate/validate/submit/review/approve/finalize/supersede/document.download`. They are not seeded in 3E-2A. All routes will use auth, verified, permission and Company context. Missing permission/company access returns 403. An ID not belonging to the authorized Company returns 404. Collector applies Company + Employee + year scope to every source and rejects manual foreign IDs.

## Future downstream boundaries

Additional payable may create a future payroll deduction/recovery obligation; refund may create a future payment instruction; signed delta may create a future annual accounting journal; approved/finalized data may generate a private statement. None is implemented or automatically dispatched in 3E-2A. Direct bank, external ERP and government filing remain `NOT_CONNECTED`.

## Concurrency test plan

Independent MySQL process cases: Create/Create, Calculate/Calculate, Calculate/Submit, Submit/Submit, Review/Reject, Approve/Reject, Finalize/Finalize and Supersede/Finalize. Prove one active version, one source snapshot/checksum per calculation version, one transition/history winner and no duplicate document/downstream intent.

## Feature and unit test plan

Feature: regular finalized inclusion; exclusion of non-final Run; Adjustment inclusion/attribution; exact versions; payable/refund/balanced; missing profile/source blocking; maker-checker; permissions; tenant 403/foreign 404; idempotency; Submit immutability; supersede; document authorization/integrity.

Unit: annual aggregation; signed result; payable/refund exclusivity; canonical checksum; attribution rule; lifecycle matrix; issue severity; currency/year/source duplicate invariants.

## Planned implementation sequence

3E-2B: additive schema, enums/DTOs, collector/checksum/read model and migration safety tests. 3E-2C: annual calculation/validation/lifecycle/CQRS/UI. 3E-2D: concurrency, private statement and final browser/full regression. Downstream payment/recovery/accounting and external filing remain separately authorized phases.

## Phase 3E-2B implementation alignment

The 3E-2B portion is now implemented as designed. The collector uses finalized Payroll Item/component versions and applied Adjustments with explicit persisted `tax_attribution_year`; it never derives attribution from timestamps. Draft scope/idempotency/current keys are database guarded. Identity/policy and source/component values are snapshotted, deterministically ordered and SHA-256 checked. Lifecycle/calculator/UI/document/downstream designs remain future work and are not represented as active code.

Phase 3E-2C implements the resident progressive strategy using immutable annual policy brackets and integer basis points, deterministic validation, maker-checker lifecycle, supersede versioning and secured admin UI. Non-resident/multiple-employer cases remain blocked. Phase 3E-2D still owns private statement, downstream execution, process races and browser completion.
# Phase 3E-2C2 lifecycle and authorization

`draft → calculated → validated → submitted → reviewed → approved → finalized`; submitted/reviewed may be rejected and finalized may be superseded into a new draft version. Creator cannot review/approve, reviewer cannot approve, and approver cannot finalize. Each action has a dedicated `payroll.annual_tax.*` permission and Company scope is verified before dispatch. Foreign record IDs resolve as 404 after the authorized Company context is established.

Result invariants use integer minor units: `liability - withheld - prior settlement = payable - refund`; payable and refund cannot both be positive, and result type must match its amounts.
