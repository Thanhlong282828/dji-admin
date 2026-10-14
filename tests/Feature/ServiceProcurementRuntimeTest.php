<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\DataQuality\DataQualityAuditService;
use Carbon\CarbonImmutable;
use Illuminate\Database\RecordNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Modules\Core\Application\Buses\CommandBusInterface;
use Modules\Document\Application\Services\EnterpriseDocumentService;
use Modules\Finance\Application\Commands\CreateFxRevaluationRunCommand;
use Modules\Finance\Application\Commands\CreateFxRevaluationRunHandler;
use Modules\Finance\Application\Services\AccountingPostingPolicyService;
use Modules\Finance\Application\Services\AccountsPayablePlanningReadService;
use Modules\Finance\Application\Services\ApprovalInboxService;
use Modules\Finance\Application\Services\CostCenterCostManagementReadService;
use Modules\Finance\Application\Services\FxRateService;
use Modules\Finance\Application\Services\SourceAccountingPostingService;
use Modules\Finance\Application\Services\TreasuryService;
use Modules\Procurement\Application\Commands\RequestServiceAcceptanceReversalCommand;
use Modules\Procurement\Application\Commands\SubmitServiceAcceptanceCommand;
use Modules\Procurement\Application\Services\ApFxRevaluationSourceService;
use Modules\Procurement\Application\Services\ProcurementExceptionReadService;
use Modules\Procurement\Application\Services\ProcurementReconciliationReadService;
use Modules\Procurement\Application\Services\ProcurementService;
use Modules\Procurement\Application\Services\ServiceAcceptanceService;
use Modules\Procurement\Application\Services\ServiceAcceptanceWorkflowOrchestrator;
use Modules\Procurement\Application\Services\VendorBillReversalService;
use Modules\Procurement\Application\Services\VendorPerformanceReadService;
use Modules\Workflow\Application\DTOs\WorkflowDefinitionData;
use Modules\Workflow\Domain\Services\WorkflowDefinitionService;
use Modules\Workflow\Domain\Services\WorkflowTaskService;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Tests\TestCase;

final class ServiceProcurementRuntimeTest extends TestCase
{
    use RefreshDatabase;

    public function test_foreign_supplier_invoice_recognition_uses_direct_spot_snapshot_and_functional_journal(): void
    {
        $f = $this->fixture('USD');
        $rates = app(FxRateService::class);
        $rate = $rates->create($f['company'], $f['maker'], [
            'base_currency' => 'USD', 'quote_currency' => 'VND', 'rate_decimal' => '24690.500000000000',
            'rate_date' => '2026-09-12', 'rate_type' => 'spot', 'source_reference' => 'AP-FX-USD-VND',
            'effective_from' => '2026-09-12', 'effective_to' => '2026-09-12', 'idempotency_key' => (string) Str::uuid(),
        ]);
        $rates->transition($f['company'], $rate, 'submit', $f['maker']);
        $rates->transition($f['company'], $rate, 'approve', $f['checker']);
        $rates->transition($f['company'], $rate, 'activate', $f['checker']);

        $acceptance = $this->accept($f, 'SA-AP-FX', 1);
        $acceptanceLine = DB::table('service_acceptance_lines')->where('service_acceptance_id', $acceptance)->firstOrFail();
        $bill = $this->procurement()->mutate('bill.create', $f['company'], $f['maker'], [
            'purchase_order_id' => $f['po'], 'purchase_order_line_id' => $f['po_line'],
            'service_acceptance_line_id' => (int) $acceptanceLine->id, 'quantity' => 1,
            'invoice_number' => 'INV-AP-FX-USD', 'invoice_date' => '2026-09-12', 'due_date' => '2026-10-12',
        ]);
        self::assertSame($bill, $this->procurement()->mutate('bill.approve', $f['company'], $f['checker'], ['id' => $bill]));
        self::assertSame($bill, $this->procurement()->mutate('bill.approve', $f['company'], $f['checker'], ['id' => $bill]));

        $evidence = DB::table('supplier_invoice_fx_recognitions')->where('vendor_bill_id', $bill)->firstOrFail();
        self::assertSame('USD', $evidence->transaction_currency);
        self::assertSame(1_000_000, (int) $evidence->transaction_amount_minor);
        self::assertSame('VND', $evidence->functional_currency);
        self::assertSame(246_905_000, (int) $evidence->functional_amount_minor);
        self::assertSame('24690.500000000000', $evidence->rate_decimal);
        self::assertSame('spot', $evidence->rate_type);
        self::assertNotNull($evidence->fx_rate_snapshot_id);
        self::assertSame(1, DB::table('supplier_invoice_fx_recognitions')->where('vendor_bill_id', $bill)->count());
        self::assertSame(1, DB::table('payroll_accounting_journals')->where(['source_type' => 'vendor_bill', 'source_id' => $bill])->count());
        $journal = DB::table('payroll_accounting_journals')->where('id', $evidence->accounting_journal_id)->firstOrFail();
        self::assertSame('VND', $journal->currency);
        self::assertSame(246_905_000, (int) $journal->total_debit_minor);
        self::assertSame((int) $journal->total_debit_minor, (int) $journal->total_credit_minor);
        self::assertSame(2, DB::table('finance_fx_rate_snapshots')->where('fx_rate_id', $rate)->count());

        $openAp = app(ApFxRevaluationSourceService::class)->eligible($f['company'], $f['branch'], '2026-09-12');
        self::assertCount(1, $openAp);
        self::assertSame('vendor_bill', $openAp[0]->sourceType);
        self::assertSame($bill, $openAp[0]->sourceId);
        self::assertSame('liability', $openAp[0]->balanceNature);
        self::assertSame(1_000_000, $openAp[0]->foreignOutstandingMinor);
        self::assertSame(246_905_000, $openAp[0]->carryingFunctionalMinor);
        self::assertSame([], $openAp[0]->evidence['settlements']);

        $closingRate = $rates->create($f['company'], $f['maker'], [
            'base_currency' => 'USD', 'quote_currency' => 'VND', 'rate_decimal' => '25000.000000000000',
            'rate_date' => '2026-09-30', 'rate_type' => 'closing', 'source_reference' => 'AP-FX-CLOSING-SEP',
            'effective_from' => '2026-09-30', 'effective_to' => '2026-09-30', 'idempotency_key' => (string) Str::uuid(),
        ]);
        $rates->transition($f['company'], $closingRate, 'submit', $f['maker']);
        $rates->transition($f['company'], $closingRate, 'approve', $f['checker']);
        $rates->transition($f['company'], $closingRate, 'activate', $f['checker']);
        $period = (int) DB::table('accounting_periods')->where(['company_id' => $f['company'], 'year' => 2026, 'month' => 9])->value('id');
        $run = app(CreateFxRevaluationRunHandler::class)->handle(new CreateFxRevaluationRunCommand(
            $f['company'], $f['branch'], $period, '2026-09-30', (string) Str::uuid(), $f['maker'], [],
        ));
        $revaluationLine = DB::table('finance_fx_revaluation_lines')->where(['fx_revaluation_run_id' => $run, 'source_type' => 'vendor_bill', 'source_id' => $bill])->firstOrFail();
        self::assertSame(250_000_000, (int) $revaluationLine->revalued_functional_minor);
        self::assertSame(3_095_000, (int) $revaluationLine->fx_difference_minor);
        $revaluationEvidence = json_decode((string) $revaluationLine->mapping_snapshot, true, 512, JSON_THROW_ON_ERROR);
        self::assertSame('liability', $revaluationEvidence['balance_nature']);
        self::assertSame('2026-09-30', $revaluationEvidence['source_evidence']['valuation_date']);

        $settlementRate = $rates->create($f['company'], $f['maker'], ['base_currency' => 'USD', 'quote_currency' => 'VND', 'rate_decimal' => '25000.000000000000', 'rate_date' => '2026-09-13', 'rate_type' => 'spot', 'source_reference' => 'AP-PAY-USD-VND', 'effective_from' => '2026-09-13', 'effective_to' => '2026-09-13', 'idempotency_key' => (string) Str::uuid()]);
        $rates->transition($f['company'], $settlementRate, 'submit', $f['maker']);
        $rates->transition($f['company'], $settlementRate, 'approve', $f['checker']);
        $rates->transition($f['company'], $settlementRate, 'activate', $f['checker']);
        $usdLedger = (int) DB::table('accounting_accounts')->insertGetId(['uuid' => (string) Str::uuid(), 'company_id' => $f['company'], 'code' => '1101', 'name' => 'USD Bank', 'account_type' => 'asset', 'normal_balance' => 'debit', 'currency' => 'USD', 'allow_posting' => true, 'status' => 'active', 'effective_from' => '2026-01-01', 'created_by' => $f['maker']->id, 'created_at' => now(), 'updated_at' => now()]);
        $usdAccount = (int) DB::table('treasury_financial_accounts')->insertGetId(['company_id' => $f['company'], 'branch_id' => $f['branch'], 'accounting_account_id' => $usdLedger, 'code' => 'USD-BANK', 'name' => 'USD Bank', 'type' => 'bank', 'currency' => 'USD', 'status' => 'active', 'created_by' => $f['maker']->id, 'created_at' => now(), 'updated_at' => now()]);
        $this->assertValidation(fn () => $this->procurement()->mutate('payment.allocate', $f['company'], $f['maker'], ['vendor_bill_id' => $bill, 'financial_account_id' => $usdAccount, 'payment_date' => '2026-09-11', 'amount_minor' => 1, 'method' => 'bank_transfer', 'reference' => 'PAY-AP-FX-NO-RATE']), 'rate', 'FX_RATE_NOT_AVAILABLE');
        self::assertSame(0, DB::table('ap_payments')->where('reference', 'PAY-AP-FX-NO-RATE')->count());
        $posting = app(SourceAccountingPostingService::class);
        $ap = $posting->accountEvidence($f['company'], '2100', 'VND', $f['maker']->id)['id'];
        $cash = $posting->accountEvidence($f['company'], '1100', 'VND', $f['maker']->id)['id'];
        $loss = (int) DB::table('accounting_accounts')->insertGetId(['uuid' => (string) Str::uuid(), 'company_id' => $f['company'], 'code' => '5498', 'name' => 'Realized FX Loss', 'account_type' => 'expense', 'normal_balance' => 'debit', 'currency' => 'VND', 'allow_posting' => true, 'status' => 'active', 'effective_from' => '2026-01-01', 'created_by' => $f['maker']->id, 'created_at' => now(), 'updated_at' => now()]);
        $policyService = app(AccountingPostingPolicyService::class);
        $lossPolicy = $policyService->create($f['company'], $f['maker'], ['policy_key' => 'VENDOR-PAYMENT-FX-LOSS', 'source_type' => 'vendor_payment', 'business_event' => 'VENDOR_PAYMENT_FX_LOSS', 'classification' => null, 'version' => 1, 'currency' => 'VND', 'effective_from' => '2026-01-01', 'lines' => [['entry_side' => 'debit', 'line_role' => 'AP', 'account_id' => $ap], ['entry_side' => 'debit', 'line_role' => 'FX_LOSS', 'account_id' => $loss], ['entry_side' => 'credit', 'line_role' => 'CASH', 'account_id' => $cash]]]);
        $policyService->submit($f['company'], $lossPolicy, $f['maker']);
        $policyService->approve($f['company'], $lossPolicy, $f['checker']);
        $vndAccount = app(TreasuryService::class)->canonicalPaymentAccountEvidence($f['company'], $f['branch'], $f['maker']->id, '2026-09-13')['id'];
        $this->assertValidation(fn () => $this->procurement()->mutate('payment.allocate', $f['company'], $f['maker'], ['vendor_bill_id' => $bill, 'financial_account_id' => $vndAccount, 'payment_date' => '2026-09-13', 'amount_minor' => 1, 'method' => 'bank_transfer', 'reference' => 'PAY-AP-FX-WRONG-ACCOUNT']), 'financial_account_id', 'AP_PAYMENT_TREASURY_CURRENCY_MISMATCH');
        $payment = $this->procurement()->mutate('payment.allocate', $f['company'], $f['maker'], ['vendor_bill_id' => $bill, 'financial_account_id' => $usdAccount, 'payment_date' => '2026-09-13', 'amount_minor' => 500_000, 'method' => 'bank_transfer', 'reference' => 'PAY-AP-FX-1']);
        $settlement = DB::table('supplier_payment_fx_settlements')->where('ap_payment_id', $payment)->firstOrFail();
        self::assertSame(125_000_000, (int) $settlement->settlement_functional_minor);
        self::assertSame(123_452_500, (int) $settlement->released_carrying_minor);
        self::assertSame(-1_547_500, (int) $settlement->realized_fx_minor);
        self::assertSame(1, DB::table('treasury_transactions')->where(['source_type' => 'ap_payment', 'source_id' => $payment])->count());
        self::assertSame(1, DB::table('payroll_accounting_journals')->where(['source_type' => 'ap_payment', 'source_id' => $payment])->count());
        $partiallyOpenAp = app(ApFxRevaluationSourceService::class)->eligible($f['company'], $f['branch'], '2026-09-13');
        self::assertCount(1, $partiallyOpenAp);
        self::assertSame(500_000, $partiallyOpenAp[0]->foreignOutstandingMinor);
        self::assertSame(123_452_500, $partiallyOpenAp[0]->carryingFunctionalMinor);
        self::assertCount(1, $partiallyOpenAp[0]->evidence['settlements']);

        $gain = (int) DB::table('accounting_accounts')->insertGetId(['uuid' => (string) Str::uuid(), 'company_id' => $f['company'], 'code' => '4498', 'name' => 'Realized FX Gain', 'account_type' => 'revenue', 'normal_balance' => 'credit', 'currency' => 'VND', 'allow_posting' => true, 'status' => 'active', 'effective_from' => '2026-01-01', 'created_by' => $f['maker']->id, 'created_at' => now(), 'updated_at' => now()]);
        $gainPolicy = $policyService->create($f['company'], $f['maker'], ['policy_key' => 'VENDOR-PAYMENT-FX-GAIN', 'source_type' => 'vendor_payment', 'business_event' => 'VENDOR_PAYMENT_FX_GAIN', 'classification' => null, 'version' => 1, 'currency' => 'VND', 'effective_from' => '2026-01-01', 'lines' => [['entry_side' => 'debit', 'line_role' => 'AP', 'account_id' => $ap], ['entry_side' => 'credit', 'line_role' => 'CASH', 'account_id' => $cash], ['entry_side' => 'credit', 'line_role' => 'FX_GAIN', 'account_id' => $gain]]]);
        $policyService->submit($f['company'], $gainPolicy, $f['maker']);
        $policyService->approve($f['company'], $gainPolicy, $f['checker']);
        $gainRate = $rates->create($f['company'], $f['maker'], ['base_currency' => 'USD', 'quote_currency' => 'VND', 'rate_decimal' => '24000.000000000000', 'rate_date' => '2026-09-14', 'rate_type' => 'spot', 'source_reference' => 'AP-PAY-GAIN', 'effective_from' => '2026-09-14', 'effective_to' => '2026-09-14', 'idempotency_key' => (string) Str::uuid()]);
        $rates->transition($f['company'], $gainRate, 'submit', $f['maker']);
        $rates->transition($f['company'], $gainRate, 'approve', $f['checker']);
        $rates->transition($f['company'], $gainRate, 'activate', $f['checker']);
        $second = $this->procurement()->mutate('payment.allocate', $f['company'], $f['maker'], ['vendor_bill_id' => $bill, 'financial_account_id' => $usdAccount, 'payment_date' => '2026-09-14', 'amount_minor' => 500_000, 'method' => 'bank_transfer', 'reference' => 'PAY-AP-FX-2']);
        self::assertSame($second, $this->procurement()->mutate('payment.allocate', $f['company'], $f['maker'], ['vendor_bill_id' => $bill, 'financial_account_id' => $usdAccount, 'payment_date' => '2026-09-14', 'amount_minor' => 500_000, 'method' => 'bank_transfer', 'reference' => 'PAY-AP-FX-2']));
        $finalSettlement = DB::table('supplier_payment_fx_settlements')->where('ap_payment_id', $second)->firstOrFail();
        self::assertSame(120_000_000, (int) $finalSettlement->settlement_functional_minor);
        self::assertSame(123_452_500, (int) $finalSettlement->released_carrying_minor);
        self::assertSame(3_452_500, (int) $finalSettlement->realized_fx_minor);
        self::assertSame(246_905_000, (int) DB::table('supplier_payment_fx_settlements')->where('supplier_invoice_fx_recognition_id', $evidence->id)->sum('released_carrying_minor'));
        self::assertSame(2, DB::table('supplier_payment_fx_settlements')->where('supplier_invoice_fx_recognition_id', $evidence->id)->count());
        self::assertSame(2, DB::table('treasury_transactions')->where('company_id', $f['company'])->where('source_type', 'ap_payment')->count());
        self::assertSame(2, DB::table('payroll_accounting_journals')->where('company_id', $f['company'])->where('source_type', 'ap_payment')->count());
        self::assertSame([], app(ApFxRevaluationSourceService::class)->eligible($f['company'], $f['branch'], '2026-09-14'));
        $this->assertValidation(fn () => $this->procurement()->mutate('payment.allocate', $f['company'], $f['maker'], ['vendor_bill_id' => $bill, 'financial_account_id' => $usdAccount, 'payment_date' => '2026-09-14', 'amount_minor' => 499_999, 'method' => 'bank_transfer', 'reference' => 'PAY-AP-FX-2']), 'reference', 'AP_PAYMENT_IDEMPOTENCY_CONFLICT');
        $this->assertValidation(fn () => $this->procurement()->mutate('payment.allocate', $f['company'], $f['maker'], ['vendor_bill_id' => $bill, 'financial_account_id' => $usdAccount, 'payment_date' => '2026-09-15', 'amount_minor' => 1, 'method' => 'bank_transfer', 'reference' => 'PAY-AP-FX-OVER']), 'amount_minor', 'Payment allocation vượt Bill outstanding.');

        $this->assertApFxDataQualityCorruptionProofs((int) $finalSettlement->id, $f['foreign_company']);
    }

    public function test_canonical_workflow_orchestrates_acceptance_and_reversal_without_duplicate_side_effects(): void
    {
        $f = $this->fixture();
        $this->workflowDefinition($f, ServiceAcceptanceWorkflowOrchestrator::APPROVAL);
        $this->workflowDefinition($f, ServiceAcceptanceWorkflowOrchestrator::REVERSAL);
        $acceptance = $this->acceptances()->create($f['company'], $f['maker'], $this->acceptanceData($f, 'SA-WORKFLOW', 4));
        $commands = app(CommandBusInterface::class);

        $commands->dispatch(new SubmitServiceAcceptanceCommand($f['company'], $acceptance, $f['maker']));
        $commands->dispatch(new SubmitServiceAcceptanceCommand($f['company'], $acceptance, $f['maker']));
        $approvalInstance = DB::table('workflow_instances')->where(['company_id' => $f['company'], 'subject_type' => ServiceAcceptanceWorkflowOrchestrator::APPROVAL, 'subject_id' => $acceptance])->firstOrFail();
        self::assertSame(1, DB::table('workflow_instances')->where(['company_id' => $f['company'], 'subject_type' => ServiceAcceptanceWorkflowOrchestrator::APPROVAL, 'subject_id' => $acceptance])->count());
        self::assertSame(1, DB::table('workflow_tasks')->where('workflow_instance_id', $approvalInstance->id)->count());
        self::assertContains('Workflow Task', array_column(app(ApprovalInboxService::class)->read($f['company'], (int) $f['checker']->id)['pending'], 'type'));
        self::assertContains('Service Acceptance Approval', array_column(app(ApprovalInboxService::class)->read($f['company'], (int) $f['checker']->id)['pending'], 'type'));
        self::assertContains('WORKFLOW_APPROVAL_PENDING', array_column($this->exceptions($f['company'], $this->report($f['company']))['rows'], 'exception_type'));
        $approvalTask = DB::table('workflow_tasks')->where('workflow_instance_id', $approvalInstance->id)->firstOrFail();
        app(WorkflowTaskService::class)->decide($f['company'], (int) $approvalTask->id, (int) $f['checker']->id, 'approved', null, 'sa-workflow-approve');
        self::assertSame('approved', DB::table('service_acceptances')->where('id', $acceptance)->value('status'));
        self::assertSame(['5100', '2160'], DB::table('payroll_accounting_journal_lines')->where('journal_id', DB::table('service_acceptances')->where('id', $acceptance)->value('accounting_journal_id'))->orderBy('line_number')->pluck('account_code_snapshot')->all());

        $period = (int) DB::table('accounting_periods')->where(['company_id' => $f['company'], 'year' => 2026, 'month' => 9])->value('id');
        $reversal = new RequestServiceAcceptanceReversalCommand($f['company'], $acceptance, $period, 'Workflow controlled reversal', 'sa-workflow-reversal', $f['maker']);
        $commands->dispatch($reversal);
        $commands->dispatch($reversal);
        $reversalInstance = DB::table('workflow_instances')->where(['company_id' => $f['company'], 'subject_type' => ServiceAcceptanceWorkflowOrchestrator::REVERSAL, 'subject_id' => $acceptance])->firstOrFail();
        self::assertSame(1, DB::table('workflow_instances')->where(['company_id' => $f['company'], 'subject_type' => ServiceAcceptanceWorkflowOrchestrator::REVERSAL, 'subject_id' => $acceptance])->count());
        self::assertContains('Service Acceptance Reversal Approval', array_column(app(ApprovalInboxService::class)->read($f['company'], (int) $f['checker']->id)['pending'], 'type'));
        $reversalTask = DB::table('workflow_tasks')->where('workflow_instance_id', $reversalInstance->id)->firstOrFail();
        app(WorkflowTaskService::class)->decide($f['company'], (int) $reversalTask->id, (int) $f['checker']->id, 'approved', null, 'sa-workflow-reversal-approve');
        self::assertSame('reversed', DB::table('service_acceptances')->where('id', $acceptance)->value('status'));
        $reversalJournal = DB::table('service_acceptances')->where('id', $acceptance)->value('reversal_journal_id');
        self::assertSame(4_000_000, (int) DB::table('payroll_accounting_journal_lines')->where(['journal_id' => $reversalJournal, 'account_code_snapshot' => '2160'])->sum('debit_minor'));
        self::assertSame(4_000_000, (int) DB::table('payroll_accounting_journal_lines')->where(['journal_id' => $reversalJournal, 'account_code_snapshot' => '5100'])->sum('credit_minor'));
        self::assertSame(4, DB::table('workflow_outbox')->whereIn('workflow_instance_id', [(int) $approvalInstance->id, (int) $reversalInstance->id])->count());
    }

    public function test_service_acceptance_evidence_reuses_private_dms_and_enforces_tenant_linkage(): void
    {
        Storage::fake('local');
        config()->set('document.storage_disk', 'local');
        $f = $this->fixture();
        $acceptance = $this->acceptances()->create($f['company'], $f['maker'], $this->acceptanceData($f, 'SA-DMS', 2));
        $documents = app(EnterpriseDocumentService::class);
        $document = $documents->create([
            'company_id' => $f['company'], 'document_type' => 'SERVICE_ACCEPTANCE_EVIDENCE', 'title' => 'Biên bản nghiệm thu',
            'classification' => 'CONFIDENTIAL', 'owner_module' => 'Procurement', 'subject_type' => 'SERVICE_ACCEPTANCE', 'subject_id' => $acceptance,
        ], UploadedFile::fake()->createWithContent('acceptance.pdf', "%PDF-1.4\nservice acceptance"), (int) $f['maker']->id);
        $row = DB::table('enterprise_documents')->where('id', $document)->firstOrFail();
        $version = DB::table('enterprise_document_versions')->where('document_id', $document)->firstOrFail();
        self::assertSame('SERVICE_ACCEPTANCE', $row->subject_type);
        self::assertSame($acceptance, (int) $row->subject_id);
        self::assertSame(64, strlen((string) $version->sha256));
        self::assertSame('local_private', $version->storage_driver);

        $this->expectException(NotFoundHttpException::class);
        $documents->create([
            'company_id' => $f['foreign_company'], 'document_type' => 'SERVICE_ACCEPTANCE_EVIDENCE', 'title' => 'Foreign evidence',
            'classification' => 'CONFIDENTIAL', 'owner_module' => 'Procurement', 'subject_type' => 'SERVICE_ACCEPTANCE', 'subject_id' => $acceptance,
        ], UploadedFile::fake()->createWithContent('foreign.pdf', '%PDF-1.4'), (int) $f['maker']->id);
    }

    public function test_partial_acceptance_bill_and_payment_preserve_canonical_lineage(): void
    {
        $f = $this->fixture();
        $inventoryBefore = DB::table('inventory_movements')->count();
        $first = $this->accept($f, 'SA-ONE', 4);
        self::assertSame($first, $this->acceptances()->approve($f['company'], $first, $f['checker']));
        $line = DB::table('service_acceptance_lines')->where('service_acceptance_id', $first)->firstOrFail();
        self::assertSame(4_000_000, (int) $line->accepted_minor);
        self::assertSame(['5100', '2160'], DB::table('payroll_accounting_journal_lines')->where('journal_id', DB::table('service_acceptances')->where('id', $first)->value('accounting_journal_id'))->orderBy('line_number')->pluck('account_code_snapshot')->all());
        self::assertSame($inventoryBefore, DB::table('inventory_movements')->count());
        $exposure = app(CostCenterCostManagementReadService::class)->read($f['company'], null, '2026-09-01', '2026-09-30')['totals'];
        self::assertSame(6_000_000, $exposure['commitment']);
        self::assertSame(4_000_000, $exposure['actual']);
        self::assertSame(10_000_000, $exposure['consumed']);
        $initialReport = $this->report($f['company']);
        self::assertSame(['ordered' => 10_000_000, 'accepted' => 4_000_000, 'unaccepted' => 6_000_000, 'accrual' => 4_000_000], array_intersect_key($initialReport['service'], array_flip(['ordered', 'accepted', 'unaccepted', 'accrual'])));
        self::assertSame(0, $initialReport['goods']['grni']);
        self::assertSame('NO_BUDGET', $initialReport['budget']['status']);
        self::assertNull($initialReport['budget']['remaining']);

        $tooMuch = $this->draftAcceptance($f, 'SA-TOO-MUCH', 7);
        $this->assertValidation(fn () => $this->acceptances()->approve($f['company'], $tooMuch, $f['checker']), 'accepted_quantity', 'SERVICE_ACCEPTANCE_EXCEEDS_ORDER');
        $second = $this->accept($f, 'SA-TWO', 6);
        self::assertSame(10, (int) DB::table('service_acceptance_lines as sal')->join('service_acceptances as sa', 'sa.id', '=', 'sal.service_acceptance_id')->where(['sal.purchase_order_line_id' => $f['po_line'], 'sa.status' => 'approved'])->sum('sal.accepted_quantity'));

        $bill = $this->procurement()->mutate('bill.create', $f['company'], $f['maker'], ['purchase_order_id' => $f['po'], 'purchase_order_line_id' => $f['po_line'], 'service_acceptance_line_id' => (int) $line->id, 'quantity' => 3, 'invoice_number' => 'INV-SERVICE-1', 'invoice_date' => '2026-09-12', 'due_date' => '2026-10-12']);
        $this->procurement()->mutate('bill.approve', $f['company'], $f['checker'], ['id' => $bill]);
        $billRow = DB::table('vendor_bills')->where('id', $bill)->firstOrFail();
        self::assertSame(3_000_000, (int) $billRow->total_minor);
        self::assertSame(['2160', '2100'], DB::table('payroll_accounting_journal_lines')->where('journal_id', $billRow->accounting_journal_id)->orderBy('line_number')->pluck('account_code_snapshot')->all());
        $this->assertValidation(fn () => $this->procurement()->mutate('bill.create', $f['company'], $f['maker'], ['purchase_order_id' => $f['po'], 'purchase_order_line_id' => $f['po_line'], 'service_acceptance_line_id' => (int) $line->id, 'quantity' => 2, 'invoice_number' => 'INV-SERVICE-OVER', 'invoice_date' => '2026-09-12', 'due_date' => '2026-10-12']), 'quantity');

        $payment = $this->procurement()->mutate('payment.allocate', $f['company'], $f['checker'], ['vendor_bill_id' => $bill, 'payment_date' => '2026-09-12', 'amount_minor' => 1_000_000, 'method' => 'bank_clearing', 'reference' => 'PAY-SERVICE-1']);
        self::assertSame($payment, $this->procurement()->mutate('payment.allocate', $f['company'], $f['checker'], ['vendor_bill_id' => $bill, 'payment_date' => '2026-09-12', 'amount_minor' => 1_000_000, 'method' => 'bank_clearing', 'reference' => 'PAY-SERVICE-1']));
        self::assertSame(2_000_000, (int) $billRow->total_minor - (int) DB::table('ap_payment_allocations')->where('vendor_bill_id', $bill)->sum('allocated_minor'));
        $settlementReport = $this->report($f['company']);
        self::assertSame(3_000_000, $settlementReport['service']['billed']);
        self::assertSame(1_000_000, $settlementReport['ap']['paid']);
        self::assertSame(2_000_000, $settlementReport['ap']['outstanding']);
        self::assertSame(2_000_000, $settlementReport['ap']['gl']);
        self::assertSame(7_000_000, $settlementReport['service']['accrual']);
        self::assertSame(0, $settlementReport['budget']['open_commitment']);
        self::assertSame(10_000_000, $settlementReport['budget']['actual']);
        self::assertSame(10_000_000, $settlementReport['budget']['consumed']);
        self::assertSame(1, DB::table('treasury_transactions')->where(['source_type' => 'ap_payment', 'source_id' => $payment])->count());
        self::assertSame($inventoryBefore, DB::table('inventory_movements')->count());
        $vendorPerformance = $this->vendorPerformance($f['company'], $this->exceptions($f['company'], $settlementReport)['rows'], ['vendor_id' => $f['vendor'], 'classification' => 'operating_expense']);
        self::assertSame(3_000_000, $vendorPerformance['rows'][0]['billed']);
        self::assertSame(1_000_000, $vendorPerformance['rows'][0]['paid']);
        self::assertSame(2_000_000, $vendorPerformance['rows'][0]['outstanding']);
        self::assertSame(0, $vendorPerformance['rows'][0]['overdue']);
        $afterSettlement = app(CostCenterCostManagementReadService::class)->read($f['company'], null, '2026-09-01', '2026-09-30')['totals'];
        self::assertSame(0, $afterSettlement['commitment']);
        self::assertSame(10_000_000, $afterSettlement['actual']);
        self::assertSame(10_000_000, $afterSettlement['consumed']);
    }

    public function test_acceptance_is_idempotent_tenant_safe_maker_checked_and_goods_isolated(): void
    {
        $f = $this->fixture();
        $service = $this->acceptances();
        $data = $this->acceptanceData($f, 'SA-IDEMPOTENT', 4);
        $id = $service->create($f['company'], $f['maker'], $data);
        self::assertSame($id, $service->create($f['company'], $f['maker'], $data));
        $service->submit($f['company'], $id, $f['maker']);
        $this->assertValidation(fn () => $service->approve($f['company'], $id, $f['maker']), 'actor', 'MAKER_CHECKER_REQUIRED');
        $service->approve($f['company'], $id, $f['checker']);
        self::assertSame($id, $service->submit($f['company'], $id, $f['maker']));
        self::assertSame($id, $service->approve($f['company'], $id, $f['checker']));
        $this->expectException(RecordNotFoundException::class);
        $service->approve($f['foreign_company'], $id, $f['checker']);
    }

    public function test_reconciliation_filters_are_tenant_scoped_and_query_count_is_bounded(): void
    {
        $f = $this->fixture();
        $this->accept($f, 'SA-REPORT-FILTER', 4);
        DB::flushQueryLog();
        DB::enableQueryLog();

        $report = $this->report($f['company'], ['branch_id' => $f['branch'], 'cost_center_id' => $f['cost_center'], 'vendor_id' => $f['vendor'], 'classification' => 'operating_expense']);
        $queryCount = count(DB::getQueryLog());
        DB::disableQueryLog();

        self::assertSame(10_000_000, $report['service']['ordered']);
        self::assertSame(4_000_000, $report['service']['accepted']);
        self::assertSame(0, $report['goods']['ordered']);
        self::assertSame('PARTIAL_EVIDENCE', $report['budget']['status']);
        self::assertNull($report['ap']['gl']);
        self::assertLessThanOrEqual(25, $queryCount);
        self::assertSame(0, $this->report($f['foreign_company'])['service']['ordered']);
    }

    public function test_exception_workbench_uses_canonical_sources_and_exact_financial_invariants(): void
    {
        $f = $this->fixture();
        $acceptance = $this->accept($f, 'SA-EXCEPTION', 4);
        $report = $this->report($f['company']);
        $exceptions = $this->exceptions($f['company'], $report);
        $types = array_column($exceptions['rows'], 'exception_type');

        self::assertContains('PO_AWAITING_SERVICE_ACCEPTANCE', $types);
        self::assertContains('SERVICE_ACCEPTED_NOT_BILLED', $types);
        self::assertContains('BUDGET_NO_BUDGET', $types);
        $acceptedException = collect($exceptions['rows'])->firstWhere('exception_type', 'SERVICE_ACCEPTED_NOT_BILLED');
        self::assertSame('service-acceptance.view', $acceptedException['permission'] ?? null);
        self::assertSame('vendor-bill.view', $acceptedException['action_permission'] ?? null);
        self::assertSame($exceptions['total'], array_sum($exceptions['counts']));
        self::assertNotContains('SERVICE_ACCRUAL_RECONCILIATION_MISMATCH', $types);
        self::assertSame(0, $this->exceptions($f['foreign_company'], $this->report($f['foreign_company']))['total']);

        $mismatch = $report;
        $mismatch['goods']['grni'] = 123;
        $mismatch['service']['accrual'] = 456;
        $mismatch['ap']['gl'] = 789;
        $mismatchTypes = array_column($this->exceptions($f['company'], $mismatch)['rows'], 'exception_type');
        self::assertContains('GOODS_GRNI_RECONCILIATION_MISMATCH', $mismatchTypes);
        self::assertContains('SERVICE_ACCRUAL_RECONCILIATION_MISMATCH', $mismatchTypes);
        self::assertContains('AP_RECONCILIATION_MISMATCH', $mismatchTypes);

        DB::table('service_acceptances')->where('id', $acceptance)->update(['status' => 'reversal_requested']);
        self::assertContains('SERVICE_REVERSAL_PENDING', array_column($this->exceptions($f['company'], $this->report($f['company']))['rows'], 'exception_type'));
    }

    public function test_vendor_performance_is_grouped_tenant_safe_and_evidence_based(): void
    {
        $f = $this->fixture();
        DB::table('purchase_orders')->where('id', $f['po'])->update(['expected_date' => '2026-09-12']);
        $this->accept($f, 'SA-VENDOR-PERFORMANCE', 4);
        $report = $this->report($f['company']);
        $exceptions = $this->exceptions($f['company'], $report);
        DB::flushQueryLog();
        DB::enableQueryLog();
        $performance = $this->vendorPerformance($f['company'], $exceptions['rows'], ['vendor_id' => $f['vendor'], 'classification' => 'operating_expense']);
        $queryCount = count(DB::getQueryLog());
        DB::disableQueryLog();
        $row = $performance['rows'][0];

        self::assertSame(1, $row['po_count']);
        self::assertSame(10_000_000, $row['ordered']);
        self::assertSame(0, $row['goods_received']);
        self::assertSame(4_000_000, $row['service_accepted']);
        self::assertSame(0, $row['billed']);
        self::assertSame(0, $row['paid']);
        self::assertSame(0, $row['outstanding']);
        self::assertSame(6_000_000, $row['open_commitment']);
        self::assertSame(2, $row['exception_count']);
        self::assertSame(1, $row['fulfillment_observations']);
        self::assertSame(1, $row['on_time']);
        self::assertSame(0, $row['late']);
        self::assertSame(0, $row['average_delay_days']);
        self::assertSame('SUPPORTED_FOR_DATED_OBSERVATIONS_ONLY', $performance['evidence']['fulfillment']);
        self::assertSame('NOT_ENOUGH_EVIDENCE', $performance['evidence']['quality']);
        self::assertSame('NOT_ENOUGH_EVIDENCE', $performance['evidence']['sla_score']);
        self::assertSame('NOT_ENOUGH_EVIDENCE', $performance['evidence']['rating']);
        self::assertLessThanOrEqual(8, $queryCount);
        self::assertSame(0, $this->vendorPerformance($f['company'], [], ['date_from' => '2026-08-01', 'date_to' => '2026-08-31', 'vendor_id' => $f['vendor']])['rows'][0]['ordered']);
        self::assertSame(10_000_000, $this->vendorPerformance($f['company'], [], ['branch_id' => $f['branch'], 'cost_center_id' => $f['cost_center']])['rows'][0]['ordered']);
        self::assertSame([], $this->vendorPerformance($f['foreign_company'], [])['rows']);
    }

    public function test_closed_period_blocks_approval_and_inventory_line_is_rejected(): void
    {
        $f = $this->fixture();
        $id = $this->draftAcceptance($f, 'SA-CLOSED', 1);
        DB::table('accounting_periods')->insert(['company_id' => $f['company'], 'year' => 2026, 'month' => 9, 'start_date' => '2026-09-01', 'end_date' => '2026-09-30', 'status' => 'closed', 'created_at' => now(), 'updated_at' => now()]);
        $this->assertValidation(fn () => $this->acceptances()->approve($f['company'], $id, $f['checker']), 'period', 'ACCOUNTING_PERIOD_CLOSED');

        DB::table('purchase_order_lines')->where('id', $f['po_line'])->update(['procurement_classification' => 'inventory']);
        $this->assertValidation(fn () => $this->acceptances()->create($f['company'], $f['maker'], $this->acceptanceData($f, 'SA-GOODS', 1)), 'purchase_order_line_id', 'SERVICE_ACCEPTANCE_LINE_NOT_ELIGIBLE');
    }

    public function test_unbilled_acceptance_reversal_reuses_accounting_adjustment_and_restores_budget(): void
    {
        $f = $this->fixture();
        $inventoryBefore = DB::table('inventory_movements')->count();
        $acceptance = $this->accept($f, 'SA-REVERSIBLE', 4);
        $period = (int) DB::table('accounting_periods')->where(['company_id' => $f['company'], 'year' => 2026, 'month' => 9])->value('id');
        $before = app(CostCenterCostManagementReadService::class)->read($f['company'], null, '2026-09-01', '2026-09-30')['totals'];
        self::assertSame(6_000_000, $before['commitment']);
        self::assertSame(4_000_000, $before['actual']);

        $service = $this->acceptances();
        $adjustment = $service->requestReversal($f['company'], $acceptance, $period, 'Dịch vụ chưa hoàn thành theo biên bản', 'sa-reversal-key', $f['maker']);
        self::assertSame($adjustment, $service->requestReversal($f['company'], $acceptance, $period, 'Dịch vụ chưa hoàn thành theo biên bản', 'sa-reversal-key', $f['maker']));
        $this->assertValidation(fn () => $service->approveReversal($f['company'], $acceptance, $f['maker']), 'approval', 'MAKER_CHECKER_REQUIRED');
        $journal = $service->approveReversal($f['company'], $acceptance, $f['checker']);
        self::assertSame($journal, $service->approveReversal($f['company'], $acceptance, $f['checker']));
        self::assertSame(['5100', '2160'], DB::table('payroll_accounting_journal_lines')->where('journal_id', $journal)->orderBy('line_number')->pluck('account_code_snapshot')->all());
        self::assertSame([0, 4_000_000], DB::table('payroll_accounting_journal_lines')->where('journal_id', $journal)->orderBy('line_number')->pluck('debit_minor')->map(fn ($v): int => (int) $v)->all());
        self::assertSame('reversed', DB::table('service_acceptances')->where('id', $acceptance)->value('status'));
        self::assertSame(1, DB::table('payroll_accounting_journals')->where('reversal_of_journal_id', DB::table('service_acceptances')->where('id', $acceptance)->value('accounting_journal_id'))->count());
        $after = app(CostCenterCostManagementReadService::class)->read($f['company'], null, '2026-09-01', '2026-09-30')['totals'];
        self::assertSame(10_000_000, $after['commitment']);
        self::assertSame(0, $after['actual']);
        self::assertSame(10_000_000, $after['consumed']);
        self::assertSame($inventoryBefore, DB::table('inventory_movements')->count());
    }

    public function test_billing_dependency_and_wrong_tenant_block_reversal(): void
    {
        $f = $this->fixture();
        $acceptance = $this->accept($f, 'SA-BILLED', 4);
        $line = DB::table('service_acceptance_lines')->where('service_acceptance_id', $acceptance)->firstOrFail();
        $bill = $this->procurement()->mutate('bill.create', $f['company'], $f['maker'], ['purchase_order_id' => $f['po'], 'purchase_order_line_id' => $f['po_line'], 'service_acceptance_line_id' => (int) $line->id, 'quantity' => 1, 'invoice_number' => 'INV-BLOCK-REVERSAL', 'invoice_date' => '2026-09-12', 'due_date' => '2026-10-12']);
        self::assertGreaterThan(0, $bill);
        $period = (int) DB::table('accounting_periods')->where(['company_id' => $f['company'], 'year' => 2026, 'month' => 9])->value('id');
        $this->assertValidation(fn () => $this->acceptances()->requestReversal($f['company'], $acceptance, $period, 'Sai nghiệm thu', 'blocked-key', $f['checker']), 'acceptance', 'SERVICE_ACCEPTANCE_HAS_BILLING_DEPENDENCY');
        $this->expectException(RecordNotFoundException::class);
        $this->acceptances()->requestReversal($f['foreign_company'], $acceptance, $period, 'Sai tenant', 'foreign-key', $f['checker']);
    }

    public function test_downstream_service_correction_is_effective_dated_idempotent_and_balanced(): void
    {
        $f = $this->fixture();
        $acceptance = $this->accept($f, 'SA-DOWNSTREAM-REVERSAL', 4);
        $line = DB::table('service_acceptance_lines')->where('service_acceptance_id', $acceptance)->firstOrFail();
        $bill = $this->procurement()->mutate('bill.create', $f['company'], $f['maker'], ['purchase_order_id' => $f['po'], 'purchase_order_line_id' => $f['po_line'], 'service_acceptance_line_id' => (int) $line->id, 'quantity' => 3, 'invoice_number' => 'INV-DOWNSTREAM-REVERSAL', 'invoice_date' => '2026-09-12', 'due_date' => '2026-10-10']);
        $this->procurement()->mutate('bill.approve', $f['company'], $f['checker'], ['id' => $bill]);
        $payment = $this->procurement()->mutate('payment.allocate', $f['company'], $f['checker'], ['vendor_bill_id' => $bill, 'payment_date' => '2026-09-12', 'amount_minor' => 1_000_000, 'method' => 'bank_clearing', 'reference' => 'PAY-DOWNSTREAM-REVERSAL']);
        $period = (int) DB::table('accounting_periods')->where(['company_id' => $f['company'], 'year' => 2026, 'month' => 9])->value('id');

        $this->assertValidation(fn () => $this->acceptances()->requestReversal($f['company'], $acceptance, $period, 'Blocked by bill', 'sa-blocked-by-bill', $f['maker']), 'acceptance', 'SERVICE_ACCEPTANCE_HAS_BILLING_DEPENDENCY');
        $this->assertValidation(fn () => app(VendorBillReversalService::class)->request($f['company'], $bill, $period, 'Blocked by payment', 'bill-blocked-by-payment', $f['maker']), 'bill', 'VENDOR_BILL_HAS_ACTIVE_PAYMENT');

        foreach (['treasury.view', 'vendor-bill.view', 'treasury.payment.reversal.request', 'treasury.payment.reversal.approve', 'vendor-bill.reversal.request', 'vendor-bill.reversal.approve'] as $name) {
            Permission::findOrCreate($name, 'web');
        }
        $paymentPayload = ['company_id' => $f['company'], 'target_accounting_period_id' => $period, 'reason' => 'Payment returned', 'idempotency_key' => 'payment-reversal-key'];
        $f['maker']->givePermissionTo(['treasury.view', 'vendor-bill.view']);
        $this->actingAs($f['maker'])->get(route('admin.finance.treasury', ['company_id' => $f['company']]))->assertOk()->assertDontSee(route('admin.finance.treasury.payment-reversal.request', ['payment' => $payment]));
        $this->actingAs($f['maker'])->post(route('admin.finance.treasury.payment-reversal.request', ['payment' => $payment]), $paymentPayload)->assertForbidden();
        $f['maker']->givePermissionTo(['treasury.payment.reversal.request', 'treasury.payment.reversal.approve']);
        $this->actingAs($f['maker'])->get(route('admin.finance.treasury', ['company_id' => $f['company']]))->assertOk()->assertSee(route('admin.finance.treasury.payment-reversal.request', ['payment' => $payment]));
        $this->actingAs($f['maker'])->post(route('admin.finance.treasury.payment-reversal.request', ['payment' => $payment]), $paymentPayload)->assertRedirect();
        $this->actingAs($f['maker'])->post(route('admin.finance.treasury.payment-reversal.request', ['payment' => $payment]), $paymentPayload)->assertRedirect();
        $paymentRequest = (int) DB::table('ap_payment_reversal_requests')->where('ap_payment_id', $payment)->value('id');
        self::assertSame(1, DB::table('ap_payment_reversal_requests')->where('ap_payment_id', $payment)->count());
        self::assertNotContains('AP Payment Reversal', array_column(app(ApprovalInboxService::class)->read($f['company'], (int) $f['checker']->id)['pending'], 'type'));
        $reversalChecker = Role::findOrCreate('Downstream Reversal Checker', 'web');
        $reversalChecker->givePermissionTo(['treasury.payment.reversal.approve', 'vendor-bill.reversal.approve']);
        $f['checker']->assignRole($reversalChecker);
        self::assertContains('AP Payment Reversal', array_column(app(ApprovalInboxService::class)->read($f['company'], (int) $f['checker']->id)['pending'], 'type'));
        $this->actingAs($f['maker'])->post(route('admin.finance.treasury.payment-reversal.approve', ['reversal' => $paymentRequest]), ['company_id' => $f['company'], 'effective_at' => '2026-09-15'])->assertSessionHasErrors('approval');
        $this->actingAs($f['checker'])->post(route('admin.finance.treasury.payment-reversal.approve', ['reversal' => $paymentRequest]), ['company_id' => $f['company'], 'effective_at' => '2026-09-15'])->assertRedirect();
        $paymentJournal = (int) DB::table('ap_payment_reversal_requests')->where('id', $paymentRequest)->value('reversal_journal_id');
        $this->actingAs($f['checker'])->post(route('admin.finance.treasury.payment-reversal.approve', ['reversal' => $paymentRequest]), ['company_id' => $f['company'], 'effective_at' => '2026-09-15'])->assertRedirect();
        self::assertSame(1, DB::table('ap_payment_allocation_reversals')->count());
        self::assertSame(1, DB::table('treasury_transactions')->where(['source_type' => 'ap_payment_reversal', 'source_id' => $paymentRequest])->count());

        $planning = app(AccountsPayablePlanningReadService::class);
        $filters = ['as_of_date' => '2026-09-12', 'vendor_id' => null, 'branch_id' => null, 'cost_center_id' => null, 'due_bucket' => null, 'payment_status' => null, 'currency' => 'VND', 'sort' => 'outstanding', 'direction' => 'desc'];
        self::assertSame(2_000_000, $planning->read($f['company'], $filters)['totals']['outstanding']);
        $filters['as_of_date'] = '2026-09-16';
        self::assertSame(3_000_000, $planning->read($f['company'], $filters)['totals']['outstanding']);

        CarbonImmutable::setTestNow('2026-09-16 12:00:00');
        $billPayload = ['company_id' => $f['company'], 'target_accounting_period_id' => $period, 'reason' => 'Acceptance correction', 'idempotency_key' => 'bill-reversal-key'];
        $this->actingAs($f['maker'])->get(route('admin.procurement.payables', ['company_id' => $f['company']]))->assertOk()->assertDontSee(route('admin.procurement.vendor-bill-reversal.request', ['bill' => $bill]));
        $this->actingAs($f['maker'])->post(route('admin.procurement.vendor-bill-reversal.request', ['bill' => $bill]), $billPayload)->assertForbidden();
        $f['maker']->givePermissionTo('vendor-bill.reversal.request');
        $this->actingAs($f['maker'])->get(route('admin.procurement.payables', ['company_id' => $f['company']]))->assertOk()->assertSee(route('admin.procurement.vendor-bill-reversal.request', ['bill' => $bill]));
        $this->actingAs($f['maker'])->post(route('admin.procurement.vendor-bill-reversal.request', ['bill' => $bill]), $billPayload)->assertRedirect();
        $this->actingAs($f['maker'])->post(route('admin.procurement.vendor-bill-reversal.request', ['bill' => $bill]), $billPayload)->assertRedirect();
        $billRequest = (int) DB::table('vendor_bill_reversal_requests')->where('vendor_bill_id', $bill)->value('id');
        self::assertSame(1, DB::table('vendor_bill_reversal_requests')->where('vendor_bill_id', $bill)->count());
        self::assertContains('Vendor Bill Reversal', array_column(app(ApprovalInboxService::class)->read($f['company'], (int) $f['checker']->id)['pending'], 'type'));
        $this->actingAs($f['checker'])->post(route('admin.procurement.vendor-bill-reversal.approve', ['reversal' => $billRequest]), ['company_id' => $f['company'], 'effective_at' => '2026-09-16'])->assertRedirect();
        $billJournal = (int) DB::table('vendor_bill_reversal_requests')->where('id', $billRequest)->value('reversal_journal_id');
        $this->actingAs($f['checker'])->post(route('admin.procurement.vendor-bill-reversal.approve', ['reversal' => $billRequest]), ['company_id' => $f['company'], 'effective_at' => '2026-09-16'])->assertRedirect();
        self::assertSame(0, $planning->read($f['company'], $filters)['totals']['outstanding']);
        self::assertGreaterThan(0, $paymentJournal);
        self::assertGreaterThan(0, $billJournal);
        self::assertSame(6, DB::table('activity_log')->whereIn('log_name', ['ap-payment-reversal', 'vendor-bill-reversal'])->count());

        $adjustment = $this->acceptances()->requestReversal($f['company'], $acceptance, $period, 'Correct accepted service', 'sa-after-bill-reversal', $f['maker']);
        self::assertGreaterThan(0, $adjustment);
        $this->acceptances()->approveReversal($f['company'], $acceptance, $f['checker']);
        $cost = app(CostCenterCostManagementReadService::class)->read($f['company'], null, '2026-09-01', '2026-09-30')['totals'];
        self::assertSame(10_000_000, $cost['commitment']);
        self::assertSame(0, $cost['actual']);
        self::assertSame(10_000_000, $cost['consumed']);
        self::assertSame(0, DB::table('inventory_movements')->count());
        self::assertSame(0, (int) DB::table('payroll_accounting_journals')->whereIn('source_type', ['ap_payment', 'vendor_bill', 'service_acceptance', 'accounting_adjustment'])->sum(DB::raw('CAST(total_debit_minor AS SIGNED)-CAST(total_credit_minor AS SIGNED)')));
        CarbonImmutable::setTestNow();
    }

    /** @return array{company:int,foreign_company:int,maker:User,checker:User,branch:int,cost_center:int,vendor:int,po:int,po_line:int} */
    private function fixture(string $currency = 'VND'): array
    {
        $maker = User::factory()->create();
        $checker = User::factory()->create();
        $company = (int) DB::table('companies')->insertGetId(['uuid' => (string) Str::uuid(), 'code' => 'SERVICE', 'name' => 'Service Company', 'currency_code' => 'VND', 'functional_currency_code' => 'VND', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
        $foreign = (int) DB::table('companies')->insertGetId(['uuid' => (string) Str::uuid(), 'code' => 'FOREIGN', 'name' => 'Foreign Company', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('company_user_access')->insert([
            ['company_id' => $company, 'user_id' => $maker->id, 'created_at' => now(), 'updated_at' => now()],
            ['company_id' => $company, 'user_id' => $checker->id, 'created_at' => now(), 'updated_at' => now()],
        ]);
        $branch = (int) DB::table('branches')->insertGetId(['company_id' => $company, 'code' => 'HQ', 'name' => 'HQ', 'status' => 'active', 'timezone' => 'Asia/Ho_Chi_Minh', 'created_at' => now(), 'updated_at' => now()]);
        $center = (int) DB::table('cost_centers')->insertGetId(['company_id' => $company, 'code' => 'OPS', 'name' => 'Operations', 'created_at' => now(), 'updated_at' => now()]);
        $procurement = $this->procurement();
        $vendor = $procurement->mutate('vendor.create', $company, $maker, ['code' => 'SERVICE-VENDOR', 'name' => 'Service Vendor']);
        $request = $procurement->mutate('request.create', $company, $maker, ['branch_id' => $branch, 'cost_center_id' => $center, 'reference' => 'PR-SERVICE', 'requested_date' => '2026-09-12', 'reason' => 'Professional service', 'description' => 'Professional service', 'procurement_classification' => 'operating_expense', 'quantity' => 10, 'estimated_unit_cost_minor' => 1_000_000]);
        $procurement->mutate('request.transition', $company, $maker, ['id' => $request, 'to' => 'submitted']);
        $procurement->mutate('request.transition', $company, $checker, ['id' => $request, 'to' => 'approved']);
        $po = $procurement->mutate('order.create', $company, $maker, ['purchase_request_id' => $request, 'vendor_id' => $vendor, 'reference' => 'PO-SERVICE', 'order_date' => '2026-09-12', 'currency' => $currency]);
        $procurement->mutate('order.transition', $company, $checker, ['id' => $po, 'to' => 'approved']);
        $poLine = (int) DB::table('purchase_order_lines')->where('purchase_order_id', $po)->value('id');
        $this->policies($company, $maker, $checker);

        return ['company' => $company, 'foreign_company' => $foreign, 'maker' => $maker, 'checker' => $checker, 'branch' => $branch, 'cost_center' => $center, 'vendor' => $vendor, 'po' => $po, 'po_line' => $poLine];
    }

    /** @param array{company:int,maker:User,checker:User} $f */
    private function workflowDefinition(array $f, string $code): int
    {
        $permissionName = $code === ServiceAcceptanceWorkflowOrchestrator::APPROVAL ? 'service-acceptance.approve' : 'service-acceptance.reverse';
        $permission = Permission::findOrCreate($permissionName, 'web');
        $role = Role::findOrCreate('Service Workflow '.$permissionName, 'web');
        $role->givePermissionTo($permission);
        $f['checker']->assignRole($role);
        $service = app(WorkflowDefinitionService::class);
        $id = $service->create(new WorkflowDefinitionData($f['company'], (int) $f['maker']->id, $code, $code, null, 'sequential', [[
            'key' => 'approve', 'name' => 'Approval', 'mode' => 'any_one', 'resolver_type' => 'permission', 'resolver_value' => $permissionName,
        ]]));
        $service->publish($f['company'], $id, (int) $f['maker']->id);

        return $id;
    }

    private function policies(int $company, User $maker, User $checker): void
    {
        $posting = app(SourceAccountingPostingService::class);
        $expense = $posting->accountEvidence($company, '5100', 'VND', $maker->id)['id'];
        $ap = $posting->accountEvidence($company, '2100', 'VND', $maker->id)['id'];
        $accrual = (int) DB::table('accounting_accounts')->insertGetId(['uuid' => (string) Str::uuid(), 'company_id' => $company, 'code' => '2160', 'name' => 'Service Received Not Invoiced', 'account_type' => 'liability', 'normal_balance' => 'credit', 'currency' => 'VND', 'allow_posting' => true, 'status' => 'active', 'effective_from' => '2026-09-12', 'created_by' => $maker->id, 'created_at' => now(), 'updated_at' => now()]);
        $service = app(AccountingPostingPolicyService::class);
        $cash = $posting->accountEvidence($company, '1100', 'VND', $maker->id)['id'];
        foreach ([['SERVICE-ACCEPTANCE', 'service_acceptance', 'SERVICE_ACCEPTANCE_APPROVED', 'operating_expense', [['entry_side' => 'debit', 'line_role' => 'EXPENSE', 'account_id' => $expense, 'requires_cost_center' => true], ['entry_side' => 'credit', 'line_role' => 'ACCRUAL', 'account_id' => $accrual]]], ['SERVICE-VENDOR-BILL', 'service_vendor_bill', 'SERVICE_VENDOR_BILL_POSTED', 'operating_expense', [['entry_side' => 'debit', 'line_role' => 'ACCRUAL', 'account_id' => $accrual], ['entry_side' => 'credit', 'line_role' => 'AP', 'account_id' => $ap]]], ['VENDOR-PAYMENT', 'vendor_payment', 'VENDOR_PAYMENT', null, [['entry_side' => 'debit', 'line_role' => 'AP', 'account_id' => $ap], ['entry_side' => 'credit', 'line_role' => 'CASH', 'account_id' => $cash]]]] as [$key, $source, $event, $classification, $lines]) {
            $id = $service->create($company, $maker, ['policy_key' => $key, 'source_type' => $source, 'business_event' => $event, 'classification' => $classification, 'version' => 1, 'currency' => 'VND', 'effective_from' => '2026-09-12', 'lines' => $lines]);
            $service->submit($company, $id, $maker);
            $service->approve($company, $id, $checker);
        }
    }

    /** @param array{company:int,maker:User,checker:User,po:int,po_line:int} $f */
    private function accept(array $f, string $reference, int $quantity): int
    {
        $id = $this->draftAcceptance($f, $reference, $quantity);
        $this->acceptances()->approve($f['company'], $id, $f['checker']);

        return $id;
    }

    /** @param array{company:int,maker:User,po:int,po_line:int} $f */
    private function draftAcceptance(array $f, string $reference, int $quantity): int
    {
        $id = $this->acceptances()->create($f['company'], $f['maker'], $this->acceptanceData($f, $reference, $quantity));
        $this->acceptances()->submit($f['company'], $id, $f['maker']);

        return $id;
    }

    /** @param array{po:int,po_line:int} $f
     * @return array<string,mixed>
     */
    private function acceptanceData(array $f, string $reference, int $quantity): array
    {
        return ['purchase_order_id' => $f['po'], 'purchase_order_line_id' => $f['po_line'], 'reference' => $reference, 'acceptance_date' => '2026-09-12', 'accepted_quantity' => $quantity, 'idempotency_key' => 'key-'.$reference];
    }

    private function procurement(): ProcurementService
    {
        return app(ProcurementService::class);
    }

    private function acceptances(): ServiceAcceptanceService
    {
        return app(ServiceAcceptanceService::class);
    }

    /** @return array<string,mixed> */
    private function report(int $company, array $filters = []): array
    {
        return app(ProcurementReconciliationReadService::class)->read($company, array_replace(['date_from' => '2026-09-01', 'date_to' => '2026-09-30', 'branch_id' => null, 'cost_center_id' => null, 'vendor_id' => null, 'classification' => null, 'currency' => 'VND'], $filters));
    }

    /** @param array<string,mixed> $report
     * @return array{rows:list<array<string,mixed>>,counts:array<string,int>,total:int}
     */
    private function exceptions(int $company, array $report): array
    {
        return app(ProcurementExceptionReadService::class)->read($company, ['date_from' => '2026-09-01', 'date_to' => '2026-09-30', 'branch_id' => null, 'cost_center_id' => null, 'vendor_id' => null, 'classification' => null, 'currency' => 'VND', 'exception_type' => null, 'severity' => null, 'business_status' => null], $report);
    }

    /** @param list<array<string,mixed>> $exceptions
     * @param  array<string,mixed>  $filters
     * @return array<string,mixed>
     */
    private function vendorPerformance(int $company, array $exceptions, array $filters = []): array
    {
        return app(VendorPerformanceReadService::class)->read($company, array_replace(['date_from' => '2026-09-01', 'date_to' => '2026-09-30', 'branch_id' => null, 'cost_center_id' => null, 'vendor_id' => null, 'classification' => null, 'currency' => 'VND', 'sort' => 'ordered', 'direction' => 'desc'], $filters), $exceptions);
    }

    private function assertValidation(callable $operation, string $key, ?string $message = null): void
    {
        try {
            $operation();
            self::fail('Expected validation exception.');
        } catch (ValidationException $exception) {
            self::assertArrayHasKey($key, $exception->errors());
            if ($message !== null) {
                self::assertSame($message, $exception->errors()[$key][0] ?? null);
            }
        }
    }

    private function assertApFxDataQualityCorruptionProofs(int $settlementId, int $foreignCompanyId): void
    {
        $service = app(DataQualityAuditService::class);
        $row = DB::table('supplier_payment_fx_settlements')->where('id', $settlementId)->firstOrFail();
        $treasury = DB::table('treasury_transactions')->where('id', $row->treasury_transaction_id)->firstOrFail();
        foreach (['supplier_payment_fx_orphan_settlement', 'supplier_payment_fx_scope_mismatch', 'supplier_payment_fx_snapshot_mismatch', 'supplier_payment_fx_currency_mismatch', 'supplier_payment_fx_released_basis_mismatch', 'supplier_payment_fx_realized_mismatch', 'supplier_payment_fx_journal_mismatch', 'supplier_payment_fx_treasury_mismatch'] as $key) {
            self::assertSame(0, $this->dataQualityCount($service, $key), "Valid AP-FX fixture triggered {$key}.");
        }

        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        try {
            DB::table('supplier_payment_fx_settlements')->where('id', $settlementId)->update(['supplier_invoice_fx_recognition_id' => 9_999_999_999]);
            self::assertGreaterThan(0, $this->dataQualityCount($service, 'supplier_payment_fx_orphan_settlement'));
            DB::table('supplier_payment_fx_settlements')->where('id', $settlementId)->update(['supplier_invoice_fx_recognition_id' => $row->supplier_invoice_fx_recognition_id]);
        } finally {
            DB::statement('SET FOREIGN_KEY_CHECKS=1');
        }

        DB::table('supplier_payment_fx_settlements')->where('id', $settlementId)->update(['company_id' => $foreignCompanyId]);
        self::assertGreaterThan(0, $this->dataQualityCount($service, 'supplier_payment_fx_scope_mismatch'));
        DB::table('supplier_payment_fx_settlements')->where('id', $settlementId)->update(['company_id' => $row->company_id]);

        DB::table('supplier_payment_fx_settlements')->where('id', $settlementId)->update(['rate_decimal' => '1.000000000000']);
        self::assertGreaterThan(0, $this->dataQualityCount($service, 'supplier_payment_fx_snapshot_mismatch'));
        DB::table('supplier_payment_fx_settlements')->where('id', $settlementId)->update(['rate_decimal' => $row->rate_decimal]);

        DB::table('supplier_payment_fx_settlements')->where('id', $settlementId)->update(['transaction_currency' => 'EUR']);
        self::assertGreaterThan(0, $this->dataQualityCount($service, 'supplier_payment_fx_currency_mismatch'));
        DB::table('supplier_payment_fx_settlements')->where('id', $settlementId)->update(['transaction_currency' => $row->transaction_currency]);

        DB::table('supplier_payment_fx_settlements')->where('id', $settlementId)->update(['released_carrying_minor' => (int) $row->released_carrying_minor + 246_905_001]);
        self::assertGreaterThan(0, $this->dataQualityCount($service, 'supplier_payment_fx_released_basis_mismatch'));
        DB::table('supplier_payment_fx_settlements')->where('id', $settlementId)->update(['released_carrying_minor' => $row->released_carrying_minor]);

        DB::table('supplier_payment_fx_settlements')->where('id', $settlementId)->update(['realized_fx_minor' => (int) $row->realized_fx_minor + 1]);
        self::assertGreaterThan(0, $this->dataQualityCount($service, 'supplier_payment_fx_realized_mismatch'));
        DB::table('supplier_payment_fx_settlements')->where('id', $settlementId)->update(['realized_fx_minor' => $row->realized_fx_minor]);

        DB::table('supplier_payment_fx_settlements')->where('id', $settlementId)->update(['accounting_policy_version' => (int) $row->accounting_policy_version + 1]);
        self::assertGreaterThan(0, $this->dataQualityCount($service, 'supplier_payment_fx_journal_mismatch'));
        DB::table('supplier_payment_fx_settlements')->where('id', $settlementId)->update(['accounting_policy_version' => $row->accounting_policy_version]);

        DB::table('treasury_transactions')->where('id', $row->treasury_transaction_id)->update(['amount_minor' => (int) $treasury->amount_minor + 1]);
        self::assertGreaterThan(0, $this->dataQualityCount($service, 'supplier_payment_fx_treasury_mismatch'));
        DB::table('treasury_transactions')->where('id', $row->treasury_transaction_id)->update(['amount_minor' => $treasury->amount_minor]);
    }

    private function dataQualityCount(DataQualityAuditService $service, string $key): int
    {
        $finding = collect($service->snapshot()['findings'])->firstWhere('key', $key);
        self::assertNotNull($finding, "Missing data quality rule {$key}.");

        return (int) $finding['count'];
    }
}
