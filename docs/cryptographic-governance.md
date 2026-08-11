# Cryptographic Governance

| Purpose | Mechanism | Key dependency | Rotation impact |
|---|---|---|---|
| Password verification | Laravel password hash | none | opportunistic rehash |
| Immutable integrity | canonical SHA-256 | none | none |
| Webhook verification | HMAC-SHA-256 | provider secret reference | dual-secret window |
| Integration credentials | Laravel encryption | application encryption key | old-key decrypt and re-encrypt |
| Private artifacts | authorization + SHA-256 checksum | authorization boundary | access/integrity verification |

Hashing, encryption, masking and redaction are not interchangeable. Passwords are hashed; recoverable credentials are encrypted; display PII is masked; unnecessary cross-boundary values are redacted or denied.

Production application-key rotation is not executed in Phase 3F-14. A future rotation must inventory dependent ciphertext, introduce old/new key compatibility, re-encrypt in bounded batches, verify, cut over, and retire the old key only after rollback evidence exists. Key-version persistence remains a documented future decision, not a speculative migration.
