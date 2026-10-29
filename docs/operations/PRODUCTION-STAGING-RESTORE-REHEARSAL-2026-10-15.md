# Production Staging Restore & Migration Rehearsal — 2026-10-15

## Executive result

Classification: `BATCH_2_MIGRATION_REHEARSAL_BLOCKED_BY_LEGACY_DATA_QUALITY`

The original `af2cc816` build blocker documented below is retained as historical
evidence. The approved recovery used release
`153e3a14e52a6f424c9ae5e2cbc3eb2fb1bd8aeb`: its immutable image built, the
encrypted production backup restored successfully, and all 23 migrations
105--127 completed. Batch 2 is not classified PASS because the post-migration
read-only data-quality audit found 12 critical legacy findings. Production and
Supabase were not accessed or mutated during the recovery.

## Recovery execution — 2026-10-16

### Release and image

- Clean committed source was transferred as a verified Git bundle; staging
  checkout was detached at the approved SHA with zero status entries.
- Linux/amd64 image ID:
  `sha256:3c20984c69e934edc56d5e6e0831bae040f192b556b98cc953e20f65b9a6ef0e`.
- OCI `org.opencontainers.image.revision` exactly matched the approved SHA.
- Image size: 430,135,514 bytes.
- ClamAV `1.4.6/28154` contained three real signature databases; the isolated
  EICAR assertion passed. No ClamAV daemon was started.

### Restore evidence

- MySQL image: pinned MySQL `8.4.11`, linux/amd64.
- Staging server UUID:
  `d51f8476-c8ca-11f1-90af-0242ac120002`; it differs from production UUID
  `baf57f7f-b152-11f1-aa55-42010a400002`.
- Target database: `laraveldji_staging_restore`; it contained zero tables before
  restore.
- Ciphertext and manifest SHA-256 values matched Batch 1.
- Decryption was streamed through gzip into MySQL; no plaintext dump was
  persisted.
- Restore duration: 40 seconds.
- Restored base tables: 336 (manifest match).
- Restored migration records: 104.
- Restored foreign-key constraints: 1,105.
- Companies/users: 4/16.

Result: `RESTORE_VERIFIED`.

The restore script required one portability correction: GNU `stat -f` prints
filesystem information while returning success, so the previous macOS fallback
did not obtain file mode. The script now selects `stat -f '%Lp'` on Darwin and
`stat -c '%a'` elsewhere. The first recovery attempt stopped before decryption
or database writes; the target still had zero tables.

### Migration evidence

- Preflight: 104 ran, exactly 23 pending (105--127).
- Migration exit code: 0.
- Duration: 51 seconds.
- Postflight: 127 ran, zero pending.
- Base tables after migration: 381.
- Foreign-key constraints after migration: 1,406.
- Companies/users remained 4/16; existing treasury transactions remained 9.
- Eleven representative new runtime tables were present.

Result: `MIGRATION_REHEARSAL_PASS`.

### Data quality and tenant isolation

The command `operations:data-quality-audit --json` ran read-only against the
restored clone:

- configured/actual database both `laraveldji_staging_restore`;
- rules 221; passed 209; failed 12; warnings 0;
- duplicates 0; orphans 0; cross-tenant 0;
- score 0 because critical findings exist;
- checksum
  `d6da5ad24a0798257b93ad85e9295cf3c20161d5a58dffcb85f610d42eedd9a4`.

Failed keys and counts:

- `identity.invalid_verified_phone`: 1;
- `journal_without_policy`: 31;
- `missing_account_mapping`: 62;
- `posting_without_policy`: 31;
- `posting_without_mapping`: 62;
- `legacy_account_mapping_usage`: 62;
- `journal_without_policy_snapshot`: 31;
- `receipt_without_policy`: 3;
- `collection_without_policy`: 2;
- `cogs_without_inventory_source`: 3;
- `expense_without_policy`: 5;
- `period_without_fiscal_year`: 4.

These findings are evidence about the restored operating dataset; no records
were repaired or synthesized during rehearsal. Zero cross-tenant findings is
positive tenant-isolation evidence, but it does not override the critical DQ
blocker.

### Regression and rollback compatibility

- Focused local regression ran only on verified
  `schema_admin_testing`: 20 tests, 20 passed, 164 assertions, exit 0, about
  40.2 seconds.
- The approved release already had an authoritative full suite result of 939
  tests, 938 passed, 1 skipped, 34,245 assertions, exit 0.
- Migration `down()` methods for 105--127 drop runtime tables and, in several
  cases, columns and foreign keys. Once the new release accepts business writes,
  database rollback is destructive. Production rollback must therefore be
  forward-compatible application rollback or a separately tested restore/cutover
  procedure; `migrate:rollback` is not accepted as a production rollback plan.

### Cleanup and cost guard

- The age identity and all generated staging application/MySQL credential files
  were securely removed; a recursive header scan found zero age secret keys.
- The MySQL container was removed. Its Docker volume remains on the stopped,
  Google-encrypted staging disk as migration evidence; deleting that volume or
  the VM/disk requires a separately approved destructive cleanup.
- VM state after execution: `TERMINATED` at
  `2026-10-15T12:15:03.169-07:00`; it still has no public IP.
- Cloud NAT `laraveldji-staging-nat` was deleted; the router has no NAT entries.
- The recovery used roughly 40 minutes of e2-medium runtime, a short-lived NAT,
  less than 1 GiB of image/backup transfer, and the retained 30 GiB disk.
  Provider billing is delayed, so an actual invoiced number is not yet
  available. The conservative recovery estimate is below USD 0.25; including
  the earlier stopped attempt remains materially below the approved USD 5 /
  VND 130,000 ceiling.
- Final script syntax checks passed. Runtime hardening unit tests: 8 passed,
  96 assertions. `git diff --check` passed.

## Approved identities re-verified

- Active Google account: `lychieuthan35@gmail.com`.
- GCP project: `project-06b7a0c7-35a4-4282-b81` (`379534698416`, ACTIVE).
- Release branch: `codex/erp-finance-tax-runtime-hardening`.
- Local and origin SHA: `af2cc816c811667bfc0ea1e51fdcff7a4d25a0db`; ahead/behind `0/0`.
- Composer lock SHA-256: `25c249a3c8edfb39d99bb22117cb4f0b54dfc39a4173bb53274df7a691cd068f`.
- Package lock SHA-256: `8b8c3e46334186621ca6233dd80dd1f3216fbf91f3197e7f2aee775edefefb51`.
- Backup ciphertext SHA-256: `26b6a22cfa8e0c8c32df5fe52b7377ccbcb5a2c8a76fb1a4932d36d488e1f6b8`.
- Manifest SHA-256: `fc09c37cfa329b2a3abf0c7ec703d435d817d06bc28ae23add8fe8cbc9b9fcd3`.
- Source database/server UUID: `schema_admin / baf57f7f-b152-11f1-aa55-42010a400002`.
- Age recovery derived the approved recipient fingerprint SHA-256 `6f184656ee458e5afdfc33743d6298314896e7d47232f8073772a6053f2d8788` without exposing the identity.
- Source archive SHA-256 on local and staging: `d8b95be7adef977794752aabb7772dbeab3c1ab74af23605baa16ac5bc67780f`.

## Budget control

- Cloud Billing Budget API was enabled for the approved project.
- Temporary budget created: `LaravelDji Batch 2 staging 2026-10-15`.
- Budget ID: `217990b9-4b85-471e-a1e6-d20a6ccce53a`.
- Amount: VND 130,000, approximately the approved USD 5 planning ceiling.
- Thresholds: 50%, 90%, and 100% current spend.
- A budget alert is not a cost shutoff. An independent systemd timer was installed to stop the VM after 23 hours.
- Once the blocker was confirmed, the VM was stopped immediately and Cloud NAT was removed.
- Billing data is delayed; actual provider-attributed cost is not yet available. Based on about 18 minutes of e2-medium runtime plus short-lived NAT and a 30 GiB disk, incurred Batch 2 cost is expected to be materially below USD 1 and the USD 5 ceiling.

## Staging identity

| Resource | Identity / observed state |
|---|---|
| VM | `laraveldji-staging-rehearsal-20261015` |
| Instance ID | `4026030371236572795` |
| Zone | `asia-southeast1-a` |
| Machine | `e2-medium`, 2 vCPU, 4 GiB RAM |
| Disk | 30 GiB `pd-balanced`, auto-delete with VM |
| Runtime | Debian 12, Docker `20.10.24`, age `1.1.1` |
| Network | `laraveldji-staging-net` |
| Subnet | `laraveldji-staging-asia-se1`, `10.60.0.0/24`, Private Google Access enabled |
| Internal IP | `10.60.0.2` |
| External IP | none |
| Admin ingress | IAP source `35.235.240.0/20`, TCP 22 only |
| Service account | `laraveldji-staging-runner@project-06b7a0c7-35a4-4282-b81.iam.gserviceaccount.com` |
| Storage permission | read-only on the dedicated encrypted backup bucket |
| Created | `2026-10-15T06:09:40.882-07:00` |
| Stopped | `2026-10-15T06:27:30.678-07:00` |
| Current VM state | `TERMINATED` |
| Cloud NAT | deleted after blocker |

The remaining stopped VM/disk, VPC, subnet, IAP firewall, empty router, service account/IAM binding, and budget are retained only pending separately approved destructive cleanup. No compute or NAT is running. The persistent disk can still accrue a small storage charge until cleanup.

## Isolation result

- No public VM IP.
- No public HTTP/HTTPS firewall rule.
- Administrative access used Google IAP and OS Login.
- No production `.env` was present in the staging release directory.
- No Supabase endpoint, access key, secret, or write permission was delivered.
- No application staging environment was generated because execution stopped at build.
- Queue, Horizon, scheduler, mail, webhook, payment, alert, broadcasting, and reconciliation processes were never started.
- Production `laravel-server` remained RUNNING with instance ID `549764252224027593` and was not accessed by the Batch 2 execution commands.

## Backup transfer and key safety

- The four approved encrypted artifact/manifest files were transferred through IAP.
- Ciphertext and manifest checksums passed again on staging.
- Age identity fingerprint passed on staging without printing key material.
- The age identity was mode 600 while transiently present.
- Because restore did not start, the transient age identity was securely removed from staging and the local temporary transfer bundle was removed.
- No plaintext SQL dump was written.

## Release build blocker

The exact release archive passed source and lock-file verification. Docker build then failed at Dockerfile step 24.

Verified conditions inside intermediate image `7fa8f06deebb`:

- `/usr/bin/clamscan`: present and executable.
- PHP extensions `bcmath`, `gd`, `intl`, `pcntl`, `pdo_mysql`, and `zip`: present.
- `/var/lib/clamav/*.cvd` or `*.cld`: zero files.

The release Dockerfile requires at least one ClamAV signature file during build:

```text
find /var/lib/clamav -maxdepth 1 -type f ( *.cvd or *.cld ) | grep -q .
```

That command returned non-zero. Therefore image tag `laraveldji-staging:af2cc816` was not created. This is a reproducible release-artifact defect, not an infrastructure, backup, database, PHP-extension, or memory failure.

- Peak observed VM available memory remained approximately 2.9–3.3 GiB.
- Disk free remained approximately 24 GiB.
- Build log SHA-256: `6d73b67d1bd366397afa55f2cadf4fd153ac5edfa719e2e3894fa0163c811060`.
- Redacted/local evidence: `/private/tmp/laraveldji-batch2-evidence/image-build-retry.log`.

No source patch was authorized in Batch 2, so the Dockerfile was not modified and an alternate/noncanonical application image was not used.

## Restore result

- Staging MySQL container/server created: **NO**.
- Target database created: **NO**.
- Target server UUID available: **NO**.
- Backup restored: **NO**.
- 336 base tables verified after restore: **NOT RUN**.
- 104 migration records verified after restore: **NOT RUN**.
- Row/FK/tenant evidence: **NOT RUN**.

Historical first-attempt result (superseded by the recovery evidence above):
`RESTORE_NOT_ATTEMPTED_BUILD_BLOCKER`.

## Migration result

- Pending boundary 105–127 in source inventory: 23 files confirmed.
- Restore verification prerequisite: not satisfied.
- Migrations executed: **0**.
- Migration duration/locks/resources: **NOT RUN**.
- Rollback compatibility: static review only; runtime compatibility not proven.

The migration scripts include many new tables and foreign keys, plus alterations such as fiscal-year linkage and supplier-credit/vendor-refund fields. Their `down()` methods commonly drop runtime tables/columns and are not accepted as a safe production rollback once new-version writes exist.

Historical first-attempt result (superseded by the recovery evidence above):
`MIGRATION_REHEARSAL_NOT_ATTEMPTED`.

## Test and DQ result

- Runtime image build: FAIL.
- MySQL restore checks: NOT RUN.
- Data quality on restored clone: NOT RUN.
- Tenant isolation on restored clone: NOT RUN.
- Focused application regression on restored clone: NOT RUN.
- `git diff --check`: PASS.

No test expectation, environment guard, migration baseline, or production configuration was changed to hide the failure.

## Production and Supabase safety

- Production database mutation: none.
- Production migration: none.
- Production VPS deployment/restart/configuration change: none.
- Supabase credential delivered to staging: none.
- Supabase object read/write/delete: none.
- Mail/webhook/payment/alert side effect: none.

## Required remediation before a new rehearsal

1. Approve a source-level correction to the release Dockerfile/ClamAV signature lifecycle.
2. Decide whether signatures are fetched during the immutable build with pinned/verifiable evidence, or mounted/updated at runtime through the approved remote scanner topology. Do not simply remove the fail-closed scanner readiness requirement.
3. Run focused container/scanner tests, PHPStan, Pint, Blade, Vite, route and security gates.
4. Commit/push a new immutable release SHA and repeat source/lock checksum verification.
5. Separately approve resuming/recreating NAT and starting the stopped staging VM for the restore/migration rehearsal.
6. Separately approve deletion of the retained staging VM/disk/network/firewall/router/IAM binding/service account/budget after evidence is no longer needed.

## Final status

`BATCH_2_MIGRATION_REHEARSAL_BLOCKED_BY_LEGACY_DATA_QUALITY`

Batch 3 and production deployment remain prohibited.
