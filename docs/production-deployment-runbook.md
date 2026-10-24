# Production deployment runbook

## Preconditions

Obtain explicit production authority and verify domain/TLS, separate MySQL database and least-privilege user, Redis, secret injection, mail, storage/object lifecycle, backup/restore evidence, Cloudflare permission if Images is enabled, immutable image digest, previous image, approval and quality evidence. Never use a local schema or run local demo bootstrap.

## Deployment

1. Run `scripts/operations/predeploy-check.sh` in the release workspace and review `operations:migration-risk`.
2. Build the Docker artifact in CI; confirm non-root runtime and scan contents. Push by immutable digest.
3. Render `compose.yml + docker-compose.prod.yml` with secrets supplied by the deployment environment.
4. Run `operations:release-check` with image, rollback target, quality and backup references.
5. Decide maintenance mode only for a reviewed incompatible/locking migration; additive compatible releases should avoid blanket downtime.
6. Run `php artisan migrate --force` exactly once under authorized deployment control. Never run it from the readiness command.
7. Start/update app, Nginx, queue/Horizon, scheduler and Reverb. Run config/route/view cache only after final environment injection; restart workers gracefully.
8. Verify `/health/live`, `/health/ready`, scheduler heartbeat, queues and failed jobs. Run `postdeploy-smoke.sh` with the HTTPS base URL.
9. Monitor logs, latency, DB/Redis, queue depth, failed jobs and integration kill switches. Generate immutable release evidence.

Required writable paths are `storage/` and `bootstrap/cache/`, owned by the runtime user; never recommend `777`. Database backup does not include Cloudflare Images or object storage, which require provider lifecycle/backup controls.
