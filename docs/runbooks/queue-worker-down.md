# Queue worker down

- Symptoms: pending queue grows, oldest job age rises, Horizon worker absent.
- Verify: health dashboard, `php artisan horizon:status`, worker logs and Redis readiness.
- Safe mitigation: restore Redis first, then restart the worker/Horizon using the deployment supervisor.
- Rollback: return to the last immutable application image if the worker crashes on the current release.
- Recovery: backlog decreases, failed count stabilizes, idempotency tests remain green.
- Escalate: repeated poison job, irreversible domain error, or backlog outside the configured operational threshold.
