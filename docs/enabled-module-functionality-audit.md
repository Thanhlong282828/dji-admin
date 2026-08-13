# Enabled module functionality audit

Audit date: 2026-08-13. Evidence: `modules_statuses.json`, registered routes/controllers, navigation definitions, service bindings, migrations, tests and local synthetic data.

## Inventory

- Modules declared: 39
- Enabled: 16
- Disabled: 23
- Registered routes: 507
- Named GET routes: 261
- Centralized sidebar: 9 groups, 51 primary links
- Operations links: 14

| Module | Enabled | User entry | Write/read architecture | Sidebar | Demo | Status |
|---|---:|---|---|---:|---:|---|
| Core | yes | authentication, health, infrastructure | shared middleware/services | indirect | n/a | operational |
| Company | yes | `admin.companies.index` | controller/service/repository boundaries | yes | 4 | operational |
| Organization | yes | cost centers, units/tree, positions, headcount | CQRS/domain services where defined | yes | 5/12/9 | operational |
| Employee | yes | `admin.employees.index`, lifecycle | command/query bus and owned repositories | yes | 36 | operational |
| Recruitment | yes | `admin.recruitment.dashboard` | domain services and owned tables | yes | 5/8/8 | operational |
| Onboarding | yes | `admin.onboarding.dashboard` | module services/repositories | yes | 4 | operational |
| Attendance | yes | `admin.attendance.index` | module-owned service/query paths | yes | 90 | operational |
| Leave | yes | `admin.leave.index` | stateful request/policy boundaries | yes | 6 | operational |
| Payroll | yes | periods, inputs, runs, adjustments and governed outputs | CQRS, maker-checker and immutable calculation contracts | yes | intentionally bounded | operational |
| Performance | yes | performance/career/analytics/governance pages | read models/domain services | yes | derived/local where supported | operational |
| Workflow | yes | `admin.workflows.dashboard` | definition/execution services, outbox | yes | 1 instance | operational |
| Integration | yes | `admin.integrations.dashboard` | outbound/dead-letter services | yes | 3 messages | operational |
| Notification | yes | `admin.notifications.index` | message/delivery channel boundaries | yes | 3 | operational |
| Iam | yes | `admin.iam.dashboard` | Spatie adapter plus governed requests/reviews | yes | 9 actors | operational |
| Audit | yes | `admin.audit.dashboard` | append-only recorder/consumer | yes | 1 service-recorded event | operational |
| Dashboard | yes | `dashboard` | bounded read models/cache-safe arrays | yes | derived | operational |

Disabled modules are: Position, Contract, Shift, Insurance, Tax, Training, Benefit, Succession, LaborRelation, Offboarding, Report, Document, Security, Setting, Article, ArticleCategory, Product, ProductCategory, Media, Page, Menu, Seo and System. They remain disabled; no skeleton was enabled or presented as a completed enterprise module. Article routes are a documented legacy application exception and are not evidence that the disabled Article module is enabled.

## Sidebar classification

The sidebar contains stable list/dashboard entry points only. Detail, create/edit, API, fixture and internal routes stay reachable from their owning list/dashboard and are intentionally not duplicated. Every child is filtered server-side by its actual permission; a group is removed when it has no visible child. Route existence is covered by navigation tests.

The dead-control scan found only valid same-page anchors (skip navigation and employee detail tabs) and one submit-button disabling handler used to prevent duplicate payroll input submissions. No `javascript:void`, fake action or missing-route placeholder was found in active Blade views.

## Flow coverage

All 16 enabled modules are represented in the canonical flow sections of `project.txt`, `luong.txt` and `db.txt`. Disabled skeletons are classified only; no invented flow is documented.

