# Enterprise Forecasting Audit — Phase 3F-26

## Scope and evidence

The audit used the registered Analytics, Organization, Employee, Recruitment and Payroll code paths. Forecasting is deterministic planning; it is not AI/ML, a statutory estimate, or committed Payroll.

| Planning input | Canonical source | Forecast use | Constraint |
|---|---|---|---|
| Approved headcount | `job_positions.approved_headcount` | Budget capacity and vacancy | Company/unit/position scoped |
| Actual workforce | Effective primary `employee_assignments` | Current headcount | One current primary assignment invariant |
| Career level | `job_positions.career_level` | Position mix and promotion delta | Level 1–6 |
| Current compensation | Effective `employee_salary_component_assignments` + `salary_components` | Monthly baseline | Requires `payroll.assignment.view` |
| Position compensation | `PositionCompensationReference` | Scenario target cost | Synthetic planning reference v1, not Payroll result |
| Recruitment demand | Open `recruitment_requisitions`, `vacancies - filled_hires` | Unfilled demand | Deterministic current state |
| Candidate coverage | Non-terminal `applications` | Candidate/vacancy ratio | Aggregate only |
| Organization labels | `organization_units`, `job_positions` | Group/filter | Tenant scoped |

## Findings

- Existing Phase 3F-25 analytics already provides tenant authorization and compensation permission boundaries.
- Existing tables are sufficient. No persistence or migration is justified for request-scoped scenarios.
- Payroll historical tables must not be a scenario target. Finalized run/item values remain historical actuals.
- Tax, insurance and employer-cost forecasting is excluded because Phase 3F-26 does not infer rates or legal policy.
- Scenario inputs are bounded to 50 lines across Scenario A/B, quantity 1–100, effective month, company-owned positions, and three explicit operations: add, remove planned headcount, promote level mix.
- Missing salary assignment is surfaced as an aggregate warning rather than silently replaced with a fabricated actual salary.
- Query shape is grouped/batched and independent of employee count; there is no query-per-employee loop.

## Classification

- **Actual:** current effective assignment and salary assignment evidence.
- **Budget:** approved headcount.
- **Forecast:** deterministic end-state at a selected month.
- **Scenario:** request-scoped what-if delta. It is never persisted.

No production/VPS access, external forecasting service, or database mutation is part of this phase.
