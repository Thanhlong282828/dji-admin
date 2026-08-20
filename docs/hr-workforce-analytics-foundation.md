# Phase 3E-5E — Workforce Analytics

Workforce Analytics is a company-scoped read model. Its original snapshots read Organization, Employee Lifecycle, Performance and Career evidence. Phase 3F-25 extends the dashboard with read-only aggregate Payroll and effective Compensation sources only when the actor also has `payroll.assignment.view`; it never exposes employee-level salary, tax/bank identifiers, or updates source aggregates.

Batch 44 creates `analytics_calculation_runs`, `workforce_analytics_snapshots` and `analytics_outbox`. Calculations are deterministic and cover headcount/active/inactive, hires, terminations, transfers, promotions, attrition, performance rating, goal completion, critical-position succession coverage, position count and vacancy. Snapshot generation is transactional, checksummed and idempotent.

HTTP flow uses shared CQRS buses. Permissions are `analytics.view`, `analytics.manage`, `analytics.export`. Production tables remained at zero rows. Browser verification passed at 1280×800 and 390×844 with no overflow or console errors. PHPStan level 10, Pint, Blade, Vite and Docker Compose passed.

Completion hardening adds three deterministic calculators, Refresh/Rebuild Commands, versioned immutable snapshots, authorization/tenant tests and MySQL process races for snapshot/refresh/rebuild. Full PHPUnit passes. Status: COMPLETED. Phase 3E-5F has not started.
