# Attendance, Branch and Payroll integration

The realistic local dataset now exercises this chain across multiple months. Approved overtime, late minutes, and unpaid-leave minutes enter the immutable Payroll Input snapshot; effective quantity-rate Salary Assignments convert those quantities during canonical Payroll calculation. The bootstrap never writes final overtime pay or deductions directly.

## Canonical chain

User → linked Employee → effective primary Branch assignment → Attendance event/record → approved Attendance, Leave and Overtime → immutable Payroll Input snapshot/version → Payroll Run → Payroll Item/Payslip.

`branch_user_access` is authorization membership. `employee_branch_assignments` is an effective-dated operational allocation and is therefore not a duplicate access system. Attendance events and daily records preserve `branch_id`; Payroll Input preserves both `branch_id` and `branch_snapshot`.

If no operational assignment exists, exactly one valid Branch Access may be used as a safe fallback. Multiple accesses are ambiguous and check-in fails with an actionable validation message until a primary operational branch is assigned.

Only approved Attendance, approved Leave and approved/reconciled Overtime are collected. Paid leave is distinct from unpaid leave. Late minutes remain quantities; they do not become a monetary deduction without an explicit company policy. A correction after snapshot does not rewrite it or a finalized Payroll Run; the operator creates the next supported snapshot/version or Payroll Adjustment.

Attendance adjustment state machine:

`draft → submitted/cancelled → approved/rejected`.

The detail UI renders only legal next actions. Raw events remain immutable, maker-checker and optimistic version checks remain enforced by the service.
