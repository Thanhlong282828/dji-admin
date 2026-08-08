# Recruitment & Onboarding audit after current implementation

Status: **PARTIAL / FUNCTIONAL CORE READ + PIPELINE**, not production-ready Phase 3.

Functional:

- Recruitment dashboard, requisition list/create and guarded state transitions.
- Candidate talent-pool create/list with normalized contacts and duplicate fingerprint.
- Application create, duplicate DB constraint, consent/open-requisition checks, pipeline and stage machine.
- Company-scoped repository queries and tenant authorization.
- Onboarding list and dashboard read model with structured checklist/provisioning metrics.
- Idempotent professional seed data and real sidebar routes.

Not implemented:

- Requisition full CRUD/detail/approval workflow adapter/headcount override.
- Candidate resume private storage, archive/merge/consent actions.
- Interview and offer tables/workflows.
- Candidate hire transaction and idempotency.
- Onboarding create/update/buddy/checklist/provisioning commands.
- Phase 3 domain events, listeners, jobs, notifications and realtime frontend refresh.
