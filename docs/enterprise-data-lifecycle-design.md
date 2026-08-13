# Enterprise Data Lifecycle Design

Flow: versioned config registry → effective-date resolver → immutable resource/hold inputs → deterministic decision → safe aggregate snapshot. The engine has no database writer and no generic module mutation capability.

Decision order is deliberate: active legal hold → active business state → dependency block → approved retention period → elapsed-time check → archive/integrity requirement → owning adapter availability → dry-run disposal eligibility. This makes legal hold authoritative and prevents cascade-delete surprises.

Policies contain key, version, owner, resource type, classification, optional duration, archive/disposal behavior, hold eligibility, source and effective dates. The normalized policy snapshot has a SHA-256 checksum for release evidence without resource identifiers or PII.

Future mutation must be implemented behind a module-owned adapter and maker-checker control. A future disposal worker must remain disabled by default, validate a current decision again, preserve append-only evidence and emit safe audit evidence. It must never execute from the dashboard or the current CLI.
