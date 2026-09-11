# ERP capability gap analysis

## Audited source of truth

The audit covered the enabled Company, Commerce, Product, Inventory, POS, Payroll, Finance, Dashboard, IAM, Workflow, Audit and Data Quality runtime. Existing capability is retained; disabled/skeleton modules are not enabled to inflate coverage.

| Capability | State | Canonical evidence | Decision |
|---|---|---|---|
| Payroll double-entry posting | Implemented | accounting accounts/policies/mappings; maker-checker journals and lines; reversal and export | Reuse unchanged |
| General Ledger | Implemented in this hardening | internally posted/exported journal lines, Company/date/account scoped | Read-only projection |
| Trial Balance | Implemented in this hardening | grouped debit/credit and normal balance by account/currency | Difference must be zero |
| Income Statement | Partial | Posted Sales Revenue and COGS now join Payroll evidence; some operating expenses remain management-only | Keep explicit posted-source coverage warning |
| Balance Sheet | Partial | Inventory Asset, AP/GRNI and Payroll liabilities exist; canonical equity/opening balance does not | Never invent balancing equity |
| Cash Flow Statement | Not implemented | Payment Clearing is operational evidence, not verified bank settlement | Do not fabricate |
| Accounts Receivable / Accounts Payable | AP implemented; AR audit-only | Vendor Bill, allocation and AP aging are governed; immediate-payment sales use clearing, not a fabricated AR subledger | Add AR only when a real receivable contract exists |
| Procurement | Implemented | Vendor, PR, PO, GR, Inventory valuation, Bill, Payment and accounting use canonical source lineage | Maintain maker-checker, period lock and idempotency |
| Inventory costing / COGS | Partial | quantities and movement ledger exist; no canonical valuation-layer policy | Do not invent cost entries |
| Fixed assets | Partial | Branch management asset/depreciation source exists; no GL asset posting | Keep management reporting separate |
| Branch P&L | Implemented as management reporting | recognized revenue, Payroll, rent, OPEX and depreciation | Never call statutory accounting |
| VAT/CIT | Configuration required | no verified transaction tax snapshot/purchase invoice/CIT posting source | Fail closed |

## Implemented slice

`GET /admin/finance/financial-statements` uses the existing `payroll.accounting.view` permission. The controller dispatches `GetFinancialStatementsQuery`; `FinancialStatementReadService` performs bounded grouped SQL over posted journals. It does not query per journal/account, perform writes, or cache sensitive accounting objects.

Server-side Company access prevents cross-tenant reads. The page exposes General Ledger, Trial Balance, source-scoped Income Statement and Balance Sheet sections, and links back to the immutable Journal evidence. A dedicated contextual guide explains the coverage boundary.

## Remaining sequence

Revenue, COGS, Procurement/AP source posting, accounting periods and reversal rules are now explicit. Certification remains bounded: Income Statement is PARTIAL until all operating sources post to GL; Balance Sheet is PARTIAL without equity/opening evidence; Cash Flow is NOT_IMPLEMENTED without verified cash/bank settlement.
