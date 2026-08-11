# Enterprise Test Engineering

Phase 3F-20 establishes a read-only governance layer over the existing regression estate. It does not replace domain tests, weaken real MySQL races, or introduce a database table. Canonical entry points are `operations:test-readiness`, `operations:test-quality`, and `APP_ENV=testing DB_CONNECTION=mysql DB_DATABASE=schema_admin_testing php artisan test`.

The quality snapshot inventories PHPUnit/browser files, maps critical invariants to executable evidence, reports known orphan workers and declares isolation contracts. `/admin/operations/test-quality` renders that snapshot for `operations.view`; it cannot launch tests or mutate state.

Release gates are tiered: static and architecture checks first, focused application tests next, concurrency serially, exact browser checks in an isolated browser database, and full regression before release acceptance. Timing is evidence, not a portable SLA.

## Phase 3F-20 completion evidence

- Inventory after implementation: 149 PHPUnit files, seven browser scripts and 477 PHPUnit tests (128 Unit, 289 Feature, 60 Concurrency).
- Critical invariants: 12 registered, 12 covered, zero file-level gaps.
- Concurrency: 60 tests, 486 assertions, 102,306 ms, exit 0; known workers before/after: 0/0.
- Stability subset: 3/3 passes; each run 35 tests, 1,675 assertions, one documented production-only skip.
- Full regression: 477 tests, 476 passed, 4,881 assertions, one skipped, 1,254,948 ms, exit 0.
- Category timings: Unit 128 tests/1,940 assertions/23,063 ms; Architecture 22 tests/1,470 assertions/2,803 ms; Security 19 tests/22 assertions/9 ms; browser exact matrix approximately 49 seconds. Feature timing is included in the canonical full run and was not rerun as another long standalone gate.
- Browser: 18 pages at 1280×800 and 390×844, zero document overflow, console warnings/errors or page errors; Operations contains 12 permitted children.
- Repository PHPStan level 10: zero errors. Affected-test PHPStan, Pint, Blade, Vite, Composer validate/audit/autoload, npm runtime audit, secret scan, Swagger generation, CI static validation and Docker config: PASS.
- No migration. All four canonical databases remain 61 migrations / 230 tables. Production business counts are unchanged and browser fixture cleanup returned zero owned rows.
