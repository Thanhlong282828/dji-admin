# Production Backup Execution Readiness — 2026-10-15

## Executive result

Classification: `BACKUP_EXECUTION_PLAN_READY / MUTATION_APPROVAL_REQUIRED`.

This is current evidence, not reusable law and not proof that a backup exists. No package was installed, bucket/VM was created, tunnel was opened, production query/dump was executed, file was uploaded, service was restarted, migration was run or production state was changed.

Release preparation base: `af2cc816c811667bfc0ea1e51fdcff7a4d25a0db`. The current working tree contains uncommitted preparation changes and is not yet a release artifact.

## Recommended backup architecture

Use a dedicated ephemeral Google Cloud backup runner rather than installing MySQL/`age` on the 964 MiB application VPS:

```text
Production MySQL 8.4 on VPS loopback
        │ MySQL protocol inside SSH inside Google IAP
        ▼
Ephemeral private backup runner
  pinned MySQL 8.4 client
  mysqldump | gzip | age
        │ ciphertext only
        ├── protected temporary runner disk
        └── dedicated private GCS backup bucket
```

Recommended runner: private `e2-small` or `e2-medium` Linux VM in `asia-southeast1`, no public IP, IAP administration, no application/runtime credentials. Use a reviewed toolbox image or packages only on that runner. For a ~90 MB source, 2 GiB is adequate for the streaming pipeline; use 4 GiB if the same runner will perform restore/migration measurements. Delete the runner only after off-host checksum and restore evidence, under a separate cleanup approval.

A trusted admin workstation is technically viable for this bounded dump, but current Mac evidence is not ready: `age` is missing and local MySQL client is 9.6, while the scripts now require pinned 8.4. An ephemeral GCP runner reduces workstation loss/network risk and keeps the operation in the approved cloud boundary.

## IAP/SSH transport

MySQL should remain bound to VPS loopback. The runner opens an SSH local forward through IAP:

```bash
gcloud compute ssh laravel-server \
  --project=project-06b7a0c7-35a4-4282-b81 \
  --zone=asia-southeast1-a \
  --tunnel-through-iap \
  -- -N -o ExitOnForwardFailure=yes \
  -o ServerAliveInterval=30 -o ServerAliveCountMax=3 \
  -L 127.0.0.1:13306:127.0.0.1:3306
```

This command is expected only after approval. It creates a live administrative tunnel but does not change MySQL configuration/firewall. MySQL traffic is encrypted by SSH/IAP. `mysqldump` then writes to stdout; gzip and age consume the stream. No plaintext dump is persisted on VPS or runner. Google documents that IAP TCP forwarding is not intended for bulk transfer, so capture duration/disconnect evidence; the current bounded database size is suitable, but growth requires reevaluation.

The runner must receive database credentials from the approved custodian into a temporary mode-0600 MySQL defaults file. Do not print/read production `.env` through a command, terminal transcript or report. Credential retrieval is unresolved and remains a blocker until a secret custodian/path is approved.

## Age key custody

- Generate the X25519 identity offline on a trusted custodian workstation or use an approved hardware-backed age plugin.
- Store the private identity in an enterprise password/secret manager or offline encrypted custody, not Git or the GCP application project.
- Record only the public recipient fingerprint in the approval manifest.
- The production VPS never receives the private identity.
- The backup runner receives only the public recipient during capture.
- Staging restore gets a temporary mode-0600 identity through a separately approved controlled delivery; it is removed by runner teardown/credential cleanup.
- Ciphertext, key and recovery instructions must not share a single failure domain.
- Test decryptability on staging before calling the backup verified.

`age` authenticates ciphertext, while SHA-256 proves byte-for-byte transfer. The manifest checksum is integrity evidence, not an independent signature. Authentic provenance therefore also depends on restricted runner identity, GCS IAM, audit logs, unique object names and retention.

## Dedicated GCS backup bucket

Proposed properties, subject to explicit approval:

- new bucket dedicated only to encrypted LaravelDji production backups;
- region `asia-southeast1`;
- Public Access Prevention enforced;
- Uniform Bucket-Level Access enabled;
- unique object path such as `mysql/schema_admin/YYYY/MM/DD/<utc>-<release>/`;
- writer principal: bucket-scoped `roles/storage.objectCreator` only;
- separate verifier/restore principal: bucket-scoped `roles/storage.objectViewer` only;
- application VM/service account receives no access;
- bucket administration/retention IAM remains with a separate operator;
- initial retention: 90 days, unlocked until restore drills and compliance review pass;
- lifecycle deletion no earlier than 365 days and never relied upon to protect the latest valid recovery point;
- consider a second project/location or dual-region only after RPO/cost review.

Do not lock Bucket Lock during the first run: Google documents that locking retention is irreversible. Locking later requires separate compliance-owner approval. Lifecycle actions are asynchronous and do not bypass active retention.

Cost envelope: a 90 MB ciphertext retained daily for 90 days is roughly 8.1 GB before sidecars/versioning, approximately USD 0.16/month at the published ~USD 0.02/GiB-month regional Standard rate, plus operation, soft-delete/version, transfer and tax charges. Allow under USD 1/month for this current database scale, but obtain a current Google Cloud calculator/SKU quote before bucket approval.

## Backup script audit

Audited `scripts/operations/backup-database-encrypted.sh`:

| Control | Result |
|---|---|
| Default behavior | connection-free `--validate-only` |
| Client compatibility | exact pinned MySQL 8.4 `mysql` and `mysqldump`; MariaDB and local 9.6 rejected |
| Source identity | exact database plus expected/confirmed MySQL server UUID |
| Consistency | `--single-transaction --quick`; refuses non-InnoDB base tables |
| Schema/data | included |
| Triggers | explicit `--triggers` |
| Routines | explicit `--routines` |
| Events | explicit `--events` |
| Binary/text safety | `--hex-blob`, `utf8mb4`, no tablespaces, GTID statements disabled |
| Pipeline failure | `set -euo pipefail`; any dump/gzip/age failure is non-zero |
| Plaintext file | never created |
| Partial output | `.partial` plus EXIT cleanup of artifact/checksum/manifest set until completion |
| Structural drift | base table/view/trigger/routine/event/migration inventory compared before/after |
| Ciphertext integrity | SHA-256 sidecar after final ciphertext |
| Evidence | source UUID/version, object size, schema inventory, migration count/head, write policy |
| Off-host copy | deliberately not performed by script; separate approval |

`--single-transaction` gives one consistent InnoDB data snapshot but cannot make concurrent DDL/routine/event definition changes transactionally consistent. The script detects coarse structural inventory changes, but the release-grade final backup should use `approved-write-freeze`. A preliminary staging-seed backup may use `approved-live-innodb` only if explicitly accepted and no DDL/deploy runs concurrently.

The dump identity needs SELECT, SHOW VIEW, TRIGGER, EVENT and routine metadata access as applicable. The least-privilege grant set must be verified read-only before execution; no new MySQL user/grant is authorized in this phase.

## Restore script audit

Audited `scripts/operations/restore-encrypted-to-staging.sh`:

- requires `APP_ENV=staging`;
- target database must end `_staging_restore` and cannot equal `schema_admin`;
- requires exact target confirmation;
- requires pinned MySQL 8.4 client;
- verifies actual selected database;
- verifies expected staging server UUID;
- refuses when target server UUID equals production UUID;
- target schema must exist and contain zero tables;
- requires artifact/manifest SHA-256;
- verifies age authenticated decryption and gzip integrity before import;
- verifies source database and production server UUID from manifest;
- compares restored base-table count with manifest.

If import fails after MySQL has accepted statements, the staging schema can be partially populated. The script intentionally does not issue broad cleanup. The failed target must be classified invalid, dropped/recreated only under the staging approval, and restored again from the same ciphertext. It can neither select nor overwrite production when the approval UUID gates are correctly populated.

## Preflight checklist

1. Re-read governance and approved manifest; confirm every intended mutation flag.
2. Verify local release SHA and clean/reviewed tooling artifact.
3. Verify cloud account/project, VM, zone, instance ID and OS Login identity.
4. Read-only verify production environment, configured/actual DB, MySQL UUID/version, migration head and database bytes.
5. Verify no migration/deploy/DDL/change window overlaps capture.
6. Confirm all base tables are InnoDB.
7. Verify pinned MySQL 8.4 client, `age`, gzip and SHA-256 tool on runner.
8. Verify runner free disk is at least two estimated compressed dumps plus 20%.
9. Verify temporary MySQL option file and any restore identity have 0600/0400 modes.
10. Verify age public recipient fingerprint with the independent custodian.
11. Verify dedicated GCS bucket identity, region, PAP, UBLA, retention/lifecycle and IAM.
12. Verify object prefix does not exist; never overwrite.
13. Open IAP SSH forward and prove it resolves the approved server UUID.
14. Execute exactly one capture with the approved write policy.
15. Verify artifact/manifest checksums locally; preserve non-secret exit/timing evidence.
16. Upload ciphertext and sidecars using writer-only principal.
17. Independently download using viewer principal and compare SHA-256.
18. Restore the exact bytes into isolated staging and reconcile manifest/data.
19. Retain runner/artifacts until restore result and cleanup approval.

## Expected execution commands — not executed

Values in angle brackets are approval-manifest inputs. Secrets must not be placed directly into command history.

```bash
# Terminal A on the approved backup runner
gcloud compute ssh laravel-server \
  --project=project-06b7a0c7-35a4-4282-b81 \
  --zone=asia-southeast1-a \
  --tunnel-through-iap \
  -- -N -o ExitOnForwardFailure=yes \
  -L 127.0.0.1:13306:127.0.0.1:3306

# Terminal B: preflight only. The pinned toolbox must contain MySQL 8.4 and age.
APP_ENV=production \
DB_DATABASE=schema_admin \
BACKUP_DIRECTORY=<protected-runner-directory> \
BACKUP_AGE_RECIPIENT=<public-age-recipient> \
scripts/operations/backup-database-encrypted.sh --validate-only

# Execute only after the manifest flags authorize capture.
APP_ENV=production \
DB_HOST=127.0.0.1 DB_PORT=13306 DB_DATABASE=schema_admin \
MYSQL_DEFAULTS_FILE=<mode-0600-runner-file> \
EXPECTED_SOURCE_SERVER_UUID=<approved-production-uuid> \
BACKUP_CONFIRM_SOURCE_SERVER_UUID=<approved-production-uuid> \
BACKUP_CONFIRM_ENVIRONMENT=production \
BACKUP_CONFIRM_DATABASE=schema_admin \
BACKUP_CONFIRM_WRITE_POLICY=approved-write-freeze \
BACKUP_DIRECTORY=<protected-runner-directory> \
BACKUP_AGE_RECIPIENT=<public-age-recipient> \
scripts/operations/backup-database-encrypted.sh --execute

# New immutable GCS objects only; exact paths come from generated manifest.
gcloud storage cp <ciphertext-and-sidecars> \
  gs://<dedicated-backup-bucket>/<new-immutable-prefix>/
```

For the recommended containerized runner, execute the same script inside a pinned toolbox image by digest with read-only mounts for script/credential and a writable protected output mount. The toolbox image/digest must be reviewed and recorded before use; no unpinned `latest` image is allowed.

## Mutation scope requiring approval

Prepared approval contract: `docs/templates/production-backup-approval-manifest.yaml`.

Separately authorize:

1. provision/delete the private ephemeral backup runner;
2. install or run pinned MySQL 8.4 + age tooling on that runner only;
3. create/configure the dedicated GCS bucket, PAP, UBLA, retention/lifecycle and bucket-scoped IAM;
4. deliver temporary DB credentials to the runner through the approved secret path;
5. open one IAP SSH local forward;
6. run read-only source identity/engine/inventory queries;
7. create one encrypted ciphertext/sidecar set on runner disk;
8. upload only those new objects;
9. independently download for checksum verification;
10. later restore the exact ciphertext to separately approved staging;
11. cleanup runner credentials/artifacts and delete the runner after restore evidence.

Production MySQL data/configuration, VPS packages, services, firewall, IAM, migrations and application files remain outside this backup approval.

## Current blockers

- production MySQL server UUID must be captured into the approval record immediately before execution;
- no approved DB credential custody/delivery path is identified;
- age key custodian and recipient fingerprint are not approved;
- backup runner/toolbox digest does not exist yet;
- GCS bucket, IAM principals, retention and lifecycle are not approved or created;
- no write-freeze window is approved;
- no encrypted artifact exists and restore remains unverified.

## Local verification

- shell syntax for encrypted backup, encrypted restore and staging migration rehearsal: PASS;
- operations unit regression: 11 tests, 102 assertions, 0 failures;
- Pint for the affected test: PASS;
- `git diff --check`: PASS;
- live source connection, dump, GCS upload, decrypt and restore: NOT RUN / NOT AUTHORIZED.

Final classification: `BACKUP_EXECUTION_PLAN_READY / MUTATION_APPROVAL_REQUIRED`.
