# Cost Center Cost Management

## Phạm vi

Workspace **Chi phí theo Cost Center** là read model quản trị chi phí của Finance. Trang này không phải Cost Center P&L và không suy đoán doanh thu hoặc COGS khi nguồn nghiệp vụ chưa có Cost Center snapshot đáng tin cậy.

Luồng đọc chuẩn:

`Organization Cost Center → Finance Budget → Posted General Ledger → Cost Center Cost Management`

## Công thức

- `Actual`: tổng debit trừ credit của các dòng tài khoản chi phí trong journal đã posted/exported và có `cost_center_id`.
- `Commitment`: cam kết Purchase Order còn mở chỉ được phân bổ cho Cost Center khi nguồn mua hàng có bằng chứng Cost Center. Nếu chưa có, giá trị được công bố là unattributed/partial evidence, không gán đại vào Cost Center hiện tại.
- `Remaining = Budget - Actual - attributed Commitment`.
- `Variance = Actual + attributed Commitment - Budget`.
- `Company expense = Attributed actual + Unattributed actual` và chênh lệch reconciliation phải bằng 0.

Các thành phần Payroll, employer statutory, depreciation, project, maintenance và other OPEX là breakdown của cùng tập dòng GL. Chúng không được cộng thêm lần nữa vào Actual.

## Nguồn và bằng chứng

- Budget: `finance_budget_lines` trạng thái approved, theo kỳ và Cost Center.
- Actual: `finance_accounting_journal_lines` thuộc journal posted/exported, theo ngày và Cost Center snapshot.
- Payroll tương lai: Cost Center snapshot bất biến tại `payroll_employee_inputs`, sau đó được truyền vào accounting expense lines.
- Payroll lịch sử không có snapshot: hiển thị rõ là **Unattributed Cost**. Không hồi tố bằng assignment hoặc Cost Center hiện tại.
- Revenue và COGS: `NOT_ENOUGH_EVIDENCE` cho tới khi owner domain tạo snapshot Cost Center hợp lệ.

`UNKNOWN` không đồng nghĩa với `0`. Một giá trị chỉ được hiển thị bằng 0 khi truy vấn nguồn chuẩn xác nhận không có giá trị trong phạm vi đã chọn.

## Bảo mật và hiệu năng

- Route yêu cầu `finance.operations.view` và company access hiện hữu.
- Cost Center filter phải thuộc đúng company; tham chiếu chéo company bị từ chối.
- Các drilldown Payroll/GL chỉ hiện khi actor có permission tương ứng.
- Read service dùng truy vấn grouped theo nguồn; số query không tăng theo số lượng Cost Center và không có query-per-row.

## Giới hạn hiện tại

- Open Purchase Order chưa có Cost Center dimension chuẩn nên commitment có thể ở trạng thái `PARTIAL_EVIDENCE`.
- Legacy Payroll hiện tại vẫn giữ nguyên lịch sử và được báo cáo unattributed.
- Trang không tạo Revenue/COGS, không tạo journal và không mutate dữ liệu Finance.
