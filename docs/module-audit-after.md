# Phase 1B — Organization Core audit

Ngày kiểm tra: 2026-07-23. Database: MySQL `schema_admin`.

`CostCenter` và `JobPosition` không tồn tại dưới dạng module riêng. Hai bounded context này được giữ trong
module `Organization` hiện có để tránh tạo module trùng. Module đã có implementation thật cho model,
CQRS command/query handler, read/write repository, policy, request, controller, routes, Blade, event,
listener, cache, activity log, broadcast, seeder và tests.

Phạm vi hoàn thành: cost center CRUD có xóa kiểm soát; organization unit CRUD/tree/move/cycle detection;
job position CRUD/headcount/critical; headcount read model people/FTE/vacancy; dashboard và tree JSON.
Employee chỉ được đọc từ `employees` và `employee_assignments`, không triển khai Employee CRUD.

Ngoài phạm vi Phase 1B và vẫn là skeleton: Recruitment, Attendance, Leave, Payroll và các module HRM khác.

## Phase 2.5A quality gate

Organization passes PHPStan level 10 with zero errors. Five export placeholders were replaced with
tenant-scoped CSV streams through QueryBus and the read repository. Feature tests cover authentication,
permissions, tenant isolation, filters, hierarchy, UTF-8, decimal precision and formula injection.
# Employee module — 2026-07-24

Employee PHPStan level 10 đạt 0. Các route nghiệp vụ mới được nối qua Command/Query Bus hoặc typed domain service, tenant-scoped policies và permission middleware. Xem `docs/employee-completion-after.md` để biết chính xác phần completed/partial/blocked.
