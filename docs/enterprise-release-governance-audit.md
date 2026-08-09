# Phase 3F-10 Enterprise Release Governance Audit

Date: 2026-08-09. Audit completed before implementation. Production remained read-only.

## Baseline

- Branch/tag governance already used immutable Git checkpoints. GitHub Actions separated testing, development and production environments and serialized production deployment.
- Images were tagged by full Git SHA and scanned by Trivy, but deployment did not require or record the registry digest.
- `release.sh` pulled/started the candidate before running migrations and had no preflight, migration classification or immutable evidence artifact.
- `rollback.sh` accepted an arbitrary image reference and did not prove config/schema backward compatibility.
- CI already ran PHPUnit, PHPStan, Pint, Vite, Composer/npm audits, secret scan and readiness smoke. Repository-wide PHPStan level 10, Blade compilation, migration risk scan and release evidence were not explicit.
- Backup/restore scripts had strong execution guards. Deployment did not require a backup-readiness reference.
- Phase 3F-8/9 provided readiness, queue/outbox, scheduler, SLO, alerts and capacity signals suitable for post-deploy acceptance.
- Docker already used non-root PHP/Nginx, cap-drop, no-new-privileges, private MySQL/Redis and production debug-off.

## Gaps resolved

1. Deterministic manifest with Git, image tag/digest, migrations, runtime, gates, approval reference, rollback target and SHA-256 checksum.
2. Read-only release preflight with fail-closed evidence/image/backup requirements.
3. Conservative migration `up()` scanner with safe/caution/blocking classifications.
4. Rollback compatibility checks and immutable image format.
5. CI repository PHPStan level 10, Blade and migration-risk gates.
6. Permission-protected release dashboard and exact responsive browser evidence.

No release database table is justified: immutable files/CI artifacts are the authority. No migration was created.

## Migration inventory audit

The scanner reviewed 61 historical files: 1 safe, 54 caution and 6 blocking/manual-review. The six blocking files contain `DB::statement()` or an `update()` in `up()` and are already-ran historical migrations; they were not modified or re-executed. Future releases should scan only newly introduced/pending files during change review and must not infer safety from a filename.
