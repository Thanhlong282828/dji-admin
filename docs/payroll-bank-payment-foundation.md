# Phase 3D-2 — Bank Payment Batch Foundation

Authoritative amount is `payroll_items.net_after_personal_income_tax_minor` from the exact finalized `calculation_version`. No live payroll source is recalculated.

Employee account numbers are encrypted with Laravel Crypt and only last four digits are exposed to read models. A bank-transfer instruction requires an active, primary, verified, effective account matching payment currency.

Lifecycle: `draft → validated → reviewed → approved → released`; eligible earlier states may be rejected with a reason. The creator cannot approve. Release creates a private `generic_csv_v1_minor_units` instruction file; it does not mean submitted-to-bank or paid.

Duplicate protection combines company idempotency, an aggregate lock on the finalized Payroll Run, an active-Batch re-check performed after that lock, and a nullable database-unique `active_intent_key` for Payroll Item + calculation version. This ordering prevents two concurrent creators from leaving a second empty Batch. Rejection explicitly releases the item intent key so a replacement Batch can be created without weakening active/released protection.

CSV descriptive text uses formula-injection protection. Account identifiers remain exact CSV strings and are decrypted only in memory at the export boundary. Private files persist SHA-256, MIME, byte size and actor/time metadata. Download rechecks integrity and records access.

Reconciliation is a documented foundation only. Direct Bank API, SFTP, bank confirmation, accounting posting and actual movement of funds are not connected.

## Migration recovery evidence

The first local production-schema attempt was not recorded in `migrations` because MySQL rejected the automatically generated identifier `payroll_payment_batch_items_payment_batch_id_payment_reference_unique` (longer than 64 characters). Only the five tables owned by migration `2026_07_29_000018` were inspected; each contained zero rows and had no legacy inbound dependency. Those partial, empty tables were removed in reverse foreign-key order. The unique index was renamed to `payment_batch_reference_unique`, the migration then completed as batch 30, and all five production payment tables remained at zero business rows.

## Verification status

- Focused HTTP integration covers encrypted persistence, masking, verification, primary-date overlap, finalized-Run eligibility, exact net-after-tax amount/version, immutable snapshots, maker-checker, private CSV, injection protection, integrity download, tenant authorization, duplicate active intent and idempotent Release.
- Database protection uses MySQL constraints and transactional row locking. A two-process create/create race against `schema_admin_testing` proves one active Batch, one Batch Item and one creation activity even with different idempotency keys.
- Browser list/create/detail empty-state pages were verified on port 8001 without creating production payment data.
- Live two-browser Reverb verification: `NOT_VERIFIED`.

Final quality gate: Payment focused 3 tests / 52 assertions; Payroll Feature 35 / 440; MySQL Payroll concurrency 11 / 68; Unit 30 / 75; Feature suite 130 / 1,168; full PHPUnit 178 / 1,442. Payroll application, Payroll Feature tests and concurrency support pass PHPStan level 10 with zero errors; Pint, Blade compilation, Vite and Docker Compose configuration pass.
# Accounting boundary

Payment Batch remains independent from Accounting Journal. Accounting reads persisted Payroll Items directly and never treats a Payment CSV or bank status as its source of truth.

## Retroactive Adjustment extension (Phase 3E-1C2)

An applied positive Adjustment may create a separate draft Payment Batch Item with `source_type=retroactive_adjustment`. It references the exact Adjustment request/run, historical Payroll Item and source calculation version, snapshots the verified account instruction and uses a deterministic active-intent/payment reference. The amount is the persisted Adjustment Run net delta; live Payroll is never recalculated. Negative/zero deltas never become negative transfers. CSV output retains regular columns and adds a descriptive source marker; regular Payroll behavior is unchanged. Direct Bank integration remains `NOT_CONNECTED`.
