# Phase 3E-2A — Annual PIT reconciliation audit

Status: **AUDIT/DESIGN ONLY**. No migration, permission, route, annual reconciliation row, document, payment, recovery or journal exists in this phase.

## Scope conclusion

The current data can support a future **single-company, single-employee, single-tax-year, single-currency, single-jurisdiction employer-scoped reconciliation**. It cannot claim a taxpayer's complete annual liability when income exists at another employer. Multiple-employer reconciliation is `NOT_SUPPORTED`; government filing, legal rate certification, tax treaties and foreign tax credits require external/legal integration.

Monthly Payroll PIT is withholding. It is not annual final liability. A future annual aggregate must separately persist final liability, tax withheld, prior settlements, additional payable and refund.

## Code and schema inspected

- `PayrollRunService`: calculates each item using Payroll Period end, persists item/component calculation versions and blocks finalization on calculation issues.
- `PayrollTaxIntegrator`: batch-resolves effective policy, profile, approved dependents and salary-component tax classifications; calculates immutable monthly inputs using integer minor units.
- `PersonalIncomeTaxCalculationService`: progressive/flat calculation with basis points and configured rounding.
- PIT migration batch 28: policies, brackets, deductions, employee profiles/dependents and PIT columns on Run/Item.
- `PayslipSnapshotBuilder` plus batch 29: checksum-protected snapshot of period, identity, totals, policy references and component rows.
- Adjustment batches 32–33 and `PayrollAdjustmentManagementService`: immutable historical/correction snapshots and applied signed PIT delta.
- Employee, assignment, employment contract and Company schemas: employment dates, effective assignments/contracts and Company country/tax code.

## Persisted-source matrix

| Annual fact | Current authoritative persistence | Availability | Gap/decision |
|---|---|---|---|
| Payroll period/year | `payroll_periods.period_start/end/payment_date` | yes | annual source must use an explicit attribution rule, never `created_at` |
| Finalized status | `payroll_runs.status/finalized_at` | yes | collector accepts only `finalized` |
| Gross income | `payroll_items.gross_amount_minor` | yes | currency scoped |
| Taxable earnings/components | `payroll_item_components` earning rows + classification-derived `TAXABLE_INCOME` | partial but reproducible | salary rows do not persist taxable flag directly; use persisted tax summary/components under exact version |
| Non-taxable income | earning component rows exist | partial | explicit annual category/classification snapshot is missing |
| Assessable/taxable before floor | `taxable_income_before_floor_minor` | yes | signed BIGINT |
| Taxable after floor | `taxable_income_minor` | yes | unsigned BIGINT |
| Employee insurance deduction | Item total + `TAX_DEDUCTION_INSURANCE` component | yes | exact calculation version required |
| Personal deduction | `TAX_DEDUCTION_PERSONAL` component | yes | amount snapshot exists; eligibility/profile identity snapshot does not |
| Dependent deduction | `TAX_DEDUCTION_DEPENDENT` component | yes | amount exists; dependent identities/evidence and count are not snapshotted |
| Other deductions | `TAX_DEDUCTION_OTHER` component | yes | category is currently aggregated |
| PIT withheld | `personal_income_tax_minor` + PIT bracket component rows | yes | this is monthly withholding only |
| Policy/version | `tax_policy_id`, `tax_policy_version`, component metadata | yes | bracket rows are persisted under calculation version |
| Tax residency | live `employee_tax_profiles.residency_status` | live only | future annual source/header must snapshot it; missing/unknown blocks |
| Tax identifier | live employee tax profile | live only | future taxpayer snapshot; missing blocks according to jurisdiction rules |
| Employment start/end | `employees.join_date/termination_date`, contracts/assignments | live effective records | Payroll source remains authoritative; dates are eligibility validation context and need annual snapshot |
| Multiple contracts/assignments | effective tables exist | internal only | no cross-employer income model |
| Company legal/tax identity | Company name/code/country/tax_code/legal representative | partial live | future immutable Company tax identity snapshot; jurisdiction registration metadata may be required |
| Payslip | snapshot payload/checksum and exact Item/version | yes where generated | collector must not require Payslip if finalized Item is authoritative, but links it when available |
| Retroactive PIT | applied Adjustment Run `delta_personal_income_tax_minor` | yes | explicit `tax_attribution_year` is absent and must be added on annual source/reconciliation input |
| Refund already processed | no annual tax source table | no | future typed source/downstream settlement |
| Additional tax collected | no annual tax source table | no | future typed source/downstream settlement |
| External employer income | no model | no | blocking `unsupported_multiple_employer_case` |

## Authoritative source decision

The future collector reads only:

1. finalized `payroll_runs` and their exact-version `payroll_items`/`payroll_item_components`;
2. associated immutable Payslip snapshots when present, as corroborating/document trace rather than a separately counted income source;
3. applied `payroll_adjustment_requests/runs/components/snapshots` with an explicit tax attribution year;
4. persisted annual settlement/manual-authorized sources introduced by the future annual schema.

It must never recalculate past months from current policy, profiles, dependents, salary assignments, contracts, attendance, insurance or brackets. Payslip and Payroll Item are not double-counted.

## Adjustment attribution

Five dates/periods remain distinct: historical earning period, Adjustment application period, tax attribution year, payment period/year and accounting period/year. The default design derives `tax_attribution_year` from the historical earning period only when a configured jurisdiction rule explicitly permits it, then persists the resolved year on every annual source row. It is never derived from `created_at`. Ambiguous/cross-year cases block for authorized review.

## Eligibility audit

- Resident/non-resident profiles exist and are effective dated.
- Join/termination dates and multiple internal contract/assignment records exist.
- Probation can be represented by contract/employment status but has no annual tax rule engine.
- Missing Tax ID, unknown residency, missing profile/policy snapshot and missing dependent evidence can be detected, but not all evidence is immutable today.
- A person working in multiple companies is not globally identified as one taxpayer; Employee is company-owned. Full taxpayer reconciliation across employers is therefore unsupported.

## Missing annual capabilities

No annual header/version, source trace, annual components, annual liability engine, issue/history lifecycle, active-version database constraint, annual checksum, annual document, annual settlement, government filing, annual permissions/routes/UI, or annual concurrency tests exist. Phase 3E-2B must start with an additive migration and typed domain implementation; it must not rewrite batches 1–33.

## Production audit

Database is MySQL `schema_admin`; batches 1–33 are `Ran`. Before design work: Payroll Runs 0, Payroll Items 0, Payslips 0, Adjustment Requests/Runs 0, Payment Batches 0 and Accounting Journals 0. This phase performs read-only production checks and creates no production business rows/files.

After documentation work the same production counts remain `0`; proposed annual header/source tables do not exist and annual reconciliation files found under `storage/app` are `0`. No production insert/update/delete, seed, migration or artifact generation was executed.

## Quality-gate result

- Payroll Feature: 53 tests / 689 assertions, exit 0 on MySQL `schema_admin_testing`.
- Unit: 30 tests / 75 assertions, exit 0.
- PHPStan `Modules/Payroll/app` level 10: 0 errors.
- Pint, Blade compile/cache, Vite and Docker Compose config: passed.
- Migrations through batch 33 remain `Ran`.

Phase 3E-2A Annual PIT Reconciliation Audit and Domain Design: **COMPLETED**. Annual Tax Reconciliation Foundation: **PARTIAL** because schema and business implementation have intentionally not started.

## Phase 3E-2B implementation audit

The design was implemented through additive batches 34–35. All eight Annual Tax tables, explicit nullable Adjustment attribution, typed enums/DTOs/model/repository, idempotent draft creation, exact finalized/applied source collection, validation issue persistence and canonical checksum verification now exist. Integration testing found and corrected the original Item uniqueness collision without editing batch 34. Production Annual Tax rows/files remain zero. Calculator, lifecycle after source collection, HTTP/UI/documents/downstream and process races remain intentionally not implemented.
