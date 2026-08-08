# Phase 2.5C implementation status

Updated: 2026-07-24.

## Completed quality and data foundation

- Safe forward migration batch 10 completed without deleting existing records.
- Added normalized interview, interviewer, evaluation, offer, application-stage
  history, onboarding checklist and onboarding provisioning tables.
- Added company ownership to candidates and onboarding cases after conflict audit
  and deterministic backfill.
- Added workflow/version/audit metadata needed by requisitions, applications and
  onboarding cases.
- Added typed Eloquent models and workflow enums for the new tables.
- Added offer state-machine and headcount capacity validator.
- Fixed accepted-offer capacity accounting.
- Candidate creation now uses server-authorized company context.
- Application creation now rejects candidate IDs belonging to another company.
- Onboarding dashboard reads normalized checklist and provisioning tables.
- Recruitment PHPStan level 10: 0 errors.
- Onboarding PHPStan level 10: 0 errors.
- Organization and Employee PHPStan level 10 remain at 0 errors.
- Pint, PHPUnit, Vite build, migration status and Docker Compose config passed.

## Still required before Phase 2.5C can be called complete

- Requisition edit/detail/archive/restore and approval decision UI/routes.
- Private resume upload/download, archive/restore and candidate merge.
- Interview scheduling, interviewer authorization and evaluation commands/UI.
- Offer commands, private document workflow, approval and expiry scheduler.
- Idempotent/concurrency-safe hire transaction.
- Onboarding checklist, buddy, provisioning and probation command-side UI.
- Domain events, queued notifications, activity log and company-private realtime.
- Export endpoints and content/security tests.
- Complete permissions/policies, feature/unit tests, sidebar links and final docs.

No item in this section is represented as complete merely because its database
table or model exists.

## Phase 2.5C-2 implementation increment

- Requisition state transitions now distinguish approval, approved and open.
- Create, approve and open paths recheck headcount under database locking.
- Headcount no longer double-counts accepted offers already covered by an active
  requisition reservation.
- Requisition transitions increment version and record actor/reason in Activity Log.
- Application creation writes the initial stage history.
- Application stage changes lock the row, enforce optional optimistic version,
  increment version and write stage history/activity.
- Generic stage movement cannot set `hired`; that remains reserved for Phase 2.5C-3.
- Candidate archive and conflict-checked restore are connected through Command Bus.
- Candidate merge locks records deterministically, blocks duplicate-requisition
  conflicts, moves applications and soft-deletes the secondary profile.
- Candidate resume upload/replace/remove is connected through Command Bus.
- Resume download is connected through Query Bus and uses authorized private storage.
- Resume MIME, size, checksum and malware-scan gates are active.
- Interview and Candidate Offer permissions were seeded idempotently.

Still missing from Phase 2.5C-2: full requisition/candidate detail and edit UI,
dedicated policies, interview workflow, offer workflow, recruitment realtime,
notifications, CSV exports and the complete requested test matrix.

## Phase 2.5C-2B increment

Interview scheduling and its core status lifecycle are now functional through
CQRS, including tenant checks, interviewer assignment, concurrency control,
activity logging and real UI. Candidate Offer creation and its approval/send/
decision lifecycle are functional through CQRS. Accepted offers use explicit
reservation markers under reservation model A and never trigger Hire Candidate.

Advanced evaluation, private Offer documents, notification/realtime integration,
CSV exports and the exhaustive test matrix remain outstanding and are documented
in `docs/interview-offer-workflow-after.md`.

Evaluation draft/submit with a typed scorecard, private Offer PDF upload/download,
and the queued five-minute Offer expiry scheduler were completed in the subsequent
batch-12 increment. Evaluation reopen UI/panel summary, notifications, realtime,
CSV exports and the exhaustive security test matrix remain outstanding.
## 2026-07-26

Recruitment internal Interview/Offer workflow: `COMPLETED`. External Calendar and Candidate delivery: `NOT_CONNECTED`. Hire Candidate and Onboarding command-side remain outside Phase 2.5C-2D.
# Phase 2.5C-3

Recruitment + Onboarding atomic Hire integration: **COMPLETED**. Accepted Offer now creates Employee, primary Assignment, draft Contract and preboarding Case with template items, consumes the reservation once, and links every aggregate with tenant-safe foreign keys.
