# Enterprise Business Continuity

Phase 3F-15 provides testing-only disaster-recovery evidence without owning or changing business state. MySQL and core HTTP are Tier 0; Redis, queue/Horizon and scheduler are Tier 1; Reverb and external adapters are Tier 2 degradable components.

## Recovery contract

- MySQL/Redis unavailable: `/health/live` remains 200 and readiness is 503 with a safe payload.
- Queue workers unavailable: durable Redis jobs remain queued and are processed after recovery.
- Scheduler unavailable: heartbeat becomes stale; a single guarded scheduler restores freshness.
- Reverb unavailable: realtime degrades while core readiness remains healthy.
- Integration/Notification failures remain isolated behind bounded retry, idempotency and dead-letter/channel state.
- Database rollback is not automatic. Application rollback uses a prior immutable compatible image; forward-fix is preferred for schema changes.

`operations:dr-check` only accepts `APP_ENV=testing` and `schema_admin_testing_*dr`. Its immutable certificate is explicitly `testing_only_not_production_certification` and contains aggregate evidence only.

## Final evidence

The isolated `schema_admin_testing_dr` exercise used 61 migrations and one synthetic company fixture. Backup was 42,213 bytes, mode 0600, 0.57 seconds; restore was 3.77 seconds into a fresh database. Source/restored fixture checksums matched. Restored inventory contained 230 tables, 1,778 index entries and 720 foreign keys. Redis and MySQL recovered readiness in 6 seconds; scheduler in 31 seconds; queue returned to zero with no failed jobs; Reverb loss did not affect core readiness. A prior immutable image booted ready against the forward-compatible schema.

Certificate: `14432b506ba79994d7a7bb252442d13ade867d1b958536b6e754e7b29a34fecf`.

Quality evidence: focused continuity/outbox 54 tests / 353 assertions; Full PHPUnit 436 tests / 4,529 assertions / one skipped / exit 0; repository and affected-test PHPStan level 10 have 0 errors; Pint, Blade, Vite, Composer/npm audit, secret scan and base/production/runtime/DR Compose validation pass. Exact browser 1280×800 and 390×844 passed with zero overflow/console/page errors and fixture cleanup zero. All `phase3f15` containers, volumes, image and disposable databases were removed. Production counts remained unchanged.

Phase 3F-15 status: **COMPLETED**.
# Phase 3F-16 security incidents

Continuity response links to the account, webhook, privilege-escalation and general security runbooks. Evidence preservation precedes recovery mutation.
