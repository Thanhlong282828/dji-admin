# Phase 3F-2 — Enterprise Notification Center

## Status

Phase 3F-2 is **COMPLETED**. Additive migration batch 50 creates the Notification Center aggregate and extends the existing durable delivery marker without changing batches 1–49.

## UI and security

Routes under `/admin/notifications` require auth, verified email, route permission and company policy. Users see only their own recipient rows; delivery audit is company scoped. Permissions: `notification.view`, `notification.template.manage`, `notification.delivery.view`, `notification.retry`, `notification.preference.manage`.

Pages: Inbox, detail, template management and delivery audit. Header/sidebar unread badge refreshes through an authorized JSON query and the existing realtime notification event. Exact browser evidence:

- Desktop 1280×800: all four pages have `clientWidth=scrollWidth=1280`, sidebar/actions/tables visible, console errors/warnings 0.
- Mobile 390×844: all four pages have `clientWidth=scrollWidth=390`; cards stack at 312px, actions wrap, sidebar opens/closes, table containers are 356px with internal 724px scroll, console errors/warnings 0.
- Real mark-read action returned a success message, removed the unread card and refreshed the built header badge to 0.
- Guarded `notification:browser-fixture` ran only on `schema_admin_testing` and cleanup returned fixture rows to zero.

## Verification

- Notification module PHPStan level 10: 0 errors.
- Notification tests PHPStan level 10: 0 errors.
- Focused Feature foundation includes template pinning, delivery idempotency, partial retry, inbox ownership, security, payload rejection, Workflow projection and fixture cleanup.
- MySQL process concurrency: 5 tests / 14 assertions passed.
- Notification Feature: 8 tests / 34 assertions passed.
- Workflow Feature+Unit regression: 15 tests / 71 assertions passed.
- Full PHPUnit: 322 tests / 2,570 assertions passed (exit code 0).
- Pint, Notification and Workflow PHPStan level 10, Blade compile/cache/clear, Vite build and Docker Compose validation passed.
- Production read-only audit after verification: all eight Notification Center tables, legacy notification delivery tables, Workflow rows/outbox and Payroll runs remain at 0; Employee rows remain unchanged at 9. No fixture, email, external request or notification artifact was created in production.

## Compatibility

Workflow task assigned/escalated/rejected/completed events pass through `workflow_outbox` and the scheduled Notification consumer. Recruitment and Workforce/Payroll delivery services remain active compatibility adapters; no broad rewrite was performed in this phase.

IAM uses `iam_outbox → iam:consume-notifications → QueueNotificationCommand → NotificationDeliveryService`. Payloads contain safe event/aggregate references only and Notification Center idempotency prevents duplicates on retry.

Process evidence: two PHP consumers raced the same IAM notification outbox row and produced one Notification message, one recipient/channel intent and one published checkpoint. Re-consuming the event left counts unchanged; Notification Center partial-channel semantics remain owned by its existing delivery service.
