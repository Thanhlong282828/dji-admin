# Enterprise Executive Planning Design

## Architecture

`ExecutivePlanningController` validates tenant and permission boundaries, creates a typed `PlanningFilter`, and delegates to `ExecutivePlanningService`. The service performs grouped reads over Organization, Employee Assignment, Recruitment and Payroll, reuses `CompensationReadService` and `DataQualityAuditService`, and returns `ExecutivePlanningViewModel`. The Blade view renders only the fields present in that model.

No persistent Plan is introduced. Budget, Actual, Forecast and Scenario remain distinct:

```text
Organization / approved headcount / position budget
                    +
Employee current primary assignment / Recruitment / Payroll
                    ↓
          computed Executive Planning baseline
                    ↓
          variance, warning and drill-down
```

## Calculation contract

- Vacancy = `max(approved headcount − current headcount, 0)`.
- Position budget uses explicit `job_positions.budgeted_cost`; when zero, it falls back to approved headcount × deterministic position compensation reference.
- Forecast monthly cost = current filled headcount × target compensation reference.
- Variance = Forecast − Budget; percentage uses integer basis points and returns zero for a zero budget.
- Annualized values multiply monthly integer minor units by 12.
- Actual Payroll is the latest eligible calculated/reviewed/finalized run ending inside the selected month. Future months have no fabricated Actual.
- Recruitment demand and candidate/interview/offer pipeline are separate aggregates joined by position.

## UI and navigation

The protected primary page is `GET /admin/planning` (`admin.planning.executive`). It sits with Enterprise Analytics and Workforce Forecasting in the collapsed Overview group. The cockpit provides eight KPI cards, five accessible visual summaries, responsive variance and position tables, deterministic warnings, filters and aggregate CSV export. It uses the established black-gold design and contained table scrolling on mobile.

Contextual help describes Organization → Headcount/Employee/Recruitment → Analytics → Forecasting → Executive Planning. Salary values are omitted server-side unless the actor has `payroll.assignment.view`. Export additionally requires `analytics.export`.

## Approval and persistence decision

Persistence: **NO**. Approval/versioning: **N/A**. A future persistent plan requires an identified owner, effective-dated versions, immutable approved baselines, maker-checker permission contracts, audit evidence and concurrency protection. This phase does not create placeholder tables or fake statuses.

## Performance and data quality

The service does not query per employee or per unit. Query-count regression compares the same read with zero and 50 additional Employee assignments. Data Quality findings are surfaced as warnings and must be repaired through the owning module; the cockpit does not mutate data.
