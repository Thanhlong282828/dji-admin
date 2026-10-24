# POS Operating Model

## Canonical flow

`Company → Branch → Customer → Visit → Company Service / Product SKU at Branch Inventory → Checkout Command → Inventory Movement → POS Receipt → Recognized Revenue → Branch Finance → Executive Dashboard`.

POS reuses Product, SKU, Inventory, Customer, Branch and Finance. It does not create POS-only product, stock, customer or revenue clones.

## Navigation

The existing runtime is exposed as one top-level **POS / Bán hàng tại quầy** group with actual pages only: Dashboard POS, Thu ngân/Check-in/Checkout, Customer, Company Service, Branch Warehouse, POS Sales và Receipt History. Inventory receipt/transfer nằm ở Inventory Operations; unified sale reversal nằm ở Sales History, không tạo POS-only clone.

## Payment boundary

Current methods are internal evidence (`cash`, card evidence, manual transfer). They are not external gateway confirmation. Server-side catalog/service prices and quantities determine totals.

## Immutability and retry

Checkout is transactional and idempotent per Visit. A checked-out Visit is immutable. Receipt/revenue/inventory history uses append-only evidence. Full correction đi qua explicit sale reversal; original receipt vẫn giữ nguyên và restock/revenue compensation chỉ chạy một lần.
