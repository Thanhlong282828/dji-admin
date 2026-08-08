# Phase 3E-3 — ERP Integration audit

Audit date: 2026-08-01. Scope: read-only inspection of the existing production schema and Payroll accounting source. No migration or production business write was performed during this audit.

## Authoritative accounting boundary

- `payroll_accounting_journals` owns company, Payroll Run/Period, policy/calculation version, lifecycle, actor timestamps, totals, reversal trace and the existing private internal CSV artifact.
- `payroll_accounting_journal_lines` is the authoritative ERP amount source. Debit/credit values are unsigned bigint minor units; account, cost-center, organization and mapping values are persisted snapshots.
- `payroll_accounting_journal_sources` provides immutable Payroll item/component traceability. ERP export must never recalculate amounts from live Payroll, Attendance, Leave or Overtime.
- Validation issues and access logs are persisted in `payroll_accounting_validation_issues` and `payroll_accounting_access_logs`.

## Existing lifecycle and controls

Accounting lifecycle is `draft → validated → reviewed → approved → internally_posted → exported`; rejection is controlled before posting. Maker/checker rules are enforced by the accounting service. Exported/internal-posted journals can be reversed by a new balancing journal with swapped debit/credit lines and `reversal_of_journal_id` / `reversed_by_journal_id` linkage. Duplicate active journal creation is prevented by `active_intent_key`; request replay is protected by `(company_id, idempotency_key)`.

The internal CSV export is private, formula-sanitized and recorded with disk/path/MIME/size/SHA-256. Download verifies file existence and checksum. Storage paths are not exposed as application identifiers.

Routes are authenticated and company context is verified by the shared Payroll request/context pipeline. Route permissions use `payroll.accounting.*`; read repositories scope every query by company. Controllers dispatch the existing CommandBus/QueryBus and do not form accounting queries directly.

## Gap to Phase 3E-3

There is no ERP connection aggregate, provider-neutral adapter contract, ERP export snapshot/items/logs, ERP-specific permissions, independent export lifecycle, idempotent external reference, or ERP access audit. The existing journal `exported` state and CSV file represent internal accounting delivery and must not be repurposed or mutated.

## Additive design decision

Phase 3E-3 adds isolated `erp_*` tables after batch 37. An ERP export references one company-scoped accounting journal and snapshots its persisted lines. It does not modify the journal, journal lines, Payroll Run or Payroll Items. Generic CSV is the only executable adapter in this phase; SAP/Odoo remain extension points and no external API call is permitted.

Lifecycle: `draft → validated → exporting → exported → failed → reversed`. Validation requires a balanced, internally-posted/exported authoritative journal, non-empty lines, same-company connection and complete mapping. Creator cannot validate; validator cannot execute. Active intent and idempotency constraints are database-backed.

Credentials are represented only by an encrypted/reference identifier. Passwords and tokens are explicitly excluded from ERP tables.

## Schema audit conclusion

Batch 1–37 is sufficient and remains unchanged. A new additive migration is required for `erp_connections`, `erp_mapping_rules`, `erp_exports`, `erp_export_items`, `erp_export_logs`, and `erp_access_logs`. No destructive or corrective migration is required.
