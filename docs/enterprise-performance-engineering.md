# Phase 3F-18 Enterprise Performance Engineering

The platform now has a typed, read-only performance snapshot, safe audit command and permission-protected dashboard. Phase 3F-18 adds no business module and no migration.

## Operational surfaces

- `php artisan operations:performance-audit [--json]`: query/cache/index/export finding summary and SHA-256 snapshot checksum.
- `php artisan performance:seed-testing-data --rows=5000 --measure`: deterministic testing-only Employee dataset and bounded export evidence. It refuses non-testing databases; `--cleanup` removes only its deterministic company/rows.
- `/admin/operations/performance`: `auth`, `verified`, `operations.view`; aggregate metadata only.

The dashboard shows HTTP percentile signals composed from observability, estimated schema size, index-entry count, queue state, export strategies, cache governance and typed findings. It contains no SQL binding, employee name, salary, tax, bank, secret or credential.

## Current optimization

Employee list queries select only display/relation keys. Employee export changed from relation-N+1-prone `cursor()` access to 500-row eager-loaded `lazyById()` windows. At 5,000 synthetic employees it processed all rows with 41 SELECTs, 460.54 ms local duration, 8 MiB memory delta, 57,147,392-byte peak and a deterministic output checksum. These are local testing observations, not production guarantees.

## Completion evidence

- Focused performance/navigation: 14 tests / 181 assertions, exit 0. Full PHPUnit: 457 tests, 456 passed, 4,750 assertions, one skipped, exit 0.
- Repository and affected-test PHPStan level 10: zero errors. Pint, Blade, Vite, Composer/npm audit, secret scan and base/production/runtime Compose validation passed.
- Exact Chrome: 16 pages at 1280×800 and 390×844, overflow/console/page errors zero. Operations has nine children and remains server-side permission filtered.
- No migration. Production/development/testing/browser-testing remain 61 migrations and 230 tables; testing/browser performance fixtures cleaned.
- Production before/after business counts are identical. No production load, index mutation, seed or external request occurred.

Phase 3F-18 status: **COMPLETED**.
