# Phase 3E-5C — Talent & Performance Management

## Scope and boundaries

The `Performance` bounded context reads company-scoped Employee identity snapshots and does not read or expose salary, tax, insurance or bank-account data. Employee Lifecycle and Payroll calculations are unchanged. Performance history and goal-progress history are append-only at the application boundary.

## Aggregate and lifecycle

- Cycle state: `draft → assigned → in_progress → self_review → manager_review → calibration → approved → closed`.
- `PerformanceStateMachine` is the typed transition authority. Closed cycles cannot return to an editable state. Goals cannot be assigned or progressed after approval/closure.
- Reviews preserve employee/cycle/reviewer identity, immutable initial checksum, rating timeline, approver and timestamps.
- Maker-checker: the cycle creator cannot be the assigned reviewer; the reviewer cannot approve their own review.
- Every accepted command is company scoped, row locked where a mutable aggregate exists, idempotent and writes a transactional outbox envelope.

## Database — batch 42

Migration `2026_08_02_000030_create_talent_performance_foundation` additively extends the pre-existing `performance_cycles` table and creates `employee_goals`, `goal_progress_history`, `competency_frameworks`, `competencies`, `employee_competencies`, `performance_reviews`, `performance_review_items`, `performance_review_history`, `development_plans` and `talent_management_outbox`.

The existing legacy cycle `status` enum remains compatible; `talent_status` carries the complete Talent lifecycle. No existing migration was edited. The first MySQL application stopped on an overlong index name after transactional-DDL-independent tables had been created; the additive migration was made idempotent, the index was renamed `employee_competency_effective_index`, and batch 42 then completed without deleting or rewriting business rows.

## CQRS and security

HTTP reads dispatch `GetPerformanceDashboardQuery` or `EmployeePerformance360Query` through the shared Query Bus and company-scoped read repository. HTTP writes use `PerformanceActionRequest`, policy authorization, a dedicated immutable Command and Handler, then the responsible domain service. Controllers do not query the database.

Permissions: `talent.performance.view`, `talent.performance.manage`, `talent.goal.create`, `talent.goal.update`, `talent.review.submit`, `talent.review.approve`, `talent.review.close`. Missing permission returns 403; a foreign Employee is hidden with 404.

## Verification evidence

- Focused lifecycle/security: 3 tests, 28 assertions.
- Typed state machine plus focused suite: 11 tests, 29 assertions.
- MySQL process race: 1 test, 10 assertions; create/create and assign/assign yield one persisted winner, while approve/approve and close/close are idempotent with one history/outbox effect.
- PHPStan `Modules/Performance/app --level=10`: 0 errors.
- Browser: dashboard and Employee 360 verified at 1280×800 and 390×844. Both pages had `scrollWidth = clientWidth`; console error count was 0.
- Production `schema_admin`: all Talent tables have 0 rows after verification; Employee and Assignment counts remain 9/9. No Talent seed was run.

## Known scope boundary

Competency Framework and Development Plan persistence/read foundations are present. Their full administration workflows are deferred; Phase 3E-5C does not begin Phase 3E-5D.
