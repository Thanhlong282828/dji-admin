# LaravelDji Technology and Capability Inventory

Ngày kiểm kê source: **2026-11-14**.

Tài liệu này mô tả các chức năng, công nghệ và kiến thức kỹ thuật tìm thấy trong repository LaravelDji. Đây là architecture inventory của source hiện tại, không phải bằng chứng rằng mọi provider hoặc chức năng đã được production-verified.

Quy tắc kiểm kê và cách phân loại bằng chứng nằm tại `docs/technology-governance.md`.

## 1. Evidence classification

- `IMPLEMENTED_AND_VERIFIED`: có implementation và executable evidence phù hợp trong source hiện tại.
- `IMPLEMENTED_NOT_RUNTIME_VERIFIED`: có implementation nhưng chưa có bằng chứng runtime đúng môi trường cần xác minh.
- `CONFIGURED_OR_SUPPORTED`: framework/config hỗ trợ nhưng chưa chứng minh đang được application sử dụng hoặc provider đang hoạt động.
- `DOCUMENTATION_ONLY`: mới có governance, design hoặc runbook.
- `BLOCKED`: implementation tồn tại nhưng verification đang có blocker.
- `NOT_PRESENT`: không tìm thấy implementation sau khi kiểm tra các nguồn bắt buộc.

## 2. Repository scale and architecture

| Thành phần | Số lượng kiểm kê |
|---|---:|
| Business/application modules | 47 |
| Canonical Admin workspaces | 143 |
| Database migrations | 127 |
| Route files | khoảng 86 |
| Blade views | 392 |
| PHP test files | 278 |
| Model files | khoảng 54 |
| Event files | 59 |
| Job files | 14 |

LaravelDji là **modular monolith**, không phải microservices. Kiến trúc sử dụng module ownership, CQRS, commands/queries/handlers, domain/application services, repositories/read models, transactions, locking, events, queues, bounded outbox usage và integration boundaries.

Việc có event, queue, outbox hoặc network integration không tự biến application thành microservices.

## 3. Business capabilities

### 3.1 Human Resources

- Employee profile and employment lifecycle.
- Company, branch, organization unit and position.
- Attendance and shifts.
- Leave.
- Recruitment.
- Onboarding and offboarding.
- Employment contracts and labor relations.
- Payroll, benefits and insurance.
- Performance, training, career and succession.
- Approval, maker-checker and audit evidence where supported by canonical workflows.

Owning modules include `Employee`, `Attendance`, `Shift`, `Leave`, `Recruitment`, `Onboarding`, `Offboarding`, `Payroll`, `Benefit`, `Insurance`, `Performance`, `Training`, `Succession`, `Organization`, `Position`, `Contract` and `LaborRelation`.

### 3.2 Finance and Tax

- Accounting policies, fiscal years and accounting-period locks.
- Journals, ledgers and posting evidence.
- Accounts receivable and accounts payable.
- Treasury, cash and budgets.
- Financial reporting.
- FX recognition, settlement and revaluation.
- Supplier and inventory FX valuation/reversal.
- VAT input/output policy and source lineage.
- Payroll-to-accounting attribution.
- Transaction, rollback, idempotency and audit controls.

Primary modules: `Finance`, `Tax`, with integration to `Payroll`, `Procurement`, `Inventory`, `Commerce` and `Report`.

### 3.3 Procurement and Supply Chain

Canonical forward lifecycle:

`Purchase Request → Purchase Order → Goods Receipt / Service Acceptance → Vendor Bill → Payment`

Canonical reverse lifecycle:

`Goods Return → Supplier Credit → Supplier Refund`

The source also covers vendor governance, approval, status tracking, company/branch scope, reversals and procurement reporting.

### 3.4 Inventory and Warehouse

- Product/category and SKU relationships.
- Warehouses, balances and inventory movements.
- Stock transfer, count and adjustment.
- Availability and low-stock information.
- Inventory governance and valuation lineage.
- Company/branch isolation and source-document traceability.

### 3.5 Sales, Commerce and Customers

- CRM and customer metrics.
- Commerce and POS.
- Orders, fulfillment, return and refund flows where implemented.
- Product/catalog, cart, wishlist and checkout boundaries.
- Article, page, media, menu and SEO content capabilities.

### 3.6 Projects, Assets and Maintenance

- Project and project activity management.
- Asset-related finance, impairment and disposal.
- Maintenance work orders and part usage/return.
- Closeout, operational status and maintenance history.

### 3.7 Documents, Workflow and Audit

- Private document and version management.
- Authorized upload/download and company isolation.
- Workflow instance, steps, claims and decisions.
- Approval/rejection history and actor/timestamp evidence.
- Transaction locking, idempotency and immutable audit records.

### 3.8 Platform Administration

- IAM, users, roles and permissions.
- Policy/Gate authorization.
- Tenant/company/branch access control.
- Security assurance and activity audit.
- Settings, notifications, integration and system operations.

Module existence does not by itself prove every business workflow is complete. Each workspace still requires route, authorization, query, state transition, runtime data and test evidence before it can be classified operational.

## 4. Backend and data technology

| Capability | Technology/evidence | Classification |
|---|---|---|
| Runtime | PHP `^8.3` | `IMPLEMENTED_AND_VERIFIED` in dependency contract |
| Framework | Laravel `^13.8` | `IMPLEMENTED_AND_VERIFIED` |
| Primary database | MySQL 8.4 in Compose/CI | `IMPLEMENTED_AND_VERIFIED` in local/test architecture |
| Cache/queue backend | Redis 7.4, Predis | `IMPLEMENTED_NOT_RUNTIME_VERIFIED` per target environment |
| Module system | `nwidart/laravel-modules` | `IMPLEMENTED_AND_VERIFIED` |
| CQRS | Command/Query/Handler conventions | `IMPLEMENTED_AND_VERIFIED` in architecture tests/source |
| Transactions | Laravel DB transactions | `IMPLEMENTED_AND_VERIFIED` |
| Concurrency | `lockForUpdate`, idempotency and process tests | `IMPLEMENTED_AND_VERIFIED` in covered workflows |
| Events/jobs | Laravel events, listeners and queued jobs | `IMPLEMENTED_AND_VERIFIED` |
| Outbox | Bounded domain-specific outbox usage | `IMPLEMENTED_AND_VERIFIED` only for owning flows |

Configured framework backends such as Memcached, DynamoDB, SQS or Beanstalkd are not considered active merely because Laravel config supports them.

## 5. Authentication, authorization and tenancy

- Laravel session authentication for Web.
- Laravel Passport OAuth2 for API.
- RSA Passport signing keys.
- OAuth scopes/token abilities, revoke and expiry behavior.
- Spatie Laravel Permission for roles and permissions.
- Policy/Gate authorization.
- Server-side company and branch scoping.
- Email verification.
- Password hashing via Laravel Hash.
- Google and Facebook social identity integration boundaries.
- Phone OTP login/verification.
- Signed routes, CSRF protection and rate limiting.
- Database-backed sessions by default.

Phone OTP is not full MFA. Full TOTP enrollment/enforcement/recovery and WebAuthn/passkeys are not present at this baseline.

## 6. Cryptography and data protection

The following controls are distinct and must not be reported as interchangeable:

| Control | Current use |
|---|---|
| HMAC-SHA-256 | Webhook authentication and employee identity keyed fingerprint |
| `hash_equals()` | Constant-time integrity/signature comparison |
| SHA-256 | Document, payload, backup, release and artifact integrity |
| Laravel encryption/encrypted casts | Recoverable credentials and sensitive values |
| Password hashing | User passwords |
| RSA | Passport token signing |
| `age` public-key encryption | Encrypted database backup artifacts |
| UUID/random object keys | Private object naming and isolation |
| Masking | Restricted data presentation |
| Sensitive-data redaction | Logs and telemetry |

Webhook flow includes `X-Signature`, `X-Event-ID`, `X-Event-Type`, connection secret lookup, HMAC verification, payload checksum, immutable receipt and provider-event deduplication. Universal provider timestamp/replay windows require provider-specific verification and must not be assumed.

Encrypted backup flow is:

`mysqldump → gzip → age recipient encryption → SHA-256 artifact checksum → manifest → manifest checksum → restricted permissions`

## 7. API and integrations

- REST API.
- Laravel Passport authentication and token scopes.
- OpenAPI 3 annotations/contracts.
- L5 Swagger generation.
- Executable API contract audit.
- HMAC-protected webhook receiver.
- Provider-event deduplication and receipt persistence.
- Outbound URL/SSRF policy.
- External-provider abstraction and kill-switch governance.
- Google/Facebook external identity boundaries.
- Mobile API foundation present in source history/current integration state as applicable; runtime status must be reverified against current HEAD.

GraphQL application API is not present.

## 8. Queue, realtime and cache

### Queue and processes

- Database and Redis queue connections.
- Laravel Horizon.
- Failed-job and batch support.
- Queue workers, scheduler and process lifecycle definitions.
- After-commit events/jobs.
- Worker/queue readiness and backlog concepts.

### Realtime

- Laravel Reverb.
- Laravel Echo.
- Pusher protocol/client.
- Broadcast/private-channel boundaries.
- Fail-open Reverb transport for non-critical realtime degradation.

### Cache

- Database, file and Redis stores.
- Cache locks and TTL.
- Failover cache configuration.
- Direct invalidation where deletion semantics are required.
- Versioned-cache design/patch history for suitable business read models.
- Tenant/cache safety governance.

Backend support listed in framework config is not proof that every cache/queue driver is active.

## 9. Storage, media and malware protection

- Laravel Flysystem.
- Local private storage.
- AWS S3-compatible adapter.
- Supabase Storage S3-compatible private document disk.
- Google Cloud Storage adapter/service.
- Cloudflare Images adapter for image workflows.
- Temporary signed downloads after authorization.
- Randomized tenant-scoped object keys.
- MIME, extension, content-signature and size validation.
- SHA-256 object/document integrity.
- ClamAV integration boundary and fail-closed malware-scanning contract.

Cloudflare, Supabase, GCS and ClamAV production status must be verified per target. Adapter source or fake tests are not live-provider evidence. Supabase S3 credentials are privileged server credentials; Laravel authorization and tenant scope remain mandatory and are not replaced by Storage RLS.

## 10. Reports, files and notifications

- DomPDF-based PDF generation.
- Payslip, payroll adjustment and annual-tax artifacts.
- Maatwebsite Excel/XLSX exports.
- CSV/HTML/PDF/XLSX enterprise reports.
- Spreadsheet formula-injection protection.
- Private artifact checksum and source lineage.
- Laravel Notifications and database notification center.
- Mail delivery abstraction and retry/idempotency.
- Mailpit for local development.
- SMTP/SES/Postmark/Resend/Slack configuration support; active-provider status requires runtime proof.

## 11. Frontend and browser technology

- Blade templates.
- Alpine.js.
- Tailwind CSS 4 and Tailwind Forms.
- Vite 8 and Laravel Vite Plugin.
- TypeScript 7.
- Laravel Echo and Pusher JS.
- Be Vietnam Pro and Lora fonts.
- Responsive design from mobile to ultrawide.
- Keyboard/focus, modal, touch-target and reduced-motion governance.
- Playwright Core/browser scripts for authenticated responsive QA.

React, Vue, Angular, Svelte and other SPA frameworks are not present as the primary frontend stack.

## 12. Testing and quality gates

- PHPUnit 12.
- Mockery and Faker.
- Unit, feature, contract, architecture, authorization and tenant-isolation tests.
- Process-level concurrency tests.
- Browser and responsive checks.
- PHPStan/Larastan Level 10.
- Laravel Pint.
- Composer and npm dependency audits.
- Secret scanning.
- Migration-risk scanning.
- API/OpenAPI contract gates.
- Blade compile/cache validation.
- Vite production build.
- EnvironmentSafetyGuard and database identity verification.
- CI line-coverage gate currently configured at 20%; this is a minimum gate, not a quality claim for every critical workflow.

## 13. Containers and runtime topology

Docker/Compose definitions cover:

- Laravel/PHP application.
- Nginx unprivileged.
- MySQL 8.4.
- Redis 7.4.
- Queue worker.
- Scheduler.
- Horizon.
- Reverb.
- Mailpit.
- Dedicated networks/volumes and health checks.
- Dropped capabilities and `no-new-privileges` security controls where configured.

Repository variants include development, production, disaster recovery, runtime verification and release certification Compose files. Nginx reverse-proxy/release-switch templates, PHP-FPM configuration, systemd Reverb service and process-management documentation are present.

## 14. CI/CD and supply-chain controls

GitHub Actions includes or configures:

- MySQL testing service and isolated testing database.
- PHP and Node setup.
- Composer validation/install.
- Migration and environment readiness gates.
- Vite build, Pint and PHPStan Level 10.
- Architecture, API contract, concurrency and PHPUnit coverage gates.
- Composer/npm audit and secret scan.
- Migration-risk and readiness smoke checks.
- Docker Buildx and GHCR publishing contract.
- Trivy image scanning.
- Development/production environments and deployment gates.
- SSH release script with immutable SHA/image evidence.
- Google Cloud Workload Identity Federation/OIDC smoke test.

Workflow presence is not current CI PASS evidence. Current run, quota, branch protection and environment approvals must be checked separately.

## 15. Release, backup and disaster recovery

- Release manifest and release certificate.
- Deterministic source checksum and Git SHA identity.
- Immutable image tag/digest and migration inventory checksum.
- Quality/security/readiness evidence.
- Blue/green candidate and Nginx release switching.
- Predeploy, postdeploy smoke and rollback scripts.
- Encrypted backup and verified restore-to-staging flow.
- DR Compose environment, DR certificate and recovery rehearsal.
- Business-continuity and RTO/RPO governance.

Command success alone is not backup recoverability; an isolated successful restore and verification are required.

## 16. Observability and operations

Implemented application-side capabilities include:

- HTTP metrics recorder.
- Request duration and HTTP status/5xx metrics.
- Queue/outbox metrics.
- Scheduler heartbeat.
- Database, Redis and Reverb readiness.
- Health/readiness endpoints.
- Reliability snapshot and SLO calculation.
- Alert evaluation and operations dashboards.
- Capacity/readiness views.
- Cloudflare DDoS monitoring boundary.
- Structured logging and sensitive-data redaction.

Prometheus/Grafana monitoring architecture and runbooks exist, but the repository inventory did not establish a complete production provisioning set such as canonical Prometheus, Alertmanager and Grafana datasource/dashboard provisioning. Report this as design/partial implementation until target runtime evidence proves otherwise.

`ErrorTrackerInterface` exists with a null adapter. Sentry/Bugsnag must not be reported as integrated without a real provider binding and runtime verification.

## 17. Cloud and external platforms

Source/config boundaries exist for:

- Cloudflare Images and Cloudflare security telemetry.
- Supabase Storage S3.
- Google Cloud Storage.
- Google Secret Manager.
- Google Cloud Workload Identity Federation.
- GitHub Container Registry.
- Google and Facebook OAuth.
- AWS-compatible S3.
- Framework-supported SES, SQS and DynamoDB drivers.

The last group is `CONFIGURED_OR_SUPPORTED` unless a concrete application call path and runtime evidence prove active use.

## 18. Enterprise engineering knowledge represented

- Modular architecture and domain ownership.
- CQRS and controlled read/write boundaries.
- Maker-checker workflows.
- Immutable snapshots and effective dating.
- Fiscal-period locking and double-entry accounting.
- Integer minor-unit money and basis-point rates.
- FX recognition, settlement and revaluation.
- Source lineage and tamper detection.
- Transaction rollback, locking and idempotency.
- Duplicate-event prevention and compensating cleanup.
- Tenant-aware projections and permission-at-query-boundary.
- Data lifecycle, retention and data-quality gates.
- Environment and database safety verification.
- Cryptographic and secret inventory.
- Release trust boundaries and business continuity.

## 19. Current baseline exclusions

At this baseline, there is not enough implementation evidence to claim use of:

- Independent microservices.
- Kubernetes.
- Helm.
- Terraform.
- Ansible.
- Kafka.
- RabbitMQ.
- NATS.
- GraphQL application API.
- Elasticsearch.
- OpenSearch.
- Meilisearch.
- Algolia.
- React.
- Vue.
- Angular.
- Svelte.
- Laravel Sanctum as the primary authentication mechanism.
- Custom JWT as the primary authentication mechanism.
- WebAuthn/passkeys.
- Full TOTP MFA.
- Real Sentry/Bugsnag provider integration.
- Service mesh.
- Complete event sourcing architecture.

These exclusions are a dated evidence statement, not a permanent technology ban. Future audits must rescan dependency, source, deployment and runtime evidence.

## 20. Known evidence limitations

- A module, controller, route or table does not prove an end-to-end workflow.
- HTTP 200 does not prove correct authorization, tenant scope or KPI formula.
- Source/test evidence does not equal production-provider verification.
- Local PASS does not equal CI, staging or production PASS.
- Framework configuration support does not mean the driver is active.
- Historical documentation must be revalidated against the current HEAD.
- Cloud provider, monitoring, mail, malware scanner and external government/bank integrations need target-specific runtime evidence.

## 21. Canonical summary

LaravelDji is a Laravel 13/PHP 8.3 modular-monolith ERP platform with MySQL, Redis, CQRS, events, queues, bounded outbox usage, OAuth2 Passport, RBAC/Policy authorization, tenant/company/branch isolation, HMAC-protected integrations, encrypted backups, private multi-provider storage, realtime Reverb/Echo, OpenAPI contracts, extensive static/testing gates and containerized release/operations governance.

It must not be described as microservices, full event sourcing, full MFA, SPA-framework based or fully production-integrated with every configured cloud/monitoring provider unless newer executable evidence proves that state.
