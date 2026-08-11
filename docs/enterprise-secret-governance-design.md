# Enterprise Secret Governance Design

Secrets are classified as application, database, cache, mail, external API/webhook, encryption/signing, storage or ephemeral token; classification is restricted or critical. Each catalog entry has an owner component, source, required state and rotation interval. Values never enter runtime feature flags.

`SecretReference` validates non-vendor-specific `secret://component/name` identifiers. `SecretProviderInterface` resolves a `ResolvedSecret` only at an infrastructure boundary. The resolved value cannot be string-cast and is absent from ViewModels, Blade, JSON, logs, audit events and release artifacts.

Provider precedence is deployment environment or mounted-secret injection, followed by an existing encrypted store when a domain requires persistence. Redis is not authoritative. Missing optional Integration secrets do not crash core HTTP; critical side effects fail closed.

Rotation lifecycle is active → rotating → rotated → retired, with revoked as an emergency terminal state. Dual-key sequence is provision next, verify, switch reference/version, monitor and retire previous. No production rotation is performed by this phase.
