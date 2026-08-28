# Commerce C6 checkout/order design

`CheckoutService` owns the atomic boundary: authenticate active Buyer; resolve owned selected Cart items; lock current Product/Variant/Store/Seller and inventory rows; recalculate integer VND price; create one idempotent Checkout Session; split one Order per Store; snapshot address/store/product/SKU/variant/price; increment `reserved`; create reservation evidence; remove only purchased Cart items; commit.

`checkout_sessions` has `UNIQUE(buyer_user_id,idempotency_key)`. A retry returns the already-created Orders. `orders` has immutable collision-safe public `order_number` and shared `checkout_reference`. Shipping is a configuration-backed internal estimate per Store (`standard`, `express`), never a carrier quote.

Cancellation is legal from `pending_payment` or `confirmed`; it locks Order/reservations/inventory, releases reservation quantities, transitions reservations to `released`, and marks Order `cancelled` atomically. `processing` and `cancelled` cannot be Buyer-cancelled. C6 does not consume inventory or reduce `on_hand`.

Buyer Address queries are owner-scoped. First address becomes default; changing/default deletion is transactional. Order address JSON is a historical snapshot. Seller reads only Orders belonging to owned Stores and sees delivery fields only after an Order exists.
