# Phase 2.5C audit before implementation

Audit date: 2026-07-24.

## Database

- Connection: MySQL, database `schema_admin`.
- Existing records: 5 requisitions, 8 candidates, 8 applications, 4 onboarding cases.
- Before migration, interview, offer, normalized checklist and provisioning tables were missing.
- Existing candidates could be assigned unambiguously to one company through their applications.

## Functional classification

| Area | Initial state | Evidence |
|---|---|---|
| Requisition list/create/transition | FUNCTIONAL | CQRS handlers and protected routes existed |
| Requisition full CRUD/approval | PARTIAL | No detail/edit/archive/restore flow |
| Candidate list/create | FUNCTIONAL | CQRS routes existed |
| Candidate private resume/archive/merge | MISSING | No private download/archive/merge workflow |
| Application pipeline/stage move | FUNCTIONAL | CQRS and tenant-scoped requisition lookup existed |
| Interview workflow | MISSING | No normalized tables or command side |
| Offer workflow | MISSING | No normalized table or command side |
| Hire candidate | MISSING | No idempotent transaction |
| Onboarding read model | FUNCTIONAL | List/dashboard query existed |
| Onboarding command side | MISSING | No checklist/provisioning commands |
| Recruitment PHPStan level 10 | FAILED | 95 baseline errors |
| Onboarding PHPStan level 10 | FAILED | 37 baseline errors |

The audit also found that candidate creation did not persist a company context and
application creation did not scope candidate lookup to the authorized company.

