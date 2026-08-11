# Security Testing Governance

Adversarial verification is permitted only against `schema_admin_testing`, `schema_admin_browser_testing`, isolated Docker runtimes and mock endpoints. Tests use bounded synthetic payloads; they do not brute-force, contact metadata services, send external email/webhooks, inspect unrelated host files or mutate production.

Required gates cover authentication/session behavior, permission and tenant denial, validation/escaping/CSRF, SSRF policy, webhook HMAC/replay, private artifact integrity, secrets/privacy, dependency audits and container hardening. A failed control remains a finding; it must not be hidden with static-analysis suppression or a changed assertion.

Production verification is limited to route/config inspection and read-only counts. Security evidence must exclude credentials, session values, private paths, PII and restricted HR/Payroll values.
