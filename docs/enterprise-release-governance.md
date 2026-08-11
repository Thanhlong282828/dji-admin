# Phase 3F-10 Enterprise Release Management

## Implemented controls

- Immutable file-based release manifest and checksum.
- Read-only `operations:release-check` preflight.
- Static migration-risk classification.
- Immutable image tag@digest and prior-image requirement.
- Fail-closed rollback compatibility contract.
- CI quality/security/migration gates.
- Read-only `/admin/operations/releases` protected by `auth`, `verified`, `operations.view`.
- Production-safe health smoke contract using only `/health/live`, `/health/ready` and `/health/metrics-readiness`.

Release stages are preflight, backup readiness, immutable build/digest, migration review, maintenance/compatibility decision, candidate migration, application deployment, readiness, read-only smoke, queue/scheduler/observability verification, acceptance and rollback decision window.

Current Docker host design is single-instance. True zero-downtime or canary deployment is **not verified**. A future canary design would select a small traffic percentage, observe production-configured availability/5xx/p95/queue/dependency signals during a bounded window, then promote or roll back. Local/demo thresholds must never drive automatic production rollback.

## Sample evidence

Testing manifest: `storage/release-manifests/phase-3f-10-foundation-sample.json`; internal checksum `6cf33e31e00b500cc5988de62e5592a6efb56eaf0c81f292c4808fabafd5dcd5`. It references baseline commit `08903359...`, 61 migrations and local verified image ID `sha256:350eb365...`. It is sample testing evidence, not a production release or approval.

Phase 3F-10 adds zero migrations and performs no production deployment, rollback, backup or mutation.

## Completion evidence

- Focused release/development/operations: 15 tests / 50 assertions, pass.
- Full PHPUnit: 409 tests, 408 passed, 4,348 assertions, 1 skipped, exit 0, 1,219.558 seconds.
- Repository `app + Modules` PHPStan level 10 and affected tests: 0 errors.
- Pint, Blade clear/cache/clear, Vite build, three Compose renders and deploy/rollback shell syntax: pass.
- Composer strict validation/audit: pass, zero advisories. npm runtime audit: zero vulnerabilities. Tracked secret scan and CI static validation: pass.
- Browser exact 1280×800 and 390×844: HTTP 200, overflow 0, console/page problems 0; fixture cleanup zero.
- Testing preflight: every check true. Sample manifest internal checksum and external SHA-256 recorded without secrets.
- Migration count remains 61 in all canonical environments; Phase 3F-10 added no migration.
- Production before/after baseline identical.

## Phase 3F-11 certification result

The manifest now includes deterministic migration inventory and source-state checksums, and the dashboard reads immutable release certificates. The original failed certificate is retained. Corrected candidate `280043f...` plus working-tree checksum passed Redis failure/recovery, queue/scheduler, blue/green, rollback, security, browser and quality gates. Production tooling requires `source_state=committed`; see `enterprise-release-certification.md`.
# Phase 3F-12 configuration evidence

New manifests include the non-secret runtime configuration schema version, canonical snapshot checksum and drift status. Secrets and business-policy values remain excluded.

Phase 3F-13 adds secret-readiness schema/checksum/status metadata only; values and ciphertext remain excluded.

Phase 3F-14 adds data-protection schema version, deterministic policy-registry checksum and audit status. Release artifacts contain no field values, PII, secret or storage path.

Phase 3F-15 reused the immutable rollback contract. The previous image started ready against the isolated forward-compatible 61-migration schema; no database down migration or production deployment occurred.
# Phase 3F-16 release security evidence

Release review includes defensive security tests, dependency audits, secret scanning and container inspection. Open critical/high findings block a security-assured release decision.

Phase 3F-18 contributes performance schema version, audit checksum and critical-finding count as safe evidence. Local latency is informational and does not become a production deployment threshold; verified critical query/index correctness findings may block release review.
# API documentation release requirement

Release builds must run `php artisan l5-swagger:generate` and retain the generated artifact required by `/docs`. Route/config cache checks must preserve the protected UI, spec and asset routes. Anonymous publication of either UI or JSON is prohibited.
