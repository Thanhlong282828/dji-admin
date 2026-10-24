# Enterprise Executive Planning Audit

## Decision

Phase 3F-27 implements a computed, read-only management cockpit. It does not add a planning database or claim that a computed baseline is an approved plan. The repository has no authoritative plan owner, version contract, approval state machine, or approval permission for a persistent budget plan; adding `planning_plans` would therefore invent governance rather than complete existing business behavior.

## Canonical semantics and sources

| Semantic | Current source | Meaning in the cockpit |
|---|---|---|
| Approved headcount | `job_positions.approved_headcount` | Organization-owned capacity ceiling |
| Current/Actual headcount | effective current primary `employee_assignments` | People assigned at the selected month |
| Position budget | `job_positions.budgeted_cost` | Explicit position budget; zero falls back to approved HC × compensation reference |
| Actual Payroll | latest eligible `payroll_runs.gross_total_minor` in the selected month | Historical/calculated Payroll result; never rewritten |
| Latest Forecast | current filled positions × Position Compensation Reference | Deterministic target-compensation estimate |
| Scenario | Phase 3F-26 request-scoped scenario | Temporary what-if; not silently persisted |
| Hiring demand | open requisition vacancies minus filled hires | Recruitment coverage for position vacancies |
| Quality warning | Data Quality snapshot | Warning only; the cockpit never auto-fixes source data |

Cost Centers and Organization Units provide aggregation dimensions. Salary assignments and position compensation references remain Payroll/Compensation-owned. Candidate, application, interview and offer aggregates remain Recruitment-owned.

## Gaps and constraints

- No approved Plan Version exists, so Plan versioning, maker-checker, approval, concurrency and Scenario-to-Plan are N/A in this phase.
- Cost figures require `payroll.assignment.view`; `analytics.view` alone exposes headcount, not restricted financial values.
- There is no external BI/AI call and no invented tax, insurance or employer-cost forecast.
- Recruitment demand and pipeline are aggregated independently before joining, preventing one-to-many joins from multiplying vacancy totals.
- No index or migration is justified: the read path uses bounded grouped queries and existing indexed tenant/relationship columns.

## Ownership and safety

Planning owns no business tables. Filters are company-scoped and validate Cost Center → Unit → Position relationships. Exports are aggregate, permission-protected and formula-injection-safe. Historical Payroll, Employee assignment, recruitment records and approved headcount are read-only to this feature.
