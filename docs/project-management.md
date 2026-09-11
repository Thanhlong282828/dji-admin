# Project Management runtime

## Ownership and scope

`Modules/Project` owns project master data, members, milestones, tasks, task dependencies and project timesheets. Every read and write is scoped by `company_id`; Project does not write Finance, Procurement, Asset, Employee, DMS or Workflow owner tables.

The HTTP write path is `ProjectRequest → ProjectController → explicit Command → Handler → ProjectManagementService → project tables → activity log`. Reads use `GetProjectsQuery → ProjectReadService` and grouped/bounded queries.

## Canonical calculations

- Progress = `DONE non-cancelled tasks / all non-cancelled tasks`; a project with no eligible task is 0%.
- `DELAYED` requires an unfinished project whose target end date has passed.
- `AT_RISK` requires an overdue unfinished task or milestone.
- `ON_TRACK` means no deterministic delay/risk evidence was found.
- Project timesheet is project-work evidence and is not Attendance.

## Lifecycle and controls

Projects follow explicit state transitions. Completion is blocked while any task or milestone remains open or a timesheet is submitted. Task completion is blocked until every Finish-to-Start predecessor is done. Dependency creation rejects self-links, cross-project links and cycles.

Timesheets require an active project member and may optionally reference a task in the same project. Submission is idempotent. Approval is maker-checker, transactionally locked and repeat-safe. Historical completed/cancelled project evidence is not rewritten.

## Cross-domain evidence

- DMS: `PROJECT`, `PROJECT_MILESTONE` and `PROJECT_TASK` are supported subject types. Versions and SHA-256 remain owned by Document.
- Workflow: Project timesheet currently uses its native maker-checker state and audit evidence. Generic Workflow orchestration is available as a platform capability but is not duplicated or automatically started; integration status is `PARTIAL`.
- Notifications: no dedicated Project notification event is emitted yet; status is `PARTIAL`.
- Finance: budget is the project planning amount. Current Expense, Procurement, Asset and Revenue owner tables do not provide a canonical project reference, so committed/actual/remaining are not calculated. Expense/Procurement/Asset are `PARTIAL`; Revenue is `NOT_IMPLEMENTED`.

## Navigation and permissions

The Projects sidebar group exposes exactly two primary destinations: Project Overview and Project List. Task, milestone, team and timesheet operations remain within Project Detail. Permissions include view/create/update, member/task management, timesheet create/approve and project completion. Low-privilege users never receive mutation controls merely by being a project member.
