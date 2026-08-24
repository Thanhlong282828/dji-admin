# Enterprise Planning Execution Design

## Architecture

`GET /admin/planning/execution` resolves `PlanningExecutionFilter`, reuses `ExecutivePlanningService`, adds one grouped monthly completed-hire read, calculates typed variance/status values with `ExecutionVarianceMath`, and returns `PlanningExecutionViewModel` to the black-gold Blade dashboard. The export route uses the same read model and emits aggregate, formula-safe CSV.

## Deterministic model

- Variance = Actual − Target.
- Variance basis points = `(Actual − Target) × 10,000 / Target`; zero target returns zero deterministically.
- Reforecast Payroll = Forecast v1 adjusted by signed basis points using integer money math.
- `BLOCKED` wins when Data Quality blocks interpretation.
- Equal values are `ON_TARGET`; a non-zero difference within five percent is `WARNING`; remaining negative/positive differences are `UNDER_PLAN`/`OVER_PLAN`.

## Dashboard and security contract

The page contains Execution Summary, Headcount/Payroll/Hiring Target vs Actual, variance charts, a unit matrix, explicit Reforecast v2, root-cause aggregates, Management Attention and assumptions. Reforecast offers Compare and Reset only; there is no Save, Submit or Approve action.

Financial cards, rows, reforecast input and exports are server-side omitted/masked without `payroll.assignment.view`. Planning Execution is one primary destination beside Analytics, Forecasting and Executive Planning; its disjoint active route pattern preserves no-flicker navigation.

## Future boundary

A future persistent plan/reforecast must be explicitly authorized and versioned, effective-dated, append-only after approval, safely audited, protected by maker-checker and concurrency tested. This phase does not imply that foundation exists.
