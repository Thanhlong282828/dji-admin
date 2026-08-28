# Realistic operating demo dataset

## Purpose and ownership

The local demo bootstrap produces deterministic synthetic operating evidence for `schema_admin`. It is not production data or external-provider confirmation.

Only records carrying the local-demo ownership contract are reconciled. Employee ownership is bounded to employee codes `E0001` through `E0035`; manual employees and user-created attendance, compensation, branch assignments, or finalized payroll are not rewritten. Deterministic `LOCAL-*` references make ownership and idempotency auditable.

## Canonical lineage

```text
Company -> Branch -> Employee -> current Assignment
        -> Attendance / approved Leave / approved Overtime
        -> effective Salary Assignment and approved Branch Bonus
        -> immutable Payroll Input snapshot
        -> Payroll Run -> Payroll Item -> Payslip
        -> Branch financial performance and management dashboards
```

The bootstrap creates source records and invokes canonical Payroll services. It does not insert final overtime pay, late or unpaid-leave deductions, insurance, PIT, gross, deductions, or net pay directly into Payroll Items.

## Operating patterns

The 33 bootstrap-owned employees are distributed across existing HCM, Hanoi, and Da Nang branches and retain existing units, positions, and current primary assignments. Salary profiles are deterministic by career level, responsibility, and supported components.

Attendance spans multiple months and includes on-time work, bounded recurring lateness, early departure, approved overtime, paid leave, and unpaid leave. Work-calendar classification determines weekdays and weekends. Holiday overtime is not fabricated without a holiday-calendar source.

Snapshot-driven variable components use immutable Payroll Input quantities. Rates come from effective Salary Assignments; the seeder never hard-codes final monetary deductions.

## Statutory demonstration

Effective-dated tax and insurance policies provide synthetic configuration evidence so the real engine produces zero and positive PIT cases and positive BHXH, BHYT, and BHTN cases. Policy/version, salary basis, rate, cap/floor, and effective-date trace remain available where supported.

These policies require legal verification before production use. Their output is not a statutory filing claim.

## Bonus and finance semantics

Branch bonuses follow proposal, authorization, approval, effective compensation assignment, Payroll Input, and Payroll Item. Branch leadership never grants IAM privileges by itself.

Branch revenue, operating expenses, rent contracts, and assets use deterministic monthly references. Payroll cost comes from Payroll Items, rent from effective contracts, and depreciation from the asset register. Branch P&L is management reporting, not legal/statutory accounting.

## Idempotency and safety

Repeated `php artisan app:bootstrap-local-demo` runs reconcile the same owned references. Mutable local-demo payroll may be regenerated through canonical services; submitted, approved, finalized, or non-local payroll is not rewritten. Duplicate owned references are a Data Quality failure.

The command is guarded to the local manual/demo database contract and must not run in production or against PHPUnit/browser databases.
