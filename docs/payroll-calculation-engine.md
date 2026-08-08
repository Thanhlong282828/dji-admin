# Payroll calculation engine foundation

Money source of truth is signed integer minor units plus ISO 4217 uppercase currency. Currency mismatch throws; arithmetic never uses float. Percentage and quantity use basis points and deterministic half-up integer division per component. Item/run totals are sums of already-rounded component amounts.

Calculators are allowlisted: fixed, percentage and quantity-rate. `formula_placeholder` deliberately fails because no safe expression engine exists. Categories are earning, deduction, employer contribution and informational. Employer contribution never reduces net. Net is gross minus employee deductions; negative net is retained for diagnosis and marks validation failure.

Calculation locks the Run, validates version/state, reads only the linked immutable Payroll Input Snapshot, bulk-loads effective assignments, replaces pre-submit calculation rows transactionally, writes employee identity/input snapshots and component snapshots, aggregates totals, increments calculation version, writes relational history/activity and dispatches a safe after-commit event.
