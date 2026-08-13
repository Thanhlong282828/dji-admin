# Enterprise Security Assurance

Environment isolation treats writable `schema_admin` as Mac local-main only, `schema_admin_testing*` as PHPUnit/integration only, and `schema_admin_browser_testing` as browser-fixture only. VPS production must use an environment-defined database. The former fourth local database is removed; no active code/config/test dependency remains.

API contract governance confirms Sanctum bearer documentation for protected APIs, explicit public Content routes, protected Swagger UI/spec routes and secret-free synthetic examples. It does not weaken controller middleware or tenant isolation.

Phase 3F-20 maps environment isolation, tenant boundaries, IAM separation and navigation authorization into the critical-invariant registry. Security fixtures remain synthetic and never target production.

Phase 3F-16 establishes a shared defensive assurance layer without adding a business module or database persistence. It inventories trust boundaries, records typed findings, hardens the Integration URL boundary, validates safe filenames and exposes a permission-protected read-only operations dashboard/command.

## Operational use

Run `php artisan operations:security-assurance --json` for the safe posture summary. Review the threat model and residual risks before enabling external HTTP delivery. The dashboard is available at `/admin/operations/security-assurance` to verified users with `operations.view`.

Accepted design gaps are not hidden: privileged MFA and a malware scanning provider require future product/infrastructure decisions. They do not weaken the existing maker-checker, private-storage, checksum or authorization controls.

## Phase 3F-16 evidence

- Route surface: 497 total; 427 authenticated; 391 explicitly privileged; 70 public/framework/auth/health routes.
- Defensive focused gate: 34 tests / 73 assertions; representative IAM, tenant, webhook, Audit, Payroll, Employee and Workflow regression exited 0.
- Full PHPUnit: 440 tests, 439 passed, 4,546 assertions, 1 skipped, exit 0.
- PHPStan repository and affected security tests: level 10, 0 errors. Pint, Blade, Vite, Composer/npm audits, secret scan and Docker configuration passed.
- Browser: exact 1280×800 and 390×844, zero document overflow and zero console/page problems; fixture cleanup returned Audit rows to zero.
- Runtime image `sha256:f7c5fc44f8133b83085208e5ee291dc7f3aa44a6a38b01706ef89d2a123edbe7`: `www-data` UID 82, no `.env`, tests or private evidence; reduced capabilities and `no-new-privileges` in the production-like Compose contract.
- Schema: no migration; all four environments remain at 61 migrations / 230 tables.
- Production counts remained unchanged and no adversarial request targeted production or an external provider.
# Phase 3F-17 accessibility security regression

Navigation labels, ARIA metadata and localStorage state contain no credentials, PII, restricted business values or permission snapshots. Backend middleware remains authoritative.

# Phase 3F-18 performance security

Performance tooling is read-only, accepts no SQL, redacts sensitive filter-key classes and exposes no bindings. The dashboard requires `auth`, `verified`, `operations.view`; synthetic load is guarded to `schema_admin_testing*`, while production remains read-only.
# API documentation boundary

Swagger UI, generated JSON and package assets are protected by `web`, `auth`, `verified` and the dedicated `api.documentation.view` permission in every environment. The generated specification uses a Sanctum bearer scheme without token examples and is checked for obvious secret material. See `docs/api-documentation-governance.md`.

# Phase 3F-19 architecture boundary

Fitness functions verify governed Domain layers do not import HTTP/Presentation types, Spatie mutations remain in `SpatieAccessProvisioningAdapter`, external HTTP remains behind Integration policy/adapter boundaries, and Operations Architecture requires `operations.view`. No secret, PII, SQL binding or source dump enters its snapshot.
# Phase 3F-22 lifecycle assurance

Destructive disposal is a high-risk fail-closed runtime flag and defaults disabled. CLI/UI are read-only, permission protected and contain no PII, secret, private storage path or mutation controls.
