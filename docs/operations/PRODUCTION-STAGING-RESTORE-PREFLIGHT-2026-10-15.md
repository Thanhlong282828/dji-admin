# Production Staging Restore Preflight — 2026-10-15

## Executive result

Classification: `BATCH_2_PREFLIGHT_READY_APPROVAL_REQUIRED`

This document is execution evidence for Batch 2. It is not a permanent governance rule. No staging resource, restore, migration, production mutation, or Supabase object operation was performed during this preflight.

## Safety boundary

- Production VM, database `schema_admin`, Nginx, PHP-FPM, Redis, queue, scheduler, and production files: unchanged.
- Production Supabase bucket, RLS, credentials, and objects: unchanged.
- Batch 2 staging must be isolated and must not receive production application/provider credentials.
- Restoration is allowed only into a database named with the suffix `_staging_restore` on a server whose UUID differs from production.
- Hard planned spend ceiling: USD 5. Stop before exceeding it and request new approval.

## Batch 1 backup identity

| Property | Verified value |
|---|---|
| GCP project | `project-06b7a0c7-35a4-4282-b81` |
| GCS bucket | `gs://laraveldji-prod-backup-project-06b7a0c7-35a4-4282-b81` |
| Object prefix | `mysql/schema_admin/2026/10/15/20261015T084047Z-af2cc816` |
| Ciphertext | `schema_admin-20261015T084047Z.sql.gz.age` |
| Ciphertext bytes | `436347` |
| Ciphertext SHA-256 | `26b6a22cfa8e0c8c32df5fe52b7377ccbcb5a2c8a76fb1a4932d36d488e1f6b8` |
| Manifest SHA-256 | `fc09c37cfa329b2a3abf0c7ec703d435d817d06bc28ae23add8fe8cbc9b9fcd3` |
| Source database | `schema_admin` |
| Source MySQL | `8.4.10-google` |
| Source server UUID | `baf57f7f-b152-11f1-aa55-42010a400002` |
| Source base tables | `336` |
| Source migrations | `104` |
| Source migration head | `2026_09_13_000104_create_procurement_downstream_reversal_evidence` |

Verification performed:

- Local ciphertext SHA-256 matches the manifest.
- Manifest SHA-256 matches the recorded evidence.
- Independent verifier service-account download produces the same ciphertext checksum.
- Backup writer cannot read the stored object.
- The age recovery identity available through the approved local recovery path derives the expected recipient fingerprint.
- Streaming age decryption followed by gzip integrity validation succeeds without writing a plaintext SQL dump to disk.
- Restore has not yet been executed; therefore recoverability is not yet classified `RESTORE_VERIFIED`.

Bucket controls observed read-only:

- Region: `ASIA-SOUTHEAST1`.
- Uniform bucket-level access: enabled.
- Public access prevention: enforced.
- Versioning: enabled.
- Retention: 7,776,000 seconds (90 days).

## Release identity

| Property | Verified value |
|---|---|
| Branch | `codex/erp-finance-tax-runtime-hardening` |
| Local HEAD | `af2cc816c811667bfc0ea1e51fdcff7a4d25a0db` |
| GitHub/origin HEAD | `af2cc816c811667bfc0ea1e51fdcff7a4d25a0db` |
| Ahead / behind | `0 / 0` |
| `composer.lock` SHA-256 | `25c249a3c8edfb39d99bb22117cb4f0b54dfc39a4173bb53274df7a691cd068f` |
| `package-lock.json` SHA-256 | `8b8c3e46334186621ca6233dd80dd1f3216fbf91f3197e7f2aee775edefefb51` |

The repository contains exactly the expected 23 migrations from canonical ordinal 105 through 127. The backup ends at ordinal 104.

There is not yet an immutable built staging artifact for this Git SHA. After approval, staging must clone the exact SHA, verify both lock-file hashes, build from that clean checkout, and record the resulting image digest. The current dirty local working tree must not be packaged.

## Proposed isolated staging topology

| Resource | Proposed identity / configuration |
|---|---|
| VPC | `laraveldji-staging-net` custom mode |
| Subnet | `laraveldji-staging-asia-se1`, `10.60.0.0/24`, region `asia-southeast1`, Private Google Access enabled |
| Firewall | IAP SSH only: source `35.235.240.0/20`, TCP 22, target tag `laraveldji-staging` |
| Router | `laraveldji-staging-router` |
| Cloud NAT | `laraveldji-staging-nat`; outbound dependency access, no external VM IP |
| Service account | `laraveldji-staging-runner`; backup-bucket object viewer only |
| VM | `laraveldji-staging-rehearsal-20261015`, `asia-southeast1-a`, `e2-medium` (2 vCPU, 4 GiB) |
| Disk | 30 GiB `pd-balanced` |
| Public ingress | None; no public HTTP/HTTPS |
| Intended lifetime | At most 24 hours for rehearsal and evidence capture |

The default VPC is deliberately not proposed because it contains broad default ingress rules. Current regional quota has sufficient headroom: 31 CPUs available from a limit of 32 and sufficient disk/address quota.

### Cost planning

Pricing is an estimate, not a billing guarantee. For a 24-hour rehearsal:

- e2-medium: approximately USD 0.80–1.20 depending on current Singapore pricing.
- 30 GiB balanced persistent disk: expected below approximately USD 0.40 prorated.
- Cloud NAT gateway and low-volume egress: approximately USD 1.10 plus negligible data processing for this rehearsal.
- Planning total: approximately USD 2.30–3.00.
- Hard fail-closed ceiling: USD 5 total for Batch 2; do not continue if projected or observed spend can exceed it.

Official calculators/rates must be checked again immediately before creation because cloud pricing can change.

## IAM and network boundary

- Staging service account receives only `roles/storage.objectViewer` scoped to the dedicated encrypted-backup bucket.
- No production VM control, production database, Secret Manager, Supabase, mail, payment, webhook, DNS, or deployment permission is granted to the staging application runtime.
- Operator impersonation, if needed, is scoped narrowly and removed during approved cleanup.
- VM has no external IP. Administrative access is through IAP SSH only.
- No broad `0.0.0.0/0` staging firewall rule is permitted.

## Restore guard verification

`scripts/operations/restore-encrypted-to-staging.sh` was syntax-checked and exercised in validate-only mode.

Validated guards:

- Requires `APP_ENV=staging`.
- Target database must end `_staging_restore` and cannot be `schema_admin`.
- Requires a MySQL client with major/minor version 8.4.
- Requires exact target database confirmation.
- Verifies ciphertext and manifest checksums.
- Requires age identity file mode 400 or 600.
- Reads the actual selected target database and target MySQL server UUID.
- Requires target server UUID to differ from the production UUID.
- Requires the target database to be empty.
- Requires source database and source UUID in the manifest to match the approved production identity.
- Performs age decryption and gzip integrity checks before import without a plaintext dump on disk.
- Compares restored base-table count against the manifest.

Negative validation using `schema_admin` was rejected as required.

Limitation: table-count equality alone does not prove relational/data integrity. Batch 2 must additionally record selected table row counts, migration history, foreign-key inventory, orphan checks, checksums/aggregates for critical tenant tables, and application-level DQ evidence before and after migration.

## Migration rehearsal guard verification

`scripts/operations/staging-migration-rehearsal.sh` was syntax-checked. It:

- Requires `APP_ENV=staging` and a `_staging_restore` database.
- Requires the full approved release SHA and a checked-out HEAD that matches it.
- Requires exactly 23 pending migrations.
- Requires the first pending migration to be ordinal 105 and the last to be ordinal 127.
- Requires explicit target database and release-SHA confirmation.
- Captures before, after, and summary evidence.
- Executes forced migrations only after all guards pass.
- Requires zero pending migrations after completion.

The script must run only after the restored database evidence has been captured. A migration `down()` method is not accepted as proof of safe rollback. Rollback compatibility will be evaluated from schema/data impact and production cutover design.

## External-side-effect isolation

The staging application will use a dedicated staging-only environment file with at least these constraints:

| Capability | Staging rule |
|---|---|
| Mail | `MAIL_MAILER=log`; no production SMTP/API credentials |
| Queue/Horizon | No queue or Horizon process started; `HORIZON_ENABLED=false` |
| Scheduler | Scheduler process not started |
| Webhooks/integrations | All provider credentials absent; no outbound delivery worker |
| Payments | No production payment credentials or callbacks |
| Broadcasting/Reverb | Disabled/not started |
| Alerts/DDOS automation | Disabled; no Cloudflare mutation token |
| Filesystem/document storage | Explicit local staging disks only |
| Supabase | Endpoint/access key/secret absent; no production bucket access |
| Cache/session | Isolated local/array configuration where compatible |
| Reconciliation | Scheduled reconciliation disabled |

The normal Compose defaults and production override are not safe to use unchanged. Batch 2 requires a staging-specific override that starts only bounded MySQL 8.4 and application CLI/optional localhost-only web services. It must not start queue, scheduler, Reverb, Horizon, Mailpit, or external integration workers. MySQL memory must be bounded for the 4 GiB VM.

Destructive PHPUnit is not run against the restored staging clone because the project's test safety guard permits destructive testing only on `schema_admin_testing`. Tenant isolation on the restored clone will be checked using read-only SQL/application queries and DQ commands proven non-mutating. PHPUnit regression remains on `schema_admin_testing`.

## Approved-order execution plan after explicit authorization

1. Re-verify operator account, project, quota, prices, backup checksum, age recovery, and release SHA.
2. Create the isolated VPC, subnet, IAP-only firewall rule, router, NAT, service account, VM, and disk described above.
3. Grant the staging service account read-only access to only the encrypted-backup bucket.
4. Clone exact SHA `af2cc816c811667bfc0ea1e51fdcff7a4d25a0db`, verify lock hashes, build, and record the immutable image digest.
5. Generate staging-only database credentials; start bounded MySQL 8.4; create only `laraveldji_staging_restore`; record its non-production UUID.
6. Download the approved ciphertext/manifest/checksum artifacts. Transfer the age identity through the approved protected channel, mode 600, without logging it.
7. Execute guarded streaming restore and remove the temporary age identity after restore verification.
8. Verify 336 base tables, 104 migrations, data counts/aggregates, FK inventory, orphan checks, tenant boundaries, and capture pre-migration evidence.
9. Verify exactly 23 pending migrations (105–127), then run the guarded rehearsal while measuring duration, resource use, and database-lock evidence.
10. Verify zero pending migrations, schema/data invariants, tenant isolation, DQ, read-only critical workflows, and source release identity.
11. Evaluate forward-only rollback compatibility and record blockers. Do not use production or Supabase credentials.
12. Stop. Cleanup/destruction of staging resources requires a separately scoped confirmation unless it is explicitly included in the Batch 2 approval.

## Exact mutations requiring approval

The following have **not** happened and require explicit authorization:

1. Create one custom VPC and one custom subnet.
2. Create one IAP-only SSH firewall rule.
3. Create one Cloud Router and one Cloud NAT configuration.
4. Create one staging service account and one bucket-scoped object-viewer binding.
5. Create one no-public-IP e2-medium VM and one 30 GiB balanced disk.
6. Create a staging-only MySQL 8.4 database and credentials on that VM.
7. Download the approved encrypted backup objects to staging, transiently provide the age identity, and restore into `laraveldji_staging_restore`.
8. Build the exact release source on staging and run the 23 migrations only against the restored staging database.
9. Write evidence files on the staging VM and local repository report directories.

Explicitly excluded:

- Any mutation to production VM/database/files/services.
- Any production migration or deployment.
- Any Supabase access or object operation.
- Any production mail, webhook, payment, alert, queue, or scheduled action.
- Any transition to Batch 3.

## Approval gate

Status: `AWAITING_EXPLICIT_BATCH_2_RESOURCE_CREATION_AND_REHEARSAL_APPROVAL`.

No staging resource will be created until the user approves the exact topology and mutations in this report, including the USD 5 hard cost ceiling.
