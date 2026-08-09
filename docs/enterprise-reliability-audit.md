# Enterprise Reliability Audit — Phase 3F-8A

Date: 2026-08-09. Scope: reliability only; production database read-only.

## Evidence before implementation

- Public liveness was process-only and safe. Readiness checked MySQL, Redis and whether the configured queue driver was durable.
- Metrics-readiness exposed only component names that were ready; it did not expose credentials or endpoints.
- Redis queue, Horizon, worker, scheduler and Reverb are separate production services with health/resource/security controls from Phase 3F-7.
- Workflow, Notification, IAM, Integration and Audit use separate transactional outboxes/checkpoints. They have idempotency and process-race tests, but no common backlog/oldest-age view.
- Scheduler tasks use `withoutOverlapping`; Attendance/Leave also use `onOneServer`. There was no common scheduler heartbeat or stale detection.
- `failed_jobs` and Integration dead letters existed, but there was no unified operations view.
- Reverb is non-critical to core HTTP. Redis and MySQL are readiness dependencies; loss must degrade readiness without failing liveness.

## Gaps found

1. No configurable SLO/error-budget calculation.
2. No scheduler heartbeat/staleness signal.
3. No unified queue, failed-job, outbox-lag and integration-dead-letter read model.
4. No permission-protected operations UI.
5. Failure/recovery evidence must remain isolated from `schema_admin` and `schema_admin_development`.

No reliability database migration is justified: configuration, cache heartbeat and existing operational tables are sufficient.

## Final static-analysis finding

The Phase 3F-8 implementation and its tests pass PHPStan level 10 with zero errors. The mandated repository-wide `app Modules` command reports 418 legacy errors concentrated in disabled/skeleton modules such as Article and ArticleCategory (missing parameter/return types and non-literal view names). They predate and are outside the reliability boundary; no baseline, ignore rule or unrelated refactor was introduced. This prevents an overall COMPLETED claim.
# Phase 3F-8 completion audit addendum

Repository-wide PHPStan is now clean at level 10. Browser verification exposed stale serialized module objects in file-backed cache and an onboarding query using `required` instead of the migrated `is_required` column; both runtime defects were fixed and the 18-page desktop/mobile matrix passed. Remaining blockers are physical production/development index drift and shared-schema isolation in the all-in-one PHPUnit run.

## Closing-gate rerun

- The prior shared-schema failures were caused by orphaned `workforce_concurrency_worker.php` children after the controlling PHPUnit process was interrupted. They continued holding locks and writing fixtures. This was not a normal `MysqlProcessRaceRunner` completion path: its bounded barrier, wait and `finally` cleanup work when the parent is allowed to finish.
- Both prior assertion failures passed independently: 2 tests, 6 assertions.
- Clean full PHPUnit: 393 tests, 4,284 assertions, 1 skipped, exit code 0, 1,241.747 seconds.
- Dedicated `tests/Concurrency`: 60 tests, 486 assertions, exit code 0.
- No per-process database implementation was introduced because parent fixtures and child workers intentionally share committed rows during a race. The safe operational rule is one controlling suite per testing schema and explicit orphan-worker cleanup after an externally interrupted run.
- Comparator normalization now ignores generated index/FK names for logical matching and compares FK source columns, target columns, `UPDATE_RULE` and `DELETE_RULE`. Genuine composite-index differences remain failures.

## Canonical index repair

Historical production tables caused conditional create migrations to skip five canonical BTREE indexes: analytics run idempotency (unique), analytics period/status, career aggregate timeline, leave-policy assignment effective date and payroll-input blocking scope. Migration `2026_08_13_000044_repair_canonical_production_indexes.php` added only missing logical indexes. Pretend evidence is stored in `storage/logs/phase3f8-index-repair-pretend.txt`.

Production migration batch 56 completed. Production business counts were identical before/after. Production, development, testing and browser-testing now each contain 61 migrations and comparator logical drift is zero.
