# Cache Governance

Cacheable read models are company scoped, versioned, filter hashed and serialization safe. `CacheKeyPolicy` excludes sensitive filter names and generates `schema:{component}:company:{id}:v{version}:{sha256}` keys.

Safe caches include Dashboard aggregates, Organization headcount/tree, workforce management, Attendance details/readiness and Payroll delivery read models. Secret plaintext, restricted export payloads and mutable Eloquent objects are never cached. Domain events/version bumps provide invalidation; bounded TTL is recovery protection, not a substitute for invalidation.

Redis failure retains the Phase 3F-8/11 contract: core responses do not fail merely because telemetry/cache recording fails. Security and side-effect runtime configuration follows its documented fail-closed classification.
