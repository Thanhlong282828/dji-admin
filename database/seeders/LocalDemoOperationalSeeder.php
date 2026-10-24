<?php

namespace Database\Seeders;

use App\Models\User;
use App\Support\Compensation\PositionCompensationReference;
use App\Support\Media\MediaPurpose;
use App\Support\Media\MediaService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Modules\Audit\Application\DTOs\AuditEventData;
use Modules\Audit\Domain\Contracts\AuditRecorderInterface;
use Modules\Commerce\Application\Services\CommerceFulfillmentService;
use Modules\Commerce\Application\Services\CommercePaymentService;
use Modules\Commerce\Application\Services\OrderTransitionService;
use Modules\Commerce\Application\Services\SalesReceivableService;
use Modules\Company\Domain\Models\Company;
use Modules\Employee\Domain\Enums\ContractType;
use Modules\Employee\Domain\Enums\EmploymentType;
use Modules\Employee\Domain\Models\Employee;
use Modules\Finance\Application\Services\FinanceOperationsService;
use Modules\Finance\Application\Services\TreasuryService;
use Modules\Maintenance\Application\Services\MaintenanceService;
use Modules\Payroll\Application\Commands\CreateAnnualTaxReconciliation\CreateAnnualTaxReconciliationCommand;
use Modules\Payroll\Application\Commands\CreatePayrollAdjustment\CreatePayrollAdjustmentCommand;
use Modules\Payroll\Application\DTOs\ErpAdministrationData;
use Modules\Payroll\Application\Services\BranchBonusService;
use Modules\Payroll\Domain\Enums\PayrollRunStatus;
use Modules\Payroll\Domain\Models\PayrollInputSnapshot;
use Modules\Payroll\Domain\Models\PayrollPeriod;
use Modules\Payroll\Domain\Models\PayrollRun;
use Modules\Payroll\Domain\Services\AccountingAdministrationService;
use Modules\Payroll\Domain\Services\EmployeePaymentAccountService;
use Modules\Payroll\Domain\Services\ErpAdministrationService;
use Modules\Payroll\Domain\Services\ErpExportService;
use Modules\Payroll\Domain\Services\GovernmentTaxFilingService;
use Modules\Payroll\Domain\Services\InsuranceManagementService;
use Modules\Payroll\Domain\Services\PayrollAccountingJournalService;
use Modules\Payroll\Domain\Services\PayrollAdjustmentManagementService;
use Modules\Payroll\Domain\Services\PayrollAnnualTaxLifecycleService;
use Modules\Payroll\Domain\Services\PayrollAnnualTaxOperationsService;
use Modules\Payroll\Domain\Services\PayrollAnnualTaxReconciliationService;
use Modules\Payroll\Domain\Services\PayrollInputOperationsService;
use Modules\Payroll\Domain\Services\PayrollPaymentBatchService;
use Modules\Payroll\Domain\Services\PayrollPeriodOperationsService;
use Modules\Payroll\Domain\Services\PayrollRunService;
use Modules\Payroll\Domain\Services\PayslipGenerationService;
use Modules\Payroll\Domain\Services\TaxManagementService;
use Modules\POS\Domain\Services\BranchPosService;
use Modules\Procurement\Application\Services\ProcurementService;
use Modules\Recruitment\Application\DTOs\CandidateOfferData;
use Modules\Recruitment\Application\DTOs\HireCandidateData;
use Modules\Recruitment\Domain\Enums\CandidateOfferStatus;
use Modules\Recruitment\Domain\Models\Application as RecruitmentApplication;
use Modules\Recruitment\Domain\Models\CandidateOffer;
use Modules\Recruitment\Domain\Services\CandidateOfferService;
use Modules\Recruitment\Domain\Services\HireCandidateService;
use Modules\Workflow\Application\DTOs\StartWorkflowData;
use Modules\Workflow\Application\DTOs\WorkflowDefinitionData;
use Modules\Workflow\Domain\Services\WorkflowDefinitionService;
use Modules\Workflow\Domain\Services\WorkflowExecutionService;
use Spatie\Permission\Models\Role;

final class LocalDemoOperationalSeeder extends Seeder
{
    public function run(): void
    {
        $company = Company::query()->where('code', 'SCHEMA-TECH-VN')->firstOrFail();
        $admin = User::query()->whereIn('email', ['admin@local.test', 'admin@schema.vn'])->orderByRaw("email = 'admin@local.test' DESC")->firstOrFail();
        $this->actors($company->id);
        $this->positionLevels($company->id);
        $this->commerceFoundation($admin);
        $this->recruitmentHandoff($company->id, $admin);
        // Only the deterministic E000x bootstrap workforce is enriched. Ad-hoc/manual
        // employees in schema_admin remain user-owned and are never rewritten here.
        $employees = Employee::query()->where('company_id', $company->id)
            ->whereBetween('employee_code', ['E0001', 'E0035'])
            ->whereIn('employment_status', ['active', 'probation', 'leave'])
            ->orderBy('employee_code')->get();
        $employeeIds = array_values($employees->pluck('id')->map(fn (mixed $id): int => $this->requiredId($id, 'employee'))->all());
        $this->erpConsolidation($company->id, $employeeIds, $admin->id);
        $this->workforce($company->id, $employeeIds, $admin->id);
        $this->resetMutableDemoPayroll($company->id, 'LOCAL-2026-08');
        $this->branchBonuses($company->id, $admin);
        $this->payroll($company->id, $employeeIds, $admin->id);
        $this->notifications($company->id, $admin->id);
        $this->integration($company->id, $admin->id);
        $this->iam($company->id, $admin->id);
        $this->workflow($company->id, $admin->id);
        $this->commerceCatalog();
        $this->commerceProductMedia($company->id);
        $this->commerceBuyerExperience();
        $this->commerceOrders();
        $this->commercePointOfSale($company->id, $admin);
        $this->procurementAccountingChain($company->id, $admin);
        $this->salesReceivableChain($company->id, $admin);
        $this->financeOperationsChain($company->id, $admin);
        $this->maintenanceScenario($company->id, $admin);
        $this->treasuryOperatingScenario($company->id, $admin);
        app(AuditRecorderInterface::class)->record(new AuditEventData($company->id, 'user', $admin->id, 'bootstrap', 'LocalDemo', 'LocalDemoBootstrapCompleted', 'local_demo_dataset', 'SCHEMA-LOCAL-DEMO', [], ['status' => 'ready'], ['synthetic_demo' => true, 'external_delivery' => false], 'local-demo-bootstrap-v1', $this->uuid(701)));
    }

    private function maintenanceScenario(int $companyId, User $maker): void
    {
        $asset = DB::table('finance_assets')->where(['company_id' => $companyId, 'asset_code' => 'LOCAL-ASSET-SERVER-01'])->first();
        $checker = User::query()->where('email', 'finance@local.test')->first()
            ?? User::query()->where('email', 'hr.manager@local.test')->first();
        $technician = DB::table('employees')->where('company_id', $companyId)->whereIn('employment_status', ['active', 'probation'])->orderBy('employee_code')->value('id');
        if ($asset === null || ! $checker instanceof User || ! is_numeric($technician)) {
            return;
        }
        $assetId = $this->requiredId(get_object_vars($asset)['id'] ?? null, 'maintenance asset');
        $service = app(MaintenanceService::class);
        $request = DB::table('maintenance_requests')->where(['company_id' => $companyId, 'code' => 'LOCAL-MR-SERVER-01'])->value('id');
        if (! is_numeric($request)) {
            $request = $service->createRequest(['company_id' => $companyId, 'asset_id' => $assetId, 'code' => 'LOCAL-MR-SERVER-01', 'title' => 'Kiểm tra hệ thống làm mát máy chủ', 'description' => 'Nhiệt độ vận hành cao hơn ngưỡng theo dõi nội bộ.', 'priority' => 'HIGH'], (int) $maker->id);
            $service->submitRequest($companyId, (int) $request, (int) $maker->id);
            $service->approveRequest($companyId, (int) $request, (int) $checker->id, true);
        }
        $order = $service->convertRequest($companyId, (int) $request, (int) $checker->id);
        $status = DB::table('maintenance_work_orders')->where('id', $order)->value('status');
        if ($status === 'DRAFT') {
            $service->assign($companyId, $order, (int) $technician, (int) $checker->id);
            $service->transitionWorkOrder($companyId, $order, 'PLANNED', (int) $checker->id);
        }
        if (! DB::table('preventive_maintenance_plans')->where(['company_id' => $companyId, 'code' => 'LOCAL-PM-SERVER-01'])->exists()) {
            $service->createPlan(['company_id' => $companyId, 'asset_id' => $assetId, 'code' => 'LOCAL-PM-SERVER-01', 'name' => 'Bảo trì máy chủ định kỳ', 'frequency_type' => 'MONTHLY', 'frequency_value' => 1, 'next_due_date' => '2026-09-30'], (int) $maker->id);
        }
    }

    private function financeOperationsChain(int $companyId, User $maker): void
    {
        $checker = User::query()->where('email', 'finance@local.test')->first()
            ?? User::query()->where('email', 'hr.manager@local.test')->firstOrFail();
        $branchId = $this->requiredId(DB::table('branches')->where('company_id', $companyId)->where('status', 'active')->orderBy('id')->value('id'), 'finance operations branch');
        $service = app(FinanceOperationsService::class);

        DB::table('finance_expense_categories')->updateOrInsert(
            ['company_id' => $companyId, 'code' => 'UTILITIES'],
            ['name' => 'Điện, nước và Internet', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()],
        );
        $expenseCategory = $this->requiredId(DB::table('finance_expense_categories')->where(['company_id' => $companyId, 'code' => 'UTILITIES'])->value('id'), 'expense category');
        if (! DB::table('finance_expenses')->where(['company_id' => $companyId, 'reference' => 'LOCAL-EXP-2026-08'])->exists()) {
            $expense = $service->mutate('expense.create', $companyId, $maker, ['branch_id' => $branchId, 'expense_category_id' => $expenseCategory, 'reference' => 'LOCAL-EXP-2026-08', 'amount_minor' => 8_750_000, 'expense_date' => '2026-08-25', 'description' => 'Chi phí điện, nước và Internet chi nhánh tháng 08/2026.']);
            $service->mutate('expense.transition', $companyId, $maker, ['id' => $expense, 'to' => 'submitted']);
            $service->mutate('expense.transition', $companyId, $checker, ['id' => $expense, 'to' => 'approved']);
        }

        DB::table('finance_asset_categories')->updateOrInsert(
            ['company_id' => $companyId, 'code' => 'IT-EQUIPMENT'],
            ['name' => 'Thiết bị CNTT', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()],
        );
        $assetCategory = $this->requiredId(DB::table('finance_asset_categories')->where(['company_id' => $companyId, 'code' => 'IT-EQUIPMENT'])->value('id'), 'asset category');
        $asset = DB::table('finance_assets')->where(['company_id' => $companyId, 'asset_code' => 'LOCAL-ASSET-SERVER-01'])->value('id');
        if (! is_numeric($asset)) {
            $asset = $service->mutate('asset.create', $companyId, $maker, ['asset_category_id' => $assetCategory, 'branch_id' => $branchId, 'asset_code' => 'LOCAL-ASSET-SERVER-01', 'name' => 'Máy chủ vận hành chi nhánh', 'acquisition_cost_minor' => 120_000_000, 'residual_value_minor' => 0, 'acquisition_date' => '2026-08-01', 'in_service_date' => '2026-08-01', 'useful_life_months' => 60]);
            $service->mutate('asset.activate', $companyId, $maker, ['id' => (int) $asset]);
        }
        $service->mutate('asset.depreciate', $companyId, $maker, ['id' => (int) $asset, 'period_month' => '2026-08-01']);

        $budget = DB::table('finance_budgets')->where(['company_id' => $companyId, 'year' => 2026, 'name' => 'LOCAL Operating Budget 2026'])->value('id');
        if (! is_numeric($budget)) {
            $budget = $service->mutate('budget.create', $companyId, $maker, ['year' => 2026, 'name' => 'LOCAL Operating Budget 2026']);
            foreach (['5100' => 10_000_000, '5200' => 2_000_000] as $code => $amount) {
                $account = $this->requiredId(DB::table('accounting_accounts')->where(['company_id' => $companyId, 'code' => $code])->value('id'), 'budget account');
                $service->mutate('budget.line', $companyId, $maker, ['budget_id' => (int) $budget, 'branch_id' => $branchId, 'account_id' => $account, 'month' => 8, 'amount_minor' => $amount]);
            }
            $service->mutate('budget.approve', $companyId, $checker, ['id' => (int) $budget]);
        }
    }

    private function treasuryOperatingScenario(int $companyId, User $maker): void
    {
        $checker = User::query()->where('email', 'finance@local.test')->first()
            ?? User::query()->where('email', 'hr.manager@local.test')->firstOrFail();
        $finance = app(FinanceOperationsService::class);
        $treasury = app(TreasuryService::class);
        $expense = DB::table('finance_expenses')->where(['company_id' => $companyId, 'reference' => 'LOCAL-EXP-2026-08'])->first();
        if ($expense !== null && $expense->status === 'approved') {
            $finance->mutate('expense.transition', $companyId, $checker, ['id' => (int) $expense->id, 'to' => 'paid']);
        }
        foreach (DB::table('ap_payments')->where('company_id', $companyId)->where('reference', 'like', 'LOCAL-%')->get() as $payment) {
            $journal = DB::table('payroll_accounting_journals')->where(['company_id' => $companyId, 'source_type' => 'ap_payment', 'source_id' => $payment->id, 'posting_type' => 'vendor_payment'])->first();
            $branchId = DB::table('vendor_bills as b')->join('ap_payment_allocations as a', 'a.vendor_bill_id', '=', 'b.id')->where('a.payment_id', $payment->id)->value('b.branch_id');
            if ($journal !== null && is_numeric($branchId)) {
                $treasury->recordSource($companyId, (int) $branchId, 'ap_payment', (int) $payment->id, (int) $journal->id, (string) $payment->reference, (int) $payment->amount_minor, (string) $payment->payment_date.' 12:00:00', $maker->id);
            }
        }
        foreach (DB::table('treasury_transactions')->where('company_id', $companyId)->where('reference', 'like', 'LOCAL-%')->where('status', 'recorded')->get() as $transaction) {
            $session = DB::table('treasury_reconciliation_sessions')->where(['company_id' => $companyId, 'financial_account_id' => $transaction->financial_account_id, 'period_from' => '2026-08-01', 'period_to' => '2026-08-31'])->value('id');
            $sessionId = is_numeric($session) ? (int) $session : $treasury->mutate('reconciliation.create', $companyId, $checker, ['financial_account_id' => (int) $transaction->financial_account_id, 'period_from' => '2026-08-01', 'period_to' => '2026-08-31']);
            $external = 'LOCAL-BANK-'.$transaction->source_type.'-'.$transaction->source_id;
            $line = DB::table('treasury_statement_lines')->where(['reconciliation_session_id' => $sessionId, 'external_reference' => $external])->value('id');
            if (! is_numeric($line) && DB::table('treasury_reconciliation_sessions')->where('id', $sessionId)->value('status') !== 'open') {
                continue;
            }
            $signedAmount = $transaction->direction === 'inflow' ? (int) $transaction->amount_minor : -((int) $transaction->amount_minor);
            $lineId = is_numeric($line) ? (int) $line : $treasury->mutate('statement.create', $companyId, $checker, ['session_id' => $sessionId, 'external_reference' => $external, 'occurred_at' => $transaction->occurred_at, 'amount_minor' => $signedAmount]);
            if (DB::table('treasury_statement_lines')->where('id', $lineId)->value('status') === 'unmatched') {
                $treasury->mutate('statement.match', $companyId, $checker, ['line_id' => $lineId, 'transaction_id' => (int) $transaction->id]);
            }
        }
        foreach (DB::table('treasury_reconciliation_sessions')->where('company_id', $companyId)->where('period_from', '2026-08-01')->where('period_to', '2026-08-31')->where('status', 'open')->pluck('id') as $sessionId) {
            $treasury->mutate('reconciliation.complete', $companyId, $checker, ['session_id' => (int) $sessionId]);
        }
    }

    private function procurementAccountingChain(int $companyId, User $maker): void
    {
        $checker = User::query()->where('email', 'finance@local.test')->first()
            ?? User::query()->where('email', 'hr.manager@local.test')->firstOrFail();
        $runtime = DB::table('inventory_locations as location')
            ->join('branches as branch', 'branch.id', '=', 'location.branch_id')
            ->join('stores as store', 'store.id', '=', 'location.store_id')
            ->join('seller_profiles as seller', 'seller.id', '=', 'store.seller_profile_id')
            ->join('products as product', 'product.store_id', '=', 'store.id')
            ->join('product_variants as variant', 'variant.product_id', '=', 'product.id')
            ->where('branch.company_id', $companyId)
            ->where('seller.company_id', $companyId)
            ->where('location.status', 'active')
            ->where('product.status', 'active')
            ->where('variant.status', 'active')
            ->orderBy('location.id')
            ->first(['branch.id as branch_id', 'location.id as location_id', 'variant.id as variant_id']);
        if ($runtime === null) {
            return;
        }
        $branchId = $this->requiredId($runtime->branch_id ?? null, 'local procurement branch');
        $locationId = $this->requiredId($runtime->location_id ?? null, 'local procurement location');
        $variantId = $this->requiredId($runtime->variant_id ?? null, 'local procurement variant');

        $service = app(ProcurementService::class);
        $vendor = DB::table('procurement_vendors')->where(['company_id' => $companyId, 'code' => 'LOCAL-VENDOR-001'])->value('id');
        $vendorId = is_numeric($vendor) ? (int) $vendor : $service->mutate('vendor.create', $companyId, $maker, [
            'code' => 'LOCAL-VENDOR-001', 'name' => 'Nhà cung cấp thiết bị vận hành Minh Long', 'tax_code' => 'LOCAL-PROCUREMENT', 'payment_terms_days' => 30,
            'note' => 'Bootstrap-owned canonical Procurement → AP → Accounting proof.',
        ]);
        $request = DB::table('purchase_requests')->where(['company_id' => $companyId, 'reference' => 'LOCAL-PR-001'])->value('id');
        if (! is_numeric($request)) {
            $request = $service->mutate('request.create', $companyId, $maker, [
                'branch_id' => $branchId, 'reference' => 'LOCAL-PR-001', 'requested_date' => '2026-08-20', 'needed_date' => '2026-08-28',
                'reason' => 'Bổ sung tồn kho bán hàng theo ngưỡng vận hành.', 'product_variant_id' => $variantId,
                'description' => 'Bổ sung SKU hiện hữu vào kho chi nhánh', 'quantity' => 5, 'estimated_unit_cost_minor' => 2_500_000,
            ]);
            $service->mutate('request.transition', $companyId, $maker, ['id' => (int) $request, 'to' => 'submitted']);
            $service->mutate('request.transition', $companyId, $checker, ['id' => (int) $request, 'to' => 'approved']);
        }
        $order = DB::table('purchase_orders')->where(['company_id' => $companyId, 'reference' => 'LOCAL-PO-001'])->value('id');
        if (! is_numeric($order)) {
            $order = $service->mutate('order.create', $companyId, $maker, ['purchase_request_id' => (int) $request, 'vendor_id' => $vendorId, 'reference' => 'LOCAL-PO-001', 'order_date' => '2026-08-21', 'expected_date' => '2026-08-28', 'payment_terms_days' => 30]);
            $service->mutate('order.transition', $companyId, $checker, ['id' => (int) $order, 'to' => 'approved']);
        }
        $receipt = DB::table('procurement_goods_receipts')->where(['company_id' => $companyId, 'reference' => 'LOCAL-GR-001'])->value('id');
        if (! is_numeric($receipt)) {
            $lineId = $this->requiredId(DB::table('purchase_order_lines')->where('purchase_order_id', (int) $order)->value('id'), 'local procurement PO line');
            $receipt = $service->mutate('receipt.create', $companyId, $maker, ['purchase_order_id' => (int) $order, 'purchase_order_line_id' => $lineId, 'inventory_location_id' => $locationId, 'reference' => 'LOCAL-GR-001', 'receipt_date' => '2026-08-28', 'quantity' => 5]);
            $service->mutate('receipt.receive', $companyId, $checker, ['id' => (int) $receipt]);
        }
        $bill = DB::table('vendor_bills')->where(['company_id' => $companyId, 'invoice_number' => 'LOCAL-INV-001'])->value('id');
        if (! is_numeric($bill)) {
            $bill = $service->mutate('bill.create', $companyId, $maker, ['purchase_order_id' => (int) $order, 'invoice_number' => 'LOCAL-INV-001', 'invoice_date' => '2026-08-29', 'due_date' => '2026-09-28']);
            $service->mutate('bill.approve', $companyId, $checker, ['id' => (int) $bill]);
        }
        if (! DB::table('ap_payments')->where(['company_id' => $companyId, 'reference' => 'LOCAL-AP-PAY-001'])->exists()) {
            $service->mutate('payment.allocate', $companyId, $checker, ['vendor_bill_id' => (int) $bill, 'payment_date' => '2026-08-30', 'amount_minor' => 5_000_000, 'method' => 'bank_clearing', 'reference' => 'LOCAL-AP-PAY-001']);
        }
    }

    private function salesReceivableChain(int $companyId, User $maker): void
    {
        $checker = User::query()->where('email', 'hr.manager@local.test')->firstOrFail();
        $runtime = DB::table('inventory_balances as balance')->join('product_variants as variant', 'variant.id', '=', 'balance.product_variant_id')->join('products as product', 'product.id', '=', 'variant.product_id')->join('inventory_locations as location', 'location.id', '=', 'balance.inventory_location_id')->join('branches as branch', 'branch.id', '=', 'location.branch_id')->join('stores as store', 'store.id', '=', 'product.store_id')->join('seller_profiles as seller', 'seller.id', '=', 'store.seller_profile_id')->where('seller.company_id', $companyId)->where('branch.company_id', $companyId)->where('balance.on_hand', '>=', 1)->orderBy('balance.id')->first(['variant.id as variant_id', 'location.id as location_id', 'branch.id as branch_id']);
        $customerId = DB::table('commerce_customers')->where('company_id', $companyId)->orderBy('id')->value('id');
        if ($runtime === null || ! is_numeric($customerId)) {
            return;
        }
        $service = app(SalesReceivableService::class);
        $quotation = DB::table('sales_quotations')->where(['company_id' => $companyId, 'reference' => 'LOCAL-ERP-QT-001'])->value('id');
        $quotationId = is_numeric($quotation) ? (int) $quotation : $service->createQuotation($companyId, $maker, ['branch_id' => $this->requiredId($runtime->branch_id ?? null, 'sales branch'), 'customer_id' => (int) $customerId, 'product_variant_id' => $this->requiredId($runtime->variant_id ?? null, 'sales variant'), 'reference' => 'LOCAL-ERP-QT-001', 'quotation_date' => '2026-08-05', 'valid_until' => '2026-08-31', 'description' => 'Thiết bị bán doanh nghiệp synthetic có lineage', 'quantity' => 1, 'unit_price_minor' => 5_500_000, 'discount_minor' => 250_000]);
        if (DB::table('sales_quotations')->where('id', $quotationId)->value('status') === 'draft') {
            $service->acceptQuotation($companyId, $checker, ['id' => $quotationId]);
        }
        $orderId = $service->convertQuotationToSalesOrder($companyId, $maker, ['quotation_id' => $quotationId, 'reference' => 'LOCAL-ERP-SO-001', 'order_date' => '2026-08-06']);
        if (DB::table('sales_orders')->where('id', $orderId)->value('status') === 'draft') {
            $service->confirmSalesOrder($companyId, $maker, ['id' => $orderId]);
        }
        $lineId = $this->requiredId(DB::table('sales_order_lines')->where('sales_order_id', $orderId)->value('id'), 'sales order line');
        if (! DB::table('sales_fulfillments')->where('reference', 'LOCAL-ERP-FUL-001')->exists()) {
            $service->fulfillSalesOrder($companyId, $maker, ['sales_order_line_id' => $lineId, 'inventory_location_id' => $this->requiredId($runtime->location_id ?? null, 'sales location'), 'quantity' => 1, 'reference' => 'LOCAL-ERP-FUL-001', 'fulfilled_on' => '2026-08-07']);
        }
        $invoiceId = $service->createCustomerInvoice($companyId, $maker, ['sales_order_id' => $orderId, 'invoice_number' => 'LOCAL-ERP-CI-001', 'invoice_date' => '2026-08-08', 'due_date' => '2026-09-07']);
        if (DB::table('customer_invoices')->where('id', $invoiceId)->value('status') === 'draft') {
            $service->issueCustomerInvoice($companyId, $checker, ['id' => $invoiceId]);
        }
        if (! DB::table('customer_collections')->where('reference', 'LOCAL-ERP-COL-001')->exists()) {
            $service->allocateCustomerCollection($companyId, $checker, ['customer_invoice_id' => $invoiceId, 'reference' => 'LOCAL-ERP-COL-001', 'collection_date' => '2026-08-20', 'amount_minor' => 3_000_000, 'method' => 'bank_transfer']);
        }
    }

    private function recruitmentHandoff(int $companyId, User $approver): void
    {
        $application = RecruitmentApplication::query()
            ->whereHas('candidate', static fn ($query) => $query->where('email', 'bui.gia.hung@example.test'))
            ->whereHas('requisition.jobPosition', static fn ($query) => $query->where('company_id', $companyId))->first();
        if ($application === null || $application->hired_employee_id !== null) {
            return;
        }

        $offerService = app(CandidateOfferService::class);
        $offer = CandidateOffer::query()->where('company_id', $companyId)->where('application_id', $application->id)->first();
        if ($offer === null) {
            $position = DB::table('recruitment_requisitions as requisition')->join('job_positions as position', 'position.id', '=', 'requisition.job_position_id')->join('organization_units as unit', 'unit.id', '=', 'position.organization_unit_id')->where('requisition.id', $application->recruitment_requisition_id)->first(['position.career_level', 'position.title', 'unit.name']);
            if ($position === null) {
                return;
            }
            $level = is_numeric($position->career_level ?? null) ? (int) $position->career_level : 1;
            $unitName = is_string($position->name ?? null) ? $position->name : '';
            $title = is_string($position->title ?? null) ? $position->title : '';
            $compensation = app(PositionCompensationReference::class)->resolve($level, $unitName, $title, '2026-09-15');
            $offer = $offerService->create($companyId, new CandidateOfferData(
                (int) $application->id,
                EmploymentType::FullTime->value,
                (string) $compensation->baseSalaryMinor,
                'VND',
                CarbonImmutable::parse('2026-09-15'),
                CarbonImmutable::parse('2026-11-15'),
                CarbonImmutable::parse('2026-09-10 23:59:59'),
                ['source' => 'local_demo', 'note' => 'Offer synthetic đi qua canonical Recruitment workflow.'],
            ), (int) $approver->id);
        }
        if ($offer->hired_employee_id !== null) {
            return;
        }

        $submitterId = $this->requiredId(DB::table('users')->where('email', 'recruiter@local.test')->value('id'), 'recruitment submitter');
        $lifecycle = [CandidateOfferStatus::Draft, CandidateOfferStatus::PendingApproval, CandidateOfferStatus::Approved, CandidateOfferStatus::Sent, CandidateOfferStatus::Accepted];
        $currentIndex = array_search($offer->status, $lifecycle, true);
        foreach ($lifecycle as $index => $target) {
            if ($index === 0 || (is_int($currentIndex) && $index <= $currentIndex)) {
                continue;
            }
            $actorId = $target === CandidateOfferStatus::Approved ? (int) $approver->id : $submitterId;
            $offer = $offerService->transition($companyId, (int) $offer->id, $target, $actorId, null, $offer->version);
        }

        $application->refresh();
        app(HireCandidateService::class)->hire($companyId, (int) $offer->id, new HireCandidateData(
            $offer->version,
            $application->version,
            'LOCAL-HIRE-001',
            CarbonImmutable::parse('2026-09-15'),
            (int) $offer->organization_unit_id,
            (int) $offer->job_position_id,
            EmploymentType::FullTime,
            'Văn phòng chi nhánh',
            null,
            ContractType::Indefinite,
            null,
            null,
            'Offer được chấp nhận và bàn giao sang Employee/Onboarding trong local demo.',
            'local-demo-hire-bui-gia-hung-v1',
        ), $submitterId);
    }

    private function commerceProductMedia(int $companyId): void
    {
        // Local bootstrap must remain network-free even when a deployment selects
        // Cloudflare Images. Production media is uploaded by the real write flow.
        if (config('media.driver') !== 'local' || ! extension_loaded('gd')) {
            return;
        }
        $products = DB::table('products as product')->join('stores as store', 'store.id', '=', 'product.store_id')->join('seller_profiles as seller', 'seller.id', '=', 'store.seller_profile_id')
            ->where('seller.company_id', $companyId)->whereNull('product.deleted_at')
            ->where(static fn ($query) => $query->where('product.code', 'like', 'SV-%')->orWhere('product.code', 'like', 'DEMO-%'))
            ->whereNotExists(static fn ($query) => $query->selectRaw('1')->from('media_assets as media')->whereColumn('media.owner_id', 'product.id')->where('media.owner_type', 'product')->where('media.purpose', MediaPurpose::ProductCover->value))
            ->orderBy('product.id')->get(['product.id', 'product.code', 'product.name']);
        $media = app(MediaService::class);
        foreach ($products as $product) {
            $productId = $this->requiredId($product->id ?? null, 'product media owner');
            $code = is_string($product->code ?? null) ? $product->code : 'LOCAL-PRODUCT';
            $name = is_string($product->name ?? null) ? $product->name : $code;
            $path = tempnam(sys_get_temp_dir(), 'local-product-cover-');
            if (! is_string($path)) {
                continue;
            }
            $image = imagecreatetruecolor(1200, 900);
            if ($image === false) {
                @unlink($path);

                continue;
            }
            $hash = crc32($code);
            $background = imagecolorallocate($image, 18 + ($hash % 30), 35 + (($hash >> 4) % 45), 58 + (($hash >> 8) % 55));
            $gold = imagecolorallocate($image, 220, 181, 83);
            $white = imagecolorallocate($image, 245, 247, 250);
            imagefilledrectangle($image, 0, 0, 1200, 900, $background);
            imagefilledrectangle($image, 85, 80, 1115, 100, $gold);
            imagestring($image, 5, 90, 670, strtoupper($code), $gold);
            imagestring($image, 5, 90, 720, mb_strimwidth($name, 0, 60, '…'), $white);
            imagestring($image, 3, 90, 790, 'LOCAL SYNTHETIC PRODUCT MEDIA', $white);
            imagepng($image, $path, 8);
            imagedestroy($image);
            try {
                $upload = new UploadedFile($path, strtolower($code).'.png', 'image/png', null, true);
                $stored = $media->stage($upload, MediaPurpose::ProductCover);
                $media->attach($companyId, 'product', $productId, MediaPurpose::ProductCover, $stored);
            } finally {
                @unlink($path);
            }
        }
    }

    private function commercePointOfSale(int $companyId, User $actor): void
    {
        $pos = app(BranchPosService::class);
        $locations = DB::table('inventory_locations as location')
            ->join('stores as store', 'store.id', '=', 'location.store_id')
            ->join('seller_profiles as seller', 'seller.id', '=', 'store.seller_profile_id')
            ->where('seller.company_id', $companyId)->where('location.status', 'active')
            ->orderBy('location.id')->get(['location.id', 'location.branch_id']);

        foreach ($locations as $index => $location) {
            $phone = '090880'.str_pad((string) ($index + 1), 4, '0', STR_PAD_LEFT);
            $customerId = DB::table('commerce_customers')->where(['company_id' => $companyId, 'phone' => $phone])->value('id');
            if (! is_numeric($customerId)) {
                $customerId = $pos->createCustomer($companyId, [
                    'full_name' => ['Nguyễn Minh Anh', 'Trần Gia Hân', 'Lê Quốc Bảo'][$index % 3],
                    'phone' => $phone,
                    'email' => 'pos.customer.'.($index + 1).'@local.test',
                    'notes' => 'Khách synthetic thuộc luồng POS local demo.',
                ]);
            }

            $serviceCode = 'LOCAL-SVC-'.str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT);
            $serviceId = DB::table('company_services')->where(['company_id' => $companyId, 'code' => $serviceCode])->value('id');
            if (! is_numeric($serviceId)) {
                $serviceId = $pos->createService($companyId, [
                    'code' => $serviceCode,
                    'name' => ['Cài đặt và bàn giao', 'Kiểm tra thiết bị', 'Hỗ trợ kỹ thuật tại quầy'][$index % 3],
                    'description' => 'Dịch vụ synthetic được bán qua POS canonical.',
                    'price_minor' => [250_000, 180_000, 320_000][$index % 3],
                    'currency' => 'VND',
                    'status' => 'active',
                ]);
            }

            if (DB::table('branch_pos_receipts as receipt')->join('customer_visits as visit', 'visit.id', '=', 'receipt.customer_visit_id')->where('visit.customer_id', (int) $customerId)->exists()) {
                continue;
            }
            $variant = DB::table('inventory_balances as balance')->join('product_variants as variant', 'variant.id', '=', 'balance.product_variant_id')->where('balance.inventory_location_id', $location->id)->whereRaw('balance.on_hand - balance.reserved > 0')->orderBy('variant.id')->first(['variant.id']);
            if ($variant === null || ! is_numeric($variant->id ?? null)) {
                continue;
            }
            $visitId = $pos->checkIn($companyId, $this->requiredId($location->branch_id ?? null, 'POS branch'), $this->requiredId($customerId, 'POS customer'), $actor);
            $pos->addService($companyId, $visitId, $this->requiredId($serviceId, 'POS service'), 1);
            $pos->addProduct($companyId, $visitId, $this->requiredId($variant->id ?? null, 'POS variant'), $this->requiredId($location->id ?? null, 'POS inventory location'), 1);
            $pos->checkout($companyId, $visitId, $index % 2 === 0 ? 'cash' : 'bank_transfer', $actor);
        }
    }

    /** @param list<int> $employeeIds */
    private function erpConsolidation(int $companyId, array $employeeIds, int $actorId): void
    {
        $branches = DB::table('branches')->where('company_id', $companyId)->whereNull('deleted_at')->orderBy('id')->get(['id', 'code', 'name']);
        if ($branches->isEmpty()) {
            return;
        }

        $branchesBySuffix = $branches->keyBy(static function (object $branch): string {
            $code = is_string($branch->code ?? null) ? $branch->code : '';

            return str_ends_with($code, '-HN') ? 'HN' : (str_ends_with($code, '-DN') ? 'DN' : 'HCM');
        });
        foreach ($employeeIds as $index => $employeeId) {
            $unitCode = DB::table('employee_assignments as a')->join('organization_units as u', 'u.id', '=', 'a.organization_unit_id')
                ->where('a.employee_id', $employeeId)->where('a.assignment_type', 'primary')->whereNull('a.effective_to')
                ->latest('a.effective_from')->value('u.code');
            $preferred = match ($unitCode) {
                'SALES-DEPT' => $index % 3 === 0 ? 'HCM' : 'HN',
                'DEVOPS', 'MOBILE' => $index % 3 === 0 ? 'HCM' : 'DN',
                'BACKEND', 'FRONTEND' => ['HCM', 'HN', 'DN'][$index % 3],
                default => 'HCM', // HR, Finance and Marketing are centred at HQ.
            };
            $branch = $branchesBySuffix->get($preferred) ?? $branches[$index % $branches->count()];
            $userId = DB::table('employees')->where('company_id', $companyId)->where('id', $employeeId)->value('user_id');
            if (is_numeric($userId)) {
                DB::table('branch_user_access')->updateOrInsert(['branch_id' => $branch->id, 'user_id' => (int) $userId], ['created_at' => now(), 'updated_at' => now()]);
            }
            DB::table('employee_branch_assignments')->updateOrInsert(
                ['company_id' => $companyId, 'employee_id' => $employeeId, 'assignment_type' => 'primary', 'effective_from' => '2026-01-01'],
                ['branch_id' => $branch->id, 'effective_to' => null, 'source' => 'local_demo', 'created_by' => $actorId, 'created_at' => now(), 'updated_at' => now()],
            );
            DB::table('attendance_records')->where('company_id', $companyId)->where('employee_id', $employeeId)->whereNull('branch_id')->update(['branch_id' => $branch->id]);
            DB::table('attendance_events')->where('company_id', $companyId)->where('employee_id', $employeeId)->whereNull('branch_id')->update(['branch_id' => $branch->id]);
            DB::table('payroll_employee_inputs')->where('company_id', $companyId)->where('employee_id', $employeeId)->whereNull('branch_id')->update(['branch_id' => $branch->id, 'branch_snapshot' => $branch->name]);
        }

        foreach ($branches as $index => $branch) {
            $suffix = is_string($branch->code ?? null) ? $branch->code : (string) $branch->id;
            DB::table('branch_management_revenues')->where('company_id', $companyId)->where('reference', 'LOCAL-REV-'.$suffix)->delete();
            DB::table('branch_operating_expenses')->where('company_id', $companyId)->where('reference', 'LOCAL-OPEX-'.$suffix)->delete();
            $monthlyFactors = [3 => 100, 4 => 104, 5 => 97, 6 => 109, 7 => 116, 8 => 112, 10 => 124];
            $baseRevenue = [1_850_000_000, 1_250_000_000, 820_000_000][$index] ?? 750_000_000;
            foreach ($monthlyFactors as $month => $factor) {
                $date = sprintf('2026-%02d-01', $month);
                $monthlyRevenue = intdiv($baseRevenue * $factor, 100);
                $revenueLines = [
                    ['SERVICE', 'service_delivery', 55, 'Nghiệm thu dịch vụ/dự án nội bộ demo'],
                    ['MANAGED', 'managed_services', 30, 'Dịch vụ vận hành định kỳ nội bộ demo'],
                    ['SUPPORT', 'training_support', 15, 'Đào tạo và hỗ trợ kỹ thuật nội bộ demo'],
                ];
                $allocatedRevenue = 0;
                foreach ($revenueLines as $lineIndex => [$lineSuffix, $sourceType, $percentage, $description]) {
                    $amount = $lineIndex === array_key_last($revenueLines)
                        ? $monthlyRevenue - $allocatedRevenue
                        : intdiv($monthlyRevenue * $percentage, 100);
                    $allocatedRevenue += $amount;
                    DB::table('branch_management_revenues')->updateOrInsert(
                        ['company_id' => $companyId, 'reference' => sprintf('LOCAL-REV-2026-%02d-%s-%s', $month, $suffix, $lineSuffix)],
                        ['branch_id' => $branch->id, 'source_type' => $sourceType, 'amount_minor' => $amount, 'currency_code' => 'VND', 'recognized_on' => $date, 'status' => 'recognized', 'description' => $description.'; synthetic DB-backed, không phải Order/Payment hay doanh thu thuế.', 'created_by' => $actorId, 'created_at' => now(), 'updated_at' => now()],
                    );
                }
                DB::table('branch_management_revenues')->where('company_id', $companyId)->where('reference', sprintf('LOCAL-REV-2026-%02d-%s', $month, $suffix))->delete();
                foreach ([['utilities', 18_000_000], ['software_internet', 12_000_000], ['marketing_transport', 24_000_000]] as $categoryIndex => [$category, $baseExpense]) {
                    if (($month + $index + $categoryIndex) % 4 === 0 && $category === 'marketing_transport') {
                        continue;
                    }
                    $amount = $baseExpense + ($index * 2_500_000) + (($month % 3) * 1_500_000);
                    DB::table('branch_operating_expenses')->updateOrInsert(
                        ['company_id' => $companyId, 'reference' => sprintf('LOCAL-OPEX-2026-%02d-%s-%s', $month, $suffix, strtoupper($category))],
                        ['branch_id' => $branch->id, 'category' => $category, 'amount_minor' => $amount, 'currency_code' => 'VND', 'incurred_on' => $date, 'status' => 'approved', 'description' => 'Chi phí vận hành synthetic có chứng từ nội bộ demo.', 'created_by' => $actorId, 'created_at' => now(), 'updated_at' => now()],
                    );
                }
            }
            $rent = [120_000_000, 88_000_000, 62_000_000][$index] ?? 55_000_000;
            DB::table('branch_rent_contracts')->updateOrInsert(['company_id' => $companyId, 'contract_reference' => 'LOCAL-RENT-'.$suffix], ['branch_id' => $branch->id, 'monthly_amount_minor' => $rent, 'currency_code' => 'VND', 'effective_from' => '2026-01-01', 'effective_to' => null, 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
            foreach ([['LAPTOP', 'Laptop nhân viên', 'computer_equipment', 180_000_000, 36], ['NETWORK', 'Thiết bị mạng chi nhánh', 'network_equipment', 95_000_000, 48], ['FURNITURE', 'Bàn ghế và tủ hồ sơ', 'office_furniture', 75_000_000, 60]] as [$assetSuffix, $name, $category, $cost, $life]) {
                DB::table('branch_assets')->updateOrInsert(['company_id' => $companyId, 'asset_code' => 'LOCAL-ASSET-'.$suffix.'-'.$assetSuffix], ['branch_id' => $branch->id, 'name' => $name.' (Demo)', 'category' => $category, 'acquisition_cost_minor' => $cost + ($index * 15_000_000), 'currency_code' => 'VND', 'acquired_on' => '2026-01-01', 'useful_life_months' => $life, 'depreciation_method' => 'straight_line', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
            }
        }
    }

    private function branchBonuses(int $companyId, User $checker): void
    {
        $maker = User::query()->where('email', 'payroll.manager@local.test')->firstOrFail();
        $service = app(BranchBonusService::class);
        $branches = DB::table('branches')->where('company_id', $companyId)->whereNull('deleted_at')->orderBy('id')->get(['id', 'code']);

        foreach (['2026-08-01', '2026-10-01'] as $effectivePeriod) {
            foreach ($branches as $index => $branch) {
                $employee = DB::table('employee_branch_assignments as a')->join('employees as e', 'e.id', '=', 'a.employee_id')
                    ->where('a.company_id', $companyId)->where('a.branch_id', $branch->id)->where('a.assignment_type', 'primary')
                    ->whereDate('a.effective_from', '<=', $effectivePeriod)->where(static fn ($query) => $query->whereNull('a.effective_to')->orWhereDate('a.effective_to', '>=', $effectivePeriod))
                    ->where('e.employment_status', 'active')->orderBy('e.employee_code')->first(['e.id']);
                if ($employee === null) {
                    continue;
                }
                $periodLabel = substr($effectivePeriod, 0, 7);
                $reference = 'LOCAL-BONUS-'.$periodLabel.'-'.(is_string($branch->code ?? null) ? $branch->code : $branch->id);
                $id = DB::table('branch_bonus_proposals')->where('company_id', $companyId)->where('bonus_reference', $reference)->value('id');
                if (! is_numeric($id)) {
                    $id = $service->create($maker, [
                        'company_id' => $companyId,
                        'branch_id' => (int) $branch->id,
                        'employee_id' => (int) $employee->id,
                        'bonus_reference' => $reference,
                        'amount_minor' => 2_000_000 + ($index * 500_000),
                        'reason_category' => 'branch_performance',
                        'reason' => 'Thưởng hiệu quả vận hành chi nhánh '.$periodLabel.' (local demo).',
                        'effective_period' => $effectivePeriod,
                    ]);
                }
                $proposal = DB::table('branch_bonus_proposals')->where('id', $id)->first(['status', 'version']);
                if (($proposal->status ?? null) === 'draft') {
                    $service->transition($maker, $companyId, (int) $id, 'submit', (int) $proposal->version, 'Trình duyệt theo kết quả chi nhánh '.$periodLabel.'.');
                    $proposal = DB::table('branch_bonus_proposals')->where('id', $id)->first(['status', 'version']);
                }
                if (($proposal->status ?? null) === 'submitted') {
                    $service->transition($checker, $companyId, (int) $id, 'approve', (int) $proposal->version, 'Duyệt dữ liệu local demo theo maker-checker.');
                }
            }
        }
    }

    private function commerceFoundation(User $admin): void
    {
        $cities = [['HCM', 'Chi nhánh TP.HCM', 'TP.HCM'], ['HN', 'Chi nhánh Hà Nội', 'Hà Nội'], ['DN', 'Chi nhánh Đà Nẵng', 'Đà Nẵng']];
        foreach (DB::table('companies')->orderBy('id')->get(['id', 'code']) as $companyIndex => $company) {
            DB::table('company_user_access')->updateOrInsert(['company_id' => $company->id, 'user_id' => $admin->id], ['created_at' => now(), 'updated_at' => now()]);
            foreach (array_slice($cities, 0, $companyIndex === 0 ? 3 : (($companyIndex % 2) + 1)) as [$suffix, $name, $city]) {
                DB::table('branches')->updateOrInsert(['company_id' => $company->id, 'code' => $company->code.'-'.$suffix], ['name' => $name, 'status' => 'active', 'address_line' => 'Địa chỉ synthetic phục vụ local demo', 'city' => $city, 'timezone' => 'Asia/Ho_Chi_Minh', 'email' => strtolower($suffix).'@branch.local.test', 'created_at' => now(), 'updated_at' => now()]);
            }
            foreach (DB::table('branches')->where('company_id', $company->id)->pluck('id') as $branchId) {
                DB::table('branch_user_access')->updateOrInsert(['branch_id' => $branchId, 'user_id' => $admin->id], ['created_at' => now(), 'updated_at' => now()]);
            }
            $leader = DB::table('employees as e')->join('users as u', 'u.id', '=', 'e.user_id')->join('employee_assignments as a', function ($join): void {
                $join->on('a.employee_id', '=', 'e.id')->where('a.assignment_type', 'primary')->whereNull('a.effective_to');
            })->where('e.company_id', $company->id)->whereIn('e.employment_status', ['active', 'probation'])->where('u.access_enabled', true)->orderByDesc('e.id')->first(['e.id', 'e.user_id']);
            if ($leader !== null) {
                foreach (DB::table('branches')->where('company_id', $company->id)->pluck('id') as $branchId) {
                    DB::table('branches')->where('id', $branchId)->whereNull('leader_employee_id')->update(['leader_employee_id' => $leader->id, 'updated_at' => now()]);
                    DB::table('branch_user_access')->updateOrInsert(['branch_id' => $branchId, 'user_id' => $leader->user_id], ['created_at' => now(), 'updated_at' => now()]);
                }
            }
        }

        $companyId = $this->requiredId(DB::table('companies')->where('code', 'SCHEMA-TECH-VN')->value('id'), 'commerce company');
        $seller = User::query()->updateOrCreate(['email' => 'seller@local.test'], ['name' => 'Seller Synthetic Việt Nam', 'password' => Hash::make('LocalSeller123!'), 'email_verified_at' => now(), 'access_enabled' => true]);
        $seller->syncRoles(['Seller']);
        DB::table('company_user_access')->updateOrInsert(['company_id' => $companyId, 'user_id' => $seller->id], ['created_at' => now(), 'updated_at' => now()]);
        DB::table('seller_profiles')->updateOrInsert(['company_id' => $companyId, 'user_id' => $seller->id], ['display_name' => 'Sao Việt Seller', 'status' => 'active', 'verified_at' => now(), 'verified_by' => $admin->id, 'created_at' => now(), 'updated_at' => now()]);
        $sellerProfileId = (int) DB::table('seller_profiles')->where('company_id', $companyId)->where('user_id', $seller->id)->value('id');
        DB::table('stores')->updateOrInsert(['slug' => 'sao-viet-technology-store'], ['seller_profile_id' => $sellerProfileId, 'name' => 'Sao Việt Technology Store', 'description' => 'Cửa hàng synthetic phục vụ kiểm thử Seller/Store local, chưa có Product hoặc Order.', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);

        $buyer = User::query()->updateOrCreate(['email' => 'buyer@local.test'], ['name' => 'Buyer Synthetic Việt Nam', 'password' => Hash::make('LocalBuyer123!'), 'email_verified_at' => now(), 'access_enabled' => true]);
        $buyer->syncRoles(['Buyer']);
        DB::table('buyer_profiles')->updateOrInsert(['user_id' => $buyer->id], ['display_name' => 'Khách hàng Synthetic', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('articles')->whereNull('company_id')->update(['company_id' => DB::table('companies')->where('code', 'SCHEMA-TECH-VN')->value('id')]);
    }

    private function commerceCatalog(): void
    {
        $companyId = $this->requiredId(DB::table('companies')->where('code', 'SCHEMA-TECH-VN')->value('id'), 'commerce company');
        $branches = DB::table('branches')->where('company_id', $companyId)->whereNull('deleted_at')->orderBy('id')->pluck('id')->map(static fn (mixed $id): int => (int) $id)->values();
        if ($branches->isEmpty()) {
            return;
        }
        $categories = [
            ['ELEC', 'Điện tử', null, 10],
            ['PHONE', 'Điện thoại', 'ELEC', 20],
            ['LAPTOP', 'Laptop', 'ELEC', 30],
            ['ACCESSORY', 'Phụ kiện', 'ELEC', 40],
            ['HOME', 'Gia dụng', null, 50],
            ['FASHION', 'Thời trang', null, 60],
            ['HEALTH', 'Sức khỏe', null, 70],
            ['OFFICE', 'Văn phòng', null, 80],
        ];
        $categoryIds = [];
        foreach ($categories as [$code, $name, $parentCode, $order]) {
            $slug = strtolower($code);
            DB::table('product_categories')->updateOrInsert(
                ['slug' => $slug],
                ['parent_id' => $parentCode === null ? null : ($categoryIds[$parentCode] ?? null), 'name' => $name, 'status' => 'active', 'sort_order' => $order, 'created_at' => now(), 'updated_at' => now()],
            );
            $categoryIds[$code] = $this->requiredId(DB::table('product_categories')->where('slug', $slug)->value('id'), 'product category');
        }

        $storeId = $this->requiredId(DB::table('stores')->where('slug', 'sao-viet-technology-store')->value('id'), 'commerce store');
        $primarySellerUserId = $this->requiredId(DB::table('stores as s')->join('seller_profiles as sp', 'sp.id', '=', 's.seller_profile_id')->where('s.id', $storeId)->value('sp.user_id'), 'primary seller user');
        DB::table('company_user_access')->updateOrInsert(['company_id' => $companyId, 'user_id' => $primarySellerUserId], ['created_at' => now(), 'updated_at' => now()]);
        DB::table('inventory_locations')->updateOrInsert(
            ['store_id' => $storeId, 'code' => 'MAIN'],
            ['branch_id' => $branches[0], 'name' => 'Kho chính', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()],
        );
        $locationId = $this->requiredId(DB::table('inventory_locations')->where('store_id', $storeId)->where('code', 'MAIN')->value('id'), 'inventory location');
        $products = [
            ['SV-PHONE-01', 'Điện thoại Sao Việt Nova 5G', 'PHONE', 8_990_000, '256GB · Đen', 18],
            ['SV-LAPTOP-01', 'Laptop Sao Việt Pro 14', 'LAPTOP', 24_990_000, '16GB · 512GB', 9],
            ['SV-AUDIO-01', 'Tai nghe không dây AirBeat', 'ACCESSORY', 1_490_000, 'Màu trắng', 32],
            ['SV-KEYBOARD-01', 'Bàn phím cơ Mekong 87', 'ACCESSORY', 1_190_000, 'Blue switch', 24],
            ['SV-MONITOR-01', 'Màn hình Vision 27 QHD', 'ELEC', 6_790_000, '27 inch', 12],
            ['SV-ROUTER-01', 'Bộ phát Wi-Fi 6 HomeMesh', 'ELEC', 2_290_000, 'Bộ 2 thiết bị', 20],
            ['SV-CAMERA-01', 'Camera an ninh SmartEye', 'ELEC', 990_000, '2K · Trong nhà', 27],
            ['SV-HOME-01', 'Máy lọc không khí An Nhiên', 'HOME', 4_590_000, 'Phòng 40m²', 11],
        ];
        foreach ($products as [$code, $name, $categoryCode, $price, $variantName, $onHand]) {
            DB::table('products')->updateOrInsert(
                ['store_id' => $storeId, 'code' => $code],
                ['product_category_id' => $categoryIds[$categoryCode], 'name' => $name, 'slug' => strtolower($code), 'description' => 'Sản phẩm synthetic phục vụ local demo; không phải chào bán thương mại thật.', 'brand' => 'Sao Việt', 'base_price_minor' => $price, 'currency' => 'VND', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()],
            );
            $productId = $this->requiredId(DB::table('products')->where('store_id', $storeId)->where('code', $code)->value('id'), 'product');
            DB::table('product_variants')->updateOrInsert(
                ['sku' => $code.'-DEFAULT'],
                ['product_id' => $productId, 'name' => $variantName, 'attributes' => json_encode(['label' => $variantName], JSON_THROW_ON_ERROR), 'price_minor' => $price, 'status' => 'active', 'created_at' => now(), 'updated_at' => now()],
            );
            $variantId = $this->requiredId(DB::table('product_variants')->where('sku', $code.'-DEFAULT')->value('id'), 'product variant');
            DB::table('inventory_balances')->insertOrIgnore([
                'product_variant_id' => $variantId, 'inventory_location_id' => $locationId,
                'on_hand' => $onHand, 'reserved' => min(2, $onHand), 'version' => 1,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        $additionalStores = [
            ['seller.home@local.test', 'Nhà Việt Seller', 'nha-viet-living-store', 'Nhà Việt Living Store'],
            ['seller.office@local.test', 'Mekong Office Seller', 'mekong-office-store', 'Mekong Office Store'],
        ];
        $storeIds = [$storeId];
        foreach ($additionalStores as [$email, $sellerName, $storeSlug, $storeName]) {
            $seller = User::query()->updateOrCreate(['email' => $email], ['name' => $sellerName, 'password' => Hash::make('LocalSeller123!'), 'email_verified_at' => now(), 'access_enabled' => true]);
            $seller->syncRoles(['Seller']);
            DB::table('company_user_access')->updateOrInsert(['company_id' => $companyId, 'user_id' => $seller->id], ['created_at' => now(), 'updated_at' => now()]);
            DB::table('seller_profiles')->updateOrInsert(['company_id' => $companyId, 'user_id' => $seller->id], ['display_name' => $sellerName, 'status' => 'active', 'verified_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
            $profileId = $this->requiredId(DB::table('seller_profiles')->where('company_id', $companyId)->where('user_id', $seller->id)->value('id'), 'seller profile');
            DB::table('stores')->updateOrInsert(['slug' => $storeSlug], ['seller_profile_id' => $profileId, 'name' => $storeName, 'description' => 'Cửa hàng synthetic phục vụ local marketplace demo.', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
            $storeIds[] = $this->requiredId(DB::table('stores')->where('slug', $storeSlug)->value('id'), 'commerce store');
        }

        $demoNames = [
            ['Nồi chiên Không Dầu An Nhiên', 'HOME', 2_690_000], ['Máy pha cà phê Ban Mê', 'HOME', 3_490_000],
            ['Máy hút bụi Sông Hồng', 'HOME', 4_190_000], ['Đèn bàn thông minh Lotus', 'OFFICE', 890_000],
            ['Ghế công thái học Mekong', 'OFFICE', 5_990_000], ['Bàn làm việc Tre Việt', 'OFFICE', 3_290_000],
            ['Máy in laser Văn Lang', 'OFFICE', 4_790_000], ['Webcam Hội Nghị Aurora', 'ELEC', 1_790_000],
            ['Chuột không dây Swift', 'ACCESSORY', 690_000], ['Ổ cứng di động Delta 1TB', 'ACCESSORY', 2_190_000],
            ['Sạc nhanh GaN 65W', 'ACCESSORY', 790_000], ['Đồng hồ sức khỏe Bình Minh', 'HEALTH', 2_990_000],
            ['Máy massage cổ An Tâm', 'HEALTH', 1_390_000], ['Cân điện tử SmartCare', 'HEALTH', 790_000],
            ['Áo khoác chống nắng Việt', 'FASHION', 590_000], ['Balo laptop Urban 15', 'FASHION', 890_000],
            ['Tai nghe Studio Mekong', 'ACCESSORY', 2_590_000], ['Tablet Hạ Long 11', 'ELEC', 9_490_000],
            ['Máy chiếu Mini Cinema', 'ELEC', 5_490_000], ['Bộ lưu điện HomeSafe', 'ELEC', 2_890_000],
            ['Quạt tuần hoàn Gió Việt', 'HOME', 1_690_000], ['Máy lọc nước Lam Sơn', 'HOME', 6_990_000],
        ];
        foreach ($demoNames as $index => [$name, $categoryCode, $price]) {
            $targetStore = $storeIds[$index % count($storeIds)];
            $code = 'DEMO-'.str_pad((string) ($index + 1), 3, '0', STR_PAD_LEFT);
            DB::table('products')->updateOrInsert(['store_id' => $targetStore, 'code' => $code], ['product_category_id' => $categoryIds[$categoryCode], 'name' => $name, 'slug' => strtolower($code), 'description' => 'Sản phẩm synthetic marketplace demo; không phải chào bán thương mại thật.', 'brand' => 'Thương hiệu Việt Demo', 'base_price_minor' => $price, 'currency' => 'VND', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
            $productId = $this->requiredId(DB::table('products')->where('store_id', $targetStore)->where('code', $code)->value('id'), 'product');
            $storeIndex = array_search($targetStore, $storeIds, true);
            $branchId = $branches[(is_int($storeIndex) ? $storeIndex : 0) % $branches->count()];
            DB::table('inventory_locations')->updateOrInsert(['store_id' => $targetStore, 'code' => 'MAIN'], ['branch_id' => $branchId, 'name' => 'Kho chính', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
            $targetLocation = $this->requiredId(DB::table('inventory_locations')->where('store_id', $targetStore)->where('code', 'MAIN')->value('id'), 'inventory location');
            foreach ([['STANDARD', 'Tiêu chuẩn', $price], ['PREMIUM', 'Cao cấp', $price + intdiv($price, 5)]] as [$suffix, $variantName, $variantPrice]) {
                $sku = $code.'-'.$suffix;
                DB::table('product_variants')->updateOrInsert(['sku' => $sku], ['product_id' => $productId, 'name' => $variantName, 'attributes' => json_encode(['edition' => $variantName], JSON_THROW_ON_ERROR), 'price_minor' => $variantPrice, 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
                $variantId = $this->requiredId(DB::table('product_variants')->where('sku', $sku)->value('id'), 'product variant');
                DB::table('inventory_balances')->insertOrIgnore(['product_variant_id' => $variantId, 'inventory_location_id' => $targetLocation, 'on_hand' => 8 + ($index % 20), 'reserved' => $index % 3, 'version' => 1, 'created_at' => now(), 'updated_at' => now()]);
            }
        }
    }

    private function commerceBuyerExperience(): void
    {
        $buyerId = $this->requiredId(DB::table('users')->where('email', 'buyer@local.test')->value('id'), 'buyer');
        DB::table('carts')->updateOrInsert(['user_id' => $buyerId, 'status' => 'active'], ['created_at' => now(), 'updated_at' => now()]);
        $cartId = $this->requiredId(DB::table('carts')->where('user_id', $buyerId)->where('status', 'active')->value('id'), 'cart');
        $variants = DB::table('product_variants as v')->join('products as p', 'p.id', '=', 'v.product_id')->where('v.status', 'active')->where('p.status', 'active')->orderBy('p.store_id')->orderBy('v.id')->limit(9)->get(['v.id', 'v.product_id', 'v.price_minor']);
        foreach ($variants as $index => $variant) {
            DB::table('cart_items')->updateOrInsert(['cart_id' => $cartId, 'product_variant_id' => $variant->id], ['product_id' => $variant->product_id, 'quantity' => ($index % 3) + 1, 'unit_price_minor' => $variant->price_minor, 'selected' => $index < 6, 'created_at' => now(), 'updated_at' => now()]);
        }
        DB::table('wishlists')->updateOrInsert(['user_id' => $buyerId], ['created_at' => now(), 'updated_at' => now()]);
        $wishlistId = $this->requiredId(DB::table('wishlists')->where('user_id', $buyerId)->value('id'), 'wishlist');
        foreach (DB::table('products')->where('status', 'active')->orderByDesc('id')->limit(10)->pluck('id') as $productId) {
            DB::table('wishlist_items')->updateOrInsert(['wishlist_id' => $wishlistId, 'product_id' => $productId], ['created_at' => now(), 'updated_at' => now()]);
        }
    }

    private function commerceOrders(): void
    {
        $buyerId = $this->requiredId(DB::table('users')->where('email', 'buyer@local.test')->value('id'), 'buyer');
        DB::table('buyer_addresses')->updateOrInsert(
            ['user_id' => $buyerId, 'label' => 'Nhà riêng'],
            ['recipient_name' => 'Nguyễn Minh Anh', 'phone' => '0901000200', 'province' => 'TP. Hồ Chí Minh', 'district' => 'Quận 3', 'ward' => 'Phường Võ Thị Sáu', 'address_line' => '123 Đường Synthetic Demo', 'postal_code' => '700000', 'is_default' => true, 'created_at' => now(), 'updated_at' => now()],
        );
        $address = json_encode(['label' => 'Nhà riêng', 'recipient_name' => 'Nguyễn Minh Anh', 'phone' => '0901000200', 'province' => 'TP. Hồ Chí Minh', 'district' => 'Quận 3', 'ward' => 'Phường Võ Thị Sáu', 'address_line' => '123 Đường Synthetic Demo', 'postal_code' => '700000'], JSON_THROW_ON_ERROR);
        $variants = DB::table('product_variants as v')->join('products as p', 'p.id', '=', 'v.product_id')->join('stores as s', 's.id', '=', 'p.store_id')->join('seller_profiles as sp', 'sp.id', '=', 's.seller_profile_id')->join('inventory_balances as ib', 'ib.product_variant_id', '=', 'v.id')->where('v.status', 'active')->where('p.status', 'active')->orderBy('v.id')->get(['v.id as variant_id', 'v.sku', 'v.name as variant_name', 'v.price_minor', 'p.id as product_id', 'p.name as product_name', 's.id as store_id', 's.name as store_name', 'sp.id as seller_profile_id', 'ib.id as balance_id']);
        if ($variants->isEmpty()) {
            return;
        }
        $admin = User::query()->whereIn('email', ['admin@local.test', 'admin@schema.vn'])->orderByRaw("email = 'admin@local.test' DESC")->firstOrFail();
        $companyId = $this->requiredId(DB::table('companies')->where('code', 'SCHEMA-TECH-VN')->value('id'), 'commerce company');
        for ($index = 0; $index < 18; $index++) {
            $variant = $variants[$index % $variants->count()];
            $quantity = ($index % 2) + 1;
            $initialStatus = $index >= 15 ? 'cancelled' : 'pending_payment';
            $checkoutKey = 'local-demo-checkout-'.str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT);
            DB::table('checkout_sessions')->updateOrInsert(['buyer_user_id' => $buyerId, 'idempotency_key' => $checkoutKey], ['checkout_reference' => $this->uuid(3001 + $index), 'status' => 'completed', 'total_minor' => ((int) $variant->price_minor * $quantity) + 30_000, 'currency' => 'VND', 'completed_at' => now()->subDays($index), 'created_at' => now()->subDays($index), 'updated_at' => now()]);
            $sessionId = $this->requiredId(DB::table('checkout_sessions')->where('buyer_user_id', $buyerId)->where('idempotency_key', $checkoutKey)->value('id'), 'checkout session');
            $orderNumber = 'ODR-DEMO-'.str_pad((string) ($index + 1), 4, '0', STR_PAD_LEFT);
            $subtotal = (int) $variant->price_minor * $quantity;
            DB::table('orders')->insertOrIgnore(['order_number' => $orderNumber, 'checkout_session_id' => $sessionId, 'checkout_reference' => $this->uuid(3001 + $index), 'buyer_user_id' => $buyerId, 'store_id' => $variant->store_id, 'seller_profile_id' => $variant->seller_profile_id, 'store_name_snapshot' => $variant->store_name, 'shipping_address_snapshot' => $address, 'shipping_method' => 'standard', 'status' => $initialStatus, 'payment_status' => 'unpaid', 'currency' => 'VND', 'subtotal_minor' => $subtotal, 'shipping_minor' => 30_000, 'discount_minor' => 0, 'total_minor' => $subtotal + 30_000, 'placed_at' => now()->subDays($index), 'created_at' => now()->subDays($index), 'updated_at' => now()]);
            $orderId = $this->requiredId(DB::table('orders')->where('order_number', $orderNumber)->value('id'), 'order');
            // This deterministic namespace represents one line per Order. Catalog
            // ordering can evolve between bootstrap versions, so stale pre-ledger
            // lines are removed only while the owned Order has no downstream evidence.
            if (! DB::table('commerce_payment_records')->where('order_id', $orderId)->exists() && ! DB::table('commerce_fulfillments')->where('order_id', $orderId)->exists()) {
                $staleItems = DB::table('order_items')->where('order_id', $orderId)->where('sku_snapshot', '<>', $variant->sku)->pluck('id');
                if ($staleItems->isNotEmpty()) {
                    DB::table('inventory_reservations')->whereIn('order_item_id', $staleItems)->delete();
                    DB::table('order_items')->whereIn('id', $staleItems)->delete();
                }
                DB::table('orders')->where('id', $orderId)->update(['store_id' => $variant->store_id, 'seller_profile_id' => $variant->seller_profile_id, 'store_name_snapshot' => $variant->store_name, 'subtotal_minor' => $subtotal, 'shipping_minor' => 30_000, 'discount_minor' => 0, 'total_minor' => $subtotal + 30_000, 'updated_at' => now()]);
            }
            DB::table('order_items')->updateOrInsert(['order_id' => $orderId, 'sku_snapshot' => $variant->sku], ['product_id' => $variant->product_id, 'product_variant_id' => $variant->variant_id, 'product_name_snapshot' => $variant->product_name, 'variant_label_snapshot' => $variant->variant_name, 'quantity' => $quantity, 'unit_price_minor' => $variant->price_minor, 'line_total_minor' => $subtotal, 'currency' => 'VND', 'created_at' => now(), 'updated_at' => now()]);
            $itemId = $this->requiredId(DB::table('order_items')->where('order_id', $orderId)->where('sku_snapshot', $variant->sku)->value('id'), 'order item');
            $reservationStatus = $initialStatus === 'cancelled' ? 'released' : 'reserved';
            DB::table('inventory_reservations')->insertOrIgnore(['reference' => $this->uuid(4001 + $index), 'order_id' => $orderId, 'order_item_id' => $itemId, 'product_variant_id' => $variant->variant_id, 'inventory_balance_id' => $variant->balance_id, 'quantity' => $quantity, 'status' => $reservationStatus, 'reserved_at' => now()->subDays($index), 'released_at' => $reservationStatus === 'released' ? now()->subDays($index)->addHour() : null, 'consumed_at' => null, 'created_at' => now(), 'updated_at' => now()]);

            // Normalize only legacy bootstrap-owned Orders that have never entered
            // the new payment/fulfillment ledger. This replaces old direct status
            // seeding with the canonical transitions below without touching user data.
            if (! DB::table('commerce_payment_records')->where('order_id', $orderId)->exists() && ! DB::table('commerce_fulfillments')->where('order_id', $orderId)->exists()) {
                DB::table('orders')->where('id', $orderId)->where('order_number', $orderNumber)->update(['status' => $initialStatus, 'payment_status' => 'unpaid', 'paid_at' => null, 'fulfilled_at' => null, 'updated_at' => now()]);
                DB::table('inventory_reservations')->where('order_id', $orderId)->update(['status' => $reservationStatus, 'released_at' => $reservationStatus === 'released' ? now()->subDays($index)->addHour() : null, 'consumed_at' => null, 'updated_at' => now()]);
            }

            if ($index < 9) {
                app(CommercePaymentService::class)->recordSucceeded($admin, $companyId, $orderId, 'internal_demo', 'LOCAL-SETTLEMENT-'.$orderNumber, 'local-payment-'.$orderNumber);
            }
            $seller = User::query()->whereKey(DB::table('seller_profiles')->where('id', $variant->seller_profile_id)->value('user_id'))->firstOrFail();
            $orderStatus = (string) DB::table('orders')->where('id', $orderId)->value('status');
            if ($index < 6 && $orderStatus === 'pending_payment') {
                app(OrderTransitionService::class)->sellerTransition($seller, $orderId, 'confirm');
                $orderStatus = 'confirmed';
            }
            // Compatibility repair for bootstrap-owned C6 rows that predate the
            // Payment/Fulfillment state machine and were seeded directly as processing.
            // User-created Orders are outside the deterministic ODR-DEMO namespace.
            if ($index < 3 && $orderStatus === 'processing' && ! DB::table('commerce_fulfillments')->where('order_id', $orderId)->exists()) {
                DB::table('orders')->where('id', $orderId)->where('order_number', $orderNumber)->update(['status' => 'confirmed', 'updated_at' => now()]);
                $orderStatus = 'confirmed';
            }
            if ($index < 3 && $orderStatus !== 'fulfilled') {
                $branchId = $this->requiredId(DB::table('inventory_balances as b')->join('inventory_locations as l', 'l.id', '=', 'b.inventory_location_id')->where('b.id', $variant->balance_id)->value('l.branch_id'), 'fulfillment branch');
                app(CommerceFulfillmentService::class)->create($seller, $orderId, $branchId);
                $fulfillmentStatus = (string) DB::table('commerce_fulfillments')->where('order_id', $orderId)->value('status');
                if ($fulfillmentStatus === 'pending') {
                    app(CommerceFulfillmentService::class)->transition($seller, $orderId, 'pack');
                    $fulfillmentStatus = 'packed';
                }
                if ($fulfillmentStatus === 'packed') {
                    app(CommerceFulfillmentService::class)->transition($seller, $orderId, 'ship', 'Local Demo Carrier', 'LOCAL-TRACK-'.$orderNumber);
                    $fulfillmentStatus = 'shipped';
                }
                if ($fulfillmentStatus === 'shipped') {
                    app(CommerceFulfillmentService::class)->transition($seller, $orderId, 'deliver');
                }
            }
        }
        $bootstrapBalanceIds = DB::table('inventory_balances as balance')->join('product_variants as variant', 'variant.id', '=', 'balance.product_variant_id')->join('products as product', 'product.id', '=', 'variant.product_id')
            ->where(static fn ($query) => $query->where('product.code', 'like', 'SV-%')->orWhere('product.code', 'like', 'DEMO-%'))->pluck('balance.id');
        foreach ($bootstrapBalanceIds as $balanceId) {
            $reserved = (int) DB::table('inventory_reservations')->where('inventory_balance_id', $balanceId)->where('status', 'reserved')->sum('quantity');
            $balance = DB::table('inventory_balances')->where('id', $balanceId)->first(['product_variant_id', 'inventory_location_id']);
            if ($balance === null) {
                continue;
            }
            $lastOnHand = DB::table('inventory_movements')->where('product_variant_id', $balance->product_variant_id)->where('inventory_location_id', $balance->inventory_location_id)->latest('id')->value('on_hand_after');
            $changes = ['reserved' => $reserved, 'version' => DB::raw('version + 1'), 'updated_at' => now()];
            if (is_numeric($lastOnHand)) {
                $changes['on_hand'] = (int) $lastOnHand;
            }
            DB::table('inventory_balances')->where('id', $balanceId)->update($changes);
        }
    }

    /** @param list<int> $employeeIds */
    private function payroll(int $companyId, array $employeeIds, int $actorId): void
    {
        $componentDefinitions = [
            'BASE-SALARY-DEMO' => ['name' => 'Lương cơ bản synthetic', 'display_order' => 10, 'system_key' => 'base_salary', 'category' => 'earning', 'calculation_type' => 'fixed', 'taxable' => 'taxable', 'insurance' => 'included'],
            'KPI-TARGET-DEMO' => ['name' => 'KPI mục tiêu synthetic', 'display_order' => 20, 'system_key' => 'kpi_target', 'category' => 'earning', 'calculation_type' => 'fixed', 'taxable' => 'taxable', 'insurance' => 'excluded'],
            'FIXED-ALLOWANCE-DEMO' => ['name' => 'Phụ cấp cố định synthetic', 'display_order' => 30, 'system_key' => 'fixed_allowance', 'category' => 'earning', 'calculation_type' => 'fixed', 'taxable' => 'taxable', 'insurance' => 'excluded'],
            'WEEKDAY-OT-DEMO' => ['name' => 'Tiền tăng ca ngày làm việc', 'display_order' => 40, 'system_key' => 'weekday_overtime_pay', 'category' => 'earning', 'calculation_type' => 'quantity_rate', 'taxable' => 'non_taxable', 'insurance' => 'excluded'],
            'WEEKEND-OT-DEMO' => ['name' => 'Tiền tăng ca cuối tuần', 'display_order' => 41, 'system_key' => 'weekend_overtime_pay', 'category' => 'earning', 'calculation_type' => 'quantity_rate', 'taxable' => 'non_taxable', 'insurance' => 'excluded'],
            'HOLIDAY-OT-DEMO' => ['name' => 'Tiền tăng ca ngày lễ', 'display_order' => 42, 'system_key' => 'holiday_overtime_pay', 'category' => 'earning', 'calculation_type' => 'quantity_rate', 'taxable' => 'non_taxable', 'insurance' => 'excluded'],
            'UNPAID-LEAVE-DEMO' => ['name' => 'Khấu trừ nghỉ không lương', 'display_order' => 80, 'system_key' => 'unpaid_leave_deduction', 'category' => 'deduction', 'calculation_type' => 'quantity_rate', 'taxable' => 'excluded', 'insurance' => 'excluded'],
            'LATE-TIME-DEMO' => ['name' => 'Khấu trừ thời gian đi trễ', 'display_order' => 81, 'system_key' => 'late_time_deduction', 'category' => 'deduction', 'calculation_type' => 'quantity_rate', 'taxable' => 'excluded', 'insurance' => 'excluded'],
        ];
        foreach ($componentDefinitions as $code => $definition) {
            DB::table('salary_components')->updateOrInsert(
                ['company_id' => $companyId, 'code' => $code],
                ['name' => $definition['name'], 'description' => 'LOCAL SYNTHETIC DEMO ONLY — rate do compensation policy cấu hình; quantity lấy từ Payroll Input snapshot.', 'category' => $definition['category'], 'calculation_type' => $definition['calculation_type'], 'value_type' => 'money', 'default_value_minor' => 0, 'currency_code' => 'VND', 'taxable_status' => $definition['taxable'], 'insurance_status' => $definition['insurance'], 'affects_gross' => $definition['category'] === 'earning', 'affects_net' => true, 'display_order' => $definition['display_order'], 'effective_from' => '2026-01-01', 'status' => 'active', 'is_system' => false, 'system_key' => $definition['system_key'], 'version' => 1, 'created_by' => $actorId, 'updated_by' => $actorId, 'created_at' => now(), 'updated_at' => now()],
            );
        }
        $components = DB::table('salary_components')->where('company_id', $companyId)->whereIn('code', array_keys($componentDefinitions))->pluck('id', 'code');
        $references = app(PositionCompensationReference::class);
        foreach ($employeeIds as $employeeId) {
            $position = DB::table('employee_assignments as a')->join('job_positions as p', 'p.id', '=', 'a.job_position_id')->join('organization_units as u', 'u.id', '=', 'a.organization_unit_id')->where('a.employee_id', $employeeId)->where('a.assignment_type', 'primary')->whereNull('a.effective_to')->latest('a.effective_from')->first(['p.career_level', 'p.title', 'u.name as unit_name']);
            if ($position === null) {
                continue;
            }
            $reference = $references->resolve((int) $position->career_level, (string) $position->unit_name, (string) $position->title, '2026-01-01');
            $hourlyRate = max(1, intdiv($reference->baseSalaryMinor, 176));
            foreach ([['BASE-SALARY-DEMO', $reference->baseSalaryMinor, null, 800], ['KPI-TARGET-DEMO', $reference->kpiTargetMinor, null, 1800], ['FIXED-ALLOWANCE-DEMO', $reference->fixedAllowanceMinor, null, 2800], ['WEEKDAY-OT-DEMO', null, intdiv($hourlyRate * 150, 100), 4800], ['WEEKEND-OT-DEMO', null, $hourlyRate * 2, 5800], ['HOLIDAY-OT-DEMO', null, $hourlyRate * 3, 6800], ['UNPAID-LEAVE-DEMO', null, $hourlyRate, 7800], ['LATE-TIME-DEMO', null, $hourlyRate, 8800]] as [$code, $amount, $rate, $offset]) {
                DB::table('employee_salary_component_assignments')->updateOrInsert(
                    ['company_id' => $companyId, 'idempotency_key' => $this->uuid($offset + $employeeId)],
                    ['employee_id' => $employeeId, 'salary_component_id' => $this->requiredId($components[$code] ?? null, $code), 'amount_minor' => $amount, 'rate_minor' => $rate, 'quantity_basis_points' => null, 'currency_code' => 'VND', 'effective_from' => '2026-01-01', 'source' => 'local_demo', 'status' => 'active', 'version' => 1, 'created_by' => $actorId, 'updated_by' => $actorId, 'created_at' => now(), 'updated_at' => now()],
                );
            }
        }

        $makerId = $this->requiredId(User::query()->where('email', 'payroll.manager@local.test')->value('id'), 'payroll manager');
        $this->statutoryProfiles($companyId, $employeeIds, $makerId);
        foreach ([3 => PayrollRunStatus::Finalized, 4 => PayrollRunStatus::Approved, 5 => PayrollRunStatus::Submitted, 6 => PayrollRunStatus::Calculated, 8 => PayrollRunStatus::Finalized] as $month => $target) {
            $this->payrollMonth($companyId, $month, $makerId, $actorId, $target);
        }
        $this->payrollDelivery($companyId, $makerId, $actorId);
    }

    private function resetMutableDemoPayroll(int $companyId, string $periodCode): void
    {
        $period = DB::table('payroll_periods')->where('company_id', $companyId)->where('code', $periodCode)->first(['id']);
        if ($period === null) {
            return;
        }
        $run = DB::table('payroll_runs')->where('company_id', $companyId)->where('payroll_period_id', $period->id)->first(['id', 'status']);
        if ($run !== null
            && DB::table('payroll_items')->where('company_id', $companyId)->where('payroll_run_id', $run->id)->where('insurance_employee_total_minor', '>', 0)->exists()
            && DB::table('payroll_employee_inputs')->where('company_id', $companyId)->where('payroll_input_snapshot_id', DB::table('payroll_runs')->where('id', $run->id)->value('payroll_input_snapshot_id'))->where('approved_overtime_minutes', '>', 0)->exists()) {
            return;
        }
        if ($run !== null && ! in_array($run->status, ['draft', 'calculated', 'validation_failed', 'failed', 'rejected'], true)) {
            return;
        }
        DB::transaction(function () use ($companyId, $period, $run): void {
            if ($run !== null) {
                DB::table('payroll_item_components')->where('company_id', $companyId)->where('payroll_run_id', $run->id)->delete();
                DB::table('payroll_validation_issues')->where('company_id', $companyId)->where('payroll_run_id', $run->id)->delete();
                DB::table('payroll_items')->where('company_id', $companyId)->where('payroll_run_id', $run->id)->delete();
                DB::table('payroll_run_histories')->where('company_id', $companyId)->where('payroll_run_id', $run->id)->delete();
                DB::table('payroll_runs')->where('company_id', $companyId)->where('id', $run->id)->delete();
            }
            $snapshotIds = DB::table('payroll_input_snapshots')->where('company_id', $companyId)->where('payroll_period_id', $period->id)->pluck('id');
            DB::table('payroll_input_source_records')->where('company_id', $companyId)->whereIn('payroll_input_snapshot_id', $snapshotIds)->delete();
            DB::table('payroll_employee_inputs')->where('company_id', $companyId)->whereIn('payroll_input_snapshot_id', $snapshotIds)->delete();
            DB::table('payroll_input_snapshots')->where('company_id', $companyId)->whereIn('id', $snapshotIds)->delete();
            DB::table('payroll_periods')->where('company_id', $companyId)->where('id', $period->id)->update(['status' => 'collecting', 'version' => DB::raw('version + 1'), 'updated_at' => now()]);
        });
    }

    private function payrollDelivery(int $companyId, int $makerId, int $checkerId): void
    {
        $run = PayrollRun::query()->where('company_id', $companyId)->where('status', PayrollRunStatus::Finalized)->orderByDesc('period_end')->first();
        if (! $run instanceof PayrollRun) {
            return;
        }
        $employees = DB::table('payroll_items')->where('company_id', $companyId)->where('payroll_run_id', $run->id)->get(['employee_id', 'employee_name_snapshot']);
        $accounts = app(EmployeePaymentAccountService::class);
        foreach ($employees as $index => $employee) {
            $employeeId = is_numeric($employee->employee_id ?? null) ? (int) $employee->employee_id : 0;
            if ($employeeId < 1 || DB::table('employee_payment_accounts')->where('company_id', $companyId)->where('employee_id', $employeeId)->exists()) {
                continue;
            }
            $accountId = $accounts->save($companyId, null, ['employee_id' => $employeeId, 'payment_method' => 'bank_transfer', 'bank_code' => 'DEMO', 'bank_name' => 'Ngân hàng Synthetic Việt Nam', 'branch_code' => 'HCM', 'account_number' => '9900'.str_pad((string) ($index + 1), 8, '0', STR_PAD_LEFT), 'account_holder_name' => is_string($employee->employee_name_snapshot ?? null) ? mb_strtoupper($employee->employee_name_snapshot) : 'NHAN VIEN DEMO', 'currency' => 'VND', 'is_primary' => true, 'effective_from' => '2026-01-01', 'effective_to' => null, 'status' => 'active'], $makerId);
            $accounts->verify($companyId, $accountId, $checkerId);
        }
        $payslips = app(PayslipGenerationService::class);
        foreach (DB::table('payroll_items')->where('company_id', $companyId)->where('payroll_run_id', $run->id)->orderBy('id')->limit(2)->pluck('id') as $index => $itemId) {
            if (is_numeric($itemId) && ! DB::table('payroll_payslips')->where('company_id', $companyId)->where('payroll_item_id', $itemId)->exists()) {
                // Scope the deterministic request key to the immutable run. A fixed
                // key would incorrectly return a Payslip from an older finalized run.
                $payslips->requestItem($companyId, $run->id, (int) $itemId, $makerId, $this->uuid(1_100_000 + ($run->id * 100) + $index));
            }
        }
        foreach (DB::table('payroll_payslips')->where('company_id', $companyId)->where('payroll_run_id', $run->id)->whereIn('status', ['pending', 'generating'])->orderBy('id')->limit(2)->pluck('id') as $payslipId) {
            if (is_numeric($payslipId)) {
                $payslips->generate((int) $payslipId);
            }
        }
        $payments = app(PayrollPaymentBatchService::class);
        $batchId = $payments->create($companyId, $run->id, $makerId, $this->uuid(1110));
        $batchStatus = DB::table('payroll_payment_batches')->where('id', $batchId)->value('status');
        if ($batchStatus === 'draft') {
            $payments->validate($companyId, $batchId, $makerId);
            $payments->review($companyId, $batchId, $makerId);
            $payments->approve($companyId, $batchId, $checkerId);
            $payments->release($companyId, $batchId, $makerId);
        }
        $journalId = $this->payrollAccounting($companyId, $run->id, $makerId, $checkerId);
        $this->payrollErp($companyId, $journalId, $makerId, $checkerId);
        $this->payrollAdjustment($companyId, $run->id, $makerId);
        $this->payrollAnnualTaxAndGovernment($companyId, $run->id, $makerId, $checkerId);
    }

    /** @param list<int> $employeeIds */
    private function statutoryProfiles(int $companyId, array $employeeIds, int $actorId): void
    {
        $insurance = app(InsuranceManagementService::class);
        $insurancePolicy = DB::table('insurance_policies')->where('company_id', $companyId)->where('code', 'LOCAL-VN-INS-2026')->value('id');
        if (! is_numeric($insurancePolicy)) {
            $insurancePolicy = $insurance->savePolicy($companyId, null, [
                'code' => 'LOCAL-VN-INS-2026', 'name' => 'Bảo hiểm bắt buộc Việt Nam — local configured demo', 'version' => 1,
                'effective_from' => '2026-01-01', 'effective_to' => null, 'status' => 'active', 'currency_code' => 'VND', 'jurisdiction' => 'VN',
                'source_reference' => 'LOCAL-DEMO-CONFIG; verify against authoritative payroll advice before production', 'source_verified_at' => '2026-08-28',
                'rounding_rule' => 'half_up', 'note' => 'Synthetic operational configuration; not legal advice.',
                'rates' => [
                    ['insurance_type' => 'social', 'employee_rate_basis_points' => 800, 'employer_rate_basis_points' => 1750],
                    ['insurance_type' => 'health', 'employee_rate_basis_points' => 150, 'employer_rate_basis_points' => 300],
                    ['insurance_type' => 'unemployment', 'employee_rate_basis_points' => 100, 'employer_rate_basis_points' => 100],
                ],
            ], $actorId);
        }

        $tax = app(TaxManagementService::class);
        $taxPolicy = DB::table('tax_policies')->where('company_id', $companyId)->where('code', 'LOCAL-PIT-DEMO')->value('id');
        $configuredTaxPolicy = is_numeric($taxPolicy)
            && (int) DB::table('tax_policies')->where('id', $taxPolicy)->value('version') === 2
            && DB::table('tax_policy_brackets')->where('tax_policy_id', $taxPolicy)->count() === 5;
        $taxPolicyId = $configuredTaxPolicy ? (int) $taxPolicy : $tax->savePolicy($companyId, is_numeric($taxPolicy) ? (int) $taxPolicy : null, [
            'code' => 'LOCAL-PIT-DEMO', 'name' => 'PIT progressive configured demo 2026', 'version' => 2,
            'effective_from' => '2026-01-01', 'effective_to' => null, 'currency_code' => 'VND', 'jurisdiction' => 'VN',
            'source_reference' => 'Luật 109/2025/QH15; local demo configuration requires production legal verification', 'source_verified_at' => '2026-08-28',
            'tax_method' => 'progressive', 'residency_group' => 'resident', 'rounding_rule' => 'half_up', 'floor_taxable_income_at_zero' => true,
            'flat_rate_basis_points' => null, 'status' => 'active', 'note' => 'Synthetic operational example; not a statutory filing claim.',
            'brackets' => [
                ['lower_bound_minor' => 0, 'upper_bound_minor' => 10_000_000, 'rate_basis_points' => 500],
                ['lower_bound_minor' => 10_000_000, 'upper_bound_minor' => 30_000_000, 'rate_basis_points' => 1000],
                ['lower_bound_minor' => 30_000_000, 'upper_bound_minor' => 60_000_000, 'rate_basis_points' => 2000],
                ['lower_bound_minor' => 60_000_000, 'upper_bound_minor' => 100_000_000, 'rate_basis_points' => 3000],
                ['lower_bound_minor' => 100_000_000, 'upper_bound_minor' => null, 'rate_basis_points' => 3500],
            ],
            'deductions' => [
                ['code' => 'PERSONAL', 'name' => 'Giảm trừ cá nhân configured demo', 'deduction_type' => 'personal', 'amount_minor' => 15_500_000],
                ['code' => 'DEPENDENT', 'name' => 'Giảm trừ người phụ thuộc configured demo', 'deduction_type' => 'dependent', 'amount_minor' => 6_200_000],
                ['code' => 'EMPLOYEE-INSURANCE', 'name' => 'Bảo hiểm người lao động', 'deduction_type' => 'employee_insurance', 'amount_minor' => null],
            ],
        ], $actorId);

        foreach ($employeeIds as $index => $employeeId) {
            $employmentStatus = DB::table('employees')->where('company_id', $companyId)->where('id', $employeeId)->value('employment_status');
            $insuranceProfile = DB::table('employee_insurance_profiles')->where('company_id', $companyId)->where('employee_id', $employeeId)->where('effective_from', '2026-01-01')->value('id');
            $insurance->saveProfile($companyId, $employeeId, is_numeric($insuranceProfile) ? (int) $insuranceProfile : null, [
                'insurance_policy_id' => (int) $insurancePolicy, 'participation_status' => $employmentStatus === 'probation' ? 'not_participating' : 'participating',
                'effective_from' => '2026-01-01', 'effective_to' => null, 'insurance_number' => 'LOCAL-BH-'.str_pad((string) $employeeId, 8, '0', STR_PAD_LEFT),
                'contribution_base_override_minor' => null, 'currency_code' => 'VND', 'exemption_reason' => $employmentStatus === 'probation' ? 'Local demo probation profile.' : null,
            ], $actorId);
            $taxProfile = DB::table('employee_tax_profiles')->where('company_id', $companyId)->where('employee_id', $employeeId)->where('effective_from', '2026-01-01')->value('id');
            $tax->saveProfile($companyId, $employeeId, is_numeric($taxProfile) ? (int) $taxProfile : null, [
                'tax_policy_id' => $taxPolicyId, 'tax_status' => 'taxable', 'residency_status' => 'resident', 'effective_from' => '2026-01-01', 'effective_to' => null,
                'tax_identification_number' => 'LOCAL-PIT-'.str_pad((string) $employeeId, 8, '0', STR_PAD_LEFT), 'personal_deduction_eligible' => true,
                'withholding_method' => 'policy', 'base_override_minor' => null, 'exemption_reason' => null,
            ], $actorId);
            if ($index % 5 === 0) {
                DB::table('employee_tax_dependents')->updateOrInsert(
                    ['company_id' => $companyId, 'employee_id' => $employeeId, 'reference_number' => 'LOCAL-DEPENDENT-'.$employeeId],
                    ['name' => 'Người phụ thuộc synthetic '.$employeeId, 'relationship' => 'child', 'effective_from' => '2026-01-01', 'effective_to' => null, 'deduction_eligible' => true, 'approval_status' => 'approved', 'approved_by' => $actorId, 'approved_at' => now(), 'created_at' => now(), 'updated_at' => now()],
                );
            }
        }
    }

    private function payrollAccounting(int $companyId, int $runId, int $makerId, int $checkerId): int
    {
        $admin = app(AccountingAdministrationService::class);
        $expense = DB::table('accounting_accounts')->where('company_id', $companyId)->where('code', '6421-DEMO')->value('id');
        $expenseId = is_numeric($expense) ? (int) $expense : $admin->saveAccount($companyId, null, ['code' => '6421-DEMO', 'name' => 'Chi phí lương synthetic', 'account_type' => 'expense', 'normal_balance' => 'debit', 'currency' => 'VND', 'allow_posting' => true, 'status' => 'active', 'effective_from' => '2026-01-01', 'effective_to' => null, 'parent_id' => null], $makerId);
        $payable = DB::table('accounting_accounts')->where('company_id', $companyId)->where('code', '3341-DEMO')->value('id');
        $payableId = is_numeric($payable) ? (int) $payable : $admin->saveAccount($companyId, null, ['code' => '3341-DEMO', 'name' => 'Phải trả nhân viên synthetic', 'account_type' => 'liability', 'normal_balance' => 'credit', 'currency' => 'VND', 'allow_posting' => true, 'status' => 'active', 'effective_from' => '2026-01-01', 'effective_to' => null, 'parent_id' => null], $makerId);
        $policy = DB::table('payroll_accounting_policies')->where('company_id', $companyId)->where('code', 'LOCAL-PAYROLL-DEMO')->value('id');
        $policyId = is_numeric($policy) ? (int) $policy : $admin->savePolicy($companyId, null, ['code' => 'LOCAL-PAYROLL-DEMO', 'name' => 'Chính sách kế toán lương synthetic', 'version' => 1, 'currency' => 'VND', 'aggregation_strategy' => 'company', 'effective_from' => '2026-01-01', 'effective_to' => null, 'rounding_rule' => 'minor_unit', 'status' => 'active', 'note' => 'LOCAL DEMO ONLY'], $makerId);
        $mappings = [
            ['earning_component', 'BASE-SALARY-DEMO', $expenseId, null],
            ['earning_component', 'KPI-TARGET-DEMO', $expenseId, null],
            ['earning_component', 'FIXED-ALLOWANCE-DEMO', $expenseId, null],
            ['earning_component', 'WEEKDAY-OT-DEMO', $expenseId, null],
            ['earning_component', 'WEEKEND-OT-DEMO', $expenseId, null],
            ['earning_component', 'HOLIDAY-OT-DEMO', $expenseId, null],
            ['earning_component', 'BRANCH_BONUS', $expenseId, null],
            ['other_deduction', 'UNPAID-LEAVE-DEMO', null, $payableId],
            ['other_deduction', 'LATE-TIME-DEMO', null, $payableId],
            ['employee_insurance', 'EMPLOYEE_INSURANCE', null, $payableId],
            ['employer_insurance_expense', 'EMPLOYER_INSURANCE_EXPENSE', $expenseId, null],
            ['employer_insurance_liability', 'EMPLOYER_INSURANCE_LIABILITY', null, $payableId],
            ['personal_income_tax', 'PERSONAL_INCOME_TAX', null, $payableId],
            ['net_pay', 'NET_PAY', null, $payableId],
        ];
        foreach ($mappings as [$type, $code, $debit, $credit]) {
            if (! DB::table('payroll_accounting_mappings')->where('company_id', $companyId)->where('accounting_policy_id', $policyId)->where('source_type', $type)->where('source_code', $code)->exists()) {
                $admin->saveMapping($companyId, null, ['accounting_policy_id' => $policyId, 'source_type' => $type, 'source_code' => $code, 'debit_account_id' => $debit, 'credit_account_id' => $credit, 'cost_center_id' => null, 'organization_unit_id' => null, 'priority' => 100, 'effective_from' => '2026-01-01', 'effective_to' => null, 'status' => 'active'], $makerId);
            }
        }
        $journals = app(PayrollAccountingJournalService::class);
        $periodEnd = DB::table('payroll_runs')->where('company_id', $companyId)->where('id', $runId)->value('period_end');
        $postingDate = is_string($periodEnd) ? CarbonImmutable::parse($periodEnd)->addDays(3)->toDateString() : '2026-04-03';
        $id = $journals->create($companyId, $runId, $postingDate, $makerId, $this->uuid(1_120_000 + $runId));
        $status = DB::table('payroll_accounting_journals')->where('id', $id)->value('status');
        if ($status === 'draft') {
            $journals->validate($companyId, $id, $makerId);
            $journals->review($companyId, $id, $makerId);
            $journals->approve($companyId, $id, $checkerId);
            $journals->post($companyId, $id, $makerId);
        }

        return $id;
    }

    private function payrollErp(int $companyId, int $journalId, int $makerId, int $checkerId): void
    {
        $admin = app(ErpAdministrationService::class);
        $connection = DB::table('erp_connections')->where('company_id', $companyId)->where('name', 'ERP Generic CSV Synthetic')->value('id');
        $connectionId = is_numeric($connection) ? (int) $connection : $admin->execute(new ErpAdministrationData($companyId, 'connection', 'create', null, $makerId, ['name' => 'ERP Generic CSV Synthetic', 'provider' => 'generic_csv', 'endpoint_reference' => null, 'credential_reference' => null, 'status' => 'active']));
        foreach (DB::table('payroll_accounting_journal_lines')->where('company_id', $companyId)->where('journal_id', $journalId)->pluck('account_code_snapshot')->unique() as $code) {
            if (! is_string($code) || DB::table('erp_mapping_rules')->where('company_id', $companyId)->where('erp_connection_id', $connectionId)->where('source_account_code', $code)->exists()) {
                continue;
            }
            $admin->execute(new ErpAdministrationData($companyId, 'mapping', 'create', null, $makerId, ['erp_connection_id' => $connectionId, 'source_account_code' => $code, 'external_account_code' => 'ERP-'.$code, 'effective_from' => '2026-01-01', 'effective_to' => null, 'status' => 'active']));
        }
        $exports = app(ErpExportService::class);
        $result = $exports->create($companyId, $connectionId, $journalId, $makerId, $this->uuid(1130));
        if ($result->status === 'draft') {
            $exports->validate($companyId, $result->exportId, $checkerId);
            $exports->execute($companyId, $result->exportId, $makerId);
        }
    }

    private function payrollAdjustment(int $companyId, int $runId, int $actorId): void
    {
        DB::table('payroll_adjustment_reasons')->updateOrInsert(['company_id' => $companyId, 'code' => 'LOCAL-OT-CORRECTION'], ['uuid' => $this->uuid(1140), 'name' => 'Điều chỉnh tăng ca synthetic', 'category' => 'earning_correction', 'created_by' => $actorId, 'created_at' => now(), 'updated_at' => now()]);
        $reason = $this->requiredId(DB::table('payroll_adjustment_reasons')->where('company_id', $companyId)->where('code', 'LOCAL-OT-CORRECTION')->value('id'), 'adjustment reason');
        $item = $this->requiredId(DB::table('payroll_items')->where('company_id', $companyId)->where('payroll_run_id', $runId)->orderBy('id')->value('id'), 'payroll item');
        if (! DB::table('payroll_adjustment_requests')->where('company_id', $companyId)->where('idempotency_key', $this->uuid(1141))->exists()) {
            app(PayrollAdjustmentManagementService::class)->create(new CreatePayrollAdjustmentCommand($companyId, $item, $reason, 'earning_correction', '2026-04-05', null, 'Synthetic overtime correction; no external delivery.', null, $this->uuid(1141), $actorId));
        }
    }

    private function payrollAnnualTaxAndGovernment(int $companyId, int $runId, int $makerId, int $checkerId): void
    {
        $employeeId = $this->requiredId(DB::table('payroll_items')->where('company_id', $companyId)->where('payroll_run_id', $runId)->orderBy('id')->value('employee_id'), 'annual tax employee');
        $tax = app(TaxManagementService::class);
        $policy = DB::table('tax_policies')->where('company_id', $companyId)->where('code', 'LOCAL-PIT-DEMO')->value('id');
        $policyId = is_numeric($policy) ? (int) $policy : $tax->savePolicy($companyId, null, ['code' => 'LOCAL-PIT-DEMO', 'name' => 'Thuế TNCN synthetic local', 'version' => 1, 'effective_from' => '2026-01-01', 'effective_to' => null, 'currency_code' => 'VND', 'tax_method' => 'progressive', 'residency_group' => 'resident', 'rounding_rule' => 'half_up', 'floor_taxable_income_at_zero' => true, 'flat_rate_basis_points' => null, 'status' => 'active', 'note' => 'Synthetic governance example only', 'brackets' => [['lower_bound_minor' => 0, 'upper_bound_minor' => null, 'rate_basis_points' => 1000]], 'deductions' => []], $makerId);
        if (! DB::table('employee_tax_profiles')->where('company_id', $companyId)->where('employee_id', $employeeId)->exists()) {
            $tax->saveProfile($companyId, $employeeId, null, ['tax_policy_id' => $policyId, 'tax_status' => 'taxable', 'residency_status' => 'resident', 'effective_from' => '2026-01-01', 'effective_to' => null, 'tax_identification_number' => 'SYNTHETIC-TAX-ID', 'personal_deduction_eligible' => true, 'withholding_method' => 'policy', 'base_override_minor' => null, 'exemption_reason' => null], $makerId);
        }
        $annual = app(PayrollAnnualTaxReconciliationService::class);
        $existing = DB::table('payroll_annual_tax_reconciliations')->where('company_id', $companyId)->where('idempotency_key', $this->uuid(1150))->value('id');
        $annualId = is_numeric($existing) ? (int) $existing : $annual->create(new CreateAnnualTaxReconciliationCommand($companyId, $employeeId, 2026, 'VN', 'VND', $this->uuid(1150), $makerId))->id;
        $status = DB::table('payroll_annual_tax_reconciliations')->where('id', $annualId)->value('status');
        if ($status === 'draft') {
            $annual->collect($companyId, $annualId, $makerId);
            app(PayrollAnnualTaxOperationsService::class)->calculate($companyId, $annualId, $makerId);
            app(PayrollAnnualTaxOperationsService::class)->validate($companyId, $annualId, $makerId);
        }
        $lifecycle = app(PayrollAnnualTaxLifecycleService::class);
        foreach ([['validated', 'submit', $makerId], ['submitted', 'review', $checkerId]] as [$from, $action, $actor]) {
            if (DB::table('payroll_annual_tax_reconciliations')->where('id', $annualId)->value('status') === $from) {
                $lifecycle->transition($companyId, $annualId, $action, $actor);
            }
        }
        $reviewerId = $this->requiredId(User::query()->where('email', 'hr.manager@local.test')->value('id'), 'annual tax reviewer');
        if (DB::table('payroll_annual_tax_reconciliations')->where('id', $annualId)->value('status') === 'reviewed') {
            $lifecycle->transition($companyId, $annualId, 'approve', $reviewerId);
        }
        if (DB::table('payroll_annual_tax_reconciliations')->where('id', $annualId)->value('status') === 'approved') {
            $lifecycle->transition($companyId, $annualId, 'finalize', $makerId);
        }
        if (! DB::table('government_tax_filings')->where('company_id', $companyId)->where('annual_tax_reconciliation_id', $annualId)->exists()) {
            $filings = app(GovernmentTaxFilingService::class);
            $filing = $filings->create($companyId, $annualId, 'annual_pit_employer_declaration', $makerId, $this->uuid(1160));
            $filings->generate($companyId, $filing->filingId, $makerId);
            $filings->validate($companyId, $filing->filingId, $makerId);
            $filings->approve($companyId, $filing->filingId, $checkerId);
        }
    }

    private function payrollMonth(int $companyId, int $month, int $makerId, int $checkerId, PayrollRunStatus $target): void
    {
        $start = new CarbonImmutable(sprintf('2026-%02d-01', $month));
        $end = $month === 8 ? new CarbonImmutable('2026-08-12') : $start->endOfMonth();
        $code = sprintf('LOCAL-2026-%02d', $month);
        $base = 900 + ($month * 10);
        $period = PayrollPeriod::query()->where('company_id', $companyId)->where('code', $code)->first();
        if (! $period instanceof PayrollPeriod) {
            $period = app(PayrollPeriodOperationsService::class)->create($companyId, [
                'code' => $code,
                'name' => 'Kỳ lương synthetic tháng '.sprintf('%02d/2026', $month),
                'start' => $start->toDateString(),
                'end' => $end->toDateString(),
                'pay_date' => $end->addDays(3)->toDateString(),
                'status' => 'collecting',
                'note' => 'LOCAL DEMO ONLY — no external payment, ERP, government, or bank traffic.',
            ], $makerId, $this->uuid($base + 1));
        }
        $existingRun = PayrollRun::query()->where('company_id', $companyId)->where('payroll_period_id', $period->id)->first();
        $snapshot = $existingRun instanceof PayrollRun
            ? PayrollInputSnapshot::query()->where('company_id', $companyId)->find($existingRun->payroll_input_snapshot_id)
            : PayrollInputSnapshot::query()->where('company_id', $companyId)->where('payroll_period_id', $period->id)->where('idempotency_key', $this->uuid($base + 2))->first();
        if (! $snapshot instanceof PayrollInputSnapshot) {
            $snapshot = app(PayrollInputOperationsService::class)->capture($companyId, $period->id, $makerId, $this->uuid($base + 2), 'Synthetic local workforce snapshot.');
        }
        if ($snapshot->status !== 'ready') {
            $snapshot = app(PayrollInputOperationsService::class)->validate($companyId, $snapshot->id, $makerId, $this->uuid($base + 3));
        }
        if ($snapshot->status !== 'ready') {
            return;
        }
        $run = PayrollRun::query()->where('company_id', $companyId)->where('payroll_period_id', $period->id)->first();
        if (! $run instanceof PayrollRun) {
            $run = app(PayrollInputOperationsService::class)->createRun($companyId, $snapshot->id, $makerId, $this->uuid($base + 4));
        }
        $run->refresh();
        if ((int) $run->calculation_version === 0) {
            $run = app(PayrollRunService::class)->calculate($companyId, $run->id, $run->version, $this->uuid($base + 5), $makerId);
        }
        $transitions = match ($target) {
            PayrollRunStatus::Submitted => [PayrollRunStatus::Submitted],
            PayrollRunStatus::Approved => [PayrollRunStatus::Submitted, PayrollRunStatus::Approved],
            PayrollRunStatus::Finalized => [PayrollRunStatus::Submitted, PayrollRunStatus::Approved, PayrollRunStatus::Finalized],
            default => [],
        };
        foreach ($transitions as $index => $status) {
            $run->refresh();
            if ($run->status === $status || $run->status === PayrollRunStatus::Finalized) {
                continue;
            }
            if (($status === PayrollRunStatus::Submitted && $run->status !== PayrollRunStatus::Calculated)
                || ($status === PayrollRunStatus::Approved && $run->status !== PayrollRunStatus::Submitted)
                || ($status === PayrollRunStatus::Finalized && $run->status !== PayrollRunStatus::Approved)) {
                continue;
            }
            $actor = $status === PayrollRunStatus::Submitted ? $makerId : $checkerId;
            $run = app(PayrollRunService::class)->transition($companyId, $run->id, $status, $run->version, $this->uuid($base + 6 + $index), $actor);
        }
    }

    private function positionLevels(int $companyId): void
    {
        $levels = ['DEPT-MGR' => 6, 'BE-DEV' => 3, 'FE-DEV' => 3, 'MOB-DEV' => 3, 'DEVOPS-ENG' => 4, 'HR-SPEC' => 3, 'ACCOUNTANT' => 3, 'SALES-EXEC' => 2, 'MKT-SPEC' => 3];
        foreach ($levels as $code => $level) {
            DB::table('job_positions')->where('company_id', $companyId)->where('code', $code)->update(['career_level' => $level]);
        }
    }

    private function actors(int $companyId): void
    {
        foreach ([
            ['hr.manager@local.test', 'HR Manager Demo', 'HR Manager'],
            ['hr.staff@local.test', 'HR Staff Demo', 'HR Admin'],
            ['payroll.manager@local.test', 'Payroll Manager Demo', 'C&B'],
            ['recruiter@local.test', 'Recruiter Demo', 'Recruiter'],
            ['line.manager@local.test', 'Line Manager Demo', 'Manager'],
            ['employee@local.test', 'Employee Demo', 'Employee'],
            ['auditor@local.test', 'Auditor Demo', 'Viewer'],
        ] as [$email, $name, $roleName]) {
            $user = User::query()->firstOrNew(['email' => $email]);
            $user->name = $name;
            if (! $user->exists) {
                $user->password = Hash::make('LocalDemoActor123!');
            }
            $user->save();
            $user->forceFill(['email_verified_at' => $user->email_verified_at ?? now()])->save();
            $role = Role::findByName($roleName, 'web');
            $user->syncRoles([$role]);
            DB::table('company_user_access')->updateOrInsert(['company_id' => $companyId, 'user_id' => $user->id], ['created_at' => now(), 'updated_at' => now()]);
            if ($email === 'employee@local.test') {
                DB::table('employees')->where('company_id', $companyId)->where('employee_code', 'E0002')->whereNull('user_id')->update(['user_id' => $user->id, 'updated_at' => now()]);
            }
        }
    }

    /** @param list<int> $employeeIds */
    private function workforce(int $companyId, array $employeeIds, int $actorId): void
    {
        DB::table('work_calendars')->updateOrInsert(['company_id' => $companyId, 'code' => 'LOCAL-VN-5D'], ['name' => 'Lịch làm việc Việt Nam 5 ngày (Demo)', 'timezone' => 'Asia/Ho_Chi_Minh', 'weekly_work_minutes' => 2400, 'status' => 'active', 'effective_from' => '2026-01-01', 'version' => 1, 'created_at' => now(), 'updated_at' => now()]);
        $calendarId = $this->requiredId(DB::table('work_calendars')->where('company_id', $companyId)->where('code', 'LOCAL-VN-5D')->value('id'), 'work calendar');
        DB::table('work_shifts')->updateOrInsert(['company_id' => $companyId, 'code' => 'LOCAL-OFFICE'], ['name' => 'Ca hành chính 08:30–17:30 (Demo)', 'timezone' => 'Asia/Ho_Chi_Minh', 'starts_at' => '08:30:00', 'ends_at' => '17:30:00', 'crosses_midnight' => false, 'planned_work_minutes' => 480, 'grace_in_minutes' => 5, 'grace_out_minutes' => 5, 'rounding_rule' => 'none', 'minimum_half_day_minutes' => 240, 'minimum_full_day_minutes' => 480, 'status' => 'active', 'version' => 1, 'rules' => json_encode(['synthetic_demo' => true], JSON_THROW_ON_ERROR), 'created_at' => now(), 'updated_at' => now()]);
        $shiftId = $this->requiredId(DB::table('work_shifts')->where('company_id', $companyId)->where('code', 'LOCAL-OFFICE')->value('id'), 'work shift');
        foreach (range(1, 7) as $weekday) {
            DB::table('work_calendar_days')->updateOrInsert(
                ['work_calendar_id' => $calendarId, 'weekday' => $weekday],
                ['is_working_day' => $weekday <= 5, 'planned_minutes' => $weekday <= 5 ? 480 : 0, 'default_shift_id' => $weekday <= 5 ? $shiftId : null, 'created_at' => now(), 'updated_at' => now()],
            );
        }

        foreach ($employeeIds as $index => $employeeId) {
            DB::table('employee_work_profiles')->updateOrInsert(['company_id' => $companyId, 'employee_id' => $employeeId, 'effective_from' => '2026-01-01'], ['timezone' => 'Asia/Ho_Chi_Minh', 'work_calendar_id' => $calendarId, 'default_shift_id' => $shiftId, 'weekly_minutes' => 2400, 'daily_minutes' => 480, 'fte_basis_points' => 10000, 'attendance_required' => true, 'overtime_eligible' => true, 'leave_eligible' => true, 'version' => 1, 'created_at' => now(), 'updated_at' => now()]);
            foreach ([3, 4, 5, 6, 7, 8, 10] as $month) {
                $businessDay = 0;
                for ($day = 1; $day <= 20 && $businessDay < 12; $day++) {
                    $date = CarbonImmutable::parse(sprintf('2026-%02d-%02d', $month, $day));
                    if (! $date->isWeekday()) {
                        continue;
                    }
                    $businessDay++;
                    $pattern = ($employeeId + $businessDay + $month) % 17;
                    $late = match ($pattern) {
                        0 => 55, 1 => 30, 2, 3 => 15, 4, 5, 6 => 7, default => 0
                    };
                    $early = ($employeeId + $businessDay + $month) % 29 === 0 ? 30 : 0;
                    DB::table('attendance_records')->updateOrInsert(
                        ['company_id' => $companyId, 'employee_id' => $employeeId, 'work_date' => $date->toDateString()],
                        ['work_shift_id' => $shiftId, 'check_in_at' => $date->setTime(8, 30)->addMinutes($late), 'check_out_at' => $date->setTime(17, 30)->subMinutes($early), 'source' => 'local_demo', 'scheduled_minutes' => 480, 'worked_minutes' => max(0, 480 - $late - $early), 'break_minutes' => 60, 'late_minutes' => $late, 'early_leave_minutes' => $early, 'overtime_minutes' => 0, 'attendance_status' => $late > 0 ? 'late' : ($early > 0 ? 'early_leave' : 'present'), 'presence_status' => 'present', 'approval_status' => 'approved', 'calculation_status' => 'calculated', 'anomalies' => json_encode(array_values(array_filter([$late > 0 ? 'late_arrival' : null, $early > 0 ? 'early_leave' : null])), JSON_THROW_ON_ERROR), 'worked_hours' => number_format(max(0, 480 - $late - $early) / 60, 2, '.', ''), 'overtime_hours' => '0.00', 'version' => 1, 'created_at' => now(), 'updated_at' => now()],
                    );
                }
            }
        }

        DB::table('overtime_policies')->updateOrInsert(
            ['company_id' => $companyId, 'code' => 'LOCAL-OT-STANDARD'],
            ['name' => 'Tăng ca ngày làm việc (Demo)', 'minimum_minutes' => 30, 'rounding_rule' => 'nearest_15', 'daily_limit_minutes' => 240, 'weekly_limit_minutes' => 720, 'requires_preapproval' => true, 'eligible_day_types' => json_encode(['working_day'], JSON_THROW_ON_ERROR), 'status' => 'active', 'effective_from' => '2026-01-01', 'effective_to' => null, 'version' => 1, 'created_at' => now(), 'updated_at' => now()],
        );
        $overtimePolicyId = $this->requiredId(DB::table('overtime_policies')->where('company_id', $companyId)->where('code', 'LOCAL-OT-STANDARD')->value('id'), 'overtime policy');
        foreach ($employeeIds as $employeeId) {
            DB::table('employee_overtime_policy_assignments')->updateOrInsert(
                ['company_id' => $companyId, 'idempotency_key' => $this->uuid(3800 + $employeeId)],
                ['employee_id' => $employeeId, 'overtime_policy_id' => $overtimePolicyId, 'effective_from' => '2026-01-01', 'effective_to' => null, 'version' => 1, 'created_at' => now(), 'updated_at' => now()],
            );
        }
        foreach (array_slice($employeeIds, 0, 8) as $index => $employeeId) {
            $minutes = $index % 3 === 0 ? 120 : 60;
            $workDate = CarbonImmutable::parse('2026-08-'.str_pad((string) (3 + $index), 2, '0', STR_PAD_LEFT));
            $idempotencyKey = $this->uuid(3900 + $employeeId);
            DB::table('overtime_requests')->updateOrInsert(
                ['company_id' => $companyId, 'idempotency_key' => $idempotencyKey],
                ['employee_id' => $employeeId, 'overtime_policy_id' => $overtimePolicyId, 'work_date' => $workDate->toDateString(), 'starts_at' => $workDate->setTime(17, 30)->utc(), 'ends_at' => $workDate->setTime(17, 30)->addMinutes($minutes)->utc(), 'requested_minutes' => $minutes, 'approved_minutes' => $minutes, 'actual_minutes' => $minutes, 'payable_minutes' => $minutes, 'reason' => $index % 2 === 0 ? 'Hoàn tất bản phát hành theo kế hoạch (local demo).' : 'Xử lý đơn hàng cuối ngày (local demo).', 'status' => 'approved', 'approver_id' => $actorId, 'version' => 3, 'calculation_status' => 'reconciled', 'calculation_version' => 1, 'reviewed_by' => $actorId, 'reviewed_at' => now(), 'review_comment' => 'Đã đối chiếu chấm công local demo.', 'created_at' => now(), 'updated_at' => now()],
            );
        }

        DB::table('leave_types')->updateOrInsert(['company_id' => $companyId, 'code' => 'ANNUAL-DEMO'], ['name' => 'Nghỉ phép năm (Demo)', 'accrual_policy' => json_encode(['method' => 'annual', 'synthetic_demo' => true], JSON_THROW_ON_ERROR), 'carry_forward_policy' => json_encode(['maximum_minutes' => 960], JSON_THROW_ON_ERROR), 'is_paid' => true, 'created_at' => now(), 'updated_at' => now()]);
        $typeId = $this->requiredId(DB::table('leave_types')->where('company_id', $companyId)->where('code', 'ANNUAL-DEMO')->value('id'), 'leave type');
        DB::table('leave_policies')->updateOrInsert(['company_id' => $companyId, 'code' => 'ANNUAL-DEMO-2026'], ['leave_type_id' => $typeId, 'name' => 'Chính sách phép năm synthetic 2026', 'unit' => 'minutes', 'accrual_method' => 'annual', 'annual_entitlement_minutes' => 5760, 'carry_forward_rule' => json_encode(['synthetic_demo' => true], JSON_THROW_ON_ERROR), 'maximum_carry_forward_minutes' => 960, 'negative_balance_allowed' => false, 'requires_attachment' => false, 'approval_workflow' => json_encode(['levels' => 1], JSON_THROW_ON_ERROR), 'status' => 'active', 'effective_from' => '2026-01-01', 'version' => 1, 'created_at' => now(), 'updated_at' => now()]);
        $policyId = $this->requiredId(DB::table('leave_policies')->where('company_id', $companyId)->where('code', 'ANNUAL-DEMO-2026')->value('id'), 'leave policy');
        foreach (array_slice($employeeIds, 0, 6) as $index => $employeeId) {
            DB::table('leave_balances')->updateOrInsert(['company_id' => $companyId, 'employee_id' => $employeeId, 'leave_type_id' => $typeId, 'year' => 2026], ['opening' => 0, 'accrued' => 5760, 'used' => $index * 480, 'adjusted' => 0, 'carried' => 0, 'expired' => 0, 'reserved' => $index < 2 ? 480 : 0, 'remaining' => 5760 - ($index * 480), 'version' => 1, 'created_at' => now(), 'updated_at' => now()]);
            $status = ['pending', 'approved', 'rejected'][$index % 3];
            $key = '00000000-0000-4000-8000-'.str_pad((string) ($index + 1), 12, '0', STR_PAD_LEFT);
            $leaveDate = CarbonImmutable::parse('2026-08-'.[3, 4, 5, 6, 7, 10][$index]);
            DB::table('leave_requests')->updateOrInsert(['idempotency_key' => $key], ['company_id' => $companyId, 'employee_id' => $employeeId, 'leave_type_id' => $typeId, 'leave_policy_id' => $policyId, 'starts_at' => $leaveDate, 'ends_at' => $leaveDate, 'timezone' => 'Asia/Ho_Chi_Minh', 'requested_minutes' => 480, 'duration_type' => 'full_day', 'days' => 1, 'reason' => 'Nghỉ phép năm theo kế hoạch (local demo).', 'status' => $status, 'version' => 1, 'approved_by' => $status === 'approved' ? $actorId : null, 'submitted_at' => $leaveDate->subDays(7), 'approved_at' => $status === 'approved' ? $leaveDate->subDays(5) : null, 'rejected_at' => $status === 'rejected' ? $leaveDate->subDays(5) : null, 'reviewed_at' => $status !== 'pending' ? $leaveDate->subDays(5) : null, 'review_comment' => $status === 'rejected' ? 'Không đủ người thay ca trong ngày.' : null, 'created_at' => $leaveDate->subDays(8), 'updated_at' => now()]);
            if ($status === 'approved') {
                DB::table('attendance_records')->where('company_id', $companyId)->where('employee_id', $employeeId)->where('work_date', $leaveDate->toDateString())->where('source', 'local_demo')->delete();
            }
        }

        DB::table('leave_types')->updateOrInsert(['company_id' => $companyId, 'code' => 'UNPAID-DEMO'], ['name' => 'Nghỉ không lương (Demo)', 'accrual_policy' => json_encode(['method' => 'none', 'synthetic_demo' => true], JSON_THROW_ON_ERROR), 'carry_forward_policy' => json_encode(['maximum_minutes' => 0], JSON_THROW_ON_ERROR), 'is_paid' => false, 'created_at' => now(), 'updated_at' => now()]);
        $unpaidTypeId = $this->requiredId(DB::table('leave_types')->where('company_id', $companyId)->where('code', 'UNPAID-DEMO')->value('id'), 'unpaid leave type');
        DB::table('leave_policies')->updateOrInsert(['company_id' => $companyId, 'code' => 'UNPAID-DEMO-2026'], ['leave_type_id' => $unpaidTypeId, 'name' => 'Chính sách nghỉ không lương synthetic 2026', 'unit' => 'minutes', 'accrual_method' => 'none', 'annual_entitlement_minutes' => 0, 'carry_forward_rule' => json_encode(['synthetic_demo' => true], JSON_THROW_ON_ERROR), 'maximum_carry_forward_minutes' => 0, 'negative_balance_allowed' => true, 'requires_attachment' => false, 'approval_workflow' => json_encode(['levels' => 1], JSON_THROW_ON_ERROR), 'status' => 'active', 'effective_from' => '2026-01-01', 'version' => 1, 'created_at' => now(), 'updated_at' => now()]);
        $unpaidPolicyId = $this->requiredId(DB::table('leave_policies')->where('company_id', $companyId)->where('code', 'UNPAID-DEMO-2026')->value('id'), 'unpaid leave policy');
        foreach (array_slice($employeeIds, 8, 3) as $index => $employeeId) {
            $date = CarbonImmutable::parse('2026-08-'.str_pad((string) (5 + $index), 2, '0', STR_PAD_LEFT));
            DB::table('leave_requests')->updateOrInsert(
                ['idempotency_key' => $this->uuid(4000 + $employeeId)],
                ['company_id' => $companyId, 'employee_id' => $employeeId, 'leave_type_id' => $unpaidTypeId, 'leave_policy_id' => $unpaidPolicyId, 'starts_at' => $date, 'ends_at' => $date, 'timezone' => 'Asia/Ho_Chi_Minh', 'requested_minutes' => 480, 'duration_type' => 'full_day', 'days' => 1, 'reason' => 'Nghỉ việc riêng không hưởng lương (local demo).', 'status' => 'approved', 'version' => 2, 'approved_by' => $actorId, 'submitted_at' => $date->subDays(5), 'approved_at' => $date->subDays(3), 'reviewed_at' => $date->subDays(3), 'review_comment' => 'Đã bố trí nhân sự thay thế trong ngày.', 'created_at' => $date->subDays(6), 'updated_at' => now()],
            );
            DB::table('attendance_records')->where('company_id', $companyId)->where('employee_id', $employeeId)->where('work_date', $date->toDateString())->where('source', 'local_demo')->delete();
        }
    }

    private function notifications(int $companyId, int $recipientId): void
    {
        foreach ([['welcome', 'Welcome to Minh Long Tech', 'unread', null], ['policy', 'Leave policy updated', 'sent', null], ['retry', 'Synthetic channel retry', 'failed', 'Synthetic local channel unavailable']] as $index => [$action, $title, $status, $failure]) {
            $key = 'local-demo-notification-'.$action;
            $payload = ['synthetic_demo' => true, 'category' => $action];
            DB::table('notification_messages')->updateOrInsert(['idempotency_key' => $key], ['uuid' => $this->uuid($index + 101), 'company_id' => $companyId, 'category' => 'local_demo', 'entity_type' => 'company', 'entity_id' => $companyId, 'action' => $action, 'title' => $title, 'body' => 'SYNTHETIC DEMO DATA — no external delivery.', 'navigation_reference' => 'dashboard', 'safe_payload' => json_encode($payload, JSON_THROW_ON_ERROR), 'payload_checksum' => hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR)), 'status' => $status, 'actor_id' => $recipientId, 'created_at' => now(), 'updated_at' => now()]);
            $messageId = $this->requiredId(DB::table('notification_messages')->where('idempotency_key', $key)->value('id'), 'notification message');
            $deliveryKey = $this->uuid($index + 201);
            DB::table('notification_deliveries')->updateOrInsert(['idempotency_key' => $deliveryKey], ['company_id' => $companyId, 'notification_message_id' => $messageId, 'notification_type' => 'local_demo', 'entity_type' => 'company', 'entity_id' => $companyId, 'entity_version' => 1, 'recipient_id' => $recipientId, 'channel' => 'database', 'action' => $action, 'status' => $status === 'failed' ? 'failed' : 'sent', 'attempts' => $status === 'failed' ? 1 : 0, 'sent_at' => $status === 'failed' ? null : now(), 'failed_at' => $status === 'failed' ? now() : null, 'failure_message' => $failure, 'created_at' => now(), 'updated_at' => now()]);
            DB::table('notification_recipients')->updateOrInsert(['notification_message_id' => $messageId, 'user_id' => $recipientId], ['company_id' => $companyId, 'resolution_source' => 'local_demo', 'created_at' => now(), 'updated_at' => now()]);
        }
    }

    private function integration(int $companyId, int $actorId): void
    {
        $configuration = ['adapter' => 'local_null', 'external_http' => false];
        DB::table('integration_connections')->updateOrInsert(['company_id' => $companyId, 'code' => 'LOCAL-NULL'], ['uuid' => $this->uuid(301), 'name' => 'Local Null Adapter (Synthetic)', 'type' => 'generic', 'status' => 'active', 'health_status' => 'healthy', 'base_url' => null, 'configuration' => json_encode($configuration, JSON_THROW_ON_ERROR), 'credential_reference' => null, 'timeout_seconds' => 1, 'max_attempts' => 3, 'version' => 1, 'checksum' => hash('sha256', json_encode($configuration, JSON_THROW_ON_ERROR)), 'created_by' => $actorId, 'updated_by' => $actorId, 'created_at' => now(), 'updated_at' => now()]);
        $connectionId = $this->requiredId(DB::table('integration_connections')->where('company_id', $companyId)->where('code', 'LOCAL-NULL')->value('id'), 'integration connection');
        foreach (['queued', 'failed', 'dead_letter'] as $index => $status) {
            $key = $this->uuid($index + 401);
            $payload = ['synthetic_demo' => true, 'sequence' => $index + 1];
            DB::table('integration_outbound_messages')->updateOrInsert(['idempotency_key' => $key], ['uuid' => $this->uuid($index + 501), 'company_id' => $companyId, 'connection_id' => $connectionId, 'source_type' => 'local_demo', 'source_id' => null, 'message_type' => 'synthetic.demo', 'status' => $status, 'payload' => json_encode($payload, JSON_THROW_ON_ERROR), 'payload_checksum' => hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR)), 'attempt_count' => $index, 'available_at' => now(), 'failure_code' => $status === 'queued' ? null : 'LOCAL_DEMO_FAILURE', 'safe_failure_message' => $status === 'queued' ? null : 'Synthetic failure; no HTTP request was made.', 'created_by' => $actorId, 'created_at' => now(), 'updated_at' => now()]);
            if ($status === 'dead_letter') {
                $messageId = $this->requiredId(DB::table('integration_outbound_messages')->where('idempotency_key', $key)->value('id'), 'integration outbound message');
                DB::table('integration_dead_letters')->updateOrInsert(['outbound_message_id' => $messageId], ['company_id' => $companyId, 'status' => 'open', 'reason_code' => 'LOCAL_DEMO_FAILURE', 'safe_reason' => 'Synthetic dead letter; external delivery disabled.', 'replay_count' => 0, 'created_at' => now(), 'updated_at' => now()]);
            }
        }
    }

    private function iam(int $companyId, int $adminId): void
    {
        $subject = User::query()->where('email', 'employee@local.test')->firstOrFail();
        $roleId = $this->requiredId(Role::findByName('Employee', 'web')->id, 'Employee role');
        $key = $this->uuid(601);
        $checksum = hash('sha256', 'local-demo-iam-request');
        DB::table('iam_access_requests')->updateOrInsert(['idempotency_key' => $key], ['uuid' => $this->uuid(602), 'company_id' => $companyId, 'requester_id' => $subject->id, 'subject_user_id' => $subject->id, 'status' => 'requested', 'approval_mode' => 'iam', 'reason' => 'Synthetic local demo access request', 'version' => 1, 'checksum' => $checksum, 'created_at' => now(), 'updated_at' => now()]);
        $requestId = $this->requiredId(DB::table('iam_access_requests')->where('idempotency_key', $key)->value('id'), 'IAM access request');
        DB::table('iam_access_request_items')->updateOrInsert(['access_request_id' => $requestId, 'entitlement_type' => 'role', 'entitlement_id' => $roleId], ['risk_level' => 'standard', 'checksum' => hash('sha256', $checksum.'-item'), 'created_at' => now(), 'updated_at' => now()]);
        DB::table('iam_access_reviews')->updateOrInsert(['company_id' => $companyId, 'name' => 'Q3 Synthetic Access Review'], ['uuid' => $this->uuid(603), 'scope_type' => 'company', 'scope_id' => $companyId, 'status' => 'draft', 'due_date' => today()->addMonth(), 'version' => 1, 'snapshot_checksum' => hash('sha256', 'local-demo-access-review'), 'created_by' => $adminId, 'created_at' => now(), 'updated_at' => now()]);
    }

    private function workflow(int $companyId, int $adminId): void
    {
        $approver = User::query()->where('email', 'hr.manager@local.test')->firstOrFail();
        $definition = DB::table('workflow_definitions')->where('company_id', $companyId)->where('code', 'LOCAL-LEAVE-APPROVAL')->value('id');
        $definitionId = is_numeric($definition) ? (int) $definition : app(WorkflowDefinitionService::class)->create(new WorkflowDefinitionData($companyId, $adminId, 'LOCAL-LEAVE-APPROVAL', 'Synthetic Leave Approval', 'LOCAL DEMO ONLY — sequential maker-checker approval.', 'sequential', [
            ['key' => 'manager-review', 'name' => 'Line manager review', 'mode' => 'sequential', 'resolver_type' => 'specific_user', 'resolver_value' => (string) $approver->id, 'due_after_minutes' => 1440],
        ]));
        $status = DB::table('workflow_definitions')->where('id', $definitionId)->value('status');
        if ($status !== 'published') {
            app(WorkflowDefinitionService::class)->publish($companyId, $definitionId, $adminId);
        }
        $existing = DB::table('workflow_instances')->where('company_id', $companyId)->where('idempotency_key', 'local-demo-leave-workflow')->value('id');
        if (! is_numeric($existing)) {
            app(WorkflowExecutionService::class)->start(new StartWorkflowData($companyId, $adminId, $definitionId, 'leave_request', 1, 'local-demo-leave-workflow', ['synthetic_demo' => true]));
        }
    }

    private function uuid(int $suffix): string
    {
        return '00000000-0000-4000-8000-'.str_pad((string) $suffix, 12, '0', STR_PAD_LEFT);
    }

    private function requiredId(mixed $value, string $resource): int
    {
        if (! is_int($value) && ! (is_string($value) && ctype_digit($value))) {
            throw new \RuntimeException("Missing numeric identifier for {$resource}.");
        }

        return (int) $value;
    }
}
