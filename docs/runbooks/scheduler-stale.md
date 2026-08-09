# Scheduler stale

- Symptoms: heartbeat age exceeds configured threshold.
- Verify: scheduler container/process, Redis readiness and `schedule:list`.
- Safe mitigation: restart only the scheduler process after confirming no overlapping task is active.
- Rollback: restore the prior immutable image if schedule boot fails.
- Recovery: heartbeat becomes healthy and `withoutOverlapping` locks remain consistent.
- Escalate: repeated duplicate ticks or a stuck domain job.
