# Phase 3E-4B — Government Filing domain design

Status: **COMPLETED**.

Lifecycle: `draft → generated → validated → approved → submitted → accepted|rejected`. Submitted/accepted/rejected versions are immutable. Any correction creates a new version linked to the same Annual Tax reconciliation; an active-key and `(company, reconciliation, filing type, version)` uniqueness guard prevent duplicate active versions.

Write flow: Route → typed FormRequest → Controller → shared CommandBus → typed Command/Handler → GovernmentTaxFilingService → repository/database transaction with row lock → history/submission evidence → response.

Read flow: Route → Controller → shared QueryBus → typed Query/Handler → company-scoped read repository → typed ViewModel/download DTO → Blade or streamed XML.

The declaration generator is provider-neutral through `GovernmentDeclarationGeneratorInterface`. Phase 3E-4 binds `GenericXmlDeclarationGenerator`, which emits deterministic UTF-8 XML with canonical element ordering from persisted filing item snapshots. It performs no network request and does not implement a production digital signature.

Maker-checker: creator cannot approve; approver cannot submit. Foreign company records resolve as 404 after the company Gate succeeds for the requested tenant. Missing permission is 403. Download verifies private disk, MIME, size, SHA-256 and XML signature before streaming, then records the result without exposing the storage path.

Submission is a simulated local foundation only. Each attempt persists an incrementing attempt number plus payload/request/response hashes. Idempotency and row locks ensure create/create, generate/generate, submit/submit and retry/retry have a single winning aggregate/document/attempt.

## Delivered foundation

The implementation provides list, create, detail, lifecycle actions, immutable declaration artifact metadata, validation issues, submission attempts, lifecycle logs and access logs. Secure download verifies private-disk existence, XML MIME, stored size, SHA-256 checksum, payload checksum and XML signature before streaming; no private path is returned.

`accepted` remains a modeled terminal status for a future authenticated government response. Phase 3E-4 deliberately performs no real external submission and does not fabricate an acceptance callback. Rejection is an explicitly recorded safe local transition. Correction creates a new filing version rather than mutating submitted evidence.

## Verification — 2026-08-01

- Phase 3E-4G Government focused: 14 tests, 65 assertions, passed.
- Government lifecycle process concurrency: 1 test, 15 assertions, passed; covers create/generate/validate/approve/submit/retry races.
- Annual Tax regression: 19 tests, 295 assertions, passed.
- ERP regression: 7 tests, 41 assertions, passed.
- Accounting regression: 5 tests, 98 assertions, passed.
- Payroll Feature regression: 87 tests, 1,042 assertions, passed.
- Full PHPUnit: 250 tests, 2,200 assertions, passed.
- PHPStan `Modules/Payroll/app` and every Payroll/Government test: level 10, 0 errors.
- Pint, Blade cache/clear, Vite production build and Docker Compose config: passed.
- Browser: desktop 1280×800 and mobile 390×844 verified; secure download verified; console errors 0; mobile document width 390/390.
- Guarded browser fixture was cleaned: filing/document/submission rows 0 and generated XML removed.
- Production `schema_admin`: Government Filing/Document/Attempt, Annual Tax, Payroll, Adjustment, Payment, Accounting and ERP rows remain 0; no production artifact or external request.

Whole-repository `tests` static analysis still reports 41 pre-existing type errors outside Payroll/Government Filing. They are classified in `docs/phpstan-phase3e4-audit.md`; no baseline, ignore rule or unrelated code change was used. Phase 3E-4G and the scoped Phase 3E-4 completion gates are **COMPLETED**. Repository-wide legacy PHPStan cleanup remains a separate task.
Integration Hub bridge: approved/submitted/accepted filings use the persisted XML document and matching filing checksum. The Hub transports metadata under a deterministic source/version intent; it never regenerates XML or changes Government Filing lifecycle state.
