# Phase 3D-3 — Payroll Accounting Posting Foundation

## Scope and terminology

This phase converts one finalized Payroll Run calculation version into a balanced internal Payroll Journal. `internally_posted` means the journal passed SCHEMA's internal controls; it does **not** mean an external ERP accepted or posted it. There is no ERP adapter in this phase.

## Authoritative persisted sources

- Salary earning/other deduction: current `payroll_item_components` belonging to current persisted `payroll_items`.
- Employee insurance, employer insurance, PIT and net salary: authoritative integer minor-unit columns on `payroll_items`.
- Calculation version: locked from the finalized `payroll_runs.calculation_version`.
- Organization dimension: captured Payroll Input organization snapshot; cost center is resolved once through that captured organization unit and copied into Journal Line snapshot columns.
- Policy and mapping effective date: `payroll_periods.period_end`, never the current date.

The service never recalculates from live Attendance, Leave, Overtime, salary assignment, tax policy or insurance policy. A later live-source change cannot mutate an existing Journal.

## Configuration

`accounting_accounts` is the company-scoped Chart of Accounts. Only active, effective, postable accounts with compatible currency can resolve a posting. No account IDs or production account codes are hard-coded or seeded.

`payroll_accounting_policies` is company-scoped, versioned and effective-dated. Active intervals cannot overlap for one policy code. A policy used by an approved/posted/exported Journal is immutable; Finance creates a new version.

`payroll_accounting_mappings` maps stable source type/code to one debit or credit account. Deterministic dimension precedence is:

1. source + cost center + organization unit;
2. source + cost center;
3. source + organization unit;
4. source default;
5. blocking error.

Duplicate active resolution keys with overlapping effective ranges are rejected.

## Journal lifecycle

`draft → validated → reviewed → approved → internally_posted → exported`

- Draft creation requires a finalized Run, a single active policy and all required mappings.
- Validate checks non-empty lines, debit/credit balance and blocking issues.
- Review records reviewer/time.
- Maker cannot approve their own Journal.
- Approve, internal post and export use row locks and state validation.
- Reject is allowed before approval and releases the active-intent key.
- One active intent per Payroll Run + calculation version is enforced by a unique database key plus Run locking.
- Repeated commands are controlled/idempotent.

## Balance and source trace

All amounts are integers in currency minor units; floats are not used. Each Journal Line has normalized rows in `payroll_accounting_journal_sources`. Tests prove each line amount equals its source-row sum, net salary payable equals `SUM(payroll_items.net_after_personal_income_tax_minor)`, and total debit equals total credit.

## Export, integrity and reversal

Export reads approved persisted Journal Lines only and writes `generic_payroll_journal_csv_v1` to private local storage. It neutralizes spreadsheet formula prefixes, persists SHA-256 checksum, MIME, size and safe access logs. Download revalidates checksum and sends private/no-store headers.

A reversal is a new linked draft Journal with swapped debit/credit lines and copied source trace. The original Journal is not edited or deleted. The reversal must pass the same review lifecycle.

## Security

- All HTTP routes: `auth`, `verified`, route permission and `workforce.company` Gate.
- Every read/write query scopes both `company_id` and record ID; foreign records resolve as 404 after company authorization.
- No payroll amounts, employee names, storage paths or mapping details are broadcast.
- Activity payloads contain safe IDs/state only; private file body is never logged.

## Enterprise UI

Black–gold screens exist for Chart of Accounts, Policies, Mappings and Journals. Journal detail includes lifecycle, balance cards, issues, lines/source counts, policy/calculation versions, integrity metadata, reversal references and state/permission-aware actions. Sidebar entries use real routes and permission visibility.

## Known limitations

- No external ERP/accounting API adapter.
- Generic CSV only; no vendor-specific import format.
- No accounting period close/calendar engine.
- No foreign-exchange conversion.
- Live two-browser Reverb remains `NOT_VERIFIED` until manually tested with two independent browser sessions.

## Retroactive Adjustment extension (Phase 3E-1C2)

An applied Adjustment creates a separate draft Journal with `source_type=retroactive_adjustment`, exact request/run/source-item/version references and normalized signed historical/corrected/delta source rows. Mapping resolution uses configured Adjustment source codes; no account ID is hard-coded. Positive deltas use the mapped direction and negative deltas reverse it, while persisted debit/credit amounts remain non-negative. The service rejects missing mappings or any unbalanced result atomically. Review, approval, posting and external export remain governed by the existing maker-checker lifecycle; no historical Journal is edited.

## Verification record — 2026-07-29

- Browser port 8001: Accounts list/create, Policies list/create, Mappings list/create and Journals list/create opened without HTTP 500 or console errors.
- Sidebar routes and production empty states were visible.
- Desktop width 1280 and mobile width 390 had no horizontal page overflow; mobile sidebar became relative and main padding adapted.
- Live two-browser realtime: `NOT_VERIFIED`.
- Payroll benchmark: 1000 employees, 1000 items, 5000 breakdowns; calculation 968.42 ms, peak delta 10 MiB. This run is above the older 876–919 ms observation but did not fail the existing performance test; Phase 3D-3 does not modify calculation queries.
