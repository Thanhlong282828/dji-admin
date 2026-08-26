# Commerce C4 product catalog audit

Date: 2026-08-26

## Existing runtime evidence

- `Modules/Product` and `Modules/ProductCategory` are disabled scaffolds: write actions are empty and create/show/edit views do not exist. They remain disabled.
- The active ownership chain is `User → seller_profiles → stores`. Store has no truthful Company/Branch relation, so inventory cannot infer a Branch.
- Existing Product/ProductCategory permissions are reused. C4 adds only inventory read/update permissions.
- Product media reuses the canonical `MediaService`; no second Cloudflare client is created.

## Proven gaps and decisions

- The active schema lacked Product, Variant, SKU and Inventory persistence.
- C4 implements `Seller Profile → Store → Product → Variant/SKU → Store Inventory Location → Inventory Balance` in the current runtime.
- `available = on_hand - reserved`; quantities are non-negative integers and updates lock the balance row.
- VND prices are integer minor units. Rating, review, promotion, Order and moderation claims are not fabricated.
- Product status is limited to the implemented `draft`, `active`, `archived` lifecycle.

## Migration decision

One additive migration is justified. It creates five owned catalog tables and does not alter historical HR, Payroll or Commerce records.
