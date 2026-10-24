# Supabase S3 Security Phase 3 — Final Audit

Date: 2026-10-14

## Scope and safety boundary

This checkpoint completes every Phase 3 activity that is read-only or safe in the Laravel testing environment. It does not authorize or perform a live object upload, content download, overwrite, delete, RLS change, grant change, bucket change, credential rotation, production database mutation or deployment.

The operating `schema_admin` database and the live Supabase project were not mutated.

## Architecture boundary

- Laravel remains the authentication, authorization and tenant-isolation authority.
- Private documents use the server-side Supabase S3-compatible Flysystem disk.
- S3 credentials are privileged server credentials and are not constrained by user-JWT Storage RLS.
- Laravel permission/Policy checks and company/employee/document ownership checks remain mandatory before any storage operation.
- No access key, secret, signed URL or object name is recorded in this report.

## Evidence inventory

| Control | Result | Evidence |
|---|---|---|
| Bucket private | PASS | User-provided live evidence and Supabase Dashboard inventory |
| RLS enabled on `storage.objects` | PASS | User-provided read-only live SQL |
| RLS enabled on `storage.buckets` | PASS | User-provided read-only live SQL |
| Policy inventory | PASS | User-provided live SQL plus read-only Dashboard verification on 2026-10-14 |
| SELECT deny policy | PASS | `laraveldji_deny_direct_select`, roles `anon`, `authenticated` |
| INSERT deny policy | PASS | `laraveldji_deny_direct_insert`, roles `anon`, `authenticated` |
| UPDATE deny policy | PASS | `laraveldji_deny_direct_update`, roles `anon`, `authenticated` |
| DELETE deny policy | PASS | `laraveldji_deny_direct_delete`, roles `anon`, `authenticated` |
| Other `storage.objects` policies | PASS | Dashboard displayed `No policies created yet` outside the bucket group |
| `storage.buckets` policies | PASS | Dashboard displayed `No policies created yet` |
| Explicit/effective table grants | PASS | Verified through read-only Supabase SQL Editor catalog query |
| Table owner and `rolbypassrls` | PASS_WITH_BYPASS_DOCUMENTED | Owner bypass exists because RLS is not forced; `anon`/`authenticated` have no BYPASSRLS |
| `service_role` privilege path | PASS_WITH_BYPASS_DOCUMENTED | `service_role` has `BYPASSRLS=true` and full effective table DML |
| Default privileges | PASS | Verified storage-schema defaults for tables, sequences and functions |
| SECURITY DEFINER and EXECUTE grants | PASS_WITH_REVIEW_NOTE | No storage SECURITY DEFINER; public RLS event-trigger function reviewed |
| Server-side S3 access | PASS_WITH_PRIVILEGED_BOUNDARY | Bounded ListObjects request succeeded; this path is outside user-JWT RLS |
| Live upload/download/checksum/delete | FAIL_CLEAN | Upload failed before persistence; cleanup verified absent; later lifecycle steps were not executable |

## Dashboard verification

Codex opened the authenticated Supabase Dashboard in read-only mode and navigated to Storage → Policies. The bucket list showed `laraveldji-media` with four policies. The Policies page displayed exactly:

- `laraveldji_deny_direct_delete` — DELETE — `anon`, `authenticated`;
- `laraveldji_deny_direct_update` — UPDATE — `anon`, `authenticated`;
- `laraveldji_deny_direct_insert` — INSERT — `anon`, `authenticated`;
- `laraveldji_deny_direct_select` — SELECT — `anon`, `authenticated`.

The same page displayed no additional policy under `storage.objects` and no policy under `storage.buckets`. No Create, Edit or Delete action was used.

Dashboard visibility confirms the policy names, commands and role targets. The exact `USING`/`WITH CHECK` expressions continue to rely on the user-provided live SQL evidence: `bucket_id = 'laraveldji-media' AND false`.

## Effective authorization result

The visible policies are PERMISSIVE policies whose expressions are always false for the named bucket. They grant no row to `anon` or `authenticated` by themselves. This is not sufficient to prove every effective access path is denied. Table owners, superusers, roles with `BYPASSRLS`, `service_role`, SECURITY DEFINER functions and S3-compatible credentials may bypass or operate outside user-level RLS.

The remaining catalog SQL was executed on 2026-10-14. `anon` and `authenticated` have table DML grants but no BYPASSRLS and remain governed by the false policies/default-deny behavior. `service_role` has BYPASSRLS. Relation-owner access can bypass because RLS is not forced. The server-side S3 credential path is separately privileged. The effective authorization classification is therefore `VERIFIED_WITH_EXPECTED_PRIVILEGED_BYPASS_PATHS`, not universal denial.

## Authorized live lifecycle result

The authorized live test failed at PutObject. Two unique generated keys were attempted: first with explicit private visibility and then with a canonical write that omitted ACL at the application call-site. Later local command capture proved Flysystem still injected `ACL=private` for that canonical write. Neither object persisted. Both exact-key cleanup blocks completed and both post-cleanup existence checks returned false. Because no object existed, download/checksum, signed URL and anonymous-object denial were not verified. Detailed redacted hashes and byte counts are recorded in the runbook; root-cause evidence is in `SUPABASE-S3-PUTOBJECT-RCA.md`.

## Laravel and build evidence

Current Phase 3 verification on 2026-10-14:

- Database gate: `APP_ENV=testing`; configured MySQL database and actual PDO database both `schema_admin_testing`.
- Combined document/security regression: 38 tests passed, 142 assertions, exit code 0.
- PHPStan: PASS, 0 errors.
- Pint: PASS.
- Blade compilation: PASS.
- Vite: PASS.
- Routes: PASS, 708 application routes.
- Public source maps: 0.
- Sensitive storage/cloud credential identifiers in built JS/CSS: 0.
- `git diff --check`: PASS.

Latest authoritative full-suite evidence remains 916 tests, 915 passed, 1 skipped, 34,013 assertions, exit code 0. The full suite was not repeated because this closure changed documentation/agent governance only and the focused security suite plus all static gates passed. Future production-code changes require fresh relevant tests; historical counts must not be presented as newly executed evidence.

## Final Phase 3 status

| Area | Status |
|---|---|
| Laravel authorization and tenant isolation | PASS |
| Fake/mock/fault storage lifecycle | PASS |
| Private bucket | PASS |
| RLS enabled | PASS |
| Complete policy inventory | PASS |
| Dashboard policy visibility | PASS |
| Read-only S3 connectivity | PASS |
| Effective PostgreSQL authorization | PASS_WITH_PRIVILEGED_BYPASS_PATHS |
| Live S3 object lifecycle | FAIL_AT_UPLOAD_CLEAN |
| Cloud mutation | ATTEMPTED_WRITE; NO OBJECT PERSISTED |
| Operating database mutation | NOT PERFORMED |

Overall classification: `SUPABASE_S3_SECURITY_PHASE3_PARTIAL_LIVE_UPLOAD_FAILED_CLEAN`.

### Post-repair live checkpoint

After the ACL-neutral source repair, one separately approved object lifecycle was attempted. The adapter mock gate passed, but the single live PutObject returned HTTP 415 / `InvalidMimeType` for a parameterized text MIME value. No object persisted, exact-key cleanup passed and post-cleanup absence was verified. No second key or retry was used. Consequently the end-to-end lifecycle remains incomplete and download/checksum/signed URL/anonymous-object denial remain unverified. See the RCA and live runbook for redacted hashes.

Follow-up closure on 2026-10-14 supersedes only that incomplete MIME attempt. Read-only Dashboard evidence verified the bucket allowlist as JPEG, PNG, WebP and PDF. A fresh mock proved exact `application/pdf` with no ACL/grant parameters. One newly approved PDF key then passed upload, authenticated checksum, signed URL checksum, anonymous denial (HTTP 403), exact-key cleanup and absence verification. No object remains and no production configuration or database was changed. Classification: `LIVE_S3_END_TO_END_PASS_PDF_ALLOWLIST_VERIFIED`.

## Mandatory continuation rule

Future Codex sessions must read this document, `SUPABASE-S3-SECURITY-VERIFICATION.md` and `SUPABASE-S3-LIVE-VERIFICATION-RUNBOOK.md` before working on Supabase storage. Do not repeat completed implementation or catalog audit. Before any further live write, diagnose S3 PutObject authorization/endpoint configuration without exposing credentials and obtain explicit approval for a new attempt.
