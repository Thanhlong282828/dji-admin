# Phase 3E-1 — Retroactive Payroll Adjustment Foundation

## Terminology and invariant

- Historical Result is the finalized Payroll Item and its persisted components; it is never updated.
- Correction is the result that should have applied to the historical period.
- Adjustment Delta is Correction minus the current effective result.
- Current effective result = historical result + all applied deltas. Therefore a second correction to 115,000 after a first applied correction to 110,000 creates +5,000, not +15,000.
- Adjustment Run is a separate delta-only record in `payroll_adjustment_runs`; regular `payroll_runs` and `payroll_items` remain unchanged.

## Snapshot and policy

Creation locks a finalized source item and captures canonical JSON with employee/dimension snapshots, exact `source_calculation_version`, persisted components, totals, insurance policy ID/version and tax policy ID/version. Correction input is stored separately with SHA-256. Policy semantics are explicitly `recalculate_under_historical_policy`; this foundation does not claim legal Vietnam reconciliation compliance.

## Lifecycle and control

`draft → calculated → validated → submitted → reviewed → approved → applied`; submitted/reviewed may be rejected. Maker cannot approve. Reject reason is mandatory. Applied records are immutable. A unique active intent and company/idempotency key protect retries and concurrent creates.

## Downstream semantics

- Positive net delta creates `payment_eligible`; it does not change an old Payment Batch.
- Negative net delta creates a recovery obligation and never a negative bank transfer.
- Apply creates a separate Adjustment Statement snapshot; it never regenerates the original Payslip.
- Apply creates `accounting_eligible` with exact adjustment-run trace. A future adapter creates a delta-only balanced journal; historical journals are untouched. External ERP remains `NOT_CONNECTED`.

## Security and realtime

All routes require `auth`, `verified`, route permission, verified company context and company-scoped reads. Foreign IDs resolve as 404 after company authorization; missing company permission is 403. Private `company.{companyId}` broadcast contains identifiers/status/refresh scopes only, never amounts, employee name or evidence URL. Live two-browser Reverb remains `NOT_VERIFIED`.

## Known limitations

Annual tax reconciliation, government amended filing, direct bank recovery, direct bank integration, external ERP posting, evidence document storage adapter and automated recovery collection are not connected.

## Phase 3E-1C2 downstream business integration

Applying an Adjustment commits only the immutable Adjustment Run and typed downstream intents. Payment, Accounting and Statement execution is dispatched after that boundary through dedicated CQRS commands and independent retryable intent lifecycles; a downstream failure never rolls back the applied Adjustment.

- A positive delta creates one Adjustment-specific draft Payment Batch Item from the persisted `delta_net_minor`, with exact request/run/source-item/calculation-version trace and an immutable encrypted payment-account snapshot. Zero or negative net is `not_applicable`; recovery remains separate.
- Accounting creates a new draft, delta-only Journal. Signed component deltas select configured adjustment mappings and reverse debit/credit direction for negative deltas. Lines never contain negative debit/credit values and the journal must balance exactly before commit. Historical journals remain unchanged and maker-checker remains mandatory.
- The Adjustment Statement is a real Dompdf PDF on the private disk. Its immutable source is the applied request, Adjustment Run, historical/correction snapshots, components, history and recovery. Storage metadata includes MIME, byte size, SHA-256, source snapshot checksum, generation actor/time and version.
- Secure download rechecks file existence, MIME, byte size, `%PDF-` signature and SHA-256 before streaming with private/no-store headers, then records an access log.
- Retry increments attempt metadata, clears safe previous failure fields and skips completed/not-applicable intents. Errors stored for operators are sanitized and contain no stack trace, SQL or secrets.

Phase 3E-1 remains `PARTIAL`; aggregate concurrency, responsive verification and the final C3 gate are not part of C2.

Implementation and automated quality gates for C2 pass. C2 status remains `PARTIAL` only because the desktop browser could verify the production empty list but not a real Adjustment detail/retry/download without creating prohibited production business data. No production fixture was introduced to manufacture that evidence.

## Phase 3E-1C3 completion

C3 adds no migration and does not change the Adjustment business model. It closes concurrency with independent MySQL process races and closes manual UI evidence using a strictly environment/database-guarded fixture command. Detail, secure PDF download, 403/404 behavior and responsive 390×844 rendering were verified in the browser. Cleanup is marker-scoped and deletes generated private PDFs. Live two-session Reverb is the only manual item still marked `NOT_VERIFIED`; it does not weaken the tested private-channel payload and fragment-refresh contract. Phase 3E-1 status: `COMPLETED`.

Final regression evidence: Payroll Feature 53 tests / 689 assertions; Unit 30 / 75; full Feature 148 / 1,417; full PHPUnit 208 / 1,769; PHPStan level 10 zero errors; Pint/Blade/Vite/Docker Compose all pass.
