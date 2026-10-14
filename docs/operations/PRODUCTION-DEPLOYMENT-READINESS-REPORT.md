# LaravelDji Production Deployment Readiness

## Evidence boundary

This is a read-only deployment-planning report captured on 2026-10-14. It compares the current Mac working tree with the artifact running on Google Cloud VM `laravel-server` in `asia-southeast1-a`. It does not authorize or record a deployment.

No source was pushed, no release was built, no production migration was executed, no `.env` value was changed, and no VPS service was restarted. Production database queries were limited to metadata, migration inventory and capacity measurements.

Classification: `DEPLOYMENT_NOT_READY`.

## Version and artifact identity

| Dimension | Mac local source | VPS artifact | Assessment |
|---|---|---|---|
| Branch | `codex/erp-finance-tax-runtime-hardening` | same branch | Same lineage |
| Git commit | `327237372aa5c9eeaca42736b711c94ace58a396` | `ab47852de19c6e6fffe017e5005fa7c62ef0f464` | VPS is older |
| Commit relationship | VPS commit is an ancestor | 3 commits behind local HEAD | Verified |
| Committed delta | 582 files, +83,163 / -805 | N/A | Large release |
| Local uncommitted state | 85 entries: 54 tracked files changed and 31 untracked files | clean artifact | **BLOCKER** |
| Local uncommitted diff | +3,318 / -339 across 54 tracked files | absent | Not represented by HEAD SHA |
| Artifact model | working checkout | `/var/www/laravel`, not a release symlink | No atomic release/rollback |
| Artifact timestamp | current working tree | 2026-09-19 UTC directory mtime | Older deployment |
| Laravel | 13.27.0 | 13.27.0 | Compatible framework version |
| PHP | 8.4.25 | 8.4.26 | Compatible patch-level difference |
| Composer lock | `0e34e8…` | same SHA-256 | PHP dependency graph matches |
| Package lock | `8b8c3e…` | `22c869…` | Frontend dependency graph differs |
| Node/npm | 24.13.1 / 11.8.0 | 22.23.2 / 10.9.8 | Build off VPS |

The local HEAD SHA is not a deployable identity because material functionality remains uncommitted. An immutable release commit and generated release manifest are mandatory before staging or production approval.

## Capability delta

| Inventory | Mac local | VPS | Delta |
|---|---:|---:|---:|
| Modules | 47 | 46 | CRM module local-only |
| Routes | 708 | 659 | +49 net local |
| Commands | 282 | 272 | +10 local |
| Migration files | 127 | 104 | +23 local |
| Production migration records | N/A | 104 | schema ends at `000104` |

Route identity comparison found 56 local-only and seven VPS-only entries. Several are environment-only fixture/auth/generated routes and must not be treated as production capabilities. The following production capabilities are confirmed local-only or materially newer:

1. CRM bounded context: Pipeline, Leads and Opportunities.
2. Procurement: Goods Return, Supplier Credit and Vendor Refund runtime.
3. Treasury: Opening Balance, Internal Transfer and reversal lineage.
4. Sales/AR: Customer Credit Notes and Customer Refunds.
5. Inventory Governance: stock count/adjustment workspace and governed mutations.
6. Fixed Assets: impairment and disposal lifecycle.
7. Finance governance: Fiscal Year, Accounting Policy workspace and Tax workspace.
8. VAT Policy foundation and governed tax workspace.
9. Functional Currency and FX Rate/Exposure/Revaluation workspaces.
10. AR-FX recognition/settlement evidence.
11. AP-FX supplier invoice recognition and supplier payment settlement.
12. Inventory-FX receipt valuation, return reversal, supplier-credit evidence and supplier-refund settlement.
13. Maintenance part return, work-order acceptance and closeout.
14. Supabase ACL-neutral S3 filesystem adapter.
15. Document storage runtime readiness plus tenant-scoped and bounded multi-company reconciliation commands.
16. Expanded observability/alert configuration. The VPS has an older `ObservabilitySnapshotService` and older `config/operations.php` hashes.

Local-only production commands are:

- `documents:storage-readiness`
- `documents:storage-reconcile`
- `documents:storage-reconcile-tenants`

Other local-only command identities (`breeze`, Debugbar commands, `pail`, `test`) are development tooling and must not be used as reasons to deploy production packages.

The VPS still exposes the legacy direct inventory adjustment route `admin.seller.inventory.adjust`; local governance intentionally removes it. The VPS also exposes local-storage routes absent from the current local route inventory. These removals need an explicit compatibility check for existing clients before cutover.

## Production schema delta

Production uses MySQL `8.4.10-google` over a loopback connection, database `schema_admin`, approximately 37,912,576 bytes across 336 tables. The migration table contains 104 rows and ends with:

`2026_09_13_000104_create_procurement_downstream_reversal_evidence`.

The release would introduce 23 migrations, `000105` through `000127`:

- procurement goods return, supplier credit and vendor refund;
- treasury opening balance/internal transfer/reversal lineage;
- customer credit note/refund and CRM;
- inventory governance, fixed-asset lifecycle and fiscal-year governance;
- VAT and FX foundations;
- AR/AP/Inventory realized and unrealized FX evidence;
- maintenance part return and work-order closeout.

The canonical migration-risk tool classifies all 23 as `caution` with manual review required; none is classified `blocking`. This does **not** make them safe automatically. Risks include:

- 23 migrations in a single production transition;
- many foreign keys and unique indexes whose prerequisite data must be validated;
- metadata locks and table rebuild risk from `Schema::table` in migrations 107, 110, 116, 117 and 118;
- application/schema coupling: new routes and services cannot safely run before their tables exist;
- rollback migrations contain destructive `drop*` operations and are not an acceptable routine production rollback mechanism;
- code rollback after new writes may be incompatible with the old artifact even if schema changes are additive.

## Backup and data-preservation gate

Before any production migration approval:

1. Generate an encrypted, timestamped MySQL logical backup using a transaction-consistent method with routines, triggers and events included.
2. Store the backup off the VM; a backup only on the same 20 GiB root disk is not sufficient.
3. Record checksum, byte count, MySQL version, migration head and table count.
4. Restore that exact backup into an isolated staging database.
5. Verify restored table count, selected business-table counts, foreign-key checks and migration head.
6. Run all 23 migrations against the restored copy and record duration, lock behavior and final migration inventory.
7. Run focused cross-domain tests and data-quality audit on the migrated staging copy.
8. Define the production recovery point objective and maximum maintenance window.

No production migration should start until restore verification succeeds. A `mysqldump` command merely exiting zero is not backup verification.

## RAM and process compatibility

The VPS has 2 vCPU, 964 MiB RAM, approximately 377 MiB available at the audit sample, 4 GiB swap with 187 MiB already used, and a 20 GiB root disk at 46% utilization.

Current runtime consumers include Nginx, PHP-FPM, Redis, one queue worker capped at 192 MiB, and Google Cloud Ops Agent. This topology has limited headroom.

### Safe operating constraints

- Do not run Composer dependency resolution, npm/Vite builds, PHPUnit, PHPStan or bulk reconciliation on this VPS.
- Build an immutable production artifact on CI/staging and transfer only runtime files, optimized vendor dependencies and compiled assets.
- Keep one `queue:work` process with the existing 192 MiB limit initially. Do not enable Horizon concurrently.
- Local Horizon configuration permits a 384 MiB worker and is not suitable for this 1 GiB VM without explicit retuning or VM resize.
- Run tenant reconciliation in bounded cursor batches. The local default is 25 companies per batch, 500 items per company, 30 seconds per company and 240 seconds per batch; it does not enumerate 1–1,000 manually.
- Schedule reconciliation outside peak hours and never run all companies in one PHP process.
- Keep Ops Agent as the host metrics collector; do not add local Prometheus, Grafana and Node Exporter to this VM unless capacity is increased.

### ClamAV decision

Do not install `clamd` or run concurrent `clamscan` processes on the current 1 GiB VM. Signature loading can consume the remaining memory and force swap/OOM alongside PHP-FPM and Redis.

The current local application invokes a configured `clamscan`-compatible binary; it does not yet prove support for a remote ClamD endpoint. Choose and approve one strategy before deployment:

1. **Preferred:** implement and test a private remote scanner adapter, run ClamAV on a separate protected VM/service, retain bounded timeout and fail-closed behavior.
2. **Alternative:** resize the application VM to at least 2 GiB, preferably 4 GiB after measured staging load, then run one ClamD daemon with memory alerts and no parallel scanner processes.

Until one strategy is verified, document uploads that require malware scanning must remain unavailable/fail-closed. Disabling scanning is prohibited.

## Deployment design

### Phase 0 — release candidate

1. Resolve and review all 85 working-tree entries.
2. Produce a clean, reviewed commit; do not deploy the current dirty directory.
3. Generate a release manifest containing commit SHA, composer/package lock hashes, migration range, route count, command count, module count and artifact checksum.
4. Run the project release gates on the exact commit: focused tests, full suite, PHPStan, Pint, Blade compile, Vite build, route/navigation contracts, architecture/CQRS checks, DQ and secret scan.

### Phase 1 — off-host build

1. Build on CI or a staging builder matching PHP 8.4 and Node 22/24 compatibility.
2. Install Composer production dependencies with dev packages excluded and optimized autoloading.
3. Run Vite once on the builder.
4. Package source, `vendor`, compiled assets and release manifest; exclude `.env`, tests, node_modules, local logs and credentials.
5. Verify package checksum before and after transfer.

### Phase 2 — isolated staging validation

1. Restore the verified production backup into isolated staging.
2. Apply migrations 105–127 once and capture duration/locks.
3. Configure staging-only Supabase, scanner and monitoring credentials.
4. Verify all new routes/commands, tenant authorization, queue worker, scheduler, reconciliation dry-run, document upload malware path and signed URL flow.
5. Load-test web plus one queue worker under a 1 GiB memory limit. Record peak RSS, swap growth and OOM events.
6. Approve either remote ClamAV or VM resize based on measured evidence.

### Phase 3 — production change window

This phase requires separate explicit approval.

1. Freeze writes or enable maintenance mode according to the approved downtime plan.
2. Gracefully stop the scheduler and queue worker; do not kill active jobs.
3. Verify queue ready/reserved/delayed counts and resolve the existing failed job decision.
4. Take and verify a final pre-migration backup and off-host checksum.
5. Deploy to a new versioned release directory, never overwrite `/var/www/laravel` in place.
6. Link shared `.env`, storage and persistent files without displaying secrets.
7. Run preflight checks against the new artifact before switching traffic.
8. Run migrations once under an explicit migration approval.
9. Build Laravel caches only after schema success.
10. Atomically switch the `current` symlink, then start the chosen queue/scheduler topology.

### Phase 4 — post-deployment verification

- commit/release manifest and checksums match the approved artifact;
- migration table contains exactly 127 canonical rows;
- route, command and module counts match the release manifest;
- `/health/live`, `/health/ready` and `/health/metrics-readiness` return 200;
- Nginx and PHP-FPM are healthy;
- Redis responds and queue depth remains bounded;
- one canonical worker topology only;
- scheduler heartbeat advances;
- reconciliation command is registered and a bounded, approved dry-run works;
- ClamAV readiness proves binary/service/signature age and PHP-worker compatibility, or remote scanner health;
- Supabase ACL-neutral PutObject contract has no ACL/Grant headers and the approved runtime verification passes;
- observability workspace reads real Ops Agent/backend data;
- no alert is sent until a separate alert-test approval;
- error rate, latency, RAM, swap and disk are watched through the rollback window.

## Rollback design

Application rollback must switch the release symlink to the previous immutable artifact. It must not use `git checkout`, overwrite files in place or run `migrate:rollback` blindly.

Before migration, rollback is an artifact switch. After migration but before new writes, rollback may use the previous artifact only if compatibility has been proven on staging. After new-version writes occur, schema rollback is unsafe; recovery becomes either a forward fix or restore of the verified pre-deployment database backup with an explicitly accepted data-loss window.

The deployment owner must choose the rollback point and write-free window before approval.

## Approval blockers

1. **BLOCKER:** local source is dirty; no immutable SHA contains the complete intended release.
2. **BLOCKER:** no release manifest/checksum for the intended artifact.
3. **BLOCKER:** full release gates have not been run on that exact clean artifact.
4. **BLOCKER:** production is 23 migrations behind; no restore-tested backup/staging migration evidence exists.
5. **BLOCKER:** current deployment is an in-place checkout, without atomic release directory/symlink rollback.
6. **BLOCKER:** ClamAV is absent and the 1 GiB VM cannot safely absorb an unbounded scanner runtime.
7. **BLOCKER:** Supabase production variables and ACL-neutral adapter are absent from the VPS artifact/runtime.
8. **BLOCKER:** bounded multi-company reconciliation commands are absent from the VPS artifact.
9. **BLOCKER:** monitoring backend adapter and alert channel are not configured.
10. **BLOCKER:** one failed queue job requires an explicit inspect/retry/discard decision; no action was taken.
11. **BLOCKER:** removal of legacy inventory mutation/local-storage routes needs consumer compatibility approval.
12. **BLOCKER:** no measured staging memory/load proof for the new artifact on a 1 GiB envelope.

## Required approvals

Separate approvals are required for:

- creating the immutable release commit/artifact;
- provisioning isolated staging and restoring a production backup there;
- taking/verifying production backup;
- choosing remote ClamAV or resizing the VM;
- configuring production `.env`/secret references;
- creating versioned deployment directories and service definitions;
- entering maintenance mode and stopping workers;
- running production migrations 105–127;
- switching the production release symlink and restarting/reloading services;
- running any live Supabase object lifecycle test;
- sending one real alert-delivery test;
- deleting the diagnostic Connectivity Test `ssh-troubleshoot-5ikwk`.

## Final decision

`DEPLOYMENT_NOT_READY`

The runtime evidence from the old VPS artifact proves only the health of that old artifact. It is not evidence that the current Mac working tree, Supabase hardening, ClamAV path, monitoring changes or 1,000-company reconciliation are production-ready.
