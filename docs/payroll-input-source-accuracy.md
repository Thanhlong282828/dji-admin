# Payroll Input Source Accuracy

## Source audit

- Approved overtime stores `work_date`, `starts_at`, `ends_at`, `payable_minutes`, status and version timestamps. It has no explicit overtime category and no night-overtime field.
- Classification therefore uses the employee's effective `employee_work_profiles.work_calendar_id`, `work_calendar_days`, and company/calendar-scoped `company_holidays`.
- A configured non-working holiday takes precedence over the weekly calendar. A calendar working day maps to `weekday`; a configured rest day maps to `weekend` (rest-day overtime).
- Missing effective profile/calendar/day or invalid intervals produce a blocking issue. There is no silent weekday fallback.
- Night overtime remains `NOT_SUPPORTED_BY_SOURCE_SCHEMA`; no night monetary result is claimed.

## Immutable capture

`payroll_input_source_records` records the approved Attendance, Leave and Overtime source ID, captured status/date/update timestamp, integer minute quantity, classification and SHA-256 checksum. Cross-midnight overtime is split at local midnight and stored as independently dated segments. Existing snapshot rows are never rewritten when live sources change.

## Classification and calculation flow

`PayrollInputGenerationService` → `PayrollOvertimeClassificationService::classify()` → effective calendar/holiday lookup → categorized integer minutes → `payroll_employee_inputs` → validation → exact snapshot read by `PayrollRunService::calculate()` → categorized minutes copied to `payroll_items`.

The calculation foundation preserves weekday/rest-day/holiday quantities but does not introduce statutory overtime rates. Tax, insurance and payslip engines remain out of scope.

## Security and UI

Employee list/detail endpoints use the existing verified/authenticated Payroll route group, `payroll-period.view`, company-access Gate, snapshot policy, company-scoped query repository, server pagination and stable employee-number/ID ordering. Detail identity and quantities come from captured snapshot fields, not live Employee rows.

