# Enterprise Release Certification Audit

## Scope and baseline

Phase 3F-11 audited the Phase 3F-10 release manifest/check, migration scanner, release/rollback scripts, Docker production/runtime overlays, CI gates, health, reliability and observability contracts at Git `280043f58be7481dedc9daa1c8927bc4bc60635e`. Production remained read-only.

## Findings before implementation

- The Phase 3F-10 manifest recorded migration count but not an inventory checksum; a certificate artifact/read model did not exist.
- The existing runtime overlay used the production environment contract and was therefore unsuitable for isolated release certification.
- A previous immutable image and candidate could be built without checkout by using temporary `git archive` contexts.
- The single-host topology does not prove production blue/green. A local two-revision proxy switch can only certify the design and rollback compatibility.
- The MySQL image healthcheck can briefly report healthy for the temporary initialization server before its planned restart. Certification waited for the final server and retried the non-destructive pretend step.

## Runtime finding and closure

The first candidate returned 500 from all health endpoints during Redis failure because `HttpMetricsRecorder` attempted Redis cache access after the controller response and let the telemetry exception escape. Its immutable failed certificate was retained.

The corrected candidate adds an explicit fail-open telemetry boundary. It does not catch exceptions from the application request; it catches only metric-recording failures after a response exists, writes a safe warning without connection data, and preserves that response. `operations:release-check` also treats scheduler-cache unavailability as a false dependency check rather than a console exception. Final isolated evidence is Redis-down live 200, ready 503, metrics-readiness 503 and `/login` 200; recovery returned 200/200/200.
