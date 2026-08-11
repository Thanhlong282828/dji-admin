# Phase 3F-12 Enterprise Runtime Configuration

The platform now has a typed, secret-free runtime configuration foundation with deterministic resolution, safe cache fallback, operational kill switches, drift evidence and release-manifest integration.

## Control plane

- UI: `/admin/operations/configuration` (`auth`, `verified`, `operations.view`).
- Audit: `php artisan operations:config-audit --json`.
- Registry: `config/runtime-configuration.php` and typed `FeatureFlag` values.
- Evidence: schema version, canonical non-secret snapshot checksum and drift status are embedded in new release manifests.

No migration or persistent mutation was added. Migration inventories remain 61. Development defaults keep external Integration HTTP and Notification email disabled; production values are configured independently without sharing secrets or demo data.

## Failure policy

Redis/cache lookup failure falls back to validated Laravel config and does not make core HTTP fail. High-risk side effects fail closed by default. The Integration adapter returns a controlled disabled result without external IO. Notification database delivery remains independent of email/broadcast switches.

Phase 3F-12 does not create a second business-policy system and does not alter HR, Payroll, Employee, Workflow, IAM, Audit or accounting semantics.

## Completion evidence

- Focused runtime configuration, release, Integration and Notification: 26 tests / 120 assertions, exit 0.
- Full PHPUnit: 419 tests, 418 passed, 4,426 assertions, one skipped, exit 0 (1,233.211 seconds).
- Repository and affected-test PHPStan level 10: 0 errors; Pint, Blade, Vite, Composer/npm audits, secret scan and Compose validation pass.
- Redis/cache exception: low-risk display capability falls back to environment default; high-risk Integration delivery fails closed. Core HTTP does not depend on flag-cache writes.
- Integration disabled emits `controlled_disabled` without external IO; enabled mock retries retain one deterministic external identity. Notification database delivery remains once-only while broadcast disabled intent stays retryable and sends once after restoration.
- `operations:config-audit --json`: schema version 1, drift 0, disabled side effects 2, evaluation failures 0, snapshot `09ba9d4f5432868f335c570d44ca48cbbe0bd986090bb42202ef4a36824f59db`.
- Exact Chrome 1280×800 and 390×844: configuration/observability/releases HTTP 200, overflow 0, console warning/error 0, page error 0; fixture cleanup 0.
- Migration matrix remains 61/61/61/61. Production business counts are unchanged.
