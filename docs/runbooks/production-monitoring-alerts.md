# Production monitoring and alert runbook

1. Verify the target host and deployment mode without printing environment values.
2. Check health endpoints, container/systemd service state, Redis, the selected queue worker topology, scheduler heartbeat and ClamAV readiness read-only.
3. Run `php artisan documents:storage-readiness --strict-production` only after confirming it is the deployed read-only command.
4. Never install packages, restart services, change `.env`, migrate, send a real alert or run reconciliation without separate approval.
5. Treat `not_configured`, `configured_not_verified`, `requires_vps_verification` and `verified` as distinct states.
6. For resource alerts, inspect sustained windows rather than one sample. Critical disk, unavailable PHP-FPM/worker/ClamAV and sustained storage failure require escalation.
7. Redact hostnames when reporting externally and never copy credentials, payloads, private paths, signed URLs or object keys.
8. Alert acknowledgment/resolution needs a canonical audited persistence capability; until implemented, the dashboard alert list is an evaluated snapshot only.

Suggested operator-only checks are `systemctl --no-pager --full status ...`, `docker compose ps`, `redis-cli ping`, `php artisan horizon:status`, `php artisan schedule:list`, `clamscan --version`, and inspection of signature timestamps. Select commands according to the actual deployment; do not run both Docker and systemd assumptions blindly.
