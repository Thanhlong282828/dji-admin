# Enterprise Integration Hub — Audit

Audit date: 2026-08-08. `Modules/Integration` existed as a disabled CRUD scaffold with no domain logic or tables; it was reused and enabled, not duplicated.

## Existing patterns

- ERP: `ErpExportService` owns accounting snapshot semantics, local CSV generation, checksum, retry and external reference.
- Government filing: `GovernmentTaxFilingService` owns immutable tax snapshots, XML integrity and local/mock submission attempts.
- Payment: payroll payment services own banking batch semantics and private artifacts.
- Workflow, Employee and IAM communicate through transactional outboxes and idempotent consumers.
- Notification Center owns user-facing delivery; domain modules must not call mail/broadcast directly.

## Gap found

External transport configuration, attempts, webhook receipts, dead letters, health and transport audit were duplicated or domain-local. The Integration Hub now centralizes transport only. Payroll/ERP/Government business states remain authoritative in their domains.

## Production baseline

Before migration: users 1, employees 9, payroll_runs 0, erp_exports 0, government_tax_filings 0, iam_access_requests 0. No integration business rows existed.

Hardening audit found batch 53 already supplied the required DB checkpoints. Network/file side effects are restricted to `IntegrationAdapterInterface`; controllers dispatch CQRS messages and never call HTTP, storage, ERP or Government domain tables directly.
