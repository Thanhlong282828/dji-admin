# Phase 3C-2 — Personal Income Tax Foundation

Status: `COMPLETED` at source/test level. This is a configurable payroll foundation, not a claim of current Vietnamese legal compliance. Electronic filing, annual reconciliation, treaty, foreign-credit and refund workflows are not connected.

## Effective-dated model

`tax_policies` is company-scoped and versioned by `(company_id, code, version)`. Resolution uses Payroll Period `period_end`, matching Insurance. Active policies are keyed by residency group; overlapping active ranges are rejected. `tax_policy_brackets` stores ordered integer-minor thresholds and basis-point rates. `tax_policy_deductions` stores personal, dependent, employee-insurance and configured deductions without statutory constants in source code.

`employee_tax_profiles` is company/employee/effective-date scoped. Overlap is rejected. Tax ID is never broadcast and is masked in read models. `employee_tax_dependents` is the deliberately small tax-only dependent foundation; only approved, eligible, effective records count.

## Calculation

`PayrollTaxIntegrator` batch-loads policies, brackets, deductions, profiles, dependents and component tax classifications once for a run. Earning components marked `taxable` form income; `non_taxable` is excluded and missing/conditional classification blocks a configured Tax run. Employee Insurance contribution is deducted; Employer Insurance never reduces employee taxable income or net.

`taxable = taxable earnings - employee insurance - personal deduction - dependent deduction - configured deductions`. The before-floor value and resolved value are both persisted. Flooring at zero is policy-controlled. `PersonalIncomeTaxCalculationService` supports marginal progressive and flat methods with integer arithmetic and basis points. Rounding is centrally configured as `half_up` or `down`.

The Payroll Item persists policy ID/version, taxable income, withholding, net-before-tax and net-after-tax. Immutable component rows persist taxable summary, deduction snapshots and bracket lines under the exact calculation version. Recalculation before submit atomically replaces rows with a new version; submitted/finalized runs cannot recalculate.

## Security and limitations

All HTTP endpoints require `auth`, `verified`, a dedicated permission and `workforce.company` tenant authorization. Foreign records resolve to 404 after company scoping; no company access/missing permission is 403. Generic realtime payloads contain identifiers and refresh scopes only, never Tax ID, dependent names or tax amounts.

No production Tax Policy was seeded. Legal rate verification, government filing, annual reconciliation, retroactive adjustments, refunds, treaties, foreign tax credit, Payslip, Bank Payment and Accounting Posting remain outside this phase.
