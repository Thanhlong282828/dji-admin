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
