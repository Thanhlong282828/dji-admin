# Procurement exception workbench

The Procurement exception workbench is a derived, read-only section of **Báo cáo mua hàng**. It does not persist exception rows, replace the Approval Inbox, or perform owner-domain transitions.

## Evidence and actions

Operational rows are built from canonical Purchase Orders, Goods Receipts, Service Acceptances, Vendor Bills, AP allocations and Workflow tasks. Financial mismatch rows compare the existing reconciliation model:

- Goods received-not-billed against Goods GRNI 2150.
- Approved non-reversed service accepted-not-billed against Service Accrual 2160.
- Procurement Vendor Bill outstanding against Procurement-sourced AP 2100.

Rows only expose existing routes. Receiving remains in Goods Receipts, service acceptance and reversal remain in Service Acceptances, billing/payment remains in Vendor Bills/AP, approvals remain in the canonical Approval Inbox, and Budget investigation remains in Finance Cost Center management.

The source-view permission controls whether a row is returned to the page. A separate action permission controls whether the destination link is rendered. Permission denial never produces a disabled privileged action.

## Severity and limitations

`CRITICAL` is reserved for exact reconciliation differences. `WARNING` is used for overdue evidence and incomplete operational realization. `INFO` is used where action is pending without deterministic lateness evidence. Amount size alone never changes severity.

`NO_BUDGET` and `PARTIAL_EVIDENCE` are not zero. Empty companies do not receive a synthetic `NO_BUDGET` exception. Workflow state supplies approval context only; Procurement business state remains authoritative. The known downstream Service Acceptance reversal limitation is not hidden or auto-corrected.
