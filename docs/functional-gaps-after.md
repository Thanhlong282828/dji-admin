# Phase 2.5A — Functional gaps after

## Organization: COMPLETED

- PHPStan level 10: 0 errors.
- Five real, tenant-scoped CSV exports.
- Organization tree export contains depth and hierarchy path.
- Job position and headcount exports use aggregate queries.
- Permission-aware export buttons lead to real routes.
- UTF-8, decimal precision, CSV formula protection and activity logging are active.
- Export feature tests and existing Organization regression tests pass.

No other HRM module was changed during Phase 2.5A.
# Phase 2.5B Employee

Employee static quality gate, Assignment, export, document pipeline, contract state machine, consent decision, avatar, User link và invitation đã functional. Employee vẫn chưa được đánh dấu `COMPLETED` toàn phần: import preview pipeline, dedicated credential/consent/signed-contract evidence actions, contract renewal, Create User/disable access và avatar thumbnail còn thiếu.
## Phase 2.5C-2D update (2026-07-26)

- Internal Interview/Offer workflow: **COMPLETED** (Panel Summary, internal queued notifications, persistent idempotency, safe backend broadcast, Echo bundle and scoped UI refresh).
- Live two-browser Reverb runtime: **NOT_VERIFIED**.
- External Google/Microsoft calendar: **NOT_CONNECTED**.
- External Candidate delivery: **NOT_CONNECTED**.
- Candidate acceptance portal: **NOT_STARTED**.
- Hire Candidate and Onboarding command-side: **NOT_STARTED / out of scope**.
# Phase 2.5C-3 status

- Atomic Hire Candidate transaction: **COMPLETED**
- Recruitment → Employee → Onboarding source links: **COMPLETED**
- Checklist/provisioning command-side initialization: **COMPLETED**
- External identity provisioning: **NOT_CONNECTED**
- E-signature: **NOT_CONNECTED**
- Candidate portal/delivery: **NOT_CONNECTED**
# Phase 3

Workforce Operations Foundation: **PARTIAL**. Attendance capture/calculation and Payroll Input snapshot vertical slices are functional. See `docs/workforce-operations-after.md` for the exact closure gaps. Full payroll remains NOT_STARTED.
