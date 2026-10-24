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

Branch revenue, operating expenses, rent contracts, and assets use deterministic monthly references. Each local-demo monthly revenue total is composed from three traceable ledger lines (`service_delivery`, `managed_services`, and `training_support`) instead of one unexplained lump sum. Those lines remain synthetic DB-backed service evidence, not external payment-provider confirmation. A second source follows real Commerce lineage: nine Orders receive local settlement evidence, six are Seller-confirmed, and three traverse `packed → shipped → delivered`; only those three create `commerce_order` revenue (60,460,000 VND subtotal). Inventory is consumed once and unpaid/undelivered Orders contribute zero. Payroll cost comes from Payroll Items, rent from effective contracts, and depreciation from the asset register. Branch P&L is management reporting, not legal/statutory accounting.

## Idempotency and safety

Repeated `php artisan app:bootstrap-local-demo` runs reconcile the same owned references. Mutable local-demo payroll may be regenerated through canonical services; submitted, approved, finalized, or non-local payroll is not rewritten. Duplicate owned references are a Data Quality failure.

The command is guarded to the local manual/demo database contract and must not run in production or against PHPUnit/browser databases.

## Commerce, media and recruitment lineage

Bootstrap-owned Product covers are deterministic local PNG evidence generated only when the local media driver and GD are available. They are staged and attached through `MediaService`, carry the Product Company scope and never call Cloudflare. Missing/invalid media is rendered as an explicit `NO_MEDIA`/`BROKEN_MEDIA` state rather than a fabricated image.

POS samples are not totals inserted for display. `BranchPosService` creates Customer → Service/Product Visit → paid Receipt → Inventory Movement → recognized Branch Revenue. Re-running the bootstrap reuses the same references, and Inventory Balance is reconciled from canonical movements/reservations instead of being reset by a catalog seeder.

The hired recruitment example follows Candidate → Application → maker-checker Offer lifecycle → `HireCandidateService` → Employee → primary Assignment → Contract → Onboarding Case. The recruitment seeder preserves that completed linkage on subsequent runs and does not overwrite it with a naked stage value.

Executive Dashboard metrics are computed at request time from those canonical sources. It owns no Employee, Payroll, Receipt, Revenue, Inventory or Recruitment totals.
