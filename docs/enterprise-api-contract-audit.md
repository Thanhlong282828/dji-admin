# Enterprise API Contract Audit

`php artisan route:list --path=api` reports 44 route records including package internals. Excluding Horizon and Swagger infrastructure leaves 21 governed application route records representing 24 HTTP operations over 11 paths. PUT/PATCH alternatives count as separate operations.

The generated OpenAPI document also contains 11 paths and 24 operations: undocumented actual 0, orphan documented 0, duplicate operationIds 0. Actual tags are Content, Company, Organization, Integration, Onboarding, Performance and Recruitment. Modules without API routes are not represented by fictional paths.

The material gap was `POST /api/v1/companies`: its FormRequest requires code, name, country_code, timezone, currency_code and status while OpenAPI declared an untyped object. Phase 3F-21 documents that actual schema with synthetic examples. Tenant evidence includes organization `company_id` and webhook `company` parameters.

Onboarding/Recruitment 410 bridges and the Performance redirect bridge remain documented as implemented. No HTTP semantics, production data or database schema changed.

Final normalized OpenAPI checksum: `fc022e2981b808241cfb48a73d54e6af5d971f66239a79ba0b6a58223f844abc`. Breaking findings 0, additive findings 0, validation mismatches 0, response mismatches 0, tenant findings 0 and unsafe example findings 0 against the Phase 3F-21 baseline.

Browser completion used only `schema_admin_browser_testing`, the guarded Audit fixture and local Chrome. Dashboard, API Contracts, Architecture, Test Quality, Performance and Security Assurance returned 200 at both exact viewports. Sidebar navigation loaded Swagger UI, `/docs` returned parseable OpenAPI 3.0.0 with 11 paths/24 operations, and the fixture/server were cleaned/stopped.
