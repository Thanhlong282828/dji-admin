# Legacy Accounting Attestation Standard

## Purpose

Legacy accounting evidence is remediated by adding independently reviewed evidence. A posted journal, its journal lines and historical `mapping_snapshot` are immutable and must never be rewritten to imply a policy existed at posting time.

## Required append-only record

Any future implementation requires a separately approved migration for an append-only `accounting_legacy_posting_attestations` aggregate. It must bind company, journal, journal line, source type/ID, account, currency and amount to hashes of the original immutable journal and mapping snapshots. It must record classification, attestation schema version, maker, checker, timestamps, repair-batch UUID, correlation ID and idempotency key.

The database must enforce one canonical active attestation per company/journal/line/classification and reject cross-company references. No cascade may delete a posted journal or attestation. Corrections use a new superseding/revocation event; update/delete of historical evidence is forbidden.

## Approval and execution contract

- Audit and repair are separate commands. Audit is always read-only.
- Repair defaults to dry-run. Mutation requires `--execute`, exact non-operating database confirmation during rehearsal, approved manifest SHA-256, batch UUID and distinct Finance maker/checker.
- The manifest must match the latest detailed audit checksum. Unknown records, changed snapshots, closed-period conflicts and checksum mismatches fail closed.
- Native policy-backed and legacy-attested coverage remain distinct in DQ/reporting.
- A genuinely missing economic effect is not solved by attestation. It requires reconciliation and an approved compensating journal in an open period through canonical CQRS, policy and period guards.
- Every attempted execution emits an immutable audit event; failed tenant batches roll back atomically. A retry returns the same canonical result.

## Phase 1 boundary

Phase 1 creates no attestation table and performs no repair. Schema creation, executable repair commands and production mutation require separate explicit approvals.
