# Production Staging Recovery Preflight — 2026-10-15

## Executive result

Classification: `BATCH_2_RECOVERY_AWAITING_RESUME_APPROVAL`.

This is read-only recovery evidence. No VM was started, no NAT was created, no
database was created/restored/migrated, and production/Supabase were not
accessed or mutated.

## Release identity

- Branch: `codex/erp-finance-tax-runtime-hardening`.
- Local HEAD: `153e3a14e52a6f424c9ae5e2cbc3eb2fb1bd8aeb`.
- GitHub tracking HEAD: `153e3a14e52a6f424c9ae5e2cbc3eb2fb1bd8aeb`.
- Ahead/behind: `0/0`.
- Migration inventory at that commit contains exactly 23 candidates from
  `2026_09_20_000105_create_procurement_goods_return_runtime` through
  `2026_10_12_000127_create_maintenance_work_order_closeout_runtime`.
- The working tree still contains preserved, uncommitted Batch documentation,
  templates, scripts, and one unstaged test hunk. None is present in release
  commit `153e3a1`, and the dirty local checkout must not be used as a release
  build context.

The local image `laraveldji-local:clamav-fix-amd64` is Linux/amd64 with local
image ID
`sha256:875324a4d047501463397948d2de2b3b7d1a121acf908632fd51e81f43ffce92`.
Its ClamAV signature and EICAR gates passed in the preceding release evidence,
but it has no OCI source-revision label. It is therefore useful build evidence,
not sufficient provenance for the staging release. Recovery must build from a
clean checkout of the full approved SHA and set/verify
`org.opencontainers.image.revision`.

## Backup verification

- Bucket:
  `gs://laraveldji-prod-backup-project-06b7a0c7-35a4-4282-b81`.
- Prefix:
  `mysql/schema_admin/2026/10/15/20261015T084047Z-af2cc816/`.
- Exact object count/bytes: `4 / 437174`.
- Ciphertext generation: `1792055179341519`.
- Ciphertext SHA-256:
  `26b6a22cfa8e0c8c32df5fe52b7377ccbcb5a2c8a76fb1a4932d36d488e1f6b8`.
- Manifest SHA-256:
  `fc09c37cfa329b2a3abf0c7ec703d435d817d06bc28ae23add8fe8cbc9b9fcd3`.
- Age identity permissions: `0600`.
- Derived recipient fingerprint matches the approved fingerprint: PASS.
- Fresh authenticated age decrypt piped to `gzip -t`: PASS.
- Plaintext dump persisted: NO.
- Bucket remains regional Singapore, private-access-prevention enforced,
  uniform bucket access enabled, versioned, 90-day retention and 365-day
  lifecycle deletion.
- Staging service account retains bucket-scoped object viewer access; no
  project-level binding was found for it in the read-only filtered check.

## Existing staging resources

| Resource | Current state |
|---|---|
| VM | `laraveldji-staging-rehearsal-20261015`, `TERMINATED` |
| Instance ID | `4026030371236572795` |
| Zone/machine | `asia-southeast1-a`, `e2-medium` |
| Boot disk | 30 GiB `pd-balanced`, READY, auto-delete with VM |
| Network/subnet | `laraveldji-staging-net` / `laraveldji-staging-asia-se1`, `10.60.0.0/24` |
| Public IP | none |
| Private Google Access | enabled |
| Ingress | IAP `35.235.240.0/20` to TCP 22 only |
| Router | `laraveldji-staging-router`, retained |
| Cloud NAT | absent |
| Service account | `laraveldji-staging-runner@project-06b7a0c7-35a4-4282-b81.iam.gserviceaccount.com` |
| Budget | VND 130,000; thresholds 50%, 90%, 100% |

The VM metadata contains OS Login and project-key blocking only; it contains no
startup secret. Historical execution evidence says the transient age identity
and local transfer bundle were removed before the VM stopped. Because the VM is
terminated, current disk contents cannot be re-inspected without starting it or
attaching the disk, both mutations. Current absence of age identity, credentials
and sensitive artifacts is therefore `NOT_CURRENTLY_REVERIFIED`; it is the first
fail-closed check after an approved start and before any new secret transfer.

The IAP SSH command resolves correctly in dry-run mode to OS Login user
`lychieuthan35_gmail_com`. Actual IAP reachability cannot be proven while the VM
is terminated.

## Guard review

The prepared restore script rejects non-staging environments, rejects
`schema_admin`, requires a `_staging_restore` suffix, exact database and server
UUID confirmations, a target UUID different from production, an empty target,
MySQL 8.4, protected identity/credential files, source manifest identity and
both checksums. Restore is streaming and persists no plaintext SQL dump.

The rehearsal script requires an exact 40-character release SHA equal to the
checked-out HEAD, exactly 23 pending migrations with the 105/127 boundaries,
explicit execute confirmations and an absolute protected evidence directory.
Both scripts pass shell syntax validation.

## Recommended reuse plan

Reuse is safer and cheaper than recreating the topology because the VM/disk,
private network, IAP-only firewall, router, service account, bucket binding and
budget still match the approved design. Do not recreate the VM unless the first
disk/security inspection finds contamination or irreparable drift.

After explicit recovery approval:

1. Recreate one bounded Cloud NAT on the retained router and start the retained
   VM; retain the no-public-IP interface.
2. Connect through IAP and inspect the disk before transferring any credential:
   confirm no age identity, MySQL defaults file, plaintext dump, production
   `.env`, Supabase credential or unexpected running workload remains.
3. Verify/rearm the 24-hour fail-safe stop mechanism and record its deadline.
4. Create a new clean release directory from GitHub SHA `153e3a14...`; verify
   checkout and lock hashes. Never build the local dirty working tree.
5. Build `linux/amd64` with an OCI revision label, record image ID/digest, verify
   genuine ClamAV databases load and EICAR is detected.
6. Generate staging-only secrets. Configure local/test storage only and explicit
   no-op/log transports. Do not deliver production Supabase, mail, payment,
   webhook, alert, broadcast or integration credentials. Do not start queue,
   Horizon, scheduler, Reverb or reconciliation workers.
7. Start pinned MySQL 8.4 with bounded memory, create only
   `laraveldji_staging_restore`, verify its UUID differs from production and the
   target has zero tables.
8. Download the four immutable backup objects, provide the age identity
   transiently with mode 0600, run the guarded streaming restore, then remove
   the identity and protected temporary credentials.
9. Verify 336 base tables, 104 migration records, row counts/aggregates, FK and
   orphan inventories, tenant boundaries and source UUID evidence. Only after
   this gate may the 23 migrations run.
10. Run migrations 105–127 once, measure time/locks/CPU/RAM/disk, then verify
    zero pending migrations, schema/data invariants, DQ, read-only tenant checks,
    focused regression on `schema_admin_testing` only, and forward-only rollback
    compatibility.
11. Stop the VM and remove NAT after evidence capture. Resource deletion remains
    a separate destructive approval unless explicitly added.

## Cost and approval boundary

No compute or NAT is currently running; the retained disk continues to accrue
storage cost. Reusing the VM for a bounded maximum of 24 hours is expected to
remain below the existing USD 5/VND 130,000 ceiling. Public NAT adds its hourly
gateway/IP charge and processed-byte charge; the exact provider bill is delayed.
The existing budget is notification-only, so the verified stop deadline and
post-run stop/NAT removal remain mandatory.

The following mutations require confirmation to resume Batch 2:

- start the retained staging VM;
- recreate bounded Cloud NAT on the retained router;
- write a clean release checkout/image and staging-only configuration to the
  staging disk;
- create the isolated MySQL 8.4 server/database;
- transiently transfer the age identity and backup ciphertext;
- restore only into `laraveldji_staging_restore`;
- run migrations 105–127 only after `RESTORE_VERIFIED`;
- stop the VM and remove NAT after the bounded rehearsal.

Production VM/database, Supabase, Batch 3 and deployment remain excluded.
