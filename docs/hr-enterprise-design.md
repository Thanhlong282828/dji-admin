# Phase 3E-5A — HR Enterprise Layer domain design

Status: **COMPLETED (design only)**. Every class, command, event, permission and table name in this document is a proposal; none was implemented in Phase 3E-5A.

## 1. Bounded context: Employee Lifecycle

Employee Lifecycle coordinates, but does not absorb, the existing contexts:

1. Hire — consumes accepted Recruitment Offer and creates the Employee lifecycle identity.
2. Onboarding — references the existing Onboarding case/checklist/provisioning aggregate.
3. Employment — controls effective employment state, primary contract and assignment references.
4. Transfer/Promotion — closes and creates effective assignments; preserves position and manager history.
5. Leave — changes work eligibility while preserving employment and return-state evidence.
6. Offboarding — coordinates handover, access revocation, assets and final obligations.
7. Termination — records the legal/effective termination boundary.
8. Archive — retention-controlled terminal projection; never physically deletes history.

## 2. Lifecycle state model

The requested labels are retained, with one important semantic distinction: `transferred` is a transition marker/projection, not a permanent employment eligibility state. `leave` is added because it is part of the requested lifecycle domain and already exists in `EmploymentStatus`.

Canonical flow:

```text
candidate → offer_created → hired → onboarding → active
                                             ├→ probation → confirmed → active
                                             └→ confirmed → active

active|probation|confirmed → transferred → prior eligible state
active|probation|confirmed|transferred → leave → recorded return_state
hired|onboarding|active|probation|confirmed|transferred|leave → terminated → archived
```

`candidate` and `offer_created` are Recruitment-owned pre-Employee projections. The Employee aggregate starts at `hired`. `active` means employment/work eligibility has started; `probation` and `confirmed` express the employment phase. A company without probation may use `active → confirmed`.

### Transition matrix

Permission names below are design identifiers only.

| From | Action | To | Proposed actor permission | Mandatory evidence/audit |
|---|---|---|---|---|
| candidate | create offer | offer_created | `recruitment.offer.create` | candidate/application/offer snapshot, actor, correlation |
| offer_created | hire accepted offer | hired | `employee.lifecycle.hire` | accepted offer checksum, idempotency key, hire marker |
| hired | start onboarding | onboarding | `employee.lifecycle.onboard` | onboarding case/version, start date, owner |
| onboarding | commence employment | active | `employee.lifecycle.activate` | checklist readiness, effective date, contract/assignment refs |
| active | start probation | probation | `employee.lifecycle.probation` | policy/contract snapshot, probation dates |
| active | confirm without probation | confirmed | `employee.lifecycle.confirm` | approval and confirmation decision |
| probation | confirm | confirmed | `employee.lifecycle.confirm` | probation review, maker/checker, effective date |
| confirmed | mark operationally active | active | system/internal | prior transition correlation; no duplicate contract/assignment |
| active/probation/confirmed | transfer | transferred | `employee.lifecycle.transfer` | old/new assignment, organization/position, reason, approval |
| active/probation/confirmed | promote | transferred | `employee.lifecycle.promote` | old/new grade/position, reason, approval |
| transferred | complete effective change | prior eligible state | system/internal | transition correlation and effective assignment version |
| active/probation/confirmed/transferred | start leave | leave | `employee.lifecycle.leave` | leave reference, effective range, return state |
| leave | return | recorded return_state | `employee.lifecycle.return` | return decision/date and eligibility projection |
| hired/onboarding/active/probation/confirmed/transferred/leave | terminate | terminated | `employee.lifecycle.terminate` | reason category, effective date, approvals, offboarding case |
| terminated | archive | archived | `employee.lifecycle.archive` | retention policy, finalization checks, actor/time |

### Forbidden transitions

- Any transition crossing company boundaries.
- `candidate → active`, `offer_created → onboarding`, or any activation without an accepted/verified hire source.
- `probation → hired`, `confirmed → onboarding`, `terminated → active`, and every transition out of `archived`.
- Transfer/promotion to a position or manager outside the same company.
- Self-manager or manager-cycle creation.
- Overlapping primary assignment, contract, position or manager intervals.
- Termination before its legal effective boundary or while mandatory offboarding blockers remain.
- Archive before termination and retention/final-settlement readiness.
- Duplicate transition for the same employee, lifecycle version and idempotency key.

Each transition requires company scope, expected lifecycle version, idempotency key, actor, reason, effective time, source reference/checksum and immutable before/after projection. Sensitive document contents are referenced, never copied into events.

## 3. Employee aggregate

Aggregate root: `Employee`.

| Aggregate member | Responsibility | Ownership rule |
|---|---|---|
| Employee | stable company/person identity and current lifecycle projection | Employee Lifecycle |
| Employment Contract | legal relationship, dates, signed evidence and contract status | Employee; historical version immutable |
| Assignment | effective unit/position/allocation | Employee Lifecycle referencing Organization |
| Position | referenced definition, never copied as mutable master data | Organization |
| Manager Relationship | effective employee-to-manager relation | Employee Lifecycle |
| Lifecycle Event | immutable transition ledger and correlation | Employee Lifecycle |
| Document Evidence | versioned private evidence reference/checksum/classification | Employee |

Invariants:

- One Employee belongs to exactly one company; cross-company movement creates an explicit new employment relationship, not an unscoped update.
- At most one active primary assignment at an effective instant; secondary assignments may total no more than 100% allocation.
- Position, unit and manager must belong to the Employee company and be effective on the transition date.
- Contract/assignment/manager history rows are append-only. Corrections supersede with a new version; they never overwrite old evidence.
- Terminated/archived Employees cannot receive new Payroll or Attendance eligibility.
- No new lifecycle table stores salary components, gross/net pay, tax, insurance or accounting values.

## 4. Event-driven flow

Proposed domain events:

- `EmployeeHired`
- `EmployeeOnboarded`
- `EmployeePromoted`
- `EmployeeTransferred`
- `EmployeeTerminated`

Common safe envelope:

```text
event_id, event_version, occurred_at
company_id, employee_id, lifecycle_version
effective_at, actor_id, correlation_id, causation_id
source_type, source_id, source_checksum
safe transition metadata (no salary, identity document or storage path)
```

Future consumers:

| Consumer | Reaction |
|---|---|
| Payroll | refresh effective employment eligibility for the next input snapshot; never change finalized runs |
| Attendance | activate/end work profile and schedule eligibility effective-dated |
| Access Control | provision/revoke application access through idempotent workflow |
| Reporting | update Employee 360, lifecycle timeline and headcount projections |

Delivery design: transactional outbox after aggregate commit, idempotent consumer inbox keyed by `event_id + consumer`, retry/dead-letter visibility, no synchronous external side effect inside the Employee transaction.

## 5. CQRS proposal

### Commands

- `CreateEmployeeLifecycle`
- `StartOnboarding`
- `CompleteOnboarding`
- `TransferEmployee`
- `PromoteEmployee`
- `TerminateEmployee`

Write flow:

```text
Route (future)
→ typed FormRequest
→ Controller
→ shared CommandBus
→ immutable Command
→ Handler
→ EmployeeLifecycleService/StateMachine
→ write repositories with transaction + row lock
→ append lifecycle/history/evidence
→ outbox event
→ response DTO
```

Commands carry `companyId`, `employeeId/sourceId`, `expectedVersion`, `idempotencyKey`, `effectiveAt`, `actorId`, reason and typed transition-specific evidence references. They do not carry HTTP Request or Payroll values.

### Queries

- `Employee360View`
- `EmploymentHistory`
- `OrganizationPositionView`
- `LifecycleTimeline`

Read flow:

```text
Route (future)
→ Controller + company Gate
→ shared QueryBus
→ typed Query
→ Handler
→ read repository/projection
→ immutable ViewModel/API Resource
```

`Employee360View` composes bounded read projections; it does not join sensitive tables indiscriminately. Salary, identity, document and consent sections require independent field-level authorization.

## 6. Immutable audit requirements

Append-only evidence is required for:

- Employment state history.
- Contract history and signed-document checksum.
- Organization unit/position history.
- Manager relationship history.
- Lifecycle event history and transition approval.
- Document evidence history.

Rules:

- No update/delete of historical facts through application repositories.
- Correction appends a superseding row with `supersedes_id`, reason and new checksum.
- Current state is a projection derived transactionally from the latest accepted event/version.
- Actor, approver, timestamp, company, source, before/after checksum and correlation are mandatory.
- Activity Log is supplementary; it does not replace the domain history ledger.

## 7. Database proposal — documentation only

No migration was created. Proposed tables:

### `employee_lifecycle_events`

`id`, `uuid`, `company_id`, `employee_id`, `event_type`, `from_state`, `to_state`, `lifecycle_version`, `effective_at`, `source_type`, `source_id`, `source_checksum`, `idempotency_key`, `correlation_id`, `causation_id`, `actor_id`, `approved_by`, `reason_code`, `safe_metadata`, `event_checksum`, `created_at`.

Unique: `(company_id, employee_id, lifecycle_version)`, `(company_id, idempotency_key)`. No `updated_at` or soft delete.

### `employment_transitions`

`id`, `uuid`, `company_id`, `employee_id`, `lifecycle_event_id`, `transition_type`, `effective_from`, `effective_to`, `contract_id`, `assignment_id`, `onboarding_case_id`, `offboarding_reference`, `from_snapshot_checksum`, `to_snapshot_checksum`, `expected_version`, `actor_id`, `approved_by`, `created_at`.

Append-only transition detail; source FKs are nullable only when the transition type does not require them.

### `employee_positions_history`

`id`, `uuid`, `company_id`, `employee_id`, `lifecycle_event_id`, `organization_unit_id`, `job_position_id`, `assignment_id`, `assignment_type`, `allocation_basis_points`, `effective_from`, `effective_to`, `supersedes_id`, `snapshot`, `checksum`, `created_at`.

### `employee_manager_history`

`id`, `uuid`, `company_id`, `employee_id`, `manager_employee_id`, `lifecycle_event_id`, `relationship_type`, `effective_from`, `effective_to`, `supersedes_id`, `snapshot`, `checksum`, `created_at`.

Checks: employee != manager; same-company FKs/application invariant; effective range valid; no overlapping primary direct-manager interval.

### `employee_documents_history`

`id`, `uuid`, `company_id`, `employee_id`, `employee_document_id`, `lifecycle_event_id`, `document_type`, `document_version`, `storage_reference`, `mime_type`, `size_bytes`, `checksum_sha256`, `classification`, `scan_status`, `effective_at`, `supersedes_id`, `created_by`, `created_at`.

Private storage reference only; no file bytes or public URL. Historical document metadata is immutable.

Suggested indexes: every table starts with company scope; effective queries use `(company_id, employee_id, effective_from, effective_to)`; timeline uses `(company_id, employee_id, effective_at, id)`; event and document checksums are SHA-256. Monetary columns are intentionally absent.

## 8. Testing strategy

### State transitions

- Every allowed transition produces exactly one lifecycle version/event.
- Every forbidden transition returns controlled 409/422 without partial writes.
- Terminated/archive states are immutable.
- Transfer/promotion closes the prior effective relationship and appends the new one atomically.

### Authorization and tenant isolation

- Missing action permission → 403.
- No company access → 403.
- Foreign Employee/source/position/manager/document ID → 404.
- Field-level Employee 360 sections obey sensitive-data permissions.
- Maker/checker restrictions for confirmation, promotion and termination.

### History immutability

- Application repository exposes append/supersede only.
- Past contract/position/manager/document checksums remain unchanged after current-state changes.
- Payroll snapshot made before a lifecycle change remains byte/checksum stable.

### Concurrency

MySQL process tests: hire/hire, onboarding complete/terminate, transfer/transfer, promote/transfer, terminate/terminate and archive/archive. Expected: one lifecycle version winner, no overlapping primary assignment/manager interval, one outbox event and deterministic idempotent retry.

### Integration contracts

- Recruitment accepted offer → EmployeeHired exactly once.
- Onboarding completed → EmployeeOnboarded exactly once.
- Transfer changes future Payroll/Attendance eligibility only from its effective date.
- Termination excludes future inputs while finalized Payroll and locked Attendance evidence remains unchanged.

## 9. Deferred decisions for implementation phase

- Whether lifecycle projection is stored on `employees` as a new field or mapped onto existing `employment_status`.
- Formal offboarding bounded context and blocker contract.
- Cross-company transfer semantics and employee identity continuity.
- Outbox/inbox physical schema and retention.
- Field-level Employee 360 authorization model.
- Migration/backfill plan from existing generic `employee_histories` without rewriting historical rows.
# Phase 3E-5B implementation note

The design is implemented by the additive Employee Lifecycle event stream, immutable transition/position/manager/document histories and transactional outbox described in `docs/hr-employee-lifecycle-foundation.md`. Employee Core remains intact and Payroll continues to read persisted snapshots.

## Talent & Performance implementation note

The Talent bounded context is implemented separately under `Modules/Performance`. It consumes only company-scoped Employee identity and never duplicates Payroll compensation data. Typed cycle/review transitions, append-only goal/review histories, maker-checker rules and the transactional outbox are detailed in `docs/hr-talent-performance-foundation.md`.

## Career & Succession implementation note

Career Management is a separate subdomain under Performance: it owns future paths, talent pools, readiness and succession evidence while reading Employee/Organization/Performance truth. See `docs/hr-career-succession-foundation.md`.
## HR Data Governance boundary

Employee owns identity/employment/contract/document metadata; Organization owns hierarchy/position; Talent owns performance; Career owns progression/succession; Payroll exclusively owns salary/tax/monetary data; Analytics owns aggregate snapshots. Governance reads these boundaries for quality and compliance projections and never mutates source aggregates. Restricted payroll and storage-path fields are excluded from its read models.

Phase 3E-5F closes the HR Enterprise Layer with an append-only governance projection. Quality detection produces correction workflow records; it does not repair Employee aggregates. Consent and document consumers receive metadata whitelists, while any source correction remains owned by its bounded context.

## Shared Workflow boundary (Phase 3F-1)

The Enterprise Workflow Engine is a shared orchestration context, not an HR source-of-truth context. HR lifecycle, governance, talent, career and analytics retain their own state machines. Future adapters may start a version-pinned workflow and consume its outbox completion event; Workflow itself cannot update those aggregates.

Phase 3F-1 completion confirms this boundary with a whitelisted outbox envelope only (`instance_id`, `subject_type`, `subject_id`). No HR restricted fields or direct cross-context writes are present. Routing consumes the pinned context and actor resolution remains company scoped.

Phase 3F-2 adds Notification Center as a read/delivery boundary only. HR domains emit safe entity/action references; Notification neither reads nor copies salary, tax, bank, identity documents, private storage paths or medical/private notes.

Employee termination now projects to idempotent IAM revocation Commands; transfer projects to a user-scoped access-review campaign. Employee never mutates Spatie runtime authorization.

Process evidence: termination/termination produced one revoked assignment/history/outbox and removed one Spatie pivot; transfer/transfer preserved the role and produced one access-review campaign/item plus one source checkpoint.
