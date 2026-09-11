<?php

declare(strict_types=1);

use App\Models\User;
use App\Services\WorkforceNotificationDeliveryService;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Modules\Attendance\Application\Commands\ManageOvertimeCommand;
use Modules\Attendance\Application\Commands\ManageWorkforceResourceCommand;
use Modules\Attendance\Application\Commands\ProcessAttendanceWorkflowCommand;
use Modules\Attendance\Domain\Services\AttendanceWorkflowService;
use Modules\Attendance\Domain\Services\OvertimeManagementService;
use Modules\Attendance\Domain\Services\WorkforceManagementService;
use Modules\Audit\Application\Commands\ApplyRetentionPolicyCommand;
use Modules\Audit\Application\Commands\ArchiveAuditRangeCommand;
use Modules\Audit\Application\Commands\CreateAuditExportCommand;
use Modules\Audit\Application\DTOs\AuditEventData;
use Modules\Audit\Domain\Contracts\AuditRecorderInterface;
use Modules\Audit\Domain\Services\AuditExportService;
use Modules\Audit\Domain\Services\AuditRetentionService;
use Modules\Audit\Infrastructure\Ingestion\WorkflowAuditConsumer;
use Modules\Commerce\Application\Services\CheckoutService;
use Modules\Employee\Application\DTOs\LifecycleTransitionData;
use Modules\Employee\Domain\Services\EmployeeLifecycleService;
use Modules\Employee\Domain\Services\HRDataGovernanceService;
use Modules\Iam\Domain\Services\AccessReviewService;
use Modules\Iam\Domain\Services\IamGovernanceService;
use Modules\Integration\Domain\Services\IntegrationHubService;
use Modules\Leave\Domain\Enums\LeaveRequestStatus;
use Modules\Leave\Domain\Services\AutomatedLeaveLedgerService;
use Modules\Leave\Domain\Services\LeaveWorkflowService;
use Modules\Notification\Application\DTOs\QueueNotificationData;
use Modules\Notification\Domain\Services\NotificationDeliveryService;
use Modules\Notification\Domain\Services\NotificationReadStateService;
use Modules\Payroll\Application\Commands\CreateAnnualTaxReconciliation\CreateAnnualTaxReconciliationCommand;
use Modules\Payroll\Application\Commands\CreatePayrollAdjustment\CreatePayrollAdjustmentCommand;
use Modules\Payroll\Application\Commands\ProcessPayrollAdjustment\ProcessPayrollAdjustmentCommand;
use Modules\Payroll\Domain\Enums\PayrollAdjustmentDownstreamType;
use Modules\Payroll\Domain\Enums\PayrollRunStatus;
use Modules\Payroll\Domain\Services\ErpExportService;
use Modules\Payroll\Domain\Services\GovernmentTaxFilingService;
use Modules\Payroll\Domain\Services\PayrollAccountingJournalService;
use Modules\Payroll\Domain\Services\PayrollAdjustmentDownstreamService;
use Modules\Payroll\Domain\Services\PayrollAdjustmentManagementService;
use Modules\Payroll\Domain\Services\PayrollAnnualTaxDeliveryService;
use Modules\Payroll\Domain\Services\PayrollAnnualTaxLifecycleService;
use Modules\Payroll\Domain\Services\PayrollAnnualTaxOperationsService;
use Modules\Payroll\Domain\Services\PayrollAnnualTaxReconciliationService;
use Modules\Payroll\Domain\Services\PayrollConfigurationService;
use Modules\Payroll\Domain\Services\PayrollPaymentBatchService;
use Modules\Payroll\Domain\Services\PayrollRunService;
use Modules\Performance\Application\DTOs\CareerActionData;
use Modules\Performance\Application\DTOs\PerformanceActionData;
use Modules\Performance\Domain\Services\CareerPathService;
use Modules\Performance\Domain\Services\GoalManagementService;
use Modules\Performance\Domain\Services\PerformanceCycleService;
use Modules\Performance\Domain\Services\PerformanceReviewService;
use Modules\Performance\Domain\Services\SuccessionPlanningService;
use Modules\Performance\Domain\Services\WorkforceAnalyticsService;
use Modules\Workflow\Application\DTOs\StartWorkflowData;
use Modules\Workflow\Domain\Services\WorkflowExecutionService;
use Modules\Workflow\Domain\Services\WorkflowTaskService;

require dirname(__DIR__, 2).'/vendor/autoload.php';

$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
if (! $app instanceof Application) {
    throw new RuntimeException('Laravel application failed to bootstrap.');
}
$app->make(Kernel::class)->bootstrap();

[$script, $scenario, $barrier, $payloadPath] = $argv + [null, null, null, null];
if (! is_string($scenario) || ! is_string($barrier) || ! is_string($payloadPath) || ! is_file($payloadPath)) {
    fwrite(STDERR, "invalid-worker-input\n");
    exit(4);
}
$environment = $app->environment();
$connection = DB::connection();
$database = $connection->getDatabaseName();
if ($environment !== 'testing' || $connection->getDriverName() !== 'mysql' || $database !== 'schema_admin_testing') {
    fwrite(STDERR, "unsafe-runtime:environment={$environment};driver={$connection->getDriverName()};database={$database}\n");
    exit(5);
}
$decoded = json_decode((string) file_get_contents($payloadPath), true);
if (! is_array($decoded)) {
    fwrite(STDERR, "invalid-json-payload\n");
    exit(4);
}
/** @var array<string, bool|int|string|null> $payload */
$payload = array_filter($decoded, static fn (mixed $value): bool => is_bool($value) || is_int($value) || is_string($value) || $value === null);
$directory = dirname($barrier);
$ready = $directory.'/'.getmypid().'.ready';
$readyTemporary = $ready.'.tmp';
file_put_contents($readyTemporary, 'ready', LOCK_EX);
rename($readyTemporary, $ready);
$deadline = microtime(true) + 20;
while (! is_file($barrier) && microtime(true) < $deadline) {
    usleep(10_000);
}
if (! is_file($barrier)) {
    @unlink($ready);
    fwrite(STDERR, "barrier-timeout\n");
    exit(3);
}

try {
    $result = match ($scenario) {
        'shift_assignment' => $app->make(WorkforceManagementService::class)->execute(new ManageWorkforceResourceCommand('assignment', 'created', requiredInt($payload, 'company_id'), null, ['employee_id' => requiredInt($payload, 'employee_id'), 'work_shift_id' => requiredInt($payload, 'shift_id'), 'assignment_type' => 'fixed', 'effective_from' => requiredString($payload, 'effective_from'), 'effective_to' => requiredString($payload, 'effective_to')], requiredInt($payload, 'actor_id'))),
        'commerce_checkout' => checkoutPlace($app->make(CheckoutService::class), $payload),
        'attendance_workflow' => attendance($app->make(AttendanceWorkflowService::class), $payload),
        'leave_transition' => leaveTransition($app->make(LeaveWorkflowService::class), $payload),
        'leave_accrual' => $app->make(AutomatedLeaveLedgerService::class)->accrue(CarbonImmutable::parse(requiredString($payload, 'date'))),
        'overtime_workflow' => overtime($app->make(OvertimeManagementService::class), $payload),
        'notification_delivery' => notification($app->make(WorkforceNotificationDeliveryService::class), $payload),
        'notification_center_queue' => notificationCenterQueue($app->make(NotificationDeliveryService::class), $payload),
        'notification_center_delivery', 'notification_center_retry' => notificationCenterRetry($app->make(NotificationDeliveryService::class), $payload),
        'notification_center_read' => notificationCenterRead($app->make(NotificationReadStateService::class), $payload),
        'notification_center_read_all' => notificationCenterReadAll($app->make(NotificationReadStateService::class), $payload),
        'payroll_assignment_overlap' => payrollAssignment($app->make(PayrollConfigurationService::class), $payload),
        'payroll_create_same_period' => payrollCreate($app->make(PayrollRunService::class), $payload),
        'payroll_calculate', 'payroll_recalculate' => payrollCalculate($app->make(PayrollRunService::class), $payload),
        'payroll_submit' => payrollTransition($app->make(PayrollRunService::class), $payload, PayrollRunStatus::Submitted),
        'payroll_approve' => payrollTransition($app->make(PayrollRunService::class), $payload, PayrollRunStatus::Approved),
        'payroll_reject' => payrollTransition($app->make(PayrollRunService::class), $payload, PayrollRunStatus::Rejected),
        'payroll_finalize' => payrollTransition($app->make(PayrollRunService::class), $payload, PayrollRunStatus::Finalized),
        'payroll_notification_delivery' => notification($app->make(WorkforceNotificationDeliveryService::class), $payload),
        'payroll_payment_batch_create' => paymentBatchCreate($app->make(PayrollPaymentBatchService::class), $payload),
        'payroll_payment_batch_approve' => paymentBatchAction($app->make(PayrollPaymentBatchService::class), $payload, 'approve'),
        'payroll_payment_batch_reject' => paymentBatchAction($app->make(PayrollPaymentBatchService::class), $payload, 'reject'),
        'payroll_payment_batch_release' => paymentBatchAction($app->make(PayrollPaymentBatchService::class), $payload, 'release'),
        'payroll_accounting_journal_create' => accountingJournalCreate($app->make(PayrollAccountingJournalService::class), $payload),
        'payroll_accounting_journal_approve' => accountingJournalAction($app->make(PayrollAccountingJournalService::class), $payload, 'approve'),
        'payroll_accounting_journal_reject' => accountingJournalAction($app->make(PayrollAccountingJournalService::class), $payload, 'reject'),
        'payroll_adjustment_create' => adjustmentCreate($app->make(PayrollAdjustmentManagementService::class), $payload),
        'payroll_adjustment_process' => adjustmentProcess($app->make(PayrollAdjustmentManagementService::class), $payload),
        'payroll_adjustment_downstream' => adjustmentDownstream($app->make(PayrollAdjustmentDownstreamService::class), $payload),
        'annual_tax_create' => annualTaxCreate($app->make(PayrollAnnualTaxReconciliationService::class), $payload),
        'annual_tax_calculate' => annualTaxCalculate($app->make(PayrollAnnualTaxOperationsService::class), $payload),
        'annual_tax_finalize' => annualTaxFinalize($app->make(PayrollAnnualTaxLifecycleService::class), $payload),
        'annual_tax_statement_generate' => annualTaxDelivery($app->make(PayrollAnnualTaxDeliveryService::class), $payload, 'statement'),
        'annual_tax_payment_intent' => annualTaxDelivery($app->make(PayrollAnnualTaxDeliveryService::class), $payload, 'payment'),
        'annual_tax_settlement_journal' => annualTaxDelivery($app->make(PayrollAnnualTaxDeliveryService::class), $payload, 'accounting'),
        'annual_tax_downstream_retry' => annualTaxDelivery($app->make(PayrollAnnualTaxDeliveryService::class), $payload, 'retry'),
        'annual_tax_finalize_downstream' => annualTaxFinalizeDownstream($app->make(PayrollAnnualTaxLifecycleService::class), $app->make(PayrollAnnualTaxDeliveryService::class), $payload),
        'erp_export_create' => erpExport($app->make(ErpExportService::class), $payload, 'create'),
        'erp_export_execute' => erpExport($app->make(ErpExportService::class), $payload, 'execute'),
        'erp_export_retry' => erpExport($app->make(ErpExportService::class), $payload, 'retry'),
        'erp_export_reverse' => erpExport($app->make(ErpExportService::class), $payload, 'reverse'),
        'government_filing_create' => governmentFiling($app->make(GovernmentTaxFilingService::class), $payload, 'create'),
        'government_filing_generate' => governmentFiling($app->make(GovernmentTaxFilingService::class), $payload, 'generate'),
        'government_filing_validate' => governmentFiling($app->make(GovernmentTaxFilingService::class), $payload, 'validate'),
        'government_filing_approve' => governmentFiling($app->make(GovernmentTaxFilingService::class), $payload, 'approve'),
        'government_filing_submit' => governmentFiling($app->make(GovernmentTaxFilingService::class), $payload, 'submit'),
        'government_filing_retry' => governmentFiling($app->make(GovernmentTaxFilingService::class), $payload, 'retry'),
        'integration_queue' => integrationHub($app->make(IntegrationHubService::class), $payload, 'queue'),
        'integration_delivery' => integrationHub($app->make(IntegrationHubService::class), $payload, 'delivery'),
        'integration_retry' => integrationHub($app->make(IntegrationHubService::class), $payload, 'retry'),
        'integration_webhook' => integrationHub($app->make(IntegrationHubService::class), $payload, 'webhook'),
        'integration_replay' => integrationHub($app->make(IntegrationHubService::class), $payload, 'replay'),
        'audit_record' => auditRecord($app->make(AuditRecorderInterface::class), $payload),
        'audit_export' => auditExport($app->make(AuditExportService::class), $payload),
        'audit_retention' => auditRetention($app->make(AuditRetentionService::class), $payload),
        'audit_archive' => auditArchive($app->make(AuditRetentionService::class), $payload),
        'audit_source_workflow' => auditSource($app->make(WorkflowAuditConsumer::class), $payload),
        'employee_lifecycle' => employeeLifecycle($app->make(EmployeeLifecycleService::class), $payload),
        'talent_cycle_create' => talentCycleCreate($app->make(PerformanceCycleService::class), $payload),
        'talent_goal_assign' => talentGoalAssign($app->make(GoalManagementService::class), $payload),
        'talent_review_approve' => talentReviewApprove($app->make(PerformanceReviewService::class), $payload),
        'talent_cycle_close' => talentCycleClose($app->make(PerformanceCycleService::class), $payload),
        'career_path_create' => careerAction($app->make(CareerPathService::class), $payload, 'create'),
        'career_plan_assign' => careerAction($app->make(CareerPathService::class), $payload, 'assign'),
        'succession_create' => successionAction($app->make(SuccessionPlanningService::class), $payload, 'create'),
        'succession_nominate' => successionAction($app->make(SuccessionPlanningService::class), $payload, 'nominate'),
        'succession_approve' => successionAction($app->make(SuccessionPlanningService::class), $payload, 'approve'),
        'analytics_snapshot', 'analytics_refresh', 'analytics_rebuild' => analyticsSnapshot($app->make(WorkforceAnalyticsService::class), $payload),
        'governance_quality_run', 'governance_quality_retry' => governanceQualityRun($app->make(HRDataGovernanceService::class), $payload),
        'governance_issue_resolve' => governanceIssueResolve($app->make(HRDataGovernanceService::class), $payload),
        'governance_document_archive' => governanceDocumentArchive($app->make(HRDataGovernanceService::class), $payload),
        'workflow_start' => workflowStart($app->make(WorkflowExecutionService::class), $payload),
        'workflow_claim' => workflowClaim($app->make(WorkflowTaskService::class), $payload),
        'workflow_approve' => workflowDecision($app->make(WorkflowTaskService::class), $payload, 'approved'),
        'workflow_reject' => workflowDecision($app->make(WorkflowTaskService::class), $payload, 'rejected'),
        'workflow_cancel' => workflowCancel($app->make(WorkflowExecutionService::class), $payload),
        'workflow_escalate' => workflowEscalate($app->make(WorkflowTaskService::class), $payload),
        'iam_request_create' => iamRequestCreate($app->make(IamGovernanceService::class), $payload),
        'iam_request_approve' => iamTransition($app->make(IamGovernanceService::class), $payload, 'approve'),
        'iam_request_reject' => iamTransition($app->make(IamGovernanceService::class), $payload, 'reject'),
        'iam_request_provision' => iamProvision($app->make(IamGovernanceService::class), $payload),
        'iam_assignment_revoke' => iamRevoke($app->make(IamGovernanceService::class), $payload),
        'iam_review_decide' => iamReviewDecide($app->make(AccessReviewService::class), $payload),
        'iam_consume_workflow' => iamConsumer('iam:consume-workflow'),
        'iam_consume_employee' => iamConsumer('iam:consume-employee-lifecycle'),
        'iam_consume_notification' => iamConsumer('iam:consume-notifications'),
        default => throw new InvalidArgumentException('unsupported-scenario'),
    };
    fwrite(STDOUT, json_encode(['status' => 'committed', 'result' => $result], JSON_THROW_ON_ERROR)."\n");
    @unlink($ready);
    exit(0);
} catch (Throwable $throwable) {
    fwrite(STDOUT, json_encode(['status' => 'rejected', 'type' => $throwable::class, 'message' => $throwable->getMessage()], JSON_THROW_ON_ERROR)."\n");
    @unlink($ready);
    exit(2);
}

/** @param array<string,bool|int|string|null> $payload */
function checkoutPlace(CheckoutService $service, array $payload): int
{
    $user = User::query()->findOrFail(requiredInt($payload, 'user_id'));
    $ids = $service->place($user, requiredInt($payload, 'address_id'), 'standard', requiredString($payload, 'idempotency_key'));

    return $ids[0];
}

/** @param array<string,bool|int|string|null> $payload */
function iamRequestCreate(IamGovernanceService $service, array $payload): int
{
    return $service->create(requiredInt($payload, 'company_id'), requiredInt($payload, 'requester_id'), requiredInt($payload, 'subject_id'), requiredString($payload, 'reason'), requiredString($payload, 'idempotency_key'), [['type' => requiredString($payload, 'entitlement_type'), 'id' => requiredInt($payload, 'entitlement_id'), 'effective_to' => optionalString($payload, 'effective_to')]]);
}

/** @param array<string,bool|int|string|null> $payload */
function iamTransition(IamGovernanceService $service, array $payload, string $action): int
{
    $id = requiredInt($payload, 'request_id');
    $service->transition(requiredInt($payload, 'company_id'), $id, requiredInt($payload, 'actor_id'), $action, optionalString($payload, 'reason'));

    return $id;
}

/** @param array<string,bool|int|string|null> $payload */
function iamProvision(IamGovernanceService $service, array $payload): int
{
    $id = requiredInt($payload, 'request_id');
    $service->provision(requiredInt($payload, 'company_id'), $id, requiredInt($payload, 'actor_id'));

    return $id;
}

/** @param array<string,bool|int|string|null> $payload */
function iamRevoke(IamGovernanceService $service, array $payload): int
{
    $id = requiredInt($payload, 'assignment_id');
    $service->revoke(requiredInt($payload, 'company_id'), $id, requiredInt($payload, 'actor_id'));

    return $id;
}

/** @param array<string,bool|int|string|null> $payload */
function iamReviewDecide(AccessReviewService $service, array $payload): int
{
    $item = requiredInt($payload, 'item_id');
    $service->decide(requiredInt($payload, 'company_id'), requiredInt($payload, 'review_id'), $item, requiredInt($payload, 'actor_id'), requiredString($payload, 'decision'), requiredString($payload, 'reason'), requiredInt($payload, 'version'));

    return $item;
}

function iamConsumer(string $command): int
{
    return Artisan::call($command, ['--limit' => 100]);
}

/** @param array<string,bool|int|string|null> $payload */
function workflowStart(WorkflowExecutionService $service, array $payload): int
{
    return $service->start(new StartWorkflowData(
        requiredInt($payload, 'company_id'),
        requiredInt($payload, 'actor_id'),
        requiredInt($payload, 'definition_id'),
        requiredString($payload, 'subject_type'),
        requiredInt($payload, 'subject_id'),
        requiredString($payload, 'idempotency_key'),
    ));
}

/** @param array<string,bool|int|string|null> $payload */
function workflowClaim(WorkflowTaskService $service, array $payload): int
{
    return $service->claim(requiredInt($payload, 'company_id'), requiredInt($payload, 'task_id'), requiredInt($payload, 'actor_id'), requiredInt($payload, 'version'), requiredString($payload, 'idempotency_key'));
}

/** @param array<string,bool|int|string|null> $payload */
function workflowDecision(WorkflowTaskService $service, array $payload, string $decision): int
{
    return $service->decide(requiredInt($payload, 'company_id'), requiredInt($payload, 'task_id'), requiredInt($payload, 'actor_id'), $decision, optionalString($payload, 'reason'), requiredString($payload, 'idempotency_key'));
}

/** @param array<string,bool|int|string|null> $payload */
function workflowCancel(WorkflowExecutionService $service, array $payload): int
{
    $instanceId = requiredInt($payload, 'instance_id');
    $service->cancel(requiredInt($payload, 'company_id'), $instanceId, requiredInt($payload, 'actor_id'), requiredString($payload, 'idempotency_key'));

    return $instanceId;
}

/** @param array<string,bool|int|string|null> $payload */
function workflowEscalate(WorkflowTaskService $service, array $payload): int
{
    return $service->escalate(requiredInt($payload, 'company_id'), requiredInt($payload, 'task_id'), requiredInt($payload, 'actor_id'), requiredString($payload, 'idempotency_key'));
}

/** @param array<string,bool|int|string|null> $payload */
function talentCycleCreate(PerformanceCycleService $service, array $payload): int
{
    return $service->create(new PerformanceActionData(requiredInt($payload, 'company_id'), requiredInt($payload, 'actor_id'), requiredString($payload, 'idempotency_key'), name: requiredString($payload, 'name'), periodStart: CarbonImmutable::parse(requiredString($payload, 'period_start')), periodEnd: CarbonImmutable::parse(requiredString($payload, 'period_end'))));
}

/** @param array<string,bool|int|string|null> $payload */
function talentGoalAssign(GoalManagementService $service, array $payload): int
{
    return $service->assign(new PerformanceActionData(requiredInt($payload, 'company_id'), requiredInt($payload, 'actor_id'), requiredString($payload, 'idempotency_key'), cycleId: requiredInt($payload, 'cycle_id'), employeeId: requiredInt($payload, 'employee_id'), title: requiredString($payload, 'title'), value: requiredString($payload, 'weight')));
}

/** @param array<string,bool|int|string|null> $payload */
function talentReviewApprove(PerformanceReviewService $service, array $payload): int
{
    return $service->approve(new PerformanceActionData(requiredInt($payload, 'company_id'), requiredInt($payload, 'actor_id'), requiredString($payload, 'idempotency_key'), reviewId: requiredInt($payload, 'review_id')));
}

/** @param array<string,bool|int|string|null> $payload */
function talentCycleClose(PerformanceCycleService $service, array $payload): int
{
    return $service->close(new PerformanceActionData(requiredInt($payload, 'company_id'), requiredInt($payload, 'actor_id'), requiredString($payload, 'idempotency_key'), cycleId: requiredInt($payload, 'cycle_id')));
}

/** @param array<string,bool|int|string|null> $payload */
function careerAction(CareerPathService $service, array $payload, string $action): int
{
    $data = new CareerActionData(requiredInt($payload, 'company_id'), requiredInt($payload, 'actor_id'), requiredString($payload, 'idempotency_key'), careerPathId: isset($payload['career_path_id']) ? requiredInt($payload, 'career_path_id') : null, employeeId: isset($payload['employee_id']) ? requiredInt($payload, 'employee_id') : null, name: optionalString($payload, 'name'), targetStep: isset($payload['target_step']) ? requiredInt($payload, 'target_step') : null, steps: isset($payload['position_id']) ? [['position_id' => requiredInt($payload, 'position_id'), 'required_level' => 1, 'expected_duration_months' => null]] : []);

    return match ($action) {
        'create' => $service->create($data),'assign' => $service->assign($data),default => throw new InvalidArgumentException('unsupported-career-action')
    };
}

/** @param array<string,bool|int|string|null> $payload */
function successionAction(SuccessionPlanningService $service, array $payload, string $action): int
{
    $data = new CareerActionData(requiredInt($payload, 'company_id'), requiredInt($payload, 'actor_id'), requiredString($payload, 'idempotency_key'), successionPlanId: isset($payload['succession_plan_id']) ? requiredInt($payload, 'succession_plan_id') : null, employeeId: isset($payload['employee_id']) ? requiredInt($payload, 'employee_id') : null, positionId: isset($payload['position_id']) ? requiredInt($payload, 'position_id') : null, readinessLevel: optionalString($payload, 'readiness_level'));

    return match ($action) {
        'create' => $service->create($data),'nominate' => $service->nominate($data),'approve' => $service->approve($data),default => throw new InvalidArgumentException('unsupported-succession-action')
    };
}

/** @param array<string,bool|int|string|null> $payload */
function analyticsSnapshot(WorkforceAnalyticsService $service, array $payload): int
{
    return $service->snapshot(requiredInt($payload, 'company_id'), requiredInt($payload, 'actor_id'), requiredString($payload, 'idempotency_key'), requiredString($payload, 'date'));
}

/** @param array<string,bool|int|string|null> $payload */
function governanceQualityRun(HRDataGovernanceService $service, array $payload): int
{
    return $service->run(requiredInt($payload, 'company_id'), requiredInt($payload, 'actor_id'), requiredString($payload, 'idempotency_key'), requiredString($payload, 'date'));
}

/** @param array<string,bool|int|string|null> $payload */
function governanceIssueResolve(HRDataGovernanceService $service, array $payload): int
{
    return $service->resolveIssue(requiredInt($payload, 'company_id'), requiredInt($payload, 'issue_id'), requiredInt($payload, 'actor_id'), requiredString($payload, 'idempotency_key'), requiredString($payload, 'reason'));
}

/** @param array<string,bool|int|string|null> $payload */
function governanceDocumentArchive(HRDataGovernanceService $service, array $payload): int
{
    return $service->archiveDocument(requiredInt($payload, 'company_id'), requiredInt($payload, 'document_id'), requiredInt($payload, 'actor_id'), requiredString($payload, 'idempotency_key'), optionalString($payload, 'retention_date'));
}

/** @param array<string,bool|int|string|null> $payload */
function attendance(AttendanceWorkflowService $service, array $payload): int
{
    $action = requiredString($payload, 'action');
    if (! in_array($action, ['adjustment.submit', 'adjustment.approve', 'adjustment.reject', 'adjustment.cancel', 'attendance.approve', 'attendance.reject', 'period.lock'], true)) {
        throw new InvalidArgumentException('unsupported-attendance-action');
    }

    return $service->execute(new ProcessAttendanceWorkflowCommand($action, requiredInt($payload, 'company_id'), requiredInt($payload, 'subject_id'), requiredInt($payload, 'actor_id'), requiredInt($payload, 'version'), ['comment' => optionalString($payload, 'comment'), 'reason' => optionalString($payload, 'reason')]));
}

/** @param array<string,bool|int|string|null> $payload */
function leaveTransition(LeaveWorkflowService $service, array $payload): int
{
    $target = LeaveRequestStatus::from(requiredString($payload, 'target'));
    if (! in_array($target, [LeaveRequestStatus::SUBMITTED, LeaveRequestStatus::APPROVED, LeaveRequestStatus::REJECTED, LeaveRequestStatus::CANCELLED, LeaveRequestStatus::WITHDRAWN], true)) {
        throw new InvalidArgumentException('unsupported-leave-transition');
    }

    return (int) $service->transition(requiredInt($payload, 'company_id'), requiredInt($payload, 'subject_id'), $target, requiredInt($payload, 'version'), requiredInt($payload, 'actor_id'), optionalString($payload, 'comment'))->id;
}

/** @param array<string,bool|int|string|null> $payload */
function overtime(OvertimeManagementService $service, array $payload): int
{
    $action = requiredString($payload, 'action');
    if (! in_array($action, ['request.submit', 'request.approve', 'request.reject', 'request.cancel', 'request.reconcile'], true)) {
        throw new InvalidArgumentException('unsupported-overtime-action');
    }

    return $service->execute(new ManageOvertimeCommand($action, requiredInt($payload, 'company_id'), requiredInt($payload, 'subject_id'), requiredInt($payload, 'actor_id'), requiredInt($payload, 'version'), requiredString($payload, 'idempotency_key'), ['comment' => optionalString($payload, 'comment')]));
}

/** @param array<string,bool|int|string|null> $payload */
function notification(WorkforceNotificationDeliveryService $service, array $payload): int
{
    $recipient = User::query()->findOrFail(requiredInt($payload, 'recipient_id'));
    $service->deliver($recipient, requiredString($payload, 'notification_type'), ['company_id' => requiredInt($payload, 'company_id'), 'entity_type' => requiredString($payload, 'entity_type'), 'entity_id' => requiredInt($payload, 'entity_id'), 'employee_id' => null, 'action' => requiredString($payload, 'action'), 'safe_title' => 'Cập nhật workforce', 'safe_message' => 'Trạng thái đã thay đổi.', 'version' => requiredInt($payload, 'version'), 'timestamp' => now()->toIso8601String(), 'url' => null]);

    return 1;
}

/** @param array<string,bool|int|string|null> $payload */
function payrollAssignment(PayrollConfigurationService $service, array $payload): int
{
    return (int) $service->assign(requiredInt($payload, 'company_id'), [
        'employee_id' => requiredInt($payload, 'employee_id'),
        'salary_component_id' => requiredInt($payload, 'component_id'),
        'amount_minor' => requiredInt($payload, 'amount_minor'),
        'currency_code' => requiredString($payload, 'currency_code'),
        'percentage_basis_points' => null,
        'quantity_basis_points' => null,
        'rate_minor' => null,
        'effective_from' => requiredString($payload, 'effective_from'),
        'effective_to' => optionalString($payload, 'effective_to'),
        'source' => 'manual',
        'status' => 'active',
        'version' => 1,
        'idempotency_key' => requiredString($payload, 'idempotency_key'),
    ], requiredInt($payload, 'actor_id'))->id;
}

/** @param array<string,bool|int|string|null> $payload */
function payrollCreate(PayrollRunService $service, array $payload): int
{
    return (int) $service->create(requiredInt($payload, 'company_id'), requiredInt($payload, 'period_id'), requiredInt($payload, 'snapshot_id'), requiredString($payload, 'run_number'), requiredString($payload, 'currency_code'), requiredString($payload, 'idempotency_key'), requiredInt($payload, 'actor_id'))->id;
}

/** @param array<string,bool|int|string|null> $payload */
function payrollCalculate(PayrollRunService $service, array $payload): int
{
    return (int) $service->calculate(requiredInt($payload, 'company_id'), requiredInt($payload, 'run_id'), requiredInt($payload, 'expected_version'), requiredString($payload, 'idempotency_key'), requiredInt($payload, 'actor_id'))->id;
}

/** @param array<string,bool|int|string|null> $payload */
function payrollTransition(PayrollRunService $service, array $payload, PayrollRunStatus $target): int
{
    return (int) $service->transition(requiredInt($payload, 'company_id'), requiredInt($payload, 'run_id'), $target, requiredInt($payload, 'expected_version'), requiredString($payload, 'idempotency_key'), requiredInt($payload, 'actor_id'), optionalString($payload, 'reason'))->id;
}

/** @param array<string,bool|int|string|null> $payload */
function paymentBatchCreate(PayrollPaymentBatchService $service, array $payload): int
{
    return $service->create(
        requiredInt($payload, 'company_id'),
        requiredInt($payload, 'run_id'),
        requiredInt($payload, 'actor_id'),
        requiredString($payload, 'idempotency_key'),
    );
}

/** @param array<string,bool|int|string|null> $payload */
function paymentBatchAction(PayrollPaymentBatchService $service, array $payload, string $action): int
{
    $companyId = requiredInt($payload, 'company_id');
    $batchId = requiredInt($payload, 'batch_id');
    $actorId = requiredInt($payload, 'actor_id');
    match ($action) {
        'approve' => $service->approve($companyId, $batchId, $actorId),
        'reject' => $service->reject($companyId, $batchId, $actorId, requiredString($payload, 'reason')),
        'release' => $service->release($companyId, $batchId, $actorId),
        default => throw new InvalidArgumentException('unsupported-payment-batch-action'),
    };

    return $batchId;
}

/** @param array<string,bool|int|string|null> $payload */
function accountingJournalCreate(PayrollAccountingJournalService $service, array $payload): int
{
    return $service->create(requiredInt($payload, 'company_id'), requiredInt($payload, 'run_id'), requiredString($payload, 'posting_date'), requiredInt($payload, 'actor_id'), requiredString($payload, 'idempotency_key'));
}

/** @param array<string,bool|int|string|null> $payload */
function accountingJournalAction(PayrollAccountingJournalService $service, array $payload, string $action): int
{
    $company = requiredInt($payload, 'company_id');
    $journal = requiredInt($payload, 'journal_id');
    $actor = requiredInt($payload, 'actor_id');
    match ($action) {
        'approve' => $service->approve($company, $journal, $actor),
        'reject' => $service->reject($company, $journal, $actor, requiredString($payload, 'reason')),
        default => throw new InvalidArgumentException('unsupported-accounting-journal-action'),
    };

    return $journal;
}

/** @param array<string,bool|int|string|null> $payload */
function adjustmentCreate(PayrollAdjustmentManagementService $service, array $payload): int
{
    return $service->create(new CreatePayrollAdjustmentCommand(requiredInt($payload, 'company_id'), requiredInt($payload, 'item_id'), requiredInt($payload, 'reason_id'), 'earning_correction', requiredString($payload, 'effective_date'), null, 'Concurrent controlled correction', null, requiredString($payload, 'idempotency_key'), requiredInt($payload, 'actor_id')));
}

/** @param array<string,bool|int|string|null> $payload */
function adjustmentProcess(PayrollAdjustmentManagementService $service, array $payload): int
{
    $service->process(new ProcessPayrollAdjustmentCommand(
        requiredInt($payload, 'company_id'),
        requiredInt($payload, 'adjustment_id'),
        requiredString($payload, 'action'),
        requiredInt($payload, 'actor_id'),
        optionalString($payload, 'reason'),
    ));

    return requiredInt($payload, 'adjustment_id');
}

/** @param array<string,bool|int|string|null> $payload */
function adjustmentDownstream(PayrollAdjustmentDownstreamService $service, array $payload): int
{
    $service->execute(
        requiredInt($payload, 'company_id'),
        requiredInt($payload, 'adjustment_id'),
        requiredInt($payload, 'actor_id'),
        PayrollAdjustmentDownstreamType::from(requiredString($payload, 'type')),
    );

    return requiredInt($payload, 'adjustment_id');
}

/** @param array<string,bool|int|string|null> $payload */
function governmentFiling(GovernmentTaxFilingService $service, array $payload, string $action): int
{
    $company = requiredInt($payload, 'company_id');
    $actor = requiredInt($payload, 'actor_id');

    return match ($action) {
        'create' => $service->create($company, requiredInt($payload, 'reconciliation_id'), 'annual_pit_employer_declaration', $actor, requiredString($payload, 'idempotency_key'))->filingId,
        'generate' => $service->generate($company, requiredInt($payload, 'filing_id'), $actor)->filingId,
        'validate' => $service->validate($company, requiredInt($payload, 'filing_id'), $actor)->filingId,
        'approve' => $service->approve($company, requiredInt($payload, 'filing_id'), $actor)->filingId,
        'submit', 'retry' => $service->submit($company, requiredInt($payload, 'filing_id'), $actor, requiredString($payload, 'idempotency_key'))->filingId,
        default => throw new InvalidArgumentException('unsupported-government-filing-action'),
    };
}

/** @param array<string,bool|int|string|null> $payload */
function requiredInt(array $payload, string $key): int
{
    $value = $payload[$key] ?? null;
    if (! is_int($value) || $value <= 0) {
        throw new InvalidArgumentException("invalid-{$key}");
    }

    return $value;
}

/** @param array<string,bool|int|string|null> $payload */
function auditRecord(AuditRecorderInterface $service, array $payload): int
{
    return $service->record(new AuditEventData(requiredInt($payload, 'company_id'), 'system', null, 'updated', 'Workflow', 'WorkflowUpdated', 'workflow_instance', requiredString($payload, 'resource_id'), [], ['status' => 'updated'], [], requiredString($payload, 'idempotency_key')));
}

/** @param array<string,bool|int|string|null> $payload */
function auditExport(AuditExportService $service, array $payload): int
{
    return $service->create(new CreateAuditExportCommand(requiredInt($payload, 'company_id'), requiredInt($payload, 'actor_id'), 'csv', [], requiredString($payload, 'idempotency_key')));
}

/** @param array<string,bool|int|string|null> $payload */
function auditRetention(AuditRetentionService $service, array $payload): int
{
    return $service->apply(new ApplyRetentionPolicyCommand(requiredInt($payload, 'company_id'), requiredInt($payload, 'actor_id'), requiredString($payload, 'name'), 2555));
}

/** @param array<string,bool|int|string|null> $payload */
function auditArchive(AuditRetentionService $service, array $payload): int
{
    return $service->archive(new ArchiveAuditRangeCommand(requiredInt($payload, 'company_id'), requiredInt($payload, 'actor_id'), requiredString($payload, 'range_start'), requiredString($payload, 'range_end'), requiredString($payload, 'idempotency_key')));
}

/** @param array<string,bool|int|string|null> $payload */
function auditSource(WorkflowAuditConsumer $consumer, array $payload): int
{
    return $consumer->consume(requiredInt($payload, 'source_id')) ?? 0;
}

/** @param array<string,bool|int|string|null> $payload */
function requiredString(array $payload, string $key): string
{
    $value = $payload[$key] ?? null;
    if (! is_string($value) || trim($value) === '') {
        throw new InvalidArgumentException("invalid-{$key}");
    }

    return $value;
}

/** @param array<string,bool|int|string|null> $payload */
function notificationCenterQueue(NotificationDeliveryService $service, array $payload): int
{
    return $service->queue(new QueueNotificationData(requiredInt($payload, 'company_id'), null, 'workflow', 'workflow_instance', requiredInt($payload, 'entity_id'), 'assigned', 'Workflow assigned', 'A workflow item requires attention.', null, ['workflow_event' => 'assigned'], [requiredInt($payload, 'recipient_id')], ['database', 'broadcast'], requiredString($payload, 'idempotency_key'), null));
}

/** @param array<string,bool|int|string|null> $payload */
function notificationCenterRetry(NotificationDeliveryService $service, array $payload): int
{
    $service->retry(requiredInt($payload, 'company_id'), requiredInt($payload, 'delivery_id'));

    return requiredInt($payload, 'delivery_id');
}

/** @param array<string,bool|int|string|null> $payload */
function notificationCenterRead(NotificationReadStateService $service, array $payload): int
{
    $service->mark(requiredInt($payload, 'company_id'), requiredInt($payload, 'recipient_id'), requiredInt($payload, 'message_id'));

    return requiredInt($payload, 'message_id');
}

/** @param array<string,bool|int|string|null> $payload */
function notificationCenterReadAll(NotificationReadStateService $service, array $payload): int
{
    return $service->markAll(requiredInt($payload, 'company_id'), requiredInt($payload, 'recipient_id'));
}

/** @param array<string,bool|int|string|null> $payload */
function optionalString(array $payload, string $key): ?string
{
    $value = $payload[$key] ?? null;

    return is_string($value) && $value !== '' ? $value : null;
}

/** @param array<string,bool|int|string|null> $payload */
function employeeLifecycle(EmployeeLifecycleService $service, array $payload): int
{
    $action = requiredString($payload, 'action');
    $data = new LifecycleTransitionData(requiredInt($payload, 'company_id'), requiredInt($payload, 'employee_id'), requiredInt($payload, 'actor_id'), requiredString($payload, 'idempotency_key'), CarbonImmutable::parse(requiredString($payload, 'effective_at')), optionalString($payload, 'reason'), $action === 'transfer' ? requiredInt($payload, 'current_assignment_id') : null, $action === 'transfer' ? requiredInt($payload, 'organization_unit_id') : null, $action === 'transfer' ? requiredInt($payload, 'position_id') : null);
    $event = match ($action) {
        'create' => $service->create($data),
        'onboarding' => $service->startOnboarding($data),
        'transfer' => $service->changeAssignment($data),
        'terminate' => $service->terminate($data),
        default => throw new InvalidArgumentException('unsupported-employee-lifecycle-action'),
    };

    return (int) $event->id;
}

/** @param array<string,bool|int|string|null> $payload */
function annualTaxCreate(PayrollAnnualTaxReconciliationService $service, array $payload): int
{
    return $service->create(new CreateAnnualTaxReconciliationCommand(requiredInt($payload, 'company_id'), requiredInt($payload, 'employee_id'), requiredInt($payload, 'tax_year'), 'VN', 'VND', requiredString($payload, 'idempotency_key'), requiredInt($payload, 'actor_id')))->id;
}
/** @param array<string,bool|int|string|null> $payload */
function annualTaxCalculate(PayrollAnnualTaxOperationsService $service, array $payload): int
{
    return $service->calculate(requiredInt($payload, 'company_id'), requiredInt($payload, 'reconciliation_id'), requiredInt($payload, 'actor_id'))->reconciliationId;
}
/** @param array<string,bool|int|string|null> $payload */
function annualTaxFinalize(PayrollAnnualTaxLifecycleService $service, array $payload): int
{
    return $service->transition(requiredInt($payload, 'company_id'), requiredInt($payload, 'reconciliation_id'), 'finalize', requiredInt($payload, 'actor_id'))->reconciliationId;
}

/** @param array<string,bool|int|string|null> $payload */
function annualTaxDelivery(PayrollAnnualTaxDeliveryService $service, array $payload, string $action): int
{
    $company = requiredInt($payload, 'company_id');
    $id = requiredInt($payload, 'reconciliation_id');
    $actor = requiredInt($payload, 'actor_id');

    return match ($action) {
        'statement' => $service->generateStatement($company, $id, $actor)->id,
        'payment' => $service->paymentIntent($company, $id, $actor)->reconciliationId,
        'accounting' => $service->settlementJournal($company, $id, $actor)->resourceId ?? 0,
        'retry' => count($service->retry($company, $id, $actor)),
        default => throw new InvalidArgumentException('unsupported-annual-tax-delivery-action'),
    };
}

/** @param array<string,bool|int|string|null> $payload */
function annualTaxFinalizeDownstream(PayrollAnnualTaxLifecycleService $lifecycle, PayrollAnnualTaxDeliveryService $delivery, array $payload): int
{
    $company = requiredInt($payload, 'company_id');
    $id = requiredInt($payload, 'reconciliation_id');
    $actor = requiredInt($payload, 'actor_id');
    $lifecycle->transition($company, $id, 'finalize', $actor);
    $delivery->retry($company, $id, $actor);

    return $id;
}

/** @param array<string,bool|int|string|null> $payload */
function erpExport(ErpExportService $service, array $payload, string $action): int
{
    $company = requiredInt($payload, 'company_id');
    $actor = requiredInt($payload, 'actor_id');

    return match ($action) {
        'create' => $service->create($company, requiredInt($payload, 'connection_id'), requiredInt($payload, 'journal_id'), $actor, requiredString($payload, 'idempotency_key'))->exportId,
        'execute' => $service->execute($company, requiredInt($payload, 'export_id'), $actor)->exportId,
        'retry' => $service->retry($company, requiredInt($payload, 'export_id'), $actor)->exportId,
        'reverse' => $service->reverse($company, requiredInt($payload, 'export_id'), $actor)->exportId,
        default => throw new InvalidArgumentException('unsupported-erp-action'),
    };
}

/** @param array<string,bool|int|string|null> $payload */
function integrationHub(IntegrationHubService $service, array $payload, string $action): int
{
    $company = requiredInt($payload, 'company_id');
    $actor = requiredInt($payload, 'actor_id');

    return match ($action) {
        'queue' => $service->queue($company, requiredInt($payload, 'connection_id'), $actor, 'race_fixture', 1, 'race.event', ['token' => requiredString($payload, 'token')], requiredString($payload, 'idempotency_key')),
        'delivery' => integrationDelivery($service, $company, requiredInt($payload, 'message_id')),
        'retry' => integrationRetry($service, $company, requiredInt($payload, 'message_id'), $actor),
        'webhook' => $service->receiveWebhook($company, requiredInt($payload, 'connection_id'), 'race-provider', requiredString($payload, 'event_id'), 'race.event', ['token' => requiredString($payload, 'token')], requiredString($payload, 'signature')),
        'replay' => integrationReplay($service, $company, requiredInt($payload, 'dead_letter_id'), $actor, requiredString($payload, 'idempotency_key')),
        default => throw new InvalidArgumentException('unsupported-integration-action'),
    };
}

function integrationDelivery(IntegrationHubService $service, int $companyId, int $messageId): int
{
    $service->deliver($companyId, $messageId);

    return $messageId;
}
function integrationRetry(IntegrationHubService $service, int $companyId, int $messageId, int $actorId): int
{
    $service->retry($companyId, $messageId, $actorId);

    return $messageId;
}
function integrationReplay(IntegrationHubService $service, int $companyId, int $deadLetterId, int $actorId, string $key): int
{
    $service->replay($companyId, $deadLetterId, $actorId, $key);

    return $deadLetterId;
}
