# Enterprise Data Lifecycle Governance

Phase 3F-22 establishes a read-only, cross-cutting lifecycle policy boundary. It evaluates retention, legal-hold precedence, archive integrity and disposal eligibility while each bounded context retains ownership of its records and mutations.

The Mac database `schema_admin` is the writable local-main application database, not the future VPS production database. Lifecycle disposal/archive experimentation nevertheless remains restricted to isolated testing databases. The future production database is named by VPS environment configuration and no VPS action was performed in this phase.

Final supplemental evidence: the guarded local bootstrap ran twice with stable counts; local login and representative company create/update/soft-delete passed through the HTTP/CQRS path and the diagnostic row was removed; 16 enabled modules and 23 disabled skeleton/legacy modules were classified; visible browser-fixture navigation had zero dead links/403/404/500 at both exact viewports; IAM navigation permissions were aligned with backend middleware. Full PHPUnit passed 497 tests, 4,970 assertions, one skipped, exit 0.

The canonical states are `ACTIVE`, `INACTIVE`, `ARCHIVED`, `RETENTION_ELIGIBLE`, `LEGAL_HOLD`, `DISPOSAL_ELIGIBLE` and `DISPOSED`. These are decision semantics, not columns added to every table. Decisions are `KEEP_ACTIVE`, `ARCHIVE_ELIGIBLE`, `HOLD`, `DISPOSAL_ELIGIBLE` and `BLOCKED`.

The registry contains 13 versioned policies covering Employee, Organization, Attendance, Leave, Recruitment, Onboarding, Payroll, Workflow, Notification, IAM, Integration, Audit and private artifacts. No legal duration is invented: every `retention_days` value remains unset until an authoritative owner/legal source is approved. An unset duration blocks disposal.

`operations:data-lifecycle-audit` and `/admin/operations/data-lifecycle` are read-only. `data_lifecycle.dry_run` defaults enabled; the fail-closed high-risk `data_lifecycle.disposal` flag defaults disabled. There is no archive, delete, purge, legal-hold release or anonymization action in this phase.

Legal hold is evaluated before age, archive and disposal. Archive is distinct from backup and requires integrity metadata. Existing audit archive artifacts remain owned by Audit; generic lifecycle governance does not move other module data into Audit storage.

Completion evidence: focused lifecycle/navigation tests passed 21 tests / 252 assertions; contract/architecture gate passed 29 tests / 1,514 assertions with one expected skip; full PHPUnit passed 492 tests / 4,963 assertions with one expected skip and exit 0. Repository and affected-test PHPStan level 10 reported 0 errors. Pint, Blade, Vite, Composer/npm audit, secret scan, Swagger, Docker config and `git diff --check` passed. Chrome browser checks passed six pages at both 1280×800 and 390×844 with 14/0 open/closed Operations children, no overflow and zero console/page/HTTP errors. Browser fixtures cleaned to 0/0/0.
