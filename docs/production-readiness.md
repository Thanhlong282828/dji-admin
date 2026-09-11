# ERP production readiness

Generated from the repository and local/testing evidence on 2026-09-09. No VPS or production provider was contacted. `PASS` means code/runtime evidence exists; `CONFIGURATION_REQUIRED` means the deployment environment must supply and verify the dependency.

| Area | Status | Evidence | Remaining production requirement | Runbook |
|---|---|---|---|---|
| Web authentication | PASS | Breeze/session routes, verified middleware, password reset and login throttling | HTTPS origin and production mail verification | `docs/operations-runbook.md` |
| API authentication | PASS | Passport bearer-token middleware and API contract tests | Production client/keys and scope review | API documentation |
| Authorization/tenant | PASS | Spatie permissions, company/branch guards, crafted cross-company tests | Review role assignments before go-live | Security assurance |
| CSRF | PASS | Laravel web middleware; health probes deliberately stateless | Trusted HTTPS proxy correctness | Deployment section |
| CORS | PARTIAL | API/web boundaries are separate | Explicit production origin policy must be approved | Incident/security section |
| Security headers | PASS | Global `ApplySecurityHeaders`; runtime test covers nosniff, frame, referrer and permissions policy | CSP host allow-list; HSTS activates only for production HTTPS | Deployment smoke |
| Session/cookies | CONFIGURATION_REQUIRED | HTTP-only and SameSite=lax defaults; JSON serialization | `SESSION_SECURE_COOKIE=true`, encrypted shared Redis/database session | Deployment section |
| Files/DMS | PASS | MIME/size validation, private authorized download, SHA-256 version evidence | Private storage backup and capacity monitoring | Storage incident |
| Cloudflare Images | CONFIGURATION_REQUIRED | Adapter has timeout and secrets remain environment-owned | Verify account/token/delivery path in deployment; non-critical to core ERP | External incident |
| Queue/jobs | PASS | 14 jobs inventoried; explicit tries/timeouts added; backoff on stateful scheduled jobs | Redis/Horizon worker capacity and failed-job alerting | Queue section |
| Failed jobs | PASS | Laravel failed-job persistence and CLI recovery | Financial retries require source/idempotency review | Queue section |
| Scheduler | PASS | Minute cron contract, heartbeat, overlap protection; critical attendance/leave jobs use one-server locks where configured | Deploy exactly one scheduler process and shared lock store | Scheduler section |
| Cache/locks | PASS | Tenant keys include company scope; notification locks and read-model invalidation exist | Shared Redis availability and eviction policy | Cache/Redis incident |
| Transactions/concurrency | PASS | Payroll, stock, Sales/AR, Treasury, Procurement, Finance, Project and Maintenance tests cover transaction/idempotency boundaries | Monitor deadlocks and retry only canonical commands | Incident section |
| Database/indexes | PASS | 96 additive migrations; tenant/source indexes; query-count and architecture tests | Production MySQL sizing, slow-query monitoring and migration review | Deployment section |
| Observability | PASS | Request/correlation ID, structured context, health/readiness, metrics and business audit | Central log sink/retention and alerts | Observability section |
| Reverb | PASS | Private channel authorization and fail-open CRUD design | Process supervisor and proxy websocket configuration | Reverb incident |
| Backup | CONFIGURATION_REQUIRED | Guarded encrypted backup script and checksum contract | Production target, retention and operator credentials | Backup section |
| Restore | PASS (isolated evidence) | Restore script only accepts `_restore_testing`; prior disposable restore evidence exists | Repeat against the selected production backup provider | Restore section |
| RPO/RTO | CONFIGURATION_REQUIRED | Recovery order is documented | Business owners must approve targets | DR section |
| Deployment | PASS (code/runbook) | Predeploy, release, migration-risk, health and smoke tooling | Immutable artifact and authorized production environment | Deployment section |

## Authentication boundaries

- Web: browser → encrypted/secure session cookie in production → CSRF → `auth`/`verified` → User → permission and tenant scope.
- API/mobile: client → Passport bearer access token → `auth:api`/scope middleware → User. A CSRF token is not an authentication token.
- Sanctum remains available for first-party token use where routes explicitly select it; it does not replace the Passport scope contract already used by documented APIs.

## Queue retry classification

Fourteen application jobs were audited. State-generating attendance, leave, employee-expiry, recruitment and payslip jobs use deterministic dates/identifiers, upsert/insert-ignore, locks or owner services. All jobs now have an explicit retry ceiling and timeout; stateful jobs have bounded backoff. `OperationsProbeJob` is side-effect free and intentionally tries once. Financial jobs must still be retried individually only after confirming their source/idempotency reference.

## Performance statement

Local deterministic suites cover Dashboard, Reporting/Planning query ceilings, Payroll calculation/export, Finance, Inventory, Project, Maintenance and Data Quality. They are regression evidence, not a production RPS claim. Load scenarios should exercise login, dashboard, catalog, inventory, sales, projects and maintenance in staging without contacting production or external media.

## Migration 000094–000096

- `000094`: additive Project runtime; independent owner tables and indexes. Review initial table creation duration but no existing-column rewrite.
- `000095`: additive Maintenance runtime; no Asset/Inventory duplication.
- `000096`: nullable source columns plus indexes/unique idempotency keys on Finance/Procurement/Asset tables. Existing rows remain valid; on large tables deploy after checking online-DDL/lock behavior.
- Rollback is not the normal production recovery path. Prefer compatible application rollback or forward fix; never blindly roll back financial/history schema.

Current classification remains **PRODUCTION_BLOCKED_EXTERNAL** because production DB/TLS/domain, shared session settings, mail, backup target, Redis runtime and deployment infrastructure have not been verified from this workspace.
