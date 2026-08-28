# Commerce C6 checkout/order audit

Audit date: 2026-08-27. Baseline: 73 migrations / 252 tables.

- Existing canonical inputs: `users`, `buyer_profiles`, `carts`, `cart_items`, `products`, `product_variants`, `stores`, `seller_profiles`, `inventory_locations`, `inventory_balances`.
- No runtime Buyer Address, Checkout, Order, Order Item, Shipment, inventory reservation or payment-intent domain exists. No disabled scaffold is suitable for enabling.
- C5 Cart keeps a price snapshot only for change detection and does not reserve stock. Authoritative availability remains `SUM(inventory_balances.on_hand-reserved)`.
- Existing Audit/activity infrastructure can record safe references. Seller ownership is `User → seller_profiles → stores`; Buyer ownership is authenticated User with active Buyer Profile.
- A persistent checkout aggregate is justified for request idempotency across multiple per-Store Orders. Address and Product data must be snapshotted on Order/Order Item.
- Payment, bank, carrier quote/tracking, voucher and fulfillment consumption are absent and remain outside C6.

Decision: additive migration with five tables (`buyer_addresses`, `checkout_sessions`, `orders`, `order_items`, `inventory_reservations`). Whole multi-Store checkout is one transaction. No production/VPS access.
