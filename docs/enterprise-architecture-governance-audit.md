# Enterprise Architecture Governance Audit

Audit date: 2026-08-11. Scope: source, 39 module manifests, PHP imports, layers, CQRS controllers, adapters, routes, permissions, outboxes, ownership, packages and historical architecture evidence. Production was read-only and no migration or business-state mutation was performed.

## Evidence summary

| Signal | Result |
|---|---:|
| Modules / manifests | 39 / 39 |
| Declared dependency edges before hardening | 53 |
| Direct PHP import edges before hardening | 38 |
| Undeclared direct edges | 0 |
| Dependency cycles | 0 |
| Routes | 503 |
| Named routes | 495 |
| Duplicate route names or method/URI pairs | 0 |

The Integration controller was the only governed enterprise controller directly querying `company_user_access`. It duplicated the Organization tenant boundary. Hardening replaces it with `TenantAccessService` and declares the real `Integration -> Organization` dependency, producing 54 declared and 39 observed direct edges.

Declared-but-not-directly-imported edges are not automatically unused: Audit source contracts, Notification/Workflow event integration and company/schema ownership are runtime/event contracts. They remain declared and documented. The graph is acyclic.

## Layer and coupling findings

The strictly governed Workflow, Notification, IAM, Integration and Audit Domain folders do not import Presentation/HTTP/View types. Their controllers use CQRS buses after the Integration tenant correction. Spatie production mutations remain isolated in `SpatieAccessProvisioningAdapter`; outbound HTTP remains behind Integration adapter and URL policy; secret and redaction boundaries remain unchanged.

Legacy/mature Employee, Leave, Recruitment and Payroll services still accept `UploadedFile` or return `StreamedResponse`, and several directly use Storage. These are transport/infrastructure coupling debts, not evidence that their business semantics are wrong. They are registered for incremental refactoring rather than mass-moved in this phase.

Core, Company, Organization and Employee are coupling hotspots because they own stable shared concepts. Payroll does not own Employee lifecycle; IAM consumes lifecycle intent and never mutates Employee; Workflow orchestrates without owning Payroll calculations.

## CQRS, routes and permissions

Enterprise controllers use Command/Query buses or read-only operations services. Legacy Article API/web controllers remain an explicit legacy-supported exception. Commands own mutations; queries/read snapshots are read-only. Request validation owns shape while maker-checker, transition and allocation invariants remain in services/handlers.

Routes have no duplicate names or method/URI pairs. Test-only fixture routes retain environment/database guards. Permission middleware is coarse capability control; policies/Gates remain resource and ownership control. Existing production permission names are not renamed. Swagger remains protected by `api.documentation.view`.

## Package/shared-kernel assessment

The application remains a modular monolith. `Modules/Core` contains buses/shared contracts; `app/Support` contains cross-cutting operations, reliability, security, performance and architecture read models. Business-specific helpers must remain in their owning module. L5-Swagger and Playwright Core are active; browser automation is dev-only. No package or module is removed based on grep-only evidence.

Potential future extraction candidates are Integration, Notification and Audit because their event contracts and delivery state are explicit. Shared database transactions and cross-context read contracts mean no production microservice extraction is claimed.
