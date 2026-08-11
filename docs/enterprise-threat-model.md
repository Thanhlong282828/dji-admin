# Enterprise Threat Model

| Threat | Asset / boundary | Preconditions | Existing control and verification | Residual risk | Status |
|---|---|---|---|---|---|
| Spoof authenticated actor | Browser → Laravel; employee/payroll/IAM | Stolen credential/session | Laravel auth, regeneration, verification, authorization matrix | MFA absent | Mitigated with medium residual |
| Cross-tenant IDOR | Laravel → MySQL | Valid user changes resource ID/company | company gates, scoped repositories, foreign resource 404 tests | New surfaces need matching regression | Mitigated |
| CSRF mutation | Browser → web routes | Victim session and forged request | Laravel CSRF middleware; stateless health routes are GET/read-only | Misconfigured exclusions | Mitigated |
| SSRF | Laravel → external Integration | Manage connection endpoint | HTTPS/public-address policy; external send disabled; mock tests | DNS rebinding if live delivery is later enabled | Fixed foundation |
| Webhook spoof/replay | Provider → webhook | Guessed endpoint/event | HMAC, constant-time comparison, immutable receipt, event dedupe | Timestamp window not universal | Mitigated |
| File traversal/tamper | Application → private storage | Manipulated reference/name | opaque keys, safe filename policy, checksums, authorized streaming | Malware scanner absent | Mitigated with medium residual |
| Privilege escalation | User → IAM runtime permission | Normal user requests/approves own access | Spatie adapter boundary, maker-checker/two-step approval, SoD | Compromised multiple privileged actors | Mitigated |
| Sensitive disclosure | DTO/view/log/outbox | Over-broad payload | classification registry, redaction, safe DTOs, no private paths | New fields require registry update | Mitigated |
| Supply-chain tamper | CI → image/deploy | Compromised dependency/action | lockfiles, audits, immutable image/release evidence, secret scan | Third-party compromise | Monitored |
| Worker replay/tamper | Queue/outbox → consumer | Duplicate or poisoned event | checksum, idempotency/checkpoint guards, bounded retry, DLQ | Operational backlog | Mitigated |
| Denial of service | Public routes/dependencies | Request spike/outage | rate limits where intended, bounded retries, health/reliability controls | No production capacity claim | Managed |

Actors considered: unauthenticated user, normal user, manager, privileged admin, compromised internal account, external provider, worker and CI/CD actor. Assets include employee/Payroll data, identity documents, IAM privilege, credentials, integration/government artifacts, audit evidence and release evidence.
