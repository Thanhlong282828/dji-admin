# Recruitment realtime, notification and panel summary — after

Updated: 2026-07-26

## Status matrix

| Capability | Status | Implementation |
|---|---|---|
| Panel Summary Query/DTO/UI | COMPLETED | `GetInterviewPanelSummaryQuery`, typed summary/criterion/recommendation DTOs, authorized Blade page |
| Criterion aggregation | COMPLETED | Server-side normalized `score / maximum_score`, raw average, max, weight and response count |
| Recommendation distribution | COMPLETED | `InterviewRecommendation` enum, count and deterministic percentage |
| Private-note protection | COMPLETED | Notes are never selected into the summary DTO, HTML or broadcast payload |
| Internal recipient resolver | COMPLETED | Tenant-scoped relation-first resolution, verified users, actor removal and deduplication |
| Internal channel matrix | COMPLETED | Queued database + private user broadcast notifications |
| Notification idempotency | COMPLETED | Durable unique delivery marker by company/entity/action/version/recipient/channel |
| Workflow listener | COMPLETED | One explicit after-commit event flow to one queued listener; discovery disabled to avoid duplicates |
| Interview reminders | COMPLETED | 24-hour and 1-hour windows every five minutes; terminal interviews excluded |
| Echo dependencies/bootstrap | COMPLETED | `laravel-echo@2.4.0`, `pusher-js@8.6.0`, one nullable Reverb singleton |
| Private company subscription | COMPLETED | `company.{companyId}` with tenant channel authorization |
| Private user subscription | COMPLETED | `App.Models.User.{id}` with exact user-ID authorization |
| Interview/evaluation/Panel UI refresh | COMPLETED | Authorized HTML re-fetch and scoped DOM replacement; no business payload rendering |
| Offer UI refresh | COMPLETED | Same safe refresh path; salary stays in policy-protected HTTP HTML |
| Pipeline/dashboard refresh | COMPLETED | Stable `data-recruitment-page` roots and company-scoped subscription |
| HTTP fallback | COMPLETED | Forms/navigation remain normal; explicit refresh button and safe error state |
| Live Reverb browser-to-browser runtime | NOT_VERIFIED | Bundle, auth endpoint and contracts verified; two simultaneous browsers were not available in this run |
| Internal Interview calendar | COMPLETED | Internal scheduling, transition and reminder workflow |
| Google Calendar sync | NOT_CONNECTED | No OAuth credentials/provider configured |
| Microsoft Calendar sync | NOT_CONNECTED | No OAuth credentials/provider configured |
| External Candidate delivery | NOT_CONNECTED | No candidate-safe delivery channel was enabled |
| Candidate acceptance portal | NOT_STARTED | Deliberately outside Phase 2.5C-2D |

## Recipient and channel matrix

- Interview scheduled/rescheduled/cancelled: assigned interviewers plus scheduler; actor is excluded.
- Interview reminder 24h/1h: currently assigned interviewers only.
- Evaluation submitted: scheduler/recruiter context plus assigned internal panel, tenant filtered; actor excluded.
- Evaluation reopened: evaluation owner, with actor exclusion.
- Offer submitted: company-scoped users holding `candidate-offer.approve`.
- Other Offer transitions: submitter/creator relations when present; actor excluded.
- Internal channels: queued `database` and private-user `broadcast`.
- Mail and external Candidate delivery: `NOT_CONNECTED`; no accidental candidate email is sent.

All generic notification/broadcast payloads exclude salary, candidate email/phone, meeting URL, scorecard, private notes, document URL/storage key, signed URL and tokens.

## Quality gates

- Pint: passed.
- PHPStan level 10: Organization 0, Employee 0, Recruitment 0, Onboarding 0.
- Focused Panel/notification/channel/Echo/Offer-recipient tests: passed (6 tests).
- Full PHPUnit: passed (71 tests, 292 assertions).
- Vite production build: passed.
- Migration status: passed; notifications are batch 13 and durable Recruitment delivery markers are batch 14.
- Scheduler/event inventories: valid, no duplicate Recruitment listener.
- Docker Compose configuration: valid.

## Remaining gaps

Live two-browser WebSocket behavior still needs environment-level verification with a running Reverb daemon. Hire Candidate, Employee creation from Candidate, Onboarding command-side integration and external calendars/portal remain outside this phase.
