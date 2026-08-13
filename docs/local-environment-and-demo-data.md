# Local environment and synthetic demo data

## Canonical environment contract

| Runtime | Database | Purpose | Mutation policy |
|---|---|---|---|
| Mac local | `schema_admin` | Main local application and manual CRUD | Writable through application business boundaries |
| PHPUnit/integration | `schema_admin_testing` and `schema_admin_testing_*` | Automated tests only | Disposable/test-owned mutations |
| Browser automation | `schema_admin_browser_testing` | Playwright fixtures only | Disposable fixtures and cleanup |
| VPS production | Environment-defined (`VPS_PRODUCTION_DATABASE`) | Future production | Never inferred to be the Mac database |

`schema_admin_development` is **REMOVED / NO LONGER EXISTS**. Its schema and synthetic rows were audited against the richer `schema_admin` dataset before removal; no raw records were copied. It is not an active runtime, test, browser or configuration target.

The safety guard allows `local + schema_admin`, allows scoped testing databases only in `testing`, blocks `testing + schema_admin`, and blocks `production + schema_admin`. Production must name a separate environment-defined database and keep debug disabled.

## Local bootstrap

Run:

```bash
php artisan app:bootstrap-local-demo
```

The command requires `APP_ENV=local` and `DB_DATABASE=schema_admin`. It refuses testing, browser-testing, deprecated development and production environments. It is deterministic and idempotent; it performs no external email, broadcast, ERP, government or webhook delivery.

Primary local credential:

- Email: `admin@local.test`
- Password: `LocalAdmin123!` (LOCAL DEMO ONLY)
- Role: Super Admin
- Company: Công ty Cổ phần Công nghệ Minh Long (`SCHEMA-LOCAL`)

Additional role actors use `@local.test` identities and are synthetic. They exist only to exercise real IAM permission and company-access paths.

## Current local dataset evidence

After two consecutive bootstrap runs, stable counts were: 4 companies, 5 cost centers, 12 organization units, 9 job positions, 36 employees, 54 assignments, 36 contracts, 90 attendance records, 6 leave requests, 5 requisitions, 8 candidates, 8 applications, 4 onboarding cases, 1 published workflow/instance/task, 3 notification messages/deliveries, 1 IAM request/review, 3 integration outbound messages, 1 dead letter and 1 audit event.

All personal identities, monetary ranges and operational states are synthetic. The integration connection uses a local-null adapter with no base URL or credential. Notifications are database-only. Audit and workflow examples are created through their application/domain services.

## Safety boundaries

- PHPUnit and integration suites must never use `schema_admin`.
- Playwright fixtures must never use `schema_admin`.
- Lifecycle disposal/archive simulations remain testing-only even though the local main database is writable for normal CRUD.
- Demo bootstrap does not copy real people, password hashes, tax IDs, bank data, credentials, private files or production artifacts.
- Deploying to VPS transfers code, migrations and configuration; it does not copy local data unless a separate, explicitly authorized import is performed.
