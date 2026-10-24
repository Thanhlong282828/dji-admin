# Inventory and Sales Reconciliation

## Equation

For each SKU/location:

`Opening on_hand + inbound movements - outbound movements ± corrections = Closing on_hand`

`available = on_hand - reserved`, with `0 ≤ reserved ≤ on_hand`.

## POS checkout

`Visit product line → InventoryService locked adjustment → inventory_movements → balance version increment → paid Receipt → recognized Revenue`.

Each movement stores variant, location, type, signed delta, before/after, source, reference, actor and idempotency key. A POS line uses `pos-visit:{visit}:line:{line}`; a repeated checkout returns the existing Receipt before any new movement/revenue write.

## Stock receipt and transfer

`Receipt draft → received → receipt movement → balance`.

`Transfer draft → in_transit → source movement → received → destination movement`.

Hai flow dùng chứng từ company/branch scoped, row lock và idempotency key. Transfer không tạo hoặc làm mất tổng tồn giữa các location.

## Historical safety and sale reversal

Paid Receipt/Order, line snapshots, source revenue và movements không bị xóa hoặc sửa. Full reversal tạo chứng từ bù append-only, restock bằng canonical InventoryService đúng một lần và giảm doanh thu thuần ở Sales History, Branch Finance và Dashboard. Partial return/refund chưa được hỗ trợ và không được mô phỏng bằng sửa chứng từ gốc.

## Current scope decision

Stock receipt và inter-branch transfer đã được xác nhận là mắt xích bắt buộc cho runtime nhiều kho nên được triển khai với document lifecycle riêng. Purchase order/supplier invoice và partial return vẫn chưa có domain nguồn đầy đủ, vì vậy không được tạo giả.
