# Cross-project logical integration audit

Audit date: 2026-08-31. Scope: current modular monolith and `schema_admin`; no production/VPS access. `CONNECTED` means the mutation owner, canonical persistence, read projection and consuming UI are traceable in current code and data. It does not mean an external provider is verified.

## Module integration matrix

| Module/domain | Status | Canonical write source | Canonical read source | Main views / dashboard consumer | Data status | Known gap |
|---|---|---|---|---|---|---|
| Company / Branch | CONNECTED | Company/Branch command handlers and services | Company/Branch repositories and scoped read services | Company, Branch, Executive Dashboard | HAS_SUFFICIENT_DATA | None found |
| Organization | CONNECTED | Organization commands | `EloquentOrganizationReadRepository` | Unit/Position/Headcount, HR/Executive | HAS_SUFFICIENT_DATA | Headcount dashboard cache is versioned per Company |
| Employee / Assignment / Portal | CONNECTED | Employee/Lifecycle commands | Employee repositories and portal queries | Employee, Lifecycle, Portal, HR/Executive | HAS_SUFFICIENT_DATA | External identity provisioning remains separate |
| Attendance / Leave / Overtime | CONNECTED | domain commands/services and approvals | workforce repositories/read models | Workforce pages, Payroll Input, Executive | HAS_SUFFICIENT_DATA | Realtime remains fail-open |
| Recruitment / Hire / Onboarding | CONNECTED | Recruitment offer state machine and `HireCandidateService` | Recruitment/Employee/Onboarding queries | Recruitment, Employee, HR/Executive | HAS_SUFFICIENT_DATA | External calendar/e-signature not connected |
| Payroll Input / Run / Item / Payslip | CONNECTED | Payroll commands/state machine | Payroll repositories/read services | Payroll UI, Portal, Finance/Executive | HAS_SUFFICIENT_DATA | Historical finalized results immutable |
| PIT / Insurance | CONNECTED | effective policy + Payroll calculation | Payroll statutory breakdown/read services | Payslip, Tax Dashboard, Finance | HAS_SUFFICIENT_DATA | Production policy verification remains operational responsibility |
| VAT / CIT | CONFIGURATION_REQUIRED | no canonical transaction/policy runtime proven | Tax Dashboard readiness projection | Tax Dashboard | EMPTY_BY_DESIGN | No fabricated statutory totals; input VAT unsupported without purchase invoice source |
| Accounting / Branch Finance | CONNECTED | Payroll journal and management ledgers | reconciliation/financial performance services | Journals, Payroll Reconciliation, Branch Finance, Executive | HAS_SUFFICIENT_DATA | Management P&L is not statutory accounting |
| Seller / Store | CONNECTED | Commerce company-scoped services | Commerce read services | Seller Governance/Store, Company, Commerce | HAS_SUFFICIENT_DATA | External marketplace provider not required |
| Product / Category | CONNECTED | Product command → `ProductCatalogWriteService` | Product queries + `ProductMediaReadService` | Product, Service Product, public catalog/detail | HAS_SUFFICIENT_DATA | None found |
| Product Media | PARTIALLY_CONNECTED | `MediaService` → configured `MediaStorageInterface` | canonical `media_assets` projection | Product, Inventory, Warehouse, POS, public catalog/detail | HAS_SUFFICIENT_DATA | Local PASS; Cloudflare Hosted Images needs delivery hash and driver activation |
| Inventory / Movement | CONNECTED | locked `InventoryService` | balance + append-only movement readers | Inventory, Warehouse, POS, Dashboard | HAS_SUFFICIENT_DATA | No purchase-invoice costing model |
| Stock Receipt / Branch Transfer | CONNECTED | Inventory workflow services | Inventory operations reads | Inventory Operations | HAS_SUFFICIENT_DATA | Supplier/procurement domain not claimed |
| Marketplace Order / Payment / Fulfillment | CONNECTED | Checkout, payment evidence and fulfillment services | Commerce order/sales projections | Orders, Sales History, Finance, Dashboard | HAS_SUFFICIENT_DATA | No external payment/bank call |
| Sale Reversal | CONNECTED | idempotent `SaleReversalService` | reversal-aware sales/revenue reads | Sales History, Inventory, Finance, Dashboard | HAS_SUFFICIENT_DATA | Full reversal only; partial refund not claimed |
| POS / Customer / Visit / Receipt / Service | CONNECTED | `BranchPosService` | `BranchCommerceReadService` | POS, Customer History, Receipt, Sales, Executive | HAS_SUFFICIENT_DATA | CRM is operational customer history, not a separate marketing automation system |
| CRM | PARTIALLY_CONNECTED | Customer/Visit/POS owners | customer history read model | Customer file/detail, POS Dashboard | HAS_SUFFICIENT_DATA | No independent CRM campaign/lead module; no isolated fake customer data |
| Dashboard | CONNECTED | owns no mutation/table | Dashboard query handlers + `ExecutiveOperatingReadService` | Executive Dashboard | HAS_SUFFICIENT_DATA | Sensitive Payroll/Finance sections permission-filtered server-side |
| Data Quality | CONNECTED | read-only rule registry | `DataQualityAuditService` | Data Quality/Executive | HAS_SUFFICIENT_DATA | No auto-fix by design |

## Verified end-to-end chains

- Recruitment: Requisition → Candidate → Application → Interview/Offer → accepted/consumed Hire → Employee → primary Assignment → Contract → Onboarding. Dashboard hired metrics read the linked records; the seeder no longer writes a naked hired stage.
- Workforce/Payroll: Employee + effective Assignment/Branch → Attendance + approved Leave/OT → immutable Payroll Input → Run → Item → Payslip → PIT/Insurance → Finance. Dashboard owns no Payroll amount.
- Marketplace: Seller → Store → Product/Variant → reservation → Order → payment evidence → Branch fulfillment → stock movement → recognized revenue → Sales History/Finance/Dashboard. Unpaid or undelivered orders are excluded.
- POS: Customer → Visit → Product/Service snapshots → Checkout → Receipt → stock movement → recognized revenue → Customer/Sales History → Finance/Dashboard. There is no POS totals table.
- Reversal: immutable original sale → one reversal → one stock-return movement → net recognized revenue subtraction → Finance/Dashboard. Retry is idempotent.
- Inventory: opening → receipt/transfer/sale/reversal movements → current balance. Bootstrap reconciles owned rows from movement/reservation evidence and never resets operating balances.
- Product media: Product upload → request/command/handler → `ProductCatalogWriteService` → `MediaService` → provider → `media_assets` → one cover projection reused by Product, Service Product, Inventory, Warehouse, POS and public pages.

## Dashboard semantic source map

| Metric | Owner / canonical source | Scope/date | Cache | Drilldown |
|---|---|---|---|---|
| Organization/headcount | Organization Units, Positions, effective Assignments | Company/current effective date | Organization dashboard: Company version + filter hash, 10 min; Organization/Employee events increment version | Organization/Headcount |
| Recruitment pipeline/hire | Recruitment requisitions/applications/interviews/offers and linked Employee | Company/current records | none in executive aggregate | Recruitment/Employee |
| Attendance/late | Attendance records at latest available company date | Company/date | none in executive aggregate | Attendance |
| Payroll/statutory | finalized Payroll Run + Items/breakdowns | Company/period | none in executive aggregate | Payroll/Tax |
| Marketplace recognized revenue | eligible fulfilled paid Order-linked management revenue | Company/Branch/date | none | Sales History |
| POS recognized revenue | paid Receipt-linked management revenue | Company/Branch/date | none | POS Sales/Receipts |
| Net revenue | Marketplace + POS recognized sources minus sale reversals | Company/Branch/date | none | Finance/Sales History |
| Inventory | canonical balances and movements at Branch locations | Company/Branch/current | none | Inventory/Warehouse |
| Data Quality | current 60-rule snapshot | authorized aggregate | none | Data Quality |

Executive, Finance, Commerce and POS labels distinguish recognized revenue from gross order/receipt volume. Finance may also contain explicitly labelled synthetic management-ledger evidence; it is never presented as payment-provider or statutory evidence.

## Cache and duplicate-value findings

- Runtime searches found no `Cache::flush()` call; the only occurrence is a governance comment.
- Executive Dashboard and cross-module Commerce/POS/Finance reads are intentionally fresh, so Product media, completed POS sale, sale reversal and hire linkage appear without a global cache invalidation dependency.
- Organization headcount cache is scoped by Company/version/filter. Organization and Employee assignment change listeners increment the Company version.
- Product cover metadata is not copied into Product, Inventory, POS, Customer or Dashboard tables. Provider resolution stays in the Media/Product read boundary.
- No hard-coded business KPI/chart dataset was found in the audited runtime views. Local-demo management revenue is database-backed and visibly labelled synthetic.

## Remaining truthful gaps

1. Cloudflare Hosted Images is `CONFIGURATION_REQUIRED`: `MEDIA_DRIVER` is currently local and `CLOUDFLARE_IMAGES_DELIVERY_HASH` is missing. Account ID/token presence alone is insufficient for canonical delivery verification.
2. Cloudflare semantic thumbnail/medium/large variant names are not configured in this project. Current UI constrains the canonical delivery asset with context-specific CSS; provider-side variant provisioning must be defined before claiming optimized Cloudflare delivery.
3. CRM is the connected Customer/Visit/Receipt history capability; a separate campaign/lead platform is not implemented and is not faked.
4. VAT/CIT remain preparation/configuration status because canonical policy/transaction evidence required for statutory output is absent.
