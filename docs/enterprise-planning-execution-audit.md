# Enterprise Planning Execution Audit

## Scope and conclusion

Phase 3F-28 audited Executive Planning, Forecasting, Analytics, Organization/Headcount, Employee Lifecycle, Recruitment, Payroll, Compensation, Workflow, IAM and Audit. The repository supports a deterministic, read-only monthly execution control surface. It does **not** contain an approved authority contract for persistent operating targets or reforecast approval. This phase therefore adds no planning table, migration, approval state machine or synthetic accounting record.

## Canonical semantics and sources

| Concept | Current authority | Phase 3F-28 treatment |
|---|---|---|
| Budget | `job_positions.budgeted_cost`, with the documented position-compensation fallback | Read-only monthly payroll target |
| Plan | Approved headcount and governed source configuration | Input intent; not a new persisted record |
| Target | Approved headcount, vacancy and position budget for the selected month/scope | Computed operating target |
| Actual | Effective current primary assignments, completed Candidate Offer hires and eligible Payroll Runs | Read-only facts |
| Forecast | Existing Executive Planning/Forecasting baseline | Preserved as Forecast v1 |
| Reforecast | Explicit user-entered headcount/payroll delta | Request-scoped Reforecast v2; never overwrites v1 |
| Scenario | Phase 3F-26 bounded what-if | Separate from target and reforecast |

Actual hires use `candidate_offers.hired_employee_id` and `hired_at`, grouped by the governed Job Position. Actual Payroll uses the existing eligible-run selection in `ExecutivePlanningService`; no Payroll Run or Item is updated.

## Persistence decision

**No migration.** A persistent target requires named ownership, version authority, approval roles, effective dating and a contract for replacing/superseding an approved version. Those requirements are absent. Workflow/IAM are not wired to a fake approval process. Maker-checker and concurrency are N/A for this request-scoped foundation.

## Security, performance and safety

- `analytics.view` protects aggregate headcount execution; `payroll.assignment.view` is checked before financial values enter HTML; `analytics.export` protects aggregate formula-safe CSV.
- `PlanningFilter` validates company → Cost Center → Unit → Position → Level scope through tenant-owned records.
- The service reuses the fixed-query Executive Planning model plus one grouped completed-hire query. A 50-Employee regression does not increase query count.
- Data Quality below 100 produces `BLOCKED`; the page never auto-corrects business data.
- Management actions are deterministic attention labels only and never execute commands.
- Historical Payroll, Employee assignments, Recruitment records and Forecast v1 remain unchanged. No external BI/AI, Government, ERP or bank traffic is used.
