# LaravelDji Production Release Preparation — 2026-10-15

## Executive result

Classification: `SOURCE_GATES_PASS / PRODUCTION_DEPLOYMENT_BLOCKED`.

This report records current evidence for preparing the release after commit `f4a503c`. It is not deployment approval. No production backup, restore, migration, import, service restart, configuration change, source transfer or release switch was performed.

## Source identity

- Branch: `codex/erp-finance-tax-runtime-hardening`.
- GitHub and local initially matched at `f4a503c2911d25ac48bd0e4d75233846bd28c506` with a clean working tree.
- `f4a503c` contains the accumulated 111-file change set, including the authentication UI/favicon refresh, document-storage hardening, maintenance closeout, monitoring, navigation and governance material.
- Composer advisory remediation subsequently changed `composer.lock` and required two type-safe command fixes. Therefore `f4a503c` is no longer the final release identity. A new clean commit and immutable release manifest are required before staging or production.
- Current Composer lock SHA-256: `25c249a3c8edfb39d99bb22117cb4f0b54dfc39a4173bb53274df7a691cd068f`.
- Current package lock SHA-256: `8b8c3e46334186621ca6233dd80dd1f3216fbf91f3197e7f2aee775edefefb51`.

## Composer security remediation

The previously reported four advisories were reproduced before remediation:

- Laravel framework debug-page XSS advisory;
- two CommonMark raw-HTML/DoS advisories;
- phpseclib X25519 timing advisory.

Local lockfile remediation updated the affected dependency graph, including:

- `laravel/framework` `13.27.0` → `13.36.0`;
- `league/commonmark` `2.10.0` → `2.10.3`;
- `phpseclib/phpseclib` `3.0.56` → `3.0.57`;
- required semver-compatible transitive dependencies.

`composer validate --strict --no-check-publish` passes. `composer audit --locked` now reports no security vulnerability advisories. No production dependency was changed.

Laravel 13.36 tightened static type inference around console options. The current operating-data report and release-check commands now reject non-string option values instead of casting unsupported mixed values. Their focused regression passes.

## Quality evidence

All commands ran locally. Mutating tests used only `APP_ENV=testing`, `DB_CONNECTION=mysql`, actual database `schema_admin_testing`.

| Gate | Result |
|---|---|
| Dependency/storage/maintenance/auth focused regression | 52 passed, 342 assertions |
| Release/report focused regression | 5 passed, 32 assertions |
| Full PHPUnit | 938 tests; 937 passed; 1 skipped; 34,209 assertions; exit 0; 1,515.868 seconds |
| PHPStan | PASS, 0 errors |
| Pint | PASS |
| Blade compile/cache/clear | PASS |
| Vite production build | PASS |
| Route inventory | PASS |
| Composer audit | PASS, zero advisories |
| `git diff --check` | PASS |

The full suite was run once after dependency remediation. No test expectation or PHPStan baseline was weakened.

## Production read-only identity

Read-only evidence was collected through Google IAP:

- Cloud project: `project-06b7a0c7-35a4-4282-b81`.
- VM: `laravel-server`, zone `asia-southeast1-a`, instance ID `549764252224027593`.
- OS Login identity: `lychieuthan35_gmail_com`.
- Application: `/var/www/laravel`, environment `production`.
- Actual database: MySQL `schema_admin`, MySQL `8.4.10-google`, approximately 89.83 MB and 902 tables as reported by Laravel's database inventory command.
- Deployed Git SHA: `ab47852de19c6e6fffe017e5005fa7c62ef0f464`.
- Production composer lock SHA-256: `0e34e82640df7530ba8d4472046c156d22c57d87f435242181e09c1e81b1141b`.
- Migration head: `2026_09_13_000104_create_procurement_downstream_reversal_evidence`.
- Nginx, PHP-FPM and Redis reported active.
- VM capacity sample: 964 MiB RAM, 285 MiB available, 4 GiB swap with 183 MiB used; root disk 20 GiB with 11 GiB available.

The production checkout remains dirty:

- modified `app/Support/EnvironmentSafetyGuard.php`;
- untracked `.env.backup.20260916-193649`;
- untracked `app/Support/EnvironmentSafetyGuard.php.backup.20260916_213141`.

Contents of the environment backup were not read. These files must be preserved and reviewed under a separately approved incident/release action; they must not be copied into the release artifact.

## Production backup and restore gate

No production backup was created. The approved future operation must:

1. re-verify cloud/VM/application/database identity;
2. select an encrypted off-host destination and key custody;
3. capture a transaction-consistent logical dump with routines, triggers and events;
4. capture only approved persistent local storage separately;
5. record checksums, byte counts, MySQL version, migration head and table counts;
6. restore the exact encrypted artifacts into an isolated staging environment;
7. reconcile table counts, selected business counts, foreign keys and migration head;
8. retain the recovery point through the deployment observation window.

A successful dump process alone is not restore evidence. Supabase object bytes remain a separate approval and are not part of an unscoped filesystem backup.

## Isolated staging migration rehearsal

Production is 23 migrations behind the release (`000105`–`000127`). The static scanner classifies every pending migration as `caution` requiring manual review; it found no new `blocking` classification inside this pending range.

Staging has not been provisioned or restored. The required staging environment must isolate MySQL, Redis, queue, scheduler, mail, webhooks, payments and Supabase writes, and must not use production write credentials. After restore it must:

1. record the pre-migration schema and row-count baseline;
2. apply migrations `105`–`127` exactly once while capturing duration and lock evidence;
3. run focused accounting, inventory, procurement, maintenance, document-storage, authorization and DQ checks;
4. validate one bounded worker and scheduler topology;
5. run a read-only document reconciliation dry-run;
6. measure peak RSS/swap under a 1 GiB constraint;
7. prove old-artifact compatibility or define the write-free rollback boundary.

Provisioning a staging VM/database and restoring production data are mutations and require explicit approval.

## Atomic release and rollback preparation

The production checkout is not an acceptable deployment target. The prepared topology is:

- immutable releases under `/var/www/releases/<release-id>`;
- shared `.env`, storage and persistent runtime paths under `/var/www/shared`;
- `/var/www/current` as the atomic symlink;
- Nginx document root targeting `/var/www/current/public`;
- previous immutable release retained as rollback target.

The artifact must be built off-host with production Composer dependencies, compiled Vite assets, manifest and checksums. It must exclude `.env`, credentials, tests, `node_modules`, local logs and the two VPS backup files. Nginx configuration/reload, directory creation, artifact transfer and symlink switch are production mutations requiring an approved change window.

Application rollback is an atomic symlink switch only while schema compatibility is proven. After new-version writes, rollback may require a forward fix or restoration of the verified backup with an explicitly accepted data-loss window; blind migration rollback is prohibited.

## ClamAV decision

The VPS has no `clamscan` or `clamdscan`. The current application scanners execute a local `clamscan`-compatible process and fail closed; a remote ClamD adapter has not been implemented or tested.

Do not install full ClamAV scanning on the current e2-micro without measured evidence. Preferred options, in order:

1. implement/test a private authenticated remote-scanner adapter and operate ClamAV on separate capacity;
2. resize the application VM to at least 2 GiB, preferably 4 GiB after staging measurement, then use one bounded scanner topology;
3. until one option is verified, keep affected uploads fail-closed.

Disabling malware scanning is not an acceptable deployment workaround.

## Local-to-production data boundary

Production business data is authoritative. No local dump or blanket table copy will be imported. The current local-data report identifies potential shared reference/config differences and local-only feature tables, but no row-level allowlist has been approved.

Before any importer is built or run, the owner must approve:

- exact entities/rows requested for import;
- company and branch mappings;
- canonical natural keys and conflict behavior;
- exclusion of users' passwords/tokens/sessions and demo/test data;
- immutable accounting/audit/economic-event policy;
- a separate private-object manifest where files are involved.

Only an allowlisted, tenant-mapped, ID-remapping, idempotent, dry-run-first importer may proceed on restored staging. Production import remains separately gated.

## Remaining blockers and required approvals

1. **New immutable source identity:** commit and push the Composer/security remediation plus this evidence, then generate a new release manifest/artifact checksum.
2. **Production backup mutation approval:** approve one encrypted logical database backup, approved persistent-file capture and off-host transfer for the verified VM/database.
3. **Staging resource/data approval:** choose and approve an isolated staging target and restoration of the exact production backup into it.
4. **Migration rehearsal:** after staging restore, approve execution of migrations `105`–`127` only on staging.
5. **Dirty VPS disposition:** approve preservation/review of the modified guard and backup files without exposing `.env` contents.
6. **Atomic topology mutation:** approve release/shared/current directory creation, Nginx root change and controlled reload after staging passes.
7. **ClamAV architecture:** approve either remote-scanner implementation/separate capacity or VM resize plus bounded local scanner validation.
8. **Local data scope:** approve an exact allowlist and tenant mappings; otherwise no local business data will be imported.
9. **Production deployment/change window:** only after all prior gates pass, separately approve backup refresh, worker/scheduler drain, migration, symlink switch, service reload/start and rollback observation window.

Current production deployment remains `BLOCKED`. GitHub availability is not production deployment evidence.

## Deployment execution re-verification — 2026-10-15

This section supersedes older source/test counts in this evidence report. It records read-only/local-safe verification only; no production backup, cloud resource, restore, migration, release directory, Nginx configuration, service state, database row or object was changed.

### Current source and release identity

- Branch: `codex/erp-finance-tax-runtime-hardening`.
- Local HEAD and GitHub remote HEAD: `af2cc816c811667bfc0ea1e51fdcff7a4d25a0db`.
- Ahead/behind after a fresh fetch: `0 / 0`.
- GitHub contains no newer commit on this branch.
- The working tree still contains reviewed deployment-preparation documents, scripts and one operations test that are not part of `af2cc816`. Therefore deployment must use the immutable GitHub commit unless those changes receive review, commit and push as a separate release candidate.

### Current production read-only evidence

- Project/VM/zone: `project-06b7a0c7-35a4-4282-b81 / laravel-server / asia-southeast1-a`.
- Machine: `e2-micro`, 964 MiB RAM, 4 GiB swap, 20 GiB root disk with about 11 GiB free.
- Application path and Nginx root: `/var/www/laravel`, `/var/www/laravel/public`.
- Deployed SHA: `ab47852de19c6e6fffe017e5005fa7c62ef0f464` on the same branch.
- Dirty production checkout remains: modified `app/Support/EnvironmentSafetyGuard.php`; untracked `.env.backup.20260916-193649`; untracked guard backup. No contents were read or changed.
- Application environment: `production`; actual application database remains `schema_admin` on MySQL `8.4.10-google`.
- Production artifact knows and has run migrations through `2026_09_13_000104`; release migrations `105`–`127` are absent from that old artifact and remain unrehearsed against a production clone.
- Nginx, PHP 8.4 FPM, Redis, queue worker and scheduler timer are active.
- Queue worker is bounded at `--memory=192 --max-jobs=250 --max-time=3600`; one historical failed broadcast job exists.
- Scheduler execution was observed in `activating` state at about 95–110 MiB while consuming an audit task. Staging must measure duration, overlap and memory before cutover.
- `/health/live`, `/health/ready` and `/health/metrics-readiness` each returned HTTP 200 on the old artifact. These are not evidence for the new release.
- VPS `mysqldump` is MariaDB 11.8.6 and remains unsuitable for the MySQL 8.4 backup contract. `age`, `clamscan` and `clamdscan` remain unavailable.

### Current local/testing quality evidence

Database safety was verified before tests: `APP_ENV=testing`, configured and actual database `schema_admin_testing`.

| Gate | Result |
|---|---|
| Backup/restore/rehearsal shell syntax | PASS |
| Runtime resource hardening focused test | 8 passed, 88 assertions |
| Full PHPUnit | 939 tests; 938 passed; 1 skipped; 34,234 assertions; exit 0; 1,477.500 seconds |
| PHPStan | PASS, 0 errors |
| Pint | PASS |
| Blade cache/compile | PASS |
| Vite production build | PASS |
| Route inventory | PASS |
| Composer audit | PASS, 0 advisories |
| NPM production audit | PASS, 0 vulnerabilities |
| `git diff --check` | PASS |

Temporary full-suite log: `/tmp/laraveldji-full-suite-20261015.log` (local machine only; not a release artifact).

### Approval batches

No batch below is approved merely by this report.

1. **Backup infrastructure and one encrypted capture** — create one private ephemeral runner in `asia-southeast1`, create one dedicated private GCS backup bucket with PAP/UBLA and least-privilege writer/viewer identities, deliver the approved age public recipient and temporary database credential, open one IAP tunnel, capture one encrypted `schema_admin` dump, upload immutable ciphertext/sidecars and verify independent download checksum. Expected current-scale cost: under USD 1 for the short-lived runner plus under USD 1/month storage; confirm with current calculator. Impact: production database read load and new GCP resources only. Rollback: close tunnel/revoke temporary access/delete runner; retain verified ciphertext under approved retention.
2. **Isolated staging restore and migration rehearsal** — create one private `e2-medium` staging VM with 30 GiB disk, restore the exact ciphertext to a `_staging_restore` database, execute migrations `105`–`127`, run tenant/data/DQ/queue/scheduler/storage tests and measure duration/RSS/swap. Expected 72-hour envelope: USD 5–15, subject to calculator. Impact: staging only. Rollback: discard invalid staging DB/VM; production remains untouched.
3. **Production file preservation and atomic topology** — archive the three dirty VPS items into a root-only protected evidence location without printing `.env`, create release/shared/current topology, validate candidate Nginx config and configure the eventual current symlink root. Impact: production filesystem/Nginx configuration; no database migration yet. Rollback: restore prior Nginx config/root and retain legacy checkout.
4. **Final production change window** — refresh verified backup, enforce write/worker/scheduler drain, deploy the exact approved artifact, run migrations only after staging evidence, atomically switch the symlink, reload Nginx/PHP workers, start bounded queue/scheduler topology and run HTTP/database/queue/scheduler/storage/business smoke checks. Impact: production application and schema. Rollback: pre-write symlink rollback where schema-compatible; otherwise forward fix or explicitly approved restore with declared data-loss window.

The next executable mutation is Batch 1 only. Batches 2–4 remain blocked until the preceding batch produces evidence and each receives separate explicit approval.

---

## Preparation update — release `af2cc816c811667bfc0ea1e51fdcff7a4d25a0db`

This update is preparation evidence only. No backup, snapshot, staging resource, restore, migration, deployment, import, service reload/restart or production write was performed.

### Release identity and local state

- Approved preparation base: `af2cc816c811667bfc0ea1e51fdcff7a4d25a0db`.
- The base commit contains the dependency remediation and previously green quality evidence recorded above.
- This preparation adds scripts, templates and governance locally, so a new reviewed clean commit/checksum will be required before staging. The base SHA must not be used to imply that these preparation artifacts are already deployed.

### Encrypted MySQL backup preparation

Prepared `scripts/operations/backup-database-encrypted.sh` with a connection-free default mode and explicit execute gates. The execute path uses transaction-consistent `mysqldump` with schema/data, triggers, routines, events, binary-safe data, gzip streaming and `age` recipient encryption. It persists no plaintext dump and emits a ciphertext SHA-256 plus secret-free manifest. Credentials must come from a mode-0400/0600 MySQL defaults file, not the process list.

Current read-only VPS preflight:

| Evidence | Current result |
|---|---|
| Target | `project-06b7a0c7-35a4-4282-b81 / laravel-server / asia-southeast1-a` |
| App / database | `/var/www/laravel`, production, `schema_admin` |
| Database size | approximately 89.83 MB from prior read-only inventory |
| Root free bytes | 10,893,938,688 |
| Root free inodes | 1,165,500 |
| Available RAM / swap | 265,699,328 bytes available; 4,107,042,816 bytes swap free |
| `mysqldump` | present, but MariaDB client 11.8.6 against MySQL 8.4 server: **rejected** |
| `age` | missing |
| `gzip` / SHA-256 | present |
| Encrypted destination | not selected |
| Off-host destination | not selected |
| Encryption-key custody | not approved |

The script now rejects a MariaDB dump client for this MySQL 8.4 source. Before backup execution, approve a compatible MySQL client delivery method and `age` installation or an equivalent reviewed, authenticated encryption implementation. With the current ~90 MB schema and ~10.9 GB free disk, capacity is ample under the runbook's two-estimated-dumps-plus-20% rule, but this does not satisfy tool, destination, access or restore gates.

Recommended off-host target: a dedicated, versioned, retention-locked Google Cloud Storage prefix in a project/bucket with separate backup IAM and no application runtime credentials. A new object path must be used, ciphertext uploaded only after local checksum, and destination integrity verified. The decrypt identity must remain off the application VM. Bucket creation, IAM, retention configuration and upload are all separate mutations requiring approval.

### Isolated Google Cloud staging plan and cost envelope

Preferred fidelity topology:

- one ephemeral `e2-medium` VM (2 vCPU, 4 GiB) in `asia-southeast1`, private address only, IAP administration;
- 30 GiB `pd-balanced` boot/data capacity;
- MySQL 8.4 matching production major version, PHP/runtime matching the release;
- staging database named with `_staging_restore` suffix;
- separate Redis/database credentials and queue namespace;
- mail, webhooks, payments, Supabase writes and every external mutation sink disabled or redirected to isolated test sinks;
- no route from staging credentials to production MySQL or production object writes;
- VM retained only through evidence review, then deleted under separate approval.

Planning cost, not a billing quote: [Google's current public general-purpose pricing](https://cloud.google.com/products/compute/pricing/general-purpose) shows `e2-medium` at about USD 0.03350571/hour in the displayed on-demand schedule (about USD 2.41 for 72 hours, USD 5.63 for seven days, or USD 24.46 for 730 hours) before Singapore SKU differences, disk, snapshots, operations, taxes and network. [Standard regional Cloud Storage](https://cloud.google.com/storage/pricing) is approximately USD 0.02/GiB-month from the published hourly rate; operations and network can add cost. Obtain a dated Singapore quote in Google Cloud Pricing Calculator immediately before approval. A 72-hour rehearsal budget envelope of USD 5–15 is reasonable for one VM, 30 GiB disk and small backup transfer, but only the calculator/billing SKU is authoritative.

Cloud SQL is an alternative when managed database operations are desired, but it costs more and differs from the current VM-local MySQL topology. The ephemeral VM option gives better migration/rollback fidelity for this rehearsal. A separate remote ClamAV host is not required merely to test migrations; if scanner integration is included, add a second e2-medium-class 4 GiB host and price it separately.

### Restore and migrations 105–127

Prepared `scripts/operations/restore-encrypted-to-staging.sh`. It checks ciphertext SHA-256, decrypts as a stream and refuses any environment other than staging or target name not ending `_staging_restore`. It does not create the database or resource.

Prepared `scripts/operations/staging-migration-rehearsal.sh`. Its default mode verifies exact release SHA and exact pending set count/boundaries without migrating. Execute mode requires matching database and SHA confirmations, records before/after migration inventories and wall-clock duration, then fails if any migration remains pending.

The exact pending set is 23 files:

`105` procurement goods return; `106` supplier credit; `107` vendor refund; `108` treasury opening balance; `109` treasury internal transfer; `110` transfer reversal lineage; `111` customer credit note; `112` customer refund; `113` CRM pipeline; `114` inventory governance; `115` fixed asset impairment/disposal; `116` fiscal year governance; `117` VAT foundation; `118` FX foundation; `119` AR-FX evidence; `120` AP-FX recognition; `121` AP-FX settlement; `122` inventory receipt FX; `123` return FX reversal; `124` supplier-credit FX; `125` supplier-refund FX; `126` maintenance part return; `127` maintenance closeout.

Static rollback review finds mostly new-table creation. Migrations `107`, `110`, `116`, `117` and `118` also alter existing tables/columns. Every `down()` deletes new data and several remove added columns; therefore `migrate:rollback` is not a production rollback strategy. Staging must prove old-code/new-schema read compatibility before cutover. Once new-version writes occur, rollback is an application forward-fix or an explicitly approved restore to the pre-change recovery point with an accepted data-loss window.

Required rehearsal measurements:

1. pre-restore and post-restore table/migration/selected domain counts;
2. restore duration and ciphertext/plain-stream checksum validation;
3. each migration start/end/duration plus total duration and lock/process-list observations;
4. row counts, FK/orphan checks, unique constraints, tenant/company boundaries and canonical Data Quality score;
5. focused cross-domain and full regression on the migrated clone;
6. old artifact read-only smoke test against migrated schema before any new write;
7. new artifact smoke test, queue/scheduler disabled first, then one bounded worker topology;
8. peak RSS, swap, disk and database growth;
9. rollback decision point and restoration timing from the same encrypted artifact.

### Dirty VPS preservation

Read-only verification still shows deployed SHA `ab47852de19c6e6fffe017e5005fa7c62ef0f464` and:

- modified `app/Support/EnvironmentSafetyGuard.php`, 3,355 bytes, SHA-256 `0283e4b6…23dda`;
- `.env.backup.20260916-193649`, 2,435 bytes, mode 0640, root-owned; contents not read and hash permission denied;
- `app/Support/EnvironmentSafetyGuard.php.backup.20260916_213141`, 1,730 bytes, SHA-256 `6ddd30ec…1e54`.

The modified guard is a production-only precursor of the newer fail-closed local implementation, but its exact bytes do not equal the release version. Preserve a secret-free patch, metadata and hash in the approved evidence location; do not merge it into the release. Preserve the two backup files into a root-only incident archive before retiring the legacy tree. Never print or copy the `.env.backup` into source/evidence. The current OS Login identity cannot read that root-owned file, so preservation requires a separately approved privileged operation.

### Atomic release topology and cutover

Target layout is now codified in the deployment runbook:

```text
/var/www/laraveldji/
├── releases/<release-id>/      immutable artifact
├── shared/.env                 protected configuration
├── shared/storage/             persistent Laravel storage
└── current -> releases/<id>    atomic symlink
```

Nginx must be validated with a candidate root of `/var/www/laraveldji/current/public`, then reloaded only in the approved window. Build Composer/Vite off-host, transfer an immutable checksum-verified artifact, link shared paths, run preflight and only approved migrations, warm caches, atomically switch `current`, restart the selected bounded workers gracefully and run health/smoke checks. The previous release remains intact. Rollback switches `current` back only while database compatibility remains proven.

### Remote ClamAV design

The current 964 MiB application VM must not host full ClamD. Preferred architecture is a separate private 4 GiB scanner VM with one ClamD worker, current signatures and no public ClamD port. Connectivity must use private VPC/firewall allowlisting; application requests use a tested presentation adapter with strict maximum size, timeout and concurrency, and fail closed on timeout, stale signatures, malware or transport failure. Content and credentials never enter logs. The existing application currently invokes a local `clamscan`-compatible process, so a remote adapter is still source work and must be implemented/tested before provisioning is useful.

### Local data import allowlist

Prepared `docs/templates/local-to-production-import-allowlist.yaml`. It requires explicit entity ownership, source tables/filter, natural keys, dependencies, tenant mappings, conflict policy, deterministic ID remapping, row counts, file manifests and owner approvals. It excludes passwords, sessions, tokens, direct user grants and demo/test data by default. This template is not executable and no local row is approved yet.

### Exact approvals still required

1. Approve installation/use of a MySQL 8.4-compatible dump client and `age` on the verified VPS, without changing database/service state.
2. Select and approve encryption recipient/key custodian, protected on-host ciphertext directory, off-host GCS bucket/prefix, IAM roles and retention.
3. Approve exactly one encrypted production logical backup and exactly one ciphertext upload/checksum verification.
4. Approve provisioning one isolated staging `e2-medium` VM plus 30 GiB disk in `asia-southeast1`, after reviewing the dated Pricing Calculator quote.
5. Approve transfer/decryption/restore of that exact production backup into the new `_staging_restore` database.
6. Approve running migrations `105`–`127` on staging only and the staged regression/performance/compatibility matrix.
7. Approve privileged preservation of the three dirty VPS files into protected evidence storage without exposing `.env.backup` contents.
8. After staging passes, approve creation of `/var/www/laraveldji/{releases,shared,current}`, artifact transfer, protected shared-path migration, Nginx config test/root change and controlled reload.
9. Choose ClamAV direction: implement/test the remote adapter and provision separate 4 GiB capacity, or resize the application host after measured staging evidence.
10. Complete and approve the import allowlist template; without it, import remains forbidden.
11. Separately approve the final production change window, fresh backup, queue/scheduler drain, 23 migrations, symlink cutover, worker start and rollback observation window.

Classification remains `PREPARATION_COMPLETE / PRODUCTION_MUTATIONS_APPROVAL_REQUIRED`.
