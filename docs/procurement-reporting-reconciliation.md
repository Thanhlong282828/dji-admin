# Procurement reporting and reconciliation

## Ownership and sources

The Procurement module owns the reporting read model, but it does not own a second ledger. The report reads Purchase Orders and lines, Goods Receipts, Service Acceptances, Vendor Bills and AP allocations, then reconciles them with posted Finance journals and the canonical Cost Center cost-management read model.

The read path is `ProcurementController → GetProcurementPageQuery → GetProcurementPageHandler → ProcurementReconciliationReadService`. It is read-only and company-scoped.

## Goods and service semantics

Goods are reported as ordered, received, unreceived, billed and received-not-billed. Goods GRNI is account 2150 and is sourced only from posted Goods Receipt and Vendor Bill journals.

Services are reported separately as ordered, approved accepted, reversed, net accepted, unaccepted, billed and accepted-not-billed. Service Accrual is account 2160 and is sourced only from posted Service Acceptance and Vendor Bill journals. Service acceptance never creates an Inventory movement.

Accounts Payable uses one consistent eligible Vendor Bill population for original amount, allocations and outstanding. Its GL comparison includes only posted `vendor_bill` and `ap_payment` sources on account 2100, so unrelated Asset and Finance Expense liabilities are excluded.

## Budget and payment

Budget exposure reuses `CostCenterCostManagementReadService`:

`Consumed = Realized Actual + Open Commitment`

`Remaining = Approved Budget - Consumed`

Vendor Bill and Payment do not consume Budget again. Payment settles AP and is not a second Actual. `NO_BUDGET`, `PARTIAL_EVIDENCE`, and `UNKNOWN` remain distinct from numeric zero.

## Filters and evidence limitations

The report supports date, company, branch, Cost Center, vendor, procurement classification and canonical VND currency. SQL aggregation is bounded and does not issue per-record queries.

Current journal snapshots do not persist Vendor and procurement-classification dimensions on every GL line. Therefore an AP GL comparison under Vendor, Cost Center, or classification filters is shown as `UNKNOWN`, never as zero. Vendor delivery/invoice SLA scores are not calculated because current records do not provide sufficient promised-date evidence. Workflow remains approval evidence; the business status remains authoritative.
