# Recruitment workflow audit — Phase 2.5C-2

Audit date: 2026-07-24. Database: MySQL `schema_admin`.

| Workflow | Table/model | Command/handler | Policy/permission | Route/controller | UI | Events/tests | Status |
|---|---|---|---|---|---|---|---|
| Requisition list/create | Yes | Connected to CQRS | Permission only | Functional | Inline create/list | Basic feature test | FUNCTIONAL |
| Requisition approval lifecycle | Model/state service | One generic transition | Permissions seeded, no policy | Generic transition route | Minimal buttons | No workflow events | PARTIAL |
| Requisition detail/edit/duplicate/export | Yes | Missing | Partial permissions | Missing | Missing | Missing | MISSING |
| Headcount validation | Service exists | Not connected | Override permission exists | Missing | Missing | Missing | SKELETON |
| Candidate list/create | Yes | Connected to CQRS | Permission only | Functional | Inline create/list | Basic feature test | FUNCTIONAL |
| Candidate detail/edit/archive/restore | Model supports soft delete | Missing | Permissions seeded | Missing | Missing | Missing | MISSING |
| Candidate private resume | Metadata columns only | Missing | Permissions seeded | Missing | Missing | Missing | SKELETON |
| Duplicate detection/merge | Fingerprint creation only | Missing | Merge permission exists | Missing | Missing | Missing | PARTIAL |
| Application pipeline/create/move | Yes | Connected to CQRS | Permission only | Functional | Basic pipeline | Basic feature test | FUNCTIONAL |
| Application history/concurrency | Table/version exist | History not written | Partial | Missing detail route | Missing | Missing | SKELETON |
| Interview scheduling/evaluation | Tables/models/enums exist | Missing | Permissions missing | Missing | Missing | Missing | SKELETON |
| Candidate offer workflow | Table/model/state machine exist | Missing | Permissions missing | Missing | Missing | Missing | SKELETON |
| Realtime/notifications | Reverb configured globally | Missing recruitment events/listeners | Missing | Missing | Missing Echo listeners | Missing | MISSING |

Quality baseline before this implementation remains zero PHPStan level-10 errors
for Organization, Employee, Recruitment and Onboarding. Existing production-like
records are preserved.

