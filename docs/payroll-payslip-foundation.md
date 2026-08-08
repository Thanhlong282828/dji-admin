# Phase 3D-1 — Payslip Generation and Secure Delivery

## Scope and status

Official Payslips are generated only from a finalized Payroll Run. The source is the persisted Payroll Item and its component rows at the exact `calculation_version`; generation never recalculates from live Employee, salary assignment, insurance, tax, attendance, leave, or overtime data.

Admin operations are enabled. Employee self-service download is `NOT_ENABLED`: although Employee can map to a User, this project does not yet expose a dedicated, verified ESS Payroll portal. Enabling an ambiguous admin URL would weaken the ownership boundary.

## State model

`pending → generating → generated → published → revoked`

- Generate is queue-backed and idempotent by company/idempotency key and by item/calculation/payslip/document version.
- Regenerate is allowed only before publish/revoke and uses the same immutable snapshot.
- Publish is idempotent, records actor/time and emits one safe employee notification through the durable notification delivery service.
- Revoke requires a reason, preserves the file and audit record, and blocks download.
- Published/revoked documents cannot be silently overwritten.

## Security and storage

- Middleware: `auth`, `verified`, route permission, plus `workforce.company` Gate in every controller action.
- All reads and writes include `company_id`; foreign IDs are returned as not accessible through the company boundary.
- PDFs are A4 documents rendered by Dompdf with remote resources disabled.
- Files use opaque UUID paths on the private `local` disk (`storage/app/private`), never `public` or a public URL.
- Every file stores MIME, byte size, SHA-256 checksum and template version. Download and publish verify checksum first.
- Download responses use `private, no-store` and `nosniff`; every publish/download/revoke/regenerate action is recorded.
- Broadcast payload contains identifiers/status/refresh scopes only—no salary amounts or employee identity.

## Tables

- `payroll_payslip_batches`: bulk request/progress counters.
- `payroll_payslips`: immutable source references, versions, private file metadata and lifecycle actors.
- `payroll_payslip_snapshots`: one canonical JSON payload and SHA-256 payload checksum per Payslip.
- `payroll_payslip_access_logs`: tenant-scoped access/action trail without document contents.

## Known limitations

- Bank payment evidence, accounting posting, email attachment delivery, e-signature and employee portal are not part of Phase 3D-1.
- Live two-browser realtime remains `NOT_VERIFIED` until exercised with two authenticated browser sessions.
- Bulk progress is persisted; worker/Horizon runtime must be running for asynchronous production processing.
## Relationship to Bank Payment Batch

Payslip delivery and payment instruction generation are separate boundaries. Bank Payment Batch does not scrape a Payslip PDF; it consumes the authoritative finalized Payroll Item net-after-PIT amount and calculation version. Releasing a payment CSV does not mark a Payslip or Payroll Item as bank-paid.
# Accounting boundary

Payslip and Accounting Journal are separate immutable projections of finalized Payroll Items. Journal generation never parses or depends on a Payslip PDF.
