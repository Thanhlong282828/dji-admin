# Payroll security matrix

Payroll Run Policy foundation maps view/create/calculate/recalculate/submit/approve/reject/finalize/export to specific permissions plus company-scoped records and lifecycle state. Self-approval is denied. Finalized runs cannot transition or recalculate. Events broadcast identifiers/state/version only—never totals, salary, tax, bank or component amounts.

New permissions are created idempotently. Dedicated Salary Component and Assignment Policies now enforce tenant/state/action scope. Tax, insurance identifiers and bank data are absent from Payroll Item snapshots and event payloads. Item/Validation Issue Policies and HTTP IDOR tests remain incomplete blockers.
