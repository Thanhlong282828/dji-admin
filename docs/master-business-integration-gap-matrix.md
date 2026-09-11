# Master Business Integration Gap Matrix

Audit date: 2026-08-31. Scope: current working tree; no production/VPS access.

| Capability | Status | Evidence / decision |
|---|---|---|
| Company → Seller → Store ownership | IMPLEMENTED | Seller profile is company-scoped; Store resolves through Seller; company views read the same relation. |
| Product/Variant/SKU → Branch inventory | IMPLEMENTED | Product variants use canonical inventory locations/balances; Warehouse and Seller Inventory share the same read source. |
| POS checkout → inventory → receipt → revenue | IMPLEMENTED | Transactional `BranchPosService`; server-side price snapshots; one receipt/revenue per Visit. |
| Marketplace paid/delivered → revenue | IMPLEMENTED | Revenue eligibility remains payment/fulfillment governed; unpaid/incomplete orders contribute zero. |
| Unified sales history | IMPLEMENTED | `GetUnifiedSalesHistoryQuery/Handler` unions Marketplace and POS with source filters and revenue lineage. |
| Full sale reversal | IMPLEMENTED | Append-only reversal, one canonical restock, original sale retained, net revenue propagated to Finance/Dashboard. |
| Stock receipt | IMPLEMENTED | Draft → received; one inbound movement with idempotency. |
| Inter-branch transfer | IMPLEMENTED | Draft → in_transit → received; source/destination movements and stock conservation. |
| Inventory opening evidence | IMPLEMENTED | Migration 83 backfills opening movement only where a pre-existing positive balance had no ledger evidence. |
| Dashboard/Branch Finance source-of-truth | IMPLEMENTED | Read services aggregate canonical employees, POS/Marketplace revenue, inventory and finance ledgers; no view-owned totals. |
| Posted General Ledger / Trial Balance | IMPLEMENTED | Finance groups internally posted/exported Payroll Accounting Journals; Company/date/account scoped and drillable to source. |
| Complete Income Statement / Balance Sheet / Cash Flow | PARTIAL | Revenue, COGS, Inventory Asset and AP/GRNI are now canonical posted evidence. Equity/opening balance and verified bank settlement remain absent, so Balance Sheet stays PARTIAL and Cash Flow NOT_IMPLEMENTED. |
| Product media boundary | IMPLEMENTED | `MediaService` + `MediaStorageInterface`; local/testing adapter verified; missing/broken state explicit. |
| Cloudflare Images live delivery | CONFIGURATION_REQUIRED | Adapter reused, but credentials/delivery are not externally verified; no live call was made. |
| Seller banned recovery | IMPLEMENTED | Controlled `banned → suspended → active`; reason/audit required by governance; no IAM privilege elevation. |
| Partial return / partial refund | MISSING_BUT_REQUIRED_LATER | Requires allocation of line quantity, refund/payment evidence and finance/tax correction policy; full reversal is the safe current boundary. |
| Supplier purchase order / input invoice | MISSING_BUT_REQUIRED_LATER | Stock receipt exists, but procurement/account-payable ownership is not yet present; no fake supplier invoice was introduced. |
| External payment/bank settlement | CONFIGURATION_REQUIRED | Current payment method is internal evidence only and is never presented as bank verification. |

## Reconciliation invariants

- `available = on_hand - reserved`, never negative.
- Receipt/transfer/sale/reversal writes use canonical inventory movements; direct view/controller balance mutation is forbidden.
- Recognized revenue has one source reference; a reversal subtracts from net reporting without deleting history.
- Dashboard and Branch Finance own no cloned sales total.
- Browser and test fixtures use isolated databases and remove only fixture-owned rows/media.
