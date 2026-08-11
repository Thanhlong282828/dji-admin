# Enterprise API Contract Governance

Phase 3F-21 governs the existing HTTP API contract without adding business endpoints. Laravel routes and `app/OpenApi/EnterpriseApi.php` are canonical; `php artisan l5-swagger:generate` produces OAS 3, and `php artisan operations:api-contract-audit` compares both sources read-only.

The inventory contains 11 paths, 21 Laravel route records and 24 HTTP operations. Every governed operation is documented, none is orphaned, and all `operationId` values are unique. Horizon and Swagger infrastructure routes are internal and excluded from application coverage.

The CI gate verifies coverage, status evidence, representative FormRequest agreement, pagination, tenant parameters, Sanctum bearer security and secret-free examples. Compatibility is evaluated against `storage/api-docs/api-contract-baseline.json`; normalized object-key ordering prevents formatting-only checksum drift.

The protected read-only dashboard is `/admin/operations/api-contracts` under `auth`, `verified` and `operations.view`. Swagger remains a separate protected capability under `api.documentation.view`.

## Completion evidence

Focused contract/navigation/security tests pass, and full PHPUnit completed with 485 tests, 484 passed, 4,923 assertions, one documented skip and exit 0. Repository and affected-test PHPStan level 10 report zero errors; Pint, Blade, Vite, Composer audit, npm runtime audit, secret scan, CI static validation and both Compose configurations pass. Production before/after counts are identical and no migration was added.

The initial browser-tooling blocker is retained as historical evidence. It was resolved without Computer Use or a downloaded browser: `playwright-core` 1.62.1 launched the installed Google Chrome 151.0.7922.77 directly. Exact 1280×800 and 390×844 runs passed six admin pages plus Swagger/spec, 13/0 visible Operations children when open/closed, zero document overflow, console warnings/errors, page errors, 403/404/500 responses, and fixture cleanup 0/0/0. Phase 3F-21H and Phase 3F-21 Overall are COMPLETED.
