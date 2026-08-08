# Workforce Operations shared-gap audit

Audit date: 2026-07-26. Database: MySQL `schema_admin`. Working tree was preserved; no reset, wipe, fresh migration or business-data seed was executed.

| Gap | Before | After | Evidence / remaining work |
|---|---|---|---|
| Calendar-day editor | MISSING | COMPLETED | Typed CQRS editor, exact ISO 1–7 validation, tenant shift validation, versioning, transaction, UI and tests. |
| Shift break editor | MISSING | COMPLETED | Fixed/flexible rules, cross-midnight window, overlap/total validation, versioning, archive, UI and tests. |
| Dedicated record Policies | MISSING | COMPLETED | Phase 3A-7 introduced 18 dedicated Policies, exact Controller abilities and exhaustive permission/tenant/state coverage. |
| Leave schedulers | NOT_CONNECTED | COMPLETED | Three daily jobs, chunking, transaction, deterministic DB idempotency, scheduler mutex and focused test. |
| Attachment runtime | NOT_CONNECTED | COMPLETED | Private quarantine/upload/replace/remove/download CQRS, MIME/size/hash/version/retention checks and clean-flow tests. |
| Scanner abstraction | NOT_CONNECTED | COMPLETED | Typed result/status/interface and truthful unavailable adapter. ClamAV runtime remains NOT_CONNECTED. |
| CSV exports | MISSING | COMPLETED | Streaming cursor/generator, UTF-8 BOM, formula guard, filter parity, large-volume test and tenant authorization for workforce, attendance, leave, balance, overtime and payroll input. |
| Notification recipients | NOT_CONNECTED | FUNCTIONAL | Same-company permission fallback, manager and employee outcome resolution. Inactive-user filtering is limited by the current users schema. |
| Durable notification idempotency | NOT_CONNECTED | COMPLETED | Per recipient/channel/entity/version/action unique marker, failure state and independent retry. |
| Frontend partial refresh | NOT_VERIFIED | FUNCTIONAL | Shared Echo reuse, scope matching, DOM-authorized URL, debounce and HTTP fallback. Leave management DOM coverage and live two-browser verification remain NOT_VERIFIED. |
| Cache invalidation | PARTIAL | COMPLETED | Registry-backed cached reads, scoped versions and cross-company read-after-write tests; no `Cache::flush()`. |
| Event/listener inventory | PARTIAL | VALID | Attendance, Leave and Overtime notification listeners each registered once; broadcast remains direct. |
| Process-level MySQL concurrency | NOT_VERIFIED | VERIFIED | Repeatable two-process MySQL barrier matrix passes 7 tests / 132 assertions. |

Security notes: export payloads exclude salary, pay amount, bank data, GPS, device payloads, attachment keys and leave reasons. Quarantined, failed, infected, pending and not-connected attachments are not downloadable. Cross-company records are scoped in SQL before mutation/read.
