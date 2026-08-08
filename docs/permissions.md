# Organization permissions

- `cost-center.view|create|update|delete|export|audit`
- `organization-unit.view|create|update|move|delete|export|audit`
- `job-position.view|create|update|delete|change-headcount|change-critical|export|audit`
- `organization.tenant-all` chỉ dành cho vai trò quản trị toàn hệ thống.

Policy kết hợp permission và membership `company_user_access`; mọi repository lookup ghi đều scope theo
`company_id`, chống IDOR/cross-company.

Export routes enforce middleware permission and tenant membership:
`cost-center.export`, `organization-unit.export`, and `job-position.export`.
Having the permission without `company_user_access` still returns HTTP 403.
# Employee permissions bổ sung

Seeder idempotent có nhóm `employee-assignment.*`, `employee-credential.*`, `employment-contract.*`, `data-consent.*`, `employee-document.*`, `employee-user.*`, `employee.manage-avatar` và `employee.archive`. Route mới có permission middleware và tenant authorization backend.
# Interview Panel Summary permissions

- `interview.view-panel-summary`: view aggregate panel results.
- `interview.view-private-notes`: reserved explicit permission; private notes are not part of the aggregate page.

The Panel Summary route has authentication, verified-user middleware, permission middleware and tenant-scoped query enforcement.
# Hire integration permissions

- `candidate-offer.hire`: the single orchestration permission enforced by both Hire routes and `CandidateOfferPolicy::hire`.
- `onboarding.create-from-hire`: seeded for explicit future delegation/audit but not stacked on the Hire route, avoiding accidental denial to an otherwise authorized hiring operator.
- `candidate-offer.view-salary`: independently controls whether salary is included in the preview HTML.

All checks also require verified authentication and tenant access. No runtime role names are used.
# Workforce permissions

Batch adds action-specific `work-calendar.*`, `shift.*`, `attendance.*`, `leave.*`, `overtime.*`, `payroll-period.*` and `payroll-input.*` permissions. Active routes enforce auth + verified + action permission + tenant access. Runtime authorization contains no hard-coded role name.

Phase 3A-4 uses `overtime.view`, `overtime.request`, `overtime.approve`, and `overtime.manage-policy` at route/controller boundaries. Every company context is checked with `TenantAccessService`; record queries also scope by `company_id` to prevent IDOR. Self approval is denied through the employee/user mapping.
# Phase 3A-7 dedicated Workforce authorization (2026-07-26)

Status: **Phase 3A — COMPLETED**. Eighteen resource Policies are registered once through `WorkforcePolicyGateRegistrar`. Controllers use exact action abilities; the shared helper only resolves company membership/permission and employee own-versus-management scope. Tenant/IDOR, own employee, approver, state and locked-period denials are covered by the Policy Matrix suite.
# Phase 3B-1 Payroll permissions

Specific component, assignment, run, item and export permissions now exist. Payroll Run Policy enforces tenant record, action state, self-approval and finalized lock. Remaining component/assignment/item Policy classes are documented blockers.
