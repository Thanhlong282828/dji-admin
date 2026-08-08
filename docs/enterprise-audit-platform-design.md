# Enterprise Audit Platform — Design

Ownership is limited to immutable audit events, snapshots, correlations, access evidence, compliance exports, archive artifacts, retention policy and Audit outbox/checkpoints. Source domains remain authoritative and Audit never mutates their business state.

## Ingestion

Each approved source has a concrete adapter: `WorkflowAuditConsumer`, `NotificationAuditConsumer`, `IamAuditConsumer`, `IntegrationAuditConsumer`, `EmployeeAuditConsumer` and `PayrollAuditConsumer`. The scheduled `audit:consume-sources` command invokes them through the common source-consumer contract.

Flow: source row lock → deterministic source envelope → unique `audit_source_checkpoints` guard → `AuditRecorderInterface` → `EnterpriseAuditService` → event/snapshots/outbox. The unique `(source_module, source_event_id, consumer_name)` constraint makes concurrent/retried delivery an idempotent no-op. Correlation and causation IDs are deterministic where the source lacks them.

## Integrity and privacy

Canonical JSON produces SHA-256 checksums. Corrections, retention and archive actions append new evidence; prior audit events and snapshots are never updated or deleted. Recursive redaction removes credentials, password/token/private-key/path values and restricted salary, tax, bank and identity fields.

## Export and archive

CSV uses cursor-based streaming. PDF uses `StreamingPdfWriter`, processing records incrementally without materializing the result set. Private UUID artifacts store only safe references plus MIME, size and checksum. Downloads verify file existence, size, SHA-256 and format signature, then append success or blocked access evidence.

Archive range processing streams immutable JSONL, records event count/range/checksum/actor in `audit_archive_artifacts`, and never physically deletes audit history. Repeated identical archive commands return the existing artifact.

Lock order is company/source checkpoint → audit event or export/archive aggregate → snapshots/history → Audit outbox. Deadlock retries are bounded and never retry business conflicts blindly.
