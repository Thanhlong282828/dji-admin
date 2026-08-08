# Phase 3F-6 — Production Readiness & Architecture Governance

This phase adds no business feature and no migration.

## Environment safety

- `schema_admin` is a protected database name and is allowed only with `APP_ENV=production`.
- Production rejects `APP_DEBUG=true`.
- Testing requires a database ending in `_testing`.
- `.env.example` and the base Compose stack use `schema_admin_development` for local development.
- Production Compose explicitly selects `schema_admin` with debug disabled.
- Browser fixture login routes are registered only in local/testing and retain the `*_testing` runtime guard.

## Module governance

Enabled enterprise module manifests declare direct/logical dependencies through `requires`. Architecture tests ensure dependencies are enabled, direct PHP imports are declared and the graph has no cycle.

## Route governance

All non-fixture admin routes require `auth` and `verified`. Routes without static permission middleware are held in an explicit allowlist because they perform action-specific Gate/Policy authorization in the controller. Fixture login routes are absent in production.

## Release governance

The release checkpoint must exclude `.env`, vendor, node modules, generated build output, private artifacts and runtime logs. Secret scanning is required before the initial commit.

## Static analysis governance

CI runs application PHPStan level 10 and an explicit enterprise-module gate for Workflow, Notification, IAM, Integration, Audit and architecture tests. This prevents the root `paths: [app]` setting from silently excluding the enterprise platform.

## Final evidence

- Protected database guard: local/testing → `schema_admin` exits non-zero; production debug exits non-zero.
- Production route inventory: 401 admin routes, zero fixture-login routes.
- Testing route inventory: 405 admin routes; all non-fixture routes pass auth/verified/permission-or-policy governance assertions.
- Architecture focused: 10 tests, 1,389 assertions; 9 passed and one production-only assertion skipped under testing.
- Auth/Profile/Architecture focused after verification hardening: 33 tests, 1,448 assertions; 32 passed and one production-only assertion skipped.
- ERP verification regression: 4 tests / 20 assertions PASS.
- Full PHPUnit final: 376 tests / 4,230 assertions PASS; one production-only assertion skipped under testing; exit code 0.
- PHPStan application and enterprise module/architecture scopes: 0 errors.
- Vite build and both development/production Docker Compose validation: PASS.
- All 60 migrations through Audit batch 55 remain `Ran`; no migration was created.
- Production read-only baseline remains employees 9, roles 14, permissions 745, company access 1; Payroll/IAM/Workflow/Notification/Integration/Audit business tables audited remain 0.

Phase 3F-6 status: **COMPLETED**. No Phase 3F-7 work was started.
