# Non-Payroll Cost Center Historical Dimensions

## Nguyên tắc

Cost Center được chụp tại business-event boundary; trạng thái Organization, Employee, Asset, Project hoặc Technician hiện tại không được dùng để viết lại lịch sử. Migration rollout chỉ bổ sung cột nullable và không backfill dữ liệu cũ.

## Finance Expense

`finance_expenses` giữ `cost_center_id`, code và name snapshot khi tạo draft. Cost Center được kiểm tra cùng Company. Khi approved, chỉ dòng Operating Expense của journal nhận dimension; Expense Payable, Cash và các dòng Balance Sheet không được gắn Cost Center trang trí. Expense đã approved/posted không có flow sửa dimension.

## Asset và Depreciation

Asset assignment là effective-dated. Mỗi lần depreciation, Finance chọn assignment có hiệu lực tại ngày cuối kỳ và chụp Cost Center vào `finance_depreciation_entries` cùng dòng Depreciation Expense. Accumulated Depreciation không mang Cost Center. Việc chuyển Asset sau đó không thay đổi depreciation entry hoặc journal cũ.

## Project và Maintenance

Project/Maintenance không ghi trực tiếp Finance. Cost Center được người vận hành truyền explicit qua integration service:

`Project/Maintenance → FinanceOperationsService → Finance Expense → Accounting`

và:

`Project/Maintenance → ProcurementService → Purchase Request`

Không suy ra Cost Center từ Project Manager, requester hoặc technician hiện tại.

## Procurement Commitment

Purchase Request hiện có một Cost Center cho toàn request. Vì mỗi PO hiện được tạo từ đúng một PR (`purchase_request_id` unique), Cost Center snapshot được truyền vào PO và từng PO line. Commitment được tính trên remaining quantity của PO line theo Cost Center snapshot.

Vendor Bill và Bill lines giữ tiếp dimension để bảo toàn lineage. Goods Receipt, inventory asset, GRNI, AP và cash lines không được gắn dimension vì chúng là Balance Sheet evidence, không phải Cost Center expense.

Khi nhận hàng, `received_quantity` tăng và open commitment giảm. Vì Actual chỉ đọc posted P&L expense lines, hệ thống không cộng cùng một amount vừa là commitment vừa là actual.

## Legacy rollout

- Không UPDATE/backfill record lịch sử.
- Record có snapshot cũ hợp lệ được dùng trực tiếp.
- Record thiếu snapshot được phân loại `NOT_ENOUGH_EVIDENCE` và giữ unattributed.
- Payroll legacy `1.558.042.912 VND` tiếp tục unattributed; thay đổi này không tác động Payroll.

## Bất biến kế toán

- Debit delta: 0.
- Credit delta: 0.
- Business amount delta: 0.
- Cost Center chỉ được phép trên account type `expense` qua generic source posting boundary.
- Retry tiếp tục sử dụng `generic_source_key`, source idempotency key và unique document relationships hiện hữu.

## Cost Center workspace

Workspace đọc Budget, attributed open Commitment và posted GL expense. Công thức:

`Remaining = Budget - attributed Actual - attributed open Commitment`

Revenue và COGS vẫn là `NOT_ENOUGH_EVIDENCE`; workspace không phải Cost Center P&L.
