# Phase 3E-5D — Career & Succession Planning

Career Management owns future-development truth. Employee Lifecycle remains employment truth, Performance remains performance/competency truth, and Payroll data is neither queried nor exposed.

## Domain

- Career path and ordered position steps with competency level/duration expectations.
- Employee career plan lifecycle: `draft → active → completed|cancelled`; terminal states cannot return active.
- Succession lifecycle: `draft → nominated → assessed → approved → active → archived`; archived cannot reactivate.
- Talent pool/readiness evidence is company scoped. Creator/owner cannot approve their own succession plan.
- `career_history` is append-only; `career_outbox` is written in the same transaction. SHA-256 and idempotency keys protect evidence and retries.

## Schema

Additive migration batch 43 creates `career_paths`, `career_path_steps`, `employee_career_plans`, `succession_plans`, `succession_candidates`, `readiness_assessments`, `career_history`, and `career_outbox`. No previous migration was edited. MySQL initially stopped before recording the batch on unsupported `unsignedDecimal` and then on an overlong generated index name. All partially created tables had zero rows; the migration was made idempotent, used `decimal()->unsigned()` and the explicit `career_history_aggregate_timeline_index`, then completed without dropping data.

## CQRS and security

Reads: `SuccessionDashboard`, `EmployeeCareer360`, `CareerTimeline`, `TalentPoolView` → Query Bus → typed read repository/ViewModel. Writes: dedicated seven Commands/Handlers → CareerPath, SuccessionPlanning or ReadinessAssessment service → locks/state validation/history/outbox. Controller contains no database query.

Permissions: `career.view`, `career.manage`, `career.plan.create`, `succession.view`, `succession.manage`, `succession.approve`. Missing permission is 403; foreign Employee/Position/Plan is 404.

## Evidence

- Feature/security/lifecycle: 2 tests, 21 assertions.
- MySQL process concurrency: 1 test, 10 assertions covering career path create, career plan assign, succession create, nomination and approval races.
- PHPStan Performance level 10: 0 errors.
- Browser dashboard and Employee Career 360: verified 1280×800 and 390×844, no overflow, zero console errors.
- Production Career tables: 0 business rows after verification; no seed was run.

Phase 3E-5E is not implemented.
