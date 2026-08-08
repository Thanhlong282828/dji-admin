# Phase 2.5C-3 — Hire Candidate integration

Status: **COMPLETED** (2026-07-26)

## Implemented

| Area | Status | Implementation |
|---|---|---|
| Hire eligibility | COMPLETED | Accepted offer, accepted timestamp, reserved vacancy, application `offer` stage, tenant chain, approved position/unit, employment type, vacancy and contract dates are validated server-side. |
| Command/Handler | COMPLETED | `HireCandidateCommand` → `HireCandidateHandler` → `HireCandidateService`. |
| Transaction boundary | COMPLETED | One outer MySQL transaction with three deadlock retries covers every write. Nested Employee services join the same connection transaction. |
| Lock order | COMPLETED | Offer → marker → application → candidate → requisition → job position → organization unit. |
| Optimistic concurrency | COMPLETED | Expected offer/application versions are mandatory; stale input returns a validation conflict without writes. |
| Idempotency | COMPLETED | Unique offer and UUID marker return the existing employee/onboarding result on retry. Database unique constraints prevent duplicate source links. |
| Employee | COMPLETED | Candidate name/email/phone/nationality map to a preboarding employee with immutable candidate/application/offer source links. Notes, scorecards, resumes and document paths are not copied. |
| Employee number | COMPLETED | Optional validated number or collision-resistant `SCH-` server-generated code; database uniqueness remains authoritative. No unsafe email merge. |
| User account | NOT_STARTED | Hire never creates credentials automatically. Account invitation remains the existing explicit Employee workflow. |
| Assignment | COMPLETED | One primary 100% assignment, approved unit/position, manager tenant checks and cycle/allocation checks. |
| Headcount | COMPLETED | The accepted offer reservation is consumed exactly once and requisition `filled_hires`/version increment once. Model A avoids accepted-offer/employee double counting. |
| Contract | COMPLETED | Draft unsigned contract; salary/currency only come from the locked accepted offer. `source_offer_id` is unique. |
| Application/Candidate | COMPLETED | Application moves to hired with stage history, actor/reason/version. Candidate receives auditable hired markers and is not deleted. |
| Onboarding | COMPLETED | Preboarding case, 10 checklist items and 8 provisioning items are created atomically with server-computed due dates and unique codes. |
| State machine | COMPLETED | `OnboardingCaseStateMachine` documents valid draft/ready/preboarding/in-progress/blocked/probation/completed/cancelled transitions. Hire does not auto-start or complete the case. |
| Events/listeners | COMPLETED | `CandidateHireCompleted` and assignment broadcast events implement after-commit semantics. Listener versions tenant caches and emits the existing safe recruitment realtime event. |
| Notifications/realtime | COMPLETED | Existing queued internal notification routing receives action `hired`; broadcast payload contains IDs/action/version only—no salary, PII, terms or storage paths. |
| Activity log | COMPLETED | Aggregate hire completion records actor, safe entity IDs, reason and hashed idempotency key; no salary/PII/documents/scorecards. |
| Cache | COMPLETED | Tenant-scoped recruitment/employee/onboarding/organization cache versions increment; no `Cache::flush()`. |
| Authorization | COMPLETED | Auth + verified + `candidate-offer.hire` middleware and tenant access checks. `CandidateOfferPolicy::hire` uses permission and tenant ownership. Runtime logic contains no role names. |
| UI | COMPLETED | Accepted offer shows a real Hire action; completed offers show Employee and Onboarding links. Preview/query is side-effect free, salary-aware, and the form clearly explains atomic writes. |
| Migration | COMPLETED | Batch 15 adds source/hired links, onboarding orchestration fields, provider state and durable transaction marker without modifying prior migrations. |

## External providers

- External calendar: **NOT_CONNECTED**
- Candidate delivery/portal: **NOT_CONNECTED**
- Identity provisioning provider: **NOT_CONNECTED**; internal tasks remain `pending` and provider state is explicit.
- Contract e-signature: **NOT_CONNECTED**; contract remains unsigned draft.

## Verification

- Hire tests: 7 passed, 52 assertions.
- Full PHPUnit: 78 passed, 344 assertions.
- PHPStan level 10 (Recruitment, Employee, Onboarding, Organization): 0 errors.
- Pint: passed.
- Vite production build: passed.
- MySQL migration batch 15: ran.
- Docker Compose configuration: valid.

## Remaining gaps

Provider adapters, candidate portal, credential delivery and e-signature are intentionally outside this phase. Production data was not seeded or modified; `hire_transaction_markers` remains empty until an authorized real hire is executed.
