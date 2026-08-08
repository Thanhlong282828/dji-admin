# Phase 2.5 — Functional gaps before implementation

Audit date: 2026-07-24  
Runtime: Laravel 13.21.1, PHP 8.4.23  
Database: MySQL `schema_admin`

## Quality baseline

| Module | PHPStan level 10 | Classification |
|---|---:|---|
| Organization | 142 errors | PARTIAL |
| Employee | 83 errors | PARTIAL |
| Recruitment | 95 errors | PARTIAL |
| Onboarding | 37 errors | SKELETON/PARTIAL |

No PHPStan baseline, broad ignore rule, or reduced analysis level is present as part of this audit.

## Organization

### Functional

- Cost center, organization unit and job position CRUD routes and CQRS handlers exist.
- Organization tree, move-node validation and headcount read model exist.
- Company-scoped policies and permissions exist.
- Redis cache versioning, activity listener and private company broadcast event exist.

### Gaps

- Export endpoints are placeholders: cost-center and job-position export return the HTML index; organization-unit export returns tree JSON. They do not produce CSV/XLSX files.
- Routes `organization-units.export-tree` and `organization.export-headcount` do not exist.
- PHPStan errors are concentrated in untyped Eloquent arrays, paginator generics, filter array shapes, query/command results, DB query result objects, event payloads and dynamic policy dispatch.
- Repository results cross application boundaries as `array<string, mixed>` rather than typed DTO/ViewModel objects.
- `Modules/Organization/routes/web.php` contains a large obsolete prompt copied as PHP comments after the real routes.

## Employee

### Functional

- Employee list/create/show/edit/update/delete/restore routes exist.
- Employee CRUD uses Core CommandBus/QueryBus and a tenant-scoped repository.
- Employee model includes relations for assignments, history, credentials, contracts, consents and documents.

### Missing routes or command flows

- Assignment list/create/update/end/transfer/promotion/allocation/history.
- Credential CRUD, expiry reports and private document download.
- Contract CRUD/state transitions/approval/private signed document.
- Consent grant/withdraw/renew/evidence.
- Employee document upload/version/download/sign.
- Avatar upload/processing/R2 adapter.
- User link/unlink/create/invitation.
- Import/export.

The related models existing in the source tree does not make these functions complete. No corresponding web routes were found.

## Recruitment

### Functional

- Recruitment dashboard and requisition list/create/transition exist.
- Candidate list/create, application create, stage transition and pipeline exist.

### Missing

- Requisition show/edit/update and the complete approval lifecycle.
- Workflow adapter and headcount override enforcement.
- Candidate private resume, archive/restore/merge.
- Interview scheduling.
- Candidate offer.
- Atomic/idempotent candidate hiring.
- Complete frontend realtime listeners and specialist test coverage.

## Onboarding

### Functional

- Read-only onboarding dashboard and case list exist.

### Skeleton or missing

- A generated `Modules\Onboarding\Http\Controllers\OnboardingController` remains with untyped empty CRUD methods.
- No real command side for case creation, buddy assignment, checklist, provisioning, probation review, completion or cancellation.
- No command-side routes, Form Requests, state policy or locking tests.

## Menu and UI

The existing employee, recruitment and onboarding menus lead only to the limited routes listed above. Tabs or actions for assignments, credentials, contracts, documents, consent, interviews, offers and onboarding command workflows have no completed routes and must remain classified as under development.

## Database note

No destructive migration command was run. Batch 7 from the previous Phase 4 turn remains applied; this Phase 2.5 audit does not modify or extend Attendance, Leave, Overtime or Payroll.
