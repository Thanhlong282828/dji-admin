# Operating Data Batch 2 — Procurement to Finance

Batch 2 extends recovered local data through existing owner services. It does not seed presentation totals, use service procurement, or alter recovered records.

## Owner boundaries

- Procurement lifecycle: `ProcurementService`.
- Stock mutation and weighted-average valuation: Inventory `InventoryService`.
- GRNI, AP and payment journals: Finance `SourceAccountingPostingService`.
- Cash evidence: Finance `TreasuryService`.
- Budget authority: `FinanceOperationsService`.
- Exposure formula: `BudgetExposureService`.

## Canonical scenarios

| Scenario | PO | Received/Actual | Open commitment | Billed | GRNI | Paid | AP outstanding |
|---|---:|---:|---:|---:|---:|---:|---:|
| `OPS-PROC-PARTIAL-2026-07` | 4,000,000 | 2,400,000 | 1,600,000 | 1,600,000 | 800,000 | 600,000 | 1,000,000 |
| `OPS-PROC-CLOSED-2026-08` | 2,500,000 | 2,500,000 | 0 | 2,500,000 | 0 | 2,500,000 | 0 |
| `OPS-PROC-COMMIT-2026-09` | 2,400,000 | 0 | 2,400,000 | 0 | 0 | 0 | 0 |

Inventory impact is eleven received units. The open-commitment scenario creates no GR, movement, AP, Treasury transaction, or journal.

## Budget exposure

The approved inventory budget uses branch HCM, Cost Center `OPS`, account `1200`, and 10,000,000 VND per month.

| Month | Approved | Actual | Open commitment | Consumed | Remaining |
|---|---:|---:|---:|---:|---:|
| 2026-07 | 10,000,000 | 2,400,000 | 1,600,000 | 4,000,000 | 6,000,000 |
| 2026-08 | 10,000,000 | 2,500,000 | 0 | 2,500,000 | 7,500,000 |
| 2026-09 | 10,000,000 | 0 | 2,400,000 | 2,400,000 | 7,600,000 |

`Consumed = Actual + Open Commitment`. GRNI, AP and Payment are settlement states and are not counted again.

## Idempotency

Stable `OPS-*` references protect every owner record. The first run created three PRs, three POs, two GRs, two Vendor Bills, two payments, two inventory movements, six journals and two Treasury transactions. The second run created zero records in every tracked table.

## Explicit limitations

- Service Acceptance: `NOT_IMPLEMENTED`.
- Service procurement accounting: `POLICY_REQUIRED`.
- Migration 101: absent and not required by this batch.
- VAT: `CONFIGURATION_REQUIRED`; no tax rate was invented.
