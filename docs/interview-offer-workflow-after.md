# Interview and Offer workflow — implementation report

Updated: 2026-07-24.

## Functional in this increment

- Interview scheduling through Command Bus with atomic interviewer assignment.
- Tenant-scoped application and interviewer validation.
- Online/offline scheduling rules, timezone/date validation and duplicate-round guard.
- Interview state machine for confirm, complete, cancel and no-show.
- Interview optimistic version checking and activity logging.
- Interview CQRS list/detail queries and working Blade pages.
- Candidate Offer draft creation through Command Bus.
- Candidate Offer state machine for submit, approve, return, send, accept,
  decline, withdraw and expire.
- Self-approval protection, expiry checks and optimistic version checking.
- Model A vacancy accounting: requisition owns the capacity reservation; accepted
  offer uses an idempotent marker inside that reservation and is not subtracted a
  second time from headcount capacity.
- Accept locks offer, application and requisition, checks vacancy, records the
  reservation and moves Application to `offer`, never `hired`.
- Salary is removed by the query handler for users without salary permission.
- Working Offer list/create/detail pages and protected routes.
- Dedicated Interview and Offer policies exist; route services also enforce
  permission and tenant context.
- Sidebar contains real Interview and Offer links.
- Migration batch 11 safely adds reservation markers.
- New state-machine and end-to-end workflow tests pass.
- Typed Interview scorecard uses integer score/weight inputs and calculates the
  weighted overall score server-side.
- Assigned interviewers can save draft or submit evaluations; submitted records
  are locked and protected by optimistic versioning.
- Evaluation concurrency/reopen metadata and interview schedule history storage
  were added safely in migration batch 12.
- Private Offer PDF upload/download now uses Command/Query Bus, private storage,
  MIME/size validation, SHA-256 checksum and the shared malware scan gate.
- Idempotent queued Offer expiry is registered in the scheduler every five minutes.
- Evaluation draft, submit and reopen are now separate CQRS use cases.
- Interview reschedule persists typed before/after history and rejects stale versions.
- Interviewer add/remove is functional and removal is blocked after an evaluation exists.
- Offer documents can be removed only while business state permits it.
- Interview and Offer CSV exports stream from CQRS generators, sanitize spreadsheet
  formulas and suppress salary unless the exporter has salary permission.
- A private-company broadcast event is dispatched after commit without PII or salary.

## Explicitly not completed

- Dedicated panel-summary ViewModel/UI.
- Generated PDF offer documents (authorized uploaded PDFs are functional).
- Queued notifications and external calendar synchronization.
- Reverb broadcast events and frontend Echo refresh.
- Frontend Echo initialization/listener (the dependency is absent from this project).
- Complete queued notification recipient/channel matrix.
- Full test matrix requested for every IDOR, policy, export and document edge case.

These missing items remain `PARTIAL`/`MISSING`; they are not represented as
complete by the presence of routes, models or schema.
## Phase 2.5C-2D finalization

Internal Interview/Offer is now closed as `COMPLETED`: evaluation aggregation has its own typed Panel Summary read flow; after-commit workflow events drive one queued notification listener; Echo refreshes authorized page regions without a full reload. External candidate/calendar delivery is intentionally not claimed.
