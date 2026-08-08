# Payroll HTTP security matrix

| Surface | Guest | Missing permission | No company access | Foreign record in authorized company | Authorized |
|---|---:|---:|---:|---:|---:|
| Employee CSV | redirect | 403 | 403 | 404 | 200 |
| Component CSV | redirect | 403 | 403 | 404 | 200 |
| Six Run fragments | redirect | 403 | 403 | 404 | 200 |
| Payroll Input list/create | redirect | 403 | 403 | n/a | 200 |
| Payroll Input detail/actions | redirect | 403 | 403 | 404 | 200/redirect |

Authority is derived from `company_user_access`, Spatie permission and record policy. Query-string `company_id` is context only and cannot grant access. Export repositories scope both Run and rows to the same company. Foreign employee/component filter IDs produce an empty export and do not reveal foreign existence.

CSV strings beginning with `=`, `+`, `-`, `@`, tab or carriage return are prefixed with an apostrophe by the shared `CsvCellSanitizer`. Money is exported as deterministic integer minor-unit strings. Bank, tax, insurance, idempotency and raw metadata fields are excluded.

Tax administration routes require `payroll.tax.view`, `payroll.tax.policy.create`, `payroll.tax.policy.update` or `payroll.tax.profile.manage`, plus authenticated verified sessions and `workforce.company` authorization. Lists are tenant-filtered, foreign policy/employee/profile IDs are 404, and Tax IDs are masked in ViewModels.
| Payroll Period list/create | `payroll-period.view/create` | Company access + viewAny/create Policy | Foreign Company 403 |
| Payroll Period show/edit | `payroll-period.view/update` | company-scoped record Policy; draft-only update | Foreign record 404 |
| Payroll Period open/lock/close | `payroll-period.open/lock/close` | record Policy + state machine + transaction | Invalid state 403/validation error |
| Payroll Period delete | `payroll-period.delete` | draft record Policy + zero Snapshot/Run dependency | Dependency validation error |
| Payroll Input create options | `payroll-input.generate` | same-company collecting/review query only | Foreign/closed/draft excluded |
| Payroll Run list/detail | `payroll.run.view` | Company access + Run Policy + tenant query | Foreign Run 404 |
| Payroll Item detail | `payroll.item.view` | Company access + parent Run Policy + item scoped to Run | Foreign/manual item ID 404 |
| Calculate/recalculate | `payroll.run.calculate` | Run Policy + state/version + exact ready Snapshot | Foreign 404; invalid transition controlled |
| Submit | `payroll.run.submit` | Policy + calculated/current aggregate consistency | Foreign 404; invalid state controlled |
| Approve/reject | `payroll.run.approve/reject` | Policy + submitted state; approve maker-checker; reject reason | Foreign 404; actor/state conflict controlled |
| Finalize | `payroll.run.finalize` | Policy + approved/current aggregate consistency | Foreign 404; finalized immutable |

## Adjustment downstream endpoints (Phase 3E-1C2)

| Surface | Permission | Enforcement |
|---|---|---|
| Execute Adjustment Payment | `payroll.adjustment.payment.create` | authenticated/verified, Company Gate, company-scoped applied Adjustment, positive persisted net delta |
| Execute Adjustment Accounting | `payroll.adjustment.accounting.create` | authenticated/verified, Company Gate, company-scoped applied Adjustment and configured mappings |
| Generate Adjustment Statement | `payroll.adjustment.statement.generate` | authenticated/verified, Company Gate, immutable applied source |
| Retry downstream intent | `payroll.adjustment.downstream.retry` plus artifact permission | company-scoped intent, failed/blocked lifecycle, completed intents skipped |
| Download Adjustment Statement | `payroll.adjustment.statement.download` | company-scoped query, SHA-256/size/MIME/PDF signature verification, private no-store stream and access log |

Foreign Adjustment IDs inside an authorized Company context resolve as 404. Missing permission or Company access resolves as 403. No route exposes storage paths, account plaintext, raw snapshots or accounting internals.
# Payroll Input employee routes

`GET /admin/payroll/inputs/{snapshot}/employees` and `GET /admin/payroll/inputs/{snapshot}/employees/{employeeInput}` require `auth`, `verified`, `payroll-period.view`, verified Company access and `payroll-input.view-snapshot`. Repository queries scope company, snapshot and employee-input ID, returning 403 for inaccessible Company context and 404 for foreign records inside an authorized context.
# Insurance administration

Policy/profile routes require verified authentication, `payroll.insurance.*` permission and `workforce.company` authorization. Cross-company parent IDs are rejected/hidden. Insurance identifiers are masked in list output.
## Payment endpoints

Payment Account and Batch routes require `auth`, `verified`, the matching `payroll.payment.*` permission and `workforce.company` access. Repository/service queries scope every employee, Run, Batch and download by company. Foreign records resolve as 404; lack of company access or permission is 403. Account plaintext and private storage paths are never returned by read views or broadcast payloads.
# Payroll Accounting HTTP matrix (Phase 3D-3)

| Surface | Read permission | Write permission | Company enforcement |
|---|---|---|---|
| Chart of Accounts | `payroll.accounting.view` | `payroll.accounting.policy.manage` | middleware + `workforce.company` Gate + scoped query |
| Policies | `payroll.accounting.view` | `payroll.accounting.policy.manage` | middleware + Gate + scoped query |
| Mappings | `payroll.accounting.view` | `payroll.accounting.mapping.manage` | middleware + Gate + scoped references |
| Journal list/detail | `payroll.accounting.view` | — | middleware + Gate + `(company_id,id)` lookup |
| Create/validate/review | — | create/review permission | finalized Run + company lock/state |
| Approve/reject/post | — | action-specific permission | company lock/state + maker-checker |
| Export/download/reverse | — | action-specific permission | scoped Journal + integrity/state checks |

All mutate operations use POST/PUT. Guest redirects to login, missing permission/company access returns 403, and a foreign record ID is not resolved outside the authorized company.
# Adjustment routes

All `/admin/payroll/adjustments*` routes use `auth`, `verified`, explicit permission middleware and `workforce.company` authorization. Reads/writes are company-scoped; foreign records return 404 after company authorization. Calculate/update/review/approve/apply use their matching `payroll.adjustment.*` permission, and maker self-approval is blocked in the locked domain transaction.

C3 browser evidence on `schema_admin_browser_testing`: an authenticated same-company user without `payroll.adjustment.statement.download` received 403; after granting only the required read/download permission and access to a second fixture company, requesting the first company's Adjustment through the second company context returned 404. An authorized same-company download returned a private PDF and wrote `statement_download` access audit rows without exposing its storage path.

## Designed annual PIT surface

Not implemented in 3E-2A. Future annual routes require auth + verified + dedicated `payroll.annual_tax.*` permission + `workforce.company`. Company access/permission failure is 403; a foreign reconciliation/source inside an authorized context is 404. Tax ID, dependent evidence, full identity snapshots, private paths, source payloads and tax amounts must never enter broadcast payloads. Document download reauthorizes tenant/permission and verifies MIME, size and SHA-256.

## Phase 3E-2B security boundary

No Annual Tax route, Controller, API, permission or download surface was added. Internal Commands require explicit Company/Employee/actor identifiers; service/repository lookups are Company scoped, and a foreign Employee cannot create a draft. Source collection repeats Company and Employee constraints on finalized Payroll and applied Adjustment queries. Snapshot payloads, tax amounts and checksums are not broadcast or exposed publicly. HTTP authorization belongs to 3E-2C.

Phase 3E-2C routes require auth, verified, action permission and `workforce.company`. Detail/query lookup includes Company ID, so foreign reconciliation identifiers return 404 inside an authorized context. The UI masks raw snapshots and exposes no download/storage path. Maker-checker remains a locked domain rule independent of middleware.
# Annual PIT Phase 3E-2C2

List/detail require `payroll.annual_tax.view`; create requires `.create`; collect/calculate require `.calculate`; validate, submit, review, approve, finalize, reject and supersede each require the same-named permission. Every controller action also authorizes `workforce.company`. Missing permission is 403; an ID outside an authorized Company is 404. Mutation routes are POST and CSRF-protected by the web middleware group.

Automated proof: dedicated route middleware matrix, missing-permission 403, foreign reconciliation 404 and no cross-company source mutation pass. Remaining C2 proof gap: successful, no-company-access and foreign-company cases have not yet been exercised for every action as one complete data-driven matrix.

Completion proof: creator cannot review their own submission; reviewer cannot approve; approver cannot finalize; a distinct finalizer succeeds. Authorized foreign detail and mutation identifiers return 404 before data mutation, while same-company actors missing the action permission receive 403. Backend policy/company/state checks remain independent from Blade button visibility.

Phase 3E-2D routes add dedicated statement generate/download, payable/refund intent, settlement journal and retry permissions. Download does not accept a storage path. It resolves Company + reconciliation + document IDs server-side, validates PDF signature/MIME/size/checksum, returns private/no-store headers, and logs success or integrity failure.
# ERP Integration security matrix — Phase 3E-3

| Surface | Permission | Same company | Missing permission | Foreign record under verified company |
|---|---|---:|---:|---:|
| Connections list/detail | `erp.integration.connection.view` | 200 | 403 | 404 |
| Connection create/update | `erp.integration.connection.manage` | allowed | 403 | 404 |
| Mapping list/detail | `erp.integration.connection.view` | 200 | 403 | 404 |
| Mapping create/version | `erp.integration.connection.manage` | allowed | 403 | 404 |
| Export list/detail/download | `erp.integration.view` | allowed | 403 | 404 |
| Export lifecycle actions | action-specific ERP permission | allowed when state/maker-checker valid | 403 | 404 |

## ERP responsive browser evidence

- Desktop viewport `1280×800`: **VERIFIED** for Connections, Mapping Rules, Export list, Export detail, lifecycle, artifact integrity, access log and secure download. No horizontal document overflow and no console warning/error.
- Mobile viewport `390×844`: **VERIFIED** for the same company-scoped pages. `document.documentElement.scrollWidth === document.documentElement.clientWidth` on every page; mobile navigation collapses/opens correctly, tables use an internal horizontal scroll container, cards stack and actions wrap.
- Secure artifact download: **VERIFIED** with an authenticated, verified, permitted same-company actor. The file download completed and the detail read model exposed `erp_artifact_download` with result `success`; no private storage path was rendered.

Credential plaintext is accepted only at the write boundary, encrypted immediately and excluded from read repository projections, activity properties and Blade. Artifact download returns no storage path and records both successful and blocked integrity attempts.
