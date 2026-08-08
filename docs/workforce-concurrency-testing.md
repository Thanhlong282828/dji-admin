# Workforce MySQL concurrency testing

Status: **Phase 3A — COMPLETED; critical process concurrency VERIFIED** (2026-07-26).

- Engine: MySQL 9.6.0; isolation: `REPEATABLE-READ`; isolated schema: `schema_admin_testing`.
- `MysqlProcessRaceRunner` starts two independent PHP/Laravel processes. Each worker writes a ready marker, waits for the common release barrier and then executes one allowlisted scenario/action.
- Worker rejects production database names, non-scalar payloads, unknown scenarios and unknown actions. Temporary payload/barrier files and company-scoped fixtures are cleaned after each case.
- Stable lock order: payroll period → employee/assignment scope → workflow subject; notification delivery locks its delivery marker. Fresh reads occur after acquiring the lock.

Verified process races: shift overlap; attendance approve/approve, approve/reject, approve/cancel, adjustment submit/period lock, lock/lock and approval/lock; leave submit/submit, approve/reject, approve/withdraw and accrual/accrual; overtime overlap submit, approve/reject, reconcile/reconcile and reconcile/period lock; notification delivery/delivery. Notification partial-channel retry is separately verified: a failed broadcast does not duplicate an already-sent database notification and succeeds on retry.

Attendance mid-lock failure injection is `NOT_APPLICABLE`: the service has no safe test-only hook and adding one would create a production backdoor; the complete lock runs in one database transaction, while mandatory lock/lock and submit/lock process races are verified. No synthetic transition was introduced.

Deadlock retries are bounded, never infinite. Schedule-rule management explicitly uses three transaction attempts; critical Attendance/Leave/Overtime workflows retain Laravel's one-attempt default so optimistic-version/business conflicts are never blindly retried. Idempotency and state predicates are evaluated inside the transaction.

Gate evidence: group `mysql-concurrency` passed 7 tests / 132 assertions.
