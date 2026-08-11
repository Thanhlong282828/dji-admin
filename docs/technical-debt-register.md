# Enterprise Technical Debt Register

| ID | Severity | Component | Description/evidence | Business impact | Recommended remediation | Target | Status |
|---|---|---|---|---|---|---|---|
| ARCH-001 | Medium | Employee/Leave/Payroll/Recruitment | Domain services accept UploadedFile or return StreamedResponse. | Transport coupling increases change/test cost. | Introduce application DTO/artifact response boundaries when each feature is next changed. | Incremental | Accepted |
| ARCH-002 | Medium | Module manifests | Runtime/event/schema dependencies are not always observable as PHP imports. | Automated “unused edge” deletion could break boot/event contracts. | Require ownership rationale for declared-unobserved edges. | Continuous | Accepted |
| ARCH-003 | Medium | Outboxes | Context-specific envelope/status/retry vocabulary remains. | Operations and lag tooling require adapters per context. | Standardize metadata vocabulary without replacing proven unique guards. | Future governance | Open |
| ARCH-004 | Low | Legacy catalog modules | Generated providers/controllers remain for disabled or legacy-supported modules. | Maintenance and route-discovery noise. | Remove only with replacement, reference, route and provider evidence. | Backlog | Accepted |
| ARCH-005 | Low | Permissions | Large catalog retains disabled-module metadata. | Entitlement review overhead. | Add owner/removal lifecycle; avoid in-place production renames. | Security governance | Open |

No critical or high debt was found. Historical Article CQRS and dynamic action routing remain documented legacy exceptions rather than Phase 3F-19 rewrites.
