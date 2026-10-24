# Service Procurement / Acceptance Audit

## Decision

Classification: `SERVICE_PROCUREMENT_POLICY_REQUIRED`.

The policy evaluation and recommended future accounting contract are documented in `docs/service-procurement-accounting-policy.md`. That recommendation does not activate runtime or authorize creation of an account.

The existing Procurement owner supports line classifications `inventory`, `asset`, and `operating_expense`. Purchase Requests and Purchase Orders preserve the selected account and Cost Center evidence at line level. However, the executable downstream receipt and billing path is deliberately limited to `inventory` lines:

- Goods Receipt accepts only `inventory` lines with a canonical Product Variant and calls the Inventory application boundary.
- Receipt posts Inventory against account `2150 – Goods Received Not Invoiced`.
- Vendor Bill currently bills received inventory quantity and clears `2150` into `2100 – Accounts Payable`.
- Payment settles `2100` through `1100 – Payment Clearing` and the canonical Treasury boundary.

`asset` and `operating_expense` classification at requisition/order level is therefore partial planning and dimensional evidence, not proof of Service Acceptance runtime.

## Accounting policy gate

The operating Chart of Accounts and `SourceAccountingPostingService` contain the goods bridge `2150 – Goods Received Not Invoiced`, but no configured account or versioned posting policy for an Accrued Service Liability, Accrued Expense, or Service Received Not Invoiced liability. Account `5100 – Operating Expense` is available for the debit side, but an expense account alone is insufficient to define the required service accrual lifecycle.

Reusing `2150` would conflate physical goods receipt with service performance evidence. Creating another liability code silently would invent accounting policy. Both actions are prohibited. Consequently no Service Acceptance migration, tables, state machine, operating scenario, journal, Inventory movement, DMS evidence, permission, route, or UI was created.

## Configuration needed before implementation

An authorized accounting decision must define, at minimum:

1. the service accrual liability account code/name/type and effective scope;
2. the posting policy/version for Acceptance (`Dr configured service expense / Cr service accrual`);
3. the Vendor Bill clearing policy (`Dr service accrual / Cr AP`);
4. whether different service categories resolve different expense accounts;
5. the effective-date, period-close, correction, and multi-currency rules.

Once configured, Service Acceptance can be implemented in the Procurement owner with partial acceptance, maker-checker, line-level PO lineage, historical Cost Center snapshots, concurrency/idempotency protection, and zero Inventory/COGS effects.
