# Enterprise Notification Audit — Phase 3F-2A

## Existing implementations

- Attendance, Leave, Overtime and Payroll call `WorkforceNotificationDeliveryService`. It writes one `notification_deliveries` marker per company/entity/version/recipient/channel/action and uses Laravel database+broadcast channels. Channel retry is already independent, but attempts have no append-only child history and there is no tenant inbox.
- Recruitment uses `RecruitmentWorkflowNotification` plus its own `recruitment_notification_deliveries`, resolver and queued listener. It is database+broadcast capable but duplicates recipient and idempotency logic.
- Employee invitations and Laravel authentication notifications call Laravel Notification directly for their narrow security lifecycle.
- Payroll payslip, payment, accounting, adjustment, Annual Tax, ERP and Government Filing own document/access/delivery records. Those are authoritative business artifacts, not user Notification Center messages.
- Workflow batch 49 has a safe transactional `workflow_outbox`; before Phase 3F-2 it emitted completion only and had no read/unread center.
- Laravel `notifications` provides a user read marker, but it does not carry company scope, template version, channel attempt history or preferences.

## Gaps and migration decision

The previous mechanisms had no shared template pinning, message/recipient aggregate, company-scoped inbox, preferences, append-only channel attempts or unified retry UI. Phase 3F-2 adds a compatibility layer instead of rewriting mature listeners. Workflow is the first native producer. Attendance/Leave/Overtime/Payroll keep their durable adapter and Recruitment keeps its dedicated adapter; both remain regression-compatible and are marked `LEGACY_ADAPTER_ACTIVE` for a later controlled migration.

Production baseline before batch 50: `notifications=0`, `notification_deliveries=0`, `recruitment_notification_deliveries=0`, `workflow_instances=0`, `workflow_outbox=0`, `payroll_runs=0`, `employees=9`.
