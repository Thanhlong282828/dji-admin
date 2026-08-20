# Enterprise Analytics Audit — Phase 3F-25

## Scope and existing foundation

The enabled, user-facing analytics capability belongs to `Modules/Performance`. The canonical route is `admin.analytics.workforce` (`GET /admin/analytics/workforce`) and is protected by `auth`, `verified`, `analytics.view`, company access, and `TenantAccessService`. `Modules/Report` is disabled/skeleton infrastructure and is not enabled by this phase.

The pre-phase dashboard used `WorkforceAnalyticsService` plus workforce, organization, and talent calculators. It exposed six summary values and two small visual summaries. Snapshot generation is immutable/versioned and uses `analytics.manage`. The sidebar and contextual-help registry already contain the Analytics primary destination.

## Actual source inventory

| Area | Canonical sources | Safe aggregates |
|---|---|---|
| Workforce | `employees`, current primary `employee_assignments`, `organization_units`, `job_positions` | status, gender, age band, join/leave month, department, position, level, span of control |
| Attendance | `attendance_records` | attendance/late/absence rates, late and overtime minutes, daily trend |
| Leave | `leave_requests`, `leave_types`, current assignment | status, paid/unpaid, department, monthly trend |
| Recruitment | `recruitment_requisitions`, `candidates`, `applications`, `application_interviews`, `candidate_offers` | funnel counts and stage distribution |
| Payroll | `payroll_runs`, `payroll_items`, `payroll_item_components` | period totals and department/position aggregates |
| Compensation | `employee_salary_component_assignments`, `salary_components`, current assignment | aggregate base/KPI/allowance distributions only |
| Organization | `organization_units`, `job_positions`, current assignment | approved headcount, people, vacancy, budgeted cost |
| Workflow | `workflow_instances`, `workflow_tasks` | open/completed/rejected/pending aggregates |
| IAM | `iam_access_requests`, `iam_access_reviews`, `iam_sod_violations` | request/review/SoD counts only |
| Quality | `DataQualityAuditService` | current score/findings only; no fabricated history |

## Findings

- Existing reads were company-scoped but did not accept unit, position, level, or date filters.
- The dashboard did not expose attendance, leave, recruitment, payroll, workflow, IAM, or data-quality decision support.
- Compensation required an additional permission boundary; `analytics.view` alone is not sufficient.
- Existing calculators use bounded aggregate queries. New reads must preserve that property and must not query once per employee.
- Existing route, sidebar destination, snapshot model, and permissions are reusable. A new module, database migration, or external BI dependency is not justified.
- Recruitment timestamps support current funnel/stage evidence, but a management SLA is not defined; no SLA claim is made.
- Data Quality has no canonical historical series; only the current snapshot is reported.

## Privacy and semantics

The dashboard exposes aggregates, never employee names, salary rows, bank/tax identifiers, secrets, or SQL bindings. Payroll and compensation panels are rendered server-side only for actors with `payroll.assignment.view`. Payroll run/item history remains read-only and calculation/state-machine semantics are unchanged.

## Completion evidence

The final read model has 13 typed sections, 50 scalar management metrics, and 19 grouped visual/table series. A fully salary-authorized local read executes 63 fixed queries; the no-salary path executes 51, and the regression test proves that adding 50 Employees does not increase that count. Read-only EXPLAIN uses `attendance_daily_unique` for company-scoped attendance and `employee_assignment_unit_effective_idx` for current-assignment aggregation. No index migration was justified.

Focused analytics passed 8 tests/33 assertions. Full PHPUnit passed 535 tests (534 passed, one skipped), 7,103 assertions, exit 0. Repository PHPStan level 10, Pint, Blade, Vite, Composer/npm audits, secret scan, Swagger generation, route/config caches, Docker Compose base/prod/runtime rendering, CI static validation, and exact 1280×800 plus 390×844 Chrome checks passed. Browser fixture cleanup left users/companies/audit events at zero.
