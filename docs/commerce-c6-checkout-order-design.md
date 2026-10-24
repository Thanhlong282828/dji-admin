# Commerce C6 checkout/order design

`CheckoutService` owns the atomic boundary: authenticate active Buyer; resolve owned selected Cart items; lock current Product/Variant/Store/Seller and inventory rows; recalculate integer VND price; create one idempotent Checkout Session; split one Order per Store; snapshot address/store/product/SKU/variant/price; increment `reserved`; create reservation evidence; remove only purchased Cart items; commit.

`checkout_sessions` has `UNIQUE(buyer_user_id,idempotency_key)`. A retry returns the already-created Orders. `orders` has immutable collision-safe public `order_number` and shared `checkout_reference`. Shipping is a configuration-backed internal estimate per Store (`standard`, `express`), never a carrier quote.

Cancellation is legal only while unpaid and from `pending_payment` or `confirmed`; it locks Order/reservations/inventory, releases reservation quantities, transitions reservations to `released`, and marks Order `cancelled` atomically. Paid, `processing`, `fulfilled` and `cancelled` Orders cannot be Buyer-cancelled.

Buyer Address queries are owner-scoped. First address becomes default; changing/default deletion is transactional. Order address JSON is a historical snapshot. Seller reads only Orders belonging to owned Stores and sees delivery fields only after an Order exists.

## Payment and fulfillment runtime extension

Finance records succeeded settlement evidence through `CommercePaymentService`; amount and currency are copied from the immutable Order total, provider/reference and idempotency keys are unique, and no external bank/provider call is claimed. Seller cannot record payment. A Seller may confirm only a paid Order.

`CommerceFulfillmentService` binds the Order reservations to one Company Branch through `inventory_locations.branch_id`. Checkout validates that every reservation of one Store Order resolves to exactly one Branch and rolls back otherwise. The typed sequence is `pending → packed → shipped → delivered`; shipping consumes the locked reservations exactly once and reduces both `on_hand` and `reserved`. Delivery calls `CommerceRevenueRecognitionService`, which inserts one `commerce_order` Branch revenue for Order subtotal only. Unpaid or undelivered Orders contribute zero recognized revenue; shipping is excluded from recognized merchandise revenue.
