# ERP Sidebar Feature Audit

Generated/audited against `AdminNavigation`, Laravel named routes, route middleware, structured contextual help, and module-owned Process Flow definitions.

## Result

- Canonical sidebar destinations: 128.
- Top-level groups: 18.
- Duplicate destination routes: 0.
- Missing landing routes: 0.
- Missing permissions: 0.
- Missing owner Process Flow definitions: 0.
- Central business fallback: 0.
- Newly exposed destinations in this pass: 0.

Every current destination has a real named route, permission middleware, structured help, and one module-owned Process Flow definition. Server-side navigation removes unauthorized children and removes a parent when no permitted child remains.

## Stable business order

1. ERP Overview
2. Organization
3. People
4. Workforce
5. Talent
6. Payroll
7. Tax and statutory
8. CRM and Commerce
9. POS
10. Procurement
11. Supply Chain
12. Finance
13. Asset and Maintenance
14. Projects
15. Documents, Workflow, Approval and Automation
16. Analytics and Planning
17. IAM and Audit
18. Platform Operations

This changes group presentation order only. It does not change routes, permissions, business ownership, or business data.

## Procurement

Canonical lifecycle order remains:

1. Vendor
2. Purchase Request
3. Purchase Order
4. Goods Receipt
5. Service Acceptance
6. Vendor Bill / AP
7. Procurement Report

Vendor Performance is contextual within Vendor. Procurement Exception Workbench is contextual within Procurement Report. Service Acceptance reversal and DMS evidence are contextual actions. None receives a duplicate sidebar destination.

## Finance and Treasury

AP Aging, AP/GL reconciliation, AP Outflow Forecast, Vendor obligation summary, and payment planning are sections of the existing `Treasury · Dòng tiền` workspace. They do not receive separate menu items. The Treasury help and Process Flow continue to connect Vendor Bill/Payment to Treasury, Accounting, and Financial Statements.

## Approval

There is one canonical Approval Inbox. Procurement, Service, Finance, and Treasury do not create parallel inbox entries in navigation.

## Admin GET routes not represented as destinations

The route inventory found 19 GET endpoints outside sidebar patterns. None is a missing independent workspace:

- `admin.index` and `admin.payroll-input.index`: redirects.
- `admin.procurement.page`: generic compatibility route duplicated by specific canonical Procurement destinations.
- `admin.compensation-reference`: contextual header dialog.
- attendance readiness, notification unread count, and organization tree data: AJAX/read endpoints.
- leave/payroll/organization exports: contextual downloads.
- employee/leave document downloads: protected contextual actions.
- organization-unit create: contextual create screen reached from the owner workspace.
- audit/IAM/integration/notification fixture login routes: test fixture infrastructure.

## Backend-only examples

CQRS buses, Workflow callbacks, outbox processing, permission resolvers, posting-policy resolvers, allocation internals, Data Quality engine internals, queue workers, and Process Flow registry are infrastructure and remain absent from the sidebar. Operator workspaces such as Data Quality, Runtime Configuration, and Workflow Center remain exposed because they have real authorized pages; their internal engines do not receive additional destinations.
