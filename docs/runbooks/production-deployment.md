# Production VPS Deployment Runbook

## Purpose

This file is mandatory, reusable governance for every Codex task that inspects, prepares, deploys, operates or troubleshoots LaravelDji on a VPS or other production compute host. It is a rulebook, not a progress log.

Runtime evidence, one-time counts, incident output and current blockers belong in a clearly named report under `docs/operations/`; they must not be appended to this runbook or `AGENTS.md`.

## Mandatory reading order

Before any VPS or production-host action, read completely:

1. `AGENTS.md`
2. `docs/runbooks/production-deployment.md`
3. `docs/enterprise-release-governance.md`
4. `docs/database-ownership-governance.md`
5. `docs/environment-configuration-governance.md`
6. `docs/operations/PRODUCTION-MONITORING-ARCHITECTURE.md`
7. the relevant security/storage/incident runbook for the requested subsystem
8. `docs/runbooks/production-backup-and-restore.md` for every release, migration, rollback or recovery task
9. `docs/runbooks/local-to-production-data-migration.md` when local/staging records or object metadata may move to production

Historical reports are evidence only. Verify the current environment again before acting.

## Authorization boundary

Read-only audit permission does not authorize mutation. Unless the user explicitly approves the exact action, Codex must not:

- deploy, pull, push, copy or overwrite application files;
- create or switch a release symlink;
- restart, reload, enable, disable or install a service/package;
- run migrations, seeders, destructive tests or production jobs;
- modify `.env`, Secret Manager, IAM, firewall, SSH keys, metadata or DNS;
- mutate production databases, queues, object storage or existing objects;
- send a real alert, notification or webhook;
- run reconciliation with side effects;
- delete diagnostic or cloud resources.

If a diagnostic command may create a resource, enable an API, write a file or change cache/state, disclose that effect and obtain separate approval first. Do not infer that a command is read-only from its name.

## Production identity hard gate

Before every production command, resolve and report without exposing secrets:

- cloud project/account;
- instance name, zone/region and instance ID;
- access path, such as IAP;
- OS Login/POSIX identity;
- application absolute path and release identity;
- application environment;
- configured database connection and actual PDO database;
- whether the operation is read-only or mutating.

Stop if any identity differs from the approved target. Never print private keys, passphrases, access tokens, `.env` contents, signed URLs or credentials. Public-key checks must output fingerprints only.

## Source and artifact gate

Never deploy a dirty working tree or use a branch name alone as release identity.

An approved release candidate must have:

- a clean, reviewed commit SHA containing every intended change;
- composer and frontend lockfile hashes;
- module, route, command and migration inventories;
- a secret-free release manifest and artifact checksum;
- completed focused/full tests and required static gates on the exact commit;
- no unreviewed untracked files;
- documented compatibility changes and removed routes/contracts.

Build on CI or a staging builder. Do not run Composer dependency resolution, npm/Vite builds, PHPUnit, PHPStan or other memory-heavy build gates on a small production VPS.

The production artifact must exclude `.env`, credentials, tests, node_modules, local logs and development packages. Verify checksums before and after transfer.

## Database and migration gate

Production migration requires its own explicit approval. Before approval:

1. Read the actual production migration table; artifact file inventory alone is insufficient.
2. Compare production and release migration names in order.
3. Run static migration-risk analysis and manually inspect table alterations, foreign keys, unique indexes, data backfills, renames and destructive rollback code.
4. Create a transaction-consistent encrypted backup including routines, triggers and events where applicable.
5. Store the backup off-host and record checksum, size, server version, schema name, migration head and table count.
6. Restore that exact backup into isolated staging.
7. Apply the pending migrations to the restored copy and record duration, locks and failures.
8. Run focused cross-domain tests and data-quality checks against the migrated staging copy.

A successful backup command is not proof of recoverability; only a verified restore is.

The complete backup set, encryption/key-custody rules, off-host requirement, retention controls and restore acceptance criteria are defined in `docs/runbooks/production-backup-and-restore.md`. Production deployment approval requires evidence from a current backup run; a local development backup does not satisfy the production backup gate.

Before a change window, separately approve the exact production database dump/snapshot and persistent-data capture. Never infer permission to create a backup from read-only audit permission, and never store the only recovery copy on the production VM.

Local business data is not part of a source deployment. Any selected local-to-production transfer must use the allowlisted, staging-first, tenant-mapped and idempotent process in `docs/runbooks/local-to-production-data-migration.md`; never load a local full dump over production.

Do not use `migrate:fresh`, `migrate:refresh`, `migrate:reset` or blind production rollback. After new-version writes begin, prefer a forward fix or an explicitly approved database restore with a defined data-loss window.

## Deployment topology

### Reverse proxy and origin exposure

- Public traffic must terminate at the approved edge/host reverse proxy. The
  application container origin must bind to loopback or a private-only network;
  never publish its HTTP/FastCGI/Reverb ports on every host interface.
- The edge proxy must overwrite `Host`, `X-Forwarded-For`,
  `X-Forwarded-Host`, `X-Forwarded-Port` and `X-Forwarded-Proto`; it must not
  append untrusted client-supplied forwarding metadata.
- Laravel may trust only the explicit loopback/private proxy ranges supplied by
  deployment configuration. Adding a public/unbounded trusted proxy is not an
  acceptable substitute for origin network isolation.
- Hiding the backend requires an infrastructure control in addition to Nginx:
  prefer Cloudflare Tunnel/private ingress, or restrict the origin firewall to
  the approved edge proxy ranges and use authenticated origin TLS. DNS proxying
  alone does not hide an origin whose public IP remains reachable.
- Before cutover, prove direct access to the origin address/port is denied,
  HTTPS URL generation uses the forwarded scheme, the application records the
  verified client IP, WebSocket upgrades work, and health endpoints remain
  reachable only through the intended path.
- Updating proxy configuration, firewall, Cloudflare Tunnel/DNS, certificates
  or reloading Nginx is a production mutation requiring explicit approval,
  configuration validation and rollback instructions.

Use versioned immutable release directories with shared persistent paths and an atomic `current` symlink. Do not overwrite a live checkout in place.

The approved sequence is:

1. verify immutable artifact and checksum;
2. validate restored staging and rollback plan;
3. enter the approved change window;
4. freeze writes or enable maintenance mode when required;
5. gracefully stop scheduler/workers without killing active jobs;
6. verify queue ready/reserved/delayed counts;
7. take and verify the final backup;
8. unpack into a new release directory;
9. link shared `.env`, storage and persistent data without printing secrets;
10. run preflight checks;
11. run only the explicitly approved migrations;
12. build Laravel caches after schema success;
13. atomically switch the release symlink;
14. start the one approved worker/scheduler topology;
15. perform post-deployment verification through the rollback observation window.

## Small-VPS capacity rules

Measure CPU, RAM, swap, disk, inodes and process RSS before deployment. One point-in-time healthy sample is not capacity proof.

For a VPS near 1 GiB RAM:

- keep one bounded queue worker initially;
- do not run `queue:work` and Horizon for the same queue simultaneously;
- do not enable a Horizon profile whose memory/process limits exceed measured headroom;
- build assets and dependencies off-host;
- run tenant work in bounded cursor batches, never one unbounded 1,000-company process;
- keep monitoring collectors bounded and avoid colocating a full Prometheus/Grafana stack;
- monitor swap growth and OOM events during staging and rollout.

## ClamAV and uploads

Document malware scanning remains fail-closed. Never disable scanning to make uploads pass.

Do not install ClamD or run concurrent `clamscan` processes on a 1 GiB application VPS without measured staging evidence and explicit approval. Prefer a private, authenticated remote scanner service on separate capacity when the application has a tested adapter. Otherwise resize the VPS first and run one bounded scanner topology.

Production readiness requires proof of:

- scanner binary/service reachability;
- current signature database age;
- PHP/queue-worker execution compatibility;
- bounded timeout and concurrency;
- clean/infected/error/timeout fail-closed behavior;
- no secret or private object data in logs.

## Queue, scheduler and reconciliation

Choose one canonical queue topology: bounded `queue:work` or Horizon. Configuration presence is not runtime evidence.

Verify Redis, worker service, backlog, reserved/delayed jobs, failed jobs and graceful restart behavior. Never retry or delete failed jobs without understanding the payload and receiving approval.

Verify scheduler timer/cron, last exit status and heartbeat. Multi-company reconciliation must be tenant-scoped, dry-run by default, cursor-based, bounded by company count/item count/time, and safe to resume. Do not enumerate or configure companies manually from 1 to 1,000.

## Monitoring and alerts

Follow `PRODUCTION-MONITORING-ARCHITECTURE.md`. Host metrics must be collected outside PHP request handling. Verify real runtime data, not only routes/config files.

Alert-channel configuration is not delivery proof. Never send a real alert without explicit approval for one bounded test. Record whether an alert was actually received and redact endpoints/tokens.

## Rollback

Define rollback before deployment:

- application rollback uses the previous immutable release symlink;
- no `git checkout`, in-place overwrite or blind migration rollback;
- prove old-code/new-schema compatibility in staging;
- define the last safe rollback point before new-version writes;
- retain the verified database backup and artifact checksum through the observation window;
- document when recovery changes from artifact rollback to forward fix/database restore.

## Post-deployment gates

Verify at minimum:

- release manifest, commit and checksums;
- exact migration inventory;
- module/route/command counts;
- Nginx, PHP-FPM and HTTPS;
- `/health/live`, `/health/ready` and `/health/metrics-readiness`;
- Redis and one canonical queue topology;
- scheduler heartbeat;
- bounded reconciliation registration and approved dry-run;
- malware-scanner readiness;
- storage adapter/provider readiness;
- monitoring backend data freshness;
- error rate, latency, RAM, swap, disk and queue depth;
- authorization, tenant isolation and source traceability for new workspaces.

Do not classify the new release as production-verified using evidence collected from the old artifact.

## Evidence and completion

Store current runtime evidence and blockers in a separate file under `docs/operations/`. Record commands in redacted form, timestamps, target identity, exit codes and PASS/FAIL/NOT VERIFIED status.

A VPS task is complete only when every required gate has current evidence. Use `PARTIAL` or `BLOCKED` when access, approval, runtime proof, backup restore, migration validation or alert delivery evidence is missing.
