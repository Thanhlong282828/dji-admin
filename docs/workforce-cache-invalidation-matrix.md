# Workforce cache invalidation matrix

Status: **Phase 3A — COMPLETED** (2026-07-26).

All cached Workforce read models use `WorkforceCacheRegistry::remember()` with company, scope, read-model name, filter hash and version. Mutations increment only the applicable company/scope version; `Cache::flush()` is not used.

| Read model | Scope | Repository/handler | Invalidated by |
|---|---|---|---|
| Workforce management/editor | workforce | EloquentWorkforceReadRepository, EloquentScheduleRuleReadRepository | calendar, holiday, shift, break and assignment changes |
| Attendance detail/readiness/dashboard | attendance | EloquentAttendanceWorkflowRepository, GetAttendanceDashboardHandler | attendance and adjustment workflow, period lock |
| Leave management | leave | EloquentLeaveReadRepository | leave policy/assignment/request/attachment/ledger changes |
| Overtime management | overtime | EloquentOvertimeReadRepository | overtime policy/assignment/request/reconciliation changes |
| Payroll input | payroll-input | not cached: command/read boundary is period-sensitive | downstream invalidation event only |

`WorkforceReadAfterWriteCacheTest` proves actual prime → mutate → fresh read for workforce, attendance, leave and overtime, and proves another company retains its independent cached value. Critical attendance lock readiness deliberately uses `readinessFresh()` inside the transaction. Cache invalidation listeners run before notification listeners; broadcast and database notification payloads never carry cached models.

