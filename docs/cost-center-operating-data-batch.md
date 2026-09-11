# Cost Center Operating Data Batch

## Canonical metric boundaries

- Approved Budget is management authority, not cash or a GL balance.
- Open Commitment is the unreceived part of an approved Purchase Order.
- Budget Actual is realized budget consumption. It includes recognized expense and inventory Goods Receipt realization when the approved budget line owns that account/dimension.
- Recognized Expense includes posted expense-account journal lines only.
- Inventory realization is a balance-sheet increase and is excluded from recognized expense.
- Cash Paid comes from Treasury evidence and is shown separately. Paying an expense does not recognize the expense again.
- Remaining is `Approved Budget - Budget Actual - Open Commitment` through `BudgetExposureService`.

## Deterministic operating scenarios

The `app:extend-local-operating-data` command creates these records only on the guarded local `schema_admin` database and uses canonical Finance application services.

| Reference | Cost Center | Business reason | Flow | Amount | Cash |
|---|---|---|---|---:|---:|
| OPS-CC-OPEX-TECH-2026-06 | TECH | Temporary backup connectivity for internal systems | Expense → submit → approve → Accounting → Treasury | 1,800,000 | 1,800,000 |
| OPS-CC-OPEX-HR-2026-07 | HR | Materials and administration for internal onboarding | Expense → submit → approve → Accounting | 2,400,000 | 0 |
| OPS-CC-OPEX-FIN-2026-08 | FIN | Bank and administrative reconciliation charges | Expense → submit → approve → Accounting → Treasury | 850,000 | 850,000 |

The SALES Cost Center remains zero because no additional direct operating event was justified. OPS retains the inventory procurement scenarios from Batch 2.

## Historical evidence

Legacy Payroll and other journals without an immutable Cost Center snapshot remain unattributed. No current assignment lookup or historical backfill is used. Revenue and COGS by Cost Center remain `NOT_ENOUGH_EVIDENCE`; therefore full Cost Center P&L remains unavailable.

## Idempotency

Expense records use stable `OPS-CC-OPEX-*` source idempotency keys. The second command run creates no expense, journal, Treasury transaction, budget, or category duplicates.

## Known limitations

- No new Payroll period is generated solely for dashboard density.
- Service Acceptance is not implemented.
- Service procurement accounting remains policy-required.
- Project Revenue is not implemented.
- VAT remains configuration-required.
