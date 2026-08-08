# Employee security

`identity_number` is encrypted with Laravel's application encrypter through
`EncryptedIdentityNumberCast`. Exact duplicate/search uses normalized HMAC-SHA256 in
`identity_number_hash`; plaintext is never used in a cache key, event payload or activity log.
Employee detail masks identity unless the authenticated user has `employee.view-sensitive`.

Tenant authorization combines Spatie permission with `company_user_access`. Repository reads and writes
are scoped by `company_id`, preventing record lookup by a foreign tenant ID.

Private document storage/download authorization is not yet implemented and must not be represented as
production-ready.
# Employee document and identity security

Identity/credential number được mã hóa; export mặc định không chứa chúng. Tài liệu private dùng UUID, MIME/size validation, SHA-256 checksum và scan status. Download authorize mỗi lần. Invitation token chỉ xuất hiện ở notification boundary; DB giữ SHA-256 hash và expiry.
# Recruitment notification and realtime security

Panel Summary is tenant-scoped and requires `interview.view-panel-summary`. Private notes are not part of its Query result or DOM. Generic workflow and notification payloads intentionally omit salary, PII, meeting URL, scorecard, private notes and private document metadata. Company and user private channels are authorized server-side; client-provided company IDs never grant access.
# Hire Candidate security

The route is authenticated, verified, permission protected and tenant scoped. IDs are re-derived through the locked Offer → Application → Candidate/Requisition → Position/Unit chain. Salary and currency are never accepted from the command; they are copied from the accepted offer. Broadcast, notification and activity payloads exclude salary, PII, scorecards, contract terms and storage keys. Idempotency keys are logged only as SHA-256 hashes.
# Workforce security

Authoritative attendance time is server-generated; client timestamps are ignored. Tenant IDs and Employee IDs are re-scoped. Realtime payloads exclude GPS, leave reasons, medical data, salary, bank and storage paths. Payroll inputs intentionally contain minutes and references, not compensation.
# Phase 3A-7 security closure (2026-07-26)

Status: **Phase 3A — COMPLETED**. Record IDs are tenant-scoped before dispatch; denied mutations do not write subject, history, activity or notification rows. Critical races use independent MySQL processes and row locks. The concurrency worker rejects production schemas and unknown operations. Private Leave downloads require ownership/approval scope plus a clean, current attachment. ClamAV remains `NOT_CONNECTED` and the fallback reports that state truthfully.
# Phase 3B-1 Payroll security

Money calculations use integer minor units; no arbitrary formula execution exists. Safe events contain identifiers and lifecycle versions only. Payroll snapshots exclude bank, tax identifier and insurance identifier data. Run self-approval/finalized mutation is denied; full HTTP IDOR matrix remains pending.
