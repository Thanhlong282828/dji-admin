# Canonical operating scenario plan

This plan extends recovered local operating history; it never replaces recovered records and never writes presentation totals. Every scenario must use an owner-module application boundary and a stable business reference.

## Priorities

| Priority | Scenario | Runtime evidence | Decision |
|---|---|---|---|
| P1 | Sales → fulfillment → AR → collection → Treasury → Accounting | Complete owner services, inventory and finance boundaries exist | Implemented as Batch 1 |
| P1 | Procurement → receipt → inventory → AP → payment | Canonical runtime exists; recovered records already provide evidence | Next batch; no mutation in this iteration |
| P2 | Attendance/Leave/OT → a new payroll period | Runtime exists, but finalized recovered payroll is immutable | Plan only; requires safe open-period selection |
| P2 | Expense/Asset/Budget | Runtime exists with evidence limitations | Plan only |
| P3 | Project/Maintenance/DMS | Runtime exists but data is optional and must attach to an actual owner event | Do not populate merely for menu density |
| Blocked | Service procurement acceptance | No canonical service-acceptance boundary; accounting policy unresolved | `NOT_IMPLEMENTED` / `POLICY_REQUIRED`; no migration 101 |

## Batch 1 — completed sell-side history

| Field | Paid sale | Open receivable |
|---|---|---|
| SCENARIO_ID | `OPS-SALES-PAID-2026-06` | `OPS-SALES-AR-2026-07` |
| BUSINESS_PURPOSE | Fulfilled enterprise sale collected in full | Fulfilled enterprise sale with overdue AR |
| OWNER_MODULE | Commerce | Commerce |
| COMPANY | Existing `SCHEMA-TECH-VN` company | Existing `SCHEMA-TECH-VN` company |
| BRANCH | Existing HCM branch resolved through inventory location | Same |
| COST_CENTER | Not attributed; sell-side cost-center revenue evidence is not authoritative | Same |
| DATE_RANGE | 2026-06-10 → 2026-06-28 | 2026-07-08 → 2026-08-14 due date |
| UPSTREAM | Existing customer + company-owned product variant + positive-cost stock | Same |
| COMMAND/USE_CASE | `SalesReceivableService` canonical intents | Same |
| STATE_TRANSITIONS | quotation draft→accepted→converted; SO draft→confirmed→fulfilled; invoice draft→issued→paid | quotation draft→accepted→converted; SO draft→confirmed→fulfilled; invoice draft→issued |
| DOWNSTREAM | Inventory movement, COGS journal, AR/revenue journal, collection, Treasury | Inventory movement, COGS journal, AR/revenue journal, AR aging |
| ACCOUNTING_IMPACT | Dr COGS/Cr Inventory; Dr AR/Cr Revenue; Dr Clearing/Cr AR | Dr COGS/Cr Inventory; Dr AR/Cr Revenue |
| INVENTORY_IMPACT | One unit issued | One unit issued |
| PAYROLL_IMPACT | None | None |
| BUDGET_IMPACT | None | None |
| IDEMPOTENCY_KEY | Stable `OPS-*` references and owner source keys | Stable `OPS-*` references and owner source keys |
| EXPECTED_MENU_PAGES_POPULATED | Sales/AR, Treasury, Accounting, Inventory movement, Finance/Executive reads | Sales/AR aging, Accounting, Inventory movement, Finance/Executive reads |

## Acceptance evidence

- First successful run: 2 quotations, 2 orders, 2 fulfillments, 2 invoices, 1 collection, 2 inventory movements, 5 balanced journals, 1 Treasury transaction.
- Second run: zero new records in every tracked table.
- Inventory moved through `InventoryService`; Commerce did not write balances directly.
- Finance postings moved through `SourceAccountingPostingService`; Treasury moved through `TreasuryService`.
- VAT remains `configuration_required`; no rate was invented.
- Recovered/manual records were not rewritten.

## Next safe scenarios (not executed)

1. Procurement inventory replenishment with partial receipt, partial bill, and partial payment. Preconditions: a positive-cost SKU, open accounting period, explicit owner references, and verified commitment realization semantics.
2. New mutable payroll period sourced from attendance/leave/OT. Preconditions: no finalized period overlap and effective tax/insurance policies.
3. Direct operating expense and budget exposure. Preconditions: approved budget authority and valid account/cost-center snapshots.
4. DMS evidence only when attached to one of the above real owner records.

Project revenue, service acceptance, and cost-center revenue/COGS remain outside this plan until authoritative persistence and policy exist.
