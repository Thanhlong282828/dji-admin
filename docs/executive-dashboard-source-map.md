# Executive Dashboard Source Map

The executive dashboard owns no business table.

| Card/section | Canonical query source | Permission boundary | Drilldown owner |
|---|---|---|---|
| Companies | Company query handler/repository | `company.view` | Company |
| Employees/status | Employee table through Dashboard repository | `dashboard.view`, company context | Employee |
| Pending leave | Leave requests | dashboard/company scope | Leave |
| Recruitment | Requisitions | dashboard/company scope | Recruitment |
| Branch/headcount | Branch + effective Employee Branch Assignment | company access | Branch/Employee |
| Marketplace Orders | Orders joined through Seller Company | `branch-commerce.view` | Commerce Orders |
| POS Receipts | paid `branch_pos_receipts` | `branch-commerce.view` | POS Sales |
| Customers/open visits | `commerce_customers`, `customer_visits` | `branch-commerce.view` | Customer/Check-in |
| Products/services sold | checked-out Visit line snapshots | `branch-commerce.view` | POS Sales |
| Commerce + POS revenue | recognized `branch_management_revenues` with source types | `branch-commerce.view` | Sales/Branch Finance |
| Low stock | Inventory Balance at Branch Location | `branch-commerce.view` | Branch Warehouse |
| Finance/trend | BranchFinancialPerformanceService over canonical revenue/payroll/OPEX/rent/assets | sensitive payroll/finance permission | Branch Finance |
| Organization planning | Organization Units + Job Positions approved headcount | `dashboard.view`, company context | Organization/Headcount |
| Recruitment handoff | Requisitions, Candidates, Applications, Interviews, Offers and linked hired Employee | `dashboard.view`, company context | Recruitment/Employee |
| Attendance snapshot | latest company Attendance date, records, late count/minutes | `dashboard.view`, company context | Attendance |
| Finalized Payroll | Payroll Run + Payroll Items/component breakdown | sensitive payroll permission; omitted server-side otherwise | Payroll |
| Data Quality | current read-only Data Quality audit snapshot | operations/data-quality permission | Data Quality |

All trends are queried from the database for bounded months. No JavaScript demo array or independent dashboard total is permitted.

Commerce revenue is the net recognized ledger: eligible Marketplace fulfillment and paid POS receipt sources minus append-only sale reversals. Payroll cards read finalized Payroll results and do not infer cost from salary assignments. Missing permission removes sensitive finance/payroll payloads before Blade rendering.
