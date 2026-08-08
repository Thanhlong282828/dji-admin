# Employee expiry schedule

- 00:20: `ExpireEmploymentContractsJob`.
- 07:00: `NotifyExpiringEmployeeCredentialsJob`.
- 07:10: `NotifyExpiringDataConsentsJob`.
- 07:20: `NotifyExpiringEmployeeDocumentsJob`.

Detection jobs dùng daily cache lock để tránh chạy lặp trong cùng cửa sổ.
# Recruitment schedules

- `ExpireCandidateOffersJob`: every five minutes, without overlap.
- `SendInterviewRemindersJob`: every five minutes, without overlap; emits idempotent 24-hour and 1-hour internal reminder actions only for scheduled/confirmed interviews.
# Phase 2.5C-3

Core Hire is synchronous and is not scheduled or queued. Only after-commit notification delivery uses the existing queue. External provisioning tasks remain pending and no provider polling schedule was added.
# Workforce schedules

- 00:05 daily: generate attendance daily records.
- Every 10 minutes: recalculate open records.
- Hourly: detect missing attendance.

All three jobs are chunked/idempotent and registered once with `withoutOverlapping` and `onOneServer`. Leave accrual scheduler remains NOT_STARTED.
