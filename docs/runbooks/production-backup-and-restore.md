# Production Backup and Restore Runbook

## Purpose and mandatory scope

This file is reusable operational law for LaravelDji backups, restore drills, release preparation and disaster recovery. It is not a progress log. One-time paths, counts, command output and current blockers belong in a dated report under `docs/operations/`.

Read this runbook before every production deployment, migration, database recovery, storage recovery, release cutover or backup task. Also read `AGENTS.md`, `docs/runbooks/production-deployment.md`, database ownership governance and the provider-specific security runbook.

When a request includes local/staging data transfer, also read `docs/runbooks/local-to-production-data-migration.md`. Backup permission never implies permission to import that data.

## Authorization boundary

- Read-only inventory does not authorize a dump, disk snapshot, object copy, upload, deletion, restore or migration.
- A production database dump, Compute disk snapshot, Supabase object inventory/copy, restore rehearsal and retention deletion each require explicit approval for the exact target.
- Never run destructive tests or restore over an operating database.
- Never change production credentials, bucket/RLS policy, IAM, firewall, service state or application data as an incidental backup step.
- Verify configured and actual database identities immediately before every database operation. Stop on any mismatch.

## Backup set

A release-grade recovery point must identify and preserve these independent assets:

1. immutable source/release artifact and release manifest;
2. Git history plus the explicitly reviewed working-tree state when preparing a pre-release local safeguard;
3. transaction-consistent MySQL logical backup with routines, triggers and events;
4. persistent local/private/public application files;
5. external object-storage inventory and, when separately approved, provider-native versioned backup/copy;
6. redacted configuration inventory and a reference to secrets held in the approved secret manager, never the secret values;
7. queue/scheduler topology and migration inventory needed to reconstruct runtime state.

Redis queues, caches and sessions are not a substitute for canonical database or object backups. Decide explicitly whether any ephemeral state must be drained, exported or intentionally discarded.

## Data ownership and tenant safety

- Preserve canonical tenant/company/branch ownership, object keys, source lineage, encryption metadata and content checksums.
- A backup tool must not enumerate companies by hand. Use canonical database metadata and bounded, resumable iteration where a tenant inventory is required.
- Never mix backup sets from different environments, database servers, buckets or release identities.
- External object metadata in MySQL is not proof that object bytes were backed up. Report metadata inventory and byte backup as separate statuses.
- Never scan, download or copy an entire private bucket without explicit scope, rate limits, destination, encryption and retention approval.

## Consistency requirements

- For InnoDB MySQL, use a transaction-consistent logical dump (`--single-transaction --quick`) and include routines, triggers, events and binary-safe data.
- Record server version, schema, table count, migration head/row count, engine inventory, dump byte count and SHA-256.
- Quiesce or freeze writes when cross-system consistency between MySQL and object storage is required. Document the accepted recovery point and any consistency window.
- Never call a command successful merely because it exited zero. Validate archive structure, decryptability, checksum and restore.

## Encryption and key custody

- Database dumps and persistent file archives must be encrypted before leaving the trusted host.
- Use an approved authenticated encryption mechanism where available. A local emergency safeguard may use AES-256 with a strong KDF only when the random key is stored separately in macOS Keychain or an approved secret manager.
- Never print, log, commit or place the encryption key beside the archive. Do not embed a password in a command-line argument or Markdown.
- Back up and test access to the key independently. Losing the key means the backup is not recoverable.
- Source archives must exclude `.env`, credentials, private keys, production dumps, logs containing secrets, runtime uploads, `vendor` and `node_modules` unless the immutable deployment artifact deliberately includes reviewed runtime dependencies.

## Destination and off-host rule

- A production backup stored only on the same VM/root disk is not a valid disaster-recovery copy.
- Use a restricted, encrypted, versioned off-host destination with least-privilege write access and separate restore/read authority where practical.
- Record destination identity without exposing credentials. Verify free space before capture and checksum after transfer.
- Do not overwrite an earlier verified recovery point. Use timestamped immutable paths.

## Required restore verification

Every release or migration backup gate requires a restore of the exact captured bytes:

1. verify the archive SHA-256;
2. decrypt using the separately held key;
3. restore MySQL into a new isolated non-production schema/server;
4. verify table count, migration inventory, selected canonical business counts, constraints and tenant boundaries;
5. extract files into a new temporary directory and verify every recorded file checksum;
6. validate application compatibility and pending migrations on the restored staging copy;
7. retain the verified recovery point through the rollout observation window.

Never restore into `schema_admin`, a browser/testing database in active use, or an existing directory. A restore test is `VERIFIED` only after these checks pass; otherwise report `NOT VERIFIED` or `BLOCKED`.

## Production database procedure

Before an approved dump:

- prove cloud project, VM, application path, `APP_ENV`, configured DB and actual PDO DB;
- prove the dump tool version and target DB engine;
- confirm all tables/engines and estimate archive size/time;
- confirm encrypted off-host destination and available capacity;
- record migration head and application release SHA;
- define write-free requirements and expected lock behavior.

The dump command must read credentials through a protected configuration channel and must not expose them in process output. Capture stderr separately with redaction. An approved snapshot complements but does not replace a portable logical dump and restore drill.

The canonical prepared implementation is `scripts/operations/backup-database-encrypted.sh`. Its default `--validate-only` mode must remain connection-free. Execute mode requires exact environment/database confirmations, a protected MySQL defaults file and an `age` recipient. It writes only encrypted output plus checksum and a secret-free manifest. It deliberately does not copy off-host; that is a separate approved operation. Never replace this with a password argument, persistent plaintext dump or key stored beside the ciphertext.

The canonical encrypted restore rehearsal is `scripts/operations/restore-encrypted-to-staging.sh`. It refuses non-staging environments and database names not ending in `_staging_restore`. Restore, migration rehearsal and teardown are separate approvals. Staging must use a distinct database host, Redis namespace, queues and outbound side-effect sinks.

Prefer a dedicated backup runner instead of installing dump/encryption packages on a resource-constrained production host. When MySQL listens only on the production loopback interface, use an approved IAP SSH local forward from the runner to `127.0.0.1:3306`. Run a MySQL-version-compatible client on the runner and stream `mysqldump | gzip | age`; plaintext may exist only transiently in encrypted transport and process memory/pipes, never as a persistent file. IAP is not a bulk-transfer service, so record duration and use this pattern only for a bounded dump whose size fits the approved window.

The runner must obtain database credentials through an approved secret custodian into a temporary mode-0600 client option file. Never extract a production `.env` into terminal output, shell history, command arguments, CI logs or reports. Destroy the runner/temporary credential material after checksum and off-host verification according to the approved cleanup step.

An `age` private identity must remain separate from the runner artifact and ciphertext. Keep it in an approved password/secret manager or hardware-backed age plugin under named custody; record only the public recipient fingerprint in an approval manifest. Never store the private identity on the production VM, in Git, beside a backup, in GCS object metadata or in an evidence report. Restore approval includes controlled, temporary key delivery and cleanup.

## Application file and object-storage procedure

- Inventory local persistent roots separately from source code.
- Preserve file modes and paths where required, reject unexpected symlinks, and checksum individual files plus the final archive.
- For Supabase S3, follow the Supabase security and live verification runbooks. Bucket privacy, RLS and server-side S3 access are authorization controls, not backup evidence.
- Record object count, total bytes, versioning/retention capabilities and metadata coverage using read-only provider APIs only after authorization.
- Do not use a one-object live verification as proof that all existing objects are backed up.

## Retention and deletion

Define retention before capture. A recommended minimum policy is daily/weekly/monthly generations aligned with the approved RPO, regulatory requirements and storage capacity. Retention deletion must be a separate reviewed operation, constrained to verified backup prefixes and protected from deleting the newest valid recovery point.

Never automate deletion until restore monitoring proves at least one current valid copy and one older independent recovery point. Legal hold and privacy deletion requirements override routine retention.

## Release and migration gate

No production deployment or migration is approved until:

- source/release identity is immutable and secret-free;
- production backup capture is explicitly approved;
- encrypted off-host database and persistent-data backups exist;
- the exact backup has passed isolated restore verification;
- pending migrations pass on the restored copy;
- rollback ownership, RPO/RTO and last safe rollback point are documented;
- post-deployment checks and observation window are approved.

A dirty local tree or dirty production checkout blocks release classification even if a safety backup exists.

## Capacity and transfer preflight

Before capture, record free bytes and inodes on the backup path, current database bytes, memory/swap headroom, expected compression ratio and a conservative temporary-space requirement. Encrypted streaming avoids a plaintext archive but does not remove the need for room for the encrypted artifact and redacted logs. Stop if the protected destination cannot hold at least two estimated compressed dumps plus 20% safety margin.

Off-host transfer must use a new immutable destination object/path. Verify the local ciphertext checksum after capture and the destination checksum or provider integrity metadata after transfer. Do not delete the on-host ciphertext until restore verification succeeds and retention ownership is documented. Never place the decryption identity on the application VM or beside the ciphertext.

For Google Cloud Storage, use a dedicated private bucket with public access prevention and uniform bucket-level access. Give the upload identity bucket-scoped `roles/storage.objectCreator`; give a distinct verification/restore identity bucket-scoped `roles/storage.objectViewer`. Neither the application service account nor the writer needs delete, overwrite, bucket-admin or IAM-admin access. Bucket creation, IAM, retention, lifecycle, upload, verifier download and deletion are independently auditable mutations.

Use unique object names so writer-only access never requires overwrite. Start with an approved but unlocked retention policy; locking Bucket Lock is irreversible and needs separate compliance-owner approval after restore drills. Lifecycle deletion must be older than the retention period, must never delete the latest verified recovery point, and is not an immediate scheduling guarantee. Upload ciphertext and checksum/manifest sidecars, then use the independent viewer to download and compare SHA-256 before classifying the off-host copy as verified.

Every production backup approval must use `docs/templates/production-backup-approval-manifest.yaml` or an equivalent signed change record. Unfilled placeholders or any authorized-mutation flag still set to false means execution remains blocked.

## Evidence and access audit

For each run, create a separate evidence report containing redacted commands, UTC timestamp, identities, archive paths, sizes, checksums, encryption/key reference, restore target, verification results, approvals and remaining blockers. Do not place secrets, signed URLs or object keys in the report.

Record who can create, read, restore and delete backups. Review this access periodically. A backup is not production-ready when the archive exists but the key, destination access or restore procedure is unverified.

## Failure handling

- Stop at the first identity, checksum, encryption, capacity or restore mismatch.
- Do not retry production capture with a different target or weaken validation without approval.
- Preserve logs with secrets redacted and preserve the last known-good recovery point.
- Do not delete partial artifacts until their exact paths and non-authoritative status are recorded.
- Classify accurately: `CREATED`, `VERIFIED`, `INVENTORY ONLY`, `APPROVAL REQUIRED`, `NOT VERIFIED` or `BLOCKED`.
