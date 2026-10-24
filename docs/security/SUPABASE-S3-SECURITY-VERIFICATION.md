# Supabase S3 Security Verification

Status date: 2026-10-13
Repository: `/Users/lahoathanh/Desktop/laraveldji/admin`
Verified test database: `schema_admin_testing`
Operating database: `schema_admin` (read-only)
Cloud mutation: prohibited by this verification

## 1. Security architecture

Private document access follows this server-side sequence:

`Laravel session → route permission → company access / Policy → tenant-scoped canonical record → Flysystem private disk → bounded signed URL or private local response`

Supabase is an S3-compatible object provider, not an authorization provider for LaravelDji. S3 credentials remain server-side and must not be placed in `VITE_*`, HTML, JavaScript, logs or database records. Storage RLS and a private bucket do not replace Laravel permission, Policy/Gate and tenant checks because the S3 credential is privileged.

MySQL remains the canonical source for company, employee, document, version, permission and audit metadata. The browser never selects an object key. Object names are random UUIDs under server-generated tenant prefixes.

## 2. Owned endpoints and services

### Enterprise Document

| Method | Route | Permission | Owner implementation |
|---|---|---|---|
| GET | `admin.documents.index` | `document.view` | `EnterpriseDocumentController` → query bus |
| GET | `admin.documents.show` | `document.view` | `EnterpriseDocumentController` → query bus |
| POST | `admin.documents.store` | `document.create` | command bus → `EnterpriseDocumentService::create` |
| POST | `admin.documents.versions.store` | `document.upload` | command bus → `EnterpriseDocumentService::upload` |
| POST | `admin.documents.archive` | `document.archive` | command bus → `EnterpriseDocumentService::archive` |
| GET | `admin.documents.download` | `document.download` | `EnterpriseDocumentService::download` |

Tenant enforcement is performed by `BranchAccessService::company`, company-scoped document/version queries and subject validation before storage access.

### Employee private documents

| Method | Route | Permission | Owner implementation |
|---|---|---|---|
| POST | `admin.employees.documents.store` | `employee-document.upload` | `EmployeeDocumentPolicy` → `EmployeeDocumentService::upload` |
| GET | `admin.employee-documents.download` | `employee-document.download` | policy → tenant-scoped lookup → `downloadLocation` |
| DELETE | `admin.employee-documents.archive` | `employee-document.archive` | policy → tenant-scoped lookup → archive |

The Employee service resolves the employee through both `employee_id` and `company_id`. Downloads and archives resolve documents through their employee's company. Existing metadata without a storage-disk marker may use the private local fallback; new Supabase metadata records the selected disk in `access_policy`.

There is no public preview endpoint. Supabase download is an authorized redirect to a short-lived provider URL. Physical delete is not exposed as a browser endpoint; archive changes canonical metadata only.

## 3. Storage contract

- Enterprise prefix: `enterprise-documents/{company_id}/{document_id}/{uuid}.{extension}`.
- Employee prefix: `employees/{company_id}/{employee_id}/documents/{uuid}.{extension}`.
- Provider allow-list: `local`, `supabase`.
- Supabase disk: private visibility, path-style S3 endpoint, exceptions enabled.
- Signed URL lifetime: bounded to 1–60 minutes; configured default 5 minutes.
- Signed response requests attachment disposition, safe content type and `Cache-Control: private, no-store`.
- Local response adds `no-store` and `X-Content-Type-Options: nosniff`.
- Upload validates size, allowed type and detected MIME/signature, computes SHA-256 and scans for malware.
- Scanner/provider/database failure fails closed; compensating cleanup prevents a newly uploaded orphan object.
- Original filenames are metadata only and never become object paths.

## 4. Command safety legend

| Class | Meaning |
|---|---|
| READ_ONLY | Reads source/config/runtime metadata without mutation |
| TEST_MUTATION | Mutates only `schema_admin_testing` and fake local storage through test isolation |
| BUILD_LOCAL | Writes generated local cache/build artifacts only |
| LIVE_MUTATION | Would alter operating DB or cloud; prohibited unless separately authorized |

Every command below must be run from `/Users/lahoathanh/Desktop/laraveldji/admin`.

## 5. Repeatable verification commands

### 5.1 Repository and configuration audit

```bash
git status --short
rg -n "SUPABASE_STORAGE|DOCUMENT_STORAGE_DISK|EMPLOYEE_DOCUMENT_STORAGE_DISK" .env.example config Modules docs
rg -n "env\\(" app Modules --glob '!**/config/*.php'
```

- Class: READ_ONLY.
- Production-safe: yes; do not print `.env` values.
- Expected: canonical variables are mapped in config; application code uses `config()`, not direct `env()`.
- On failure: stop and audit the consumer before editing credentials.

### 5.2 Database hard gate

```bash
APP_ENV=testing DB_CONNECTION=mysql DB_DATABASE=schema_admin_testing php artisan env
APP_ENV=testing DB_CONNECTION=mysql DB_DATABASE=schema_admin_testing php artisan tinker --execute="echo DB::connection()->getDatabaseName();"
```

- Class: READ_ONLY.
- Production-safe: yes only as written; the expected database is `schema_admin_testing`.
- Expected: environment `testing`, actual PDO database `schema_admin_testing`.
- On failure: do not run PHPUnit or any destructive command.

### 5.3 Tenant isolation and Storage fake regression

```bash
APP_ENV=testing DB_CONNECTION=mysql DB_DATABASE=schema_admin_testing php artisan test \
  tests/Feature/EnterpriseDocumentManagementTest.php \
  tests/Feature/Employee/EmployeeCompletionTest.php \
  --stop-on-failure
```

- Class: TEST_MUTATION.
- Production-safe: no. Run only on the confirmed testing database.
- Storage behavior: Laravel `Storage::fake('local')` and `Storage::fake('supabase')`; no live object request.
- Expected: company access, permissions, forged IDs, guest access, upload, download, archive, missing objects and signed-URL ordering pass.
- On failure: retain the failure evidence, inspect the backend tenant boundary and do not substitute UI hiding for authorization.

### 5.4 Security and credential exposure

```bash
APP_ENV=testing DB_CONNECTION=mysql DB_DATABASE=schema_admin_testing php artisan test tests/Security/SecurityPolicyTest.php --stop-on-failure
npm run build
find public/build -type f -name '*.map'
rg -n -i "SUPABASE_(SERVICE_ROLE|STORAGE_SECRET|ACCESS_TOKEN|ANON_KEY)|AWS_SECRET_ACCESS_KEY" public/build --glob '*.{js,css,json}'
```

- Classes: TEST_MUTATION for PHPUnit; BUILD_LOCAL for Vite; READ_ONLY for inspection.
- Production-safe: do not run the PHPUnit command against production; build/inspection do not contact Supabase.
- Expected: tests pass, no `.map`, no sensitive browser-bundle hit.
- On failure: remove frontend secret exposure, rotate any actually exposed credential through deployment governance, then rerun.

### 5.5 Static regression

```bash
php artisan optimize:clear
php artisan view:cache
php artisan route:list --except-vendor
vendor/bin/phpstan analyse --memory-limit=2G --no-progress
vendor/bin/pint --test
git diff --check
```

- Class: BUILD_LOCAL/READ_ONLY; writes only local caches.
- Production-safe: use in CI/development, not as an operating database test.
- Expected: Blade/routes/PHPStan/Pint/diff checks pass.
- On failure: make the smallest scoped correction; do not suppress diagnostics or weaken baselines.

### 5.6 Read-only S3 configuration probe

A live probe is optional and requires explicit authorization plus an existing redacted project script or AWS-compatible client. Allowed operations are only `HeadBucket`, bounded `ListObjectsV2(MaxKeys=1)` and anonymous-denial checks. Never print an object key, signed URL or credential.

- Class: READ_ONLY cloud request.
- Production-safe: only with explicit approval and bounded read-only calls.
- Expected: authenticated head/list succeeds and anonymous access is denied.
- On failure: classify `NOT_VERIFIED`, `EXPIRED_OR_REVOKED`, `INSUFFICIENT_PERMISSION` or `WRONG_RESOURCE_SCOPE`; do not rotate credentials automatically.
- Prohibited: upload, overwrite, download business content, delete, bucket/policy/RLS change.

## 6. PASS / FAIL / BLOCKED criteria

### PASS by automated test

Requires a current successful run on confirmed `schema_admin_testing` using Storage fake/mock. It proves Laravel authorization, tenant queries, object-key construction and adapter interaction, but not live cloud behavior.

### PASS by live integration

Requires a separately authorized, non-operating/disposable resource for upload/download/delete tests, or a specifically authorized read-only production probe for only the properties it observes. Fake/mock results must never be relabeled live.

### FAIL

Any unauthorized request obtains metadata/content/a signed URL, a forged tenant/subject ID is accepted, an object key is client-controlled, credentials appear in browser artifacts, or rollback leaves a new orphan object.

### BLOCKED

The actual database cannot be proven safe, required fake/mock infrastructure is unavailable, or live verification lacks separately authorized credentials/resource. Stop rather than falling back to operating data.

## 7. Actual evidence — 2026-10-13

Environment gate:

- `APP_ENV=testing`: verified.
- Configured/actual database `schema_admin_testing`: verified.
- Live cloud upload/download/delete: not run.

Focused Document + Employee run after adding explicit boundary cases:

- Tests: 16.
- Passed: 16.
- Assertions: 105.
- Failures/errors: 0.

Combined Document + Employee + SecurityPolicy regression:

- Tests: 36.
- Passed: 36.
- Assertions: 134.
- Failures/errors: 0.

Phase 2 transport-failure focused run:

- Tests: 18.
- Passed: 18.
- Assertions: 113.
- Failures/errors: 0.
- Fault injection: S3-compatible filesystem `putFileAs` throws before persistence; Enterprise Document rolls back its database row and attempts scoped directory cleanup, while Employee Document creates no metadata.

Verified by automated tests:

- authorized same-company upload and download;
- guest and missing-permission denial;
- Company A cannot resolve Company B document/version;
- forged company, employee, subject and document identifiers are rejected;
- unauthorized/missing-object paths generate no signed URL;
- archive is tenant-scoped;
- private random tenant prefixes for Enterprise and Employee objects;
- fake Supabase upload existence and temporary URL redirect;
- missing provider configuration, invalid MIME/signature, excessive size and unavailable malware scanner fail closed;
- no partial database row after rejected upload;
- local legacy compatibility.

Previously completed repository gates in the same working tree:

- Full PHPUnit: 916 tests, 915 passed, 1 skipped, 34,013 assertions, exit 0.
- PHPStan: 0 errors.
- Pint, Blade, Vite, routes and `git diff --check`: passed.
- Public source maps: 0.
- Sensitive bundle-name hits: 0.

## 8. Live Supabase evidence and limitation

Manually supplied configuration evidence states that bucket `laraveldji-media` is private, RLS is enabled for `storage.objects` and `storage.buckets`, four `laraveldji_deny_direct_*` policies exist, and three PDF objects exist. This is cloud-configuration evidence only; it is not Laravel authorization evidence.

On 2026-10-14, the user supplied results from read-only SQL executed directly in the production Supabase project:

- `storage.objects` and `storage.buckets` were included in the live `pg_policies` inventory;
- the complete result contained exactly four policies, all on `storage.objects` and none on `storage.buckets`;
- no additional permissive policy was found;
- SELECT, INSERT, UPDATE and DELETE are represented once each;
- the applicable roles are `anon` and `authenticated`;
- every policy expression uses `bucket_id = 'laraveldji-media' AND false`;
- the bucket is private;
- RLS for `storage.objects` and `storage.buckets` was confirmed enabled by the user's earlier live catalog queries.

Evidence classification:

| Control | Status | Evidence boundary |
|---|---|---|
| RLS enabled | `PASS_USER_VERIFIED_LIVE_SQL` | User-provided production catalog output; not independently executed by Codex |
| Policy inventory | `PASS_USER_VERIFIED_LIVE_SQL` | Exactly four `storage.objects` rows; no other policy in the queried inventory |
| Other permissive-policy conflict | `PASS_USER_VERIFIED_LIVE_SQL` | No other permissive policy was present in that live inventory |
| Effective authorization | `PASS_WITH_PRIVILEGED_BYPASS_PATHS` | Completed later on 2026-10-14; see section 13 and the live runbook |

The four false permissive expressions grant no row to `anon` or `authenticated` by themselves. This does not prove absolute denial for every access path. A table owner, superuser, `BYPASSRLS` role, `service_role`, privileged function or server-side S3 credential may bypass or operate outside user-level RLS. Laravel authorization and tenant scoping therefore remain mandatory.

The Phase 2 bounded ListObjects probe against `security-verification/read-only-connectivity-probe` succeeded and returned zero items without emitting object names or mutating cloud state. The later authorized live lifecycle is recorded in section 13 and failed cleanly at upload.

Complete PostgreSQL catalog SQL, effective-policy interpretation and the proposed live object lifecycle are recorded in `SUPABASE-S3-LIVE-VERIFICATION-RUNBOOK.md`.

## 9. Findings and remediation

No backend authorization vulnerability reproduced. Existing tenant scope and permission boundaries were retained. The evidence gap was incomplete explicit regression coverage, corrected by tests for guest access, forged tenant/employee/document identifiers, cross-tenant archive and missing storage objects before signed URL generation.

No RLS, bucket, credential or production database change was made. PostgreSQL/RLS evidence remains governed by `SUPABASE-RLS-AUDIT-REPORT.md`; the effective privilege and bypass inventory was subsequently completed and is recorded in section 13.

## 10. Maintenance rule

Update this document whenever a document endpoint, storage adapter, permission, object path, signed URL policy, malware scan, rollback behavior or relevant test command changes. A future session must rerun the current safe tests and record its own evidence; historical PASS is not a substitute for a current run.

## 11. Persistent Codex continuation checkpoint

Future Codex sessions must start here and must not repeat completed implementation unless current evidence shows a defect.

### Closed and verified

- Supabase is used through Laravel Flysystem's private S3-compatible disk; credentials stay server-side.
- Enterprise Document and Employee Document enforce Laravel permission plus company/subject scope before storage access.
- Fake/mock upload, download, archive, missing-object handling, forged IDs and signed-URL ordering are covered.
- S3 transport fault injection proves no metadata remains; Enterprise Document attempts scoped compensating cleanup.
- Current bounded live ListObjects connectivity is verified read-only.
- User-provided production SQL on 2026-10-14 verifies RLS enabled and exactly four false PERMISSIVE policies on `storage.objects`, with no other permissive policy in the queried inventory.
- Public source maps and sensitive browser-bundle credential names were absent in the latest build scan.

### Current evidence counts

- Phase 2 combined regression: 38 tests, 38 passed, 142 assertions.
- Latest standalone SecurityPolicy run: 20 tests, 20 passed, 29 assertions.
- Latest authoritative full suite before the documentation-only RLS checkpoint: 916 tests, 915 passed, 1 skipped, 34,013 assertions, exit code 0.

Do not present historical counts as a new PASS after future production-code changes; rerun the appropriate current tests.

### Remaining blockers

1. Live S3 upload failed before persistence; content download, checksum comparison, signed URL and anonymous-object denial remain unverified.
2. A new live retry requires diagnosis of PutObject authorization/endpoint configuration and new explicit approval.
3. Server-side S3 credentials remain a privileged bypass path outside user-JWT Storage RLS; Laravel authorization must never be removed.

### Mandatory next safe action

Do not repeat the completed catalog audit. Diagnose PutObject authorization/endpoint configuration without exposing secrets, and do not retry a live write without new explicit approval.

If code related to storage or authorization changes, first verify:

```bash
APP_ENV=testing DB_CONNECTION=mysql DB_DATABASE=schema_admin_testing php artisan env
APP_ENV=testing DB_CONNECTION=mysql DB_DATABASE=schema_admin_testing php artisan tinker --execute='echo DB::connection()->getDatabaseName();'
```

Then run:

```bash
APP_ENV=testing DB_CONNECTION=mysql DB_DATABASE=schema_admin_testing php artisan test \
  tests/Feature/EnterpriseDocumentManagementTest.php \
  tests/Feature/Employee/EmployeeCompletionTest.php \
  tests/Security/SecurityPolicyTest.php \
  --stop-on-failure
```

After relevant code changes, run PHPStan, Pint, Blade compilation, Vite, routes, bundle/source-map scans and `git diff --check`. Never run these tests against `schema_admin`.

### Approval boundary

Do not automatically:

- upload, overwrite, content-download or delete a live object;
- change RLS, grants, bucket settings or credentials;
- migrate, seed, truncate or test against `schema_admin`;
- reveal access keys, object keys, signed URLs or raw provider exceptions.

The optional live object lifecycle remains a separate approval gate defined in `SUPABASE-S3-LIVE-VERIFICATION-RUNBOOK.md`.

## 12. Phase 3 Dashboard checkpoint

On 2026-10-14, Codex independently inspected the authenticated Supabase Dashboard in read-only mode at Storage → Policies. The `laraveldji-media` bucket displayed exactly four policies named `laraveldji_deny_direct_select`, `laraveldji_deny_direct_insert`, `laraveldji_deny_direct_update` and `laraveldji_deny_direct_delete`, each targeting `anon` and `authenticated`. The Dashboard displayed no other policy under `storage.objects` and no policy under `storage.buckets`.

No Dashboard mutation control was used. This confirms policy visibility and agrees with the user's live SQL evidence; it does not independently expose the full policy expressions or close the GRANT/owner/BYPASSRLS/service-role/SECURITY-DEFINER audit.

The consolidated Phase 3 evidence and PASS/BLOCKED matrix are stored in `SUPABASE-S3-PHASE3-FINAL-AUDIT.md`.

## 13. Effective authorization and live lifecycle checkpoint

On 2026-10-14, Codex completed the remaining read-only PostgreSQL catalog audit through Supabase SQL Editor. `anon` and `authenticated` have table DML privileges but are non-superuser, non-BYPASSRLS roles, so their row access remains governed by the verified RLS policy set. `service_role` has `BYPASSRLS=true`. Both storage relations are owned by `supabase_storage_admin`, have RLS enabled but not forced, so relation-owner access is an expected bypass path. Server-side S3 credentials remain a separate privileged path.

The `storage` schema has no SECURITY DEFINER function. The sole reviewed SECURITY DEFINER function in `public`, `rls_auto_enable()`, has a fixed `pg_catalog` search path and is attached to the enabled RLS-enforcement DDL event trigger. See the live runbook for the full non-secret findings.

The user authorized a bounded live S3 object lifecycle. Both upload attempts failed at PutObject before an object existed. Exact-key cleanup and post-cleanup absence checks passed. Therefore upload, authenticated content download, checksum match, signed URL and anonymous-object denial are not verified and must remain FAIL/BLOCKED rather than PASS.

Follow-up local RCA proved that the canonical Flysystem call was not ACL-free: the adapter automatically generated `PutObject` with `ACL=private`, while Supabase documents PutObject ACL/grant headers as unsupported. See `SUPABASE-S3-PUTOBJECT-RCA.md`. No live request or cloud mutation occurred during that RCA, and the original provider HTTP status/error code/request ID were not retained.

The subsequent source repair registers an ACL-neutral custom driver only for the `supabase` disk and preserves the stock S3/local drivers. AWS SDK transport-boundary mocks prove PutObject string/stream writes contain no ACL/grant parameters; GetObject, HeadObject and DeleteObject remain functional. Sequential Document, Employee and Security regressions prove tenant paths, authorization, malware scanning and rollback remain intact. Local adapter status is PASS, but live upload/download/checksum/signed URL remain NOT VERIFIED until a separately approved lifecycle runs.

One subsequently approved ACL-neutral lifecycle was executed on 2026-10-14. Its only key was absent before upload. PutObject failed with HTTP 415 / `InvalidMimeType` for `text/plain; charset=utf-8`; no object persisted. Exact-key cleanup and post-cleanup absence passed. No retry was attempted. Authenticated download/checksum, signed URL and anonymous-object denial remain NOT VERIFIED. Detailed redacted hashes are recorded in the runbook and RCA.

### PDF allowlist live closure

A later, separately approved single-key lifecycle followed a read-only bucket inspection and a fresh transport mock. The bucket allowlist was verified as `image/jpeg`, `image/png`, `image/webp`, `application/pdf`; `text/plain; charset=utf-8` was outside it. A valid 431-byte PDF was sent with exact `application/pdf` and no ACL/grant parameters.

The single live key was absent before upload. PutObject, authenticated download/checksum, signed URL download/checksum, anonymous denial (HTTP 403), exact-key deletion and post-delete absence all passed. No retry or second key was created. No existing object or cloud/database configuration was modified. Redacted hashes are recorded in the live runbook.

Classification for the bounded PDF lifecycle: `LIVE_S3_END_TO_END_PASS_PDF_ALLOWLIST_VERIFIED`.
# Final hardening evidence (2026-10-14)

Application-level hardening now includes fail-closed ClamAV adapters with configurable binaries/timeouts, redacted operation telemetry, alert definitions, tenant-scoped dry-run reconciliation, process-level upload concurrency evidence and fault injection. These controls do not change the established authorization model: privileged server S3 credentials bypass user-JWT RLS, so Laravel permission/policy and tenant scope remain mandatory.

Production ClamAV execution and external alert delivery are not inferred from local tests and remain `NOT_VERIFIED` / `BLOCKED`. No bucket, policy, RLS, grant, credential, existing object or production database was changed.
