# Finance Expense, Asset and Budget Runtime

## Canonical lineage

`Company → Branch/Cost Center → Expense or Asset → maker-checker/state transition → balanced Accounting Journal → General Ledger/Financial Statements → Budget vs Actual`.

Expense actual is posted only after approval. Payment is an internal clearing evidence (`AP/clearing → payment clearing`) and is not described as bank settlement. Asset acquisition preserves original cost; assignments are effective-dated; monthly straight-line depreciation is unique by asset/month and posts depreciation expense against accumulated depreciation. Disposal preserves acquisition, assignment and depreciation history.

Budget is a governed operating target. It never overwrites accounting actual. Actual is reconstructed only from posted journal lines and matched by Company, Branch, Account and Month. Variance is `Actual - Budget`; percentage uses integer basis points with a zero-budget safe result.

## Governance

- `finance.operations.view`: read Expense, Asset and Budget pages.
- `finance.operations.manage`: create/submit Expense, create/activate/transfer/depreciate Asset, create Budget lines.
- `finance.operations.approve`: approve/reject/pay Expense, dispose Asset and approve Budget.
- Expense and Budget makers cannot approve their own record.
- Posted journals, depreciation entries and approved budgets are not edited in place.
- Data Quality checks cover missing Expense journals, depreciation mismatch, future depreciation after disposal, Asset cross-tenant scope and Budget cross-tenant scope.

## Local operating evidence

The deterministic `LOCAL-*` bootstrap chain creates one approved utility Expense, one active IT Asset with one monthly depreciation entry and one approved operating Budget. Re-running bootstrap reuses their references and does not duplicate postings.
