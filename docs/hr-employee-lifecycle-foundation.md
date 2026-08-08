# Phase 3E-5B — Employee Lifecycle Platform

## Status and scope

The Employee bounded context now owns an append-only lifecycle stream without replacing Employee Core, Recruitment, Onboarding, Attendance, or Payroll. Payroll calculation was not changed and continues to consume persisted employment/payroll input snapshots.

## Aggregate and persistence

- `employee_lifecycle_events`: authoritative ordered lifecycle event stream, company-scoped idempotency and SHA-256 payload checksum.
- `employment_transitions`: immutable business transition ledger.
- `employee_positions_history`: append-only position/unit evidence.
- `employee_manager_history`: append-only reporting-line evidence.
- `employee_documents_history`: immutable document versions.
- `employee_lifecycle_outbox`: transactional outbox written in the same database transaction; no external side effect is executed in this phase.

Migration `2026_08_01_000029_create_employee_lifecycle_foundation` is additive and ran as batch 41. Batch 1–40 was not edited.

## Lifecycle

The typed state machine supports candidate → offer_created → hired → onboarding → active → probation → confirmed, confirmed → leave, leave → active, active/probation/confirmed → terminated, and terminated → archived. Transfer and promotion are same-state business markers available only from active, probation, or confirmed.

The Phase 3E-5B operational UI exposes the implemented command path: initialize hired lifecycle, start onboarding, complete onboarding, and terminate. Existing assignment workflow remains the source for controlled transfer/promotion of organization, position, and manager.

## CQRS and security

Write: route → `EmployeeLifecycleActionRequest` → `EmployeeLifecycleController` → command bus → typed command/handler → `EmployeeLifecycleService` → locked Employee/event/history/outbox → domain event.

Read: route → controller → query bus → list/Employee360 query handler → `EmployeeLifecycleReadRepositoryInterface` → readonly `EmployeeLifecycleViewModel` → Blade.

Permissions: `hr.lifecycle.view`, `create`, `onboarding`, `transfer`, `promote`, `terminate`. Middleware checks permission; policy checks permission plus company access; repository/service ID lookup is company-scoped so an Employee ID from another accessible context resolves as 404.

Employee360 deliberately excludes salary, tax, insurance identifiers and bank account data.

## Verification evidence

- Focused HTTP/unit: 10 tests, 34 assertions, passed on `schema_admin_testing`.
- MySQL process race: create/create, onboarding/onboarding, transfer/transfer and terminate/terminate; 1 test, 11 assertions, passed. One transition/event/history winner was persisted.
- PHPStan `Modules/Employee/app --level=10`: 0 errors.
- Browser: desktop 1280×800 and mobile 390×844 verified for list/detail; both had `scrollWidth == clientWidth`, navigation/table/detail sections rendered, console errors 0.
- Production UI verification was read-only; no lifecycle action was submitted.
- Full PHPUnit regression after the final concurrency change: 261 tests, 2,245 assertions, passed.
- Pint, Blade compile/cache/clear, Vite production build and Docker Compose config: passed.

## Known boundary

Recruitment/Onboarding automatic event consumers remain future integration work. No outbox publisher or external consumer is enabled in this foundation phase.
