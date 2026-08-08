# Phase 3E-5A — HR Enterprise Layer audit

Audit date: 2026-08-01. Status: **COMPLETED (audit only)**. No migration, PHP implementation, route, permission, seed or production write was performed.

## Audited bounded contexts

### Organization

Current authoritative structures are `companies`, `organization_units`, `job_positions`, `cost_centers` and effective-dated `employee_assignments`. Organization already protects tenant scope, hierarchy cycles, parent/child rules, headcount and position budget. A position belongs to one organization unit; an assignment connects an Employee to a position/unit and optionally a manager.

Gap: there is no dedicated immutable position or manager relationship history. Current history is inferred from effective-dated assignments and generic `employee_histories`. Organization must remain owner of structure/position definitions; Employee Lifecycle owns the fact that a person occupied a position.

### Employee

Existing aggregate data:

- Profile: `employees`, encrypted identity number, addresses, emergency contacts, dependants and employment status.
- Assignment: effective-dated unit, position, manager, assignment type and allocation percentage.
- Contract: contract type/status, dates, legal terms, signed evidence and termination metadata.
- Credential: typed credential, issue/expiry, private evidence and archive metadata.
- Document: private storage key, classification, checksum/scan/access policy, expiry/signing/archive metadata.
- Consent: purpose, decision, version, evidence, expiry and actor metadata.
- Generic history: `employee_histories` with transfer, promotion, salary change, discipline, award and status-change events.

Existing behavior includes assignment overlap/allocation checks, manager-cycle protection, effective transfer/promotion, contract state machine, document/credential expiry jobs and company-scoped policies.

Gaps:

- `EmploymentStatus` (`preboarding`, `probation`, `active`, `suspended`, `leave`, `terminated`, `alumni`) is not a complete lifecycle state machine and is updated independently from Recruitment/Onboarding.
- `employees.manager_id` and `employee_assignments.manager_id` can diverge and do not provide immutable temporal manager history.
- Transfer currently writes a generic status-change entry when creating the new assignment and a second transfer/promotion history entry; future orchestration needs one canonical lifecycle transition record.
- Generic JSON before/after history has no explicit lifecycle version, idempotency key, source aggregate, transition correlation or immutable database contract.
- Offboarding/termination orchestration, archival policy, Employee 360 and lifecycle timeline are absent.

### Recruitment and Onboarding

Recruitment owns requisition, candidate, application pipeline, interviews, evaluations and offers. `HireCandidateService` atomically creates an Employee, primary assignment, employment contract and Onboarding case, then records an idempotent hire marker and emits `CandidateHireCompleted`.

Onboarding is a separate existing bounded context (`Modules/Onboarding`), not part of Recruitment storage. It owns cases, checklist items and provisioning items with states `draft`, `ready`, `preboarding`, `in_progress`, `blocked`, `probation_review`, `completed`, `cancelled`.

Gaps:

- Cross-context hire is implemented as one database transaction with direct model/service calls. It is safe locally but tightly couples Recruitment, Employee and Onboarding.
- Completion of Onboarding does not yet drive a canonical Employee Lifecycle transition.
- No explicit immutable link exists from candidate/offer/hire transition to the complete Employee lifecycle timeline.
- No offboarding counterpart exists.

### Attendance

Attendance owns work profiles, calendars, shifts, effective shift assignments, raw events, daily records, adjustments, approvals and overtime. Records are company/employee scoped, versioned where operationally required and lock against Payroll periods. It represents workforce activity, not employment truth.

Gap: eligibility is currently resolved from current Employee/work-profile state. Future lifecycle consumers need an effective-dated employment projection so terminated/leave/transfer changes are applied consistently without Attendance owning Employee lifecycle.

### Payroll

Payroll owns salary component definitions and employee component assignments, input snapshots, runs/items, tax, insurance, payslip, payment, accounting, annual tax, ERP and filing evidence. Payroll Input currently captures Employee identity, contract, assignment, organization and position into persisted snapshots before calculation.

Boundary decision:

- Payroll remains owner of calculation configuration, salary component assignment and monetary results.
- Employment Contract may retain legally signed compensation terms as contract evidence, but new lifecycle tables must not copy operational salary components, Payroll results or balances.
- Payroll consumes an immutable effective-dated employment snapshot and never mutates Employee lifecycle.
- Finalized Payroll snapshots remain authoritative for historical calculations even if HR lifecycle changes later.

## Ownership map

| Fact | System of record | Consumers |
|---|---|---|
| Candidate/application/offer | Recruitment | Employee Lifecycle orchestration, reporting |
| Onboarding checklist/provisioning | Onboarding | Employee Lifecycle projection, access control |
| Employee identity/profile/consent/evidence | Employee | Payroll, Attendance, reporting |
| Organization unit/position definition | Organization | Employee, Recruitment, Payroll |
| Effective assignment/manager relationship | Employee Lifecycle | Organization headcount, Attendance, Payroll |
| Workforce activity | Attendance | Payroll Input, reporting |
| Compensation components/calculation | Payroll | Payslip, accounting, reporting |
| Lifecycle event/history | Employee Lifecycle | All downstream consumers |

## Phase 3E-5A conclusion

The project already contains strong operational slices but lacks one canonical lifecycle aggregate spanning hire through archive. The next implementation phase should add an Employee Lifecycle orchestration layer over existing aggregates, not replace them and not duplicate Payroll data.
