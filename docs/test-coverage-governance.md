# Test Coverage Governance

Coverage means an invariant has behavioral evidence, not merely an HTTP 200. The canonical registry has 12 critical/high invariants: environment isolation, employee allocation, payroll maker-checker, workflow transitions, notification idempotency, IAM separation, Integration idempotency, Audit immutability, tenant isolation, architecture boundaries, navigation authorization/accessibility and fail-open telemetry health.

Each entry records ID, owner, risk, behavior, type and concrete files. A missing file is a coverage gap and fails `operations:test-quality`. Critical tests should assert transitions, history, idempotency, effects, tenant behavior, checksums and outbox/checkpoints where applicable. Authorization matrices keep explicit guest redirect, missing-permission 403 and foreign-tenant 404 semantics.
