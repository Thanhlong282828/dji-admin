# Development data baseline

## Environment ownership

- `schema_admin`: writable Mac local-main/manual/demo database.
- `schema_admin_development`: **REMOVED / NO LONGER EXISTS**; local demo data now belongs only to writable `schema_admin`.
- `schema_admin_testing`: destructive automated PHPUnit/concurrency tests only.
- `schema_admin_browser_testing`: disposable browser fixtures; verified empty after cleanup.

The three active local databases contain the same 61 migrations and 230 tables. VPS production is a separate environment-defined database and was not accessed during local consolidation.

## Local bootstrap

```bash
php artisan app:bootstrap-local-demo
```

The canonical command is idempotent and only accepts `APP_ENV=local` with `schema_admin`; production and testing/browser databases are refused. The older development-named commands remain internal backward-compatible aliases for the same guarded local-main semantics.

Local login:

- Email: `admin@local.test`
- Password: `LocalAdmin123!`
- Role: `Super Admin`
- Primary company: `Schema Local Development` (`SCHEMA-LOCAL`)

The synthetic baseline currently covers Company, Organization, Employee, Recruitment and Onboarding through existing seeders: four companies, five cost centers, twelve organization units, nine positions, nine employees with assignments/contracts/credentials/consents, five requisitions, eight candidates/applications and four onboarding cases. No production personal, payroll, banking, tax, credential or private artifact data is copied.

Other bounded contexts remain intentionally empty where no safe idempotent development mutation boundary exists. Their fixture commands stay restricted to disposable testing/browser databases.

## Integrity evidence — 2026-08-09

- Repository-wide PHPStan `app Modules`, level 10: 0 errors.
- Guard/bootstrap focused tests: 10 tests, 12 assertions, passed.
- Development bootstrap rerun: no duplicate rows.
- `/login`: HTTP 200; `/health/live`: HTTP 200.
- Authenticated browser matrix: 18 representative major pages returned HTTP 200 at exact 1280×800 and 390×844; document overflow 0 and console warnings/errors 0 after supplying the authorized company context.
- Browser fixture database: zero users, companies, employees, workflow, IAM, integration and audit rows after cleanup.
- Production baseline remained users 1, employees 9, roles 14, company access 1, payroll runs 0; IAM/workflow/notification/integration/audit business tables remained 0.

The canonical index repair migration was applied historically without changing business rows. The current local trio contains 61 migrations / 230 tables each; the removed database is not part of the active matrix.

The final full suite passes with 397 tests, 4,291 assertions, one skipped and exit code 0. The complete concurrency directory passes with 60 tests and 486 assertions. Phase 3F-8 is complete.
