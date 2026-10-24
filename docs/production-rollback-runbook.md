# Production rollback runbook

| Incident | Preferred response |
|---|---|
| Code defect; schema/config backward compatible | Switch to the previous immutable image and verify health/smoke. |
| Bad configuration | Restore reviewed configuration and redeploy; do not expose values in tickets/logs. |
| Unsafe migration | Stop rollout and forward-fix. Do not blindly run `migrate:rollback`. |
| External adapter failure | Enable the existing kill switch/disable delivery and preserve outbox/idempotency evidence. |
| Reverb outage | Restart/recover Reverb; core CRUD remains fail-open. |
| Redis/DB outage | Restore dependency first, then verify readiness/workers; use DR procedure for data recovery. |

Rollback requires `MIGRATION_COMPATIBLE`, `CONFIG_COMPATIBLE` and `DATABASE_BACKWARD_COMPATIBLE` evidence. Database restore is first verified in an isolated restore database with checksum and application readiness. Production cutover always needs explicit owner approval.
