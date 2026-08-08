# Phase 3F-1 — Enterprise Workflow Engine Foundation

## Status

`COMPLETED`. Backend, routing/actor/SLA, MySQL process concurrency, security and real desktop/mobile browser gates are verified.

## Boundary

Workflow owns reusable approval orchestration. Employee, Payroll, Recruitment, Attendance and other bounded contexts continue to own their state machines and source data. Workflow stores only a subject reference and an immutable definition/context snapshot. Completion writes a tenant-safe transactional outbox event; it never updates a domain table.

## Lifecycle

- Definition: `draft → published → archived`; a running instance pins `workflow_definition_version_id`, configuration checksum and snapshot.
- Instance: `draft → running → completed|rejected|cancelled|expired` in `engine_status`; legacy `status` remains compatible.
- Task: `pending → claimed → approved|rejected|cancelled|expired` with optimistic `lock_version` and row locks.
- Decisions and histories are append-only and idempotent. Reject requires a reason.

## Routing and actors

Routing types are sequential, parallel, any-one, all-must-approve and conditional. Actor resolver contracts support a specific user, employee manager, permission and organization role, always constrained by `company_user_access`.

- Sequential activates the next matching step only after the current step completes.
- Any-one records one winning decision and cancels the remaining tasks for that step before advancing.
- Parallel/all-must waits until every required actor has approved.
- Conditional routing evaluates only the immutable `definition_snapshot.context` pinned when the instance starts; live domain changes cannot alter the path.
- Manager resolution maps `employees.manager_id` to the manager employee's `user_id`. Specific-user, permission and organization-role results are filtered by company access.

## Transactions, locks and idempotency

The canonical mutation lock order is definition/version (publish/start) → workflow instance → workflow task → decision/history → outbox. Task commands pre-read only the task reference, then acquire the instance row lock before the task row lock. Transactions use bounded deadlock retry. Unique idempotency keys protect instance start, decisions and history; one completion outbox envelope is allowed per instance/event.

SLA due and escalation timestamps are persisted with tasks. The scheduler locks eligible overdue tasks, applies the configured fallback resolver once per idempotency key and never escalates a terminal task.

## Security

All Admin routes require `auth`, `verified`, a route permission and a second policy check combining permission with company access. Foreign company context returns 404; missing permission returns 403. Workflow payloads do not contain salary, tax, bank data or storage paths.

## Verification evidence

- Migration `2026_08_08_000037_create_enterprise_workflow_engine_foundation`: batch 49.
- Workflow PHPStan level 10: 0 errors.
- Pint (Workflow scope): passed.
- Focused Feature test covers definition → publish → start → claim → approve → completed, exact snapshot linkage, append-only history and outbox.
- Security matrix covers all seven capabilities (`workflow.view`, `workflow.definition.manage`, `workflow.start`, `workflow.task.claim`, `workflow.task.decide`, `workflow.cancel`, `workflow.audit.view`), guest redirect, 403 without permission, actor denial, controlled state conflicts and foreign-company 404. Audit has a dedicated CQRS route/view.
- Unit routing matrix covers empty/equal/not-equal/greater-than/missing-field paths.
- Workflow Feature: 10 tests / 66 assertions passed; Workflow Unit: 5 / 5; MySQL Workflow concurrency: 6 / 29.
- Full PHPUnit regression: 309 tests, 2,520 assertions, all passed (1,172.200s).
- Blade compile/cache/clear, Vite production build and Docker Compose validation: passed.
- MySQL process concurrency: 6 tests / 29 assertions passed. Invariants cover start/start, claim/claim, approve/approve, approve/reject, cancel/approve, escalation/decision and complete/complete: one start history, one claimant/decision/terminal transition, no inconsistent active task, and one completion outbox.
- Real desktop 1280×800 browser: dashboard, definitions, create, tasks, instance timeline, claim and approve verified; document width 1280 and console errors/warnings 0.
- Real mobile 390×844 browser: document width exactly 390, sidebar open/close, stacked cards and wrapped actions verified; wide tables scroll only inside their 320px container and console errors/warnings 0.
- Browser fixture is guarded to `local|testing` plus a `*_testing` database and was cleaned after verification.

## Callback boundary

Completion writes only `workflow_outbox` with `instance_id`, `subject_type` and `subject_id`. Payloads contain no salary, tax, bank, employee-restricted or storage-path data. Workflow never mutates Employee, Payroll, Accounting, Tax, ERP, Government Filing, Performance, Career or Governance tables.

Phase 3F-2 extends this safe boundary with notification-request events for task assignment, escalation and rejection. The Notification Center consumes unpublished Workflow outbox rows idempotently, resolves the safe recipient and marks the source event published only after the Notification message is accepted.

## Production safety

Migration is additive. No workflow definition, task, decision, history or outbox business rows were seeded into `schema_admin`. The original `workflow_instances` and `workflow_actions` contained zero rows before migration.

IAM pins workflow instance/version on each managed request. `iam:consume-workflow` checkpoints completed/rejected events and dispatches IAM Commands; it never writes IAM aggregates directly and retry cannot provision twice.

Process evidence: two PHP processes consumed the same completed Workflow outbox row. Result: one checkpoint, one IAM assignment, one Spatie role pivot, one provisioned history and no duplicate IAM outbox; a later retry was an idempotent no-op.
