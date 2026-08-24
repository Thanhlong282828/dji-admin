# Enterprise Forecasting Design — Phase 3F-26

## Boundary

`/admin/analytics/forecasting` is protected by `auth`, `verified`, `analytics.view`, and company access. Headcount remains visible to an authorized analytics viewer. Cost values are not rendered unless the actor also has `payroll.assignment.view`. CSV export additionally requires `analytics.export`.

## Runtime flow

`WorkforceForecastController`
→ validates company, unit, forecast month, and at most 50 scenario lines
→ `WorkforceScenarioLine`
→ `WorkforceForecastService`
→ grouped Organization/Employee/Recruitment/Payroll reads plus `CompensationReadService`
→ `WorkforceForecastViewModel`
→ forecasting dashboard or streamed CSV.

The request has no Save action. Lines are assigned to Scenario A or Scenario B so both can be compared with the same baseline in request scope. Reset drops scenario query parameters. Export contains aggregate metrics and assumptions only.

## Projection semantics

- Add/remove applies only when `effective_month <= forecast_month`.
- Promotion keeps headcount unchanged and uses target minus source position reference; target level must be higher.
- Monthly scenario target gross is base + KPI target + fixed allowance delta. Annualized target gross is monthly delta × 12 using integer minor units.
- Recruitment demand is remaining vacancies on open requisitions. Candidate coverage uses non-terminal applications.
- Unknown compensation is excluded and disclosed as a warning.
- Payroll tax, insurance, employer contributions, overtime, attendance outcomes and Net Pay are excluded.

## Security, performance and lifecycle

- Every DB read is company scoped before data reaches the view.
- Salary masking is server-side; unauthorized HTML and export contain no salary amounts.
- Position and assignment inputs are aggregated in bounded queries; scenario math is in memory over at most 50 lines.
- No Employee, Assignment, Salary Assignment, Payroll Run or Payroll Item is written.
- No migration, queue, realtime or external traffic is required.

## Assumption disclosure

The page always states that the result is a planning estimate, not committed Payroll, and lists the canonical inputs, exclusions and incomplete-data warnings.
