# HR separation and accounting adjustment runtime

## Contract renewal

`HrOperationsController → MutateHrOperationsCommand → MutateHrOperationsHandler → HrOperationsService → EmploymentContractService` creates a new contract linked through `employment_contract_renewals`. The prior contract and historical payroll evidence are never overwritten. The renewal effective date must be exactly one day after the prior contract end date, and approval uses maker-checker separation.

## Resignation and offboarding

Submission records intent only; it does not deactivate the employee. Approval establishes the approved last working date and creates a canonical offboarding case. Completion is blocked until every mandatory HR, Payroll, IAM and Asset checklist item is complete and the effective date is reached. Completion ends open primary assignments, disables access through `EmployeeAccountService`, and preserves the User and all history.

Final-pay calculations remain owned by Payroll. This runtime does not invent a second final-pay engine or rewrite finalized runs.

## Generic accounting adjustment

`AccountingAdjustmentController → MutateAccountingAdjustmentCommand → MutateAccountingAdjustmentHandler → AccountingAdjustmentService` supports a controlled full reversal of a posted journal into an open target period. The original journal and lines remain immutable. The reversal swaps debit and credit, is balanced, retains source linkage, requires maker-checker approval, and uses a unique source key so retry returns the same resulting journal.

Arbitrary correction and reclassification line editors are intentionally not enabled in the current scope; they require a separately governed account-line input policy.

## Notifications and reporting

HR events use the existing event/listener/database-notification path. Accounting-adjustment transitions use the same canonical recipient and delivery services. `CurrentOperatingDataReportService`, the executive dashboard, contextual help, navigation and Data Quality all read the owner tables; no dashboard totals are cloned into a second store.
