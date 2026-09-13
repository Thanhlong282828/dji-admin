# AP Aging and Treasury Payment Planning

This workspace is a read-only Finance/Treasury projection over the canonical Procurement AP records. It does not create Vendor Bills, payments, allocations, Treasury transactions, or journals.

## Source and invariant

For each financially active Vendor Bill at an explicit `as_of_date`:

`Outstanding = Bill total - allocations whose AP Payment is approved on or before as_of_date`

Draft, rejected, and cancelled bills are not financial liabilities. Fully paid bills remain in original/paid totals but are excluded from outstanding aging and payment-planning rows. All amounts use integer VND minor units.

The current owner domain has no payment-allocation reversal workflow. The projection includes only allocations attached to an approved AP Payment; reversal handling remains `NOT_IMPLEMENTED_IN_OWNER_DOMAIN` rather than being inferred from notes.

## Aging

Aging uses the report's explicit `as_of_date`, never implicit runtime time:

- `NOT_DUE`
- `1_30`
- `31_60`
- `61_90`
- `90_PLUS`
- `DUE_DATE_UNKNOWN`

The current schema requires Vendor Bill `due_date`, so `DUE_DATE_UNKNOWN` is structurally expected to remain zero. It is retained as an explicit safe classification for future nullable/import boundaries; no artificial due date is generated.

## AP outflow forecast

The forecast groups outstanding AP into mutually exclusive windows: overdue, due today, 1–7, 8–30, 31–60, 61–90, beyond 90 days, and unscheduled. It is labelled **AP Outflow Forecast** and is not a complete company cash-flow forecast.

## Payment readiness

`READY` requires the existing Procurement payment prerequisites visible to the read model: an approved/partially-paid bill, positive outstanding balance, accounting journal evidence, and configured payment-clearing ledger evidence. Incomplete evidence is `UNKNOWN`. No synthetic priority or risk score is used; rows sort factually by due date, outstanding value, or Vendor.

## Reconciliation

Unfiltered Procurement AP outstanding reconciles to posted GL account 2100 lines sourced only from `vendor_bill` and `ap_payment`. Vendor, cost-center, aging, and payment-status filters return `PARTIAL_EVIDENCE` for GL reconciliation because those dimensions are not all present on every journal line.

## Cash evidence limitation

Treasury contains canonical movement and payment-clearing evidence, but the system has no verified opening cash balance. Therefore Available Cash and coverage ratios remain `NOT_ENOUGH_EVIDENCE`; the application does not manufacture liquidity coverage.
