# Runtime Configuration Design

## Ownership and precedence

Configuration is classified as static application, runtime safe, secret or business policy. Only runtime-safe operational enablement belongs to this foundation. Resolution is deterministic: code default → environment-backed Laravel config → bounded cache override. Cache failure falls back to environment config. High-risk side-effect controls default disabled/fail-closed; display-only operational controls may fail-open.

`FeatureFlag` is the typed key boundary. `RuntimeConfigurationService` produces immutable entries and a canonical SHA-256 snapshot. It never accepts serialized PHP, dynamic code, arbitrary keys, secret values or per-user PII.

## Side-effect safety

- `integration.outbound_delivery`: checked before Generic HTTP adapter execution; disabled returns `controlled_disabled`, preserving logical intent for later retry.
- `notification.email` and `notification.broadcast`: checked while delivery intents are planned; database notifications remain available.
- realtime, audit export and privileged access flags establish fail-closed typed policy boundaries for their owning modules; adoption must occur at the relevant domain adapter, not controllers.

The current config-file implementation has no runtime mutation. Consequently immutable history, approval, rollback and concurrency are governance contracts rather than fake database state. A future persistent implementation must be additive, versioned, effective-dated, append-only, maker-checker for high risk, tenant-scoped where appropriate and audited through Enterprise Audit.

Cache overrides accept only booleans or typed arrays containing a boolean `enabled` and optional ISO-8601 effective bounds. Invalid values and expired/not-yet-effective values never execute dynamic code. Expired overrides fall back to environment config; invalid high-risk overrides fail closed. Evaluation metrics are aggregate counts only.

## Security

`/admin/operations/configuration` is read-only and guarded by `auth`, `verified`, `operations.view`. `operations:config-audit` is read-only and emits only non-secret aggregate values. Operational/security-global flags cannot be overridden by a company.
