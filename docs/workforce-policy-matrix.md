# Workforce dedicated Policy matrix

Status: **Phase 3A — COMPLETED** (2026-07-26).

Dedicated Policies are registered once by `WorkforcePolicyGateRegistrar`; controllers authorize the exact resource/action before Command Bus dispatch. `WorkforceAuthorizationPolicy` now only supplies shared company and employee-scope primitives.

| Area | Dedicated Policy | Covered actions |
|---|---|---|
| Calendar | WorkCalendarPolicy, HolidayPolicy | view, create, update, archive, day management, export |
| Shift | ShiftPolicy, EmployeeShiftAssignmentPolicy | view, CRUD/archive, break rules, assign/change/end, export |
| Attendance | AttendanceRecordPolicy, AttendanceAdjustmentPolicy, AttendancePeriodPolicy | own/read, capture, submit, approve/reject, adjustments, readiness, lock, export |
| Leave | LeavePolicyManagementPolicy, EmployeeLeavePolicyAssignmentPolicy, LeaveRequestPolicy, LeaveBalancePolicy, LeaveAttachmentPolicy | policy/assignment, own/request workflow, approve/reject/withdraw, balance, private attachments, export |
| Overtime | OvertimePolicyManagementPolicy, EmployeeOvertimePolicyAssignmentPolicy, OvertimeRequestPolicy, OvertimeReconciliationPolicy | policy/assignment, own/request workflow, approval, reconciliation, export |
| Payroll boundary | PayrollInputPolicy, PayrollPeriodPolicy | view/readiness/generate/export and period view/lock |

`DedicatedWorkforcePolicyMatrixTest` reflects every required method and verifies permission, tenant, own/approver scope, state and locked-period denial. Denied HTTP mutations return 403 and leave history, activity, notification and subject state unchanged. Role names are never hard-coded.

Focused result: `3 tests / 263 assertions`, passed.
