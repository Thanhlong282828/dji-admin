# Commerce C4 product catalog and inventory design

## Domain and write flow

`Seller Profile → Store → Product → Variant/SKU → Inventory Location → Inventory Balance`

Validated Product request → seller/store ownership gate → database transaction → Product → Variant/SKU → locked Inventory balance → Media attachment → audit event.

Media is staged before the transaction. A failed database write compensates new provider objects; cover replacement removes old owned references only after commit.

## Invariants

- Product code is unique within Store; SKU and Product slug are globally unique.
- Existing Variant IDs must belong to the Product being edited.
- Seller can mutate only Products in owned Stores.
- `on_hand >= 0`, `reserved >= 0`, `reserved <= on_hand`; available is derived.
- Public pages expose only active Seller, Store and Product records.

## Read, security and quality

Listings aggregate variants and availability in grouped SQL; Blade performs no queries. Authorization is server-side permission plus ownership. Image MIME/size/dimensions use the unified media/provider boundary. Read-only Data Quality rules validate Product ownership and Inventory invariants without auto-fixing business data.
