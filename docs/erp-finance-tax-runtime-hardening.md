# ERP Finance and tax runtime hardening

## Canonical reconciliation

Payroll Finance Reconciliation is a read-only projection. For each eligible Payroll Run it compares:

```text
Payroll gross + employer statutory contribution
  = expected employer payroll cost
  = active Accounting Journal debit
  = active Accounting Journal credit
  = Branch Finance payroll cost from the immutable Branch input snapshot
```

Accounting is due only for finalized/locked/paid runs. Earlier calculated, submitted, or approved runs still reconcile to Branch Finance but show `NOT_DUE` for journal generation. Missing or unbalanced journals on a finalized run are an explicit difference; the page never auto-fixes them.

Branch Bonus is already included in Payroll gross after the approved Salary Assignment reaches calculation. Branch P&L displays it as a disclosure but does not subtract it twice.

## Liability trace

PIT and employee/employer BHXH, BHYT, and BHTN totals are aggregated from immutable Payroll Item components. Accounting liabilities are compared against those same totals. Journal creation remains idempotent per Payroll Run; retries cannot create a second active original journal.

## VAT and CIT boundary

The active repository has no VAT category/policy snapshot on a recognized Commerce/Finance transaction and no supplier purchase-invoice domain. Output VAT is therefore `CONFIGURATION_REQUIRED`; Input VAT is `NOT SUPPORTED`.

Branch management P&L can provide accounting profit, but the repository has no verified effective CIT policy plus tax-adjustment ledger. Taxable income and estimated CIT are therefore not rendered as zero and are not represented as official filing amounts.

The Tax Dashboard labels these boundaries as management/preparation status. It never claims Government certification and makes no external request.

## Data Quality

The audit detects missing Branch payroll attribution, finalized Payroll/accounting mismatch, duplicate active original journals, insurance liability mismatch, and PIT liability mismatch. Findings are read-only and must be corrected through the owning Payroll/Accounting workflow.
