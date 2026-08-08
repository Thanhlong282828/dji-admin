# Phase 3B-1 closure record

Current status: `COMPLETED`. The complete required test matrix returned exit code 0. Live two-browser verification remains an explicitly documented operational `NOT_VERIFIED`; authorized HTTP fallback and the realtime source/security contract are verified.

Verified in Phase 3B-1G:

- Partial notification retry: successful database channel remains at one attempt; failed broadcast retries to sent at two attempts; one database notification.
- Employee and component CSV: real streamed HTTP, UTF-8 BOM, stable ordering, exact headers, integer minor units, formula neutralization, sensitive-field exclusion, permission and tenant isolation.
- Payroll Run fragments: six authorized tenant-scoped partials share initial-page templates.
- Realtime source contract: shared Echo, allowlisted scopes/payload, 250 ms debounce, per-region abort, company/run/version filtering, DOM-owned URLs, no full-page reload.
- Calculation benchmark: 1,000 Items and 5,000 Breakdown rows through the production calculation service.
- Large export benchmark: 5,000 rows fully consumed through cursor-backed HTTP stream.

Deferred by scope: Tax, statutory insurance, bank payment, payslip, accounting integration, adjustments/reopen, off-cycle Payroll and complete multi-currency Payroll.

Final verified matrix (2026-07-28): Payroll Feature 12 tests/164 assertions; Payroll concurrency 7/46; Workforce concurrency 7/132; full Unit 25/61; full Feature 107/892; full PHPUnit 146/1,131. Seven module PHPStan runs and Payroll test PHPStan returned 0 errors. Pint and Vite passed. Routes: 246 total, 16 Payroll. Migrations remain batch 1–22. Production connection was read-only verified as MySQL `schema_admin`; Payroll Period/Run/Item/Component counts were all zero. Docker Compose config is valid and Docker Server 29.5.3 was reachable.
