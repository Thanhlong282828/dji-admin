# Supabase S3 Live Verification Runbook

Status: `RLS_POLICY_INVENTORY_VERIFIED / EFFECTIVE_PRIVILEGES_VERIFIED_WITH_PRIVILEGED_BYPASS / LIVE_WRITE_FAILED_CLEAN`
Production cloud mutation: one bounded lifecycle was authorized on 2026-10-14; no object persisted

This runbook separates PostgreSQL/Storage RLS metadata audit, bounded S3 read-only connectivity and an optional future live object lifecycle test. Never place credentials, signed URLs or object names from the operating bucket in reports.

## 1. Preconditions

1. Read `AGENTS.md`, `docs/environment-configuration-governance.md`, `SUPABASE-STORAGE-RLS-SECURITY-STANDARD.md` and `SUPABASE-S3-SECURITY-VERIFICATION.md`.
2. Confirm whether the action is read-only, testing-only or live mutation.
3. Do not use `schema_admin` for tests or fixtures.
4. Do not change bucket, RLS, policies, grants or credentials during verification.
5. Stop before every live upload, content download or delete until the user explicitly approves that exact lifecycle.

## 2. PostgreSQL RLS inventory — read-only SQL

Run the following in the Supabase SQL Editor with an authorized metadata-readable role. These statements read catalogs only and do not inspect object content.

### Table owner and RLS state

```sql
select
    n.nspname as schema_name,
    c.relname as table_name,
    pg_get_userbyid(c.relowner) as table_owner,
    c.relrowsecurity as rls_enabled,
    c.relforcerowsecurity as rls_forced
from pg_class c
join pg_namespace n on n.oid = c.relnamespace
where n.nspname = 'storage'
  and c.relname in ('objects', 'buckets')
  and c.relkind in ('r', 'p')
order by c.relname;
```

Expected: both relations are present; record `rls_enabled`, `rls_forced` and owner without assuming that enabled RLS alone denies access.

### Complete policy inventory

```sql
select
    schemaname,
    tablename,
    policyname,
    permissive,
    roles,
    cmd,
    qual as using_expression,
    with_check as with_check_expression
from pg_policies
where schemaname = 'storage'
  and tablename in ('objects', 'buckets')
order by tablename, policyname, cmd;
```

Expected: capture every policy, not only names beginning with `laraveldji_`. PostgreSQL combines applicable permissive policies with OR. A permissive expression ending in `AND false` grants nothing by itself, but another applicable permissive policy may still grant access. Restrictive policies are then combined with AND against the permissive result.

### Policies that mention the LaravelDji bucket

```sql
select
    schemaname,
    tablename,
    policyname,
    permissive,
    roles,
    cmd,
    qual,
    with_check
from pg_policies
where schemaname = 'storage'
  and tablename = 'objects'
  and (
      coalesce(qual, '') ilike '%laraveldji-media%'
      or coalesce(with_check, '') ilike '%laraveldji-media%'
      or qual is null
      or with_check is null
  )
order by policyname, cmd;
```

Expected: manually review null/unrestricted expressions and every policy capable of matching the bucket. Do not conclude effective denial from policy names.

### Explicit table grants

```sql
select
    table_schema,
    table_name,
    grantee,
    privilege_type,
    is_grantable
from information_schema.role_table_grants
where table_schema = 'storage'
  and table_name in ('objects', 'buckets')
  and grantee in ('PUBLIC', 'anon', 'authenticated', 'service_role')
order by table_name, grantee, privilege_type;
```

### Effective table privileges

```sql
select
    role_name,
    relation_name,
    has_table_privilege(role_name, relation_name, 'SELECT') as can_select,
    has_table_privilege(role_name, relation_name, 'INSERT') as can_insert,
    has_table_privilege(role_name, relation_name, 'UPDATE') as can_update,
    has_table_privilege(role_name, relation_name, 'DELETE') as can_delete
from (values ('anon'), ('authenticated'), ('service_role')) roles(role_name)
cross join (values ('storage.objects'), ('storage.buckets')) relations(relation_name)
order by role_name, relation_name;
```

Privileges are necessary but not sufficient for row access; RLS still evaluates for non-bypass roles. `service_role`, table owners and roles with `BYPASSRLS` require separate trust review.

### Role bypass attributes

```sql
select rolname, rolsuper, rolbypassrls, rolcanlogin
from pg_roles
where rolname in ('anon', 'authenticated', 'service_role')
order by rolname;
```

### Default privileges

```sql
select
    pg_get_userbyid(d.defaclrole) as owner,
    n.nspname as schema_name,
    d.defaclobjtype as object_type,
    d.defaclacl as acl
from pg_default_acl d
left join pg_namespace n on n.oid = d.defaclnamespace
where n.nspname = 'storage' or d.defaclnamespace = 0
order by owner, schema_name, object_type;
```

### Functions and SECURITY DEFINER review

```sql
select
    n.nspname as schema_name,
    p.proname as function_name,
    pg_get_userbyid(p.proowner) as owner,
    p.prosecdef as security_definer,
    p.proconfig as function_config,
    has_function_privilege('anon', p.oid, 'EXECUTE') as anon_execute,
    has_function_privilege('authenticated', p.oid, 'EXECUTE') as authenticated_execute,
    pg_get_function_identity_arguments(p.oid) as arguments
from pg_proc p
join pg_namespace n on n.oid = p.pronamespace
where n.nspname in ('storage', 'public')
order by p.prosecdef desc, n.nspname, p.proname;
```

For every `security_definer = true`, verify the owner, purpose, fixed safe `search_path`, input validation and EXECUTE grants. Do not paste function bodies containing sensitive logic into public reports.

## 3. Effective authorization interpretation

Classify the evidence independently:

1. `POLICY_EXISTS`: a row exists in `pg_policies`.
2. `RLS_ENABLED`: `relrowsecurity` is true.
3. `ROLE_HAS_TABLE_PRIVILEGE`: the role can attempt the operation.
4. `POLICY_MATCHES`: at least one applicable permissive policy allows the row and every applicable restrictive policy allows it.
5. `BYPASS_PATH`: owner, superuser, BYPASSRLS, `service_role`, SECURITY DEFINER or S3 credential path avoids user-level policy enforcement.

The four supplied `laraveldji_deny_direct_*` policies are PERMISSIVE with `bucket_id = 'laraveldji-media' AND false`. They do not themselves grant a row, but cannot establish absolute denial until all other policies, grants and bypass paths are inventoried.

### 2026-10-14 live SQL checkpoint

The user executed the read-only production policy inventory and reported:

- exactly four policy rows across `storage.objects` and `storage.buckets`;
- all four policies belong to `storage.objects`;
- no policy exists on `storage.buckets` in that inventory;
- no other permissive policy was found;
- SELECT, INSERT, UPDATE and DELETE each use `bucket_id = 'laraveldji-media' AND false` for `anon` and `authenticated`;
- both storage tables have RLS enabled and the bucket is private.

This supports:

- RLS enabled: `PASS_USER_VERIFIED_LIVE_SQL`;
- policy inventory: `PASS_USER_VERIFIED_LIVE_SQL`;
- conflicting permissive-policy inventory: `PASS_USER_VERIFIED_LIVE_SQL`.

It does not yet support `EFFECTIVE_AUTHORIZATION_PASS`. The explicit/effective table grants, table owner behavior, `rolbypassrls`, `service_role`, default privileges, SECURITY DEFINER functions and EXECUTE grants must still be reviewed with the remaining read-only queries in section 2. Server-side S3 access remains a separate privileged bypass path regardless of user-role policy results.

Laravel's S3 access key is a privileged server-side transport identity. Treat Laravel session authorization and company/subject scoping as mandatory even when Storage RLS appears restrictive.

## 4. Current bounded live read-only probe

From the repository root, after confirming no value will be printed:

```bash
APP_ENV=local php artisan tinker --execute='$disk = Storage::disk("supabase"); $items = $disk->files("security-verification/read-only-connectivity-probe"); echo json_encode(["configured" => true, "list_request" => "verified", "returned_count" => count($items)]);'
```

- Mutation: none; bounded ListObjects only.
- Expected: request succeeds and returns zero for the dedicated nonexistent prefix.
- Failure: classify credential/endpoint/network/permission state without printing raw provider exceptions containing signatures.
- This proves current S3 read connectivity only. It does not prove upload, content download, delete or Laravel route authorization.

## 5. Live object lifecycle procedure

Do not execute this section without explicit authorization. Authorization was granted once on 2026-10-14; its failed-clean result is recorded in section 10 and does not authorize another retry.

Planned artifact:

- Prefix: `security-verification/{timestamp}-{random-uuid}/`.
- Object: `probe.txt`.
- Payload: a small generated non-business string.
- Local evidence: SHA-256 and byte length only.
- Visibility: private.
- No existing key may be reused.

Planned sequence:

1. Generate the unique key and harmless payload locally.
2. Upload exactly one private object.
3. HEAD the object and verify byte length/metadata.
4. Download through authenticated server-side S3 and compare SHA-256 without printing content or signed URL.
5. Verify anonymous HTTP access is denied.
6. If route-level verification is required, create only isolated testing-database metadata; never point operating metadata at the probe.
7. Delete only the exact generated key.
8. HEAD/list the exact prefix to prove cleanup.
9. If cleanup fails, stop, report the exact redacted key hash/prefix and request operator cleanup; do not broaden delete scope.

Rollback is deletion of the single generated object. Bucket/policy/RLS/credential changes are never part of rollback.

## 6. PASS criteria

- RLS inventory: PASS only after the complete catalog queries are captured and conflicting policies/bypass roles reviewed.
- Read-only S3: PASS when the bounded authenticated request succeeds without mutation.
- Live upload/download: PASS only after the explicitly approved lifecycle completes, checksum matches, anonymous access is denied and cleanup is proven.
- Laravel authorization: PASS only from current automated tests on the verified testing database.
- Any missing authorization or failed cleanup is FAIL, not an acceptable warning.

## 7. Absolute prohibitions

- No automatic production upload/delete.
- No object overwrite.
- No wildcard/bucket-wide cleanup.
- No RLS, grant, bucket or credential mutation.
- No secret or signed URL output.
- No `schema_admin` test fixture, migration, truncate or reset.
- No claim that an S3 credential is constrained by user JWT RLS.

## 8. Phase 3 read-only Dashboard evidence

On 2026-10-14, Codex navigated the authenticated Supabase Dashboard to Storage → Policies without using any mutation action. The Dashboard showed the private `laraveldji-media` bucket with four deny policies for SELECT, INSERT, UPDATE and DELETE, applied to `anon` and `authenticated`. It showed no other policy under `storage.objects` and no policy under `storage.buckets`.

This closed the Dashboard visibility checkpoint. The later effective-authorization audit and authorized live lifecycle result are recorded in sections 9 and 10.

## 9. 2026-10-14 effective authorization result

Codex executed the section 2 catalog audit through the authenticated Supabase SQL Editor. No DDL or DML was executed.

### Relations, grants and roles

- `storage.objects` and `storage.buckets` are owned by `supabase_storage_admin`.
- RLS is enabled on both relations and `FORCE ROW LEVEL SECURITY` is false on both.
- `anon` and `authenticated` have SELECT, INSERT, UPDATE and DELETE table privileges on both relations, are not superusers and do not have `BYPASSRLS`.
- `service_role` has effective SELECT, INSERT, UPDATE and DELETE privileges on both relations and has `BYPASSRLS=true`.
- `supabase_storage_admin` is not superuser and does not have the BYPASSRLS attribute, but it owns both relations. Because RLS is not forced, the relation owner is an expected owner-bypass path.
- Storage-schema default privileges owned by `postgres` grant table DML, sequence use/update and function execute to `anon`, `authenticated` and `service_role`. RLS still evaluates row access for the non-bypass roles.

Consequently, `anon` and `authenticated` can attempt table operations but the complete policy inventory denies all rows for `laraveldji-media` (and no policy on `storage.buckets` produces PostgreSQL's default deny). Absolute denial does not apply to `service_role`, the relation owner or server-side S3 credentials.

### SECURITY DEFINER inventory

- The `storage` schema contains 19 functions and zero SECURITY DEFINER functions.
- The `public` schema contains one function: `rls_auto_enable()`.
- `rls_auto_enable()` is SECURITY DEFINER, owned by `postgres`, has a fixed `search_path=pg_catalog`, takes no arguments and is attached to the enabled `ensure_rls` `ddl_command_end` event trigger.
- EXECUTE is granted to `anon`, `authenticated` and `service_role`; its reviewed purpose is to enable RLS on newly created tables in the `public` schema. Its body is not reproduced in public reports.
- No SECURITY DEFINER function in `storage` provides a discovered object-data bypass.

Classification: `EFFECTIVE_PRIVILEGES_VERIFIED_WITH_EXPECTED_PRIVILEGED_BYPASS_PATHS`.

## 10. 2026-10-14 authorized live object lifecycle

The user explicitly authorized one isolated lifecycle under a new `security-verification/{timestamp}-{uuid}/` prefix.

Observed attempts:

1. The first PutObject attempt included explicit private visibility metadata and failed at upload with `League\\Flysystem\\UnableToWriteFile`.
2. A retry omitted visibility/ACL at the application call-site, but subsequent local command capture proved the stock Flysystem adapter still injected `ACL=private`. It failed at the same upload stage with the same exception class.

For both generated keys, the key was absent before the write, no upload succeeded, exact-key cleanup ran in `finally`, and the post-cleanup existence check was false. No object was ever persisted, so authenticated content download, checksum comparison, signed URL retrieval and anonymous denial for the generated object could not be executed. No third attempt was made. No existing object, bucket configuration, RLS policy, grant or credential was changed.

| Attempt | Key SHA-256 | Payload SHA-256 | Bytes | Upload | Cleanup |
|---|---|---|---:|---|---|
| Explicit private visibility | `87f5b8c4a58da71b62fbd9d65b81c525b62b2d90bccd9fe98eeffaeefd855d58` | `293cbe0cc90692db8c274fd3494c6aedaf71c70a76407ba0987b4ea87b09bec6` | 82 | FAIL | PASS; absent |
| Canonical adapter write | `ec260b18b20465730ff8f9939659db4e63bc80a1d9d9e9638dbbfe7288add6ea` | `e22929c53f1a13e80b6f8ed900bcf676d44d1404002e045ac615f59ded0c826a` | 82 | FAIL | PASS; absent |

Classification: `LIVE_S3_LIFECYCLE_FAILED_AT_UPLOAD_CLEAN`. Do not report upload/download/signed URL/anonymous-object verification as PASS.

### PutObject root-cause checkpoint

The follow-up RCA is recorded in `SUPABASE-S3-PUTOBJECT-RCA.md`. A local AWS SDK mock captured `PutObject` with `ACL=private` for the canonical write call. Supabase supports PutObject but not its ACL/grant headers. This is a verified request-construction incompatibility; the original provider HTTP status, S3 error code and request ID were not retained. No live write/delete was performed during RCA.

### ACL-neutral application repair

The dedicated Laravel `supabase` disk now resolves through the custom `supabase-s3` driver. Its client-only middleware removes ACL/grant parameters while retaining the standard Flysystem adapter for read, head, delete and temporary URLs. Local transport-boundary mocks and Document/Employee/Security regressions pass; see `SUPABASE-S3-PUTOBJECT-RCA.md` for commands and counts. This does not authorize a live retry and does not establish that provider write authorization succeeds.

### Safe reproduction command shape

After obtaining new explicit approval, use Laravel's configured `supabase` disk, generate a UUID key beneath `security-verification/`, reject a pre-existing key, write without printing the key or credentials, compare SHA-256 after an authenticated read, request a temporary URL, perform an unsigned GET, and delete the exact generated key in a `finally` block. Emit only key hash, payload hash, byte count, sanitized provider status/error code/request ID and booleans. Do not retry automatically after any failure.

## 11. 2026-10-14 ACL-neutral live lifecycle

The user approved one lifecycle after the ACL-neutral adapter was implemented. The local mock gate passed immediately before the request. One new generated key was absent before upload. The single PutObject attempt failed with HTTP 415 and provider code `InvalidMimeType`; no request ID was returned. The 109-byte non-sensitive payload used `ContentType: text/plain; charset=utf-8`.

Redacted evidence:

- key SHA-256: `3b866ee61880967cc7d6be8a0e8b7bbcb09fe001bfab9f28ae4976247146ec4d`;
- payload SHA-256: `10abf6fce4b95fb11c20c0c6c4ca11759ceb5b28426b4686cedbb409a6c0ee95`;
- upload: FAIL;
- exact-key cleanup: PASS;
- post-cleanup absence: PASS.

The procedure stopped immediately and did not generate a second key. Since no object existed, authenticated content download/checksum, signed URL and anonymous-object denial were not run. No bucket, RLS, policy, grant, credential or MySQL data changed.

Classification: `LIVE_S3_END_TO_END_INCOMPLETE_INVALID_MIME_TYPE_CLEAN`. A future retry requires new explicit approval and must use a locally verified exact provider-supported MIME value without parameters.

## 12. 2026-10-14 PDF MIME allowlist verification and successful live lifecycle

The bucket configuration was inspected in the authenticated Supabase Dashboard without using any mutation action. The current `laraveldji-media` MIME allowlist is:

- `image/jpeg`
- `image/png`
- `image/webp`
- `application/pdf`

`text/plain; charset=utf-8` is not in that allowlist. This verifies the cause of the preceding HTTP 415 / `InvalidMimeType`: the request reached provider MIME validation with a value that the bucket did not allow. No bucket setting was changed.

Before live access, `SupabaseAclNeutralFilesystemTest` passed 7 tests / 26 assertions. Its transport-boundary mock proved that a valid minimal PDF is sent by `PutObject` with exact `ContentType=application/pdf`, with no `ACL` or `Grant*` parameter. The generated PDF had a valid PDF header, cross-reference table, trailer and EOF marker; local MIME detection returned `application/pdf`.

The user explicitly approved one new lifecycle. Exactly one new key under `security-verification/{timestamp}-{uuid}/minimal.pdf` was generated. The key was absent before upload. No retry or second key was used.

Redacted evidence:

| Evidence | Result |
|---|---|
| Driver | `supabase-s3` |
| Bucket | `laraveldji-media` |
| Content type | `application/pdf` |
| Local detected MIME | `application/pdf` |
| Key SHA-256 | `6340a9cf42d9dbc45ba65e1e0b94a71ca23dbc615503b5bd4f9bd08124481750` |
| Payload SHA-256 | `0f95295eef93f98c1b77cc88ecbdbe76488948379ac491725a0fa32e5be45dcd` |
| Bytes | `431` |
| Preflight absent | PASS |
| PutObject | PASS |
| Authenticated download | PASS |
| Authenticated checksum | PASS |
| Signed URL download | PASS |
| Signed URL checksum | PASS |
| Anonymous unsigned access | DENIED, HTTP 403 |
| Exact-key delete | PASS |
| Post-delete absence | PASS |

No object remains. The procedure did not inspect, modify or delete any existing object and did not mutate MySQL, bucket configuration, RLS, policy, grant or credentials. No secret, key name, signed URL, authorization header or payload content was recorded.

Classification: `LIVE_S3_END_TO_END_PASS_PDF_ALLOWLIST_VERIFIED`.

Safe re-verification remains approval-gated: rerun the focused mock first, inspect the current allowlist read-only, then use exactly one newly generated key and stop on the first failure while cleaning up only that key. Do not reuse the key hash above as an object identifier; the raw key was intentionally not retained.
# Post-lifecycle operational controls

The approved PDF lifecycle remains the only live object proof. Final hardening added no live request. For local/testing verification use the ClamAV, telemetry, reconciliation, concurrency and failure tests documented in `docs/security/SUPABASE-S3-FINAL-PRODUCTION-READINESS.md`. For incidents and dry-run reconciliation use `docs/runbooks/supabase-storage-failure.md`.

Never interpret a reconciliation anomaly as authorization to delete an object. A future live lifecycle still requires fresh explicit approval and the exact one-key stop/cleanup rules in this runbook.
