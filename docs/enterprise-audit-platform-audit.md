# Enterprise Audit Platform — Existing-system audit

Phase 3F-5A audited Workflow histories/outbox, Notification deliveries/outbox, IAM governance histories/outbox, Integration histories/attempts/outbox, Employee lifecycle/governance histories and Payroll activity sources. The raw inventory remains in `storage/logs/audit-platform-existing-sources.txt`.

The source domains retain ownership. Audit consumes typed immutable envelopes and does not update Workflow, Notification, IAM, Integration, Employee or Payroll records. Payroll ingestion uses authoritative `activity_log` event rows because Payroll has no dedicated outbox.

The original gaps—uneven actor/correlation fields, no common source checkpoint, materialized PDF data and retention without an artifact—are closed by concrete source consumers, deterministic envelope normalization, batch-55 checkpoint uniqueness, incremental PDF writing and immutable JSONL range archives.

Final production read-only baseline: employees 9; payroll runs 0; IAM requests 0; Workflow instances 0; Notification deliveries 0; Integration outbound messages 0. Audit events, exports, archive artifacts and source checkpoints are all 0. No fake production event, artifact, archive or external call was created.
