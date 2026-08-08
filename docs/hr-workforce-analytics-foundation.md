# Phase 3E-5E — Workforce Analytics

Workforce Analytics is a company-scoped read model. It reads Organization, Employee Lifecycle, Performance and Career evidence only; it does not update source aggregates and never reads salary, tax, bank or Payroll data.

Batch 44 creates `analytics_calculation_runs`, `workforce_analytics_snapshots` and `analytics_outbox`. Calculations are deterministic and cover headcount/active/inactive, hires, terminations, transfers, promotions, attrition, performance rating, goal completion, critical-position succession coverage, position count and vacancy. Snapshot generation is transactional, checksummed and idempotent.

HTTP flow uses shared CQRS buses. Permissions are `analytics.view`, `analytics.manage`, `analytics.export`. Production tables remained at zero rows. Browser verification passed at 1280×800 and 390×844 with no overflow or console errors. PHPStan level 10, Pint, Blade, Vite and Docker Compose passed.

Completion hardening adds three deterministic calculators, Refresh/Rebuild Commands, versioned immutable snapshots, authorization/tenant tests and MySQL process races for snapshot/refresh/rebuild. Full PHPUnit passes. Status: COMPLETED. Phase 3E-5F has not started.
