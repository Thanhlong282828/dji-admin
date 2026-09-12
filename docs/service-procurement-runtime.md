# Service Procurement Runtime

Service procurement extends the canonical Procurement owner; it does not create a second vendor, purchase-order, AP, Treasury, Inventory, Accounting, DMS, or Workflow engine.

## Canonical lifecycle

`Purchase Request → approved Purchase Order → Service Acceptance draft → submitted → approved → Vendor Bill → AP Payment`.

Only `operating_expense` PO lines without an inventory SKU are eligible. Approval locks the PO line, prevents aggregate approved quantity from exceeding ordered quantity, resolves the effective `SERVICE_ACCEPTANCE_APPROVED` policy, and posts Expense/Service Accrual through the canonical accounting boundary. It never calls Inventory.

The immutable acceptance stores Company, Branch, Vendor, PO, Cost Center/account snapshots, policy ID/version, journal evidence, quantity and integer VND amount. Retry safety uses Company-scoped acceptance idempotency and accounting source keys. Maker and checker must differ. A closed accounting period fails closed.

Service Vendor Bills link each line to an approved Acceptance line. Aggregate non-reversed billing cannot exceed accepted quantity. Bill approval resolves `SERVICE_VENDOR_BILL_POSTED`, clearing Service Accrual into AP without recognizing Expense again. Existing AP Payment settles AP through Payment Clearing and Treasury.

Goods remain isolated: Goods Receipt uses Inventory and account `2150`; Service Acceptance uses configured policy account `2160`. Payment does not consume Budget again. Explicit acceptance reversal is not enabled until coordinated billed-dependency reversal policy is implemented; approved acceptance deletion/editing is unsupported.

Enterprise DMS remains available as the canonical evidence owner, but this slice creates no filler documents. Existing domain approval remains authoritative; no Workflow or Notification rows are inserted manually.
