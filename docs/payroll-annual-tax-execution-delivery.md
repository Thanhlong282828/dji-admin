# Phase 3E-2D — Annual Tax Reconciliation Execution & Delivery

Status: **COMPLETED** (2026-08-01).

Finalized, non-blocked Annual PIT aggregates can generate a private PDF statement using only persisted reconciliation identity/company/policy snapshots, source rows, component snapshots, validation state and settlement totals. The artifact uses a UUID filename on the private `local` disk and persists SHA-256, MIME, byte size, source checksum, generator, timestamp and generation version. Storage paths never enter the view or download URL.

Secure download is a dedicated Query. It repeats authentication, verified-email middleware, permission and Company Gate checks, then verifies file existence, `application/pdf`, recorded size, SHA-256 and `%PDF-` signature. Success and integrity failure are written to `payroll_annual_tax_access_logs`; client IP is SHA-256 hashed and user agent is bounded.

Payable creates/completes payment eligibility only and never creates a bank transfer. Refund creates one versioned refund obligation and never creates a negative payment. Balanced creates no payment/refund/accounting resource. Settlement accounting writes a separate annual journal with immutable component JSON and reconciliation/calculation/checksum trace; two lines always satisfy debit equals credit. Monthly Payroll journals remain untouched.

All delivery commands are typed and dispatched through the shared Command Bus. Download uses `DownloadAnnualTaxStatementQuery`. Unique reconciliation/version keys, row locks and intent uniqueness make statement, intent, journal and retry operations idempotent.

Browser fixture: `php artisan payroll:annual-tax-delivery-fixture`; cleanup with `--cleanup`. It is guarded to `local|testing` and a database ending `_testing` and creates payable/refund/balanced cases only in the testing database.

Browser verification: desktop 1280×800 list/detail/statement/download/downstream passed with document width equal to scroll width; secure download emitted a PDF download. Mobile 390×844 passed with page width/scroll width 390; the wide source table scrolls inside its container. Console errors: 0.

Final quality gate: Annual focused 11 tests/214 assertions; Annual delivery/process concurrency 1/16; Payroll Feature 69/958; Payroll Unit 11/37; Full PHPUnit 227/2,074. PHPStan Payroll and Payroll tests level 10: 0 errors. Pint, Blade compile/cache/clear, Vite and Docker Compose config passed. Batch 37 is Ran in production and testing.
