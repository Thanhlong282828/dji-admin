# Payroll Adjustment Quality Gate

## Phase 3E-1B testing database audit

Guard verified `mysql / 127.0.0.1:3306 / schema_admin_testing`; password was not logged. The database contains no business fixtures (`companies`, `users`, `payroll_runs`, `payroll_adjustment_requests`, and `permissions` all zero after cleanup). All migrations 1–32 and their tables are now consistent. The earlier missing `companies`/existing `permissions` state was transient partial test bootstrap state after an interrupted multi-command run. Since the schema is currently reproducible and clean, no destructive drop/recreate was performed. Adjustment lifecycle smoke test passed 2 tests/34 assertions. Adjustment create/create process race passed 1 test/4 assertions with one active request and one snapshot. Full concurrency then exposed an existing cleanup deadlock deleting notifications; cleanup now uses Laravel transaction deadlock retry.

- Additive migration: `2026_07_29_000020_create_payroll_adjustment_foundation.php`, production batch 32.
- Production before/after: Payroll/Adjustment/Payslip/Payment/Accounting business rows all remain zero.
- Focused HTTP lifecycle: finalized-only source, snapshots/checksum, idempotency, cumulative delta, maker-checker, tenant boundary, positive eligibility, negative recovery and historical item/run immutability.
- Static gate: Payroll PHPStan level 10 must report zero; Pint, Blade cache, Vite and Docker Compose config must exit zero.
- Live two-browser Reverb: `NOT_VERIFIED` until tested with two authenticated sessions.

## Phase 3E-1C1 downstream schema compatibility — COMPLETED

- Additive migration `2026_07_29_000021_complete_payroll_adjustment_downstream_schema.php` ran as production batch 33 after production/testing `--pretend` review.
- Payment items retain regular Payroll behavior and now support an explicit nullable Adjustment source with request/run/historical item/calculation-version trace.
- Accounting journals and normalized sources retain regular Payroll behavior and now support Adjustment request/run/source trace; historical, corrected and delta amounts are signed BIGINT minor units.
- The legacy `payroll_adjustment_payslips` table remains named as-is and now carries private statement storage, MIME, byte size, SHA-256, snapshot SHA-256, generation actor/time/version metadata.
- Downstream intents now carry idempotency, retry timestamps/count, completion/failure metadata and explicit nullable foreign keys to each artifact type.
- Production business counts before/after are identical: all nine audited Payment/Accounting/Adjustment tables contain zero rows. No Payment Item, Journal, Statement file or seed was created.
- Compatibility test: 5 tests / 30 assertions, including the source-shape domain invariant. Regressions: Payment 3/52, Accounting 4/64, Adjustment lifecycle 2/34.
- Phase 3E-1 remains `PARTIAL`; actual downstream execution belongs to C2 and race/responsive/full E2E verification belongs to C3.

## Phase 3E-1C2 downstream integration

C2 adds real Adjustment Payment, balanced Accounting and private PDF Statement artifacts without adding or editing a migration. Production `schema_admin` business-row counts and private artifact files were captured before and after and remained unchanged; all fixtures ran on isolated MySQL testing schemas.

Focused evidence: Payment 2 tests/31 assertions, Accounting 1/34, Statement 1/22, retry 1/17, C1 schema 5/30, regular Payment 3/52, regular Accounting 4/64, Adjustment lifecycle 2/34, Payroll Feature 51/670 and Unit 30/75. Payroll application plus new Feature/support code pass PHPStan level 10 with zero errors; Pint, Blade compilation, Vite and Docker Compose configuration pass. Full-suite and manual-browser evidence are recorded from their actual terminal/browser results and are not inferred from focused tests.

Full Feature completed with 146 tests/1,401 assertions. Full PHPUnit completed on the required `schema_admin_testing` MySQL database with 197 tests/1,688 assertions. The first full attempt on `schema_admin_c2_final_testing` correctly failed only the explicit concurrency database-name guards; it was not reported as a product PASS. The canonical rerun exited zero.

Desktop browser verified the authenticated Adjustment list at `company_id=1`, its real Create route, explicit production empty state and zero console errors. Production contains no Adjustment and was deliberately not seeded, so the real detail cards/retry/download could not be exercised in a browser session. Their HTTP behavior and file contents are covered by isolated integration tests, but the requested desktop detail/download browser gate remains `NOT_VERIFIED`. Therefore Phase 3E-1C2 is reported `PARTIAL` rather than overstated as completed. Responsive 390 × 844 remains `NOT_VERIFIED IN C2`; process concurrency and final E2E remain C3 scope.

## Phase 3E-1C3 final completion gate

C3 closes the remaining aggregate-race and browser gaps without a migration. The isolated MySQL process suite covers terminal lifecycle collisions, Payment/Accounting/Statement create/create and completed retry/retry. Result: 23 tests / 145 assertions. A downstream intent in `processing` is an active lease: a concurrent worker exits without constructing a second artifact; failed/blocked intents remain retryable and completed/not-applicable intents are no-ops.

The guarded `payroll:adjustment-browser-fixture` command accepts only `local|testing` plus a database ending `_testing`. Positive and negative scenarios generated real applied Adjustments and downstream artifacts in `schema_admin_browser_testing`, never `schema_admin`. Desktop detail, secure PDF download, missing-permission 403, foreign-company 404, and a real 390×844 layout were browser-verified. Mobile document width was 390/390; wide data stays inside an explicitly scrollable table region. Fixture cleanup removed two companies, six users and two PDF files; zero marked companies, Adjustments or Statements remained.

Browser evidence used `/admin/payroll/adjustments/2?company_id=4` with fixture reference `ADJ-20260729-EEBWPUPX`. At desktop 1280 px, detail cards, timeline, +250,000 VND delta, draft Payment, balanced Journal and the secure Statement action rendered without console errors. At 390×844, `document.documentElement.scrollWidth/clientWidth` was `390/390`; hero bounds were 42–348 px, download bounds 43–163.4 px, and the table's own container reported `overflow-x:auto`. List, Create and Detail share the same responsive admin surface; the Detail route was exercised end-to-end. A browser viewport image was captured in the task evidence but no binary screenshot was committed to the project.

Production remained read-only and all audited Adjustment-linked business counts stayed zero. Live two-session Reverb remains `NOT_VERIFIED`; safe event payload and shared refresh source contracts retain automated coverage.

Final C3 commands exited zero: focused Adjustment 9/154; MySQL process concurrency 23/145; Payroll Feature 53/689; Unit 30/75; full Feature 148/1,417; full PHPUnit 208/1,769; PHPStan level 10 0 errors; Pint, Blade, Vite and Docker Compose config passed. Migrations through batch 33 remain `Ran` and exactly eight Adjustment routes are registered. `Phase 3E-1C3 — COMPLETED`; `Phase 3E-1 — COMPLETED`.
