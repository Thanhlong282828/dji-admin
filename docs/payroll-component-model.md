# Salary component model

Batch 22 adds company-scoped `salary_components` and effective-dated `employee_salary_component_assignments`. Component codes are unique per company. Categories and calculation types are allowlisted; executable formulas/eval are prohibited. Values use amount minor units, percentage basis points, quantity basis points and rate minor units.

Assignments reference tenant employee/component, carry effective range, source, status, optimistic version and company-scoped UUID idempotency. The calculation engine consumes active effective assignments and snapshots component code/name/category/type into `payroll_item_components`.

Application Commands/Handlers, domain validation, finalized semantic protection, transactional overlap detection, assignment end-dating and dedicated Policies are implemented. HTTP endpoints/UI and read repositories remain blockers; direct production assignment seeding was not performed.
