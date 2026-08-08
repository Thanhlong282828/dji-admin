# Phase 3E-3 — ERP Integration Foundation

Status: **COMPLETED**. Security, MySQL process concurrency, secure artifact delivery and the exact responsive browser gate are verified. Phase 3E-3G UI and Phase 3E-3 overall are completed; real SAP/Odoo/API integration remains explicitly out of scope.

## Completion evidence — 2026-08-01

- ERP Connection list/create/update/detail and effective-dated Mapping Rule list/create/update/detail are connected through QueryBus/CommandBus. Credential references are encrypted and never hydrated into a ViewModel.
- Updating a mapping supersedes the prior row and creates a new version. Export items retain the external-account and dimension snapshot plus per-item checksum.
- Secure artifact download verifies state, private disk, MIME, stored size, SHA-256 and UTF-8 CSV signature. Success and blocked attempts write `erp_artifact_download` access logs.
- MySQL process race: create/create, execute/execute, retry/retry and reverse/reverse passed (1 test, 15 assertions). Result: one intent/snapshot/artifact/reference; attempt remains 1; checksum/path remain unchanged; one reversed terminal log.
- ERP focused/security/concurrency suite: 8 tests, 46 assertions passed. Accounting regression: 5 tests, 98 assertions passed. Full Payroll Feature and full PHPUnit exited 0.
- PHPStan Payroll level 10 and ERP tests level 10: 0 errors. Pint, Blade compile, Vite and Docker Compose config passed.
- Browser desktop viewport **1280×800 VERIFIED**: ERP Connections, Mapping Rules, Export list and Export detail render the sidebar, header, breadcrumbs, table/action controls, lifecycle timeline, artifact integrity card and secure access log without document overflow. Secure CSV download passed its integrity checks and created one `erp_artifact_download / success` access-log row. Console errors/warnings: `[]`.
- Browser mobile viewport **390×844 VERIFIED**: all four ERP pages report `document.documentElement.scrollWidth === document.documentElement.clientWidth` (`390 === 390`). The admin menu is collapsed by default and opens through its accessible toggle; tables scroll inside `.table-scroll`, cards stack inside the viewport and action buttons wrap without overflow. Console errors/warnings: `[]`.

## Architecture

`payroll_accounting_journal_lines` is authoritative. `CreateErpExport` snapshots exact minor-unit lines plus account/dimension snapshots, resolves company-scoped ERP mappings and stores SHA-256 checksums. It never reads live workforce or recalculates Payroll.

Lifecycle is `draft → validated → exporting → exported → reversed`; `failed → retry → exporting` is supported. Active intent and `(company_id, idempotency_key)` are unique. Creator cannot validate; validator cannot execute. Every write locks the export/intent inside a transaction.

`ErpAccountingAdapter` is provider-neutral. Phase 3E-3 binds only `GenericCsvErpAdapter`; SAP/Odoo are intentionally not implemented and no external request is made. CSV is UTF-8, formula-neutralized and stored on the private local disk under an opaque UUID path with MIME, size and SHA-256 checksum metadata.

## Security

All routes use `auth`, `verified`, route permissions and the existing `workforce.company` Gate. Queries and commands receive verified `company_id`; all record lookup is scoped by company, yielding 404 for foreign records. ERP permissions are:

- `erp.integration.view`
- `erp.integration.connection.manage`
- `erp.integration.export.create`
- `erp.integration.export.validate`
- `erp.integration.export.execute`
- `erp.integration.export.retry`
- `erp.integration.export.reverse`

Connection schema permits only an encrypted/reference credential locator. Password and token columns do not exist.

## Completion boundary

- Phase 3E-3E Security: **COMPLETED**.
- Phase 3E-3F Concurrency: **COMPLETED**.
- Phase 3E-3G UI: **COMPLETED**.
- Phase 3E-3 Overall: **COMPLETED**.
- SAP/Odoo and real ERP calls remain explicitly out of scope; no Government Filing work was started.
Integration Hub bridge: an already-exported immutable ERP artifact can be queued through `EnterpriseDomainIntegrationBridge::queueErpExport`. ERP remains lifecycle owner; the Hub owns only transport attempts/status. The transport payload contains artifact UUID/name/MIME/size/checksum and never the private storage path.
