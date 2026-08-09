# Enterprise Release Governance Design

## Ownership and lifecycle

Release governance owns evidence and deployment control, not business state. Lifecycle: `draft → ready → approved → deploying → verified → completed`; failure moves `deploying → failed → rolled_back|forward_fixed`. A production author must not self-approve where multiple authorized actors exist. A single-developer environment records an explicit approval reference without pretending two actors participated.

## Manifest flow

Quality/CI evidence → immutable Git SHA/tag → immutable image tag + digest → migration inventory → approval/backup/rollback references → `operations:release-manifest` → canonical JSON + SHA-256 → host/CI artifact.

Writing an existing release ID with different content fails. Manifests never include passwords, tokens, registry credentials, database connection data, PII or private paths.

## Preflight flow

`operations:release-check` reads environment/database contract, debug state, connectivity, pending migrations, Redis, scheduler heartbeat, writable runtime paths, immutable candidate/rollback images, quality evidence and backup readiness. It reports JSON PASS/FAIL and never deploys or changes business data.

## Migration governance

- Safe: additive create-table/basic nullable additions without detected risky operations.
- Caution: indexes, unique/FK changes, `Schema::table`, `change`, chunks/cursors; manual review required.
- Blocking: drop/rename, raw statements, mass update/delete; explicit DBA/owner review and deployment plan required.

Scanner output is conservative. Unknown constructs are not automatically certified safe. Historical migrations are never rewritten.

## Configuration governance

Feature configuration is typed under `config/release.php`, environment-overridable and has safe defaults. Secrets remain environment/runtime secret configuration and never enter Git or manifests. Disabling the release dashboard changes presentation only; it does not alter routes or business state.

## Rollback

Application rollback uses a previous immutable tag@digest only after migration, config and database backward compatibility are all proven. Database down migrations are never automated in production. If compatibility is not proven, the decision is forward-fix.
