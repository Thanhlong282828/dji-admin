# Enterprise IAM audit — Phase 3F-3A

## Runtime baseline

Laravel Auth, Sanctum and Spatie Permission remain authoritative at runtime. Production baseline before IAM schema: users 1, roles 14, permissions 725, model roles 1, direct permissions 0, role permissions 1,928, company access 1, employees 9.

Role and permission mutation existed in `RolePermissionSeeder`, browser fixtures, tests and several employee/workflow support services. Company scope is stored in `company_user_access`; Organization `TenantAccessService`, Policies and route permissions enforce most domain access. Employee user linking creates company access, while unlinking does not delete the user. There was no common maker-checker boundary for role assignment, no time-bounded privileged access, access certification or SoD engine.

Restricted capability families include Payroll calculation/salary/payment/accounting, tax, Government Filing submission, ERP execution, identity documents and IAM approval. Existing domain maker-checker rules are local to Payroll, Government Filing, ERP and Workflow and were not evaluated before generic access provisioning.

## Gaps closed by Phase 3F-3

- Central access request and immutable governance history.
- Spatie mutation isolated behind one provisioning adapter.
- Company-scoped effective assignments, risk metadata and expiry.
- Role-role, permission-permission and role-permission SoD foundation.
- Privileged role metadata and mandatory expiry at provisioning.
- Review campaign, immutable review snapshot and certification schema.
- Safe IAM outbox for Workflow, Notification and Employee Lifecycle consumers.

Existing direct mutations are compatibility paths and future migration candidates; Phase 3F-3 does not rewrite unrelated bounded contexts.

Completion audit confirms IAM runtime mutations only in `SpatieAccessProvisioningAdapter` (the guarded browser fixture is test setup). Request routes enforce distinct create/review/approve/manage permissions and Access 360 is a readonly tenant-scoped ViewModel without restricted fields.

Repeated source scan confirms `assignRole`, `givePermissionTo`, `removeRole` and `revokePermissionTo` occur only in `SpatieAccessProvisioningAdapter`; the only other call is guarded browser fixture setup on a `*_testing` database. No consumer or IAM domain service directly mutates Spatie.
