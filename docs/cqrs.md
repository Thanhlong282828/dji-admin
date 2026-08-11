# Organization CQRS

Write: Route → FormRequest → Controller → CommandBus → Command Handler → Write Repository →
Model → Domain Event → OrganizationChangeListener → cache version + activity log → response.

Read: Route → Controller → QueryBus → Query Handler → Read Repository → array read model /
paginator → Blade hoặc JSON.

Read repository whitelist filter/sort; controller không dùng Model, DB facade hoặc repository trực tiếp.

Employee CRUD follows the same flow through `EmployeeController`, immutable command/query messages,
handlers and scoped Eloquent repositories. Employee subdomain workflows remain pending.

Recruitment write flow uses commands for requisition creation/transition, candidate creation,
application creation and application stage changes. Dashboard, lists and pipeline are query handlers.
Onboarding dashboard/list are QueryBus read flows; write commands remain pending.

Organization export: Route → `OrganizationExportController` → authorization →
`GetOrganizationExportQuery` → QueryBus → Handler → `OrganizationReadRepositoryInterface::export` →
typed generator → `SpreadsheetFormulaSanitizer` → streamed CSV → activity log.
# Employee CQRS flows

- Assignment: `StoreEmployeeAssignmentRequest → Controller → CreateEmployeeAssignmentCommand → Handler → EmployeeAssignmentService → lock/transaction → Event`.
- Export: `EmployeeExportController → GetEmployeeExportQuery → Handler → EmployeeReadRepository → Generator → StreamedResponse`.
- Contract: `Controller → TransitionEmploymentContractCommand → Handler → Service → StateMachine`.
- Consent withdraw luôn tạo decision record mới.
# Interview Panel Summary read flow

`GET /admin/interviews/{interview}/panel-summary` → permission/tenant authorization → `InterviewController::panelSummary` → Query Bus → `GetInterviewPanelSummaryQuery` → typed Handler aggregation → typed DTOs → escaped Blade HTML.

Realtime remains a change signal only; every UI refresh returns through this authorized read flow.
# Hire Candidate CQRS

Read: `GET candidate-offers/{offer}/hire` → controller → Query Bus → `GetHireCandidatePreviewQuery/Handler` → tenant-scoped Offer read → typed `HireCandidatePreview` → Blade.

Write: `POST candidate-offers/{offer}/hire` → `HireCandidateRequest` → controller → Command Bus → `HireCandidateCommand/Handler` → `HireCandidateService` → Employee/Assignment/Contract/Onboarding domain services → after-commit event → redirect.
# Workforce CQRS flows

- Check-in/out: Request → AttendanceController → Command Bus → CheckIn/Out Handler → AttendanceCaptureService → repository → event after commit.
- Leave: Request → LeaveController → Create/Transition Command → LeaveWorkflowService → ledger/state event.
- Payroll input: Controller → Generate/Lock Command → PayrollInputGenerationService → immutable snapshot/period lock.
# Phase 3A-7 CQRS closure (2026-07-26)

Status: **Phase 3A — COMPLETED**. Workforce Controllers authorize exact resource/action abilities and dispatch typed Commands/Queries; they do not issue persistence queries. Cached repositories use `WorkforceCacheRegistry`, while lock/readiness decisions perform fresh reads inside their transaction. Domain events invalidate only the affected company/scope before notification listeners execute.
# Phase 3B-1 Payroll CQRS

Typed commands and handlers exist for Run create, calculate/recalculate, submit, approve, reject and finalize. Core writes dispatch through services/transactions and safe events. Query-side repositories/DTO handlers and component-assignment commands are still blockers; Phase 3B-1 remains PARTIAL.
## Payroll Input Operations

Payroll Input HTTP controllers perform context/Policy checks and dispatch typed Commands/Queries only. Capture, validate, next-version and create-Run writes pass through Command Bus handlers into `PayrollInputOperationsService`; list/create-options/detail reads pass through Query Bus into the dedicated read repository and typed operations ViewModel. No Payroll Input controller queries persistence directly.

## Payroll Period Operations — Phase 3B-2B

Read: Route → `PayrollPeriodController` → Query Bus → List/Detail Query + Handler → `PayrollPeriodReadRepositoryInterface` → Eloquent read repository → `PayrollPeriodViewModel` → Blade.

Write: FormRequest → Controller → Command Bus → Create/Update/Open/Lock/Close/Delete Handler → `PayrollPeriodOperationsService` → model/history/activity/event → redirect. Controllers and Blade do not query persistence.

## Payroll Run operational flow — Phase 3B-3

Read list/detail/item: `PayrollDeliveryController::runs/show/item` → `QueryBusInterface::ask()` → `GetPayrollRunList/GetPayrollRunDetail/GetPayrollItemDetail` Handler → `PayrollDeliveryReadRepositoryInterface` → `EloquentPayrollDeliveryReadRepository` → typed ViewModel → Blade.

Tax writes: route → `TaxAdministrationRequest::validated()` → `TaxAdministrationController` → `CommandBusInterface::dispatch()` → `SaveTaxPolicyCommand` / `SaveEmployeeTaxProfileCommand` → Handler → `TaxManagementService` transaction → tax tables → activity log → redirect.

Tax reads: route → `TaxAdministrationController` → `QueryBusInterface::ask()` → typed Tax Query → Handler → `TaxAdministrationReadRepositoryInterface` → `EloquentTaxAdministrationReadRepository` → `TaxAdministrationViewModel` → Blade.

Write: `PayrollRunMutationRequest` → `PayrollRunController::store/mutate` → Policy/Gate → `CommandBusInterface::dispatch()` → Create/Calculate/Submit/Approve/Reject/Finalize Handler → `PayrollRunService` → transaction/current-version consistency → history/event/notification → redirect. Controllers issue no persistence query.
# Payroll Input employee reads

Employee list/detail follow Route → Controller authorization → typed Query → shared Query Bus → typed Handler → read repository → ViewModel → Blade. Controllers contain no direct database query.
# Insurance CQRS

Insurance policy/profile writes use FormRequest → Controller → Command Bus → typed Command/Handler → transaction service. Reads use Controller → Query Bus → typed Query/Handler → company-scoped read repository → typed ViewModel → Blade.
# Phase 3D-1 Payslip CQRS

Payslip write endpoints dispatch typed Commands through the shared Command Bus. Read/detail/download endpoints dispatch typed Queries through the shared Query Bus and tenant-scoped read repository. `PayslipController` performs request normalization, permission/company authorization and response construction only; it does not query persistence. PDF generation is delegated to an ID-only unique queued Job, which hydrates the immutable snapshot server-side.
## Bank Payment Batch

Payment HTTP writes dispatch typed Commands to Handlers and `PayrollPaymentBatchService`; reads/downloads dispatch Queries to Handlers and `PaymentReadRepositoryInterface`. Controllers contain authorization, validated input, Bus dispatch and response construction only—no database, Crypt, Storage or CSV operations.
# Payroll Accounting CQRS (Phase 3D-3)

Accounting reads use `GetAccountingViewQuery → GetAccountingViewHandler → AccountingReadRepositoryInterface → EloquentAccountingReadRepository → AccountingViewModel`. Secure file download uses `DownloadAccountingJournalQuery`.

Configuration writes use `AccountingAdministrationRequest → Accounting*Controller → SaveAccountingAdministrationCommand → Handler → AccountingAdministrationService`. Journal writes use `AccountingJournalRequest → AccountingJournalController → ExecuteAccountingJournalCommand/ReverseAccountingJournalCommand → Handler → PayrollAccountingJournalService`. Controllers authorize, normalize request data, dispatch the bus and return HTTP responses; they do not query the database.
# Phase 3E-1 adjustment CQRS

`POST /admin/payroll/adjustments` → `PayrollAdjustmentRequest` → `PayrollAdjustmentController::store` → `CommandBusInterface::dispatch` → `CreatePayrollAdjustmentCommand` → `CreatePayrollAdjustmentHandler::handle` → `PayrollAdjustmentManagementService::create` → locked finalized Payroll Item → historical snapshot/checksum → history/event → redirect.

`POST /admin/payroll/adjustments/{adjustment}/{action}` → request validation + company authorization → `ProcessPayrollAdjustmentCommand` → handler → lifecycle/delta/application transaction → safe private event → redirect.

`GET /admin/payroll/adjustments[/{adjustment}]` → controller → `QueryBusInterface::ask` → `GetPayrollAdjustmentQuery` → handler → `PayrollAdjustmentReadRepositoryInterface` → typed `PayrollAdjustmentViewModel` → Blade.

## Phase 3E-1C2 — Adjustment downstream CQRS

Write actions remain thin HTTP adapters: `PayrollAdjustmentController` authorizes Company and artifact permission, creates one of `CreateAdjustmentPaymentInstructionCommand`, `CreateAdjustmentAccountingJournalCommand`, `GenerateAdjustmentStatementCommand` or `RetryAdjustmentDownstreamCommand`, and dispatches it through the shared Command Bus. Handlers delegate lifecycle coordination to `PayrollAdjustmentDownstreamService`; artifact rules live in the dedicated Payment, Accounting and Statement services. The coordinator persists typed attempt/completion/failure state and artifact references without embedding artifact construction logic.

Secure Statement reads use `DownloadAdjustmentStatementQuery → DownloadAdjustmentStatementHandler → PayrollAdjustmentStatementService`, which performs tenant-scoped lookup, integrity verification and access logging before returning a streamed private response. Adjustment detail continues through `GetPayrollAdjustmentQuery` and the read repository/ViewModel; Controllers and Blade do not query Eloquent directly.

C3 keeps these HTTP CQRS paths unchanged. `PayrollAdjustmentDownstreamService::begin` now treats an existing `processing` intent as an active execution lease, while the MySQL worker invokes the same Commands/Handlers used by HTTP to prove race safety. `PayrollAdjustmentBrowserFixtureCommand → PayrollAdjustmentBrowserFixtureService` is an explicitly local/testing support boundary, not an admin production endpoint.

## Phase 3E-2A designed annual PIT CQRS

No annual CQRS class is implemented yet. The approved design reuses the existing buses: FormRequest/Controller → typed Create/Calculate/Validate/Submit/Review/Approve/Finalize/Reject/Supersede Command → Handler → annual aggregate services/repository; and Controller → typed List/Detail/Audit/Document Query → Handler → company-scoped read repository → ViewModel/secure response. Controllers must never collect Payroll sources or calculate annual tax directly. See `docs/payroll-annual-tax-reconciliation-design.md`.

## Phase 3E-2B implemented Annual Tax CQRS foundation

`CreateAnnualTaxReconciliationCommand → CreateAnnualTaxReconciliationHandler::handle() → PayrollAnnualTaxReconciliationService::create() → AnnualTaxReconciliationRepositoryInterface/EloquentAnnualTaxReconciliationRepository → draft + immutable identity/policy snapshots + created history`.

`CollectAnnualTaxSourcesCommand → CollectAnnualTaxSourcesHandler::handle() → PayrollAnnualTaxReconciliationService::collect() → AnnualTaxSourceCollector::collect() → finalized Payroll Item/applied attributed Adjustment snapshots → AnnualTaxChecksumService → source/component/issue rows + sources_collected history`.

Both handlers are resolved by the existing shared Command Bus. No HTTP Controller, route, Query/UI or lifecycle endpoint was added in 3E-2B.

## Phase 3E-2C Annual Tax HTTP CQRS

Annual HTTP writes use `AnnualTaxReconciliationRequest → PayrollAnnualTaxReconciliationController → shared CommandBus → CreateAnnualTaxReconciliationCommand|ProcessAnnualTaxReconciliationCommand → thin Handler → source/operations/lifecycle service`. Reads use `GetAnnualTaxReconciliationQuery → Handler → bounded Company-scoped read projections → Blade`. Controllers perform Company Gate authorization, request normalization, dispatch and response only.
# Annual PIT dedicated mutation flow (Phase 3E-2C2)

`POST /admin/payroll/annual-tax/{id}/{action}` → action-specific FormRequest → `PayrollAnnualTaxReconciliationController::{action}` → action-specific Command → CommandBus → action-specific Handler → shared Annual Tax operation/lifecycle service → transaction/history/issues/intents → redirect.

The old `ProcessAnnualTaxReconciliationCommand` remains only for internal backward compatibility and is not reachable from the public Annual PIT HTTP routes.

Verification 2026-08-01: all 13 Annual PIT routes resolve; nine mutation routes are POST-only and dispatch dedicated Commands. Static analysis of `Modules/Payroll/app` and Payroll tests passes at level 10 with 0 errors.

Completed read split: `index → GetAnnualTaxReconciliationListQuery/Handler → AnnualTaxReconciliationListViewModel`; detail header uses `GetAnnualTaxReconciliationDetailQuery/Handler → AnnualTaxReconciliationDetailViewModel`; independently paged sources and histories use `GetAnnualTaxSourceQuery/Handler → AnnualTaxSourceViewModel` and `GetAnnualTaxHistoryQuery/Handler → AnnualTaxHistoryViewModel`. Controllers only authorize, normalize, dispatch and return responses.

Phase 3E-2D delivery writes: Route → Company Gate/action permission → Controller → Command Bus → `GenerateAnnualTaxStatement|CreateAnnualTaxPaymentIntent|CreateAnnualTaxRefundIntent|CreateAnnualTaxSettlementJournal|RetryAnnualTaxDownstream` → dedicated Handler → `PayrollAnnualTaxDeliveryService` → locked finalized aggregate → private artifact/intent/refund obligation/annual settlement journal. Download: Route → Gate → Controller → Query Bus → `DownloadAnnualTaxStatementQuery` → Handler → integrity service → private bytes response + access log.
# ERP integration (Phase 3E-3)

`POST /admin/payroll/erp/exports` → `ErpExportRequest` → `ErpExportController@store` → `CreateErpExportCommand` → `CreateErpExportHandler` → `ErpExportService` → authoritative accounting journal lines → ERP snapshot/items/log → redirect.

`POST /admin/payroll/erp/exports/{export}/{action}` → company Gate + permission → typed Validate/Execute/Retry/Reverse command → handler → locked domain service transition → Generic ERP adapter/private artifact → history → redirect.

`GET /admin/payroll/erp/exports[/{export}]` → `ErpExportController` → QueryBus → list/detail/log query handler → `ErpExportReadRepositoryInterface` → company-scoped ViewModel → Blade.

Connection/Mapping writes → `ErpExportRequest` → `ErpExportController` → `ManageErpAdministrationCommand` → handler → `ErpAdministrationService` → transaction/encryption/versioning → database.

Artifact download → `ErpExportController@download` → `DownloadErpArtifactQuery` → QueryBus → handler → `ErpExportService@download` → MIME/size/SHA-256/signature verification → access log → streamed response.

# Government Filing foundation (Phase 3E-4)

Write: `POST /admin/payroll/government-filings[/{filing}/{action}]` → `GovernmentFilingRequest` → `GovernmentFilingController` → shared `CommandBus` → typed Create/Generate/Validate/Approve/Submit/Retry/Reject Command + Handler → `GovernmentTaxFilingService` → locked immutable Annual Tax snapshot → filing/items/document/attempt/issues/log rows → redirect.

Read: `GET /admin/payroll/government-filings[/{filing}]` → Controller → shared `QueryBus` → List/Detail/History/CreateOptions Query + Handler → `GovernmentFilingReadRepositoryInterface` → company-scoped ViewModel → Blade. Download uses `DownloadGovernmentDocumentQuery` and revalidates the private XML artifact before returning bytes and recording an access log.

# Phase 3E-5A — Employee Lifecycle CQRS proposal (documentation only)

Future writes: Route → typed Request → Controller → shared CommandBus → `CreateEmployeeLifecycle|StartOnboarding|CompleteOnboarding|TransferEmployee|PromoteEmployee|TerminateEmployee` → Handler → lifecycle state machine/domain service → company-scoped locked write repositories → append-only history/outbox → typed result.

Future reads: Route → company/field authorization → QueryBus → `Employee360View|EmploymentHistory|OrganizationPositionView|LifecycleTimeline` → Handler → bounded read projections → typed ViewModel/API Resource. No route, Command, Query or PHP class was created in Phase 3E-5A.
# Employee Lifecycle CQRS (Phase 3E-5B)

`GET /admin/employee-lifecycle` → `EmployeeLifecycleController@index` → `GetEmployeeLifecycleListQuery` → QueryBus → `GetEmployeeLifecycleListHandler` → `EmployeeLifecycleReadRepositoryInterface::paginate` → paginator → `lifecycle/index.blade.php`.

`GET /admin/employee-lifecycle/{employee}` → `Employee360ViewQuery` → QueryBus → `Employee360ViewHandler` → read repository → readonly `EmployeeLifecycleViewModel` → `lifecycle/show.blade.php`.

Lifecycle POST routes → `EmployeeLifecycleActionRequest` → controller → CommandBus → named command/handler → `EmployeeLifecycleService` → row lock/idempotency/state validation → lifecycle event + immutable transition + outbox → redirect.

# Talent & Performance CQRS (Phase 3E-5C)

`GET /admin/talent/performance` → `PerformanceController::index()` → `GetPerformanceDashboardQuery` → QueryBus → Handler → `PerformanceReadRepositoryInterface::dashboard()` → black-gold dashboard.

`GET /admin/talent/performance/employees/{employee}` → `EmployeePerformance360Query` → QueryBus → Handler → company-scoped repository + `CompetencyService` → readonly `EmployeePerformanceViewModel` → Employee 360.

`POST /admin/talent/performance/{action}/{subject?}` → `PerformanceActionRequest` → policy/company gate → dedicated Create Cycle/Assign Goal/Progress/Start Review/Self Review/Manager Review/Approve/Close Command → Handler → Cycle/Goal/Review service → typed state machine + row lock + idempotency → append-only history and transactional outbox → redirect.

# Career & Succession CQRS (Phase 3E-5D)

`GET /admin/career` and `/employees/{employee}` → `CareerController` → QueryBus → `SuccessionDashboardQuery|EmployeeCareer360Query` → Handler → `CareerReadRepositoryInterface` → ViewModel/typed array → Blade.

`POST /admin/career/{action}/{subject?}` → `CareerActionRequest` → company Policy → dedicated Career/Succession Command → CommandBus → Handler → domain service/state machine → row lock/idempotency → aggregate + append-only `career_history` + transactional `career_outbox` → redirect.

# Workforce Analytics CQRS (Phase 3E-5E)

GET `/admin/analytics/workforce` → Controller → `WorkforceDashboardQuery` → QueryBus → Handler → deterministic read service → readonly ViewModel → dashboard. POST snapshots → permission/company gate → `GenerateWorkforceSnapshotCommand` → CommandBus → Handler → transaction/idempotency/checksum → run + three typed snapshots + outbox.
## HR Data Governance

`POST /admin/hr/governance/runs -> HRGovernanceActionRequest -> HRGovernanceController::run -> CommandBus -> RunDataQualityCheckHandler -> HRDataGovernanceService -> quality rule registry -> governance tables -> append-only audit`.

`GET /admin/hr/governance -> HRGovernanceController::dashboard -> QueryBus -> HRDataQualityDashboardHandler -> HRGovernanceReadRepositoryInterface -> HRGovernanceDashboardViewModel -> Blade`.

`GET /admin/hr/governance/consents -> ConsentAuditViewQuery -> ConsentAuditViewHandler -> company-scoped read repository -> readonly view model`. `GET /admin/hr/governance/documents` follows the equivalent `DocumentGovernanceView` flow. Each protected view dispatches `RecordHRGovernanceAccess` through CommandBus to append a `data_viewed` audit event.

## Enterprise Workflow Engine — Phase 3F-1

Completion hardening keeps controllers limited to company authorization, typed Command/Query dispatch and HTTP responses. The new audit path is `GET /admin/workflows/instances/{instance}/audit → WorkflowController::audit() → WorkflowAuditViewQuery → WorkflowAuditViewHandler → WorkflowReadRepositoryInterface::detail() → WorkflowDashboardViewModel → workflow::audit`. Writes preserve the lock order definition/version → instance → task → append-only decision/history → outbox.

## Enterprise Notification Center — Phase 3F-2

HTTP routes authorize company+permission and dispatch typed Commands/Queries only. Write examples: `POST /admin/notifications/{message}/read → MarkNotificationReadCommand → NotificationReadStateService`; retry dispatches `RetryNotificationDeliveryCommand → NotificationDeliveryService`. Reads use `NotificationInboxQuery`, detail, unread count, template list and delivery history handlers backed by `NotificationReadRepository` and `NotificationCenterViewModel`. Workflow integration is `workflow_outbox → notification:consume-workflow-outbox → QueueNotificationData → NotificationDeliveryService`.

Write: `Admin route → WorkflowRequest → WorkflowController → CommandBusInterface → typed Command/Handler → Workflow Domain Service → locked/idempotent persistence → append-only history/outbox → redirect`.

Read: `Admin route → WorkflowController → QueryBusInterface → typed Query/Handler → WorkflowReadRepositoryInterface → WorkflowDashboardViewModel → Blade`.

Workflow completion is an outbox callback contract and cannot mutate the owner domain aggregate.
# IAM Governance CQRS

IAM write: route → `IamRequest` → `IamController` → Command Bus → typed Command/Handler → `IamGovernanceService` → SoD → `SpatieAccessProvisioningAdapter` → immutable history/outbox. IAM reads use Query Bus and `IamReadRepository`; Controllers do not query IAM data.

IAM certification: route → Create/Start/Decide/Complete Review Command → Handler → `AccessReviewService` → locked campaign/item → immutable snapshot/certification → adapter revoke → history/outbox. Workflow/Employee callbacks use checkpointed consumers and IAM Command Bus; IAM outbox uses `QueueNotificationCommand` and Notification Center.
# Enterprise Integration Hub

`POST /admin/integrations/outbound → IntegrationRequest → IntegrationController::queue → CommandBus → QueueIntegrationMessageHandler → IntegrationHubService::queue → integration_outbound_messages/history/outbox → redirect`.

`POST /api/integrations/webhooks/{company}/{connection}/{provider} → IntegrationController::webhook → CommandBus → ReceiveWebhookHandler → IntegrationHubService::receiveWebhook → signature verification → webhook receipt/inbound message`.

`ERP/Government persisted artifact → QueueErpExportIntegrationCommand|QueueGovernmentFilingIntegrationCommand → CommandBus → handler → EnterpriseDomainIntegrationBridge → IntegrationHubService::queue → outbound/history/outbox`.

# Enterprise Audit Platform

`Workflow|Notification|IAM|Integration|Employee outbox, or Payroll activity event → concrete source consumer::consume() → source row/company lock → audit_source_checkpoints unique guard → AuditRecorderInterface::record() → EnterpriseAuditService::record() → audit_events + immutable snapshots + audit_outbox`. Retry of the same source/consumer identity is a no-op.

Admin reads use `AuditPageQuery → AuditPageHandler → AuditReadRepository → AuditPageViewModel`. CSV and incremental PDF use `CreateAuditExportCommand → CreateAuditExportHandler → AuditExportService`; retention archive uses `ArchiveAuditRangeCommand → ArchiveAuditRangeHandler → AuditRetentionService::archive → audit_archive_artifacts`. Controllers never query source or Audit tables directly.
# Phase 3F-19 architecture governance

CQRS is the enterprise orchestration boundary, not a ban on domain/application services. Governed writes enter Command Bus/handlers; reads enter Query Bus/read-only snapshot services. Controllers do not mutate persistence directly. The Integration controller's duplicated `company_user_access` query was replaced by the canonical Organization `TenantAccessService`, with the dependency declared in `module.json`. Legacy Article controllers remain documented legacy-supported exceptions.
