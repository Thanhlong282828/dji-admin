# Enterprise Executive Dashboard

Đây là command center read-only thuộc `Modules/Dashboard`, không lưu KPI và không sửa owner-domain.

Luồng CQRS: `HTTP → QueryBus → GetEnterpriseExecutiveDashboardQuery → Handler → EnterpriseExecutiveDashboardReadService → EnterpriseAnalyticsReadService → Finance/SCM/CRM owner reads → View`.

Nguồn: Revenue/COGS/profit/payroll từ posted GL; AR/AP từ invoice/bill và allocation; cash flow từ Treasury; inventory từ valuation/SCM; people/CRM từ read model canonical. Company bắt buộc, Branch được kiểm tra bởi `BranchAccessService`, khoảng ngày tối đa 24 tháng.

Biểu đồ nhận integer minor units đã serialize an toàn, resize theo container và reflow ở desktop/tablet/mobile. Không query DB trong Blade hoặc theo từng điểm.

Giới hạn được giữ rõ: chưa có monthly owner statement series cho Gross Profit/Operating Result; closing cash thiếu opening balance; inventory trend, Project billing và Maintenance MTTR/MTBF không được suy đoán.

## Phase 2 controls

KPI drill-down is permission-aware: `analytics.view` authorizes the aggregate page but never grants Finance, Treasury, Sales, Procurement, Inventory, Project, Maintenance or Payroll navigation. Compatible company/branch/date context is retained on financial destinations; owner routes still enforce their own authorization and tenant policy.

AR and AP aging are calculated in the Finance owner read service from open invoice/bill balances and due dates: Current, 1–30, 31–60, 61–90 and 90+. Buckets remain non-clickable until owner screens publish an aging-filter contract. There is no fabricated historical AR/AP snapshot.

The Exception Center composes Approval Inbox, AR/AP overdue balances, Project risk/delay, Maintenance overdue work and Inventory alerts. Severity is deterministic, not an AI score. Data Quality remains an on-demand link because evaluating all audit rules inside every dashboard request would create unacceptable query growth.

Chart interaction lives in `resources/js/admin/executive-dashboard.js`: responsive SVG paths, nearest-point hit areas, vertical crosshair, active points, exact VND tooltip and ResizeObserver. Gross Profit and Operating Result remain period KPIs until Finance exposes a canonical monthly statement series; they are not synthesized from incomplete components.

Project and Maintenance expose small grouped `executiveSummary()` owner boundaries so the dashboard does not load operator paginators. The query-growth guard remains unchanged rather than raising its ceiling.

## Phase 3 — Branch profitability, Budget và forward outlook

- `ExecutiveFinanceReadService` thuộc Finance và cung cấp ba hợp đồng đọc nhóm; Dashboard chỉ composition, không sở hữu phép tính hay ghi snapshot.
- Branch profitability lấy duy nhất journal đã post/export và `branch_id` được chụp tại journal. Revenue, COGS, Payroll, Depreciation và OPEX được phân loại từ cùng một GL nên Maintenance, Project, Procurement không bị cộng lần hai. Journal thiếu branch được công bố là unattributed và hạ trạng thái xuống `PARTIAL`.
- Budget dùng budget đã approved theo company/branch/account/month. Công thức hiện hữu là `Remaining = Budget - Actual - open PO Commitment`; Actual là expense từ posted GL và Commitment là phần PO chưa nhận. Commitment không phải expense.
- Forward outlook dùng outstanding Customer Invoice/Vendor Bill theo bốn bucket độc lập: 7 ngày, 8–30, 31–60 và 61–90. Invoice/bill đã thanh toán không tham gia.
- Outlook là nghĩa vụ theo due date, không phải cash chắc chắn. Không dự báo doanh thu, payroll tương lai hoặc projected closing cash khi thiếu opening balance/evidence canonical.
- Drill-down chỉ xuất hiện khi actor có permission của Finance/Procurement/Sales tương ứng; `analytics.view` không nâng quyền owner module.
- UI dùng bar chart cho category Branch/Budget/Outlook, metric selector gold, exact VND và semantic red/green cho giá trị âm/dòng tiền.

## Payroll → Accounting dimensional attribution

- Journal Payroll mới lưu `branch_id` và `branch_name_snapshot` trên từng journal line. Nhóm kế toán tách theo mapping, debit/credit, Branch, Cost Center, Organization Unit và source code; một Payroll Run đa chi nhánh không còn bị gắn vào một header Branch duy nhất.
- Branch được lấy từ `payroll_employee_inputs` của immutable Payroll Input Snapshot. Việc nhân viên chuyển chi nhánh sau kỳ lương không làm đổi chiều phân bổ lịch sử.
- Journal cũ không bị update/backfill. Finance đọc lineage bất biến `journal source → payroll item/employee → payroll run snapshot → payroll employee input` để phân bổ ở read time; phần không đủ chứng cứ vẫn công bố `unattributed` thay vì đoán.
- Organization Unit dùng tên snapshot của payroll input. Từ rollout migration `000098`, Cost Center ID/code/name cũng được chụp độc lập trên Payroll Employee Input; journal tương lai chỉ đọc snapshot này. Payroll legacy trước rollout vẫn không được suy ra từ Organization Unit hoặc trạng thái nhân viên hiện tại.
- Employer statutory contribution được nhận diện bằng source type kế toán; phép phân loại không phụ thuộc duy nhất vào mã tài khoản tùy biến của từng công ty. Tổng Debit/Credit và số tiền Payroll không đổi.
