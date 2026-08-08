# Phase 3F-3 — Identity & Access Governance

Additive migration batch 51 creates eleven IAM governance tables without replacing Auth, Sanctum or Spatie. No password, token, salary, tax, bank value or document path is stored.

Implemented CQRS commands: Create, Submit, Review, Approve, Reject, Provision access request; Revoke and Expire assignment. Queries: request list/detail, User Access 360, role entitlement, privileged access, history, dashboard, SoD and review views.

Security uses authentication, verified email, route permission, company membership and foreign-company 404. Maker-checker and SoD are revalidated in the domain service. Spatie mutations exist only in `SpatieAccessProvisioningAdapter`.

UI routes: `/admin/iam`, `/admin/iam/requests`, `/admin/iam/users/{user}/access-360`, `/admin/iam/roles`, `/admin/iam/privileged`, `/admin/iam/sod`, `/admin/iam/reviews`.

Completion evidence and remaining gates are recorded in the final Phase report; no production role, permission or company access was mutated.

## Verification evidence

- IAM app PHPStan level 10: 0 errors; IAM test PHPStan level 10: 0 errors.
- Focused IAM: 5 tests / 22 assertions passed. Pint, Blade compile/cache/clear, Vite production build and Docker Compose validation passed.
- Browser desktop 1280×800: dashboard, requests, Access 360, privileged, SoD and reviews all have `clientWidth=scrollWidth=1280`; console errors/warnings 0.
- Browser mobile 390×844: all pages have `clientWidth=scrollWidth=390`; tables scroll internally (356px container/752px content); console errors/warnings 0.
- Guarded testing fixture cleaned successfully.
- Production after migration: all twelve IAM tables contain 0 business rows. Existing users/roles/permissions/pivots/company access/Employee/Payroll counts exactly match the pre-change baseline.

## Phase 3F-3 completion hardening

Batch 52 is additive: workflow correlation/version, three-actor approval evidence, immutable review snapshots, SoD evidence metadata and dedicated consumer checkpoints. Batches 1–51 were not edited.

- Privileged flow is Requester → Reviewer → Approver → Provision; all three actors differ and expiry is mandatory.
- Campaigns follow `draft → active → under_review → completed`; decisions follow `pending → keep|revoke|change`. Completion uses the captured snapshot, creates one immutable certification and revokes only through the provisioning adapter.
- SoD evaluates current runtime entitlements plus the requested entitlement for role-role, permission-permission and role-permission rules. Blocking prevents approval/provision; warning violations are persisted and deduplicated.
- Workflow, Employee termination/transfer and Notification consumers are company-scoped and idempotent; they dispatch Commands rather than mutating owner aggregates directly.
- Lock order: company/user → request or review → assignment → Spatie pivot → history → outbox, with bounded deadlock retry.

Evidence after consumer hardening: IAM PHPStan level 10 has 0 errors; focused IAM 9 tests/42 assertions and MySQL process concurrency 9 tests/42 assertions pass. Dedicated races cover Workflow completion consumer, Employee termination consumer, Employee transfer consumer, IAM Notification consumer and privileged approval/provision, in addition to request/review/provision/revoke races. Workflow/Notification/Employee regression passes 22 tests/128 assertions. Pint, Blade, Vite and Docker Compose validation pass.

Consumer checkpoint invariant: the source outbox primary key/event UUID is the deterministic external identity; `iam_consumed_at` or `published_at` is updated under `lockForUpdate` on that same row. Competing processes re-check the null checkpoint inside the lock, so only one dispatches. Owner-domain idempotency and unique active-assignment/review/message keys provide the second guard. No batch 53 is required.

Final automated gate: full PHPUnit 340 tests/2,654 assertions passed; focused IAM 9/42, IAM process concurrency 9/42, and shared Workflow/Notification/Employee 22/128 passed. Production counts match baseline and all 12 IAM tables remain empty.

Historical browser note: the in-app runtime exposed only 1280×720. This blocker was later resolved with dev-only `playwright-core` driving the existing local Chrome binary; the exact evidence below supersedes the earlier runtime limitation.

## Exact viewport tooling audit

Local audit on 2026-08-08 found Google Chrome `151.0.7922.77`, but no Playwright, Puppeteer, Playwright browser cache, Chromium, Chrome for Testing, ChromeDriver, Selenium or Chrome DevTools automation package. `npx --no-install playwright --version` and `npx --no-install puppeteer --version` both confirmed the packages are absent. Installing or downloading a new browser/runtime was intentionally not performed. Therefore no test-only script was created and no 1280×720 result was substituted for the required 1280×800 or 390×844 evidence.

## Exact viewport completion evidence

The test-only `tests/Browser/iam-responsive-check.mjs` uses `playwright-core` with the existing Google Chrome `151.0.7922.77`; no browser binary was downloaded. It is guarded by `APP_ENV=testing`, MySQL and `schema_admin_testing`.

- Desktop 1280×800: eight IAM pages verified with exact `innerWidth=1280`, `innerHeight=800`, `clientWidth=scrollWidth=1280`.
- Mobile 390×844: eight IAM pages verified with exact `innerWidth=390`, `innerHeight=844`, `clientWidth=scrollWidth=390`.
- Dashboard, request list/detail, User Access 360, privileged access, SoD, review list/detail all returned successful responses.
- Console errors, console warnings and page errors: 0.
- Table inner scrolling, action wrapping, responsive cards and sidebar bounds: passed on every page.
- Sixteen screenshots are retained under `storage/logs/iam-browser-evidence`.
- Testing fixture cleanup: company 0, users 0, IAM requests 0, IAM reviews 0. Testing server stopped.

Phase 3F-3H UI/Browser is `COMPLETED`; Phase 3F-3 Overall is `COMPLETED`.
