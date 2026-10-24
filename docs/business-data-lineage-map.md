# Business Data Lineage Map

## Bounded-context ownership

| Use case | Canonical owner |
|---|---|
| Company and Branch lifecycle | `Modules/Company` |
| Seller/Store/Buyer/Order lifecycle | `Modules/Commerce` |
| Product Catalog, Category, Variant, SKU | `Modules/Product` |
| Inventory Location, Balance, Reservation, Movement | `Modules/Inventory` |
| Customer Visit, POS Checkout, Receipt, POS Sales | `Modules/POS` |
| Revenue reconciliation and Branch Finance | `Modules/Finance` |
| Executive aggregate | `Modules/Dashboard` |
| Article content and public Article API | `Modules/Article` |
| Account security, profile and phone identity | `Modules/Iam` |

## Nguyên tắc

Mỗi business fact có đúng một canonical owner. Dashboard, report và Branch Finance là read consumers; chúng không sở hữu số liệu và không được seed tổng số riêng. Dữ liệu mutable được tính lại qua application service. Snapshot đã finalized chỉ được điều chỉnh bằng workflow correction/reversal, không bị nguồn live ghi đè.

## Lineage matrix

| Domain | Canonical source | Owner | Upstream | Downstream | Mutable / snapshot | CQRS/runtime boundary | Duplicate source / quyết định |
|---|---|---|---|---|---|---|---|
| Company | `companies` / `Company` | Company | IAM actor | Branch, Employee, Seller, Store, Finance | mutable, soft lifecycle | Company commands/queries + repositories | Không có bảng view riêng |
| Branch | `branches` | Branch/Organization | Company | employee access, inventory location, POS, Finance | mutable; delete guarded | Branch service (legacy CQRS gap tracked) | Không copy tên branch sang Employee |
| Employee | `employees` | Employee | Company, User | Assignment, Attendance, Payroll, Portal | mutable; archive/history | Employee commands + repository/service | Dashboard chỉ aggregate |
| Employee assignment | `employee_assignments`, `employee_branch_assignments` | Employee/Organization | Employee, unit, position, branch | Attendance scope, Payroll, analytics | effective-dated history | assignment services/commands | `branch_user_access` là account access, không thay assignment |
| Attendance | attendance events/day aggregates | Attendance | Employee, schedule | Payroll Input, analytics | event history immutable; aggregate rebuildable | Attendance commands/queries | Không seed deduction cuối |
| Leave | `leave_requests` + policies | Leave | Employee | Attendance/Payroll Input | stateful history | Leave command/service | paid/unpaid distinction canonical |
| Overtime | overtime request/approval tables | Attendance/Payroll | Employee, attendance | Payroll Input | approved state required | OT command/service | Payroll không đọc OT giả |
| Compensation | salary components/assignments | Payroll | Employee assignment, position | Payroll Input, planning | effective-dated | Payroll commands/services | Không lưu salary ở Employee |
| Payroll Input | payroll input snapshots | Payroll | Attendance, Leave, OT, compensation, bonus, policies | Payroll Run | mutable/versioned before lock | `PayrollInputGenerationService` | finalized snapshots không rebuild |
| Payroll Run | `payroll_runs` | Payroll | input snapshot | items, payslips, accounting, finance | state machine / finalized immutable | Payroll commands + `PayrollRunService` | Không direct status update |
| Payroll Item | `payroll_items` and breakdown | Payroll | Payroll Run | payslip, PIT, insurance, liabilities | run snapshot | calculation service/read repo | Không seed final amounts per view |
| Payslip | payslip records/artifacts | Payroll | Payroll Item | Employee Portal, report | immutable per generated version | payslip commands | Amounts reconcile Payroll Item |
| PIT | payroll statutory breakdown/policy | Payroll Tax | Payroll Item, tax profile/policy | liability, accounting, filing | policy-version snapshot | tax services/queries | Dashboard aggregate only |
| Insurance | payroll statutory breakdown/policy | Payroll Statutory | Payroll Item, policy | liability, accounting | policy-version snapshot | statutory services | Không có insurance total riêng cho view |
| Accounting | journals + journal lines | Payroll Accounting | Payroll, revenue, expense, assets | reporting/export/reconciliation | posted history immutable | accounting services/commands | Debit/credit derives source reference |
| Branch Finance | revenue/OPEX/rent/assets + derived payroll | Finance | canonical ledgers and finalized payroll | dashboard/reporting | source mutable by lifecycle; snapshot read-only | `BranchFinancialPerformanceService` | Không có P&L total table/seed |
| Seller | `seller_profiles` | Commerce Governance | Company, User, IAM | Store, products, order scope | governed state machine | `ChangeSellerGovernanceStatusCommand` → service | Role/IAM remains permission owner |
| Store | `stores` | Commerce | Seller/Company | Product, orders | mutable/archive | store service (legacy CQRS gap tracked) | Không duplicate Company owner |
| Product | `products` | Product Catalog | Store, category, media | variants, inventory, public catalog, POS | mutable/archive | product service (legacy CQRS gap tracked) | No POS product clone |
| SKU / Variant | `product_variants` | Product Catalog | Product | inventory, order/POS lines | mutable until transacted; line snapshot historical | inventory/product service | SKU unique canonical |
| Media | `media_assets` | Media | Product/Employee/etc. | list/detail/warehouse | provider metadata mutable; reference owned | common `MediaService` adapter | Test fake uses same interface; no base64 DB |
| Inventory location | `inventory_locations` | Inventory | Store, optional Branch | balances/movements/POS | mutable | Inventory service | Branch warehouse reads same location |
| Inventory balance | `inventory_balances` | Inventory | movements/reservations | checkout availability/dashboard | mutable, locked atomically | `InventoryService` | Never direct form edit |
| Inventory movement | `inventory_movements` | Inventory | catalog set / sale / future reversal | balance reconciliation/audit | append-only | `InventoryService` | Required evidence for every on-hand change |
| Stock receipt / transfer | `inventory_stock_receipts`, `inventory_stock_transfers` | Inventory | company, branch, location, SKU | inventory movements/balances | governed document state | `MutateInventoryOperationCommand` → `InventoryOperationsService` | Không direct balance write |
| Customer | `commerce_customers` | CRM/POS | Company | Visit, receipt/history | mutable; delete blocked after history | `MutateBranchCommerceCommand` → `BranchPosService` | Buyer identity remains distinct unless linked |
| Visit | `customer_visits` | POS | Customer, Branch | product/service lines, receipt | open mutable; checked-out immutable | POS commands → service | No second session table |
| Service | `company_services` | Company Service Catalog | Company | Visit service lines | mutable; delete blocked after usage | Branch Commerce command/service | Branch references company catalog |
| Cart | `carts`, `cart_items` | Commerce | Buyer, Variant | Checkout/Order | mutable | `CartService` (legacy CQRS wrapper gap tracked) | Not a sale/revenue source |
| Order | `orders`, `order_items` | Commerce Order | Cart, Store/Seller, inventory reservation | Payment, Fulfillment, Sales History | state machine; line snapshots | Checkout/order services | No revenue until canonical recognition condition |
| Payment | `commerce_payment_records` | Commerce Payment | Order | revenue eligibility, order status | append/state history | payment service | Internal demo evidence not bank verification |
| Fulfillment | `commerce_fulfillments` | Commerce Fulfillment | paid Order | revenue recognition, sales history | state machine | fulfillment service | Cannot fabricate delivery |
| POS Receipt | `branch_pos_receipts` + visit line snapshots | POS | checked-in Visit, inventory, payment evidence | Revenue, customer history, sales | immutable after paid | checkout command/service transaction | Unique per visit |
| Recognized Revenue | `branch_management_revenues` | Finance Revenue | fulfilled commerce order or paid POS receipt; management ledger only when explicit | Branch Finance, Dashboard, reporting | append/reversal lifecycle | idempotent recognition services | Single aggregation source; no dashboard arrays |
| Sale reversal | `commerce_sale_reversals` | Commerce correction | completed Marketplace/POS sale + original revenue | inventory restock, net revenue, finance/dashboard | append-only full reversal | `ReverseCompletedSaleCommand` → `SaleReversalService` | Không rewrite Order/Receipt/revenue gốc |
| Executive Dashboard | query DTO/read services only | Dashboard | all canonical owners above | operator drilldown | read-only | QueryBus + bounded read services | Owns no business rows |

## Canonical synchronization paths

```text
Employee → Assignment → Attendance/Leave/OT → Payroll Input Snapshot
→ Payroll Run → Payroll Item → Payslip/PIT/Insurance → Accounting → Branch Finance

Company → Seller → Store → Product → SKU → Inventory
Customer → Visit → POS Checkout → Inventory Movement + Receipt
→ Recognized Revenue → Branch Finance → Executive Dashboard

Cart → Order → Payment → Fulfillment → Recognized Revenue
→ Unified Sales History → Branch Finance → Executive Dashboard
```

## Gaps xác nhận trong audit hiện tại

- Đã sửa: Branch Commerce Customer/Service/Visit/Checkout mutations trước đây nằm trực tiếp trong controller; hiện đi qua CommandBus và application handler.
- Đã sửa: Seller transition/state machine trước đây nằm trong controller; hiện đi qua CommandBus, governance service, transaction và activity audit.
- Đã sửa: Seller `banned` có controlled recovery `banned → suspended → active`; quan hệ leader/seller không tự nâng IAM role.
- Đã sửa: Dashboard Commerce/POS đọc trực tiếp Order, Receipt, Customer, Visit, Inventory và canonical recognized revenue; không có mảng số giả.
- Đã sửa: Dashboard Leave/Recruitment/Article/activity trước đây không đồng nhất Company scope và overview cache thiếu cross-module invalidation; hiện tất cả query theo Company và đọc tươi để không phục vụ số stale.
- Đã sửa: Article create/update đi qua `SaveArticleCommand` và `ArticleWriteService`; public Article API nằm trong Article Presentation. Profile update/delete và Account Security read đi qua IAM commands/queries. Presentation controllers không còn mutation DB trực tiếp; framework Auth/session orchestration vẫn ở HTTP boundary.
- Đã sửa gap bắt buộc: stock receipt, inter-branch transfer và full sale reversal có lifecycle/idempotency riêng; retry không nhân đôi movement hoặc revenue compensation.
- Chưa triển khai speculative: purchase order/supplier invoice và partial refund/return vì chưa có canonical upstream domain đủ bằng chứng.
