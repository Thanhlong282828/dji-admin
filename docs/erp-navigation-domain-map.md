# ERP navigation domain map

Audit date: 2026-08-28. Navigation remains Laravel server-rendered and permission-filtered by `AdminNavigation`; moving an item does not change its route or backend permission.

## Runtime domains

- ERP overview: Dashboard only.
- HRM: Company/Branch/Organization, Employee/Lifecycle/Portal, Attendance/Shift/Leave/Overtime, Recruitment/Onboarding/Talent.
- Payroll: configuration, periods, inputs, runs, adjustments, Branch Bonus, payslips and payment batches.
- Tax & statutory insurance: employee tax/insurance profiles, effective-dated policies, annual PIT reconciliation and Government Filing.
- Finance: Branch Financial Performance, accounting journals/configuration and ERP export/configuration.
- Analytics & Planning: analytics, forecasting, executive planning, planning execution and enterprise reports.
- CRM & Commerce: Seller/Store, Product/Category, Inventory, Orders and Content. It is not represented as a CRM interaction system because no customer-service/segmentation runtime exists.
- Workflow/Automation, IAM/Audit, Integration and Operations retain their governed runtime routes.

Parent groups render only when at least one permitted child exists. The browser preference stores only a stable group key; it never reconstructs routes or permissions.

## Deliberately absent

VAT, CIT, customer service, CRM campaigns and statutory asset depreciation do not have primary routes. They are not added as menu placeholders. Branch P&L is explicitly a management report, not a statutory return.
