# Rollback Runbook

## Decision

Separate application rollback from database recovery. Prefer an application rollback only when the previous image exists by immutable tag@digest and schema/config are backward compatible. Otherwise stop and use a reviewed forward-fix.

## Procedure

1. Freeze promotion, preserve release/correlation evidence and classify impact.
2. Confirm `PREVIOUS_APP_IMAGE`, migration compatibility, config compatibility and database backward compatibility.
3. Run rollback precheck. Never set compatibility flags merely to bypass the gate.
4. Start the previous immutable application image; do not run `migrate:rollback` automatically.
5. Verify live/ready, read-only smoke, worker/Horizon, scheduler and observability signals.
6. Record outcome as `rolled_back` or `forward_fixed`, with approver/change reference.

Database restoration follows the disaster-recovery process into an isolated `*_restore_testing` database first. Production cutover requires explicit ownership and approval.
# Phase 3F-11 evidence

Local application rollback was simulated from candidate `280043f...` to prior image `0890335...`; both returned ready 200 on the shared forward schema after Redis was healthy. This proves only local compatibility, not production blue/green. Database down migrations remain prohibited; use forward-fix when compatibility is uncertain.

The corrected candidate repeated green-to-blue rollback successfully after telemetry hardening. A shared Redis outage is handled through dependency recovery, while release-check safely blocks acceptance without a stack-trace crash.
