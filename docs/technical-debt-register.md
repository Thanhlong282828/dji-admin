# Enterprise Technical Debt Register

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
