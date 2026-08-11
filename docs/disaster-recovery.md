# Disaster Recovery

## Objectives

- Target RPO: 15 minutes when provider binlogs are available; otherwise 24 hours from the daily logical backup.
- Target RTO: 4 hours for critical admin service; 8 hours for asynchronous/reporting functions.
- Targets are operational objectives pending a measured restore exercise and infrastructure SLA.

## Incident response

1. Incident commander classifies severity, freezes deployments, preserves logs, and opens a correlation record.
2. Security lead rotates affected credentials outside the repository. Operations isolates compromised connectors/workers.
3. Database owner takes no destructive action until recovery point and checksum evidence are approved.
4. Communications owner provides timed internal updates without sensitive payloads.

## Recovery and rollback

1. Prefer immutable image rollback using `scripts/deploy/rollback.sh` and the last approved image digest.
2. Do not roll migrations back automatically. Add forward-compatible corrective migrations after impact analysis.
3. For data recovery, restore into an isolated `*_restore_testing` database, verify integrity and migration inventory, then obtain two-person approval before any production cutover.
4. Re-enable scheduler, queue, Reverb and integrations progressively; verify `/health/ready`, failed jobs, outboxes, idempotency checkpoints and external delivery status.
5. Close with timeline, root cause, affected tenant scope, RPO/RTO actuals and corrective actions.

## Exercise cadence

Run a tabletop quarterly and an isolated restore exercise twice yearly. Load tests and disaster exercises must never target production.
# Reliability verification addendum

Redis and MySQL recovery contracts are exercised only in an isolated Docker runtime. Liveness remains available while readiness reports 503, then returns to 200 after dependency recovery. Operational response procedures are in `docs/runbooks/`.
# Observability references

Phase 3F-9 alerts link existing runbooks by component and severity. Alert snapshots are informational; they do not automatically create incidents or perform recovery. Operators must verify symptoms against health/readiness and follow the corresponding runbook before mitigation.

Release rollback now fails closed unless prior image, migration, config and database backward compatibility are proven. Production database rollback remains manual; forward-fix is preferred.
# Release certification linkage

Phase 3F-11 validated an application-only green-to-blue rollback against a forward-compatible isolated schema. Shared Redis loss affected both revisions, so dependency recovery—not revision rollback—is the correct first response for that incident class.
# Credential recovery

Compromised or unavailable credentials follow the Phase 3F-13 reference cutover runbooks. APP_KEY and database credentials require explicit dual-key/least-privilege recovery plans and are never rotated automatically during disaster recovery tests.

Data exposure and privacy incidents use `runbooks/data-exposure.md`, `privacy-incident.md` and `encryption-key-compromise.md`. Incident records contain classification/component/correlation metadata, never leaked values.

## Phase 3F-15 measured exercise

An isolated testing exercise verified logical backup/restore integrity, Redis/MySQL health degradation and recovery, retained queue work, scheduler stale/fresh detection, non-critical Reverb degradation and immutable-image rollback compatibility. Testing observed RTO was 31 seconds and deterministic fixture RPO was 0 seconds; neither is an official production SLA. PITR and private-storage outage remain explicitly design-only.
# Security evidence during recovery

Preserve immutable audit/release/security evidence, rotate referenced credentials after compromise and verify tenant/authorization controls before reopening traffic.
