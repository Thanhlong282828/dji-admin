# Cross-module Synchronization

## POS / Commerce → Finance

POS checkout khóa Visit, kiểm tra trạng thái, lấy giá snapshot server-side, trừ inventory bằng canonical `InventoryService`, tạo đúng một Receipt và đúng một `branch_management_revenues` row trong cùng transaction. Branch Finance và Dashboard cùng đọc revenue row đó. Retry cùng Visit trả Receipt hiện hữu và không ghi doanh thu lần hai.

Marketplace Order chỉ được ghi nhận doanh thu qua payment/fulfillment policy hiện hành. Sales History join canonical revenue; không cộng đơn unpaid/pending.

Sales History hiện hợp nhất hai nguồn Marketplace và POS. Full reversal không sửa chứng từ gốc: nó tạo `commerce_sale_reversals`, hoàn kho đúng một lần qua `InventoryService`, rồi Branch Finance/Dashboard trừ chứng từ bù để ra recognized revenue thuần.

## Inventory lifecycle

Nhập kho dùng `inventory_stock_receipts`: draft → received → một inbound movement. Chuyển kho dùng `inventory_stock_transfers`: draft → in_transit (giảm kho nguồn) → received (tăng kho đích). Reference, transition và movement idempotency ngăn retry ghi đôi; tổng tồn giữa hai chi nhánh được bảo toàn khi transfer hoàn tất.

## HRM → Payroll → Finance

Assignment/Attendance/Leave/OT/Compensation là upstream. Payroll Input là point-in-time snapshot. Kỳ mutable có thể regenerate bằng service; run finalized không bị rewrite. Payslip, PIT, insurance, accounting và Branch Finance đọc Payroll result/snapshot, không nhận số seed cuối độc lập.

## Cache/invalidation

Executive Dashboard được query tươi theo Company qua QueryBus/read services vì nhiều source module cùng đóng góp và trước đây chưa có invalidation đầy đủ cho mọi Employee/Leave/Recruitment mutation. Vì vậy checkout/status/source change phản ánh ngay và không cần global cache flush. Nếu bổ sung cache sau này phải dùng version/key theo company + branch + period và invalidation từ successful mutation của mọi owner.

## Không đồng bộ bằng clone

- Không sao chép Product cho POS.
- Không sao chép Customer cho Sales History.
- Không ghi payroll cost riêng nếu derive được từ Payroll.
- Không seed P&L/dashboard totals.
- Không dùng JavaScript array để thay dữ liệu DB.
