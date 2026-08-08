# Organization realtime

Domain events broadcast trên private channel `company.{companyId}`. Payload chỉ chứa id, company và tên
event. `routes/channels.php` authorize channel bằng tenant access.

Tree frontend trong `resources/ts/schema.ts` lắng nghe create/update/move/delete/headcount và gọi lại
endpoint `admin.organization-units.tree-data`; DOM được cập nhật tại chỗ, không reload toàn trang.
Backend sử dụng Reverb/Echo theo cấu hình chung của project.
# Employee realtime

`EmployeeAssignmentChanged` broadcast trên private company channel với company, employee, entity type/id và action; không phát identity, salary, storage key, token hoặc URL.
# Recruitment realtime (Phase 2.5C-2D)

The browser creates a single nullable Echo/Reverb instance from `VITE_REVERB_*`. When disabled or incomplete, it returns `null` and the HTTP application remains fully usable. Recruitment pages subscribe to `private-company.{companyId}` and listen for `.recruitment.workflow.changed`; notifications subscribe to `private-App.Models.User.{id}`.

The workflow event contains only company ID, entity, entity ID, action, version and update timestamp. The browser never renders business data from this payload. It fetches the current authorized HTML and replaces only the stable `[data-recruitment-page]` region. Interview evaluation changes also refresh Interview/Panel Summary roots. Offer changes refresh Offer roots; Pipeline and Dashboard roots accept all Recruitment entity changes in their company.

Channel authorization is in `routes/channels.php` through `TenantAccessService`; the model user channel requires an exact authenticated user ID. Tests cover same-company allow, cross-company deny, anonymous deny and wrong-user-channel deny.

Local runtime:

```bash
php artisan reverb:start --debug
npm run dev
```

Live two-browser runtime remains `NOT_VERIFIED`; backend contracts, channel auth and frontend bundle are verified.
# Candidate hired

`CandidateHireCompleted` is dispatched after the database commit. Its listener emits `recruitment.workflow.changed` on `private-company.{companyId}` through the existing event abstraction with action `hired`. The payload is limited to company/entity/id/action/version/timestamp. Employee assignment broadcasting was also changed to after-commit so rolled-back assignments cannot appear in another browser.
# Workforce realtime

After-commit events use `workforce.attendance.changed`, `workforce.leave.changed` and `workforce.payroll-input.changed` with IDs/action/status/version only. Backend contracts are VERIFIED; live two-browser DOM refresh remains NOT_VERIFIED.
# Phase 3A-7 realtime status (2026-07-26)

Status: **Phase 3A — COMPLETED** for internal backend/frontend contracts. Cache invalidation precedes workflow notification delivery; durable per-channel markers make delivery retry-safe. Echo shared instance, private company channels, 250 ms debounce, partial DOM refresh and HTTP/manual fallback remain active. Live two-browser WebSocket verification is truthfully `NOT_VERIFIED`.
# Phase 3B-1 Payroll realtime

`PayrollRunChanged` broadcasts a safe company-private refresh contract without salary amounts and invalidates the company payroll-run cache scope. Authorized DOM refresh scopes and HTTP fragments remain pending, so Phase 3B-1 realtime is PARTIAL.
