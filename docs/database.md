# Organization database

Các bảng chính: `companies`, `company_user_access`, `cost_centers`, `organization_units`,
`job_positions`, `employees`, `employee_assignments`.

Migration bổ sung `2026_07_23_130000_optimize_organization_core.php` chỉ thêm pivot tenant và index cho
tree/effective-date/headcount. FK `job_positions.company_id → companies.id` đã tồn tại, không có orphan
trước migration. Không migration cũ nào bị sửa và không dùng `migrate:fresh`.

Production seed hiện có: 5 cost centers, 12 organization units, 9 job positions cho
Schema Technology Vietnam. Seeder idempotent.

Employee migration `2026_07_23_140000_optimize_employee_core.php` adds unique User link,
company/work-email, identity HMAC and composite effective-date/timeline/expiry/version indexes. It ran
after a duplicate audit and did not alter an existing migration.

Employee production seed: 9 employees, assignments, histories, credentials, contracts, consents and
document metadata records. All records are explicitly demo data.

Phase 3 migration `2026_07_23_150000_optimize_recruitment_onboarding.php` adds pipeline/dashboard,
candidate consent/archive and onboarding deadline indexes. Production demo counts: 5 requisitions,
8 candidates, 8 applications and 4 onboarding cases.

Phase 2.5A added no migration. Post-change verification retained 5 cost centers, 12 organization units,
9 job positions, approved headcount 73, annual budget 6,200,000,000.00 VND and budgeted position cost
2,740,000,000.00 VND.
# Employee Core additions — 2026-07-24

- `employee_credentials`: credential number, private document key, archive timestamp.
- `employment_contracts`: signed private key, termination reason/time, approved/cancelled statuses.
- `data_consents`: actor, reason, private evidence key.
- `employee_documents`: MIME, size, scan status, archive timestamp.
- `employees`: private avatar key.
- `employee_user_invitations`: email, token hash, expiry/accepted/invalidated, creator.
# Phase 2.5C-2D database addition

Migration `2026_07_26_000001_create_notifications_table` adds Laravel's UUID-keyed `notifications` table. Migration `2026_07_26_000002_create_recruitment_notification_deliveries_table` adds a tenant-scoped durable delivery marker with a unique SHA-256 idempotency key derived from company/entity/action/version/recipient/channel. No existing table or data was removed or rewritten.
# Migration batch 15 — hire transaction

`2026_07_26_000003_complete_hire_candidate_transaction` adds nullable hired/source foreign keys to candidates, applications, candidate offers, employees and contracts; orchestration references to onboarding cases; provider fields to provisioning items; and `hire_transaction_markers`. Unique constraints enforce one Employee/Contract/Onboarding Case per accepted Offer. Existing Organization production counts remained 5 cost centers, 12 organization units and 9 job positions after migration.
# Batch 16 — Workforce Operations Foundation

Adds work calendars/days, work profiles, shift breaks, immutable attendance events, daily approval/calculation fields, adjustment histories, leave policies/assignments and minute ledger fields, overtime policies/actual/payable minutes, payroll periods and immutable employee input snapshots. Existing Phase 3 source tables contained zero rows; no production demo data was inserted.

Batch 19 (`2026_07_26_000007_complete_overtime_management_workflow`) adds overtime policy status, assignment/request idempotency and version fields, reconciliation status/version, effective lookup indexes, and relational `overtime_request_histories`. It is additive and does not modify existing overtime records.

Batch 20 (`2026_07_26_000008_close_workforce_operations_shared_gaps`) adds effective/version/archive metadata for shift break rules, leave attachment replacement/removal lifecycle fields and the shared `notification_deliveries` durable idempotency table. No business records are seeded.
# Phase 3A-7 MySQL closure (2026-07-26)

Status: **Phase 3A — COMPLETED**. Production connection remains MySQL `schema_admin`, version 9.6.0, `REPEATABLE-READ`. Process races run only on `schema_admin_testing`. Additive migration `2026_07_26_000009_align_leave_request_workflow_schema` safely aligned Leave states and the approval actor FK after verifying there were no incompatible rows; no fresh/wipe/reset was used.
# Phase 3B-1 batch 22

Additive Payroll migration ran on MySQL production/test schemas. It preserves legacy decimals while defining integer minor-unit columns as the new source of truth. New tables cover component catalog, effective assignments, immutable component lines, validation issues and relational run history. No Payroll production rows were present or seeded.
## Batch 23 — Payroll Input Operations

The additive `2026_07_28_000011_complete_payroll_input_operations` migration extends immutable snapshot headers/employee rows and adds dedicated validation/history tables. It is backward compatible with Phase 3B-1 Run linkage. A MySQL 64-character FK-name failure during the first application was safely resumed using schema-aware guards and short explicit constraint names; migration batch 23 is now recorded as Ran without deleting or rewriting business rows.

## Batch 24 — Payroll Period Operations

Migration `2026_07_28_000012_complete_payroll_period_operations` additively adds nullable creator, UUID idempotency key, note, stable list/idempotency indexes and `payroll_period_histories`. Existing `(company_id, code)` uniqueness and lifecycle columns remain. No production business row was created.

## Batch 25 — Payroll finalization actor

Migration `2026_07_28_000013_add_payroll_run_finalized_actor` additively adds nullable `payroll_runs.finalized_by` with a foreign key to `users.id`. Existing rows and lifecycle columns are preserved. The actual calculation breakdown table is `payroll_item_components`; no `payroll_item_breakdowns` table is introduced. Manual fixtures are restricted to `schema_admin_testing`; production `schema_admin` was not seeded or updated.
# Phase 3B-4 schema

Migration `2026_07_28_000014_add_payroll_input_source_accuracy` adds `payroll_input_source_records` and three categorized overtime-minute columns to `payroll_items`. It is additive, ran as batch 26, and changed no existing Payroll business rows.
# Batch 27 — Insurance Foundation

Additive migration `2026_07_28_000015_create_statutory_insurance_foundation` creates policy/rate/profile tables and adds immutable insurance aggregates/references to Payroll Run/Item. No Payroll business rows were backfilled or recalculated.
# Personal Income Tax foundation (batch 28)

Additive migration `2026_07_28_000016_create_personal_income_tax_foundation` creates `tax_policies`, `tax_policy_brackets`, `tax_policy_deductions`, `employee_tax_profiles`, and `employee_tax_dependents`; adds Tax Policy/version, taxable-income, withholding and before/after-tax values to `payroll_items`; and adds Tax withholding/net-after-tax aggregates to `payroll_runs`. Monetary values use integer minor units; rates use integer basis points. No production policy/profile/dependent was seeded and no Payroll row was backfilled.
# Phase 3D-1 Payslip persistence

Batch 29 adds `payroll_payslip_batches`, `payroll_payslips`, `payroll_payslip_snapshots`, and `payroll_payslip_access_logs`. The authoritative document points to an exact finalized run item and calculation version. The JSON snapshot is immutable, and private file integrity is represented by SHA-256, MIME and size metadata. The production migration created schema only; no payroll business rows or PDFs were created.
## Phase 3D-2 tables

Migration batch 30 adds `employee_payment_accounts`, `payroll_payment_batches`, `payroll_payment_batch_items`, `payroll_payment_validation_issues` and `payroll_payment_access_logs`. Account and item account snapshots are ciphertext with separate last4 fields. Integer minor units avoid floating point. Unique company idempotency, payment reference and nullable active-intent constraints support retry and duplicate-payment protection. Production business row counts remained zero after migration and verification.
# Payroll Accounting tables — migration batch 31

- `accounting_accounts`: company Chart of Accounts, effective date and postable status.
- `payroll_accounting_policies`: effective, versioned accounting configuration.
- `payroll_accounting_mappings`: deterministic source/dimension to debit/credit resolution.
- `payroll_accounting_journals`: locked Run/policy/calculation snapshots, lifecycle, totals and private export metadata.
- `payroll_accounting_journal_lines`: account/dimension/mapping snapshots and integer debit/credit.
- `payroll_accounting_journal_sources`: normalized trace to exact Payroll Item/Component and calculation version.
- `payroll_accounting_validation_issues`: safe blocking/warning issues.
- `payroll_accounting_access_logs`: private export/download audit.

All foreign keys and indexes have explicit MySQL-safe names. Migration `2026_07_29_000019_create_payroll_accounting_posting_foundation.php` is additive; no existing migration was edited.
# Batch 32 — Payroll retroactive adjustment

Adds reasons, requests, immutable historical/correction snapshots, delta components, validation issues, recovery obligations, delta-only adjustment runs, separate adjustment statements, downstream eligibility intents, history and access logs. No prior migration or historical Payroll table was modified; production business rows remain zero.

# Batch 33 — Adjustment downstream schema compatibility

Migration `2026_07_29_000021_complete_payroll_adjustment_downstream_schema` is additive. It makes only the legacy regular-source foreign keys `payroll_payment_batch_items.payroll_item_id`, `payroll_accounting_journals.payroll_run_id`, and `payroll_accounting_journal_sources.payroll_item_id` nullable, while preserving their existing indexes and foreign keys. Explicit Adjustment request/run/source references, signed delta trace, statement artifact integrity metadata, and retry-safe downstream intent metadata were added with MySQL-safe named indexes/foreign keys. Production counts remained zero before and after batch 33.

## Phase 3E-1C2 data usage

No migration was created or modified. C2 populates the batch-33 compatibility fields only on MySQL testing fixtures: Adjustment Payment Items use explicit Adjustment source references; Accounting Journals/Sources persist signed historical/corrected/delta trace; Adjustment Statement rows persist private artifact integrity metadata; downstream intents persist processing, attempt, completion/failure and artifact references. Production `schema_admin` audited business tables and private artifact directory remained unchanged.

## Phase 3E-1C3 database verification

No migration was created or modified. Process races ran on `schema_admin_testing`; browser fixtures ran on `schema_admin_browser_testing`. Marker-scoped cleanup left zero browser fixture companies, Adjustment requests and Statement rows and removed both generated PDFs. Production `schema_admin` was queried read-only: Adjustment requests/runs/components/histories/intents/statements and Adjustment-linked Payment Items/Journals remained 0 before and after.

## Phase 3E-2A annual PIT schema design

No migration/table was added. Audit proposes future additive `payroll_annual_tax_reconciliations`, sources, components, validation issues, histories, downstream intents, documents and access logs. Existing finalized Item/component/Payslip and applied Adjustment snapshots remain immutable source records. Header/source rows must add explicit tax year attribution, identity/policy snapshots, integer annual totals, versioning, active-intent/idempotency keys and canonical checksums. Production annual reconciliation row count is structurally N/A because no annual table exists.

## Phase 3E-2B annual PIT source foundation

Batch 34 creates the eight designed Annual Tax tables and adds nullable indexed `payroll_adjustment_requests.tax_attribution_year`. Batch 35 scopes the Payroll Item uniqueness guard by source type so regular and retroactive sources can retain the same historical Item reference without collision. Header identity/policy snapshots and source/component amounts are immutable JSON/BIGINT minor-unit records with explicit source FKs, version/idempotency/current guards and SHA-256 fields. Production Annual Tax tables and downstream/document rows remain 0; no Annual Tax file or seed was created.

## Phase 3E-2C calculation lifecycle schema

Batch 36 adds nullable `calculator_version`, `calculated_source_checksum`, validation `stage`, SHA-256 `fingerprint`, and a reconciliation/fingerprint unique guard. It contains no data backfill or destructive statement. Finalize may create eligibility intent metadata only in testing/business operation; production Annual Tax rows remain zero.
# Annual PIT Phase 3E-2C2

No migration was added. Batch 34–36 remains unchanged. `validation_stage` and unique issue fingerprint from batch 36 support stage reconciliation. Production `schema_admin` was read-only audited before the quality gate: Annual Tax tables contained 0 rows and document/access/downstream tables contained 0 rows.

Phase 3E-2C completion added no migration and did not modify batches 1–36. Final read-only production audit on 2026-08-01 found 0 rows in all eight Annual Tax tables and 0 rows in Payroll Run/Item, Adjustment Request/Run, Payment Batch and Accounting Journal tables. No production insert, update, delete or seed was executed.

## Phase 3E-2D delivery schema — batch 37

Batch 37 adds `payroll_annual_tax_refund_obligations`, `payroll_annual_tax_settlement_journals`, `payroll_annual_tax_settlement_journal_lines` and nullable Annual Tax intent links. A separate annual journal is required because the existing monthly journal requires Payroll Run and Period FKs. The migration contains no backfill and does not modify batches 1–36. Unique reconciliation/calculation keys enforce one refund obligation and one settlement journal per immutable calculation version.

Post-test audit 2026-08-01 reconfirmed `mysql/schema_admin`: reconciliation/source/component/issue/history/intent/document/access-log rows all 0; payroll run/item/payslip, adjustment request/run, payment batch/item and accounting journal rows all 0. Tests ran only against `schema_admin_testing`; no production seed/update/delete was executed.
# ERP integration tables — batch 38

- `erp_connections`: company/provider/status and credential reference only.
- `erp_mapping_rules`: company-scoped source-account to external-account mapping.
- `erp_exports`: immutable journal snapshot, lifecycle, actors, idempotency, payload/artifact checksums and private artifact metadata.
- `erp_export_items`: exact minor-unit copies of authoritative accounting journal lines with mapping/dimension snapshot.
- `erp_export_logs`: safe lifecycle history.
- `erp_access_logs`: artifact/access audit foundation.

Migration `2026_08_01_000026_create_erp_integration_foundation` is additive; batches 1–37 were not modified.

Batch 39 (`2026_08_01_000027_complete_erp_security_and_delivery`) adds endpoint/verification/updater metadata, effective-dated mapping versions and export attempt metadata. During the first application MySQL rejected dropping an FK-supporting unique index; no rows existed. The migration was made idempotent, added a dedicated FK index, reran successfully, and all ERP tables remained at zero production rows.

# Government Filing — batch 40

`2026_08_01_000028_create_government_tax_filing_foundation` additively creates `government_tax_filings`, `government_tax_filing_items`, `government_tax_filing_documents`, `government_tax_submission_attempts`, `government_tax_validation_issues`, `government_tax_filing_logs` and `government_tax_access_logs`. Monetary values remain BIGINT minor units; snapshots/checksums are persisted; company/version/idempotency constraints protect active intent and document/attempt duplication. The migration contains no backfill and does not alter batches 1–39.

# Phase 3E-5A HR Enterprise proposal — no migration

Audit/design proposes append-only `employee_lifecycle_events`, `employment_transitions`, `employee_positions_history`, `employee_manager_history` and `employee_documents_history`. These names and columns are documented in `docs/hr-enterprise-design.md`; no table, column, migration or production row was created. Existing batches 1–40 remain unchanged.
# Employee Lifecycle additive schema — batch 41

`employee_lifecycle_events`, `employment_transitions`, `employee_positions_history`, `employee_manager_history`, `employee_documents_history`, and `employee_lifecycle_outbox` were added by migration `2026_08_01_000029_create_employee_lifecycle_foundation`. All records are company/employee scoped. Lifecycle/history tables do not expose update/delete operations in the application layer. Checksums are SHA-256 and the outbox is persisted transactionally.

# Talent & Performance additive schema — batch 42

Migration `2026_08_02_000030_create_talent_performance_foundation` extends existing `performance_cycles` without changing its original migration and creates goals, immutable goal progress, competency framework/profile, reviews/items/immutable history, development plans and `talent_management_outbox`. Company/employee/cycle indexes and idempotency/checksum guards are additive. Production audit: every Talent table contains 0 rows; existing Employee and Assignment counts remain 9 and 9.

# Career & Succession additive schema — batch 43

Migration `2026_08_02_000031_create_career_succession_foundation` creates eight company-scoped Career tables: paths/steps, employee plans, succession plans/candidates, readiness assessments, append-only history and transactional outbox. It does not modify batches 1–42 and contains no production seed/backfill.

# Workforce Analytics — batch 44

Additive migration `2026_08_02_000032_create_workforce_analytics_foundation` creates calculation runs, immutable typed snapshots and an outbox. Production row counts are zero; source-domain tables are unchanged.

Batches 45–46 add snapshot versioning and normalize the versioned uniqueness guard without changing batches 1–44. Rebuild appends version N+1 and never overwrites historical rows.
## HR Data Governance (batches 47–48)

The governance projection uses `hr_data_quality_rules`, `hr_data_quality_runs`, `hr_data_quality_issues`, `hr_governance_audit_events`, `hr_consent_governance_history`, and `hr_document_governance_history`. Batch 48 adds run rule version/count and the immutable issue review/approval/resolution evidence fields. It is additive and does not modify source HR aggregates.

Integrity constraints: run idempotency is unique per company; issue identity is unique per run/entity/rule; audit idempotency is unique per company; consent/document history versions are unique per source record. History writers append new rows and never update earlier history.

## Batch 49 — Enterprise Workflow Foundation

Additive migration `2026_08_08_000037_create_enterprise_workflow_engine_foundation.php` adds `workflow_definitions`, `workflow_definition_versions`, `workflow_steps`, `workflow_step_transitions`, `workflow_tasks`, `workflow_decisions`, `workflow_histories`, and `workflow_outbox`; it extends legacy `workflow_instances` with a pinned version, engine lifecycle, lock version, idempotency key, checksum and execution timestamps. Monetary and restricted HR data are not stored.

Phase 3F-1 completion audit: migration remains batch 49. All ten Workflow tables (`workflow_definitions`, versions, steps, transitions, legacy instances/actions, tasks, decisions, histories and outbox) contain 0 production rows in MySQL `schema_admin`; no fixture or mutation was performed there. Race fixtures use only `schema_admin_testing`.

Batch 50 (`2026_08_08_000038_create_enterprise_notification_center`) additively creates notification templates/versions, messages, recipients, attempts, preferences, read states and notification outbox; it adds nullable message/artifact references and an index to the existing durable `notification_deliveries`. Published template versions and delivery attempts are immutable. No secrets or restricted HR/Payroll fields are stored.
# IAM Governance (batch 51)

`iam_access_requests`, `iam_access_request_items`, `iam_access_assignments`, `iam_role_entitlements`, `iam_privileged_roles`, `iam_sod_rules`, `iam_sod_violations`, `iam_access_reviews`, `iam_access_review_items`, `iam_certifications`, `iam_governance_histories`, `iam_outbox`. All are additive and company-scoped where applicable; histories and certifications are immutable snapshots with SHA-256 checksums.

Batch 52 additively adds Workflow correlation/version, assignment requester/reviewer evidence, SoD detection/resolution evidence, versioned checksummed review decisions with immutable JSON snapshots, and independent IAM-consumption timestamps on Workflow and Employee outboxes. Production IAM tables remain empty and existing runtime authorization/business counts are unchanged.
# Integration Hub (batch 53)

`integration_connections`, `integration_connectors`, `integration_outbound_messages`, `integration_inbound_messages`, `integration_delivery_attempts`, `integration_dead_letters`, `integration_external_references`, `integration_webhook_receipts`, `integration_histories`, `integration_outbox`. Monetary business values are not owned or interpreted by these tables.

# Enterprise Audit Platform (batch 54)

`audit_correlations`, `audit_events`, `audit_event_snapshots`, `audit_access_logs`, `audit_exports`, `audit_retention_policies`, `audit_outbox`. Tables are additive, company scoped and checksummed. Events/snapshots are append-only; retention archives evidence without physical deletion. Export paths remain private and never appear in ViewModels.

Batch 55 adds `audit_source_checkpoints` and `audit_archive_artifacts`. Checkpoints enforce one consumer result per `(source_module, source_event_id, consumer_name)`. Archive artifacts retain company/range/event-count, UUID reference, SHA-256, MIME, size, actor and timestamps without storing absolute paths or deleting source events. Batches 54 and 55 are Ran; final production counts for events, exports, checkpoints and archive artifacts are 0.
