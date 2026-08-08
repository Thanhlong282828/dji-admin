# Phase 3F-4 — Enterprise Integration Hub

Status: **COMPLETED** (2026-08-08).

Implemented in the existing `Modules/Integration` module: additive schema, CQRS command/query entry points, Generic File and guarded Generic HTTP adapters, encrypted credential boundary, outbound idempotency, delivery attempt history, webhook integrity/deduplication, dead-letter replay, tenant authorization, black-gold admin UI and guarded browser fixture.

Current foundation deliberately does not call SAP/Odoo/Government/Payment production endpoints. Existing ERP CSV and Government XML/local submission remain operational and authoritative. Hub bridge adoption is additive and must be activated per domain after payload-contract approval.

Permissions: `integration.view`, `integration.connection.manage`, `integration.message.view`, `integration.retry`, `integration.deadletter.manage`, `integration.audit.view`.

Migration: `2026_08_10_000041_create_enterprise_integration_hub.php` (batch 53). All ten Integration tables remain empty in production.

## Completion hardening evidence

- ERP bridge reads only the persisted `erp_exports` artifact metadata/checksum. Repeating the same source/version intent returns the same Hub message.
- Government bridge reads only the persisted filing/document XML metadata/checksum. Repeating submission intent does not duplicate transport.
- Five MySQL process races passed: queue/queue, delivery/delivery, retry/retry, webhook/webhook and dead-letter replay/replay.
- Webhook tests cover valid/missing/invalid HMAC, tampered payload and provider-event deduplication.
- Credential ViewModel exposes only a masked suffix; plaintext and `credential_reference` are absent.
- Browser evidence: 8 real pages × desktop 1280×800 and mobile 390×844 = 16 successful checks. Client width equals scroll width, console warnings/errors and page errors are zero. Screenshots are in `storage/logs/integration-browser-evidence/` and machine-readable evidence in `storage/logs/integration-browser-evidence.json`.
- Browser fixture was removed after verification; Integration testing rows returned to zero.

Lock order is `source aggregate/artifact → connection → outbound message → attempt/external reference → history → outbox`. Adapter delivery happens after intent persistence; no production external adapter is enabled.

Final gates: Integration focused 12 tests/42 assertions; ERP/Government regression 20 tests/114 assertions; Integration process concurrency 1 test/13 assertions covering five process races; full PHPUnit 352 tests/2,695 assertions in 1,313.517 seconds; Integration app and tests PHPStan level 10 both zero errors; Pint, Blade, Vite and Docker Compose passed; production Integration tables remain zero.
