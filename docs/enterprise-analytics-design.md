# Enterprise Analytics Design — Phase 3F-25

## Architecture

`WorkforceAnalyticsController` resolves the tenant and a bounded `AnalyticsFilter`, dispatches `WorkforceDashboardQuery`, and renders the existing Performance analytics page. `EnterpriseAnalyticsService` performs a fixed set of company-scoped grouped queries and returns a typed `WorkforceAnalyticsViewModel`. No analytics validation runs on business requests.

The canonical filter contract is company, organization unit, position, career level 1–6, and an inclusive date range capped at 366 days. Position must belong to the selected unit and every selected resource must belong to the selected company.

## Permission model

- Dashboard: `analytics.view`.
- Snapshot mutation: `analytics.manage` (existing behavior).
- Aggregate export: `analytics.export`.
- Payroll and actual compensation aggregates: additionally `payroll.assignment.view`.

Salary authorization is enforced before the view model is built and before HTML/export serialization; it is not CSS hiding.

## Query and capacity policy

- Use SQL `COUNT`, `SUM`, `AVG`, and `GROUP BY` over bounded company/date scopes.
- Resolve the current primary assignment with one derived subquery; never query per employee.
- Return top/limited management distributions where necessary.
- Use existing indexes first. This phase creates no migration because no demonstrated missing index requires rollout.
- Export aggregate rows only and protect spreadsheet cells against formula injection.

## Dashboard composition

The page is organized into Executive Summary, Workforce, Attendance & Leave, Recruitment, Organization, Payroll & Compensation, Workflow & IAM, and Risk & Quality. Semantic tables and CSS bars avoid a new JavaScript chart dependency. Empty sources render zero/empty states without invented data.

## Data ownership and correction flow

Analytics is read-only. A finding is corrected in its owning menu: Employee/Organization for workforce, Attendance/Leave for time data, Recruitment for funnel data, Payroll for financial results, Workflow/IAM for governance, and Data Quality for reconciliation evidence. Attendance changes affect a payroll input only through the existing snapshot/version workflow.

## Export and realtime

CSV export contains tenant-scoped aggregate values only. Attendance may continue to receive existing Reverb-driven updates, but analytics itself does not require realtime transport and business CRUD remains fail-open when Reverb is unavailable.
