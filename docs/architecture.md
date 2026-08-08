# Organization Core architecture

Module `Organization` là bounded context duy nhất cho cost center, organization unit, job position và
headcount read model. Controllers chỉ dispatch `CommandBusInterface`/`QueryBusInterface`. Eloquent và
transaction nằm trong infrastructure repositories. Tenant boundary được kiểm tra bởi policy và
`TenantAccessService` qua bảng `company_user_access`.

Tree được dựng từ một tập row đã aggregate, tránh query đệ quy trong Blade. Headcount dùng active
assignment theo effective date, phân biệt `people_count` và `allocated_fte`.

Employee Core uses the existing Core CommandBus/QueryBus. Employee identity encryption is implemented
as an Eloquent custom cast plus a domain identity service; read repositories return masked data by default.

Recruitment uses the same buses with separate read/write repositories. Requisition and application
transitions are enforced by domain state machines. Onboarding currently exposes CQRS read models only.

Organization exports follow Route → Export Controller → tenant authorization → Export Query →
QueryBus → Handler → Read Repository → typed generator DTO → sanitized CSV stream. Independent rows
use database cursors; hierarchy export builds one bounded parent map and uses aggregate metrics.
# Phase 2.5B Employee Core

Employee write flow giữ CQRS: Request → Controller → Command Bus → typed Command/DTO → Handler → Domain Service/Repository → transaction/model → event/listener. Assignment khóa Employee và các assignment giao nhau trước khi kiểm tra allocation/primary. Private files dùng Laravel private disk; production scanner mặc định ClamAV và fail-closed.
## Recruitment realtime boundary

Domain writes emit `RecruitmentWorkflowChanged` after commit. The event is both a minimal private-company change signal and the single input to the queued internal-notification listener. Recipient resolution is relation-first and tenant-scoped. Browser code treats events as invalidation signals and re-enters authorized CQRS HTTP reads; no sensitive business aggregate is transported through WebSocket payloads.
# Phase 2.5C-3 atomic hire boundary

Recruitment owns the `HireCandidateCommand` orchestration boundary. Employee assignment/contract and Onboarding case creation remain owned by their respective modules and are called synchronously inside one outer MySQL transaction. Cross-module side effects are emitted only through `CandidateHireCompleted` after commit. Durable source foreign keys and `hire_transaction_markers` make retries idempotent.
# Phase 3 Workforce Operations

Attendance, Leave and Payroll are enabled bounded modules. Writes use Core Command Bus and domain transactions; reads use Query Bus/typed DTOs. Raw attendance events and leave ledgers are immutable sources, while daily attendance and payroll inputs are versioned projections/snapshots.
# Phase 3A-7 architecture closure (2026-07-26)

Status: **Phase 3A — COMPLETED**. HTTP writes follow Request → dedicated Policy → Controller → Command Bus → transaction/service/repository → event → cache invalidation → durable notification. Cached reads use company/scope/version keys through one registry; critical decisions bypass stale cache after acquiring locks. Race tests exercise the same services from independent Laravel processes.
# Phase 3B-1 Payroll foundation

Payroll Input remains separate from Payroll Result. A Run references one exact immutable Input Snapshot; calculation resolves effective component assignments into immutable item/component snapshots using integer minor units. No result is written back into Attendance, Leave or Overtime. Phase 3B-1 remains PARTIAL pending HTTP/read/export/concurrency closure.
