# Commerce Payment, Fulfillment and Revenue lineage

Seller ownership is Company-scoped: `company_user_access` → `seller_profiles(company_id,user_id)` → Store → Product/Inventory → Order. Payment, fulfillment Branch and recognized revenue must resolve to that same Company. Seller dashboards aggregate the canonical Order and `branch_management_revenues` lineage; they do not store cloned sales totals.

Sales History is a read-only projection, not a new ledger. It shows paid Order Items and joins settlement evidence, fulfillment/Branch and the unique revenue record by `order_id`. Paid but undelivered Orders remain visible as revenue pending; only delivered Orders contribute to recognized revenue.

The runtime flow is deterministic and database-backed:

`Cart → Checkout → Order + Reservation → Payment evidence → Seller confirmation → Fulfillment → Inventory consumption → Branch recognized revenue`.

Finance records settlement evidence; this local/manual boundary does not claim real bank or payment-provider verification. The service copies integer Order total/currency and enforces idempotency and unique settlement references. Seller permissions can view Order payment state but cannot create payment evidence.

Fulfillment is Seller-owned and bound to the Branch of every reserved inventory location. Checkout fails atomically if one Store Order cannot be reserved from exactly one Branch, so it never creates an Order that the one-fulfillment model cannot deliver. `ship` requires carrier/tracking evidence and consumes inventory exactly once. `deliver` is the recognition point. Revenue is Order subtotal (shipping excluded), has one unique `order_id`, and is absent for unpaid, cancelled or undelivered Orders.

Data Quality reconciles Payment↔Order, Fulfillment↔Branch/Inventory, shipped reservations and delivered Order↔Revenue. The local bootstrap uses `internal_demo` evidence only, runs idempotently and never contacts an external provider.
