# Enterprise business functionality assurance

Phase 3F-23 verifies existing functionality rather than adding a business module. The evidence combines route/controller inventory, centralized permission-aware navigation, focused module/security/concurrency tests, local-main manual HTTP checks and isolated browser automation.

## End-to-end boundary

Enabled enterprise flows retain their actual architecture:

1. permission-filtered sidebar or owned list/dashboard;
2. named route with auth, verified, tenant and feature permission middleware;
3. controller and FormRequest/typed input;
4. CommandBus/QueryBus or the module's existing application/domain service;
5. owned repository and transaction;
6. domain event, outbox, notification or Audit boundary where implemented;
7. typed response/read model and Blade/API contract.

Simple and legacy surfaces are assessed using their real architecture; no CQRS layer was invented.

## Environment isolation

- Local manual runtime: writable `schema_admin` with `admin@local.test`.
- PHPUnit/integration/concurrency: `schema_admin_testing` only.
- Automated Chrome/Playwright: `schema_admin_browser_testing` only, followed by fixture cleanup.
- `schema_admin_development`: **REMOVED / NO LONGER EXISTS**; zero active dependency.
- VPS production: separate environment-defined database; no VPS action.

## Acceptance evidence

- Historical Phase 3F-23 local authenticated navigation: 51/51 primary links returned HTTP 200.
- Current Phase 3F-24 coverage audit: 73/73 primary links are centralized and permission-matched; exact browser evidence supersedes the historical 51-link baseline.
- Runtime proof: all 73 links return HTTP 200 at 1280×800 and 390×844 with menu-specific help and zero overflow/console/page errors. Leave, Overtime and Payroll Delivery legacy object caches discovered during the walk were replaced with validated JSON cache boundaries and regression tests.
- Operations: 14/14 children available and permission filtered.
- Swagger/API contracts: 11 paths and 24 operations; zero undocumented/orphan operations.
- IAM: 14 roles, 748 permissions, Super Admin 748/748, verified account and scoped company access.
- Demo data remains linked and synthetic; no external email, HTTP, ERP, government or bank delivery was performed.
- No new migration is required.
- Full PHPUnit after realtime fail-open hardening: 498 tests, 497 passed, 4,981 assertions, one skipped, exit 0 in 1,324.081 seconds.
- Repository PHPStan level 10, Pint, Blade, Vite, Composer/npm audit, Swagger/API contract, secret scan, CI and Docker Compose validation all pass.

## Functional completion rule

PASS means the existing route/backend/UI/permission contract executes and its supported mutations are covered by focused/full automated tests or an owned local diagnostic flow. N/A means that capability is not implemented by the module and no fake button or route was introduced. External provider availability is reported separately and cannot be converted into a false PASS.

## Local database consolidation evidence

The removed historical database had the same 61 migrations / 230 tables but a smaller synthetic dataset (1 user and 9 employees versus local-main 9 users and 36 employees). Local-main also contained the valid Attendance, Leave, Workflow, Notification, IAM, Integration and Audit demo evidence absent from the historical database. No unique required data existed, so no raw records or sensitive data were copied.

Before removal, `app:bootstrap-local-demo` ran twice with stable counts, the verified Super Admin retained 748/748 permissions and company access, Dashboard/Company returned HTTP 200, and an owned Company create/update/delete diagnostic completed through the application boundary and was cleaned. Removal targeted only local MySQL at `127.0.0.1:3306`; the three canonical databases remained at 61 migrations / 230 tables and VPS was not accessed.

## Realtime dependency hardening

Core write flows no longer return an ambiguous HTTP 500 after committing when local Reverb is unavailable. The shared broadcaster decorator catches only Laravel transport `BroadcastException`, logs safe failure evidence and preserves the original business response. Reverb-down diagnostics covered Company create/update/delete, Employee update/restore, Organization Unit update/restore, Attendance holiday create/delete and Payroll component creation; every valid request returned a redirect and owned fixtures were cleaned. Non-broadcast exceptions remain observable and propagate by regression test.
