# Supabase S3 final integration and production-readiness checkpoint

Date: 2026-10-14

## Executive status

Classification: `SUPABASE_S3_PRODUCTION_READINESS_PARTIAL`

## Final hardening continuation (2026-10-14)

Implemented locally without production mutation:

- Configurable, bounded ClamAV invocation for both document pipelines; CLEAN, INFECTED, ERROR, TIMEOUT and UNAVAILABLE are tested. ERROR/TIMEOUT/UNAVAILABLE remain fail-closed. Production PHP-worker ClamAV availability is still `NOT_VERIFIED`.
- Redacted storage telemetry for scanner/upload/head/signed URL/cleanup/reconciliation operations, including safe tenant hash, outcome, classification and duration. The observability snapshot and alert evaluator now expose the corresponding signals. External live alert delivery remains `BLOCKED` without a verified alert destination.
- `documents:storage-reconcile` is company-required, dry-run-only, bounded by item count/time, and emits hashes rather than object keys. Fake-storage tests prove missing/orphan detection and tenant boundary without deletion.
- A real two-process MySQL race proves two simultaneous uploads to one Enterprise Document serialize into immutable versions 1 and 2 with distinct keys and no overwrite. The current document API has no canonical idempotency key, so duplicate-key idempotency and deterministic same-key collision remain an explicit architecture gap rather than a claimed PASS.
- Fault injection proves transport rollback, cleanup failure telemetry without masking the original exception, missing-object fail-closed behavior, signed URL provider failure without fallback, and checksum drift detection.

Safe commands and incident response are recorded in `docs/runbooks/supabase-storage-failure.md`. No additional live PutObject/DeleteObject was performed.

### Verification result

| Gate | Result |
|---|---|
| Focused hardening regression | PASS — 57 tests, 257 assertions |
| Real MySQL process race | PASS — 2 independent PHP workers, 1 test, 10 assertions |
| Full PHPUnit | PASS — 935 tests, 934 passed, 1 skipped, 34,165 assertions, 1,487.183 seconds |
| PHPStan | PASS — 0 errors |
| Pint | PASS |
| Blade compile | PASS |
| Vite | PASS |
| Routes | PASS — 708 routes |
| Frontend secret-name scan | PASS — no matches in `resources` or public source outside build output |
| Public source maps | PASS — no `.map` / `.map.gz` in build output |
| `git diff --check` | PASS |
| Local ClamAV runtime | MISSING |
| Production ClamAV runtime | NOT VERIFIED |
| External alert delivery | BLOCKED — no verified destination/delivery evidence |

Final classification remains `SUPABASE_S3_PRODUCTION_READINESS_PARTIAL`. All safe source/testing work in this phase is complete, but local test success cannot prove the production PHP worker has ClamAV or that an external monitoring backend delivered an alert. The absence of a canonical document-upload idempotency key also prevents a claim of duplicate-idempotency PASS. No production database/cloud/object mutation occurred.

The ACL-neutral transport and the allowlisted PDF lifecycle are verified. Enterprise Documents and Employee Documents now enforce the verified production bucket MIME contract before upload, send an exact detected `ContentType`, preserve tenant-scoped random object keys, store immutable checksum/driver metadata, and authorize every signed download before URL generation.

This checkpoint does not claim complete production readiness. ClamAV availability on the deployment host, operational metrics/alerts for storage failures, a reconciliation command for orphan/checksum drift, and process-level collision/concurrency evidence remain unverified.

No new live object lifecycle was run in this phase. The previously approved PDF lifecycle in `SUPABASE-S3-LIVE-VERIFICATION-RUNBOOK.md` remains the sole current live proof.

## Canonical architecture

### Enterprise Documents

`HTTP route + document permission -> EnterpriseDocumentController -> CommandBus/QueryBus -> handler -> EnterpriseDocumentService -> Laravel supabase disk -> SupabaseAclNeutralFilesystemFactory -> private bucket`

Metadata is stored in `enterprise_documents` and immutable `enterprise_document_versions`. The object prefix is `enterprise-documents/{company_id}/{document_id}/{uuid}.{extension}`. Signed URLs are generated only after company scope, permission, metadata driver allowlist and object existence checks.

### Employee Documents

`HTTP route + EmployeeDocumentPolicy -> EmployeeDocumentController -> EmployeeDocumentService -> Laravel supabase disk -> ACL-neutral adapter -> private bucket`

Metadata is stored in `employee_documents`. The object prefix is `employees/{company_id}/{employee_id}/documents/{uuid}.{extension}`. New rows record their storage disk. A recorded disk is authoritative and no longer silently falls back to another provider. Legacy rows without a recorded disk retain the bounded configured/local compatibility lookup.

## Driver and provider contract

- Only disk `supabase` resolves driver `supabase-s3`.
- AWS SDK build middleware removes `ACL` and all `Grant*` parameters before transport.
- Stock `s3`, R2, Cloudflare Images and local disks are unchanged.
- Supabase remains path-style and private; server credentials never enter frontend assets.
- Signed URL lifetime is bounded to 1–60 minutes, default 5.
- Historical live proof verified PDF upload, authenticated checksum, signed URL checksum, anonymous HTTP 403, exact-key delete and final absence.

## MIME and upload contract

The verified production bucket allowlist is:

- `application/pdf`
- `image/jpeg`
- `image/png`
- `image/webp`

When the selected disk is Supabase, both document services reject every other MIME before object persistence. MIME is determined from file content with `finfo`, checked against the filename extension where Enterprise Documents support legacy local types, and passed to storage as exact `ContentType` without a charset parameter. Empty and files larger than 10 MiB are rejected.

Local Enterprise Document storage retains legacy Word/Excel/CSV/TXT support. That compatibility must not be interpreted as Supabase bucket support. Employee local storage retains DOCX compatibility; Supabase Employee Documents do not.

## Security invariants

- Bucket is private; anonymous direct access remains denied.
- Laravel authentication, permission/policy and company/employee scope are mandatory because S3 credentials are a privileged server-side path outside user-JWT RLS.
- Object keys contain tenant identifiers and a UUID, never the original filename.
- Downloads do not accept caller-controlled disk or object keys.
- Missing object returns 404 before signed URL generation.
- Malware scan occurs before cloud upload; infected files are rejected and scanner failure returns 503.
- Production config selects ClamAV; null scanner fails closed outside local/testing.
- DB failure after upload triggers exact-object cleanup; create failure triggers tenant/document directory cleanup.
- Metadata stores SHA-256, detected MIME, byte count, scan status and storage driver; signed URLs and credentials are never persisted.

## Current test evidence

Executed sequentially on configured and actual PDO database `schema_admin_testing`:

| Gate | Result |
|---|---|
| Enterprise Document | PASS — 7 tests, 52 assertions |
| Employee completion/document | PASS — 13 tests, 70 assertions |
| ACL-neutral adapter + Security Policy | PASS — 27 tests, 55 assertions |
| PHPStan | PASS — 0 errors |
| Pint (affected files) | PASS |
| Blade compile | PASS |
| Routes | PASS — 712 output lines including header/footer |
| Vite | PASS |
| Frontend sensitive credential-name scan | PASS — no match outside ignored build output |
| Public sourcemap config | PASS — `sourcemap: false` |
| `git diff --check` | PASS |
| Full PHPUnit | PASS — 929 tests, 928 passed, 1 skipped, 34,087 assertions, 1,442.158 seconds |

Sanitized runtime configuration inspection reported the Supabase driver, endpoint, region, bucket, access key, secret key, Enterprise Document disk and Employee Document disk as `PRESENT`; no value was printed. `clamscan` is `MISSING` on the local development host. This does not prove its state on production and reinforces the malware-runtime deployment gate below.

The first combined feature invocation overlapped with an earlier still-running test command and caused concurrent `RefreshDatabase` migration collisions. Only `schema_admin_testing` was affected. It was restored with canonical `migrate:fresh`, after confirming the target database, and both feature suites were rerun strictly sequentially to the PASS results above. This is not counted as an application regression.

## Operations and monitoring gap

Existing activity logs provide document lifecycle evidence without object URLs or credentials. Provider exceptions are surfaced through Laravel's reporting path. However, there is no dedicated storage metric set or alert for upload failure rate, signed URL failures, latency, orphan cleanup failures, MIME rejection trends, or storage capacity. There is also no canonical dry-run reconciliation command comparing DB metadata to provider existence/checksum.

Required before `VERIFIED`:

1. Verify ClamAV is installed, updated and executable by the production PHP worker; run a safe EICAR-equivalent deployment validation under the approved security procedure.
2. Add redacted storage metrics and alert thresholds without logging keys, URLs, payloads or credentials.
3. Add a tenant-aware, read-only-by-default reconciliation workflow for missing objects, orphan objects where safely enumerable, size/checksum mismatch and stale metadata.
4. Add real process-level tests for simultaneous same-business upload/version creation and deterministic collision behavior.
5. Add explicit fault tests for cleanup failure, signed URL provider failure, checksum mismatch and retry semantics.
6. Rerun the complete PHPUnit suite after any future production-code change; the current authoritative result is recorded above and the prior 916-test historical result is superseded.

## Safe rerun commands

Always prove the configured and actual PDO target before destructive tests:

```bash
APP_ENV=testing DB_CONNECTION=mysql DB_DATABASE=schema_admin_testing php artisan env
APP_ENV=testing DB_CONNECTION=mysql DB_DATABASE=schema_admin_testing php artisan db:show --database=mysql
```

Run `RefreshDatabase` suites sequentially, never concurrently against the same schema:

```bash
APP_ENV=testing DB_CONNECTION=mysql DB_DATABASE=schema_admin_testing php artisan test tests/Feature/EnterpriseDocumentManagementTest.php --stop-on-failure
APP_ENV=testing DB_CONNECTION=mysql DB_DATABASE=schema_admin_testing php artisan test tests/Feature/Employee/EmployeeCompletionTest.php --stop-on-failure
APP_ENV=testing DB_CONNECTION=mysql DB_DATABASE=schema_admin_testing php artisan test tests/Unit/Storage/SupabaseAclNeutralFilesystemTest.php tests/Security/SecurityPolicyTest.php --stop-on-failure
```

No live Put/Delete is authorized by this document. Any future live lifecycle requires a new explicit approval and the one-key stop/cleanup procedure in the live runbook.
