# Enterprise Data Quality Design

`DataQualityAuditService` is a bounded, read-only cross-cutting query service. It evaluates aggregate rules and returns only counts, statuses and a deterministic SHA-256 checksum. It never returns employee identifiers, emails, payroll values or SQL bindings.

Business modules retain ownership. Data Quality may detect an inconsistency but cannot mutate Employee, Organization, Attendance, Payroll or IAM tables. Correction remains an explicitly authorized module-owned workflow.

The Employee list uses the existing optimized read repository with eager-loaded manager, account and current assignment relations. Age is derived from DOB at render time. Manager choices contain name, primary position and organization unit, exclude self and are still validated server-side for tenant scope and cycles.

Employee account access remains a separate lifecycle from the retained Employee aggregate. `users.access_enabled` is enforced inside the credential attempt, while account provisioning uses Laravel hashing, Spatie roles and `company_user_access` in one transaction. Blank edit passwords preserve the current hash. Self-disable and disabling the last active Super Admin are rejected.

Job positions own `career_level`; promotion requires a strictly higher target and demotion a strictly lower target. Both use the existing assignment transaction/overlap controls and write distinct immutable history events. Payroll local fixtures call the real Period/Input/Run services with synthetic VND values and no external adapter.
