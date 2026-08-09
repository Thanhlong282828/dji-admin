# Enterprise Release Certification Design

## Evidence chain

Git commit → immutable candidate image tag/digest → migration inventory count/checksum → release manifest checksum → isolated release gates → release certificate checksum → read-only operations dashboard.

The certificate scope is `testing-production-like-certification-only`. It must never be represented as a production deployment or approval. Manifest and certificate writers are immutable: a path may be reused only when bytes are identical.

The manifest records Git baseline separately from source state/checksum. A committed release uses `committed`; this uncommitted certification candidate explicitly uses `working_tree` and a deterministic SHA-256, so its image is never represented as pristine Git content.

## Isolated topology

- Candidate app, Nginx, one queue worker set, one scheduler, Horizon, Reverb, MySQL and Redis run under Compose project `phase3f11cert`.
- Database is `schema_admin_testing_release` in a disposable Docker volume.
- Application-only blue and green revisions share isolated dependencies; neither starts another worker or scheduler.
- A non-root test-only Nginx proxy switches traffic and emits a non-sensitive certification revision header.
- Application rollback switches green back to blue. Database rollback is never automatic; forward-fix is required when backward compatibility is not proven.

## Acceptance

Certification is fail-closed. Every boolean gate, every final health result, rollback compatibility and browser result must pass. A false gate produces an immutable failed certificate and a non-zero command exit.

HTTP telemetry is non-critical. `RequestCorrelationContext` lets application exceptions propagate normally, but after a response exists it invokes `HttpMetricsRecorder::recordSafely()`. Only that side-effect boundary is fail-open; dependency health remains owned by `OperationsHealthService`.
