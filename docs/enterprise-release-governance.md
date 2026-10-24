# Phase 3F-10 Enterprise Release Management

Database routing is environment-owned: Mac local manual/demo uses `schema_admin`; automated testing uses testing-only namespaces; VPS production must supply a separate `DB_DATABASE` through deployment configuration. Release tooling must not default production to any Mac local or testing database. The former fourth local database is removed.

Phase 3F-21 adds a pre-release API contract gate and sanitized OpenAPI checksum evidence. Breaking findings require explicit approval/version/deprecation evidence; formatting-only JSON drift is not a release blocker.

## Implemented controls

- Immutable file-based release manifest and checksum.
- Read-only `operations:release-check` preflight.
- Static migration-risk classification.
- Immutable image tag@digest and prior-image requirement.
- Fail-closed rollback compatibility contract.
- CI quality/security/migration gates.
- Read-only `/admin/operations/releases` protected by `auth`, `verified`, `operations.view`.
- Production-safe health smoke contract using only `/health/live`, `/health/ready` and `/health/metrics-readiness`.

## Permanent bug-fix integration gate

An isolated worktree is a development and verification boundary, not the canonical completion target. Every verified fix must carry an integration record containing the source worktree or commit, exact changed-file manifest, root cause, regression evidence and one of these states: integrated into the primary project, awaiting approval, conflicted or intentionally rejected. A fix that exists only under a temporary path must not be reported as complete.

Before integration, capture the primary project's branch, `HEAD`, status and dirty-file inventory. Preserve all existing changes. Never reset, restore, clean, stash, replace files from another tree or partially apply an interdependent release to avoid a conflict. If a candidate overlaps a dirty file, stop that integration unit and obtain approval for an explicit conflict-resolution plan. GitHub push, PR creation and artifact publication do not update the primary local working tree.

Every bug fix requires executable regression protection at the narrowest meaningful boundary: behavioral PHPUnit/browser/process-race coverage for runtime defects, or a deterministic architecture/configuration/static contract test for tooling defects. Existing assertions and authorization or tenant-denial expectations must not be weakened. Required gates remain PHPStan level 10 over both `app` and `Modules`, Pint, relevant security tests and the domain-specific regression suite; no baseline, `ignoreErrors` or scope exclusion may substitute for a fix.

Browser performance and navigation evidence must come from a fresh isolated application-server process for each authoritative run. An interrupted or timed-out run must terminate that process before retrying so stale single-process requests or session locks cannot be misclassified as an application-query regression. When Reverb is not part of the QA environment, assets must be built with `VITE_REVERB_ENABLED=false`; this is a testing-only presentation setting and must not rewrite production configuration. Browser QA must assert HTTP status, console/page errors and page-level horizontal overflow at the required desktop and mobile viewports; wide tables may scroll only inside their own bounded container.

Root-cause evidence must distinguish source, configuration, tooling and environment failures. Integration must preserve tenant isolation, RBAC/ABAC, policies, explicit Eloquent mass-assignment allowlists, immutable accounting evidence and Finance/Tax calculation contracts. Reintroducing `$guarded = []`, global `Model::unguard()` or silent authorization bypasses is prohibited.

Completion reporting must identify integrated fixes, pending fixes, conflicts, exact files, current gate results and approvals still required. Worktrees containing unintegrated changes remain preserved. Local integration does not authorize commit, push, merge, deployment or any production/cloud/database mutation; each retains its independent approval gate.

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
