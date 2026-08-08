# Enterprise Workflow Engine Design

The Workflow bounded context owns versioned definitions, pinned instances, tasks, decisions, routing, SLA/escalation, append-only history and outbox. Domain bounded contexts continue to own Employee, Payroll, Accounting and Filing status. Completion is emitted through an outbox and consumed through `WorkflowCompletionHandlerInterface`; the engine never performs a domain-table update.

Foundation routing modes: sequential, parallel, any-one, all-must-approve and conditional. Resolvers support a specific user, employee manager, permission-based approver and organization role. Definition versions are immutable after publication and instances pin the exact version/checksum.

Mutation lock order is definition/version → instance → task → decision/history → outbox. Conditional evaluation uses the persisted instance context. Resolver output is always intersected with `company_user_access`; manager resolution returns the manager employee's linked user. Completion is idempotent and emits one tenant-safe outbox record rather than writing another bounded context.
