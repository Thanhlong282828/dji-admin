# GCP runtime resource hardening

## Deployment evidence and scope

The repository supports Docker Compose, but the measured Google Compute Engine production host is Debian/systemd: Nginx, PHP 8.4 FPM, one Laravel queue worker, a scheduler timer, Reverb, Redis and Cloud SQL Proxy. MySQL runs in external Cloud SQL. Docker limits remain valid for the Compose topology but are not production truth. The checked-in `deploy/systemd` and `deploy/php-fpm` files are reviewed templates only; installing them requires separate production authorization.

The default queue owner is the Compose `queue` service. Horizon remains opt-in through the `horizon-only` profile and must replace—not accompany—the default worker for the same Redis queue. Docker `restart: unless-stopped` owns replacement when a bounded worker exits. Reverb and the scheduler are separate supervised containers.

## Bounded process lifecycle

| Process | Bound | Restart owner | Notes |
|---|---:|---|---|
| PHP-FPM | 2 on-demand children; 300 requests/child | FPM/Compose | Peak RSS still requires deployment measurement. |
| Queue worker | 384 MB, 250 jobs, 3,600 seconds, 330-second job timeout | Docker Compose | Redis `retry_after=390` remains above worker/job timeout. |
| Horizon (opt-in) | 1 process, 384 MB, 250 jobs, 3,600 seconds | Horizon/Compose | Not started in the default topology. |
| Scheduler | one `schedule:work` container | Docker Compose | Scheduled tasks use overlap guards; multi-node jobs additionally need `onOneServer`. |
| Reverb | separate container, 384 MB container limit | Docker Compose | Graceful `reverb:restart` is the deployment mechanism; measured connection RSS is unavailable. |

### Measured systemd target (2026-09-19 UTC)

The 964 MiB host had 632 MiB used, 332 MiB available and 134 MiB of its unchanged 4 GiB swap in use. Memory PSI was zero at 10 seconds and low but non-zero at 60/300 seconds; no kernel OOM was found. Root storage was 43% used after the authorized 20 GB expansion. Health endpoints returned 200/200.

| Process | Observed evidence | Proposed lifecycle | Rationale |
|---|---:|---|---|
| PHP-FPM | three workers around 29–31 MiB RSS; cgroup peak 81 MiB | `ondemand`, 2 children, 10s idle, 300 requests | Releases idle workers while retaining two-request concurrency. |
| Queue | about 85 MiB process RSS; cgroup peak 60 MiB in the sampled lifecycle | 192 MB graceful recycle, 250 jobs, 3600s, timeout 330s | More than 2x observed RSS; no hard cgroup memory kill until representative heavy jobs are measured. |
| Scheduler | about 85 MiB process RSS; cgroup peak 113 MiB | keep oneshot timer and 300s timeout | systemd does not start a second instance while the oneshot service is active. |
| Reverb | about 55 MiB RSS; cgroup peak 64 MiB; zero restarts | keep current lifecycle | One snapshot does not establish monotonic growth; periodic restart is unjustified. |
| Redis | 8.95 MiB RSS; 2.00 MiB logical; zero evictions | keep `noeviction` | Shared queue/session/lock state must not be evicted. |
| Cloud SQL Proxy | about 22 MiB RSS; cgroup peak 40 MiB | keep current lifecycle | Cloud SQL server memory is external to the VM. |

The systemd queue template intentionally omits `MemoryHigh` and `MemoryMax`. Laravel's `--memory=192` is a graceful post-job lifecycle threshold, not a reservation or hard cgroup ceiling. `retry_after=390` remains greater than the 330-second worker timeout and `TimeoutStopSec=390` permits graceful termination.

The worker limits are safety ceilings for the current single-worker 768 MB container, not measured capacity claims. A deployment operator must measure representative peak RSS before raising concurrency. Use `/usr/bin/time -v` around a rehearsal worker/job or container metrics (`docker stats --no-stream`) and calculate:

`web worker budget = VM RAM - OS - MySQL - Redis - queue - Reverb - safety reserve`

`safe FPM children = floor(web worker budget / measured peak FPM RSS)`

Until that measurement exists, the checked-in pool deliberately keeps concurrency at two.

## Large-data processing audit

Attendance generation/recalculation, contract expiry, recruitment expiry and leave ledger automation use `chunkById` or `lazyById`. Employee, organization, attendance and payroll CSV exports use generators/cursors and streamed responses. Transaction-local `get()` calls for one order, receipt, invoice or other bounded document remain because replacing them mechanically would not improve safety and could weaken transaction invariants.

PDF and financial artifacts are permanent business evidence. They are not temporary files and are excluded from generic cleanup. Audit export buffers use a 5 MB in-memory threshold. Some small board-pack/payroll adapter paths still use `php://temp`; their business result sets are currently bounded, but queueing/object-storage output is required before treating them as 100k-row exports.

## Retention and disk

| Artifact | Retention | Reason |
|---|---|---|
| Failed queue records | 14 days | Operational diagnosis with bounded growth. |
| Finished/cancelled/unfinished job batches | 7 days | Canonical Laravel batch metadata; active batches are not pruned. |
| Horizon recent/completed | 60 minutes | Opt-in operational UI only. |
| Horizon failed | 14 days | Matches failed-job investigation window. |
| Docker JSON logs | 3 × 10 MB per container | Prevent container log growth. |
| Laravel production file log | disabled by default | Production emits to stderr; no duplicate container copy. |
| DMS/media/payroll/accounting/inventory evidence | preserve | Business or audit evidence, not runtime trash. |
| `storage/app/recovery/**` | protected indefinitely | Incident/PITR evidence; never included in automatic pruning. |

No generic artifact deletion command was added because the audit found no safe, canonical in-progress marker for all possible exports/imports. Existing `tempnam()` paths use `finally` cleanup. Deleting by age alone would risk active or user-owned artifacts.

## Redis and cache

The current Compose topology shares Redis between cache, queues, sessions, locks and broadcast coordination. Because queue/session/lock state is durable, the server uses `noeviction`; `FLUSHALL` and `FLUSHDB` are prohibited. AOF rewrite thresholds bound AOF rewrite behavior but do not impose a data-loss-prone memory cap. Before enabling cache eviction, deploy a separate disposable-cache Redis instance/database with independently monitored memory and TTL policy.

The only `Cache::forever` use is a small integer cache-version key. Business caches use version invalidation/TTL and are not globally flushed on a schedule.

## Storage evidence and monitoring

The local audit observed roughly 2.0 GB of protected incident binlogs under `storage/app/recovery`, about 256 MB of private business artifacts, and 54 MB of logs/browser evidence. These are local facts, not production capacity measurements. Largest operating MySQL tables were approximately 3,135 activity rows and 2,943 attendance rows; production table size remains unknown.

Alert at 70% disk/RAM for investigation and at 85% for operator action. Monitor sustained RAM, swap pressure, Redis memory/AOF size, queue depth, failed jobs, worker restart count and scheduler heartbeat. Alerting must precede deletion. RPO/RTO and final VM CPU/RAM/disk sizing remain business/deployment decisions.

## Safe deployment recycle

Deploy the immutable image, run reviewed migrations once, start Compose services, rebuild Laravel caches, then issue `queue:restart` (or `horizon:terminate` when Horizon is the sole owner) and `reverb:restart`. Allow in-flight work to finish according to Laravel timeouts; do not hard-kill financial jobs mid-transaction. Validate readiness, scheduler heartbeat, queue depth and failed jobs after replacement.

Google Cloud Ops Agent also consumed material host memory during measurement (collector about 69 MiB plus logging agents about 30 MiB combined). It is part of the safety budget and must not be silently omitted. VM swap and Cloud SQL were not changed in this phase.

## Systemd deployment authorization package

This section is **NOT EXECUTED — FUTURE AUTHORIZATION REQUIRED**. Preserve the production dirty tree (`app/Support/EnvironmentSafetyGuard.php` plus the two existing backup files); never use `git reset`, `restore`, `checkout` or `clean` as a deployment shortcut.

The permanent production database guard is represented in source: `AppServiceProvider` passes the configured local and production database names into `EnvironmentSafetyGuard`. Production refuses testing databases, refuses a missing `VPS_PRODUCTION_DATABASE`, and requires the active database to match that explicit allowlist. Production backup artifacts remain non-canonical and must never be imported.

Proposed targets are the matching files under `/etc/systemd/system` and `/etc/php/8.4/fpm/pool.d/zz-runtime-pool.conf`. Before mutation, require 200/200 from `/health/live` and `/health/ready`, copy every target to a new timestamped root-owned backup directory, and record `systemctl cat`. Stage files under a new temporary directory; validate with `systemd-analyze verify` and `php-fpm8.4 -t` before installation. Reload systemd only after validation. Reload FPM gracefully, then restart only the queue worker; scheduler and Reverb need no restart when their unit contents are unchanged. Recheck all services and both health endpoints.

Rollback restores the timestamped configuration copies, reruns both validators, performs `daemon-reload`, gracefully reloads FPM, restarts the queue, and repeats health checks. The disk snapshot is not the primary rollback mechanism for configuration hardening. Expected interruption is a brief FPM graceful reload and queue worker replacement; no VM reboot, database migration, Redis restart or Nginx restart is planned.

## Logging ownership

Production currently writes Laravel's default stack to a 22 MiB `storage/logs/laravel.log`, while journald occupies about 163 MiB and `/var/log` about 304 MiB. A future change should choose one bounded primary owner: set Laravel production to stderr and configure an explicit journald retention ceiling, or retain Laravel `daily` with 14 days and avoid duplicate ingestion. Do not apply Docker JSON rotation to systemd. Because journald forwards to syslog on this host, rsyslog/logrotate ownership must be verified before changing retention; no logging mutation is included in the first queue/FPM rollout.
