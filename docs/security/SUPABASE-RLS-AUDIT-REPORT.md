# Supabase PostgreSQL RLS Audit Report

Status date: 2026-10-13
Scope: read-only discovery and repository-side security verification
Classification: `SUPABASE_RLS_AWAITING_CLOUD_ACCESS`

## Executive result

LaravelDji ERP continues to use MySQL as its canonical application database. Supabase PostgreSQL, Auth, Data API, Realtime and Edge Functions are not part of the current runtime. Supabase is used only through its server-side S3-compatible private Storage endpoint.

The configured S3 access path was previously verified read-only, including authenticated bucket access and denial of anonymous bucket/object access. A live PostgreSQL RLS inventory cannot be produced from an S3 access key and no authorized Data API, Management API, CLI or PostgreSQL connection is configured. This report therefore does not claim that the cloud PostgreSQL project, its grants or its RLS policies are secure.

## Authorized connection discovery

| Connection method | Status | Audit capability |
|---|---|---|
| Supabase S3-compatible endpoint | Configured and previously read-only verified | Bucket/object transport only; cannot inspect PostgreSQL RLS |
| `SUPABASE_URL` + anon key | Missing | Data API exposure cannot be enumerated |
| Supabase service-role key | Missing | Privileged REST inspection unavailable; not required by the ERP runtime |
| Supabase management access token | Missing | Project metadata and exposed-schema inspection unavailable |
| PostgreSQL DSN/password | Missing | `pg_catalog`, grants, policies, functions and default privileges unavailable |
| Supabase CLI | Unavailable | No authenticated CLI project context exists |
| Authorized connector | None available to this audit | No alternative cloud inventory path |

No credential value was printed or stored during discovery.

## Verified architecture boundary

| Resource class | Current classification | Evidence |
|---|---|---|
| MySQL ERP application tables | `UNUSED_OR_NOT_APPLICABLE` for Supabase RLS | MySQL remains canonical and is not replicated to Supabase PostgreSQL |
| Enterprise documents | `RLS_REQUIRED` only if a future JWT/Storage REST path is introduced | Current path is Laravel authorization followed by privileged server-side S3 access |
| Employee private documents | `RLS_REQUIRED` only if a future JWT/Storage REST path is introduced | Current path is Laravel policy/tenant authorization followed by privileged server-side S3 access |
| Supabase PostgreSQL schemas/tables/views/functions | `NEEDS_MANUAL_REVIEW` | Live catalog inventory is blocked by missing authorized access |
| Supabase Storage policy metadata | `NEEDS_MANUAL_REVIEW` | S3 credentials do not expose RLS policy definitions |

The S3 adapter must never be treated as proof that Storage RLS is effective. The canonical security boundary remains Laravel authentication, route permission, Policy/Gate and company/subject scope before a short-lived signed URL is issued.

## Inventory blocker

The following facts remain unverified and must not be inferred:

- exposed PostgreSQL schemas and Data API settings;
- application-owned tables, views and materialized views;
- `anon`, `authenticated`, `service_role`, `public` and custom role grants;
- default privileges;
- table RLS enablement and force-RLS state;
- `USING` and `WITH CHECK` policy expressions;
- `SECURITY DEFINER` functions, owners and safe `search_path` settings;
- function EXECUTE grants;
- Storage policy metadata.

Exact missing access: one authorized read-only PostgreSQL connection, or an authorized Supabase Management/Data API mechanism capable of enumerating the above metadata. Browser login alone is not authorization, and production credentials must not be pasted into source, documentation, terminal output or chat.

## Confirmed vulnerabilities

No live PostgreSQL/RLS vulnerability is confirmed because the underlying catalog cannot be inspected. Absence of evidence is not evidence of secure policy configuration.

Repository-side review confirms no active Laravel business flow depends on Supabase PostgreSQL or Supabase Auth. Consequently there is no justified table-specific remediation SQL to prepare until the live inventory identifies an actual table, role and policy defect.

## Remediation policy

When authorized read-only access becomes available:

1. Export schemas, relations, owners, grants, default privileges, RLS flags, policies and functions without reading business rows.
2. Classify each actual relation as `RLS_REQUIRED`, `RLS_ALREADY_SECURE`, `POLICY_TOO_PERMISSIVE`, `UNUSED_OR_NOT_APPLICABLE` or `NEEDS_MANUAL_REVIEW`.
3. For each confirmed defect, prepare table-specific forward and rollback SQL with least-privilege grants and separate operation policies.
4. Prove allowed and denied access in a disposable development Supabase project.
5. Keep every production SQL/policy change `AWAITING_APPROVAL` until the exact change set and rollback plan are explicitly authorized.

Blanket `USING (true)`, `WITH CHECK (true)`, public access, global `ALL` policies and automatic RLS enablement across unknown tables are prohibited.

## Storage regression boundary

The audit preserves these established controls:

- private S3 bucket and server-only credential;
- Document and Employee owner-module authorization;
- company and employee scope;
- random server-generated object keys;
- MIME/signature/size validation and checksum evidence;
- malware scan with production fail-closed behavior;
- compensating object cleanup after database rollback;
- bounded signed URL lifetime with private/no-store response options;
- legacy local-file fallback only for existing legacy metadata;
- no cloud object upload, overwrite, deletion or migration during this audit.

## Concurrency regression evidence

The previous full suite reported six 120-second process timeouts across Treasury Internal Transfer, Treasury Opening Balance, IAM request creation and Inventory FX Supplier Credit. A focused rerun of the four affected MySQL process-race test files on `schema_admin_testing` completed with 14 tests passed and 584 assertions in approximately 113 seconds.

The subsequent authoritative full suite completed with 916 tests, 915 passed, 1 skipped, 34,013 assertions, no failures or errors and exit code 0 in approximately 1,404 seconds. The six timeouts did not reproduce. No timeout was increased and no assertion was weakened. Evidence therefore supports transient full-suite resource or worker-scheduling pressure in the earlier run rather than a reproducible domain, locking or exactly-once defect.

Concurrency regression status: `RECOVERED_AND_VERIFIED`.

## Cloud mutation statement

No Supabase PostgreSQL DDL, grant, RLS policy, Storage policy, bucket setting or cloud object was created, updated or deleted. All proposed production remediation remains `AWAITING_APPROVAL`.
