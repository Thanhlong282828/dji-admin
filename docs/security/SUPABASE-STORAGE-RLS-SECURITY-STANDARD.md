# Supabase Storage & RLS Security Standard

Status date: 2026-10-13. This standard governs LaravelDji ERP use of Supabase. It is both a permanent development rule and the evidence record for the current audit. It contains no credentials, object names or confidential business data.

## 1. Architecture boundary

- MySQL remains the canonical ERP database and tenant source of truth.
- Laravel Auth, Sanctum/Passport, Spatie permissions, policies and company/branch access remain canonical authorization.
- Supabase is used only as an S3-compatible private object provider for Enterprise Document and Employee Document.
- Laravel Reverb/Redis remain realtime and queue infrastructure. Supabase Auth, Realtime, Edge Functions and PostgreSQL Data API are not part of the current ERP runtime.
- No MySQL table is replicated into Supabase PostgreSQL and no second login/user mapping may be introduced without a separate approved architecture phase.

## 2. Trust model

The browser never receives S3 credentials. A request must pass this sequence:

`authenticated session → route permission → company/tenant policy → canonical document/version lookup → server-side S3 operation → short-lived signed URL`

An S3 access key is a privileged server credential. Supabase Storage RLS does not constrain S3 operations in the same manner as `anon` or user-scoped JWT Storage API calls. A private bucket and RLS policies therefore do not replace Laravel authorization.

## 3. Storage ownership

| Capability | Owner | Path contract | Authorization | Provider |
|---|---|---|---|---|
| Enterprise documents | Document module | `enterprise-documents/{company_id}/{document_id}/{uuid}.{ext}` | route permission + company access + company-scoped version query | local private or Supabase S3 |
| Employee private documents | Employee module | `employees/{company_id}/{employee_id}/documents/{uuid}.{ext}` | route permission + EmployeeDocumentPolicy + employee/company query | local private or Supabase S3 |

The client never supplies an object key. Original filenames are metadata/download names only, sanitized with `basename` and header-control removal. Provider selection is allow-listed to `local` and `supabase`.

## 4. Secure upload contract

- Enforce maximum size before storage.
- Validate extension and detected MIME/signature; do not trust the browser content type alone.
- Generate the object name server-side with a UUID.
- Compute SHA-256 before upload and store it with immutable version metadata where supported.
- Run malware scanning before cloud upload. Production uses ClamAV; scanner failure fails closed with no object or database row.
- A detected infection is rejected before upload.
- Database failure after upload triggers compensating object deletion. Outer create rollback removes the new document directory to prevent orphaned cloud objects.
- Never silently fall back to local when Supabase is selected but unavailable or incomplete.
- Do not seed or upload fake production documents for verification.

## 5. Secure download contract

- Resolve document/version by both identifier and `company_id`; forged IDs and cross-company access return 404 after authorization context is established.
- Download permission must be enforced server-side even when the UI hides the action.
- Supabase downloads use server-generated temporary URLs only after authorization.
- URL lifetime is configurable and bounded to 1–60 minutes; default is 5 minutes.
- Signed URLs are never stored in MySQL, activity logs, notifications, HTML state or durable audit evidence.
- Signed object responses request `Content-Disposition: attachment`, safe content type and `Cache-Control: private, no-store`.
- Local private downloads return `no-store` and `X-Content-Type-Options: nosniff`.
- Legacy local objects remain accessible through the same authorized route; no automatic copy or provider migration is performed.

## 6. Delete, archive and retention

- Archive changes canonical metadata state; it is not an uncontrolled physical delete.
- No browser route accepts an arbitrary key for list/delete/overwrite.
- Physical deletion requires owner-module retention/disposal policy, audit evidence and explicit authorization.
- Cloud cleanup must only target the server-generated prefix for the document being compensated.
- Bucket deletion, bulk object deletion and lifecycle-policy changes are production mutations and require explicit approval.

## 7. S3 credential governance

- Canonical variables are `SUPABASE_STORAGE_ENDPOINT`, `SUPABASE_STORAGE_REGION`, `SUPABASE_STORAGE_BUCKET`, `SUPABASE_STORAGE_ACCESS_KEY_ID` and `SUPABASE_STORAGE_SECRET_ACCESS_KEY`.
- Secrets remain server-side and must never use a `VITE_*` prefix.
- `.env.example` contains blank placeholders only.
- Do not log credentials, signed URLs, authorization headers or raw provider errors containing request signatures.
- Use the smallest provider permission set available. Do not assume an S3 key is bucket-scoped merely because one bucket is configured.
- Rotate credentials through deployment secret governance; do not place rotation values in Git or database rows.
- Credential verification reports only configured/missing and redacted outcome classes.

## 8. Storage RLS applicability

| Access path | Identity | RLS applicability | Required control |
|---|---|---|---|
| Laravel S3 adapter | S3 access key | Storage RLS must not be relied upon | Laravel permission/policy/tenant checks; private bucket; server-only credential |
| Signed URL consumer | Time-limited signature | Authorization occurred before URL generation | short TTL, no logging/persistence, private/no-store response |
| Supabase Storage REST with anon key | Not used | RLS would apply | must remain unused for private ERP documents unless separately approved |
| Supabase Storage REST with user JWT | Not used | RLS would apply | requires proven Laravel↔Supabase identity mapping; no implicit `auth.uid()` mapping |
| Supabase service role | Not configured for ERP | bypasses RLS | must not be exposed or introduced casually |

Do not add `USING (true)`, `WITH CHECK (true)`, blanket `ALL`, or public SELECT policies for private ERP objects.

## 9. PostgreSQL/Data API RLS standard

Current LaravelDji runtime does not use Supabase PostgreSQL. Therefore application-table RLS is `NOT_APPLICABLE` until a separately approved capability uses it. Existing cloud PostgreSQL objects still require a live read-only inventory before production security can be asserted.

For any future exposed table:

1. Prove the authentication identity model; never assume `auth.uid()` equals MySQL `users.id`.
2. Enable RLS and separately define least-privilege SELECT, INSERT, UPDATE and DELETE policies.
3. Use both `USING` and `WITH CHECK` where applicable.
4. Revoke unnecessary `anon`, `authenticated`, `public` and function EXECUTE grants.
5. Audit views, ownership, default privileges and every `SECURITY DEFINER` function, including a fixed safe `search_path`.
6. Test allowed and denied cross-user/cross-tenant operations in an isolated Supabase development project.
7. Prepare exact DDL, affected resources and rollback SQL. Production execution remains `AWAITING_APPROVAL`.

## 10. Frontend and DevTools

- Backend authorization remains effective if a user modifies HTML or JavaScript.
- Unauthorized controls are not rendered, but hiding controls is not authorization.
- Object keys, signed URLs and sensitive metadata are not serialized unless necessary for the immediate authorized response.
- Production Vite build is minified and emits no public source maps.
- No Supabase credential is allowed in `VITE_*`, HTML, CSS, JavaScript or public build artifacts.
- Do not attempt to disable DevTools or claim browser-delivered code is secret.

## 11. Required tests

At minimum, maintain tests for:

- unauthenticated and unauthorized upload/download/archive;
- company and subject tenant mismatch;
- forged document/version IDs;
- private random server-side paths;
- MIME/signature and size rejection;
- malware infected/unavailable fail-closed behavior;
- storage failure and database rollback cleanup;
- signed URL provider, bounded expiration and response options;
- legacy local compatibility;
- secrets absent from frontend/build and source maps absent;
- navigation and route permission contracts.

Cloud tests must use fakes unless an explicitly authorized read-only or disposable resource is available. Never run destructive integration tests against the operating bucket.

## 12. Production change approval and rollback

Before changing a production bucket, storage policy, grant, exposed schema, RLS policy or function:

1. Record the finding and severity.
2. Name exact project, schema, table/bucket and policy/grant affected without exposing credentials.
3. Prepare forward and rollback SQL/API operations.
4. Prove both in a disposable development environment.
5. Capture allowed/denied test evidence.
6. Mark the production action `AWAITING_APPROVAL` and stop.

Rollback must restore the previous policy/grant from an exported definition, never disable RLS broadly or make a bucket public. Application rollback must preserve local-file compatibility and must not orphan objects.

## 13. 2026-10-13 audit inventory

### Local implementation

| Area | Result | Severity / status |
|---|---|---|
| Server-side S3 configuration | Complete; no browser-prefixed secret | PASS |
| Enterprise tenant path | Company/document UUID path generated server-side | PASS |
| Employee tenant path | Company/employee UUID path generated server-side | PASS |
| Route permission and tenant lookup | Present for both document flows | PASS |
| Temporary URL | Bounded configuration, authorization first | PASS |
| Enterprise malware gate | Missing before this audit; added fail-closed ClamAV/null adapter boundary | HIGH, FIXED |
| Employee scanner failure | Previously persisted an unavailable-scan object; now rejected before upload | HIGH, FIXED |
| Outer create rollback cleanup | Previously could leave a cloud object after outer rollback; prefix compensation added | MEDIUM, FIXED |
| Local download cache/sniff headers | Missing on Employee local response; added | LOW, FIXED |
| Branch scope | Document records are company-owned and contain no branch key | NOT_APPLICABLE; do not invent branch filtering |

### Live S3 read-only evidence

- Configured S3 endpoint/key/bucket tuple: complete.
- Authenticated `HeadBucket`: verified.
- Authenticated `ListObjectsV2` with `MaxKeys=1`: verified; no object name was emitted.
- Bucket ACL read returned zero grants through the compatible endpoint.
- Anonymous bucket listing: denied HTTP 403.
- Anonymous HEAD for the sampled existing object: denied HTTP 403.
- No object was uploaded, downloaded, overwritten or deleted during this audit.

This evidence verifies private anonymous behavior for the sampled S3 path. It does not prove that every provider-side policy, access key scope or Supabase-managed metadata setting is correct.

### PostgreSQL/RLS inventory

| Item | Evidence | Status |
|---|---|---|
| Supabase PostgreSQL used by Laravel ERP | No; MySQL remains canonical | NOT_APPLICABLE |
| `SUPABASE_URL` | Not configured | BLOCKED_BY_CONFIGURATION |
| anon key | Not configured | BLOCKED_BY_CONFIGURATION |
| service role key | Not configured | BLOCKED_BY_CONFIGURATION |
| Supabase management access token | Not configured | BLOCKED_BY_CONFIGURATION |
| Exposed schemas/tables/views/functions | Cannot be inspected from S3 credentials | NOT_VERIFIED |
| RLS enablement/policies | Cannot be inspected from S3 credentials | NOT_VERIFIED |
| grants/default privileges/roles | Cannot be inspected from S3 credentials | NOT_VERIFIED |
| `SECURITY DEFINER` functions | Cannot be inspected from S3 credentials | NOT_VERIFIED |

No PostgreSQL DDL, grant, RLS policy, storage policy or cloud configuration was changed. Production cloud mutation is not authorized by this audit.

## 14. Classification rule

Local hardening may be complete while live PostgreSQL/RLS remains unverified. Do not classify the entire Supabase project as secure until the actual project is inspected with authorized read-only Data API/management/database access. When that access is absent, the required classification is `SUPABASE_LIVE_AUDIT_BLOCKED_BY_CONFIGURATION`.

The current detailed PostgreSQL/RLS discovery record, access blocker, classification matrix and concurrency recovery evidence are maintained in `docs/security/SUPABASE-RLS-AUDIT-REPORT.md`.
