# Local-to-Production Data Migration Runbook

## Purpose

This file is reusable governance for any request to move selected LaravelDji data from a developer/local environment into production. It is a rulebook, not a status log. Current counts, mappings, test results and blockers belong in a dated report under `docs/operations/`.

Read this runbook together with `AGENTS.md`, production deployment, backup/restore, database ownership and environment configuration governance before inventory, tool design, dry-run or import.

## Default decision

Production business data remains authoritative. Deployment moves reviewed application source and approved schema migrations; it does not replace production with a local database.

Never import a complete local dump over production. Never copy local users, password hashes, remember tokens, OAuth tokens, sessions, verification codes, API tokens, private keys or development credentials into production. Never import test/demo/factory rows merely because they exist locally.

## Authorization and environment gate

- Read-only comparison does not authorize export, staging restore or production import.
- Production export, staging restore, dry-run and production import require separate approvals for exact sources, destinations and table allowlists.
- Before every mutating command, verify application environment, configured database and actual PDO database. Stop on any mismatch.
- Import tooling must fail closed unless an explicit environment, source manifest, destination identity and `--dry-run`/approval contract are satisfied.
- A production import command must never default to a production connection or infer approval from `.env` presence.

## Required classification

Classify every candidate row/table before design:

1. production-owned business data;
2. local-only business data requested for transfer;
3. shared canonical reference/configuration data;
4. test, demo, synthetic or disposable development data;
5. identity/access/security data;
6. audit/immutable accounting data;
7. file/document metadata;
8. external private object bytes.

Reject unclassified data. Production-owned rows are never overwritten by a local copy. Identity/security, immutable accounting and audit history require owner-specific migration approval and reconciliation.

## Ownership and tenant mapping

- Follow bounded-context table ownership; an owning module validates and writes its records.
- Define explicit source-to-destination company, branch, cost center, employee and owner mappings.
- Do not assume numeric IDs have the same meaning across environments.
- Map stable business identifiers only after duplicate/collision review; retain a protected old-ID/new-ID ledger.
- Preserve tenant scope, source lineage, accounting immutability, audit actor/time and object ownership.
- Reject cross-company references, missing parent mappings and ambiguous identities.

## Collision and dependency analysis

Before importing, compare on an isolated staging copy:

- primary and foreign keys;
- every unique/indexed business key;
- user email/phone identities without exposing values in reports;
- company/branch codes and tax identifiers;
- permission/role semantics rather than numeric IDs;
- document numbers, object metadata and checksums;
- accounting source identities and idempotency keys;
- polymorphic owner types and soft-deleted rows.

Build a dependency graph and import parents before children. Do not disable FK checks as a substitute for correct ordering. If a constraint must be deferred, document and validate it before commit.

## Import tool contract

An approved importer must provide:

- explicit table/entity allowlist; no wildcard all-table mode;
- `--dry-run` as the default and a distinct approved execute mode;
- source manifest checksum and immutable import ID;
- explicit tenant/company mapping file with schema validation;
- deterministic ID remapping and canonical idempotency keys;
- conflict policies of `reject`, `map-existing` or explicitly reviewed merge—never silent overwrite;
- bounded batches, transactions and checkpoint/resume;
- per-entity validation through the owning module/service where available;
- audit trail with counts and redacted reasons, never PII/secrets;
- row-level before/after reconciliation and source lineage;
- safe retry that cannot duplicate economic effects;
- failure rollback limited to the import ID and never broad table truncation.

Do not embed credentials, environment URLs or production IDs in the tool. Do not put business rules into an ad-hoc SQL script when the canonical module must enforce them.

## Staging-first proof

Production import is blocked until an isolated staging environment exists with separate database, Redis, queue, mail sandbox, object storage and credentials. Restore the approved production backup there, apply the candidate migrations, then run import dry-run against that restored state.

Required staging evidence:

1. production backup restore verification;
2. schema/migration compatibility;
3. collision report and approved mapping;
4. dry-run counts and rejects;
5. execute rehearsal using the same artifact/import manifest;
6. tenant, FK, unique and data-quality checks;
7. source/target/file reconciliation;
8. rollback and safe retry proof;
9. no external email/webhook/payment/storage side effects.

Never use an active testing database or production database as staging.

## Users, roles and permissions

- Do not copy passwords, sessions, tokens or direct user grants from local.
- Map a person to an existing production identity only after approved identity verification.
- Create a new identity through the canonical invitation/onboarding flow when no safe match exists.
- Map roles/permissions by canonical name, guard and governed role policy, not local numeric IDs.
- Super Admin and privileged grants require separate authorization and post-import negative-access checks.

## Files and Supabase objects

Database metadata and object bytes are separate migration units. Do not create metadata pointing to an object that has not been copied and verified, and do not copy an object without canonical tenant ownership and metadata.

Any Supabase object transfer requires a separately approved prefix/object manifest, encryption, MIME/malware validation, checksum comparison, destination collision handling and cleanup plan. Never scan or copy a whole private bucket by default. Signed URLs and credentials must not enter manifests or logs.

## Cutover, reconciliation and rollback

- Define write freeze/maintenance window and source cutoff before production import.
- Take and restore-verify a fresh encrypted production backup before execution.
- Run one immutable import manifest; record accepted/rejected/skipped counts.
- Reconcile every imported entity, FK, tenant aggregate, document checksum and accounting total.
- Observe queues, scheduler, errors and audit events through the approved window.
- Rollback only rows/effects created by the import ID when proven safe. If imported records already generated downstream business effects, stop and use a forward correction or approved database restore with a defined data-loss window.

## Completion criteria

Classify migration as ready only when production backup, isolated staging, schema migration, collision mapping, dry-run, execute rehearsal, data quality, rollback and reconciliation all have current evidence. Otherwise report `PLAN READY`, `APPROVAL REQUIRED`, `STAGING BLOCKED` or `NOT VERIFIED` accurately.
