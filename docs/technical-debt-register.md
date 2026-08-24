# Enterprise Technical Debt Register

## Phase 3F-24 closure

The previously recorded rank/demotion, account access, Attendance XLSX and duplicate-primary gaps are closed with typed domain boundaries and regression evidence. Remaining external Payroll payment/ERP/Government execution is intentionally disabled local functionality, not a Data Quality defect.

- DQ-EXT-001 (medium, open): the configured Cloudflare account/token returned Images API HTTP 403 / code 5403 during a non-business diagnostic. The adapter and compensation contract are verified with HTTP fakes, but live Cloudflare remains `NOT VERIFIED` until an account authorized for Cloudflare Images is supplied. Local operation explicitly uses private-local storage and does not silently fall back.

Phase 3F-21 records that representative FormRequest/response checks are intentionally targeted rather than a universal Laravel-rule-to-JSON-Schema translator. Expanding typed schemas for additional real APIs remains incremental debt, not permission to add fictional paths.

## Test engineering debt (Phase 3F-20)

- TEST-001 (medium, accepted): seven browser scripts retain context-specific launch/login/fixture duplication; consolidate only after preserving all viewport contracts.
- TEST-002 (low, accepted): line coverage is informational and is not automatically correlated with the critical-invariant registry.
- TEST-003 (low, accepted): legacy tests remain split between module-local and root trees; avoid mass moves without stable mapping.

| ID | Severity | Component | Description/evidence | Business impact | Recommended remediation | Target | Status |
|---|---|---|---|---|---|---|---|
| ARCH-001 | Medium | Employee/Leave/Payroll/Recruitment | Domain services accept UploadedFile or return StreamedResponse. | Transport coupling increases change/test cost. | Introduce application DTO/artifact response boundaries when each feature is next changed. | Incremental | Accepted |
| ARCH-002 | Medium | Module manifests | Runtime/event/schema dependencies are not always observable as PHP imports. | Automated “unused edge” deletion could break boot/event contracts. | Require ownership rationale for declared-unobserved edges. | Continuous | Accepted |
| ARCH-003 | Medium | Outboxes | Context-specific envelope/status/retry vocabulary remains. | Operations and lag tooling require adapters per context. | Standardize metadata vocabulary without replacing proven unique guards. | Future governance | Open |
| ARCH-004 | Low | Legacy catalog modules | Generated providers/controllers remain for disabled or legacy-supported modules. | Maintenance and route-discovery noise. | Remove only with replacement, reference, route and provider evidence. | Backlog | Accepted |
| ARCH-005 | Low | Permissions | Large catalog retains disabled-module metadata. | Entitlement review overhead. | Add owner/removal lifecycle; avoid in-place production renames. | Security governance | Open |

No critical or high debt was found. Historical Article CQRS and dynamic action routing remain documented legacy exceptions rather than Phase 3F-19 rewrites.

## Final project audit (2026-08-24)

- CACHE-001 (closed): `WorkforceCacheRegistry` now rejects legacy/deserialization results whose runtime type is `__PHP_Incomplete_Class`, evicts that key and reconstructs the read model. Dashboard/external caches remain arrays or JSON; the Company paginator cache already had equivalent recovery.
- SCHEMA-001 (low, open): `employees.identity_number_hash` has both the original non-unique index and a later unique index over the same column. The unique index enforces identity integrity and makes the earlier index redundant; remove it only in a separately reviewed additive/online migration window.
- MONEY-001 (medium, accepted): legacy Organization/Recruitment budget and Employment Contract columns still use decimal/float-facing boundaries. Canonical Payroll, Compensation, Planning and Reporting calculations use integer minor units. Migrate the legacy fields only with an effective-dated compatibility/backfill plan.
- DOCS-001 (closed): current development matrix documentation was corrected from the historical 61-migration count to 63 migrations / 230 tables and 39 local demo employees.
- NAV-001 (closed): API Documentation now has a permission-protected admin landing page, contextual help and links to the still-protected Swagger UI/OpenAPI JSON; all 78 primary destinations render the shared navigation contract.

Production infrastructure, TLS/domain, least-privilege MySQL/Redis, mail delivery, backup/PITR and provider credentials remain external go-live blockers. Cloudflare Images remains `BLOCKED` by the last live HTTP 403/code 5403 result; production Mail/ERP/Government/Bank and malware-provider verification are not claimed. MFA and the disabled/skeleton catalog remain accepted product/security backlog, not hidden code completion claims.
# Phase 3F-22 governed gaps

- Approved jurisdiction/owner retention sources and durations are not yet supplied.
- Persistent legal-hold lifecycle and maker-checker enforcement are deferred until mutation is authorized.
- Provider-specific orphan private-object detection needs an owning storage adapter.
- Irreversible anonymization rules are intentionally absent pending field-level authority.

# Phase 3F-23 functionality assurance gaps

- FUNC-001 (medium, open): ClamAV remains `NOT_CONNECTED`; private upload scanning is fail-closed and no file is falsely marked clean.
- FUNC-002 (low, accepted): live two-browser Reverb delivery is not certified on an external realtime topology; authorized channel/fallback tests remain the current evidence.
- FUNC-003 (low, accepted): disabled legacy/skeleton modules and historical import ideas remain intentionally unavailable; they must not gain UI actions until a real owned implementation exists.
Navigation/flow coverage audit (2026-08-21): no open primary-menu or routed-controller documentation debt. Twenty-one proven work centers were wired; 44 zero-parameter landing candidates were classified, with Integration/Audit internal tabs intentionally remaining child navigation. Disabled module skeletons are not treated as debt unless product scope explicitly enables them.
