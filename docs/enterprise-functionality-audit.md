# Enterprise functionality audit

Audit date: 2026-08-13. Baseline: `bc42c08` / `phase-3f-22-data-lifecycle-governance`.

## Canonical database routing

Completion hardening uses exactly three local databases: `schema_admin` for manual/demo functionality, `schema_admin_testing` for PHPUnit/integration, and `schema_admin_browser_testing` for Playwright. The former fourth local database was removed after confirming that `schema_admin` contained the richer, idempotently reproducible synthetic dataset. VPS production remains separate and environment-defined.

## Inventory evidence

- 39 declared modules: 16 enabled, 23 disabled/legacy/skeleton.
- 507 registered routes: 266 GET-capable routes and 243 write-capable routes.
- Enabled-module route ownership: Attendance 27/14 writes, Audit 11/3, Company 11/6, Dashboard 1/0, Employee 53/39, IAM 20/11, Integration 14/6, Leave 9/6, Notification 11/6, Onboarding 7/3, Organization 33/12, Payroll 152/77, Performance 14/7, Recruitment 45/26, Workflow 12/6. Cross-cutting App routes account for 87 routes.
- Central navigation: 9 groups, 51 primary permitted destinations; Operations remains 14 children.
- Exact browser walker visited all 51 Super Admin destinations at 1280×800 and 390×844 with HTTP 200, no overflow, console warning/error or page error.

## Enabled functionality matrix

| Module | Business purpose | GET/write routes | Primary functionality observed | Permission/sidebar | Demo/test evidence | Status |
|---|---|---:|---|---|---|---|
| Core | Auth, sessions, health, buses and shared boundaries | App 68/21 | login/logout, verified session, CSRF, health and infrastructure | middleware owned | full/security tests | PASS |
| Company | Tenant/company administration | 5/6 | list/show/create/update/status/soft-delete/restore | `company.*`, Organization group | local CRUD + browser | PASS |
| Organization | Cost centres, hierarchy, positions/headcount | 21/12 | CRUD, tree/move safeguards, position/headcount read models | scoped permissions | browser + module tests | PASS |
| Employee | Employee aggregate and lifecycle | 14/39 | list/filter, create/update, assignments, transfer/promotion, contracts, consent/documents/export | employee/HR permissions | demo + concurrency/security tests | PASS |
| Attendance | Attendance, workforce calendars/shifts and overtime | 13/14 | lists, workflow actions, workforce management and safe cache | attendance/shift permissions | cache/runtime fix + tests | PASS |
| Leave | Request, policy, balance and approvals | 3/6 | management/request transitions and attachment boundary | leave permissions | demo + workflow tests | PASS |
| Recruitment | Requisitions, candidates, applications/interviews/offers | 19/26 | dashboard, CRUD and state transitions | recruitment permissions | demo + feature/browser | PASS |
| Onboarding | Cases, checklist and provisioning | 4/3 | dashboard, cases and checklist transitions | onboarding permissions | demo + feature/browser | PASS |
| Payroll | Governed payroll and external-delivery evidence | 75/77 | periods, inputs, runs, adjustments, tax, payslips, payments, accounting, ERP/government guarded paths | granular payroll permissions | full/concurrency/export tests | PASS |
| Performance | Performance, career, analytics and HR governance | 7/7 | governed dashboards/read and supported mutations | talent/career/analytics permissions | browser + feature tests | PASS |
| Workflow | Definitions, instances, tasks and outbox | 6/6 | publish/start/decide/history/outbox | `workflow.*` | demo + concurrency | PASS |
| Notification | Message centre and channel deliveries | 5/6 | read/unread, delivery/retry/idempotency | `notification.*` | DB-only demo + concurrency | PASS |
| IAM | Requests, reviews, privileged access and SoD | 9/11 | maker-checker, provisioning adapter and Access 360 | exact IAM permissions | 748-permission local admin + tests | PASS |
| Integration | Connections, outbound/retry/dead-letter/checkpoints | 8/6 | local-null adapter, SSRF and kill-switch boundaries | `integration.*` | no-external demo + concurrency | PASS |
| Audit | Append-only governed evidence | 8/3 | search/filter/export/archive metadata, checksum/redaction | `audit.*` | recorder demo + concurrency | PASS |
| Dashboard | Company-scoped operational overview | 1/0 | bounded/cached read model | `dashboard.view` | browser/local data | PASS |

The 23 disabled modules remain disabled. Article routes are a deliberate legacy application exception; this audit does not present the disabled Article module as an enabled enterprise context.

## Runtime findings and corrections

The complete navigation walker exposed a real `__PHP_Incomplete_Class` failure on the workforce Shift page. `EloquentWorkforceReadRepository` had cached a PHP DTO object; a later runtime/autoload boundary could deserialize it without its class. The repository now caches deterministic JSON and reconstructs a validated typed `WorkforceManagementViewModel`. A regression test injects a legacy object cache value, verifies recovery, and confirms the replacement cache is a string.

The browser fixture now receives permissions derived from centralized navigation plus its Audit-specific actions. This makes the E2E walker permission-complete without weakening route middleware or granting permissions in production/local data.

## Dead controls and deferred external capabilities

The active Blade/TypeScript scan found no `javascript:void`, empty `href="#"`, TODO/FIXME action or disabled placeholder. Same-page employee detail anchors and the accessibility skip link are valid navigation; Payroll disables submit after submission to prevent duplicate intent.

ClamAV runtime and live two-browser Reverb delivery remain external-runtime gaps already recorded in the debt register. Their fail-closed/fallback contracts exist; this phase does not fake connectivity. Historical unfinished import ideas are not exposed as active buttons/routes.
