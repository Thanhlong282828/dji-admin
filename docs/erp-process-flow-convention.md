# ERP process-flow convention

## Purpose

The visible process strip and contextual `?` help share one resolved `menuFlow`. The strip is presentation metadata only: it explains lineage and navigation, and never infers transaction completion or mutates an owner domain.

## Ownership

Definitions for important flows live beside their owning module:

- `Modules/Commerce/resources/process-flows.php`
- `Modules/Procurement/resources/process-flows.php`
- `Modules/Payroll/resources/process-flows.php`
- `Modules/Finance/resources/process-flows.php`
- equivalent files in Organization, Employee, Inventory, Project, Maintenance, Document, Workflow, Notification and Dashboard.

`App\Support\Navigation\ProcessFlowRegistry` is the shared read-only adapter. `AdminHelpRegistry` remains the route-to-help contract and supplies its resolved flow to both render surfaces. The renderer is `resources/views/components/admin-process-flow.blade.php`.

## Adding or changing a flow

1. Open the owning module's `resources/process-flows.php`.
2. Add an entry keyed by the canonical `AdminNavigation` item key.
3. Set `current` to exactly one node key.
4. Add ordered nodes. Use an existing navigation key when the node should deep-link. For an explanatory stage without a dedicated page, give it an explicit permission and leave it without a navigation destination.
5. Never invent a route merely to make a node clickable. Never represent a future node as completed.
6. Update focused route/current-path tests when business order changes.

Each node may provide `key`, `label`, `navigation`, `permission`, `icon` and `help`. Navigation metadata supplies the real named route, parameters and permission. The resolver classifies nodes before the current node as `upstream`, the selected node as `current`, and later nodes as `future`.

## Authorization

The layout filters every node with the same permission semantics as the sidebar. Unauthorized nodes and links are not rendered. A virtual contextual node must carry an explicit owner permission. Named routes are checked before a link is emitted.

## Motion and accessibility

The wordmark, active upstream connectors, current-node border, primary brand buttons and focused input border reuse the same metallic-gold gradient and duration tokens. Future connectors never animate. Danger, warning, success and information colors remain semantic.

`prefers-reduced-motion: reduce` disables brand, connector, button and focus movement while preserving position, border and contrast cues. Links retain keyboard `focus-visible` outlines. On narrow screens the component scrolls internally and does not enlarge the document viewport.

## Ownership completeness

All 127 canonical Sidebar destinations have an explicit module-owned or shared-Core definition. Normal business menus never use a centralized fallback: a missing owner definition fails fast. The only generic fallback is reserved for non-menu emergency help routes.

Every definition declares a stable flow ID, category, current node, unique nodes and validated linear edges. Both contextual help and the visible strip consume the same resolved `menuFlow`.

## Complete destination lookup (122/122)

| Menu | Route | Category | Owner module | Definition file | Flow ID | Current node |
|---|---|---|---|---|---|---|
| Dashboard | `dashboard` | `READ_MODEL` | `Dashboard` | `Modules/Dashboard/resources/process-flows.php` | `dashboard.dashboard` | `dashboard` |
| Công ty | `admin.companies.index` | `MASTER_DATA` | `Company` | `Modules/Company/resources/process-flows.php` | `company.organization-runtime` | `companies` |
| Chi nhánh | `admin.branches.index` | `MASTER_DATA` | `Company` | `Modules/Company/resources/process-flows.php` | `company.organization-runtime` | `branches` |
| Trung tâm chi phí | `admin.cost-centers.index` | `MASTER_DATA` | `Organization` | `Modules/Organization/resources/process-flows.php` | `organization.company-structure-workforce` | `cost-centers` |
| Đơn vị tổ chức | `admin.organization-units.index` | `MASTER_DATA` | `Organization` | `Modules/Organization/resources/process-flows.php` | `organization.company-structure-workforce` | `organization-units` |
| Cây tổ chức | `admin.organization-units.tree` | `READ_MODEL` | `Organization` | `Modules/Organization/resources/process-flows.php` | `organization.company-structure-workforce` | `organization-tree` |
| Vị trí công việc | `admin.job-positions.index` | `MASTER_DATA` | `Organization` | `Modules/Organization/resources/process-flows.php` | `organization.company-structure-workforce` | `job-positions` |
| Báo cáo định biên | `admin.organization.headcount` | `REPORT` | `Organization` | `Modules/Organization/resources/process-flows.php` | `organization.company-structure-workforce` | `headcount` |
| Cổng thông tin nhân viên | `employee.portal` | `READ_MODEL` | `Employee` | `Modules/Employee/resources/process-flows.php` | `employee.employee-portal` | `employee-portal` |
| Nhân viên | `admin.employees.index` | `MASTER_DATA` | `Employee` | `Modules/Employee/resources/process-flows.php` | `employee.employees` | `employees` |
| Vòng đời nhân sự | `admin.employee-lifecycle.index` | `BUSINESS_PROCESS` | `Employee` | `Modules/Employee/resources/process-flows.php` | `employee.employee-lifecycle` | `employee-lifecycle` |
| Hợp đồng & Offboarding | `admin.hr.operations.index` | `BUSINESS_PROCESS` | `Employee` | `Modules/Employee/resources/process-flows.php` | `employee.hr-operations` | `hr-operations` |
| HR Data Governance | `admin.hr.governance.dashboard` | `CONTROL` | `Employee` | `Modules/Employee/resources/process-flows.php` | `employee.hr-governance` | `hr-governance` |
| Chấm công | `admin.attendance.index` | `BUSINESS_PROCESS` | `Attendance` | `Modules/Attendance/resources/process-flows.php` | `attendance.workforce-to-payroll` | `attendance` |
| Ca và lịch làm việc | `admin.workforce.management` | `CONFIGURATION` | `Attendance` | `Modules/Attendance/resources/process-flows.php` | `attendance.workforce-to-payroll` | `shifts` |
| Nghỉ phép | `admin.leave.index` | `BUSINESS_PROCESS` | `Leave` | `Modules/Leave/resources/process-flows.php` | `leave.policy-request-payroll` | `leave` |
| Chính sách nghỉ phép | `admin.leave-management.index` | `CONFIGURATION` | `Leave` | `Modules/Leave/resources/process-flows.php` | `leave.policy-request-payroll` | `leave-management` |
| Tăng ca | `admin.overtime.index` | `BUSINESS_PROCESS` | `Attendance` | `Modules/Attendance/resources/process-flows.php` | `attendance.workforce-to-payroll` | `overtime` |
| Tổng quan tuyển dụng | `admin.recruitment.dashboard` | `BUSINESS_PROCESS` | `Recruitment` | `Modules/Recruitment/resources/process-flows.php` | `recruitment.candidate-to-hire` | `recruitment` |
| Yêu cầu tuyển dụng | `admin.recruitment-requisitions.index` | `BUSINESS_PROCESS` | `Recruitment` | `Modules/Recruitment/resources/process-flows.php` | `recruitment.candidate-to-hire` | `requisitions` |
| Ứng viên | `admin.candidates.index` | `BUSINESS_PROCESS` | `Recruitment` | `Modules/Recruitment/resources/process-flows.php` | `recruitment.candidate-to-hire` | `candidates` |
| Pipeline ứng tuyển | `admin.applications.pipeline` | `BUSINESS_PROCESS` | `Recruitment` | `Modules/Recruitment/resources/process-flows.php` | `recruitment.candidate-to-hire` | `applications` |
| Phỏng vấn | `admin.interviews.index` | `BUSINESS_PROCESS` | `Recruitment` | `Modules/Recruitment/resources/process-flows.php` | `recruitment.candidate-to-hire` | `interviews` |
| Offer tuyển dụng | `admin.candidate-offers.index` | `BUSINESS_PROCESS` | `Recruitment` | `Modules/Recruitment/resources/process-flows.php` | `recruitment.candidate-to-hire` | `candidate-offers` |
| Onboarding | `admin.onboarding.dashboard` | `BUSINESS_PROCESS` | `Onboarding` | `Modules/Onboarding/resources/process-flows.php` | `onboarding.offer-to-employee` | `onboarding` |
| Hiệu suất và tài năng | `admin.talent.performance.index` | `BUSINESS_PROCESS` | `Performance` | `Modules/Performance/resources/process-flows.php` | `performance.employee-talent-lifecycle` | `performance` |
| Career và kế nhiệm | `admin.career.index` | `BUSINESS_PROCESS` | `Performance` | `Modules/Performance/resources/process-flows.php` | `performance.employee-talent-lifecycle` | `career` |
| Cấu hình lương | `admin.payroll.delivery.index` | `CONFIGURATION` | `Payroll` | `Modules/Payroll/resources/process-flows.php` | `payroll.payroll-dashboard` | `payroll-dashboard` |
| Kỳ lương | `admin.payroll.periods.index` | `MASTER_DATA` | `Payroll` | `Modules/Payroll/resources/process-flows.php` | `payroll.payroll-periods` | `payroll-periods` |
| Payroll Inputs | `admin.payroll.inputs.index` | `BUSINESS_PROCESS` | `Payroll` | `Modules/Payroll/resources/process-flows.php` | `payroll.payroll-inputs` | `payroll-inputs` |
| Payroll Runs | `admin.payroll.runs.index` | `BUSINESS_PROCESS` | `Payroll` | `Modules/Payroll/resources/process-flows.php` | `payroll.payroll-runs` | `payroll-runs` |
| Điều chỉnh lương | `admin.payroll.adjustments.index` | `BUSINESS_PROCESS` | `Payroll` | `Modules/Payroll/resources/process-flows.php` | `payroll.payroll-adjustments` | `payroll-adjustments` |
| Thưởng chi nhánh | `admin.branch-bonuses.index` | `BUSINESS_PROCESS` | `Payroll` | `Modules/Payroll/resources/process-flows.php` | `payroll.branch-bonuses` | `branch-bonuses` |
| Phiếu lương | `admin.payroll.payslips.index` | `READ_MODEL` | `Payroll` | `Modules/Payroll/resources/process-flows.php` | `payroll.payslips` | `payslips` |
| Thanh toán | `admin.payroll.payment-batches.index` | `BUSINESS_PROCESS` | `Payroll` | `Modules/Payroll/resources/process-flows.php` | `payroll.payments` | `payments` |
| Tổng quan thuế & nghĩa vụ | `admin.finance.tax-dashboard` | `READ_MODEL` | `Finance` | `Modules/Finance/resources/process-flows.php` | `finance.finance-tax-dashboard` | `finance-tax-dashboard` |
| Hồ sơ thuế TNCN | `admin.payroll.tax.employees.index` | `READ_MODEL` | `Payroll` | `Modules/Payroll/resources/process-flows.php` | `payroll.tax-administration` | `tax-administration` |
| Chính sách thuế | `admin.payroll.tax.policies.index` | `CONFIGURATION` | `Payroll` | `Modules/Payroll/resources/process-flows.php` | `payroll.tax-policies` | `tax-policies` |
| Hồ sơ BHXH/BHYT/BHTN | `admin.payroll.insurance.employees.index` | `READ_MODEL` | `Payroll` | `Modules/Payroll/resources/process-flows.php` | `payroll.insurance-administration` | `insurance-administration` |
| Chính sách bảo hiểm | `admin.payroll.insurance.policies.index` | `CONFIGURATION` | `Payroll` | `Modules/Payroll/resources/process-flows.php` | `payroll.insurance-policies` | `insurance-policies` |
| Đối soát thuế TNCN năm | `admin.payroll.annual-tax.index` | `REPORT` | `Payroll` | `Modules/Payroll/resources/process-flows.php` | `payroll.annual-tax` | `annual-tax` |
| Government Filing | `admin.payroll.government-filings.index` | `EXTERNAL` | `Payroll` | `Modules/Payroll/resources/process-flows.php` | `payroll.government` | `government` |
| Tổng quan tài chính | `admin.finance.overview` | `READ_MODEL` | `Finance` | `Modules/Finance/resources/process-flows.php` | `finance.finance-overview` | `finance-overview` |
| Chi phí theo Cost Center | `admin.finance.cost-center-costs` | `READ_MODEL` | `Finance` | `Modules/Finance/resources/process-flows.php` | `finance.cost-center-cost-management` | `cost-center-cost-management` |
| Sales · AR · Collection | `admin.sales.index` | `BUSINESS_PROCESS` | `Commerce` | `Modules/Commerce/resources/process-flows.php` | `commerce.sales-receivable` | `sales-receivable` |
| Nhà cung cấp | `admin.procurement.vendors` | `BUSINESS_PROCESS` | `Procurement` | `Modules/Procurement/resources/process-flows.php` | `procurement.vendor-to-ap.procurement-vendors` | `procurement-vendors` |
| Yêu cầu mua hàng | `admin.procurement.requests` | `BUSINESS_PROCESS` | `Procurement` | `Modules/Procurement/resources/process-flows.php` | `procurement.vendor-to-ap.purchase-requests` | `purchase-requests` |
| Đơn mua hàng | `admin.procurement.orders` | `BUSINESS_PROCESS` | `Procurement` | `Modules/Procurement/resources/process-flows.php` | `procurement.vendor-to-ap.purchase-orders` | `purchase-orders` |
| Phiếu nhận hàng | `admin.procurement.receipts` | `BUSINESS_PROCESS` | `Procurement` | `Modules/Procurement/resources/process-flows.php` | `procurement.vendor-to-ap.goods-receipts` | `goods-receipts` |
| Trả hàng nhà cung cấp | `admin.procurement.goods-returns` | `BUSINESS_PROCESS` | `Procurement` | `Modules/Procurement/resources/process-flows.php` | `procurement.vendor-to-ap.goods-returns` | `goods-returns` |
| Nghiệm thu dịch vụ | `admin.procurement.service-acceptances` | `BUSINESS_PROCESS` | `Procurement` | `Modules/Procurement/resources/process-flows.php` | `procurement.vendor-to-ap.service-acceptances` | `service-acceptances` |
| Hóa đơn NCC · AP | `admin.procurement.payables` | `BUSINESS_PROCESS` | `Procurement` | `Modules/Procurement/resources/process-flows.php` | `procurement.vendor-to-ap.vendor-bills` | `vendor-bills` |
| Chi phí · Tài sản · Ngân sách | `admin.finance.operations` | `BUSINESS_PROCESS` | `Finance` | `Modules/Finance/resources/process-flows.php` | `finance.finance-operations` | `finance-operations` |
| Treasury · Dòng tiền | `admin.finance.treasury` | `BUSINESS_PROCESS` | `Finance` | `Modules/Finance/resources/process-flows.php` | `finance.treasury` | `treasury` |
| Accounting Period Close | `admin.finance.period-close` | `CONTROL` | `Finance` | `Modules/Finance/resources/process-flows.php` | `finance.accounting-period-close` | `accounting-period-close` |
| Accounting Adjustments | `admin.finance.accounting-adjustments` | `BUSINESS_PROCESS` | `Finance` | `Modules/Finance/resources/process-flows.php` | `finance.accounting-adjustments` | `accounting-adjustments` |
| Hiệu quả tài chính chi nhánh | `admin.finance.branch-performance` | `READ_MODEL` | `Finance` | `Modules/Finance/resources/process-flows.php` | `finance.branch-financial-performance` | `branch-financial-performance` |
| Đối soát Payroll | `admin.finance.payroll-reconciliation` | `CONTROL` | `Finance` | `Modules/Finance/resources/process-flows.php` | `finance.payroll-finance-reconciliation` | `payroll-finance-reconciliation` |
| Sổ cái & Báo cáo tài chính | `admin.finance.financial-statements` | `REPORT` | `Finance` | `Modules/Finance/resources/process-flows.php` | `finance.financial-statements` | `financial-statements` |
| Kế toán và bút toán | `admin.payroll.accounting.journals.index` | `BUSINESS_PROCESS` | `Finance` | `Modules/Finance/resources/process-flows.php` | `finance.accounting` | `accounting` |
| Cấu hình kế toán | `admin.payroll.accounting.policies.index` | `CONFIGURATION` | `Payroll` | `Modules/Payroll/resources/process-flows.php` | `payroll.accounting-configuration` | `accounting-configuration` |
| ERP Export | `admin.payroll.erp.exports.index` | `EXTERNAL` | `Payroll` | `Modules/Payroll/resources/process-flows.php` | `payroll.erp` | `erp` |
| Kết nối ERP | `admin.payroll.erp.connections.index` | `CONFIGURATION` | `Payroll` | `Modules/Payroll/resources/process-flows.php` | `payroll.erp-configuration` | `erp-configuration` |
| Tổng quan chuỗi cung ứng | `admin.supply-chain.index` | `READ_MODEL` | `Dashboard` | `Modules/Dashboard/resources/process-flows.php` | `dashboard.supply-chain-overview` | `supply-chain-overview` |
| Tổng quan dự án | `admin.projects.overview` | `READ_MODEL` | `Project` | `Modules/Project/resources/process-flows.php` | `project.overview` | `project-overview` |
| Dự án | `admin.projects.index` | `BUSINESS_PROCESS` | `Project` | `Modules/Project/resources/process-flows.php` | `project.delivery` | `projects` |
| Tổng quan bảo trì | `admin.maintenance.overview` | `READ_MODEL` | `Maintenance` | `Modules/Maintenance/resources/process-flows.php` | `maintenance.overview` | `maintenance-overview` |
| Yêu cầu bảo trì | `admin.maintenance.requests` | `BUSINESS_PROCESS` | `Maintenance` | `Modules/Maintenance/resources/process-flows.php` | `maintenance.request` | `maintenance-requests` |
| Lệnh công việc | `admin.maintenance.work-orders` | `BUSINESS_PROCESS` | `Maintenance` | `Modules/Maintenance/resources/process-flows.php` | `maintenance.work-order` | `maintenance-work-orders` |
| Bảo trì định kỳ | `admin.maintenance.plans` | `CONFIGURATION` | `Maintenance` | `Modules/Maintenance/resources/process-flows.php` | `maintenance.preventive-plan` | `maintenance-plans` |
| Executive Dashboard | `admin.executive.index` | `READ_MODEL` | `Dashboard` | `Modules/Dashboard/resources/process-flows.php` | `dashboard.executive-dashboard` | `executive-dashboard` |
| Phân tích điều hành | `admin.analytics.enterprise` | `READ_MODEL` | `Dashboard` | `Modules/Dashboard/resources/process-flows.php` | `dashboard.enterprise-analytics` | `enterprise-analytics` |
| Enterprise Analytics | `admin.analytics.workforce` | `READ_MODEL` | `Performance` | `Modules/Performance/resources/process-flows.php` | `performance.enterprise-planning` | `analytics` |
| Workforce Forecasting | `admin.analytics.forecasting` | `READ_MODEL` | `Performance` | `Modules/Performance/resources/process-flows.php` | `performance.enterprise-planning` | `forecasting` |
| Executive Planning | `admin.planning.executive` | `CONTROL` | `Performance` | `Modules/Performance/resources/process-flows.php` | `performance.enterprise-planning` | `executive-planning` |
| Planning Execution | `admin.planning.execution` | `CONTROL` | `Performance` | `Modules/Performance/resources/process-flows.php` | `performance.enterprise-planning` | `planning-execution` |
| Enterprise Reports | `admin.reports.index` | `REPORT` | `Performance` | `Modules/Performance/resources/process-flows.php` | `performance.enterprise-planning` | `enterprise-reports` |
| Kho tài liệu | `admin.documents.index` | `BUSINESS_PROCESS` | `Document` | `Modules/Document/resources/process-flows.php` | `document.enterprise-evidence` | `enterprise-documents` |
| Approval Inbox | `admin.approvals.index` | `CONTROL` | `Workflow` | `Modules/Workflow/resources/process-flows.php` | `workflow.owner-approval` | `approval-inbox` |
| Workflow Center | `admin.workflows.dashboard` | `CONTROL` | `Workflow` | `Modules/Workflow/resources/process-flows.php` | `workflow.owner-approval` | `workflow` |
| Workflow Definitions | `admin.workflows.definitions` | `CONFIGURATION` | `Workflow` | `Modules/Workflow/resources/process-flows.php` | `workflow.definition-to-runtime` | `workflow-definitions` |
| Workflow Tasks | `admin.workflows.tasks` | `CONTROL` | `Workflow` | `Modules/Workflow/resources/process-flows.php` | `workflow.owner-approval` | `workflow-tasks` |
| Notification Center | `admin.notifications.index` | `READ_MODEL` | `Notification` | `Modules/Notification/resources/process-flows.php` | `notification.delivery-lifecycle` | `notifications` |
| Notification Templates | `admin.notifications.templates.index` | `CONFIGURATION` | `Notification` | `Modules/Notification/resources/process-flows.php` | `notification.delivery-lifecycle` | `notification-templates` |
| Notification Deliveries | `admin.notifications.deliveries.index` | `READ_MODEL` | `Notification` | `Modules/Notification/resources/process-flows.php` | `notification.delivery-lifecycle` | `notification-deliveries` |
| Integration Hub | `admin.integrations.dashboard` | `EXTERNAL` | `Integration` | `Modules/Integration/resources/process-flows.php` | `integration.configuration-runtime-evidence` | `integrations` |
| IAM Governance | `admin.iam.dashboard` | `CONTROL` | `Iam` | `Modules/Iam/resources/process-flows.php` | `iam.access-governance` | `iam` |
| Access Requests | `admin.iam.requests.index` | `BUSINESS_PROCESS` | `Iam` | `Modules/Iam/resources/process-flows.php` | `iam.access-governance` | `access-requests` |
| Roles & Permissions | `admin.iam.roles` | `CONFIGURATION` | `Iam` | `Modules/Iam/resources/process-flows.php` | `iam.access-governance` | `roles-permissions` |
| Privileged Access | `admin.iam.privileged` | `CONTROL` | `Iam` | `Modules/Iam/resources/process-flows.php` | `iam.access-governance` | `privileged` |
| Segregation of Duties | `admin.iam.sod` | `CONTROL` | `Iam` | `Modules/Iam/resources/process-flows.php` | `iam.access-governance` | `segregation-of-duties` |
| Access Reviews | `admin.iam.reviews` | `CONTROL` | `Iam` | `Modules/Iam/resources/process-flows.php` | `iam.access-governance` | `access-reviews` |
| Audit Platform | `admin.audit.dashboard` | `CONTROL` | `Audit` | `Modules/Audit/resources/process-flows.php` | `audit.domain-events-evidence` | `audit` |
| Reliability | `admin.operations.reliability` | `SYSTEM` | `Core` | `Modules/Core/resources/process-flows.php` | `core.reliability` | `reliability` |
| Observability | `admin.operations.observability` | `SYSTEM` | `Core` | `Modules/Core/resources/process-flows.php` | `core.observability` | `observability` |
| Performance | `admin.operations.performance` | `SYSTEM` | `Core` | `Modules/Core/resources/process-flows.php` | `core.performance-engineering` | `performance-engineering` |
| Architecture | `admin.operations.architecture` | `CONTROL` | `Core` | `Modules/Core/resources/process-flows.php` | `core.architecture-governance` | `architecture-governance` |
| Test Quality | `admin.operations.test-quality` | `CONTROL` | `Core` | `Modules/Core/resources/process-flows.php` | `core.test-quality` | `test-quality` |
| API Contracts | `admin.operations.api-contracts` | `CONTROL` | `Core` | `Modules/Core/resources/process-flows.php` | `core.api-contracts` | `api-contracts` |
| Releases | `admin.operations.releases` | `CONTROL` | `Core` | `Modules/Core/resources/process-flows.php` | `core.releases` | `releases` |
| Runtime Configuration | `admin.operations.configuration` | `CONFIGURATION` | `Core` | `Modules/Core/resources/process-flows.php` | `core.configuration` | `configuration` |
| Secrets | `admin.operations.secrets` | `CONFIGURATION` | `Core` | `Modules/Core/resources/process-flows.php` | `core.secrets` | `secrets` |
| Data Protection | `admin.operations.data-protection` | `CONTROL` | `Core` | `Modules/Core/resources/process-flows.php` | `core.data-protection` | `data-protection` |
| Data Lifecycle | `admin.operations.data-lifecycle` | `CONTROL` | `Core` | `Modules/Core/resources/process-flows.php` | `core.data-lifecycle` | `data-lifecycle` |
| Data Quality | `admin.operations.data-quality` | `CONTROL` | `Core` | `Modules/Core/resources/process-flows.php` | `core.data-quality` | `data-quality` |
| Business Continuity | `admin.operations.continuity` | `CONTROL` | `Core` | `Modules/Core/resources/process-flows.php` | `core.continuity` | `continuity` |
| Security Assurance | `admin.operations.security-assurance` | `CONTROL` | `Core` | `Modules/Core/resources/process-flows.php` | `core.security-assurance` | `security-assurance` |
| Go-Live Readiness | `admin.operations.go-live` | `CONTROL` | `Core` | `Modules/Core/resources/process-flows.php` | `core.go-live-readiness` | `go-live-readiness` |
| API Documentation | `admin.operations.api-documentation` | `REPORT` | `Core` | `Modules/Core/resources/process-flows.php` | `core.api-documentation` | `api-documentation` |
| Tổng quan Seller | `admin.seller.dashboard` | `READ_MODEL` | `Commerce` | `Modules/Commerce/resources/process-flows.php` | `commerce.seller-dashboard` | `seller-dashboard` |
| Hồ sơ cửa hàng | `admin.seller.store` | `MASTER_DATA` | `Commerce` | `Modules/Commerce/resources/process-flows.php` | `commerce.store-profile` | `store-profile` |
| Sản phẩm | `admin.products.index` | `BUSINESS_PROCESS` | `Product` | `Modules/Product/resources/process-flows.php` | `product.catalog-to-sales` | `products` |
| Tồn kho | `admin.seller.inventory` | `READ_MODEL` | `Inventory` | `Modules/Inventory/resources/process-flows.php` | `inventory.stock-availability` | `seller-inventory` |
| Lịch sử biến động kho | `admin.seller.inventory.movements` | `REPORT` | `Inventory` | `Modules/Inventory/resources/process-flows.php` | `inventory.movement-ledger` | `inventory-movements` |
| Đơn hàng | `admin.seller.orders.index` | `BUSINESS_PROCESS` | `Commerce` | `Modules/Commerce/resources/process-flows.php` | `commerce.seller-orders` | `seller-orders` |
| Lịch sử đơn hàng đã bán | `admin.commerce.sales-history` | `READ_MODEL` | `Commerce` | `Modules/Commerce/resources/process-flows.php` | `commerce.commerce-sales-history` | `commerce-sales-history` |
| Commerce Payments | `admin.commerce.payments.index` | `BUSINESS_PROCESS` | `Commerce` | `Modules/Commerce/resources/process-flows.php` | `commerce.commerce-payments` | `commerce-payments` |
| Danh mục sản phẩm | `admin.product-categories.index` | `MASTER_DATA` | `Product` | `Modules/Product/resources/process-flows.php` | `product.catalog-to-sales` | `product-categories` |
| Quản trị Seller | `admin.commerce.sellers.index` | `CONTROL` | `Commerce` | `Modules/Commerce/resources/process-flows.php` | `commerce.seller-governance` | `seller-governance` |
| Kho nội dung | `articles.index` | `BUSINESS_PROCESS` | `Article` | `Modules/Article/resources/process-flows.php` | `article.content-authoring` | `articles` |
| Viết bài mới | `articles.create` | `BUSINESS_PROCESS` | `Article` | `Modules/Article/resources/process-flows.php` | `article.content-authoring` | `article-create` |
| Tổng quan POS | `admin.branch-commerce.dashboard` | `READ_MODEL` | `POS` | `Modules/POS/resources/process-flows.php` | `pos.customer-checkout-revenue` | `pos-dashboard` |
| Thu ngân · Check-in / Checkout | `admin.branch-commerce.checkins` | `BUSINESS_PROCESS` | `POS` | `Modules/POS/resources/process-flows.php` | `pos.customer-checkout-revenue` | `customer-checkins` |
| Tệp khách hàng | `admin.branch-commerce.customers` | `MASTER_DATA` | `POS` | `Modules/POS/resources/process-flows.php` | `pos.customer-checkout-revenue` | `branch-customers` |
| Dịch vụ công ty | `admin.branch-commerce.services` | `MASTER_DATA` | `POS` | `Modules/POS/resources/process-flows.php` | `pos.customer-checkout-revenue` | `company-services` |
| Kho POS / Tồn kho chi nhánh | `admin.branch-commerce.warehouse` | `READ_MODEL` | `POS` | `Modules/POS/resources/process-flows.php` | `pos.customer-checkout-revenue` | `branch-warehouse` |
| Phiếu thu · Doanh số POS | `admin.branch-commerce.sales` | `READ_MODEL` | `POS` | `Modules/POS/resources/process-flows.php` | `pos.customer-checkout-revenue` | `branch-sales` |
| Lịch sử phiếu thu | `admin.branch-commerce.receipts` | `READ_MODEL` | `POS` | `Modules/POS/resources/process-flows.php` | `pos.customer-checkout-revenue` | `pos-receipts` |
