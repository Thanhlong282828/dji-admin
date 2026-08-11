# Credential Rotation Governance

Rotation requires an owner, change reference, synthetic or provider-safe verification, rollback reference and evidence containing reference/version only. High-risk production rotation uses maker-checker policy when multiple authorized operators exist.

APP_KEY rotation requires decrypt/re-encrypt inventory, session/cookie impact review, dual-key reader support and rollback proof. Database credentials require a new least-privilege user, verification, application cutover, observation and old-user revocation. Production runtime credentials must not have `DROP DATABASE`, `CREATE USER` or `GRANT` unless a separately controlled migration operator requires them.

Historical evidence is retained; secret values, reversible fingerprints and low-entropy hashes are prohibited.
