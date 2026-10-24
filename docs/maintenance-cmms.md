# Maintenance / CMMS

Maintenance là bounded context sở hữu yêu cầu bảo trì, Work Order và lịch bảo trì định kỳ. Module không sao chép Asset, Employee, Inventory, Procurement, Expense, DMS hay Workflow.

Luồng corrective: `Finance Asset → Maintenance Request → submit → maker-checker approval → Work Order → technician Employee → PLANNED → IN_PROGRESS → COMPLETED`. Request có unique conversion và transition lặp lại không tạo thêm Work Order.

Luồng preventive: `Plan + due occurrence → Work Order`. Unique `(preventive_plan_id, preventive_due_date)` bảo vệ retry; khi Work Order hoàn tất, ngày tới được tính từ frequency đã cấu hình.

Spare part là requirement của Maintenance nhưng tồn kho và giá vốn do `InventoryService` sở hữu. Việc issue tạo canonical `inventory_movements` với idempotency key; Maintenance chỉ lưu movement reference và actual-cost evidence. Không có ghi trực tiếp vào balance.

DMS hỗ trợ request, Work Order và plan bằng private versioned evidence/SHA-256. Approval request hiện là native maker-checker; Workflow/Notification, Procurement, Expense và Accounting giữ trạng thái `PARTIAL` cho đến khi owner module có canonical Work Order reference. Không tạo bản ghi giả để đổi trạng thái.

Tenant scope được kiểm tra cho Company, Asset, Branch, Employee và inventory location/product. Downtime chỉ ghi khi execution cung cấp evidence. MTTR/MTBF là `NOT_ENOUGH_EVIDENCE` cho tới khi có đủ failure/start/end history tin cậy.

HTTP writes đi qua FormRequest, explicit Command, CommandBus, Handler và `MaintenanceService`. Reads đi qua QueryBus và `MaintenanceReadService`. Activity Log lưu transition metadata, không lưu file content hay secret.
