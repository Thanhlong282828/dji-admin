# Workforce CSV export filter parity

All six exports use `WorkforceExportRequest`, Query Bus, `GetWorkforceCsvExportQuery`, a cursor-based handler, `StreamingCsvResponseFactory`, and `CsvCellSanitizer`.

| Export | Supported filters |
|---|---|
| Attendance | employee, shift, date range, approval status, calculation status, locked |
| Leave Request | employee, policy, date range, status |
| Leave Balance | employee, policy, effective date range |
| Overtime | employee, policy, work-date range, status, calculation status |
| Payroll Input | employee, snapshot, period date range, snapshot status |
| Workforce | company-scoped resource stream |

Company is authorized through Gate and is always applied server-side. CSV values beginning with `=`, `+`, `-`, `@`, tab or carriage return are prefixed with an apostrophe; numeric cells remain numeric. Test evidence validates headers/content, filter exclusion, tenant exclusion, formula protection and UTF-8.

Latest large-volume result: 5,000 rows, 61.86 ms response streaming, no additional measured process peak and a hard assertion below 64 MiB. Metrics: `storage/logs/workforce-large-export-metrics.json`.
