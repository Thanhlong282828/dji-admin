# Supabase S3 PutObject Root Cause Analysis

Date: 2026-10-14

## Scope and safety

This analysis used source inspection, sanitized configuration inspection and an AWS SDK mock handler only. It did not call live `PutObject` or `DeleteObject`, modify credentials, change the bucket, alter RLS/policies, or mutate a production database.

## Verified configuration

The `supabase` filesystem disk uses Laravel's S3 driver with the five required environment inputs. Runtime inspection reported only presence/state, never credential values:

| Item | Result |
|---|---|
| Access key | PRESENT |
| Secret key | PRESENT |
| Endpoint | PRESENT; HTTPS Supabase Storage S3 path |
| Region | PRESENT; `ap-southeast-1` |
| Bucket | PRESENT; expected private bucket |
| Path-style addressing | enabled |
| Visibility | private |
| Exceptions/reporting | enabled |

The endpoint, project region and path-style configuration match Supabase's documented S3 client shape. Previous bounded `ListObjects` evidence also proves that the endpoint, TLS path, bucket, credentials and request signing are usable for an authenticated read operation.

Relevant source:

- `config/filesystems.php:66-77`
- `.env.example:105-113`
- `vendor/laravel/framework/src/Illuminate/Filesystem/FilesystemManager.php:249-267`
- `vendor/league/flysystem-aws-s3-v3/AwsS3V3Adapter.php:141-166`

Installed transport versions at analysis time:

- `aws/aws-sdk-php` 3.388.12
- `league/flysystem` 3.35.3
- `league/flysystem-aws-s3-v3` 3.35.2
- `laravel/framework` 13.27.0

## Root cause

Classification: `VERIFIED_REQUEST_CONSTRUCTION_INCOMPATIBILITY`.

Laravel creates the standard Flysystem AWS S3 adapter with a portable visibility converter. The adapter's private `upload()` method always resolves an ACL and passes it as the fourth argument to the AWS SDK uploader. When no per-write visibility is supplied, the adapter defaults to private visibility. Therefore a canonical `Storage::disk('supabase')->put(...)` call is not ACL-free: it constructs `PutObject` with `ACL=private`.

A fully local AWS SDK mock-handler probe captured the resulting command:

```json
{
    "command": "PutObject",
    "has_acl": true,
    "acl": "private"
}
```

Supabase documents `PutObject` as supported but documents the PutObject ACL/grant headers as unsupported. The request produced by the current Laravel/Flysystem adapter is therefore incompatible with the provider. This corrects the earlier checkpoint description that called the second attempt "without ACL"; it omitted an ACL at application call-site only, while Flysystem still injected `private`.

The historical failed process retained only `League\\Flysystem\\UnableToWriteFile`. No prior provider HTTP status, AWS error code or request ID exists in application logs or the security checkpoints. Those values must remain `NOT_RETAINED`; they must not be inferred or fabricated.

## Cause matrix

| Candidate | Finding |
|---|---|
| ACL unsupported | VERIFIED incompatibility: local command capture contains `ACL=private`; Supabase documents PutObject ACL headers unsupported |
| Flysystem/SDK configuration | VERIFIED contributing cause: stock adapter injects ACL even when caller omits visibility |
| Endpoint wrong | Ruled out by matching documented endpoint form and successful bounded authenticated read |
| Region wrong | Ruled out for the observed read path; runtime project region is configured and authenticated read succeeded |
| Bucket missing | Ruled out by private-bucket evidence and successful bounded list operation |
| InvalidAccessKeyId / SignatureDoesNotMatch | Not supported by retained evidence; authenticated list succeeded with the same configured identity |
| Write permission missing / AccessDenied | Not independently proven or disproven because an ACL-free live PutObject has not been authorized or executed |
| Network/TLS | Ruled out for authenticated read path; no retained evidence of a transport/TLS failure on write |

## Implemented ACL-neutral repair

Do not merely remove `'visibility' => 'private'` from the disk. Laravel's S3 factory defaults its visibility converter to public, while Flysystem still resolves and sends an ACL. That edit would not create an ACL-free request and could weaken intent.

The production source now uses a narrowly scoped custom `supabase-s3` Laravel filesystem driver. It preserves the existing Flysystem AWS adapter, AWS SDK client, private bucket semantics, exception behavior and temporary URL support. A build-stage middleware attached only to the Supabase S3 client removes `ACL`, `GrantFullControl`, `GrantRead`, `GrantReadACP`, `GrantWrite` and `GrantWriteACP` before request serialization.

Implementation:

- `app/Support/Storage/SupabaseAclNeutralFilesystemFactory.php`
- `app/Providers/AppServiceProvider.php`
- `config/filesystems.php`
- `tests/Unit/Storage/SupabaseAclNeutralFilesystemTest.php`

The change applies only to the named `supabase` disk. The standard `s3` and `local` driver declarations remain unchanged; the unit regression proves the stock S3 adapter still emits its normal private ACL. No file under `vendor/` was modified.

Local AWS SDK transport-boundary mocks prove both string and stream writes produce `PutObject` without ACL/grant parameters. `GetObject`, `HeadObject` and `DeleteObject` remain delegated to the canonical adapter. Transport failure remains `UnableToWriteFile` with no fallback. Existing feature tests continue to prove tenant key partitioning, malware scan fail-closed behavior, signed URL authorization and database rollback/no metadata after failed upload.

Removing only `'visibility' => 'private'` remains prohibited: Laravel/Flysystem would still resolve an ACL and could weaken intent.

## 2026-10-14 local verification

Database gate before feature tests:

- `APP_ENV=testing`
- configured database: `schema_admin_testing`
- actual PDO database: `schema_admin_testing`

Valid sequential test evidence:

| Suite | Result | Tests | Assertions |
|---|---|---:|---:|
| ACL-neutral adapter mock | PASS | 6 | 19 |
| Enterprise Document | PASS | 6 | 48 |
| Employee Document | PASS | 12 | 65 |
| Security Policy | PASS | 20 | 29 |
| Total | PASS | 44 | 161 |

The feature suites were run sequentially with `vendor/bin/phpunit` because running several `RefreshDatabase` files together against one MySQL schema caused migration races; those race errors are invalid test evidence and were not treated as product failures.

Quality gates:

- PHPStan: PASS, 0 errors.
- Pint: PASS.
- Blade compilation: PASS.
- Vite production build: PASS.
- Routes: PASS, 708 application routes from JSON output.
- Public source maps: none.
- Sensitive credential-name scan of built JS/CSS/JSON: no matches.
- `git diff --check`: PASS.

Repeatable commands:

```bash
vendor/bin/phpunit tests/Unit/Storage/SupabaseAclNeutralFilesystemTest.php --stop-on-failure --do-not-cache-result

APP_ENV=testing DB_CONNECTION=mysql DB_DATABASE=schema_admin_testing \
  vendor/bin/phpunit tests/Feature/EnterpriseDocumentManagementTest.php --stop-on-failure --do-not-cache-result

APP_ENV=testing DB_CONNECTION=mysql DB_DATABASE=schema_admin_testing \
  vendor/bin/phpunit tests/Feature/Employee/EmployeeCompletionTest.php --stop-on-failure --do-not-cache-result

APP_ENV=testing DB_CONNECTION=mysql DB_DATABASE=schema_admin_testing \
  vendor/bin/phpunit tests/Security/SecurityPolicyTest.php --stop-on-failure --do-not-cache-result

vendor/bin/phpstan analyse --memory-limit=2G --no-progress
vendor/bin/pint --test
APP_ENV=testing DB_CONNECTION=mysql DB_DATABASE=schema_admin_testing php artisan view:cache
APP_ENV=testing DB_CONNECTION=mysql DB_DATABASE=schema_admin_testing php artisan route:list --except-vendor
npm run build
git diff --check
```

## Safe diagnostic handling for the next approved live retry

Within the same process, traverse `UnableToWriteFile::getPrevious()`. If it is an AWS exception, record only:

- exception class;
- HTTP status;
- AWS error code;
- AWS request ID;
- retryable boolean where available.

Never record the exception's raw request, URL query, Authorization header, signing material, credential, signed URL or object content.

## Retest plan — requires new explicit approval

1. Reconfirm the committed/deployed code contains the verified ACL-neutral driver and rerun its mock/static gates.
2. Reconfirm private bucket, environment and sanitized variable presence.
3. Obtain explicit approval for exactly one new object under `security-verification/{timestamp}-{uuid}/`.
4. Assert the key is absent, upload a small non-sensitive payload, verify existence, download and compare SHA-256.
5. Check temporary signed URL and anonymous denial where feasible without exposing the URL.
6. Delete only that exact generated key in `finally`, and verify absence.
7. If upload fails, capture only the sanitized provider fields above and stop; do not retry automatically.

Until that approved lifecycle succeeds, upload, authenticated download/checksum, signed URL and anonymous-object denial remain `NOT_VERIFIED`.

## 2026-10-14 approved ACL-neutral live lifecycle result

The user approved exactly one new lifecycle after the ACL-neutral repair. The mandatory local mock gate passed first: 6 tests, 19 assertions. Runtime configuration resolved the `supabase-s3` driver and all five required S3 settings were present without exposing values.

Exactly one generated object key was used. No retry or second key was created.

| Evidence | Result |
|---|---|
| Key SHA-256 | `3b866ee61880967cc7d6be8a0e8b7bbcb09fe001bfab9f28ae4976247146ec4d` |
| Payload SHA-256 | `10abf6fce4b95fb11c20c0c6c4ca11759ceb5b28426b4686cedbb409a6c0ee95` |
| Payload bytes | 109 |
| Key absent before upload | PASS |
| Upload | FAIL |
| Provider HTTP status | 415 |
| Provider error code | `InvalidMimeType` |
| Provider request ID | not returned |
| Exact-key cleanup | PASS |
| Key absent after cleanup | PASS |

The probe supplied `ContentType: text/plain; charset=utf-8`. Supabase rejected the request as an invalid MIME type before object persistence. This is distinct from the earlier ACL incompatibility: the ACL-neutral request reached provider validation and returned a specific MIME error. Because upload failed, authenticated download/checksum, signed URL and anonymous object denial were not executed and remain `NOT_VERIFIED`.

### MIME follow-up and closure

Read-only Dashboard evidence subsequently verified the bucket allowlist as `image/jpeg`, `image/png`, `image/webp`, `application/pdf`; therefore the parameterized `text/plain` value was outside the configured allowlist. No bucket configuration was changed.

The focused AWS SDK transport mock now also covers a minimal valid PDF and proves exact `ContentType=application/pdf` with no ACL/grant parameter. Result: 7 tests, 26 assertions, PASS.

After that mock gate, one separately approved live PDF lifecycle completed successfully: PutObject, authenticated download/checksum, signed URL download/checksum, anonymous denial (HTTP 403), exact-key delete and post-delete absence all passed. Redacted hashes and byte count are recorded in `SUPABASE-S3-LIVE-VERIFICATION-RUNBOOK.md`. This closes the ACL/MIME transport defect for the verified PDF path without changing authorization, tenant isolation, malware scanning, RLS, bucket policy or credentials.

Historical MIME-attempt classification: `LIVE_S3_END_TO_END_INCOMPLETE_INVALID_MIME_TYPE_CLEAN`.

Current superseding lifecycle classification: `LIVE_S3_END_TO_END_PASS_PDF_ALLOWLIST_VERIFIED`. The final application-integration readiness classification and remaining operational gaps are maintained in `SUPABASE-S3-FINAL-PRODUCTION-READINESS.md`.

No live retry is authorized by this result. A future separately approved single-object test should use an exact provider-supported media type without parameters, after first proving the command through a local mock. Do not change bucket, RLS, policy or credentials to address this MIME validation response.

Executed command shape (the script emitted no key, URL, credential or payload):

```bash
APP_ENV=local php /tmp/laraveldji_supabase_live_acl_neutral.php
```
