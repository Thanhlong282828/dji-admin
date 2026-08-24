# Final project audit

Audit date: 2026-08-24. Baseline: `224fcab` on `codex/final-project-audit`. Scope: repository and the three authorized local databases only. No VPS, production endpoint, external provider mutation, commit or tag was used.

## Scorecard

| Area | Status | Evidence |
|---|---|---|
| Architecture | PASS | 39 modules, 54 declared/39 observed edges, zero cycle, undeclared edge, layer, CQRS or external-boundary violation |
| Business functionality | PASS | 16 enabled modules; 15 own runtime routes and Core is infrastructure-only; disabled routes/providers loaded: zero |
| Security | WARNING | Critical/high code finding: zero; MFA and malware-provider integration remain accepted medium gaps |
| Testing | PASS | 179 PHPUnit files, 20 browser scripts, 14/14 critical invariants covered; final clean suite: 568 tests, 567 passed, 7,508 assertions, one expected skip, exit 0 |
| Performance | PASS | Grouped/batched analytics/planning/reporting paths and existing N+1/query-growth gates remain green |
| Data Quality | PASS | 16/16 rules, score 100; duplicate/orphan/cross-tenant/manager/attendance/payroll/compensation findings: zero |
| Accessibility/navigation | PASS | 78/78 primary destinations at 1280×800 and 390×844, specific help 78/78, overflow/console/page errors zero |
| Operations | WARNING | Code/read-only readiness controls pass; target infrastructure and provider verification remain external |
| Reporting and planning | PASS | Analytics, Forecasting, Executive Planning, Planning Execution and Enterprise Reporting stay tenant-scoped/read-only |
| Production readiness | BLOCKED_EXTERNAL | Production MySQL/TLS/mail/backup/provider evidence is unavailable; Cloudflare Images remains HTTP 403/code 5403 |

## Inventory and route coverage

- Declared modules: 39. Enabled: 16. Disabled/legacy/skeleton: 23.
- Enabled modules with runtime routes: 15. Enabled without a runtime route by design: Core (bus/shared infrastructure), 1.
- Routes: 525 total, 519 named; GET/HEAD 280, POST 182, PUT 34, PATCH 21, DELETE 22, OPTIONS 1.
- Duplicate names: 0. Duplicate method/URI pairs: 0. Missing routed controller methods: 0.
- Public application boundaries are limited to liveness/readiness probes, the public Article read API and the signed/throttled Integration webhook boundary. Unexpected public business routes: 0.
- Navigation: 78 primary destinations, 78 sidebar destinations, dead links 0, permission mismatches 0, unauthorized/empty-group leaks 0.
- Context help: 78 specific guides, generic fallback on primary routes 0, broken/unauthorized related links 0.
- Routed application/module controller actions in the final completeness matrix: 453. `luong.txt` references all 453 controller classes/actions; pre-audit missing class-flow references 36, after 0.

## Data and runtime integrity

- `schema_admin`, `schema_admin_testing`, `schema_admin_browser_testing`: 63 migrations / 230 tables each.
- `schema_admin_development`: absent.
- Database foreign keys inspected: 720; constraints with orphan rows: 0.
- Duplicate index finding: the Employee identity hash has a unique and redundant non-unique index over the same column. It is low severity and intentionally not changed without a reviewed online migration.
- Demo bootstrap was executed twice against `schema_admin`; company/user/employee/assignment/position/payroll counts were stable and user-owned rows were retained.
- Canonical Payroll/Compensation/Planning money uses integer minor units. Legacy Organization/Recruitment/Employment Contract decimal boundaries are retained as medium compatibility debt rather than silently rewritten.

## Cache, jobs, events and integrations

- Direct cache call sites: 11. Dashboard, Organization and external-cache boundaries return arrays/JSON. Company paginator cache already rebuilds incomplete objects.
- Final defect `CACHE-001` fixed: `WorkforceCacheRegistry` evicts `__PHP_Incomplete_Class` and rebuilds Attendance/Schedule/Payroll read models.
- Queue jobs: 14; event classes: 56; listeners: 13. Existing idempotency, chunking, unique constraints, maker-checker and fail-open realtime tests remain the executable governance evidence.
- Reverb is non-critical/fail-open; private-channel tenant authorization remains covered.
- External traffic in tests/demo: zero. ERP/Government/Bank remain mock or kill-switch controlled.
- Cloudflare Images adapter tests pass, but live provider status is **NOT VERIFIED / BLOCKED** by the last HTTP 403/code 5403 result.

## Security and auth

- Browser authentication: Breeze/session. API authentication: Sanctum Bearer. Authorization: Spatie Permission plus IAM/company scope. Passport is not required.
- Security assurance: critical 0, high 0; fixed SSRF finding retained. Medium accepted gaps: phishing-resistant MFA and a selected malware-scanning provider.
- No tracked secret scan finding, dependency advisory, raw salary leak or cross-company data-quality finding.
- Salary/reporting values remain omitted server-side without `payroll.assignment.view`.

## Defects fixed in this audit

1. `CACHE-001`: incomplete serialized Workforce read models could reach runtime after class/autoload changes. Invalid objects are now evicted and rebuilt; regression test added.
2. `NAV-001`: the API Documentation primary menu entered a vendor Swagger view without the shared sidebar/context-help shell. A protected admin landing now owns the primary destination and links to the unchanged protected Swagger UI/JSON.
3. `ROUTE-001`: two actual public Article read routes lacked stable route names. Added `api.articles.index` and `api.articles.show` without changing URI or response semantics.
4. `DOCS-001`: stale current-environment claims (61 migrations/nine Employees) were corrected to the verified 63/230/39 contract; historical phase evidence remains labelled as history.
5. `FLOW-001`: 36 routed controller class-flow references missing from `luong.txt` were documented; final missing count is zero.

## Remaining real gaps

| ID | Severity | Component | Type | Why it matters | Recommended next action |
|---|---|---|---|---|---|
| EXT-INFRA-001 | High | Production target | External | Production DB, HTTPS/proxy, Redis runtime, mail and backup/PITR are not target-verified | Provision target through the runbook and capture staging/production evidence |
| DQ-EXT-001 | Medium | Cloudflare Images | Credential/external | Live upload/delete cannot be claimed after 403/5403 | Supply an Images-authorized token/account and run one safe upload/delete diagnostic |
| SEC-MFA-001 | Medium | IAM/Auth | Business/security requirement | Privileged browser access has no native phishing-resistant MFA | Approve an MFA product contract before broad production privileged access |
| SEC-AV-001 | Medium | Private uploads | External/security | MIME/size/private storage are enforced but malware cleanliness is not provider-certified | Select a scanner and add quarantine/asynchronous release workflow |
| ARCH-001..003 | Medium | Layer/module/outbox | Code debt | Legacy transport coupling and context-specific envelopes raise evolution cost | Refactor incrementally when each owned flow is next changed |
| MONEY-001 | Medium | Legacy budgets/contracts | Compatibility debt | Decimal boundaries differ from canonical integer-minor money | Design additive effective-dated minor-unit migration/backfill |
| TEST-001 | Medium | Browser harness | Test debt | Context-specific scripts duplicate fixture/login setup | Consolidate without weakening exact viewport contracts |
| SCHEMA-001 | Low | Employee index | Schema debt | One redundant index adds write/storage overhead | Drop only in a separately reviewed online-safe migration |
| ARCH-004/005 | Low | Disabled modules/permissions | Governance debt | Skeleton and stale catalog metadata add review noise | Retire only with ownership and reference evidence |
| RT-EXT-001 | Low | Reverb | External | Real multi-node topology is not production-certified | Verify origins/proxy/process lifecycle in staging; core remains fail-open |

There are no remaining Critical or High **code defects**. The only High item is external production-target readiness.

## Final evidence

- Browser: 78/78 HTTP 200 and specific help triggers at both exact viewports; 403/404/500, console warning/error, page error and overflow are all zero. Sidebar same-group, cross-group and Operations scroll deltas are 0; initial animation none, flicker not observed, mobile drawer reset correct. Browser fixture residue is zero.
- API contract: 24/24 operations, duplicate operation ID 0, undocumented/orphan 0.
- Architecture: violations 0; PHPStan level 10: 0.
- Composer/npm advisories: 0. Data Quality: 16/16.
- Docker: production overlay renders; image digest `sha256:c3b1027f4dbeddb6c3a4e32d6ed078104cce3d84b8545e68cfd1a23bb06029b2`, 62.2 MB, non-root `www-data` (82:82), Vite manifest present and forbidden build artifacts 0.
- Full PHPUnit: 568 tests, 567 passed, 7,508 assertions, one expected skip, 27m16s, exit 0; orphan workers after completion: 0.
- Production classification: `PRODUCTION_BLOCKED_EXTERNAL`.
