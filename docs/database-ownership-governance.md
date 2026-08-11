# Database Ownership Governance

Tables follow their bounded-context prefix and migration evidence: `workflow_*` → Workflow, `notification_*` → Notification, `iam_*` → IAM, `integration_*` → Integration, `audit_*` → Audit. Employee owns `employees` and lifecycle/career records; Payroll owns payroll, tax, payment, accounting, ERP-export and filing state; Organization owns organization units, positions and tenant-access policy data; Company owns companies.

An owning module is the only normal writer. Consumers use CQRS contracts, services or outbox/checkpoints. Read-only cross-module queries require tenant scope and must not infer write ownership. Historical migrations remain central/additive and are not moved for aesthetics. New migrations must state ownership in name/design evidence and preserve the four-environment parity process.
