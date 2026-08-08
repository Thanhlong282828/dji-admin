# Phase 3C-1 — Statutory Insurance Foundation

This is a configurable payroll insurance foundation, not a statement of current Vietnamese statutory compliance. No legal rates are seeded or hard-coded.

## Effective policy and participation

`insurance_policies` are company-scoped, versioned and effective-dated. Each policy owns typed rate rows with employee/employer basis points and optional per-type floors/caps. Active policy ranges may not overlap. `employee_insurance_profiles` record effective participation, optional policy selection, masked insurance identifier, explicit base override, currency and exemption reason; profile ranges may not overlap.

## Calculation

Salary components use the existing `insurance_status`: `included`, `excluded`, `conditional`, or `not_configured`. When an active policy exists, participating employees require exactly one profile, a same-company effective policy, rates, matching currency and classified components. Missing/ambiguous configuration produces blocking `payroll_validation_issues`.

The base resolver uses approved profile override first, otherwise included calculated components. The calculator applies per-rate floor/cap and integer basis-point arithmetic through one deterministic `half_up` or `down` rounding service. Binary floats are not used.

Employee contributions increase deductions and reduce net-before-personal-income-tax. Employer contributions increase employer cost only. Contribution lines persist policy ID/version/effective date, party, type, base, rate, amount and calculation version in `payroll_item_components`. Existing calculated rows do not change when live policy/profile data changes; recalculation before submit replaces calculation rows atomically under a new version.

## Security and limitations

Insurance routes require `auth`, `verified`, dedicated permission and verified company context. Read repositories scope all IDs to company; insurance numbers are masked in list/activity surfaces. Broadcast salary/insurance amounts are prohibited.

Personal Income Tax, payslip, filing/XML, retroactive adjustment, bank and accounting integrations remain outside this phase.
