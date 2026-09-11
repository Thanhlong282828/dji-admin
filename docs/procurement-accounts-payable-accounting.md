# Procurement, AP, inventory valuation and accounting

## Ownership audit

| Business concept | Existing owner before this change | Canonical table/service | Status/action |
|---|---|---|---|
| Seller supplier text on receipt | Inventory | `inventory_stock_receipts.supplier_name` | Kept as legacy operational receipt metadata; not treated as Vendor master. |
| Inventory location/balance/movement | Inventory | `InventoryService` | Extended with weighted-average value and immutable movement cost snapshots. |
| Vendor | None | Procurement / `procurement_vendors` | Added company-scoped master. |
| Purchase Request / Purchase Order | None | Procurement / `ProcurementService` | Added governed request/order lifecycle. |
| Goods Receipt | Inventory had a single-line quantity receipt only | Procurement GR + Inventory movement | Added PO-linked receipt as canonical procurement stock-in. |
| Vendor Bill / AP allocation | None | Procurement / `vendor_bills`, `ap_payments`, `ap_payment_allocations` | Added maker-checker approval, derived outstanding and aging. |
| Double-entry journals | Payroll module supplied the existing accounting platform | Existing `payroll_accounting_journals` and lines | Reused and generalized; no second journal engine. |
| Financial statements | Finance | `FinancialStatementReadService` | Reads both Payroll and generic source journals. |

## Canonical purchase lineage

`Vendor → Purchase Request → approval → Purchase Order → Goods Receipt → InventoryService → valued inventory movement → Dr Inventory / Cr GRNI → Vendor Bill approval → Dr GRNI / Cr AP → Payment allocation → Dr AP / Cr Payment Clearing`.

All money is integer VND minor units. Receipt retry uses a stable movement idempotency key. Automatic journals use the unique tuple `company + source_type + source_id + posting_type`. A closed general accounting period rejects normal source posting.

## Inventory costing

The selected method is weighted average for this ERP scope:

- incoming value is added to the existing balance value;
- average unit cost is deterministically rounded from total value / total quantity;
- an outbound sale snapshots the current unit cost and movement value;
- reversal restores the original movement cost, never today's recalculated cost.

Legacy quantity-only movements remain readable. They are not silently assigned invented historical cost.

## Sales accounting

Recognized Marketplace and POS product sales create two independent, idempotent postings:

- revenue: Dr Payment Clearing / Cr Sales Revenue;
- inventory: Dr COGS / Cr Inventory Asset.

Service-only POS lines have no inventory COGS. A sale reversal preserves the original journals and creates source-linked reversal journals for revenue and the original COGS value.

## Period and statement limitations

General Ledger and Trial Balance include Payroll, Procurement, Marketplace and POS source postings. Income Statement gains posted Sales Revenue and COGS, but remains PARTIAL wherever operating expense sources are management-only rather than posted GL evidence. Balance Sheet remains PARTIAL because no canonical equity/opening-balance source has been implemented. Cash Flow is NOT_IMPLEMENTED; Payment Clearing is not represented as verified bank settlement.

## Deterministic local proof

The local bootstrap owns one chain identified by `LOCAL-VENDOR-001`, `LOCAL-PR-001`, `LOCAL-PO-001`, `LOCAL-GR-001`, `LOCAL-INV-001`, and `LOCAL-AP-PAY-001`. It calls the canonical application service and is skipped when those references already exist. It never rewrites manual procurement rows.
