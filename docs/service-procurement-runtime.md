# Service Procurement Runtime

Service procurement extends the canonical Procurement owner; it does not create a second vendor, purchase-order, AP, Treasury, Inventory, Accounting, DMS, or Workflow engine.

## Canonical lifecycle

`Purchase Request → approved Purchase Order → Service Acceptance draft → submitted → approved → Vendor Bill → AP Payment`.

Only `operating_expense` PO lines without an inventory SKU are eligible. Approval locks the PO line, prevents aggregate approved quantity from exceeding ordered quantity, resolves the effective `SERVICE_ACCEPTANCE_APPROVED` policy, and posts Expense/Service Accrual through the canonical accounting boundary. It never calls Inventory.

## Reversal and correction

An approved, unbilled acceptance can enter `reversal_requested` and then `reversed`. The request requires a reason, idempotency key and open target accounting period. It reuses the generic Accounting Adjustment lifecycle and reverses the immutable original journal lines, so historical account, policy and Cost Center evidence are preserved. The requester cannot approve the adjustment.

Any non-cancelled Vendor Bill line linked to the acceptance blocks reversal with `SERVICE_ACCEPTANCE_HAS_BILLING_DEPENDENCY`. The system never cascades a reversal into AP, Payment or Treasury; downstream evidence must be corrected first through its own owner workflow. A reversed acceptance is excluded from active accepted quantity, restores the PO open commitment, nets the original expense to zero and remains visible in history.

The immutable acceptance stores Company, Branch, Vendor, PO, Cost Center/account snapshots, policy ID/version, journal evidence, quantity and integer VND amount. Retry safety uses Company-scoped acceptance idempotency and accounting source keys. Maker and checker must differ. A closed accounting period fails closed.

Service Vendor Bills link each line to an approved Acceptance line. Aggregate non-reversed billing cannot exceed accepted quantity. Bill approval resolves `SERVICE_VENDOR_BILL_POSTED`, clearing Service Accrual into AP without recognizing Expense again. Existing AP Payment settles AP through Payment Clearing and Treasury.

Goods remain isolated: Goods Receipt uses Inventory and account `2150`; Service Acceptance uses configured policy account `2160`. Payment does not consume Budget again. Explicit acceptance reversal is not enabled until coordinated billed-dependency reversal policy is implemented; approved acceptance deletion/editing is unsupported.

Enterprise DMS remains available as the canonical evidence owner, but this slice creates no filler documents. Existing domain approval remains authoritative; no Workflow or Notification rows are inserted manually.
