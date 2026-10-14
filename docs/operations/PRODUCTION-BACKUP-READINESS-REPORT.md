# LaravelDji Backup and Release Readiness Evidence

## Evidence boundary

Evidence captured on 2026-10-14 for local release preservation and production backup preflight. The local backup and isolated local restore were authorized. Production access was read-only: no production dump, Compute snapshot, file archive, migration, service change, Supabase object scan or object mutation was performed.

Classification: `BACKUP_READINESS_PARTIAL`.

## Local source preservation

| Check | Evidence | Result |
|---|---|---|
| Project | `/Users/lahoathanh/Desktop/laraveldji/admin` | VERIFIED |
| Branch | `codex/erp-finance-tax-runtime-hardening` | VERIFIED |
| HEAD | `327237372aa5c9eeaca42736b711c94ace58a396` | VERIFIED |
| Working-tree entries at capture | 86 | PRESERVED, release remains dirty |
| Source file count | 4,644 tracked/untracked non-ignored paths at final sealing | VERIFIED |
| Git history | verified Git bundle | PASS |
| Working tree | `working-tree-source.tar.gz`, secret path scan applied | CREATED |
| Source restore | extracted to a new temporary directory and per-file SHA-256 checked | PASS |

The source safeguard deliberately excludes ignored files and blocks `.env`, environment variants, private keys, credential containers and untracked SQL/dump files. The tracked `docker/mysql/001-schema.sql` is canonical source and was retained. This backup does not convert the dirty tree into an immutable release candidate.

Final release inventory at source sealing: 47 modules, 753 routes, 282 Artisan commands and 127 migration files. Composer and npm lockfile SHA-256 values are recorded in `manifests/release-candidate-inventory.txt`. Status remains `BLOCKED_DIRTY_WORKTREE`.

## Local MySQL backup and restore

| Check | Evidence | Result |
|---|---|---|
| Environment | local Mac only | VERIFIED |
| Configured/actual database | `schema_admin` on `MacBook-Air-cua-la.local` | VERIFIED |
| MySQL | 9.6.0; 355 tables; 113 migration rows; all tables InnoDB | VERIFIED |
| Logical consistency | `--single-transaction --quick`, routines/triggers/events included | VERIFIED |
| Source size | 38,567,936 bytes | RECORDED |
| Archive | gzip then AES-256-CBC/PBKDF2-SHA-256, 600,000 iterations | CREATED |
| Restore target | `schema_admin_backup_verify_20261014143044` | ISOLATED LOCAL DB |
| Restored identity | 355 tables; 113 migration rows | PASS |
| Restore result | exact encrypted archive decrypted and restored | PASS |
| Full row-count comparison | 355/355 tables, zero mismatches | PASS |
| Foreign-key integrity | 1,226 constraints checked, zero orphan violations | PASS |

The random encryption secret is not stored with the archive. It is referenced by macOS Keychain service `laraveldji-backup-20261014T143044Z`; the value was never printed in evidence.

## Local persistent files

| Check | Evidence | Result |
|---|---|---|
| Root | `storage/app` | VERIFIED |
| Files | 439 | RECORDED |
| Uncompressed bytes | 2,452,590,592 | RECORDED |
| Archive | tar + zstd, encrypted with the same separately held random key | CREATED |
| Restore | extracted into a new temporary directory | PASS |
| Integrity | every recorded file SHA-256 matched | PASS |

## Backup location and integrity

Canonical verified backup root:

`/Users/lahoathanh/Documents/Znews like newspaper/backups/laraveldji/20261014T143044Z`

Canonical artifacts:

- Source/history: `source/repository-history.bundle`
- Current working tree: `source/working-tree-source.tar.gz`
- Local MySQL: `database/schema_admin.sql.gz.enc`
- Local persistent files: `storage/local-storage-app.tar.zst.enc`
- Integrity manifest: `checksums.sha256`

The initially planned Desktop destination was denied by macOS before capture. The backup was therefore written outside the repository to the authorized Documents workspace.

| Artifact group | Approximate encrypted/archive size |
|---|---:|
| Whole backup set | 315 MiB |
| Source/history | 9.9 MiB |
| Database | 320 KiB |
| Persistent storage | 304 MiB |

The top-level checksum manifest contains 17 artifact/manifest entries and passed `shasum -a 256 -c`. A failed pre-scan attempt exists at `20261014T143013Z`; it is incomplete and non-authoritative and must not be used for restore.

Canonical immutable-data checksums at sealing:

- Git history bundle: `77de77bc728824224653cdb13477e8a5e1ef4a52113b6ef751fc09e8bab84b1c`
- Encrypted MySQL archive: `21bccc65626afa7ba105bcf0bfaa534775c438d62bdcbca4d25f8416c52b36a3`
- Encrypted persistent-storage archive: `2b9432c0faf84906f1eefcfc4a3c80b5700131997d76a6cbb28b5d798b5a77a8`

The source archive contains this report, so its final checksum is intentionally maintained only in the external sibling `checksums.sha256` manifest to avoid a self-referential checksum. That manifest is the authority for every artifact.

## Local data-source classification

- `LOCAL_DATA`: local `schema_admin`, local `storage/app`, and the dirty source tree were captured as described above.
- `PRODUCTION_DATA`: inspected read-only only; no production bytes were copied.
- `SUPABASE_OBJECT_DATA`: configuration and MySQL metadata inventory only; object bytes were not enumerated or copied.
- `TEST_DATA`: testing/browser/recovery schemas were excluded from the backup and are not deployment inputs.
- Redis: local Redis answered `PONG`; RDB persistence reported healthy and AOF was disabled at discovery. Redis cache/session/queue state was not promoted to canonical business data and was not included in the recovery set.

## Supabase S3 inventory boundary

Laravel resolves the canonical `supabase` disk as `supabase-s3`; endpoint, bucket, access key ID and secret are all `PRESENT` without values being displayed. Local metadata counts include:

- `employee_documents`: 36
- `enterprise_documents`: 3
- `enterprise_document_versions`: 3
- `government_tax_filing_documents`: 1
- `media_assets`: 31

Other matching document/history/attachment tables were zero at capture. These are database metadata counts only. No bucket listing, object download, copy, upload or deletion was authorized in this phase.

Status: `INVENTORY ONLY — OBJECT BYTE BACKUP NOT VERIFIED`.

## Production read-only preflight

| Dimension | Evidence | Result |
|---|---|---|
| Cloud project | `project-06b7a0c7-35a4-4282-b81` | VERIFIED |
| VM / zone | `laravel-server` / `asia-southeast1-a` | VERIFIED |
| Access | OS Login user `lychieuthan35_gmail_com` through IAP | VERIFIED |
| Application env/path | production / `/var/www/laravel` | VERIFIED |
| Artifact | `ab47852de19c6e6fffe017e5005fa7c62ef0f464` | OLD ARTIFACT |
| Database | configured and actual `schema_admin`; MySQL 8.4.10-google | VERIFIED READ-ONLY |
| DB inventory | 336 tables; 104 migration rows; 37,912,576 bytes; all InnoDB | RECORDED |
| Root disk | 20 GiB, 11 GiB available, pd-balanced | CAPACITY PREFLIGHT ONLY |
| RAM | 964 MiB total, 275 MiB available at sample; swap active | CONSTRAINED |
| Tools | mysqldump, mysql, tar, gzip, zstd, openssl, shasum present | PASS |
| Local storage root | 3 files, 61 bytes in deployed artifact | INVENTORY ONLY |
| `/var/backups` | exists; audit identity not writable | BLOCKER FOR THAT IDENTITY |
| Production capture | no dump, snapshot or archive created | APPROVAL REQUIRED |

The VPS checkout is not clean. Read-only status reported:

- modified `app/Support/EnvironmentSafetyGuard.php`;
- untracked `.env.backup.20260916-193649`;
- untracked `app/Support/EnvironmentSafetyGuard.php.backup.20260916_213141`.

Their contents were not read. The environment backup filename is a security and release-hygiene concern and must be reviewed under separate approval without printing secrets. It must never enter a release artifact or backup report.

## Production backup plan requiring approval

1. Re-verify project, VM, release, production environment, configured DB and actual PDO DB.
2. Resolve the dirty VPS checkout and sensitive backup-file disposition through a separately approved incident/release action.
3. Select an encrypted off-host destination and approve narrowly scoped credentials.
4. Capture a transaction-consistent MySQL dump with routines, triggers and events; record checksum/bytes/version/migration head.
5. Capture persistent local files separately and inventory external Supabase objects under provider-specific approval.
6. Optionally create an explicitly approved Compute disk snapshot as an additional recovery layer, not as a substitute for the logical dump.
7. Restore the exact database dump and file archive into isolated staging.
8. Apply pending release migrations only to that restored copy, run focused/DQ checks and record duration/locks.
9. Retain the verified off-host recovery point through deployment observation and define approved retention/deletion.

No production backup command or snapshot creation is authorized by this report.

## Release readiness

| Gate | Status |
|---|---|
| Local source/data safety copy | CREATED AND LOCALLY RESTORE-VERIFIED |
| Production encrypted off-host backup | APPROVAL REQUIRED |
| Production restore rehearsal | NOT VERIFIED |
| Supabase object-byte backup | NOT VERIFIED |
| Immutable clean release SHA | BLOCKED: local and VPS working states are dirty |
| Staging migration rehearsal | NOT VERIFIED |
| Production deployment | NOT EXECUTED |

The local backup improves recoverability but does not satisfy the production deployment backup gate.

## Data migration strategy

Production business data remains authoritative and must be preserved in place. The new application source and its reviewed migrations move forward; local business rows are not a deployment input.

If a future request requires selected local data in production, first classify each table/record as local development data, shared reference data, production-owned data or private object metadata. Then produce a tenant-aware mapping for company/branch IDs, primary keys, foreign keys and object ownership; detect collisions/duplicates; build an idempotent importer; run dry-run and rollback proof against restored staging; and obtain separate production-import approval. Importing the local dump over `schema_admin` is prohibited.

Status: `PLAN READY — ANY PRODUCTION DATA IMPORT REQUIRES SEPARATE APPROVAL`.
