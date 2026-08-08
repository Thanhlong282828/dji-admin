# Phase 3 Workforce Operations — audit before implementation

Audit date: 2026-07-26. Database: MySQL `schema_admin`.

| Capability | Before |
|---|---|
| Work calendar / calendar days | MISSING |
| Holiday calendar | PARTIAL (`company_holidays`, no calendar/override model) |
| Shift definition | SKELETON (`work_shifts`, minimal columns) |
| Shift assignment | PARTIAL schema, no workflow |
| Employee work profile / schedule | MISSING |
| Attendance raw events | MISSING |
| Attendance daily record | PARTIAL legacy aggregate, no tenant/approval/calculation version |
| Check-in/out | MISSING |
| Attendance adjustment / approval | PARTIAL schema, no workflow |
| Leave type | PARTIAL schema |
| Leave policy | MISSING |
| Leave balance/ledger | PARTIAL legacy mutable balance + transaction table |
| Leave request/approval | PARTIAL schema, no CQRS workflow |
| Leave accrual | MISSING |
| Overtime policy | MISSING |
| Overtime request | PARTIAL schema, no workflow |
| Payroll period/input snapshot | MISSING |
| Tenant isolation / policies / permissions | MISSING in these modules |
| Events / notifications / realtime / schedulers / exports / tests | MISSING |

All inspected Phase 3 business tables contain zero production records. Existing Company, Organization, Employee, Recruitment and Onboarding workflows are read-only dependencies for this phase.
