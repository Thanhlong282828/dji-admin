# Interview / Offer final gap audit

Updated: 2026-07-24.

| Feature | Backend/UI/Test status | Result |
|---|---|---|
| Evaluation draft | Dedicated Command/Handler, typed DTO, UI, version test path | COMPLETED |
| Evaluation submit | Dedicated Command/Handler, assigned-user and state checks | COMPLETED |
| Evaluation reopen | Dedicated Command/Handler, reason, actor and audit metadata | FUNCTIONAL |
| Scorecard | Typed items, duplicate/range checks, server weighted score | COMPLETED |
| Reschedule | Command/Handler, locking, expected version, before/after history | COMPLETED |
| Interviewer add/remove | Command/Handler, tenant checks, evaluation protection, UI | COMPLETED |
| Offer PDF | Private upload/replace/download/remove, checksum and scan gate | COMPLETED |
| Offer expiry | Idempotent queued job registered every five minutes | COMPLETED |
| Interview CSV | CQRS generator/stream, tenant authorization, UTF-8, sanitization | COMPLETED |
| Offer CSV | CQRS generator/stream, tenant authorization and salary suppression | COMPLETED |
| Backend realtime | Private company broadcast with non-sensitive payload | FUNCTIONAL |
| Frontend realtime | Echo package/bootstrap is not present in current frontend dependencies | BLOCKED |
| Notifications | No complete recipient/channel matrix implemented | MISSING |
| Panel summary | Existing detail loads evaluations; dedicated aggregate ViewModel/UI missing | PARTIAL |
| External calendar | No production Google/Microsoft credentials/provider | BLOCKED |

The phase must not be labelled fully complete while frontend Echo and the
notification recipient/channel matrix remain absent. No Hire Candidate or
Onboarding command route was introduced.
## Closure update (2026-07-26)

Panel Summary, internal notification routing/idempotency and Echo/Reverb frontend integration are implemented and covered by focused tests. The remaining gaps are external integrations (calendar/candidate delivery), live two-browser runtime verification, Hire Candidate and Onboarding command-side.
