# Enterprise Reporting Audit

## Conclusion

Phase 3F-29 audited Analytics, Forecasting, Executive Planning, Planning Execution, Payroll/Attendance/Audit exports and the installed document stack. The repository already contains Dompdf 3.1, Maatwebsite Excel/PhpSpreadsheet, streamed CSV conventions and spreadsheet formula sanitization. A bounded synchronous management board pack can therefore support HTML preview, CSV, XLSX and PDF without an external service or new dependency.

## Source reuse

The reporting read side reuses `EnterpriseAnalyticsService` and `PlanningExecutionService` (which itself reuses Executive Planning). It does not duplicate workforce, attendance, leave, recruitment, Payroll, compensation, variance or Data Quality formulas. Monthly, quarterly and YTD filters are derived from a validated period and bounded to the selected year/scope.

## Artifact decision

**No migration and no persisted report artifact.** The current requirement is live preview/export, not governed certification/history. Export bytes carry a real SHA-256 response header, but the report is explicitly labelled live, not immutable or certified. Persistent artifacts require a future owner, retention/access policy and certification/version contract.

## Security and performance

- `analytics.view` protects preview; `analytics.export` protects all formats.
- Payroll/Compensation sections are created only with `payroll.assignment.view`; they are absent from unauthorized HTML and export data.
- `ReportingFilter` validates tenant-owned Cost Center, Unit and Position scope.
- CSV/XLSX cells beginning with spreadsheet operators are escaped.
- Queries remain fixed at 91 when 50 Employees are added: no per-Employee query.
- Critical Data Quality failure becomes a visible source warning and never triggers auto-fix.

## Existing export distinction

Payroll/Audit persisted evidence keeps its own integrity/lifecycle semantics. Enterprise Board Pack exports are generated live in memory/temp storage and are not stored, queued, approved or sent externally.
