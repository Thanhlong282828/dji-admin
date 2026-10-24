# Enterprise data completeness audit

Generated read-only from `schema_admin` at 2026-09-10T20:31:52+00:00.

## Summary

- Menu destinations: 122
- Tables: 329
- Empty tables: 120
- Disconnected findings: 0

## Menu classification

| Group | Menu | Route | Owner | Rows | Data | Runtime | Action |
|---|---|---|---|---:|---|---|---|
| ERP · Tổng quan | Dashboard | `dashboard` | Dashboard | N/A | NOT_APPLICABLE | NOT_ENOUGH_EVIDENCE | AUDIT_OWNER_READ_MODEL |
| HRM · Tổ chức | Công ty | `admin.companies.index` | CrossProject | 4 | HEALTHY | PASS | NONE |
| HRM · Tổ chức | Chi nhánh | `admin.branches.index` | CrossProject | 8 | HEALTHY | PASS | NONE |
| HRM · Tổ chức | Trung tâm chi phí | `admin.cost-centers.index` | CrossProject | 5 | HEALTHY | PASS | NONE |
| HRM · Tổ chức | Đơn vị tổ chức | `admin.organization-units.index` | CrossProject | N/A | NOT_APPLICABLE | NOT_ENOUGH_EVIDENCE | AUDIT_OWNER_READ_MODEL |
| HRM · Tổ chức | Cây tổ chức | `admin.organization-units.tree` | CrossProject | N/A | NOT_APPLICABLE | NOT_ENOUGH_EVIDENCE | AUDIT_OWNER_READ_MODEL |
| HRM · Tổ chức | Vị trí công việc | `admin.job-positions.index` | CrossProject | N/A | NOT_APPLICABLE | NOT_ENOUGH_EVIDENCE | AUDIT_OWNER_READ_MODEL |
| HRM · Tổ chức | Báo cáo định biên | `admin.organization.headcount` | CrossProject | N/A | NOT_APPLICABLE | NOT_ENOUGH_EVIDENCE | AUDIT_OWNER_READ_MODEL |
| HRM · Nhân sự | Cổng thông tin nhân viên | `employee.portal` | CrossProject | N/A | NOT_APPLICABLE | NOT_ENOUGH_EVIDENCE | AUDIT_OWNER_READ_MODEL |
| HRM · Nhân sự | Nhân viên | `admin.employees.index` | Employee | 40 | HEALTHY | PASS | NONE |
| HRM · Nhân sự | Vòng đời nhân sự | `admin.employee-lifecycle.index` | CrossProject | N/A | NOT_APPLICABLE | NOT_ENOUGH_EVIDENCE | AUDIT_OWNER_READ_MODEL |
| HRM · Nhân sự | Hợp đồng & Offboarding | `admin.hr.operations.index` | Employee | N/A | NOT_APPLICABLE | NOT_ENOUGH_EVIDENCE | AUDIT_OWNER_READ_MODEL |
| HRM · Nhân sự | HR Data Governance | `admin.hr.governance.dashboard` | Employee | N/A | NOT_APPLICABLE | NOT_ENOUGH_EVIDENCE | AUDIT_OWNER_READ_MODEL |
| HRM · Chấm công và nghỉ phép | Chấm công | `admin.attendance.index` | CrossProject | 3092 | HEALTHY | PASS | NONE |
| HRM · Chấm công và nghỉ phép | Ca và lịch làm việc | `admin.workforce.management` | CrossProject | N/A | NOT_APPLICABLE | NOT_ENOUGH_EVIDENCE | AUDIT_OWNER_READ_MODEL |
| HRM · Chấm công và nghỉ phép | Nghỉ phép | `admin.leave.index` | CrossProject | 9 | HEALTHY | PASS | NONE |
| HRM · Chấm công và nghỉ phép | Chính sách nghỉ phép | `admin.leave-management.index` | CrossProject | N/A | NOT_APPLICABLE | NOT_ENOUGH_EVIDENCE | AUDIT_OWNER_READ_MODEL |
| HRM · Chấm công và nghỉ phép | Tăng ca | `admin.overtime.index` | CrossProject | 8 | HEALTHY | PASS | NONE |
| HRM · Tuyển dụng và phát triển | Tổng quan tuyển dụng | `admin.recruitment.dashboard` | Dashboard | N/A | NOT_APPLICABLE | NOT_ENOUGH_EVIDENCE | AUDIT_OWNER_READ_MODEL |
| HRM · Tuyển dụng và phát triển | Yêu cầu tuyển dụng | `admin.recruitment-requisitions.index` | CrossProject | N/A | NOT_APPLICABLE | NOT_ENOUGH_EVIDENCE | AUDIT_OWNER_READ_MODEL |
| HRM · Tuyển dụng và phát triển | Ứng viên | `admin.candidates.index` | CrossProject | N/A | NOT_APPLICABLE | NOT_ENOUGH_EVIDENCE | AUDIT_OWNER_READ_MODEL |
| HRM · Tuyển dụng và phát triển | Pipeline ứng tuyển | `admin.applications.pipeline` | CrossProject | N/A | NOT_APPLICABLE | NOT_ENOUGH_EVIDENCE | AUDIT_OWNER_READ_MODEL |
| HRM · Tuyển dụng và phát triển | Phỏng vấn | `admin.interviews.index` | CrossProject | N/A | NOT_APPLICABLE | NOT_ENOUGH_EVIDENCE | AUDIT_OWNER_READ_MODEL |
| HRM · Tuyển dụng và phát triển | Offer tuyển dụng | `admin.candidate-offers.index` | CrossProject | N/A | NOT_APPLICABLE | NOT_ENOUGH_EVIDENCE | AUDIT_OWNER_READ_MODEL |
| HRM · Tuyển dụng và phát triển | Onboarding | `admin.onboarding.dashboard` | Dashboard | N/A | NOT_APPLICABLE | NOT_ENOUGH_EVIDENCE | AUDIT_OWNER_READ_MODEL |
| HRM · Tuyển dụng và phát triển | Hiệu suất và tài năng | `admin.talent.performance.index` | CrossProject | N/A | NOT_APPLICABLE | NOT_ENOUGH_EVIDENCE | AUDIT_OWNER_READ_MODEL |
| HRM · Tuyển dụng và phát triển | Career và kế nhiệm | `admin.career.index` | CrossProject | N/A | NOT_APPLICABLE | NOT_ENOUGH_EVIDENCE | AUDIT_OWNER_READ_MODEL |
| Payroll | Cấu hình lương | `admin.payroll.delivery.index` | Payroll | N/A | NOT_APPLICABLE | NOT_ENOUGH_EVIDENCE | AUDIT_OWNER_READ_MODEL |
| Payroll | Kỳ lương | `admin.payroll.periods.index` | Payroll | 7 | HEALTHY | PASS | NONE |
| Payroll | Payroll Inputs | `admin.payroll.inputs.index` | Payroll | 5 | HEALTHY | PASS | NONE |
| Payroll | Payroll Runs | `admin.payroll.runs.index` | Payroll | 155 | HEALTHY | PASS | NONE |
| Payroll | Điều chỉnh lương | `admin.payroll.adjustments.index` | Payroll | N/A | NOT_APPLICABLE | NOT_ENOUGH_EVIDENCE | AUDIT_OWNER_READ_MODEL |
| Payroll | Thưởng chi nhánh | `admin.branch-bonuses.index` | CrossProject | N/A | NOT_APPLICABLE | NOT_ENOUGH_EVIDENCE | AUDIT_OWNER_READ_MODEL |
| Payroll | Phiếu lương | `admin.payroll.payslips.index` | Payroll | N/A | NOT_APPLICABLE | NOT_ENOUGH_EVIDENCE | AUDIT_OWNER_READ_MODEL |
| Payroll | Thanh toán | `admin.payroll.payment-batches.index` | Payroll | N/A | NOT_APPLICABLE | NOT_ENOUGH_EVIDENCE | AUDIT_OWNER_READ_MODEL |
| Thuế & Bảo hiểm bắt buộc | Tổng quan thuế & nghĩa vụ | `admin.finance.tax-dashboard` | Finance | N/A | NOT_APPLICABLE | NOT_ENOUGH_EVIDENCE | AUDIT_OWNER_READ_MODEL |
| Thuế & Bảo hiểm bắt buộc | Hồ sơ thuế TNCN | `admin.payroll.tax.employees.index` | Payroll | N/A | NOT_APPLICABLE | NOT_ENOUGH_EVIDENCE | AUDIT_OWNER_READ_MODEL |
| Thuế & Bảo hiểm bắt buộc | Chính sách thuế | `admin.payroll.tax.policies.index` | Payroll | N/A | NOT_APPLICABLE | NOT_ENOUGH_EVIDENCE | AUDIT_OWNER_READ_MODEL |
| Thuế & Bảo hiểm bắt buộc | Hồ sơ BHXH/BHYT/BHTN | `admin.payroll.insurance.employees.index` | Payroll | N/A | NOT_APPLICABLE | NOT_ENOUGH_EVIDENCE | AUDIT_OWNER_READ_MODEL |
| Thuế & Bảo hiểm bắt buộc | Chính sách bảo hiểm | `admin.payroll.insurance.policies.index` | Payroll | N/A | NOT_APPLICABLE | NOT_ENOUGH_EVIDENCE | AUDIT_OWNER_READ_MODEL |
| Thuế & Bảo hiểm bắt buộc | Đối soát thuế TNCN năm | `admin.payroll.annual-tax.index` | Payroll | N/A | NOT_APPLICABLE | NOT_ENOUGH_EVIDENCE | AUDIT_OWNER_READ_MODEL |
| Thuế & Bảo hiểm bắt buộc | Government Filing | `admin.payroll.government-filings.index` | Payroll | N/A | NOT_APPLICABLE | NOT_ENOUGH_EVIDENCE | AUDIT_OWNER_READ_MODEL |
| Tài chính | Tổng quan tài chính | `admin.finance.overview` | Finance | N/A | NOT_APPLICABLE | NOT_ENOUGH_EVIDENCE | AUDIT_OWNER_READ_MODEL |
| Tài chính | Chi phí theo Cost Center | `admin.finance.cost-center-costs` | Finance | N/A | NOT_APPLICABLE | NOT_ENOUGH_EVIDENCE | AUDIT_OWNER_READ_MODEL |
| Tài chính | Sales · AR · Collection | `admin.sales.index` | Commerce | 8 | HEALTHY | PASS | NONE |
| Tài chính | Mua hàng & Công nợ phải trả | `admin.procurement.page` | Procurement | 11 | HEALTHY | PASS | NONE |
| Tài chính | Chi phí · Tài sản · Ngân sách | `admin.finance.operations` | Finance | N/A | NOT_APPLICABLE | NOT_ENOUGH_EVIDENCE | AUDIT_OWNER_READ_MODEL |
| Tài chính | Treasury · Dòng tiền | `admin.finance.treasury` | Finance | 8 | HEALTHY | PASS | NONE |
| Tài chính | Accounting Period Close | `admin.finance.period-close` | Finance | N/A | NOT_APPLICABLE | NOT_ENOUGH_EVIDENCE | AUDIT_OWNER_READ_MODEL |
| Tài chính | Accounting Adjustments | `admin.finance.accounting-adjustments` | Finance | 31 | HEALTHY | PASS | NONE |
| Tài chính | Hiệu quả tài chính chi nhánh | `admin.finance.branch-performance` | Finance | N/A | NOT_APPLICABLE | NOT_ENOUGH_EVIDENCE | AUDIT_OWNER_READ_MODEL |
| Tài chính | Đối soát Payroll | `admin.finance.payroll-reconciliation` | Finance | N/A | NOT_APPLICABLE | NOT_ENOUGH_EVIDENCE | AUDIT_OWNER_READ_MODEL |
| Tài chính | Sổ cái & Báo cáo tài chính | `admin.finance.financial-statements` | Finance | N/A | NOT_APPLICABLE | NOT_ENOUGH_EVIDENCE | AUDIT_OWNER_READ_MODEL |
| Tài chính | Kế toán và bút toán | `admin.payroll.accounting.journals.index` | Payroll | 31 | HEALTHY | PASS | NONE |
| Tài chính | Cấu hình kế toán | `admin.payroll.accounting.policies.index` | Payroll | 31 | HEALTHY | PASS | NONE |
| Tài chính | ERP Export | `admin.payroll.erp.exports.index` | Payroll | N/A | NOT_APPLICABLE | NOT_ENOUGH_EVIDENCE | AUDIT_OWNER_READ_MODEL |
| Tài chính | Kết nối ERP | `admin.payroll.erp.connections.index` | Payroll | N/A | NOT_APPLICABLE | NOT_ENOUGH_EVIDENCE | AUDIT_OWNER_READ_MODEL |
| Chuỗi cung ứng | Tổng quan chuỗi cung ứng | `admin.supply-chain.index` | CrossProject | N/A | NOT_APPLICABLE | NOT_ENOUGH_EVIDENCE | AUDIT_OWNER_READ_MODEL |
| Projects | Tổng quan dự án | `admin.projects.overview` | Project | 1 | SPARSE_BUT_VALID | PASS | MONITOR |
| Projects | Dự án | `admin.projects.index` | Project | 1 | SPARSE_BUT_VALID | PASS | MONITOR |
| Tài sản & Bảo trì | Tổng quan bảo trì | `admin.maintenance.overview` | Maintenance | N/A | NOT_APPLICABLE | NOT_ENOUGH_EVIDENCE | AUDIT_OWNER_READ_MODEL |
| Tài sản & Bảo trì | Yêu cầu bảo trì | `admin.maintenance.requests` | Maintenance | 1 | SPARSE_BUT_VALID | PASS | MONITOR |
| Tài sản & Bảo trì | Lệnh công việc | `admin.maintenance.work-orders` | Maintenance | 3 | HEALTHY | PASS | NONE |
| Tài sản & Bảo trì | Bảo trì định kỳ | `admin.maintenance.plans` | Maintenance | 2 | SPARSE_BUT_VALID | PASS | MONITOR |
| Analytics & Planning | Executive Dashboard | `admin.executive.index` | CrossProject | N/A | NOT_APPLICABLE | NOT_ENOUGH_EVIDENCE | AUDIT_OWNER_READ_MODEL |
| Analytics & Planning | Phân tích điều hành | `admin.analytics.enterprise` | Dashboard | N/A | NOT_APPLICABLE | NOT_ENOUGH_EVIDENCE | AUDIT_OWNER_READ_MODEL |
| Analytics & Planning | Enterprise Analytics | `admin.analytics.workforce` | Dashboard | N/A | NOT_APPLICABLE | NOT_ENOUGH_EVIDENCE | AUDIT_OWNER_READ_MODEL |
| Analytics & Planning | Workforce Forecasting | `admin.analytics.forecasting` | Dashboard | N/A | NOT_APPLICABLE | NOT_ENOUGH_EVIDENCE | AUDIT_OWNER_READ_MODEL |
| Analytics & Planning | Executive Planning | `admin.planning.executive` | CrossProject | N/A | NOT_APPLICABLE | NOT_ENOUGH_EVIDENCE | AUDIT_OWNER_READ_MODEL |
| Analytics & Planning | Planning Execution | `admin.planning.execution` | CrossProject | N/A | NOT_APPLICABLE | NOT_ENOUGH_EVIDENCE | AUDIT_OWNER_READ_MODEL |
| Analytics & Planning | Enterprise Reports | `admin.reports.index` | CrossProject | N/A | NOT_APPLICABLE | NOT_ENOUGH_EVIDENCE | AUDIT_OWNER_READ_MODEL |
| Tự động hóa doanh nghiệp | Kho tài liệu | `admin.documents.index` | Document | 6 | HEALTHY | PASS | NONE |
| Tự động hóa doanh nghiệp | Approval Inbox | `admin.approvals.index` | CrossProject | N/A | NOT_APPLICABLE | NOT_ENOUGH_EVIDENCE | AUDIT_OWNER_READ_MODEL |
| Tự động hóa doanh nghiệp | Workflow Center | `admin.workflows.dashboard` | Dashboard | N/A | NOT_APPLICABLE | NOT_ENOUGH_EVIDENCE | AUDIT_OWNER_READ_MODEL |
| Tự động hóa doanh nghiệp | Workflow Definitions | `admin.workflows.definitions` | CrossProject | N/A | NOT_APPLICABLE | NOT_ENOUGH_EVIDENCE | AUDIT_OWNER_READ_MODEL |
| Tự động hóa doanh nghiệp | Workflow Tasks | `admin.workflows.tasks` | CrossProject | N/A | NOT_APPLICABLE | NOT_ENOUGH_EVIDENCE | AUDIT_OWNER_READ_MODEL |
| Tự động hóa doanh nghiệp | Notification Center | `admin.notifications.index` | CrossProject | 77 | HEALTHY | PASS | NONE |
| Tự động hóa doanh nghiệp | Notification Templates | `admin.notifications.templates.index` | CrossProject | N/A | NOT_APPLICABLE | NOT_ENOUGH_EVIDENCE | AUDIT_OWNER_READ_MODEL |
| Tự động hóa doanh nghiệp | Notification Deliveries | `admin.notifications.deliveries.index` | CrossProject | N/A | NOT_APPLICABLE | NOT_ENOUGH_EVIDENCE | AUDIT_OWNER_READ_MODEL |
| Tự động hóa doanh nghiệp | Integration Hub | `admin.integrations.dashboard` | Dashboard | N/A | NOT_APPLICABLE | NOT_ENOUGH_EVIDENCE | AUDIT_OWNER_READ_MODEL |
| IAM và Audit | IAM Governance | `admin.iam.dashboard` | Iam | N/A | NOT_APPLICABLE | NOT_ENOUGH_EVIDENCE | AUDIT_OWNER_READ_MODEL |
| IAM và Audit | Access Requests | `admin.iam.requests.index` | Iam | N/A | NOT_APPLICABLE | NOT_ENOUGH_EVIDENCE | AUDIT_OWNER_READ_MODEL |
| IAM và Audit | Roles & Permissions | `admin.iam.roles` | Iam | N/A | NOT_APPLICABLE | NOT_ENOUGH_EVIDENCE | AUDIT_OWNER_READ_MODEL |
| IAM và Audit | Privileged Access | `admin.iam.privileged` | Iam | N/A | NOT_APPLICABLE | NOT_ENOUGH_EVIDENCE | AUDIT_OWNER_READ_MODEL |
| IAM và Audit | Segregation of Duties | `admin.iam.sod` | Iam | N/A | NOT_APPLICABLE | NOT_ENOUGH_EVIDENCE | AUDIT_OWNER_READ_MODEL |
| IAM và Audit | Access Reviews | `admin.iam.reviews` | Iam | N/A | NOT_APPLICABLE | NOT_ENOUGH_EVIDENCE | AUDIT_OWNER_READ_MODEL |
| IAM và Audit | Audit Platform | `admin.audit.dashboard` | Dashboard | N/A | NOT_APPLICABLE | NOT_ENOUGH_EVIDENCE | AUDIT_OWNER_READ_MODEL |
| Vận hành nền tảng | Reliability | `admin.operations.reliability` | CrossProject | N/A | NOT_APPLICABLE | NOT_ENOUGH_EVIDENCE | AUDIT_OWNER_READ_MODEL |
| Vận hành nền tảng | Observability | `admin.operations.observability` | CrossProject | N/A | NOT_APPLICABLE | NOT_ENOUGH_EVIDENCE | AUDIT_OWNER_READ_MODEL |
| Vận hành nền tảng | Performance | `admin.operations.performance` | CrossProject | N/A | NOT_APPLICABLE | NOT_ENOUGH_EVIDENCE | AUDIT_OWNER_READ_MODEL |
| Vận hành nền tảng | Architecture | `admin.operations.architecture` | CrossProject | N/A | NOT_APPLICABLE | NOT_ENOUGH_EVIDENCE | AUDIT_OWNER_READ_MODEL |
| Vận hành nền tảng | Test Quality | `admin.operations.test-quality` | CrossProject | N/A | NOT_APPLICABLE | NOT_ENOUGH_EVIDENCE | AUDIT_OWNER_READ_MODEL |
| Vận hành nền tảng | API Contracts | `admin.operations.api-contracts` | CrossProject | N/A | NOT_APPLICABLE | NOT_ENOUGH_EVIDENCE | AUDIT_OWNER_READ_MODEL |
| Vận hành nền tảng | Releases | `admin.operations.releases` | CrossProject | N/A | NOT_APPLICABLE | NOT_ENOUGH_EVIDENCE | AUDIT_OWNER_READ_MODEL |
| Vận hành nền tảng | Runtime Configuration | `admin.operations.configuration` | CrossProject | N/A | NOT_APPLICABLE | NOT_ENOUGH_EVIDENCE | AUDIT_OWNER_READ_MODEL |
| Vận hành nền tảng | Secrets | `admin.operations.secrets` | CrossProject | N/A | NOT_APPLICABLE | NOT_ENOUGH_EVIDENCE | AUDIT_OWNER_READ_MODEL |
| Vận hành nền tảng | Data Protection | `admin.operations.data-protection` | CrossProject | N/A | NOT_APPLICABLE | NOT_ENOUGH_EVIDENCE | AUDIT_OWNER_READ_MODEL |
| Vận hành nền tảng | Data Lifecycle | `admin.operations.data-lifecycle` | CrossProject | N/A | NOT_APPLICABLE | NOT_ENOUGH_EVIDENCE | AUDIT_OWNER_READ_MODEL |
| Vận hành nền tảng | Data Quality | `admin.operations.data-quality` | CrossProject | N/A | NOT_APPLICABLE | NOT_ENOUGH_EVIDENCE | AUDIT_OWNER_READ_MODEL |
| Vận hành nền tảng | Business Continuity | `admin.operations.continuity` | CrossProject | N/A | NOT_APPLICABLE | NOT_ENOUGH_EVIDENCE | AUDIT_OWNER_READ_MODEL |
| Vận hành nền tảng | Security Assurance | `admin.operations.security-assurance` | CrossProject | N/A | NOT_APPLICABLE | NOT_ENOUGH_EVIDENCE | AUDIT_OWNER_READ_MODEL |
| Vận hành nền tảng | Go-Live Readiness | `admin.operations.go-live` | CrossProject | N/A | NOT_APPLICABLE | NOT_ENOUGH_EVIDENCE | AUDIT_OWNER_READ_MODEL |
| Vận hành nền tảng | API Documentation | `admin.operations.api-documentation` | CrossProject | N/A | NOT_APPLICABLE | NOT_ENOUGH_EVIDENCE | AUDIT_OWNER_READ_MODEL |
| CRM & Commerce | Tổng quan Seller | `admin.seller.dashboard` | Commerce | N/A | NOT_APPLICABLE | NOT_ENOUGH_EVIDENCE | AUDIT_OWNER_READ_MODEL |
| CRM & Commerce | Hồ sơ cửa hàng | `admin.seller.store` | Commerce | N/A | NOT_APPLICABLE | NOT_ENOUGH_EVIDENCE | AUDIT_OWNER_READ_MODEL |
| CRM & Commerce | Sản phẩm | `admin.products.index` | Product | 30 | HEALTHY | PASS | NONE |
| CRM & Commerce | Tồn kho | `admin.seller.inventory` | Commerce | 142 | HEALTHY | PASS | NONE |
| CRM & Commerce | Lịch sử biến động kho | `admin.seller.inventory.movements` | Commerce | 142 | HEALTHY | PASS | NONE |
| CRM & Commerce | Đơn hàng | `admin.seller.orders.index` | Commerce | 18 | HEALTHY | PASS | NONE |
| CRM & Commerce | Lịch sử đơn hàng đã bán | `admin.commerce.sales-history` | Commerce | N/A | NOT_APPLICABLE | NOT_ENOUGH_EVIDENCE | AUDIT_OWNER_READ_MODEL |
| CRM & Commerce | Commerce Payments | `admin.commerce.payments.index` | Commerce | N/A | NOT_APPLICABLE | NOT_ENOUGH_EVIDENCE | AUDIT_OWNER_READ_MODEL |
| CRM & Commerce | Danh mục sản phẩm | `admin.product-categories.index` | Product | 9 | HEALTHY | PASS | NONE |
| CRM & Commerce | Quản trị Seller | `admin.commerce.sellers.index` | Commerce | N/A | NOT_APPLICABLE | NOT_ENOUGH_EVIDENCE | AUDIT_OWNER_READ_MODEL |
| CRM & Commerce | Kho nội dung | `articles.index` | CrossProject | N/A | NOT_APPLICABLE | NOT_ENOUGH_EVIDENCE | AUDIT_OWNER_READ_MODEL |
| CRM & Commerce | Viết bài mới | `articles.create` | CrossProject | N/A | NOT_APPLICABLE | NOT_ENOUGH_EVIDENCE | AUDIT_OWNER_READ_MODEL |
| POS / Bán hàng tại quầy | Tổng quan POS | `admin.branch-commerce.dashboard` | Dashboard | N/A | NOT_APPLICABLE | NOT_ENOUGH_EVIDENCE | AUDIT_OWNER_READ_MODEL |
| POS / Bán hàng tại quầy | Thu ngân · Check-in / Checkout | `admin.branch-commerce.checkins` | CrossProject | N/A | NOT_APPLICABLE | NOT_ENOUGH_EVIDENCE | AUDIT_OWNER_READ_MODEL |
| POS / Bán hàng tại quầy | Tệp khách hàng | `admin.branch-commerce.customers` | CrossProject | 7 | HEALTHY | PASS | NONE |
| POS / Bán hàng tại quầy | Dịch vụ công ty | `admin.branch-commerce.services` | CrossProject | N/A | NOT_APPLICABLE | NOT_ENOUGH_EVIDENCE | AUDIT_OWNER_READ_MODEL |
| POS / Bán hàng tại quầy | Kho POS / Tồn kho chi nhánh | `admin.branch-commerce.warehouse` | CrossProject | 142 | HEALTHY | PASS | NONE |
| POS / Bán hàng tại quầy | Phiếu thu · Doanh số POS | `admin.branch-commerce.sales` | CrossProject | N/A | NOT_APPLICABLE | NOT_ENOUGH_EVIDENCE | AUDIT_OWNER_READ_MODEL |
| POS / Bán hàng tại quầy | Lịch sử phiếu thu | `admin.branch-commerce.receipts` | CrossProject | 5 | HEALTHY | PASS | NONE |

## Empty-table classification

| Table | Category | Population policy |
|---|---|---|
| `accounting_adjustment_requests` | WORKFLOW | INDIRECT_ONLY_VIA_OWNER_WORKFLOW |
| `accounting_period_close_checks` | BUSINESS_TRANSACTION | REQUIRES_OWNER_DOMAIN_REVIEW_BEFORE_DATA |
| `accounting_period_close_requests` | WORKFLOW | INDIRECT_ONLY_VIA_OWNER_WORKFLOW |
| `analytics_calculation_runs` | BUSINESS_TRANSACTION | REQUIRES_OWNER_DOMAIN_REVIEW_BEFORE_DATA |
| `analytics_outbox` | BUSINESS_TRANSACTION | REQUIRES_OWNER_DOMAIN_REVIEW_BEFORE_DATA |
| `application_interview_histories` | BUSINESS_TRANSACTION | REQUIRES_OWNER_DOMAIN_REVIEW_BEFORE_DATA |
| `application_interviewers` | BUSINESS_TRANSACTION | REQUIRES_OWNER_DOMAIN_REVIEW_BEFORE_DATA |
| `application_interviews` | BUSINESS_TRANSACTION | REQUIRES_OWNER_DOMAIN_REVIEW_BEFORE_DATA |
| `attendance_adjustment_histories` | BUSINESS_TRANSACTION | REQUIRES_OWNER_DOMAIN_REVIEW_BEFORE_DATA |
| `attendance_adjustment_requests` | WORKFLOW | INDIRECT_ONLY_VIA_OWNER_WORKFLOW |
| `attendance_approval_histories` | WORKFLOW | INDIRECT_ONLY_VIA_OWNER_WORKFLOW |
| `audit_access_logs` | AUDIT | REMAIN_EMPTY_UNTIL_RUNTIME_EVENT |
| `audit_archive_artifacts` | AUDIT | REMAIN_EMPTY_UNTIL_RUNTIME_EVENT |
| `audit_exports` | AUDIT | REMAIN_EMPTY_UNTIL_RUNTIME_EVENT |
| `audit_retention_policies` | AUDIT | REMAIN_EMPTY_UNTIL_RUNTIME_EVENT |
| `audit_source_checkpoints` | AUDIT | REMAIN_EMPTY_UNTIL_RUNTIME_EVENT |
| `cache` | CACHE_SYSTEM | REMAIN_EMPTY_UNTIL_RUNTIME_EVENT |
| `cache_locks` | CACHE_SYSTEM | REMAIN_EMPTY_UNTIL_RUNTIME_EVENT |
| `career_path_steps` | BUSINESS_TRANSACTION | REQUIRES_OWNER_DOMAIN_REVIEW_BEFORE_DATA |
| `career_paths` | BUSINESS_TRANSACTION | REQUIRES_OWNER_DOMAIN_REVIEW_BEFORE_DATA |
| `commerce_sale_reversals` | BUSINESS_TRANSACTION | REQUIRES_OWNER_DOMAIN_REVIEW_BEFORE_DATA |
| `company_holidays` | BUSINESS_TRANSACTION | REQUIRES_OWNER_DOMAIN_REVIEW_BEFORE_DATA |
| `competencies` | BUSINESS_TRANSACTION | REQUIRES_OWNER_DOMAIN_REVIEW_BEFORE_DATA |
| `competency_frameworks` | BUSINESS_TRANSACTION | REQUIRES_OWNER_DOMAIN_REVIEW_BEFORE_DATA |
| `development_plans` | BUSINESS_TRANSACTION | REQUIRES_OWNER_DOMAIN_REVIEW_BEFORE_DATA |
| `employee_career_plans` | BUSINESS_TRANSACTION | REQUIRES_OWNER_DOMAIN_REVIEW_BEFORE_DATA |
| `employee_competencies` | BUSINESS_TRANSACTION | REQUIRES_OWNER_DOMAIN_REVIEW_BEFORE_DATA |
| `employee_documents_history` | AUDIT | REMAIN_EMPTY_UNTIL_RUNTIME_EVENT |
| `employee_goals` | BUSINESS_TRANSACTION | REQUIRES_OWNER_DOMAIN_REVIEW_BEFORE_DATA |
| `employee_leave_policy_assignments` | BUSINESS_MASTER | REQUIRES_OWNER_DOMAIN_REVIEW_BEFORE_DATA |
| `employee_offboarding_cases` | BUSINESS_TRANSACTION | REQUIRES_OWNER_DOMAIN_REVIEW_BEFORE_DATA |
| `employee_offboarding_items` | BUSINESS_CHILD | INDIRECT_ONLY_VIA_OWNER_WORKFLOW |
| `employee_resignations` | BUSINESS_TRANSACTION | REQUIRES_OWNER_DOMAIN_REVIEW_BEFORE_DATA |
| `employee_shift_assignments` | BUSINESS_TRANSACTION | REQUIRES_OWNER_DOMAIN_REVIEW_BEFORE_DATA |
| `employee_user_invitations` | BUSINESS_TRANSACTION | REQUIRES_OWNER_DOMAIN_REVIEW_BEFORE_DATA |
| `employment_contract_renewals` | BUSINESS_TRANSACTION | REQUIRES_OWNER_DOMAIN_REVIEW_BEFORE_DATA |
| `erp_access_logs` | AUDIT | REMAIN_EMPTY_UNTIL_RUNTIME_EVENT |
| `goal_progress_history` | AUDIT | REMAIN_EMPTY_UNTIL_RUNTIME_EVENT |
| `government_tax_access_logs` | AUDIT | REMAIN_EMPTY_UNTIL_RUNTIME_EVENT |
| `government_tax_submission_attempts` | BUSINESS_TRANSACTION | REQUIRES_OWNER_DOMAIN_REVIEW_BEFORE_DATA |
| `government_tax_validation_issues` | BUSINESS_TRANSACTION | REQUIRES_OWNER_DOMAIN_REVIEW_BEFORE_DATA |
| `hr_consent_governance_history` | AUDIT | REMAIN_EMPTY_UNTIL_RUNTIME_EVENT |
| `hr_data_quality_rules` | BUSINESS_TRANSACTION | REQUIRES_OWNER_DOMAIN_REVIEW_BEFORE_DATA |
| `hr_document_governance_history` | AUDIT | REMAIN_EMPTY_UNTIL_RUNTIME_EVENT |
| `iam_access_assignments` | BUSINESS_TRANSACTION | REQUIRES_OWNER_DOMAIN_REVIEW_BEFORE_DATA |
| `iam_access_review_items` | BUSINESS_CHILD | INDIRECT_ONLY_VIA_OWNER_WORKFLOW |
| `iam_certifications` | BUSINESS_TRANSACTION | REQUIRES_OWNER_DOMAIN_REVIEW_BEFORE_DATA |
| `iam_governance_histories` | BUSINESS_TRANSACTION | REQUIRES_OWNER_DOMAIN_REVIEW_BEFORE_DATA |
| `iam_outbox` | BUSINESS_TRANSACTION | REQUIRES_OWNER_DOMAIN_REVIEW_BEFORE_DATA |
| `iam_privileged_roles` | BUSINESS_TRANSACTION | REQUIRES_OWNER_DOMAIN_REVIEW_BEFORE_DATA |
| `iam_role_entitlements` | BUSINESS_TRANSACTION | REQUIRES_OWNER_DOMAIN_REVIEW_BEFORE_DATA |
| `iam_sod_rules` | BUSINESS_TRANSACTION | REQUIRES_OWNER_DOMAIN_REVIEW_BEFORE_DATA |
| `iam_sod_violations` | BUSINESS_TRANSACTION | REQUIRES_OWNER_DOMAIN_REVIEW_BEFORE_DATA |
| `integration_connectors` | OPTIONAL_FEATURE | REMAIN_EMPTY_UNTIL_CONFIGURED |
| `integration_delivery_attempts` | OPTIONAL_FEATURE | REMAIN_EMPTY_UNTIL_CONFIGURED |
| `integration_external_references` | OPTIONAL_FEATURE | REMAIN_EMPTY_UNTIL_CONFIGURED |
| `integration_histories` | OPTIONAL_FEATURE | REMAIN_EMPTY_UNTIL_CONFIGURED |
| `integration_inbound_messages` | OPTIONAL_FEATURE | REMAIN_EMPTY_UNTIL_CONFIGURED |
| `integration_logs` | AUDIT | REMAIN_EMPTY_UNTIL_RUNTIME_EVENT |
| `integration_outbox` | OPTIONAL_FEATURE | REMAIN_EMPTY_UNTIL_CONFIGURED |
| `integration_webhook_receipts` | OPTIONAL_FEATURE | REMAIN_EMPTY_UNTIL_CONFIGURED |
| `interview_evaluations` | BUSINESS_TRANSACTION | REQUIRES_OWNER_DOMAIN_REVIEW_BEFORE_DATA |
| `inventory_stock_receipts` | BUSINESS_TRANSACTION | REQUIRES_OWNER_DOMAIN_REVIEW_BEFORE_DATA |
| `inventory_stock_transfers` | BUSINESS_TRANSACTION | REQUIRES_OWNER_DOMAIN_REVIEW_BEFORE_DATA |
| `job_batches` | BUSINESS_TRANSACTION | REQUIRES_OWNER_DOMAIN_REVIEW_BEFORE_DATA |
| `jobs` | CACHE_SYSTEM | REMAIN_EMPTY_UNTIL_RUNTIME_EVENT |
| `learning_courses` | BUSINESS_TRANSACTION | REQUIRES_OWNER_DOMAIN_REVIEW_BEFORE_DATA |
| `learning_enrollments` | BUSINESS_TRANSACTION | REQUIRES_OWNER_DOMAIN_REVIEW_BEFORE_DATA |
| `leave_attachments` | BUSINESS_TRANSACTION | REQUIRES_OWNER_DOMAIN_REVIEW_BEFORE_DATA |
| `leave_balance_transactions` | BUSINESS_TRANSACTION | REQUIRES_OWNER_DOMAIN_REVIEW_BEFORE_DATA |
| `leave_request_histories` | WORKFLOW | INDIRECT_ONLY_VIA_OWNER_WORKFLOW |
| `maintenance_part_requirements` | BUSINESS_TRANSACTION | REQUIRES_OWNER_DOMAIN_REVIEW_BEFORE_DATA |
| `model_has_permissions` | PIVOT | INDIRECT_ONLY_VIA_OWNER_WORKFLOW |
| `notification_delivery_attempts` | BUSINESS_TRANSACTION | REQUIRES_OWNER_DOMAIN_REVIEW_BEFORE_DATA |
| `notification_outbox` | BUSINESS_TRANSACTION | REQUIRES_OWNER_DOMAIN_REVIEW_BEFORE_DATA |
| `notification_preferences` | BUSINESS_TRANSACTION | REQUIRES_OWNER_DOMAIN_REVIEW_BEFORE_DATA |
| `notification_read_states` | BUSINESS_TRANSACTION | REQUIRES_OWNER_DOMAIN_REVIEW_BEFORE_DATA |
| `notification_template_versions` | BUSINESS_CHILD | INDIRECT_ONLY_VIA_OWNER_WORKFLOW |
| `notification_templates` | BUSINESS_MASTER | REQUIRES_OWNER_DOMAIN_REVIEW_BEFORE_DATA |
| `oauth_access_tokens` | BUSINESS_TRANSACTION | REQUIRES_OWNER_DOMAIN_REVIEW_BEFORE_DATA |
| `oauth_auth_codes` | BUSINESS_TRANSACTION | REQUIRES_OWNER_DOMAIN_REVIEW_BEFORE_DATA |
| `oauth_clients` | BUSINESS_TRANSACTION | REQUIRES_OWNER_DOMAIN_REVIEW_BEFORE_DATA |
| `oauth_device_codes` | BUSINESS_TRANSACTION | REQUIRES_OWNER_DOMAIN_REVIEW_BEFORE_DATA |
| `oauth_refresh_tokens` | BUSINESS_TRANSACTION | REQUIRES_OWNER_DOMAIN_REVIEW_BEFORE_DATA |
| `overtime_request_histories` | WORKFLOW | INDIRECT_ONLY_VIA_OWNER_WORKFLOW |
| `password_reset_tokens` | BUSINESS_TRANSACTION | REQUIRES_OWNER_DOMAIN_REVIEW_BEFORE_DATA |
| `payroll_accounting_validation_issues` | BUSINESS_TRANSACTION | REQUIRES_OWNER_DOMAIN_REVIEW_BEFORE_DATA |
| `payroll_adjustment_access_logs` | AUDIT | REMAIN_EMPTY_UNTIL_RUNTIME_EVENT |
| `payroll_adjustment_components` | BUSINESS_TRANSACTION | REQUIRES_OWNER_DOMAIN_REVIEW_BEFORE_DATA |
| `payroll_adjustment_downstream_intents` | BUSINESS_TRANSACTION | REQUIRES_OWNER_DOMAIN_REVIEW_BEFORE_DATA |
| `payroll_adjustment_payslips` | BUSINESS_TRANSACTION | REQUIRES_OWNER_DOMAIN_REVIEW_BEFORE_DATA |
| `payroll_adjustment_recoveries` | BUSINESS_TRANSACTION | REQUIRES_OWNER_DOMAIN_REVIEW_BEFORE_DATA |
| `payroll_adjustment_runs` | BUSINESS_TRANSACTION | REQUIRES_OWNER_DOMAIN_REVIEW_BEFORE_DATA |
| `payroll_adjustment_validation_issues` | BUSINESS_TRANSACTION | REQUIRES_OWNER_DOMAIN_REVIEW_BEFORE_DATA |
| `payroll_annual_tax_access_logs` | AUDIT | REMAIN_EMPTY_UNTIL_RUNTIME_EVENT |
| `payroll_annual_tax_components` | BUSINESS_TRANSACTION | REQUIRES_OWNER_DOMAIN_REVIEW_BEFORE_DATA |
| `payroll_annual_tax_documents` | BUSINESS_TRANSACTION | REQUIRES_OWNER_DOMAIN_REVIEW_BEFORE_DATA |
| `payroll_annual_tax_refund_obligations` | BUSINESS_TRANSACTION | REQUIRES_OWNER_DOMAIN_REVIEW_BEFORE_DATA |
| `payroll_annual_tax_settlement_journal_lines` | BUSINESS_CHILD | INDIRECT_ONLY_VIA_OWNER_WORKFLOW |
| `payroll_annual_tax_settlement_journals` | BUSINESS_TRANSACTION | REQUIRES_OWNER_DOMAIN_REVIEW_BEFORE_DATA |
| `payroll_annual_tax_validation_issues` | BUSINESS_TRANSACTION | REQUIRES_OWNER_DOMAIN_REVIEW_BEFORE_DATA |
| `payroll_input_validation_issues` | BUSINESS_TRANSACTION | REQUIRES_OWNER_DOMAIN_REVIEW_BEFORE_DATA |
| `payroll_payslip_access_logs` | AUDIT | REMAIN_EMPTY_UNTIL_RUNTIME_EVENT |
| `payroll_payslip_batches` | BUSINESS_TRANSACTION | REQUIRES_OWNER_DOMAIN_REVIEW_BEFORE_DATA |
| `payroll_validation_issues` | BUSINESS_TRANSACTION | REQUIRES_OWNER_DOMAIN_REVIEW_BEFORE_DATA |
| `performance_cycles` | BUSINESS_TRANSACTION | REQUIRES_OWNER_DOMAIN_REVIEW_BEFORE_DATA |
| `performance_goals` | BUSINESS_TRANSACTION | REQUIRES_OWNER_DOMAIN_REVIEW_BEFORE_DATA |
| `performance_review_history` | AUDIT | REMAIN_EMPTY_UNTIL_RUNTIME_EVENT |
| `performance_review_items` | BUSINESS_CHILD | INDIRECT_ONLY_VIA_OWNER_WORKFLOW |
| `performance_reviews` | BUSINESS_TRANSACTION | REQUIRES_OWNER_DOMAIN_REVIEW_BEFORE_DATA |
| `readiness_assessments` | BUSINESS_TRANSACTION | REQUIRES_OWNER_DOMAIN_REVIEW_BEFORE_DATA |
| `recruitment_notification_deliveries` | BUSINESS_TRANSACTION | REQUIRES_OWNER_DOMAIN_REVIEW_BEFORE_DATA |
| `shift_break_rules` | BUSINESS_TRANSACTION | REQUIRES_OWNER_DOMAIN_REVIEW_BEFORE_DATA |
| `succession_candidates` | BUSINESS_TRANSACTION | REQUIRES_OWNER_DOMAIN_REVIEW_BEFORE_DATA |
| `talent_management_outbox` | BUSINESS_TRANSACTION | REQUIRES_OWNER_DOMAIN_REVIEW_BEFORE_DATA |
| `user_external_identities` | OPTIONAL_FEATURE | REMAIN_EMPTY_UNTIL_CONFIGURED |
| `workflow_actions` | WORKFLOW | INDIRECT_ONLY_VIA_OWNER_WORKFLOW |
| `workflow_decisions` | WORKFLOW | INDIRECT_ONLY_VIA_OWNER_WORKFLOW |
| `workflow_step_transitions` | WORKFLOW | INDIRECT_ONLY_VIA_OWNER_WORKFLOW |
| `workforce_analytics_snapshots` | BUSINESS_TRANSACTION | REQUIRES_OWNER_DOMAIN_REVIEW_BEFORE_DATA |

## Business graph evidence

Counts are source-table evidence, not cloned dashboard totals.

### ORGANIZATION / IAM
- `companies`: 4
- `branches`: 8
- `organization_units`: 12
- `cost_centers`: 5
- `employees`: 40
- `employee_assignments`: 94
- `users`: 16
- `roles`: 16
- `permissions`: 858
- `company_user_access`: 19

### HR / PAYROLL
- `employees`: 40
- `attendance_records`: 3092
- `leave_requests`: 9
- `overtime_requests`: 8
- `payroll_input_snapshots`: 5
- `payroll_runs`: 5
- `payroll_items`: 150

### SALES / CRM
- `commerce_customers`: 7
- `orders`: 18
- `branch_pos_receipts`: 5
- `sales_orders`: 3
- `sales_fulfillments`: 3
- `customer_invoices`: 3
- `customer_collections`: 2
- `treasury_transactions`: 8

### PROCUREMENT
- `purchase_requests`: 4
- `purchase_orders`: 4
- `vendor_bills`: 3

### FINANCE
- `finance_expenses`: 5
- `finance_assets`: 2
- `payroll_accounting_journals`: 31
- `payroll_accounting_journal_lines`: 157
- `treasury_transactions`: 8

### PROJECT
- `projects`: 1
- `project_tasks`: 2
- `project_timesheets`: 1

### MAINTENANCE
- `maintenance_requests`: 1
- `maintenance_work_orders`: 3
- `preventive_maintenance_plans`: 2

## Disconnected-island findings

No deterministic findings from implemented checks.
