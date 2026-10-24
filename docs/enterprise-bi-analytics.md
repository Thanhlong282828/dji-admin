# Enterprise BI / Analytics audit and design

## Ownership decision

Cross-domain management analytics belongs to `Modules/Dashboard`, which already composes HR, Commerce, Finance and SCM read models. `Modules/Performance` remains owner of workforce analytics, forecasting and planning. No `Modules/BI`, BI fact table or copied KPI-value table is introduced.

## Capability matrix

| Capability | Canonical source | Status |
| --- | --- | --- |
| Recognized revenue trend/channel | Customer Invoice plus recognized Marketplace/POS revenue | COMPLETE |
| COGS, gross profit, operating result | Posted GL through Finance Overview | COMPLETE within current account coverage |
| AR/AP and cash forecast | Customer/Vendor subledgers and due dates | COMPLETE / deterministic forecast |
| Payroll trend | Finalized Payroll Run period | COMPLETE |
| Expense trend | Approved/paid Expense business date | COMPLETE |
| Cash movement | Recorded Treasury occurred_at | COMPLETE |
| Inventory current health/value | Inventory balances through SCM | COMPLETE |
| Historical inventory value/turnover | Period inventory snapshots | NOT_ENOUGH_EVIDENCE |
| CRM segments | CustomerMetricsService | COMPLETE current-state |
| Historical CRM segment trend | Transaction-time historical segments | NOT_ENOUGH_EVIDENCE |
| People/attendance/OT | Employee status and Attendance work_date | COMPLETE current filter scope |
| Future revenue | Confirmed committed revenue evidence | NOT_ENOUGH_EVIDENCE |

## Read flow

`GET /admin/analytics/enterprise → EnterpriseAnalyticsController → GetEnterpriseAnalyticsQuery/Handler → EnterpriseAnalyticsReadService → grouped owner-domain queries → Blade`.

The default period is 12 months and the bound is 24 months. Monthly rows are produced in memory after a fixed number of grouped queries; there is no query per month, employee, product or branch. Branch filters apply only where canonical branch attribution exists. Payroll headcount remains Company scoped because historic payroll branch attribution has its own owner reporting.

## Semantics

- Revenue uses `invoice_date` for issued ERP invoices and `recognized_on` for Marketplace/POS recognized revenue.
- Payroll uses finalized `period_end`; Attendance uses `work_date`; Expense uses `expense_date`; Treasury uses `occurred_at`.
- Variance basis points are absent when the previous value is zero.
- Forecast is explicitly limited to AR/AP due-date evidence from Finance Overview.
- Data Quality is linked on demand rather than executing all audit rules inside every analytics page request.
