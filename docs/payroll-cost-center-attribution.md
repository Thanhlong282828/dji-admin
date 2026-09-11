# Payroll historical Cost Center attribution

## Ownership and historical truth

Organization owns Cost Center and the Organization Unit mapping. Employee owns the effective-dated primary assignment. Payroll owns the immutable employee input captured for a payroll period. Migration `000098` adds nullable `cost_center_id`, `cost_center_code_snapshot`, and `cost_center_name_snapshot` to that immutable Payroll input; it creates no table and performs no historical backfill.

At generation time Payroll bulk-loads all applicable primary assignments, their Organization Units, and Cost Centers for the employee set. The selected assignment must overlap the payroll period and is ordered deterministically by effective date and ID. Cross-company Cost Center evidence rejects the transaction. Cost Center metadata participates in the input source hash.

After capture, later changes to `organization_units.cost_center_id` do not affect the old input. A new Payroll Input version may capture the new Cost Center. Payroll Item does not duplicate the dimension because its run retains stable lineage to the immutable Payroll Input Snapshot.

## Accounting behavior

Payroll accounting reads Cost Center only from `payroll_employee_inputs`; it no longer resolves Cost Center through the current Organization Unit. Cost Center is attached at journal-line granularity because one run can contain multiple Cost Centers.

Expense lines such as salary expense and employer statutory expense inherit the employee snapshot Cost Center. Salary payable, PIT payable, employee/statutory liability, and other balance-sheet lines intentionally do not receive Cost Center. Dimension attribution does not change amount, debit, credit, source trace, or journal idempotency.

## Rollout and legacy limitation

Rows created before `000098` remain nullable. Existing posted journals and finalized Payroll snapshots are not updated. The current legacy dataset contains no independent immutable Cost Center snapshot. Current Organization Unit mapping and current employee assignment are therefore forbidden evidence; legacy Payroll remains Cost Center-unattributed even where an old journal line happens to contain a value resolved under the previous posting behavior.

## Cost Center management readiness

- Payroll and employer statutory cost: `PASS` for future snapshots; `NOT_ENOUGH_EVIDENCE` for legacy attribution.
- Budget, commitment, actual, and variance: schema/runtime supports Cost Center, but comparable coverage depends on source transactions consistently carrying that dimension; `PARTIAL`.
- OPEX and assets/depreciation: Finance source tables support Cost Center; historical completeness is `PARTIAL`.
- Revenue and COGS: no canonical Cost Center evidence; `NOT_ENOUGH_EVIDENCE`.
- Project and Maintenance expense: source context exists but consistent Cost Center evidence is not proven; `PARTIAL`.

The evidence supports a future **Cost Center Cost Management** scope, not a full Cost Center P&L. Revenue must not be assigned from Branch, employee, or proportional guesses.
