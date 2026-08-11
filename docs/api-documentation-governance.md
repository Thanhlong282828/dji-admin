# API Documentation Governance

Phase 3F-21 adds deterministic route-to-spec governance. `POST /api/v1/companies` now documents all six required FormRequest fields, and a normalized sanitized baseline/checksum drives the early CI contract gate. API Documentation remains Swagger UI; API Contracts is a separate read-only governance dashboard.

The application uses `darkaonline/l5-swagger` 11.1.0 with `zircote/swagger-php` 6.4.0. The Swagger UI is `/api/documentation`; the generated OpenAPI JSON is `/docs`. Generate it with `php artisan l5-swagger:generate`. Both routes and all package asset routes run through `web`, `auth`, `verified` and `permission:api.documentation.view`; the specification is never left public behind a protected UI.

The dedicated permission is assigned to Super Admin through the idempotent `RolePermissionSeeder`. Sidebar authorization is server-side: API Documentation is the tenth Operations child, but is removed from HTML without `api.documentation.view`. The other nine Operations pages retain `operations.view`. A documentation-only actor may see an Operations group containing only the permitted API Documentation child.

The OpenAPI source is `app/OpenApi/EnterpriseApi.php`. Its server is the current origin (`/`), and its bearer scheme describes the existing Sanctum boundary without embedding a token. The generated document contains 11 paths and 24 HTTP operations covering all 11 distinct registered application API paths: Content, Company, Organization, Integration, Onboarding, Performance and Recruitment. Employee, Attendance, Payroll, Workflow, Notification, IAM and Audit currently expose admin web routes rather than API routes and are deliberately not represented as fictional APIs. The Onboarding/Recruitment 410 bridges and Performance redirect bridge are documented as they actually behave.

Examples must remain synthetic. Specifications must not include passwords, tokens, credentials, bank/tax values, private keys, private paths or production identifiers. Route/config cache verification and a feature test protect the UI and JSON consistently in local, testing and production configuration; no production database access is needed to prove this policy.

## Restored route evidence

The previous `/docs` response was 404 because the L5-Swagger generated file `storage/api-docs/api-docs.json` did not exist. `/api/documentation` was the actual UI route and was anonymously reachable under package defaults. Publishing application config, generating a real spec and applying the dedicated middleware corrected both gaps without patching vendor code or adding a dependency.

Exact Chrome automation at 1280×800 and 390×844 loads the UI and protected JSON from the Operations sidebar with no HTTP 404, overflow, console warning/error or page error. Local development verification with `admin@local.test` also loads the OAS 3.0 UI successfully.

Phase 3F-19 contract governance treats backward-compatible optional fields/paths as additive, and removal, required-field changes, semantic response changes or auth weakening as breaking. Breaking changes require version/deprecation evidence and a migration window. The current 11 paths/24 operations remain truthful; no v2 or fictional module API was added.
