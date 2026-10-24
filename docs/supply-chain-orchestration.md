# Supply Chain orchestration audit

## Decision

SCM is a read-only orchestration surface owned by `Modules/Dashboard`. It does not own Purchase Orders, Goods Receipts, inventory balances/movements, Sales Orders, Marketplace Orders, Treasury or Accounting records. A separate `Modules/SCM` is not justified.

No separate Logistics bounded context was added. Marketplace fulfillment already owns the verified `pending → packed → shipped → delivered` evidence, ERP Sales owns fulfillment/COGS, and Inventory owns issues and transfers. Shipment evidence must never produce a second inventory movement.

## Canonical sources

| Area | Owner | Evidence |
| --- | --- | --- |
| Supply | Procurement | Purchase Order lines, Goods Receipts, Vendor Bills and AP allocations |
| Inventory | Inventory | Locations, balances, movements and stock transfers |
| ERP demand | Commerce | Sales Orders, Sales Order lines and Sales Fulfillments |
| Marketplace fulfillment | Commerce | Orders and Commerce Fulfillments |
| Value and COGS | Inventory / Finance | Inventory value, immutable fulfillment COGS and posted journals |
| Executive summary | Dashboard | The same `SupplyChainOverviewReadService`; no persisted KPI copies |

## Runtime flow

`GET /admin/supply-chain → SupplyChainOverviewController → GetSupplyChainOverviewQuery → GetSupplyChainOverviewHandler → SupplyChainOverviewReadService → grouped owner-domain queries → Blade`.

The workspace is permission- and Company-scoped, exposes drilldowns to owner workflows, and performs no business mutation.

## Semantics and limitations

- `available = on_hand - reserved`; reserved stock is read from the canonical Inventory balance.
- In-transit stock is limited to canonical Inventory transfer quantities.
- External carrier integration is `NOT_CONNECTED`; no carrier response is fabricated.
- Delivery performance is `NOT_ENOUGH_EVIDENCE` because there is no canonical promised delivery timestamp.
- Logistics cost is `NOT_IMPLEMENTED`; no guessed carrier fee enters Finance.
- Failed/returned shipment metrics are unsupported by the current lifecycle; sale reversal remains in Commerce.

## Data quality

Existing controls cover received Goods Receipts missing Inventory Movement evidence and fulfilled physical Sales lines missing Inventory Movement/COGS evidence. Workflow services reject over-receipt, over-fulfillment and duplicate source movement retries. No summary-only green rule was added.
