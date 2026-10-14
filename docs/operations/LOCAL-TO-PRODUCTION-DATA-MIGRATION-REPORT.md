# Local-to-Production Data Migration Evidence

## Executive result

Classification: `DATA_MIGRATION_PLAN_READY`, `STAGING_BLOCKED`, `PRODUCTION_IMPORT_NOT EXECUTED`.

This report contains aggregate/schema evidence only. It does not contain PII, password/token material, object keys or row values. A proposed HMAC-based identity comparison was rejected by the execution safety layer as sensitive-data egress; it was not bypassed. Record-level duplicate/collision proof therefore remains for an approved isolated staging workflow.

## Authoritative boundaries

- Production business data remains authoritative.
- Local `schema_admin` is a development/manual data source, not a replacement production database.
- Testing/browser/recovery schemas are excluded from migration scope.
- Source deployment, schema migration, selected row import and Supabase object transfer are four separate approvals.
- No production row, object, service or configuration was changed.

## Schema delta

Fresh inventories:

- local: 355 tables, 113 applied migration records;
- production: 336 tables, 104 applied migration records;
- source: 127 migration files;
- production-pending: 23 source migrations (105–127);
- local-applied but production-missing: 9;
- source-present but local-unapplied: 14;
- applied migrations absent from source: zero in both environments.

Local-only tables are:

`crm_leads`, `crm_opportunities`, `customer_collection_allocation_fx_settlements`, `customer_invoice_fx_recognitions`, `finance_fx_rate_snapshots`, `finance_fx_rates`, `finance_fx_revaluation_lines`, `finance_fx_revaluation_runs`, `procurement_exception_cases`, `procurement_goods_return_lines`, `procurement_goods_returns`, `supplier_credit_note_lines`, `supplier_credit_notes`, `supplier_refund_receipts`, `treasury_internal_transfer_legs`, `treasury_internal_transfers`, `treasury_opening_balances`, `vendor_bill_receipt_allocations`, `vendor_refund_receivables`.

These tables are not automatically importable. Their rows must be classified as real requested business data versus demo/testing data, and their parent/economic lineage must be mapped only after production is migrated on staging.

## Aggregate data inventory

| Group | Local | Production | Decision |
|---|---:|---:|---|
| Companies | 4 | 4 | Potential identity collision; map by approved canonical code/tax identity in staging |
| Users | 16 | 16 | Never copy passwords/tokens/sessions; reconcile identities via canonical onboarding |
| Roles | 16 | 16 | Map by canonical name + guard, not ID |
| Permissions | 881 | 867 | Source governance delta; synchronize canonical permission catalog, do not copy IDs |
| Role-permission links | 2,407 | 2,389 | Recompute governed mapping; never blanket-replace production roles |
| Employees | 40 | 40 | Potential overlap; company + employee code collision proof required |
| Branches | 8 | 8 | Company mapping and branch-code collision proof required |
| Enterprise documents | 3 | 3 | Metadata/object-byte reconciliation required |
| Employee documents | 36 | 36 | Private PII; explicit record and object scope required |
| Media assets | 31 | 31 | Provider/object availability and checksum required |
| Customer invoices | 3 | 3 | Immutable financial lineage; default decision is no local import |

Equal counts are not proof that records are identical. No raw identity comparison was exported from production.

## Material row-count differences

The largest differences include sessions (local 3 / production 752), attendance records (3,092 / 3,427), audit snapshots/events/checkpoints, activity logs, governance/DQ history, permission mappings, accounting policies/journals, inventory balances and treasury transactions. Sessions, caches and tokens are excluded. Audit/accounting/treasury differences must not be merged casually; production histories remain authoritative.

## Migration matrix

| Data class | Default action | Required proof |
|---|---|---|
| Production business rows | Preserve | Current backup + staging restore + post-migration reconciliation |
| Local-only requested business rows | Reject until allowlisted | Owner module, tenant map, collision report, ID map, dry-run and rollback |
| Shared reference/config | Reconcile by canonical key | Version/effective-date semantics and idempotent upsert review |
| Demo/test/synthetic data | Exclude | Proven source classification |
| Users/roles/permissions | No raw copy | Identity onboarding plus canonical permission synchronization |
| Accounting/audit/economic events | No merge by default | Immutable lineage, balanced totals and owner-specific approval |
| Files/document metadata | Separate manifest | Tenant ownership, malware/MIME/checksum and target object proof |
| Supabase object bytes | Separate approval | Explicit object/prefix list, encryption, collision and cleanup plan |

## Import design

The required importer is allowlist-only, dry-run-first, tenant-mapped, ID-remapping, idempotent, bounded and resumable. It records an immutable import manifest, rejects ambiguous conflicts, uses owner-module validation, and reconciles every accepted/rejected/skipped row. It must not default to production, contain credentials or disable foreign keys as a shortcut.

No executable production importer was created in this phase because the table/row allowlist and mapping decisions are not approved. This avoids creating a dangerous generic all-table tool.

## Staging requirement

No verified isolated staging environment currently exists. `schema_admin_testing` is active test infrastructure and is not staging. Production backup has not been approved/captured/restored. Therefore migration dry-run, migration 105–127 execution, collision analysis, import rehearsal and rollback proof are `NOT EXECUTED`.

Staging must isolate MySQL, Redis, queue, scheduler, mail, webhooks, payments and Supabase writes; use no production credentials; and restore an approved production recovery point before migration/import testing.

## Approval gates

1. Approve encrypted production backup and isolated restore.
2. Provision and identity-verify staging.
3. Apply/review migrations 105–127 on restored staging.
4. Approve exact local table/entity allowlist and tenant mappings.
5. Approve privacy-safe collision comparison inside staging.
6. Build and review the narrow importer.
7. Run dry-run, execute rehearsal, DQ and rollback proof.
8. Separately approve any private object transfer.
9. Approve production import manifest/change window only after reconciliation.

Production import remains `APPROVAL REQUIRED`.
