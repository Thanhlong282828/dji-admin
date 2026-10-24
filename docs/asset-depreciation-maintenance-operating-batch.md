# Asset, depreciation and maintenance operating batch

## Canonical lifecycle

`OPS-ASSET-TECH-2026` is a deterministic local operating record for a technical workstation used by the HCM branch and the `TECH` Cost Center. It is created through `FinanceOperationsService`, activated on 2026-09-01 and depreciated by the existing straight-line policy.

The acquisition journal records fixed asset against payable (`Dr 1300 / Cr 2100`). Acquisition is therefore not recognized as OPEX. The September depreciation entry is derived from 36,000,000 VND acquisition cost, zero residual value and a 36-month useful life, producing 1,000,000 VND (`Dr 5200 / Cr 1390`). The expense line preserves the effective Cost Center snapshot; the accumulated-depreciation line is not dimensioned.

Finance Asset assignment currently records Branch and Cost Center effective dates. It does not have an Employee assignee field, so no employee ownership is fabricated. Employee `E0002` is reused only as the technician for the preventive Work Order.

## Maintenance and documents

The quarterly plan `OPS-MAINT-PM-TECH-2026` creates the unique 2026-09-05 preventive occurrence. The Work Order uses the Maintenance state machine (`DRAFT → PLANNED → IN_PROGRESS → COMPLETED`) and requires no spare parts or external expense because the performed inspection and cleaning do not provide evidence for either cost.

Two private DMS records preserve relevant evidence: the Asset handover record and the completed preventive-maintenance report. Both are linked to canonical subjects and each stored version has SHA-256 integrity evidence. No public media or external storage provider is used.

## Idempotency and limits

The batch resolves stable business references before invoking owner services. Depreciation has a unique Asset/period boundary, preventive generation has a unique plan/due occurrence, accounting posting is source-idempotent, and documents use deterministic numbers. A second run creates zero Asset, assignment, depreciation, journal, Work Order, plan, document or version rows.

Procurement-to-Asset conversion is not implemented by the current owner boundaries, so this batch uses the valid direct Finance Asset acquisition path. Employee assignment, asset payment settlement and evidence-backed maintenance cost remain unsupported/not applicable to this scenario; no substitute records are invented.
