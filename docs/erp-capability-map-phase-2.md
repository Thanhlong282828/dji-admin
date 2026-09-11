# ERP Capability Map — Finance Control Phase 2

## Machine-backed audit result

The audit used current module manifests, named routes, controllers, application services, migrations, permissions, navigation, Blade views, and tests. It did not enable a disabled module.

| Capability | Status | Canonical evidence | Decision |
|---|---|---|---|
| Procurement / AP | COMPLETE | PR → PO → Receipt → Inventory → Vendor Bill → AP Payment → Journal | Reused; no duplicate engine |
| Treasury / Cash control | COMPLETE (local/manual) | AP/Expense payment → balanced journal → `treasury_transactions` → statement reconciliation | Added under Finance; no banking API |
| Accounts Receivable | AUDIT_ONLY / NOT_REQUIRED | Marketplace and POS currently recognize only paid transactions | No artificial receivable was created |
| Accounting Period Close | COMPLETE foundation | period → checklist snapshot → maker-checker → soft close/close/reopen | Posting service remains period authority |
| Approval Inbox | COMPLETE read orchestration | bounded union of owner tables with owner deep-links | No giant approval table and no approval bypass |
| Budget Commitment | COMPLETE for inventory PO | approved/sent PO remaining receipt quantity → commitment; receipt moves value to journal Actual | No commitment/actual double counting |
| Notification Center | COMPLETE existing / PARTIAL integration | internal messages, delivery attempts, preferences already exist | New owner events are deferred; no external provider claim |
| HR movement | COMPLETE existing | effective-dated assignment/lifecycle services | No rewrite in this scope |
| CRM segmentation | PARTIAL | POS/customer history exists; cross-channel identity remains incomplete | Deferred rather than inventing identity joins |
| Vendor governance | PARTIAL | vendor master, terms, purchase/AP evidence | blocked-vendor lifecycle deferred |
| Asset lifecycle | COMPLETE current scope | register, assignment transfer, depreciation, disposal, GL | Reused unchanged |

## Implemented connected flows

`Expense → approval → expense journal → payment journal → Treasury → statement reconciliation → period close → General Ledger / reporting`

`Purchase Request → Purchase Order → Goods Receipt → Inventory value / accrual → Vendor Bill → AP Payment → Treasury → Accounting → Budget Commitment / Actual`

Treasury stores operational settlement evidence, not an independent balance. Cash/bank balance remains derived from posted General Ledger lines. Automated tests keep `MEDIA_DRIVER=local`; no Cloudflare call is part of these flows.

## Current limitations

- No live banking connection; statement rows are deterministic/manual evidence.
- Soft-close currently blocks normal source posting. A separately authorized adjustment-posting path has not been added.
- Reconciliation undo and certified bank-statement import are deferred governance work.
- Accounts Receivable is intentionally absent until a deferred-payment/invoice business flow exists.
- Approval Inbox does not authorize or execute owner actions.
