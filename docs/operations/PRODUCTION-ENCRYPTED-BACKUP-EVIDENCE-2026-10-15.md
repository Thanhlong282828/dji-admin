# Production Encrypted Backup Evidence — 2026-10-15

## Executive result

Classification: `ENCRYPTED_BACKUP_OFF_HOST_VERIFIED / RESTORE_NOT_VERIFIED`.

Batch 1 produced exactly one encrypted logical backup of production database `schema_admin`, copied it to a dedicated private Google Cloud Storage bucket and independently downloaded/verified the exact bytes. No plaintext dump was persisted. No production migration, deployment, restart, configuration change or business-data write was performed.

This report is run evidence, not reusable governance and not approval for Batch 2.

## Source identity gate

- Active Google identity: `lychieuthan35@gmail.com`.
- Project: `project-06b7a0c7-35a4-4282-b81`.
- VM/zone: `laravel-server / asia-southeast1-a`.
- Instance ID: `549764252224027593`.
- Application: `/var/www/laravel`, environment `production`.
- Database: `schema_admin`.
- Server UUID: `baf57f7f-b152-11f1-aa55-42010a400002`.
- Server version: `8.4.10-google`.
- Migration count/head: `104 / 2026_09_13_000104_create_procurement_downstream_reversal_evidence`.
- Base tables before/after: `336 / 336`; all base tables passed the InnoDB guard.
- Views/triggers/routines/events before/after: `0 / 0 / 0 / 0`.

## Encryption and recovery gate

- Encryption: age X25519, local age version `1.3.2`.
- Public recipient fingerprint SHA-256: `6f184656ee458e5afdfc33743d6298314896e7d47232f8073772a6053f2d8788`.
- The public recipient and private identity were never written to Git or this report.
- Private identity is stored in a local protected directory with mode `0600`; directory mode is `0700`.
- Recovery copy is stored as a canonical age secret identity in macOS Keychain under the dedicated LaravelDji backup service entry.
- Keychain recovery was used to derive the same public recipient and complete an encrypt/decrypt self-test: PASS.
- The application VPS never received the private identity.

An independent off-device recovery copy is still recommended before long-term reliance on this backup. Creating that copy was outside Batch 1 and no secret should be pasted into a ticket, report or Git.

## Backup runner and transport

- Runner: ephemeral local Docker process; no GCE backup VM was created.
- Reason: the current subnet lacks Private Google Access and a private GCE runner would have required additional NAT/subnet mutation. The approved workstation runner avoided public IP/firewall expansion and compute cost.
- MySQL client: MySQL Community `8.4.11` for Linux/aarch64.
- Pinned image digest: `mysql@sha256:b3b90af2a6552ae30c266fdb7d5dd55f3afb72404bb78d37fe8a23eb857fd3fb`.
- Transport: one Google IAP SSH local forward to production MySQL loopback.
- Write policy: `approved-live-innodb`.
- Pipeline: `mysqldump -> gzip -9 -> age`; no plaintext dump file.
- Capture duration: `1,386 seconds`.
- Temporary database defaults file: mode `0600`, deleted after verification.
- IAP tunnel: closed after verification.
- Backup container: removed; no backup container remains running.

## Artifact identity

- Artifact: `schema_admin-20261015T084047Z.sql.gz.age`.
- Created UTC: `2026-10-15T08:40:47Z`.
- Bytes: `436,347`.
- Artifact SHA-256: `26b6a22cfa8e0c8c32df5fe52b7377ccbcb5a2c8a76fb1a4932d36d488e1f6b8`.
- Manifest: `schema_admin-20261015T084047Z.manifest`.
- Manifest SHA-256: `fc09c37cfa329b2a3abf0c7ec703d435d817d06bc28ae23add8fe8cbc9b9fcd3`.
- Content declared by manifest: schema, data, triggers, routines and events.
- Authenticated local age decrypt plus gzip integrity test: PASS.
- Streaming SQL structural check: 336 `CREATE TABLE` statements and terminal dump-completion marker present.

The small compressed size is consistent with the production schema containing many lightly populated tables. It is not a substitute for an actual restore.

## Off-host storage

- Bucket: `gs://laraveldji-prod-backup-project-06b7a0c7-35a4-4282-b81`.
- Location: `ASIA-SOUTHEAST1`.
- Prefix: `mysql/schema_admin/2026/10/15/20261015T084047Z-af2cc816/`.
- Public Access Prevention: enforced.
- Uniform Bucket-Level Access: enabled.
- Versioning: enabled.
- Retention: 90 days, unlocked; current object retention expiry `2027-01-13`.
- Lifecycle deletion: age 365 days.
- Objects: exactly 4; total `437,174` bytes.
- Writer principal: dedicated service account with bucket-scoped `roles/storage.objectCreator`.
- Verifier principal: separate service account with bucket-scoped `roles/storage.objectViewer`.
- Writer read attempt: denied as expected.

`gcloud storage cp` attempted destination reads even for new objects and was rejected by the intentionally write-only principal. IAM was not broadened. Upload used the GCS JSON media-upload API with `ifGenerationMatch=0`, preserving create-only/no-overwrite behavior.

Object generations:

| Object | Bytes | Generation |
|---|---:|---:|
| encrypted SQL | 436,347 | 1792055179341519 |
| encrypted SQL SHA-256 sidecar | 107 | 1792055180146393 |
| manifest | 615 | 1792055180843320 |
| manifest SHA-256 sidecar | 105 | 1792055181634162 |

## Independent verifier evidence

The verifier principal downloaded all four objects into a new mode-0700 temporary directory. Verification proved:

- four expected objects and no additional object in the approved prefix;
- byte-for-byte equality with the local encrypted artifact set;
- artifact and manifest SHA-256 sidecars PASS;
- redownloaded artifact authenticated age decryption PASS;
- redownloaded gzip integrity PASS;
- temporary verifier download removed after the check.

## Restore capability

Current evidence proves that the exact off-host ciphertext can be read by the independent verifier, authenticated by age, decrypted with the separately held identity and decompressed into a syntactically complete logical dump stream.

It does **not** prove that MySQL can restore all objects/data/constraints or that migrations `105`–`127` succeed. No MySQL restore was executed in Batch 1. Status is therefore exactly:

`RESTORE_NOT_VERIFIED`

Restore verification requires separately approved Batch 2 on an isolated `_staging_restore` database using the exact object generations and checksums above.

## Production invariants after capture

Read-only post-check:

- deployed SHA remains `ab47852de19c6e6fffe017e5005fa7c62ef0f464`;
- the same three pre-existing dirty checkout entries remain, untouched;
- actual database remains `schema_admin` with the same server UUID/version;
- migration count remains `104` and base-table count remains `336`;
- Nginx, PHP-FPM, Redis, queue worker and scheduler timer remain active;
- Nginx, PHP-FPM and Redis report zero restarts; queue/scheduler were not restarted by Batch 1;
- no migration, deploy, application file edit, service restart or production database write was issued.

## Cost evidence

- Backup runner compute: USD `0` because the local ephemeral container was used.
- Persistent off-host bytes: approximately `0.000407 GiB`.
- Storage estimate at approximately USD 0.02/GiB-month: about USD `0.000009/month`, excluding minimum operation, soft-delete/versioning, tax and billing rounding.
- API operations and sub-megabyte verification transfer: expected below USD `0.01`; exact Cloud Billing attribution is delayed and was not yet available.
- Total Batch 1 expected cost remains safely below the approved USD 5 stop threshold.

## Remaining blockers

1. Actual restore into isolated staging has not been performed.
2. Migration rehearsal `105`–`127` has not been performed on a production clone.
3. Tenant, data-quality, queue, scheduler, storage and rollback compatibility have not been tested on restored staging.
4. The age identity has a verified Keychain recovery copy, but independent off-device key custody remains recommended.
5. Production deployment remains blocked and was not started.

Final Batch 1 classification: `ENCRYPTED_BACKUP_OFF_HOST_VERIFIED / RESTORE_NOT_VERIFIED`.
