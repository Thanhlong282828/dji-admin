# ERP Sidebar Architecture Standard

This document is the binding navigation standard for every user-facing LaravelDji capability. The runtime source of truth remains `App\Support\Navigation\AdminNavigation`; `ERP-NAVIGATION-MAP.md` is generated evidence, not an independent registry.

## 1. ERP abbreviation dictionary

| Abbreviation | Meaning | Navigation responsibility |
|---|---|---|
| ERP | Enterprise Resource Planning | Enterprise dashboard, company, branch, organization, cost center, position, headcount and projects |
| HRM | Human Resource Management | Employee lifecycle, recruitment, time, leave, payroll, performance and succession |
| CRM | Customer Relationship Management | Lead, opportunity, pipeline and customer relationship workspaces |
| SCM | Supply Chain Management | Cross-domain supply-chain coordination |
| WMS | Warehouse Management System | Warehouse, balance, movement, count, adjustment and valuation |
| OMS | Order Management System | Sales order, fulfillment, invoice, receivable, collection, credit and refund |
| POS | Point of Sale | Branch counter sales, visits, receipts and POS stock operations |
| PMS | Procurement Management System | Vendor, request, order, receipt, acceptance, return, supplier credit, bill and payable |
| FMS | Financial Management System | Treasury, accounting, GL, policies, statements, FX, tax, budget and close |
| AMS / EAM | Asset / Enterprise Asset Management | One shared group: **EAM · Tài sản & bảo trì** |
| MES | Manufacturing Execution System | Only verified manufacturing execution workspaces |
| MRP | Material Requirements Planning | Only verified material-planning workspaces; never a WMS alias |
| QMS | Quality Management System | Only verified quality-management workspaces |
| DMS | Document Management System | Enterprise documents, versions, approvals and governance |
| LMS | Learning Management System | Training and employee learning workspaces |
| TMS | Transportation Management System | Only verified transport workspaces |
| BI | Business Intelligence | Analytics, KPI, forecasts, plans and reports |
| CMS | Content Management System | Article, page, media, SEO and website content |
| ITSM | IT Service Management | Integration operations, reliability, observability and releases |
| PLM | Product Lifecycle Management | Only verified lifecycle governance, not ordinary catalog CRUD by default |
| IAM | Identity and Access Management | Users, roles, permissions, privileged access, SoD and access review |
| SYS | System Administration | Workflow, notification, runtime and system administration |

## 2. Canonical ownership

Every workspace has exactly one primary sidebar owner. Contextual source links may cross domains, but must not duplicate the landing workspace or its business engine. Module ownership, route ownership and navigation classification are related but distinct: classification never transfers write ownership between bounded contexts.

Projects belong to ERP. Procurement uses PMS. Learning uses LMS. Asset and maintenance share EAM. Financial source records retain their FMS landing pages. CMS menu/content administration must never be confused with this ERP registry.

## 3. Domain-to-capability classification

The canonical classification is recorded in `ERP-NAVIGATION-MAP.md`. Domains without a verified landing route remain `NOT_IMPLEMENTED` in documentation and are not rendered as empty groups. Actions embedded in detail workflows—such as spare-part issue/return—remain page actions with permission-aware source links.

## 4. Hierarchy

The maximum depth is three levels:

`ERP domain → functional area → workspace`

The current component may omit the functional-area level when a domain has a single compact area. Groups are collapsible. Empty groups, duplicate workspaces and action-only sidebar entries are forbidden.

## 5. Registration contract

Register a workspace only when all of these exist:

- named GET landing route;
- controller/read-side implementation and rendered view;
- canonical permission middleware or an explicitly tested dynamic authorization boundary;
- owning module and tenant contract;
- unique stable group key, item key and route;
- active-route patterns;
- required route parameters;
- Process Help/source-lineage reference where the workflow is material.

JavaScript never authorizes navigation. `AdminNavigation::for()` filters server-side before HTML is emitted.

## 6. Permission and tenant visibility

Never hard-code roles. Visibility uses `User::can()` against the same permission enforced by the landing route. Company context must preserve `company_id`; branch-specific destinations require canonical branch access. Lack of context hides the destination rather than producing a broken link. Super Admin behavior must still pass the same tenant-governance contracts defined by the application.

## 7. Breadcrumb and active state

Each landing and detail route must match exactly one primary item’s active patterns. The active group is expanded in server markup and takes precedence over saved presentation state. Breadcrumbs use the same canonical domain, functional area and workspace naming; deep links and existing URLs remain stable during menu reclassification.

## 8. Process Help and traceability

Important workflows register Process Help through `AdminHelpRegistry`/`ProcessFlowRegistry`. Help must state owner module, CQRS source, business meaning, accounting impact, next step and authorized source link. KPI cards, document references and table rows link to canonical source pages without inventing IDs or exposing mutation routes.

## 9. UI and accessibility

Preserve the dark-gold design system, responsive drawer, 44px controls, visible focus, keyboard operation, `aria-expanded`, `aria-current`, reduced motion and readable contrast. Collapsed descendants must be hidden and non-tabbable. Wide tables scroll locally; the page must not overflow horizontally. No second sidebar engine is allowed.

## 10. Navigation tests

Tests must prove:

1. every registered route exists and has a usable HTTP contract;
2. route middleware matches the registered permission;
3. group, item and landing route keys are unique;
4. every workspace has one primary owner;
5. permission and tenant filtering work;
6. active patterns, deep links and Process Help references remain valid;
7. generated navigation map matches the registry;
8. desktop/mobile collapse, keyboard and responsive behavior remain valid.

## 11. New feature integration checklist

- [ ] Identify canonical module owner.
- [ ] Identify ERP domain and functional area.
- [ ] Reuse route conventions and apply permission middleware.
- [ ] Apply company/branch isolation.
- [ ] Register one canonical sidebar entry.
- [ ] Update active state and breadcrumb.
- [ ] Update Process Help and source links.
- [ ] Regenerate `ERP-NAVIGATION-MAP.md`.
- [ ] Add navigation, authorization and tenant tests.
- [ ] Verify desktop/mobile behavior and absence of duplicates.

A user-facing feature is not UI-complete until every applicable item passes.

## 12. Forbidden anti-patterns

- Empty placeholder groups or invented routes/permissions.
- Duplicate primary links for one workspace.
- Role-name checks or client-side authorization.
- Business queries in Blade or navigation TypeScript.
- Action/create/edit/post routes as primary destinations.
- Broken tenant context, fake IDs or cross-tenant links.
- A second menu registry or CMS menu engine masquerading as ERP navigation.
- Renaming URLs or moving domain ownership solely for visual grouping.

## 13. Updating the navigation map

1. Audit route, permission, owner, tenant scope and help source.
2. Update `AdminNavigation` and relevant help/breadcrumb metadata.
3. Run `php artisan app:erp-architecture-map`.
4. Review `docs/architecture/ERP-NAVIGATION-MAP.md` and generated architecture diagrams.
5. Run navigation/unit/HTTP/browser regression and static gates.

## 14. Synchronization rule

`AdminNavigation`, named routes and module manifests are authoritative. The map generator must fail on a missing route or invalid module dependency. CI tests compare generated rows with the registry. Manual map edits that are not reproducible by the generator are invalid.

## Appendix A. 2026-10-12 old-to-new group mapping

All 143 pre-existing destinations remain present. This table records the complete group transition; individual destination positions are generated in `ERP-NAVIGATION-MAP.md`.

| Previous group | Canonical group |
|---|---|
| ERP · Tổng quan | ERP · Hoạch định nguồn lực |
| HRM · Tổ chức | ERP · Hoạch định nguồn lực |
| Projects | ERP · Hoạch định nguồn lực |
| Workforce · Nhân sự | HRM · Nhân sự cốt lõi |
| Attendance · Chấm công | HRM · Chấm công & thời gian |
| HRM · Tuyển dụng và phát triển | HRM · Tuyển dụng và phát triển |
| Payroll | HRM · Payroll |
| CRM · Lead & Opportunity | CRM · Lead & Opportunity |
| Chuỗi cung ứng | SCM · Chuỗi cung ứng |
| Inventory · Kho | WMS · Kho & tồn kho |
| Commerce · Sales (sales/order/catalog items) | OMS · Bán hàng & đơn hàng |
| Commerce · Sales (article items) | CMS · Nội dung website |
| POS / Bán hàng tại quầy | POS · Bán hàng tại quầy |
| Mua hàng | PMS · Mua hàng |
| Accounting · Tài chính | FMS · Tài chính & kế toán |
| Tax · Thuế & nghĩa vụ | FMS · Thuế & nghĩa vụ |
| Tài sản & Bảo trì | EAM · Tài sản & bảo trì |
| Analytics & Planning | BI · Phân tích & hoạch định |
| Tự động hóa doanh nghiệp (document/approval items) | DMS · Tài liệu doanh nghiệp |
| Tự động hóa doanh nghiệp (workflow/notification items) | SYS · Quy trình & thông báo |
| Administration · IAM & Audit | IAM · Danh tính & kiểm soát |
| System · Vận hành nền tảng | ITSM · Vận hành nền tảng |
