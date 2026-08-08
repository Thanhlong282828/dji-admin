# Payroll performance verification

Both benchmarks are PHPUnit groups and enforce `schema_admin_testing` through `phpunit.xml`.

- `payroll-performance`: 1,000 employees are bulk-fixtured in chunks of 200; the production `PayrollRunService::calculate()` creates 1,000 Payroll Items and 5,000 Component Breakdown rows. Latest measured calculation: 875.98 ms; peak memory delta: 10 MiB; fixture: 240.45 ms.
- `payroll-large-export`: 5,000 Payroll Item rows are streamed through the production HTTP export and cursor repository. Latest measured full stream: 105.49 ms; stream response start: 40.80 ms; peak memory delta: 2 MiB; fixture: 451.40 ms.

Measurements vary by workstation. Tests assert aggregate integrity, uniqueness, exactly one BOM/header, exact data-row count and stable final row. Query count is `NOT_MEASURED`; source audit confirms export uses `cursor()` and calculation bulk-loads inputs/assignments before its employee loop.
