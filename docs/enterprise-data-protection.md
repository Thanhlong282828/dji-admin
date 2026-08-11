# Enterprise Data Protection

OpenAPI examples are synthetic and scanned for credential-like material. Contract inventory and dashboard expose route/schema aggregates only, not production IDs, payloads, bindings, PII, Payroll values, credentials or private paths.

Phase 3F-14 establishes a read-only, no-migration data-protection foundation. It does not own business data or change HR, Payroll, IAM, Integration, Notification, Workflow, or Audit lifecycle rules.

## Canonical contract

- Classifications: `public`, `internal`, `confidential`, `restricted`.
- Surfaces: UI, API, log, audit, export, integration, notification.
- Decisions: allow, mask, redact, deny.
- Both authorization and a permitted purpose are required for protected cross-boundary disclosure.
- Logs and audit snapshots always redact registered protected fields.
- Restricted monetary values are denied/redacted, never approximated.

The typed registry contains policy metadata only. It never reads or stores field values. Its deterministic SHA-256 checksum is included in release manifests as non-sensitive evidence.

## Boundaries

Enterprise Audit stores structured redaction markers and calculates integrity checksums over the canonical safe payload. Integration ERP/Government bridges use explicit payload allowlists. Notification payloads reject restricted field names. Private documents remain on private disks with authorized company-scoped downloads, checksums and safe metadata.

Retention periods, erasure and anonymization remain policy decisions requiring legal/domain-owner approval. Termination is not an erasure event; immutable Payroll, tax, accounting and audit histories are not deleted by this phase.

## Evidence

`php artisan operations:data-protection-audit --json` returns classification counts, coverage, findings, cryptographic inventory count and policy checksum without reading PII or secrets. `/admin/operations/data-protection` exposes the same read-only metadata behind `auth`, `verified`, and `operations.view`.

Final evidence: registry 23 fields (19 restricted, 4 confidential), checksum `15d28208f5f3fa97f35cd4915b833886ca06c3372f1dee0bf2526005b82b9a32`; focused 47 tests / 221 assertions; Full PHPUnit 431 tests / 4,500 assertions / one skipped / exit 0; repository PHPStan level 10 has 0 errors; Pint, Blade, Vite, Composer/npm audit, secret scan and Docker Compose pass. Chrome exact 1280×800 and 390×844 passed across data-protection/secrets/configuration/observability/releases with zero overflow, console warning/error and page error. Browser fixture cleanup returned zero. Migration inventory remains 61/61/61/61 and production counts are unchanged.

Phase 3F-14 status: **COMPLETED**.

Phase 3F-15 backup artifacts were mode 0600 and evidence contained only counts, durations and checksums. No production backup, protected value, private path or credential entered the DR certificate.
# Phase 3F-16 regression note

Threat/finding DTOs and the security dashboard contain aggregate evidence only. PII, Payroll values, credentials and private paths remain excluded.
# Accessible metadata boundary

ARIA labels and navigation search data are treated as presentation surfaces: only safe static labels/group keys are emitted; no user, tenant, Payroll, credential or private-path value is persisted client-side.
