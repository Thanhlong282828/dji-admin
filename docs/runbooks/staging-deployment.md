# Staging deployment runbook

This runbook governs staging only. It never authorizes production access, a cloud mutation, an image publish, a migration, or a service start. Every execution requires a separate approval naming the release SHA, immutable image digest, target VM, database, backup reference and rollback image.

## Topology and isolation

- Use a dedicated staging VM and the `compose.yml` + `docker-compose.staging.yml` overlay.
- Use the local MySQL and Redis containers with dedicated named volumes. Never connect staging to a production database, production database user, production Redis, production object store or production secret.
- `APP_ENV=staging`, `APP_DEBUG=false` and `DB_DATABASE=laraveldji_staging_restore` are mandatory. `STAGING_PROTECTED_DATABASE_NAMES` must include every production database name.
- The origin binds only to `127.0.0.1`. IAP-only access is suitable for command-line health rehearsal, but it does not certify browser cookies, OAuth callbacks, WebSockets or public TLS. Full acceptance requires a separately approved HTTPS staging hostname and exact trusted-proxy allowlist.
- Reverb is opt-in through the `reverb` profile. The default staging broadcast connection is `log`; do not claim realtime coverage unless the profile, TLS WebSocket proxy and browser client are verified together.
- Staging mail is `log`. External integrations remain disabled or use dedicated sandbox credentials. No production endpoint or credential is allowed.

## Artifact and secret contract

1. Set `APP_IMAGE` to `ghcr.io/thanhlong282828/dji-admin:<40-char-sha>@sha256:<64-char-digest>`.
2. Authenticate the host to GHCR with a pull-only credential stored outside the repository. Never place it in Compose, shell history, instance metadata or application logs. If the VM has no approved egress, an approved operator may pull and verify the digest on a trusted transfer host, export an OCI archive, checksum it, transfer it through IAP, verify the checksum and OCI identity on the VM, then delete the archive. This is a design option only and does not authorize an IAP transfer.
3. Supply staging values from a root-owned deployment environment file with mode `0600`, or an equivalent approved secret injector. Do not reuse a production environment file.
4. Run `scripts/operations/staging-preflight.sh` before any pull or service action. Its successful exit is static evidence only.
5. Nginx receives `public/` from the same immutable application image through the one-shot `public-assets` service. The host checkout supplies deployment manifests and Nginx configuration only, not application PHP or built frontend assets.

## Required pre-deploy evidence

- Exact release SHA, image digest, OCI revision/source and successful security verification.
- VM identity, network/subnet, absence of public IP and approved egress path.
- Separate staging database/user/volumes and configured-versus-actual database identity.
- A protected database list containing all production databases.
- Root-owned secret-file metadata without values and proof that credentials are staging-only.
- Current database migration status plus manual decisions for every caution/blocking migration.
- Database plus `staging_mysql_data`, `staging_storage` and `release_manifests` backup evidence with checksums, off-VM retention and an isolated restore rehearsal.
- Boot/data disk identity and `autoDelete` state. Named volumes on an auto-deleted boot disk are not persistent recovery evidence.
- Previous immutable staging image plus migration/config/database backward-compatibility decision.

## Deployment sequence after explicit approval

1. Capture target identity and current container/image/volume inventory.
2. Verify GHCR authentication, then pull only the approved digest.
3. Run static preflight and render the merged Compose model without printing secret values.
4. Create and checksum staging database/storage backups; verify restore in an isolated temporary target.
5. Run migration-risk and pending-migration inventory against the staging database. Stop on every pending migration; the staging release command does not apply migrations or bypass the pending-migration gate.
6. Run any approved migration rehearsal as a separate operation with an exact approved migration inventory/checksum and approval reference. Record lock duration and row counts for data-changing migrations.
7. Only after the database is current, deploy by immutable digest, optimize config, and verify liveness/readiness/metrics readiness.
8. Verify queue, scheduler heartbeat and Redis isolation. Enable and verify Reverb only when it is in scope.
9. Run anonymous and authenticated smoke checks, tenant-denial checks and desktop/mobile browser acceptance.
10. Record the release manifest, backup references, acceptance result and rollback decision window.

## Backup and rollback

- Back up MySQL with a consistent logical dump using a staging-only account and store the encrypted artifact off the VM with a SHA-256 sidecar.
- Back up `staging_storage` and `release_manifests` independently; a database dump is not a document or release-evidence backup.
- Treat `staging_mysql_data` only as runtime persistence. A consistent logical dump and isolated restore proof remain mandatory even when the volume sits on a persistent disk.
- Keep durable volumes on a separately identified disk with auto-delete disabled, or prove that off-VM backups can reconstruct the environment before accepting loss of the boot disk.
- A disk snapshot is supplementary and does not replace byte-level database/storage restore evidence.
- Application rollback takes an explicit `staging` or `production` argument and uses the previous canonical immutable tag@digest only after validating the current release manifest environment, current image and rollback target. The source manifest is copied once into a private `0700` temporary directory as a regular `0400` snapshot; every subsequent host/container check uses only that snapshot. `ROLLBACK_MANIFEST_SHA256` must be supplied by the independent approval channel and must match the SHA-256 of the complete snapshot before parsing or any Docker action; the validation container hashes the same bytes it parses. The manifest's internal checksum remains a second integrity check, not approval evidence. Migrations, config and database must also be backward compatible; otherwise stop traffic/workers and use a reviewed forward fix.
- Never run blind down migrations or restore over a running database/volume.

## Acceptance and stop conditions

Accept only when health endpoints pass, actual PDO identity is staging, production resources are unreachable, queue/scheduler are healthy, required browser journeys pass over HTTPS, no secret appears in evidence and backup restoration is proven. Stop on identity drift, mutable image reference, production resource access, missing backup/rollback evidence, migration ambiguity, health failure, failed jobs, storage/scanner failure, missing HTTPS for browser acceptance or unverified Reverb when realtime is required.
