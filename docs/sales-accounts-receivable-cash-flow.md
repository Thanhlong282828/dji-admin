# ERP Sales, Accounts Receivable and Cash Flow

## Canonical ownership

This slice is the sell-side counterpart of Procurement/AP. Marketplace and POS remain sales channels; ERP Sales is the governed commercial workflow. It reuses `commerce_customers`, Product variants, Inventory balances/movements, Treasury transactions and posted Accounting journals. It does not clone customer, stock, cash or ledger balances.

## Runtime lineage

`Customer → Quotation → Sales Order → Confirmation → Fulfillment → Inventory issue + COGS snapshot → Customer Invoice → AR → Collection allocation → Treasury inflow → balanced General Ledger`.

Confirmation creates no stock movement. Fulfillment validates the branch-owned inventory location, prevents negative stock through the existing Inventory service and snapshots weighted-average COGS. Invoice issue creates Accounts Receivable and Sales Revenue entries. Collection cannot exceed outstanding, creates one idempotent Treasury inflow and clears AR through a balanced journal.

All money is integer VND minor units. Quotation acceptance and invoice issue require maker-checker. Company, Branch, Customer, Product and Inventory ownership are validated server-side.

## Finance overview and branch scope

`FinanceOverviewReadService` composes posted GL statements, derived AR, derived AP allocations, recorded Treasury movements, approved budget lines and open purchase commitments. It persists no copied KPI totals. Company, Branch and bounded date filters are applied to owner-domain queries; the Branch filter also reaches journal and Treasury evidence.

The 7/30/60/90-day cash outlook is a forecast from outstanding Customer Invoice and Vendor Bill due dates, not actual cash. Expense cash classification accepts the canonical `finance_expense_payment` source emitted by Finance; unrecognised sources remain visible in the unclassified bucket.

## Tax and statement completeness

VAT remains `CONFIGURATION_REQUIRED` for this ERP Sales flow until a verified, effective transaction tax policy can be resolved. No rate is inferred from product names. Income Statement and Balance Sheet use posted GL evidence. Cash Flow classifies actual Treasury movements as operating/investing/financing where source evidence is deterministic; unknown sources remain `UNCLASSIFIED`. Cash Flow and Balance Sheet remain `PARTIAL` while canonical opening cash/equity evidence is unavailable.

## Current limitations

- Credit note/reversal reuses the existing sale-reversal foundation conceptually, but a Customer Invoice credit-note UI is not opened in this slice.
- A collection currently allocates to one invoice; an invoice supports multiple partial collections. Multi-invoice allocation from one remittance remains a future verified requirement.
- Customer Invoice rows expose due date, deterministic aging bucket, days overdue and a derived overdue presentation status without mutating invoice history.
- No Shopee/Lazada/TikTok rows are fabricated; disconnected channels remain configuration-required.

## Controls

Data Quality checks invoice-to-GL, paid/outstanding allocation, fulfillment inventory/COGS evidence, collection Treasury/GL lineage and AR-to-GL reconciliation. Automated tests use local media and make zero Cloudflare writes.
