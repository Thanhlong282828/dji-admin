# Phase 3E-5F — HR Data Governance

Ownership: Employee owns identity/employment/contracts/document metadata; Organization owns structure; Talent owns performance; Career owns progression; Payroll exclusively owns restricted monetary/tax/bank data; Analytics owns aggregates only. Classification is Public, Internal, Confidential and Restricted. Restricted fields create no new access path.

Batch 47 adds company-scoped quality rules/runs/issues, append-only governance audit, versioned consent history and versioned document governance history. Source entities are never modified by quality checks. Current implementation status is PARTIAL pending CQRS handlers, UI, security/concurrency matrix and full quality evidence.
## Phase 3E-5F Completion Hardening (2026-08-03)

- Additive schema completion is migration batch 48 (`2026_08_03_000036`); batches 1–47 were not modified.
- CQRS write paths: `RunDataQualityCheck`, `ResolveDataQualityIssue`, `ApproveDataCorrection`, `ArchiveEmployeeDocument`.
- Read paths: `HRDataQualityDashboard`, `DataOwnershipView`, `ConsentAuditView`, `DocumentGovernanceView`, backed by the company-scoped `HRGovernanceReadRepositoryInterface`.
- Quality rules: `employee_without_position` (blocking), `employee_without_manager` (warning), `invalid_contract` (blocking), `missing_document` (warning), `duplicate_identity` (blocking).
- Runs are deterministic and idempotent by company/idempotency key. Issues are unique per run/entity/rule. Source Employee, Contract, Consent and Document aggregates are read-only.
- Issue workflow: `open -> reviewed -> approved -> resolved`; maker/reviewer cannot approve. Resolution retains reason and SHA-256 evidence checksum.
- Audit events are append-only and contain actor, company, entity, action, timestamp, checksum; repository projections never expose `storage_key` or a filesystem path.
- UI: `/admin/hr/governance`, `/admin/hr/governance/issues`, `/admin/hr/governance/audit`.
- Browser fixture: `php artisan hr:governance-browser-fixture`; guarded to `local/testing` plus a database ending `_testing`; cleanup uses `--cleanup`.

### Completion evidence (2026-08-08)

- Lifecycle hardening: direct `open -> resolved` is rejected; creator approval is rejected; reviewer approval is allowed; creator may resolve an approved issue; resolved evidence is immutable on retry.
- Issue integrity: deterministic SHA-256 fingerprint/checksum over entity, rule, severity and message; database identity prevents duplicate issue per run/entity/rule; runtime checksum verification is available.
- Rule matrix is covered for all five rules, including code, severity, detection, checksum uniqueness and idempotent retry.
- Consent view exposes immutable version/purpose/decision metadata only. Document governance exposes lifecycle metadata only and never returns `storage_key`; archive records version, retention date, actor and checksum plus success/blocked access audit.
- Audit events `data_viewed`, `data_changed`, `permission_changed`, and `document_accessed` are append-only through an idempotent writer with company, actor and SHA-256 checksum.
- MySQL process races verified: quality create/create, retry/retry, resolve/resolve, document archive/archive. Result: one run, stable issues, one resolution audit, one document history version.
- Security verified: guest redirect, missing permission 403, foreign company 404; salary/tax/bank/storage path are absent from governance DTOs, Blade payloads and repository projections.
- Browser verified on `/admin/hr/governance`, `/issues`, `/audit`: desktop 1280x800 and mobile 390x844; all pages had `scrollWidth === clientWidth`; console error count was 0. Fixture was cleaned afterwards.
- PHPStan level 10: Employee module 0 errors; governance feature/concurrency tests 0 errors. Scoped and repository-wide Pint pass; Blade cache/clear, Vite and Docker Compose config pass.
- Production safety: source counts remained Employee/Assignment/Contract/Document/Consent = 9/9/9/9/9 and Payroll Run/Item = 0/0. An existing governance run/issue/audit row timestamped 2026-08-03 predates this completion turn and was preserved; no production fixture or source-domain mutation was performed.
- Final PHPUnit: **288 tests, 2,422 assertions, PASS** (MySQL `schema_admin_testing`). Final status: **Phase 3E-5F COMPLETED**.
