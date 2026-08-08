# Enterprise Integration Hub — Design

Boundary: domains create business intents and typed payloads; the Hub transports them. It never decides payroll, accounting, filing, employee, workflow or IAM state.

Lifecycle:

`domain outbox → QueueIntegrationMessage → outbound message → adapter → attempt → delivered|failed → dead letter → replay`

Inbound:

`HTTP webhook → signature validation → immutable receipt + dedupe → inbound message → future typed domain consumer`

Security invariants:

- every record is company scoped;
- credentials are encrypted and only masked status reaches DTO/Blade;
- `(company_id,idempotency_key)` uniquely guards outbound intent;
- `(company_id,provider,event_id)` uniquely guards receipts/messages;
- attempt/history rows are append-only;
- adapters receive payloads but do not contain ERP/Government semantics;
- no real external provider call is enabled in this foundation phase.

Lock order: connection → outbound message → delivery attempt/external reference → history → outbox. Dead-letter replay locks dead letter before resetting its outbound message. Transactions use bounded retry.

Bridge checkpoint identity is derived deterministically from company, source type, source aggregate ID, immutable version and artifact checksum, then protected by the batch-53 outbound unique constraint. No batch-54 migration was required.

ERP: `erp_exports(exported artifact) → EnterpriseDomainIntegrationBridge::queueErpExport → IntegrationHubService::queue → outbound message`. Government: `government_tax_filings + government_tax_filing_documents → queueGovernmentFiling → outbound message`. Neither bridge reconstructs a payload from live Payroll data or exposes a private path.

Dead-letter lifecycle: `queued → delivered|failed`; bounded terminal failure creates one dead-letter record and marks the message `dead_letter`; replay records an immutable history transition and queues the same logical message for a new attempt.
