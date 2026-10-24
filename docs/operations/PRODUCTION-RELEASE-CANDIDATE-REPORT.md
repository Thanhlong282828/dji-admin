# LaravelDji Release Candidate Evidence

## Executive result

Evidence captured 2026-10-14/15 for source revision `rc-20261014T164913Z-898c13d98681`.

- Release artifact: `PREPARED FOR REVIEW` after this report is sealed.
- Deployment readiness: `BLOCKED`.
- Production deployment: `NOT EXECUTED`.

The candidate is a content snapshot of the complete dirty working tree, not a Git commit and not an approval to deploy.

## Provenance and backup gate

| Item | Evidence |
|---|---|
| Project | `/Users/lahoathanh/Desktop/laraveldji/admin` |
| Branch | `codex/erp-finance-tax-runtime-hardening` |
| Git HEAD ancestry | `327237372aa5c9eeaca42736b711c94ace58a396` |
| Working-tree entries before governance/report additions | 87 |
| Submodules | none |
| Modules | 47 |
| Routes | 753 total / 748 named by architecture audit |
| Artisan commands | 282 |
| Migration files | 127 |
| Composer lock | `0e34e82640df7530ba8d4472046c156d22c57d87f435242181e09c1e81b1141b` |
| npm lock | `8b8c3e46334186621ca6233dd80dd1f3216fbf91f3197e7f2aee775edefefb51` |

The verified local backup at `/Users/lahoathanh/Documents/Znews like newspaper/backups/laraveldji/20261014T143044Z` was checked against all 4,644 files in its source manifest: zero missing and zero changed before this phase. Governance and evidence files added by this phase are included in the release snapshot itself; the older recovery backup remains immutable and was not deleted.

## Artifact contract

Planned canonical location:

`/Users/lahoathanh/Documents/Znews like newspaper/releases/laraveldji/rc-20261014T164913Z-898c13d98681`

The directory contains a secret-screened source archive, file-level SHA-256 manifest, artifact checksum, provenance, build instructions and inventories. It excludes `.env*` except `.env.example`, credentials/private keys, database dumps, persistent uploads, runtime logs, `vendor`, `node_modules` and build caches. The external manifest is authoritative because embedding an archive checksum inside the archive would be self-referential.

## Database and migration evidence

Fresh read-only inventories show:

| Dimension | Local | Production |
|---|---:|---:|
| Actual database | `schema_admin` | `schema_admin` |
| MySQL | 9.6.0 | 8.4.10-google |
| Tables | 355 | 336 |
| Applied migration records | 113 | 104 |
| Source migration files | 127 | 127 in candidate |

The production migration set is a strict prefix of local applied migrations. Production lacks source migrations 105–127 (23 files). Local has applied only 9 of these—105–110, 113, 118 and 119—and still lacks 14 source migrations. Therefore neither `127 - 113` nor the local schema can be used as a production migration rehearsal.

Schema comparison found 336 common tables, 19 local-only tables, no production-only tables, and no foreign-key-definition difference among common tables. The only common table with column/index drift is `companies`: local contains `functional_currency_code` and `companies_functional_currency_idx`, production does not. Column ordinal shifts are consequential only as evidence of that added column.

All 23 production-pending migrations require manual review; the pending range is classified caution by the static scanner. Migration execution and rollback feasibility remain `NOT EXECUTED` because no isolated staging restore of production exists.

## Architecture and quality gates

| Gate | Result |
|---|---|
| Full PHPUnit | 938 tests; 937 passed; 1 skipped; 34,206 assertions; exit 0; 1,407.278 s (shell 1,410 s) |
| PHPStan | PASS, 0 errors |
| Pint | PASS |
| Blade compile/cache/clear | PASS |
| Vite production build | PASS |
| Public source maps | 0 `.map` files; `sourcemap: false` |
| Routes/commands/scheduler | PASS; inventories captured |
| OpenAPI generation | PASS |
| Architecture audit | PASS: 47 modules, no cycles, undeclared edges, layer/CQRS/external-boundary violations |
| Data Quality | 221/221 passed, 0 failed, 0 warnings, score 100 on `schema_admin_testing` |
| Tracked secret scan | PASS |
| npm runtime audit | PASS, 0 vulnerabilities |
| Composer audit | **FAIL/BLOCKER**: 4 advisories across 3 packages |
| `git diff --check` | rerun after artifact/report sealing |

Composer advisories at capture:

- `laravel/framework`: one low-severity advisory, affected below 13.30.0;
- `league/commonmark`: one high and one medium advisory, installed range affected through 2.10.1;
- `phpseclib/phpseclib`: one medium advisory, fixed in the applicable patched series.

Dependencies were not changed automatically. A reviewed lockfile update and complete regression run are required before deployment approval.

## VPS compatibility

Read-only runtime evidence confirms 2 vCPU, about 964 MiB RAM, active swap, 20 GiB root disk, Nginx/PHP-FPM/Redis/one bounded `queue:work` worker and Google Ops Agent. Do not build Composer/Vite, run PHPUnit/PHPStan or enable Horizon on this host.

The release must be built off-host and deployed as a versioned immutable directory with an atomic `current` switch. Keep one bounded worker initially. ClamAV is absent; do not install a full local scanner on this 1 GiB VM without measured staging evidence and approval. Prefer a tested private remote scanner or resize first. Document uploads must remain fail-closed.

## Staging and deployment blockers

1. No approved encrypted production backup or restore rehearsal.
2. No isolated staging environment with production-like restored data.
3. No execution evidence for migrations 105–127 against restored production.
4. Composer security advisories remain open.
5. Source is a dirty-tree content snapshot, not a reviewed clean Git commit.
6. Production checkout is dirty and contains an unreviewed `.env.backup...` file name.
7. Production lacks atomic release topology.
8. ClamAV/runtime upload scanning is not ready on the VPS.
9. Supabase/reconciliation/monitoring changes are not deployed or staged.
10. Local-to-production record collision mapping and import rehearsal are not verified.

## Classification

- `LOCAL_BACKUP_VERIFIED`
- `RELEASE_CANDIDATE_PREPARED`
- `DEPLOYMENT_READY = NO`
- `PRODUCTION_BACKUP_APPROVAL_REQUIRED`
- `PRODUCTION_DEPLOYMENT_NOT_EXECUTED`
