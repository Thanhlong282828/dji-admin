# Supabase Capability Matrix

Audit date: 2026-10-12. This document records architectural compatibility; it is not evidence of a live Supabase connection.

| Capability | Existing Laravel equivalent | ERP use case | Security / operations | Decision |
|---|---|---|---|---|
| Storage | Flysystem local/S3, DMS, Cloudflare Images for public images | Private DMS documents with tenant paths and temporary downloads | Secret access keys remain server-side; private bucket and lifecycle policy are deployment-owned; provider cost/egress must be monitored | **IMPLEMENT** through the existing Flysystem boundary |
| Realtime | Laravel Reverb + Echo | Existing notifications and business-event UI updates | A second transport risks duplicate delivery and ordering semantics | **REUSE_EXISTING** |
| Auth | Laravel auth, Sanctum/Passport, Spatie permissions | Employee/admin identity and ERP authorization | A second identity store would split tenant, role, MFA and audit governance | **REUSE_EXISTING** |
| PostgreSQL | Canonical MySQL domain schema and migrations | None in this phase | Database migration would be a separate high-risk program | **NOT_APPLICABLE** |
| Edge Functions | Laravel application services, queues and HTTP endpoints | Potential provider-local file processing later | Duplicates domain logic and complicates secrets/audit ownership | **DEFER** |
| Cron | Laravel Scheduler | Scheduled ERP jobs | A second scheduler risks duplicate economic effects | **REUSE_EXISTING** |
| Queues | Horizon/Redis | Asynchronous ERP processing | Exactly-once/idempotency contracts already live in Laravel | **REUSE_EXISTING** |
| Vector / AI | No canonical ERP vector engine | Future document search with explicit privacy design | Sensitive documents require retention, consent and tenant-safe indexing | **DEFER** |
| REST / GraphQL | Laravel routes/resources and OpenAPI | Existing ERP API consumers | Auto-generated database APIs could bypass service/policy boundaries | **NOT_APPLICABLE** |

## Implemented storage boundary

- `DOCUMENT_STORAGE_DISK=local` remains the safe default.
- `supabase` is an optional S3-compatible Flysystem disk.
- DMS objects use `enterprise-documents/{company_id}/{document_id}/{uuid}.{extension}`.
- Existing files are not copied or migrated.
- Local versions continue to stream through an authorized Laravel route.
- Supabase versions receive a short-lived server-generated download URL only after route permission and company access checks.
- Upload metadata records the provider without storing credentials or public URLs.

## Live connection status

`STORAGE_READ_ONLY_VERIFIED / POSTGRESQL_RLS_BLOCKED_BY_CONFIGURATION`

On 2026-10-13 the configured server-side S3 credential successfully completed read-only `HeadBucket` and bounded `ListObjectsV2(MaxKeys=1)` probes. Anonymous bucket listing and anonymous HEAD of the sampled existing object were both denied with HTTP 403. No object name was emitted and no object or policy was mutated. Supabase Data API/Management/PostgreSQL credentials are not configured, so exposed schemas, grants, functions and RLS policies remain unverified. Full evidence and limitations are recorded in `docs/security/SUPABASE-STORAGE-RLS-SECURITY-STANDARD.md`.

## Frontend exposure review

- Production Vite output is minified and public source maps are explicitly disabled.
- No Supabase secret is accepted through `VITE_*` configuration.
- Browser HTML, CSS, JavaScript and network requests remain inspectable in DevTools by design; the security boundary is server authorization, tenant filtering and private signed access.
- Existing global headers provide `nosniff`, `SAMEORIGIN`, restrictive browser permissions and referrer controls. A strict CSP is deferred because the current application contains inline scripts and Laravel Echo integrations that require a nonce/hash migration and compatibility testing first.

## Navigation decision

No sidebar entry is created. A storage adapter is backend infrastructure, not a genuine user workspace. Existing DMS pages remain the canonical user-facing owner; operational configuration remains visible through existing ITSM runtime/secrets workspaces.

## Maintenance checkpoint

Maintenance Work Order Closeout remains **PARTIAL**. Outstanding evidence gaps are the non-divisible rounding-residue scenario and closeout transaction failure injection/rollback proof; the latest post-change authoritative full PHPUnit suite has not been run.
