# Release Runbook

1. Freeze release scope and identify Git SHA/tag, author, approval/change reference and rollback target.
2. Require green PHPUnit, repository PHPStan level 10, Pint, Blade, Vite, dependency audits, secret scan, Docker validation and image scan.
3. Run migration scanner; manually review every caution/blocking result. Decide whether maintenance is required. Do not claim zero downtime without topology evidence.
4. Confirm backup strategy/reference and restore ownership. Do not create a production backup from CI unless explicitly authorized.
5. Build/push full-SHA image, record registry digest and verify image history/security controls.
6. Run `operations:release-check`. A failed check stops promotion.
7. Run only reviewed forward migrations, then promote application containers.
8. Verify live, ready, metrics-readiness, queue/Horizon, scheduler and Phase 3F-9 observability signals.
9. Run read-only smoke routes only. Never create Employee, Payroll, IAM, Integration, Notification or fake Audit data.
10. Publish immutable manifest/evidence, record acceptance and monitor during the rollback decision window.

Escalate on readiness failure, migration uncertainty, missing digest/evidence, scheduler/queue degradation, 5xx/p95 burn or dead-letter growth. Follow component runbooks before mitigation.
