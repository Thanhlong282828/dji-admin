# Attendance foundation

Raw `attendance_events` are immutable, UTC and deduplicated by company + UUID. `AttendanceCaptureService` authoritatively timestamps check-in/out, requires an active Employee Work Profile and prevents invalid open-session transitions. Daily records are unique per company/employee/date and separate presence, approval and calculation status. Schedule precedence is employee shift assignment → work profile default shift → calendar-day default shift → no shift. External biometric/device integration is **NOT_CONNECTED**.
# Phase 3A-2 update

Attendance adjustment, attendance approval and payroll-period readiness/locking now use CQRS handlers and a transactional domain service. Raw events remain immutable. See `docs/attendance-adjustment-approval-after.md` for routes, state transitions, security boundaries and verified limitations.
