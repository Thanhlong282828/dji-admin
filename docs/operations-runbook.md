# ERP operations runbook

All production commands require explicit deployment authority. Never use `schema_admin`, `schema_admin_testing`, `schema_admin_browser_testing`, demo bootstrap or browser fixtures in production. Never print secret values.

## Deploy

1. Verify immutable revision/artifact, previous rollback artifact, production environment injection and backup evidence.
2. Run `scripts/operations/predeploy-check.sh` and `php artisan operations:migration-risk` outside production first.
3. Install production dependencies with optimized autoload and deploy built Vite assets.
4. Decide maintenance mode from migration compatibility; do not enter maintenance mode automatically for additive compatible changes.
5. Under one authorized release process run `php artisan migrate --force` once.
6. After final environment injection run `php artisan optimize:clear`, then `config:cache`, `route:cache` and `view:cache`.
7. Gracefully restart queue workers (`php artisan queue:restart`); terminate Horizon using its canonical command if Horizon owns workers. Reload Reverb separately.
8. Verify `/health/live`, `/health/ready`, scheduler heartbeat and read-only smoke tests. Monitor errors, latency, queue depth and failed jobs.

If maintenance mode is required, use `php artisan down` immediately before the incompatible step and always execute `php artisan up` after validation or rollback. Record an operator/approval; never leave the application down after a failed shell step.

## Health and observability

- Liveness means the PHP application process responds; it must not depend on optional Cloudflare/Reverb.
- Readiness checks DB, Redis and queue backend without exposing exceptions or credentials.
- Propagate `X-Request-ID` through ingress. Logs include request/correlation ID, route and safe environment context. Business audit records preserve actor/company/resource references.
- Alert on readiness failure, HTTP 5xx rate, latency, queue depth, failed jobs, stale scheduler heartbeat, disk pressure and database connections.

## Queue and failed jobs

- Inspect: `php artisan queue:failed` and Horizon dashboard/metrics where enabled.
- Retry one reviewed job: `php artisan queue:retry <uuid>`.
- Retry-all is prohibited for financial/external side effects until idempotency evidence is confirmed.
- Forget a resolved record: `php artisan queue:forget <uuid>`; prune only under retention policy with `php artisan queue:prune-failed`.
- Deploy workers with `php artisan queue:restart`; avoid duplicate Supervisor and Horizon ownership of the same queue. The shipped Compose topology starts the bounded `queue:work` service by default. Horizon is opt-in through the `horizon-only` profile and must replace, never accompany, the default worker manager for the same queues.

## Scheduler

Run exactly once per minute from the scheduler host: `php artisan schedule:run`. Verify `operations:scheduler:heartbeat`. Shared Redis locks are required for multi-node `onOneServer`/overlap protection. Do not manually invoke lifecycle jobs against production without owner approval.

## Cache and Redis

Config/routes/views are release caches; business authoritative financial values remain database-backed. On Redis loss, restore service, validate prefixes/connections, then rebuild disposable caches. Do not restore stale serialized application objects. Queue payloads and locks are operational state and require separate incident review.

## Backup and restore

- Validate only: `scripts/operations/backup-database.sh --validate-only`.
- Execution requires an exact confirmed database, process-injected credential and protected absolute target. The script creates checksum evidence.
- Restore verification may target only a disposable `*_restore_testing` database through `scripts/operations/restore-verify.sh`.
- Disable mail, workers, scheduler, webhooks, Reverb and external adapters during restore testing. Verify checksum, migrations, integrity and read-only smoke, then remove the disposable database under approval.
- Database backup does not include private documents or Cloudflare/object data. Back up each storage domain and metadata consistently.
- RPO/RTO remain `BUSINESS_CONFIGURATION_REQUIRED`.

## Incident response

| Incident | First response | Recovery/decision |
|---|---|---|
| DB unavailable | Stop mutations/workers; preserve logs and request IDs | Restore connectivity or fail over; verify migrations and integrity before workers |
| Redis unavailable | Assess cache vs queue impact; prevent duplicate worker ownership | Restore Redis; validate queues/locks; warm disposable caches |
| Queue backlog | Inspect queue/age/failures and worker health | Scale only after confirming dependency health; review financial jobs individually |
| Failed financial job | Freeze source retry and capture reference/journal/payment state | Confirm idempotency, then retry the specific UUID or apply owner-domain correction |
| Cloudflare unavailable | Keep core ERP available; switch/upload only through configured media policy | Retry with bounded adapter timeout; do not expose token or bulk overwrite media |
| Reverb unavailable | Preserve CRUD fail-open behavior | Restart supervised Reverb/proxy and validate private channel authorization |
| Disk/private storage issue | Stop uploads/exports before capacity exhaustion | Restore capacity/permissions; verify SHA-256 and authorized download |
| High error rate | Correlate release revision and request IDs; pause rollout | Roll back compatible code/config or forward-fix; preserve DB evidence |

## Rollback decision

- Code defect with compatible DB: redeploy the previous immutable release.
- Bad configuration: restore the previous secret/config version and rebuild caches.
- Queue payload incompatibility: stop workers, deploy compatible consumers, then restart deliberately.
- Database change: prefer forward-compatible fix. Do not blindly run `migrate:rollback` for financial/history tables.
- External integration failure: activate the existing kill switch/fail-open policy where supported.

Recovery order is database → private/object storage → Redis/cache → queue → application → Reverb → external integrations. After recovery run readiness, smoke, reconciliation and Data Quality checks.
