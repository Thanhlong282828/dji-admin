# Enterprise IAM Governance design

IAM owns governance decisions, not authentication. Laravel Auth owns credentials and sessions; Sanctum owns token internals; Spatie owns effective runtime roles/permissions. Domain Policies retain business authorization.

## Lifecycle

`requested → reviewed → approved → provisioned → active → expired|revoked`; requested/reviewed may become rejected. Requester cannot approve. Blocking SoD prevents provisioning. Privileged assignments require a reason, approver and finite expiry. State changes lock aggregate rows and append checksummed history/outbox records.

## Boundary and lock order

HTTP Route → typed FormRequest → Controller → Command/Query Bus → Handler → IAM service/read repository → SoD → Spatie adapter → IAM history/outbox. Lock order: access request, request items, assignment, Spatie pivot, history, outbox. Unique idempotency and active-assignment keys provide retry safety.

## SoD examples

Payroll Maker/Approver, Payment Creator/Approver, Accounting Creator/Approver, Government Filing Creator/Submitter, ERP Creator/Executor and IAM Requester/Approver. Rules support role-role, permission-permission and role-permission with warning or blocking severity.

## Integrations

IAM emits safe outbox intents. Workflow remains the approval engine; Notification Center remains the delivery engine. Employee termination creates a future revocation governance intent and transfer creates a review intent—Employee does not mutate Spatie directly through the new boundary.

Review certification persists user/company, runtime roles, direct permissions, effective assignments and privileged expiry at campaign creation. Live Spatie changes cannot rewrite it. Workflow-managed requests pin instance/version; completion and rejection consumers validate the reference before dispatching an IAM command. IAM outbox notification payloads contain only safe event and aggregate references.

Integration lock order is source outbox/checkpoint → IAM request or review → IAM assignment → Spatie adapter pivot → governance history → IAM outbox. Every consumer uses a transaction with a bounded three-attempt deadlock retry and does not retry domain validation conflicts blindly.
