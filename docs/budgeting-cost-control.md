# Budgeting & Cost Control

## Canonical vocabulary

- **Budget** is an approved management limit. It is not cash and creates no journal.
- **Open Commitment** is the unreceived value of approved/sent Purchase Order lines: `(ordered - received) × immutable unit cost`.
- **Realized Actual** is classification-specific Budget consumption. For Inventory it is the received inventory debit at Goods Receipt, not the Vendor Bill or payment.
- **GRNI** is received Inventory not yet invoiced. It is a liability bridge, not another Budget consumption.
- **AP** arises when an eligible Vendor Bill clears GRNI. Partial bills may not cumulatively exceed received quantity.
- **Payment** settles AP through Treasury/GL. It never changes Actual or Open Commitment.

The canonical calculation is owned by `BudgetExposureService`:

`consumed = realized_actual + open_commitment`

`remaining = approved_budget - consumed`

There is no second authoritative “variance” formula: the control value is **Remaining**. Positive Remaining is available Budget; negative Remaining is `OVER_BUDGET`. `NO_BUDGET` means no matching approved line exists and is distinct from an explicitly approved zero Budget. The current enforcement policy is `NO_CONTROL_POLICY`: read models expose the state but PO approval is not silently blocked.

## Inventory timeline

1. PO approval creates Open Commitment.
2. Goods Receipt reduces Open Commitment and posts Inventory / GRNI for the received value.
3. Vendor Bill posts only its received-and-unbilled quantity from GRNI to AP.
4. Payment reduces AP and creates Treasury evidence.

Neither Bill nor Payment changes Inventory Actual. A retry with the same vendor invoice identity or payment reference returns the existing canonical record.

## Dimensions and periods

Procurement classification and Accounting Account belong to the line. Cost Center, Account code/name and classification are snapshotted into PO and Bill lines. Current employee or organization state is never used to rewrite history.

- Commitment period: PO order/approval evidence; current reports use PO `order_date`.
- Inventory Actual period: Goods Receipt `receipt_date`.
- AP period: Vendor Bill `invoice_date`.
- Cash period: Payment `payment_date`.

All implemented transaction currencies are VND and Company runtime evidence uses VND. Budget lines have no independent currency column; therefore this control contract is VND-only and performs no FX conversion or cross-currency summation.

The financial event period remains source-owned: PO `order_date` owns Commitment, Goods Receipt `receipt_date` owns Inventory Actual, Vendor Bill `invoice_date` owns AP, and Payment `payment_date` owns cash. A policy for carrying a Commitment against one period's Budget and realizing it against another period's Budget is not configured; cross-period Budget ownership is therefore `CROSS_PERIOD_BUDGET_REALIZATION_POLICY_REQUIRED`, while each financial event remains correctly reported in its canonical period.

Approved Budget authority is immutable through the current application flow: lines may only be added while draft. Approval is maker-checker protected and serialized per Company. A second draft with the same year/month/Branch/Cost Center/Account dimension is rejected if an approved authority already exists. There is no revision/version workflow; Budget revision remains `PARTIAL` and requires an explicit future policy rather than editing approved history.

## Classification support

- `inventory`: storage, receipt, partial billing, GRNI/AP and Budget realization supported.
- `asset`: classification/account evidence supported; Procurement-to-Asset capitalization remains partial.
- `operating_expense`: classification/account evidence supported; non-inventory acceptance and Procurement OPEX realization remain partial.

Legacy lines remain `NULL`/unknown. They are not backfilled from mutable Product, employee or organization relationships.

## Controls

The service locks PO/Bill lines while deriving received and already-billed quantities. Over-receipt and cumulative overbilling are rejected. Company-scoped Account and Cost Center validation prevents cross-tenant dimension injection. Data Quality audits detect received-over-ordered, billed-over-received, and invalid classification/account/company mappings.

Partial PO cancellation/reduction after partial receipt is not implemented by the current Procurement state machine. It is not inferred by rewriting ordered quantity.
