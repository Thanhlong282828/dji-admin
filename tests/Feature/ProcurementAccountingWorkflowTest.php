<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\DataQuality\DataQualityAuditService;
use Carbon\CarbonImmutable;
use Illuminate\Database\RecordNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Modules\Finance\Application\Services\AccountingPostingPolicyService;
use Modules\Finance\Application\Services\AccountsPayablePlanningReadService;
use Modules\Finance\Application\Services\ApPaymentReversalService;
use Modules\Finance\Application\Services\CostCenterCostManagementReadService;
use Modules\Finance\Application\Services\FxMoneyConverter;
use Modules\Finance\Application\Services\FxRateService;
use Modules\Finance\Application\Services\SourceAccountingPostingService;
use Modules\Payroll\Domain\Repositories\AccountingReadRepositoryInterface;
use Modules\Procurement\Application\Services\GoodsReturnService;
use Modules\Procurement\Application\Services\ProcurementExceptionReadService;
use Modules\Procurement\Application\Services\ProcurementReconciliationReadService;
use Modules\Procurement\Application\Services\ProcurementService;
use Modules\Procurement\Application\Services\VendorBillReversalService;
use Modules\Procurement\Application\Services\VendorPerformanceReadService;
use Tests\TestCase;

final class ProcurementAccountingWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_vendor_bill_input_vat_posts_recoverable_tax_and_ap_evidence(): void
    {
        [$maker, $checker, $company, $branch, $variant, $location] = $this->runtime();
        $grni = (int) DB::table('accounting_accounts')->where(['company_id' => $company, 'code' => '2150'])->value('id');
        $ap = (int) DB::table('accounting_accounts')->where(['company_id' => $company, 'code' => '2100'])->value('id');
        $inputVat = (int) DB::table('accounting_accounts')->insertGetId(['uuid' => (string) Str::uuid(), 'company_id' => $company, 'code' => '1250', 'name' => 'Input VAT Recoverable', 'account_type' => 'asset', 'normal_balance' => 'debit', 'currency' => 'VND', 'allow_posting' => true, 'status' => 'active', 'effective_from' => '2020-01-01', 'created_by' => $maker->id, 'created_at' => now(), 'updated_at' => now()]);
        $policies = app(AccountingPostingPolicyService::class);
        $accountingPolicy = $policies->create($company, $maker, ['policy_key' => 'VAT-VENDOR-BILL', 'source_type' => 'vendor_bill', 'business_event' => 'VENDOR_BILL_VAT', 'classification' => 'inventory', 'version' => 1, 'currency' => 'VND', 'effective_from' => '2026-01-01', 'lines' => [
            ['entry_side' => 'debit', 'line_role' => 'GRNI', 'account_id' => $grni],
            ['entry_side' => 'debit', 'line_role' => 'TAX_RECOVERABLE', 'account_id' => $inputVat],
            ['entry_side' => 'credit', 'line_role' => 'AP', 'account_id' => $ap],
        ]]);
        $policies->submit($company, $accountingPolicy, $maker);
        $policies->approve($company, $accountingPolicy, $checker);
        $category = (int) DB::table('tax_categories')->insertGetId(['company_id' => $company, 'code' => 'VAT_INPUT', 'name' => 'Input VAT', 'direction' => 'input', 'country_code' => 'VN', 'status' => 'active', 'created_by' => $maker->id, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('vat_policies')->insert(['company_id' => $company, 'tax_category_id' => $category, 'policy_key' => 'VAT-INPUT-10', 'version' => 1, 'country_code' => 'VN', 'currency' => 'VND', 'rate_basis_points' => 1_000, 'effective_from' => '2026-01-01', 'tax_account_id' => $inputVat, 'accounting_posting_policy_id' => $accountingPolicy, 'status' => 'approved', 'created_by' => $maker->id, 'submitted_by' => $maker->id, 'submitted_at' => now(), 'approved_by' => $checker->id, 'approved_at' => now(), 'created_at' => now(), 'updated_at' => now()]);

        $service = app(ProcurementService::class);
        $vendor = $service->mutate('vendor.create', $company, $maker, ['code' => 'VAT-VENDOR', 'name' => 'VAT Vendor']);
        $request = $service->mutate('request.create', $company, $maker, ['branch_id' => $branch, 'reference' => 'PR-VAT', 'requested_date' => '2026-09-01', 'reason' => 'Input VAT source', 'product_variant_id' => $variant, 'description' => 'VAT goods', 'quantity' => 1, 'estimated_unit_cost_minor' => 10_000_000]);
        $service->mutate('request.transition', $company, $maker, ['id' => $request, 'to' => 'submitted']);
        $service->mutate('request.transition', $company, $checker, ['id' => $request, 'to' => 'approved']);
        $order = $service->mutate('order.create', $company, $maker, ['purchase_request_id' => $request, 'vendor_id' => $vendor, 'reference' => 'PO-VAT', 'order_date' => '2026-09-02']);
        $service->mutate('order.transition', $company, $checker, ['id' => $order, 'to' => 'approved']);
        $line = (int) DB::table('purchase_order_lines')->where('purchase_order_id', $order)->value('id');
        $receipt = $service->mutate('receipt.create', $company, $maker, ['purchase_order_id' => $order, 'purchase_order_line_id' => $line, 'inventory_location_id' => $location, 'reference' => 'GR-VAT', 'receipt_date' => '2026-09-03', 'quantity' => 1]);
        $service->mutate('receipt.receive', $company, $checker, ['id' => $receipt]);
        $receiptLine = (int) DB::table('procurement_goods_receipt_lines')->where('goods_receipt_id', $receipt)->value('id');
        $bill = $service->mutate('bill.create', $company, $maker, ['purchase_order_id' => $order, 'purchase_order_line_id' => $line, 'goods_receipt_line_id' => $receiptLine, 'quantity' => 1, 'invoice_number' => 'VB-VAT', 'invoice_date' => '2026-09-04', 'due_date' => '2026-10-04', 'tax_category_code' => 'VAT_INPUT']);
        $service->mutate('bill.approve', $company, $checker, ['id' => $bill]);

        $stored = DB::table('vendor_bills')->where('id', $bill)->firstOrFail();
        self::assertSame(10_000_000, (int) $stored->subtotal_minor);
        self::assertSame(1_000_000, (int) $stored->tax_minor);
        self::assertSame(11_000_000, (int) $stored->total_minor);
        $journal = DB::table('payroll_accounting_journals')->where(['source_type' => 'vendor_bill', 'source_id' => $bill])->firstOrFail();
        self::assertSame(11_000_000, (int) $journal->total_debit_minor);
        self::assertSame(11_000_000, (int) $journal->total_credit_minor);
        self::assertSame(3, (int) $journal->line_count);
        self::assertSame(1, DB::table('tax_transaction_evidence')->where(['source_type' => 'vendor_bill', 'source_id' => $bill, 'accounting_journal_id' => $journal->id])->count());
    }

    public function test_goods_bill_reversal_restores_grni_and_ap_without_touching_inventory_or_budget(): void
    {
        CarbonImmutable::setTestNow('2026-09-12 12:00:00');
        [$maker, $checker, $company, $branch, $variant, $location] = $this->runtime();
        $procurement = app(ProcurementService::class);
        $center = (int) DB::table('cost_centers')->insertGetId(['company_id' => $company, 'code' => 'GOODS-CC', 'name' => 'Goods Cost Center', 'created_at' => now(), 'updated_at' => now()]);
        $vendor = $procurement->mutate('vendor.create', $company, $maker, ['code' => 'GOODS-VENDOR', 'name' => 'Goods Vendor']);
        $request = $procurement->mutate('request.create', $company, $maker, ['branch_id' => $branch, 'cost_center_id' => $center, 'reference' => 'PR-GOODS-REV', 'requested_date' => '2026-09-01', 'reason' => 'Goods replenishment', 'product_variant_id' => $variant, 'description' => 'Goods inventory', 'quantity' => 8, 'estimated_unit_cost_minor' => 1_000_000]);
        $procurement->mutate('request.transition', $company, $maker, ['id' => $request, 'to' => 'submitted']);
        $procurement->mutate('request.transition', $company, $checker, ['id' => $request, 'to' => 'approved']);
        $order = $procurement->mutate('order.create', $company, $maker, ['purchase_request_id' => $request, 'vendor_id' => $vendor, 'reference' => 'PO-GOODS-REV', 'order_date' => '2026-09-02']);
        $procurement->mutate('order.transition', $company, $checker, ['id' => $order, 'to' => 'approved']);
        $line = (int) DB::table('purchase_order_lines')->where('purchase_order_id', $order)->value('id');
        $receipt = $procurement->mutate('receipt.create', $company, $maker, ['purchase_order_id' => $order, 'purchase_order_line_id' => $line, 'inventory_location_id' => $location, 'reference' => 'GR-GOODS-REV', 'receipt_date' => '2026-09-10', 'quantity' => 5]);
        $procurement->mutate('receipt.receive', $company, $checker, ['id' => $receipt]);
        $bill = $procurement->mutate('bill.create', $company, $maker, ['purchase_order_id' => $order, 'purchase_order_line_id' => $line, 'quantity' => 4, 'invoice_number' => 'INV-GOODS-REV', 'invoice_date' => '2026-09-11', 'due_date' => '2026-10-11']);
        $procurement->mutate('bill.approve', $company, $checker, ['id' => $bill]);
        $payment = $procurement->mutate('payment.allocate', $company, $checker, ['vendor_bill_id' => $bill, 'payment_date' => '2026-09-12', 'amount_minor' => 1_000_000, 'method' => 'bank_clearing', 'reference' => 'PAY-GOODS-REV']);
        $period = (int) DB::table('accounting_periods')->where(['company_id' => $company, 'year' => 2026, 'month' => 9])->value('id');
        $filters = ['date_from' => '2026-09-01', 'date_to' => '2026-09-12', 'branch_id' => null, 'cost_center_id' => null, 'vendor_id' => null, 'classification' => null, 'currency' => 'VND'];
        $reporting = app(ProcurementReconciliationReadService::class);
        $before = $reporting->read($company, $filters);
        self::assertSame(5_000_000, $before['goods']['received']);
        self::assertSame(4_000_000, $before['goods']['billed']);
        self::assertSame(1_000_000, $before['goods']['unbilled_received']);
        self::assertSame(1_000_000, $before['goods']['grni']);
        self::assertSame(3_000_000, $before['ap']['outstanding']);
        $inventoryBefore = DB::table('inventory_balances')->where(['product_variant_id' => $variant, 'inventory_location_id' => $location])->firstOrFail();
        $movementCount = DB::table('inventory_movements')->count();
        $budgetBefore = app(CostCenterCostManagementReadService::class)->read($company, $center, '2026-09-01', '2026-09-30')['totals'];

        $billReversals = app(VendorBillReversalService::class);
        $this->assertValidation(fn () => $billReversals->request($company, $bill, $period, 'Goods invoice correction', 'goods-bill-blocked', $maker), 'bill', 'VENDOR_BILL_HAS_ACTIVE_PAYMENT');
        $paymentReversals = app(ApPaymentReversalService::class);
        $paymentRequest = $paymentReversals->request($company, $payment, $period, 'Payment returned', 'goods-payment-reversal', $maker);
        $paymentReversals->approve($company, $paymentRequest, '2026-09-15', $checker);
        $planning = app(AccountsPayablePlanningReadService::class);
        self::assertSame(3_000_000, $planning->read($company, $this->apFilters('2026-09-12'))['totals']['outstanding']);
        self::assertSame(4_000_000, $planning->read($company, $this->apFilters('2026-09-15'))['totals']['outstanding']);

        CarbonImmutable::setTestNow('2026-09-16 12:00:00');
        $billRequest = $billReversals->request($company, $bill, $period, 'Goods invoice correction', 'goods-bill-reversal', $maker);
        $billJournal = $billReversals->approve($company, $billRequest, '2026-09-16', $checker);
        self::assertSame($billJournal, $billReversals->approve($company, $billRequest, '2026-09-16', $checker));
        $reversalLines = DB::table('payroll_accounting_journal_lines')->where('journal_id', $billJournal)->orderBy('line_number')->get();
        self::assertSame(['2150', '2100'], $reversalLines->pluck('account_code_snapshot')->all());
        self::assertSame([0, 4_000_000], $reversalLines->pluck('debit_minor')->map(static fn ($amount): int => (int) $amount)->all());
        self::assertSame([4_000_000, 0], $reversalLines->pluck('credit_minor')->map(static fn ($amount): int => (int) $amount)->all());
        self::assertSame(0, $planning->read($company, $this->apFilters('2026-09-16'))['totals']['outstanding']);
        $filters['date_to'] = '2026-09-16';
        $after = $reporting->read($company, $filters);
        self::assertSame(5_000_000, $after['goods']['received']);
        self::assertSame(0, $after['goods']['billed']);
        self::assertSame(5_000_000, $after['goods']['unbilled_received']);
        self::assertSame(5_000_000, $after['goods']['grni']);
        self::assertSame(0, $after['ap']['outstanding']);
        $exceptions = app(ProcurementExceptionReadService::class)->read($company, $filters + ['exception_type' => null, 'severity' => null, 'business_status' => null], $after);
        self::assertSame(5_000_000, collect($exceptions['rows'])->firstWhere('exception_type', 'GOODS_RECEIVED_NOT_BILLED')['amount_minor'] ?? null);
        $performanceFilters = $filters + ['sort' => 'billed', 'direction' => 'desc'];
        $performance = app(VendorPerformanceReadService::class)->read($company, $performanceFilters, $exceptions['rows']);
        self::assertSame(0, $performance['rows'][0]['billed']);
        self::assertSame(5_000_000, $performance['rows'][0]['received_not_billed']);
        $inventoryAfter = DB::table('inventory_balances')->where(['product_variant_id' => $variant, 'inventory_location_id' => $location])->firstOrFail();
        self::assertSame((int) $inventoryBefore->on_hand, (int) $inventoryAfter->on_hand);
        self::assertSame((int) $inventoryBefore->inventory_value_minor, (int) $inventoryAfter->inventory_value_minor);
        self::assertSame((int) $inventoryBefore->average_unit_cost_minor, (int) $inventoryAfter->average_unit_cost_minor);
        self::assertSame($movementCount, DB::table('inventory_movements')->count());
        self::assertSame($budgetBefore, app(CostCenterCostManagementReadService::class)->read($company, $center, '2026-09-01', '2026-09-30')['totals']);
        CarbonImmutable::setTestNow();
    }

    public function test_canonical_purchase_chain_values_inventory_ap_and_balanced_accounting(): void
    {
        [$maker, $checker, $company, $branch, $variant, $location] = $this->runtime();
        $service = app(ProcurementService::class);
        $costCenter = (int) DB::table('cost_centers')->insertGetId(['company_id' => $company, 'code' => 'PROC-CC', 'name' => 'Procurement Cost Center', 'created_at' => now(), 'updated_at' => now()]);
        $vendor = $service->mutate('vendor.create', $company, $maker, ['code' => 'V-001', 'name' => 'Nhà cung cấp thiết bị', 'payment_terms_days' => 30]);
        $request = $service->mutate('request.create', $company, $maker, ['branch_id' => $branch, 'cost_center_id' => $costCenter, 'reference' => 'PR-001', 'requested_date' => '2026-08-01', 'needed_date' => '2026-08-15', 'reason' => 'Bổ sung tồn kho', 'product_variant_id' => $variant, 'description' => 'Thiết bị chuẩn', 'quantity' => 5, 'estimated_unit_cost_minor' => 200]);
        $service->mutate('request.transition', $company, $maker, ['id' => $request, 'to' => 'submitted']);
        $this->expectSelfApprovalBlocked($service, $company, $maker, $request);
        $service->mutate('request.transition', $company, $checker, ['id' => $request, 'to' => 'approved']);
        $order = $service->mutate('order.create', $company, $maker, ['purchase_request_id' => $request, 'vendor_id' => $vendor, 'reference' => 'PO-001', 'order_date' => '2026-08-02', 'expected_date' => '2026-08-15', 'payment_terms_days' => 30]);
        $service->mutate('order.transition', $company, $checker, ['id' => $order, 'to' => 'approved']);
        self::assertSame($costCenter, (int) DB::table('purchase_orders')->where('id', $order)->value('cost_center_id'));
        self::assertSame('Procurement Cost Center', DB::table('purchase_orders')->where('id', $order)->value('cost_center_name_snapshot'));
        $budget = (int) DB::table('finance_budgets')->insertGetId(['company_id' => $company, 'year' => 2026, 'name' => 'Inventory Budget', 'status' => 'approved', 'created_by' => $maker->id, 'approved_by' => $checker->id, 'approved_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
        $inventoryAccount = (int) DB::table('accounting_accounts')->where(['company_id' => $company, 'code' => '1200'])->value('id');
        DB::table('finance_budget_lines')->insert(['budget_id' => $budget, 'branch_id' => $branch, 'cost_center_id' => $costCenter, 'account_id' => $inventoryAccount, 'month' => 8, 'amount_minor' => 2000, 'created_at' => now(), 'updated_at' => now()]);
        $openCost = app(CostCenterCostManagementReadService::class)->read($company, $costCenter, '2026-08-01', '2026-08-31');
        self::assertSame(2000, $openCost['totals']['budget']);
        self::assertSame(1000, $openCost['totals']['commitment']);
        self::assertSame(0, $openCost['totals']['actual']);
        self::assertSame(1000, $openCost['totals']['consumed']);
        self::assertSame(1000, $openCost['totals']['remaining']);
        DB::table('cost_centers')->where('id', $costCenter)->update(['name' => 'Renamed Current Center']);
        $line = (int) DB::table('purchase_order_lines')->where('purchase_order_id', $order)->value('id');
        self::assertSame('Procurement Cost Center', DB::table('purchase_order_lines')->where('id', $line)->value('cost_center_name_snapshot'));
        $receipt = $service->mutate('receipt.create', $company, $maker, ['purchase_order_id' => $order, 'purchase_order_line_id' => $line, 'inventory_location_id' => $location, 'reference' => 'GR-001', 'receipt_date' => '2026-08-15', 'quantity' => 2]);
        self::assertSame($receipt, $service->mutate('receipt.receive', $company, $checker, ['id' => $receipt]));
        $afterReceipt = app(CostCenterCostManagementReadService::class)->read($company, $costCenter, '2026-08-01', '2026-08-31');
        self::assertSame(600, $afterReceipt['totals']['commitment']);
        self::assertSame(400, $afterReceipt['totals']['actual']);
        self::assertSame(1000, $afterReceipt['totals']['consumed']);
        self::assertSame(1000, $afterReceipt['totals']['remaining']);
        self::assertSame(-400, $this->glNet($company, '2150'));
        self::assertSame($receipt, $service->mutate('receipt.receive', $company, $checker, ['id' => $receipt]));

        $balance = DB::table('inventory_balances')->where(['product_variant_id' => $variant, 'inventory_location_id' => $location])->firstOrFail();
        self::assertSame(12, (int) $balance->on_hand);
        self::assertSame(1400, (int) $balance->inventory_value_minor);
        self::assertSame(117, (int) $balance->average_unit_cost_minor);
        self::assertSame(1, DB::table('inventory_movements')->where('idempotency_key', 'procurement-gr-'.DB::table('procurement_goods_receipt_lines')->where('goods_receipt_id', $receipt)->value('id'))->count());

        $bill = $service->mutate('bill.create', $company, $maker, ['purchase_order_id' => $order, 'purchase_order_line_id' => $line, 'quantity' => 1, 'invoice_number' => 'INV-001', 'invoice_date' => '2026-08-16', 'due_date' => '2026-09-15']);
        self::assertSame($bill, $service->mutate('bill.create', $company, $maker, ['purchase_order_id' => $order, 'purchase_order_line_id' => $line, 'quantity' => 1, 'invoice_number' => 'INV-001', 'invoice_date' => '2026-08-16', 'due_date' => '2026-09-15']));
        self::assertSame($costCenter, (int) DB::table('vendor_bills')->where('id', $bill)->value('cost_center_id'));
        self::assertSame('Procurement Cost Center', DB::table('vendor_bill_lines')->where('vendor_bill_id', $bill)->value('cost_center_name_snapshot'));
        $service->mutate('bill.approve', $company, $checker, ['id' => $bill]);
        self::assertSame(-200, $this->glNet($company, '2150'));
        self::assertSame(-200, $this->glNet($company, '2100'));
        self::assertSame(200, (int) DB::table('vendor_bills')->where('id', $bill)->value('total_minor'));
        try {
            $service->mutate('bill.create', $company, $maker, ['purchase_order_id' => $order, 'purchase_order_line_id' => $line, 'quantity' => 2, 'invoice_number' => 'INV-OVER', 'invoice_date' => '2026-08-17', 'due_date' => '2026-09-16']);
            self::fail('Cumulative billed quantity must not exceed received quantity.');
        } catch (ValidationException $exception) {
            self::assertArrayHasKey('quantity', $exception->errors());
        }
        $payment = $service->mutate('payment.allocate', $company, $checker, ['vendor_bill_id' => $bill, 'payment_date' => '2026-08-20', 'amount_minor' => 100, 'method' => 'bank_clearing', 'reference' => 'PAY-001']);
        self::assertGreaterThan(0, $payment);
        self::assertSame($payment, $service->mutate('payment.allocate', $company, $checker, ['vendor_bill_id' => $bill, 'payment_date' => '2026-08-20', 'amount_minor' => 100, 'method' => 'bank_clearing', 'reference' => 'PAY-001']));
        self::assertSame(1, DB::table('ap_payment_allocations')->where('payment_id', $payment)->count());
        self::assertSame('partially_paid', DB::table('vendor_bills')->where('id', $bill)->value('status'));
        self::assertSame(100, (int) DB::table('vendor_bills as b')->leftJoin('ap_payment_allocations as a', 'a.vendor_bill_id', '=', 'b.id')->where('b.id', $bill)->selectRaw('b.total_minor - COALESCE(SUM(a.allocated_minor),0) outstanding')->groupBy('b.id', 'b.total_minor')->value('outstanding'));
        self::assertSame(-100, $this->glNet($company, '2100'));
        self::assertSame(100, (int) DB::table('treasury_transactions')->where(['company_id' => $company, 'source_type' => 'ap_payment', 'source_id' => $payment])->value('amount_minor'));
        $afterPayment = app(CostCenterCostManagementReadService::class)->read($company, $costCenter, '2026-08-01', '2026-08-31');
        self::assertSame(600, $afterPayment['totals']['commitment']);
        self::assertSame(400, $afterPayment['totals']['actual']);
        self::assertSame(1000, $afterPayment['totals']['consumed']);
        self::assertSame(1000, $afterPayment['totals']['remaining']);

        $receipt2 = $service->mutate('receipt.create', $company, $maker, ['purchase_order_id' => $order, 'purchase_order_line_id' => $line, 'inventory_location_id' => $location, 'reference' => 'GR-002', 'receipt_date' => '2026-08-21', 'quantity' => 3]);
        $service->mutate('receipt.receive', $company, $checker, ['id' => $receipt2]);
        $afterFinalReceipt = app(CostCenterCostManagementReadService::class)->read($company, $costCenter, '2026-08-01', '2026-08-31');
        self::assertSame(0, $afterFinalReceipt['totals']['commitment']);
        self::assertSame(1000, $afterFinalReceipt['totals']['actual']);
        self::assertSame(1000, $afterFinalReceipt['totals']['consumed']);
        self::assertSame(1000, $afterFinalReceipt['totals']['remaining']);
        $bill2 = $service->mutate('bill.create', $company, $maker, ['purchase_order_id' => $order, 'purchase_order_line_id' => $line, 'quantity' => 4, 'invoice_number' => 'INV-002', 'invoice_date' => '2026-08-22', 'due_date' => '2026-09-21']);
        $service->mutate('bill.approve', $company, $checker, ['id' => $bill2]);
        self::assertSame(1000, (int) DB::table('vendor_bill_lines')->where('purchase_order_line_id', $line)->sum('line_total_minor'));
        self::assertSame(0, $this->glNet($company, '2150'));
        self::assertSame(-900, $this->glNet($company, '2100'));

        DB::flushQueryLog();
        DB::enableQueryLog();
        $planning = app(AccountsPayablePlanningReadService::class)->read($company, ['as_of_date' => '2026-09-20', 'vendor_id' => $vendor, 'branch_id' => $branch, 'cost_center_id' => $costCenter, 'due_bucket' => null, 'payment_status' => null, 'currency' => 'VND', 'sort' => 'due_date', 'direction' => 'asc']);
        $planningQueryLog = DB::getQueryLog();
        $planningQueries = count($planningQueryLog);
        DB::disableQueryLog();
        self::assertSame(['original' => 1000, 'credits' => 0, 'paid' => 100, 'outstanding' => 900], $planning['totals']);
        self::assertSame(800, $planning['aging']['NOT_DUE']);
        self::assertSame(100, $planning['aging']['1_30']);
        self::assertSame(100, $planning['forecast']['OVERDUE']);
        self::assertSame(800, $planning['forecast']['NEXT_7']);
        self::assertSame(900, $planning['vendors'][0]['outstanding']);
        self::assertSame(800, $planning['vendors'][0]['due_next_7']);
        self::assertSame(2, $planning['readiness']['READY']);
        self::assertSame('NOT_ENOUGH_EVIDENCE', $planning['cash_evidence']['status']);
        self::assertLessThanOrEqual(
            4,
            $planningQueries,
            json_encode(array_column($planningQueryLog, 'query'), JSON_THROW_ON_ERROR),
        );

        $reconciled = app(AccountsPayablePlanningReadService::class)->read($company, ['as_of_date' => '2026-09-20', 'vendor_id' => null, 'branch_id' => $branch, 'cost_center_id' => null, 'due_bucket' => null, 'payment_status' => null, 'currency' => 'VND', 'sort' => 'outstanding', 'direction' => 'desc']);
        self::assertSame(900, $reconciled['reconciliation']['subledger']);
        self::assertSame(900, $reconciled['reconciliation']['gl_2100']);
        self::assertSame(0, $reconciled['reconciliation']['difference']);

        self::assertSame(5, DB::table('payroll_accounting_journals')->where('company_id', $company)->whereNotNull('generic_source_key')->count());
        self::assertSame(0, (int) DB::table('payroll_accounting_journals')->where('company_id', $company)->whereNotNull('generic_source_key')->selectRaw('SUM(total_debit_minor-total_credit_minor) difference')->value('difference'));
        self::assertSame(5, DB::table('payroll_accounting_journals')->where('company_id', $company)->whereNotNull('generic_source_key')->distinct()->count('generic_source_key'));

        $receiptJournal = (int) DB::table('payroll_accounting_journals')->where(['company_id' => $company, 'source_type' => 'goods_receipt', 'source_id' => $receipt])->value('id');
        $detail = app(AccountingReadRepositoryInterface::class)->journal($company, $receiptJournal)->detail;
        self::assertTrue($detail['is_generic_source']);
        self::assertSame('goods_receipt', $detail['source_type']);
        self::assertSame($receipt, $detail['source_id']);
        self::assertSame(2, count($detail['lines']));
    }

    public function test_closed_general_accounting_period_blocks_normal_source_posting(): void
    {
        [$maker, , $company, $branch] = $this->runtime();
        DB::table('accounting_periods')->insert(['company_id' => $company, 'year' => 2026, 'month' => 8, 'start_date' => '2026-08-01', 'end_date' => '2026-08-31', 'status' => 'closed', 'created_at' => now(), 'updated_at' => now()]);

        $this->expectException(ValidationException::class);
        app(SourceAccountingPostingService::class)->post(
            $company, $branch, 'period_lock_proof', 1, 'period_lock_proof', '2026-08-15', 'VND',
            [['account_code' => '1200', 'debit' => 100, 'credit' => 0, 'description' => 'Inventory'], ['account_code' => '2150', 'debit' => 0, 'credit' => 100, 'description' => 'GRNI']],
            $maker->id,
        );
    }

    public function test_procurement_mutation_requires_the_action_specific_permission(): void
    {
        [$maker, , $company] = $this->runtime();

        $this->actingAs($maker)->post(route('admin.procurement.mutate'), [
            'company_id' => $company,
            'action' => 'vendor.create',
            'code' => 'DENIED',
            'name' => 'Denied Vendor',
        ])->assertForbidden();
        self::assertFalse(DB::table('procurement_vendors')->where('code', 'DENIED')->exists());
    }

    public function test_purchase_request_rejects_foreign_company_cost_center(): void
    {
        [$maker, , $company, $branch, $variant] = $this->runtime();
        $foreign = (int) DB::table('companies')->insertGetId(['uuid' => (string) Str::uuid(), 'code' => 'PROC-FOREIGN', 'name' => 'Foreign Procurement Company', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
        $foreignCenter = (int) DB::table('cost_centers')->insertGetId(['company_id' => $foreign, 'code' => 'FOREIGN', 'name' => 'Foreign Center', 'created_at' => now(), 'updated_at' => now()]);

        $this->expectException(RecordNotFoundException::class);
        app(ProcurementService::class)->mutate('request.create', $company, $maker, ['branch_id' => $branch, 'cost_center_id' => $foreignCenter, 'reference' => 'PR-FOREIGN', 'requested_date' => '2026-08-01', 'reason' => 'Must fail', 'product_variant_id' => $variant, 'description' => 'Foreign dimension', 'quantity' => 1, 'estimated_unit_cost_minor' => 1]);
    }

    public function test_procurement_classification_is_line_level_and_foreign_account_is_rejected(): void
    {
        [$maker, , $company, $branch] = $this->runtime();
        $service = app(ProcurementService::class);
        $assetRequest = $service->mutate('request.create', $company, $maker, ['branch_id' => $branch, 'reference' => 'PR-ASSET', 'requested_date' => '2026-08-01', 'reason' => 'Capital equipment', 'procurement_classification' => 'asset', 'description' => 'Máy chủ', 'quantity' => 1, 'estimated_unit_cost_minor' => 10_000]);
        $assetLine = DB::table('purchase_request_lines')->where('purchase_request_id', $assetRequest)->firstOrFail();
        self::assertSame('asset', $assetLine->procurement_classification);
        self::assertSame('1300', DB::table('accounting_accounts')->where('id', $assetLine->account_id)->value('code'));

        $opexRequest = $service->mutate('request.create', $company, $maker, ['branch_id' => $branch, 'reference' => 'PR-OPEX', 'requested_date' => '2026-08-01', 'reason' => 'Dịch vụ vận hành', 'procurement_classification' => 'operating_expense', 'description' => 'Dịch vụ vệ sinh', 'quantity' => 1, 'estimated_unit_cost_minor' => 5_000]);
        $opexLine = DB::table('purchase_request_lines')->where('purchase_request_id', $opexRequest)->firstOrFail();
        self::assertSame('operating_expense', $opexLine->procurement_classification);
        self::assertSame('5100', DB::table('accounting_accounts')->where('id', $opexLine->account_id)->value('code'));

        $foreign = (int) DB::table('companies')->insertGetId(['uuid' => (string) Str::uuid(), 'code' => 'PROC-ACCOUNT-FOREIGN', 'name' => 'Foreign Account Company', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
        $foreignAccount = app(SourceAccountingPostingService::class)->accountEvidence($foreign, '1200', 'VND', $maker->id)['id'];
        $this->expectException(ValidationException::class);
        $service->mutate('request.create', $company, $maker, ['branch_id' => $branch, 'reference' => 'PR-BAD-ACCOUNT', 'requested_date' => '2026-08-01', 'reason' => 'Must fail', 'procurement_classification' => 'inventory', 'account_id' => $foreignAccount, 'description' => 'Bad account', 'quantity' => 1, 'estimated_unit_cost_minor' => 1]);
    }

    public function test_operating_expense_order_cannot_use_goods_receipt_or_goods_grni_without_service_policy(): void
    {
        [$maker, $checker, $company, $branch, , $location] = $this->runtime();
        $service = app(ProcurementService::class);
        $request = $service->mutate('request.create', $company, $maker, ['branch_id' => $branch, 'reference' => 'PR-SERVICE-POLICY', 'requested_date' => '2026-08-01', 'reason' => 'Dịch vụ cần policy riêng', 'procurement_classification' => 'operating_expense', 'description' => 'Dịch vụ kết nối', 'quantity' => 1, 'estimated_unit_cost_minor' => 5_000]);
        $service->mutate('request.transition', $company, $maker, ['id' => $request, 'to' => 'submitted']);
        $service->mutate('request.transition', $company, $checker, ['id' => $request, 'to' => 'approved']);
        $vendor = $service->mutate('vendor.create', $company, $maker, ['code' => 'V-SERVICE-POLICY', 'name' => 'Nhà cung cấp dịch vụ']);
        $order = $service->mutate('order.create', $company, $maker, ['purchase_request_id' => $request, 'vendor_id' => $vendor, 'reference' => 'PO-SERVICE-POLICY', 'order_date' => '2026-08-02']);
        $service->mutate('order.transition', $company, $checker, ['id' => $order, 'to' => 'approved']);
        $line = (int) DB::table('purchase_order_lines')->where('purchase_order_id', $order)->value('id');
        $movementsBefore = DB::table('inventory_movements')->count();
        $journalsBefore = DB::table('payroll_accounting_journals')->count();

        try {
            $service->mutate('receipt.create', $company, $maker, ['purchase_order_id' => $order, 'purchase_order_line_id' => $line, 'inventory_location_id' => $location, 'reference' => 'GR-SERVICE-MUST-FAIL', 'receipt_date' => '2026-08-03', 'quantity' => 1]);
            self::fail('Operating expense/service lines must not use Goods Receipt or goods GRNI.');
        } catch (ValidationException $exception) {
            self::assertArrayHasKey('purchase_order_line_id', $exception->errors());
        }

        self::assertSame($movementsBefore, DB::table('inventory_movements')->count());
        self::assertSame($journalsBefore, DB::table('payroll_accounting_journals')->count());
        self::assertSame(0, DB::table('payroll_accounting_journal_lines')->where('account_code_snapshot', '2150')->count());
        self::assertFalse(DB::table('accounting_accounts')->where(['company_id' => $company, 'code' => '2160'])->exists());
        try {
            app(SourceAccountingPostingService::class)->accountEvidence($company, '2160', 'VND', $maker->id);
            self::fail('Unapproved service accrual account must not be created implicitly.');
        } catch (ValidationException $exception) {
            self::assertArrayHasKey('accounting', $exception->errors());
        }
        self::assertFalse(DB::table('accounting_accounts')->where(['company_id' => $company, 'code' => '2160'])->exists());
    }

    public function test_foreign_inventory_receipt_values_stock_and_matches_grni_with_purchase_price_variance(): void
    {
        [$maker, $checker, $company, $branch, $variant, $location] = $this->runtime();
        $rates = app(FxRateService::class);
        foreach ([['2026-10-03', '24690.500000000000'], ['2026-10-04', '25000.000000000000']] as [$date, $rate]) {
            $rateId = $rates->create($company, $maker, ['base_currency' => 'USD', 'quote_currency' => 'VND', 'rate_decimal' => $rate, 'rate_date' => $date, 'rate_type' => 'spot', 'source_reference' => 'INV-FX-'.$date, 'effective_from' => $date, 'effective_to' => $date, 'idempotency_key' => (string) Str::uuid()]);
            $rates->transition($company, $rateId, 'submit', $maker);
            $rates->transition($company, $rateId, 'approve', $checker);
            $rates->transition($company, $rateId, 'activate', $checker);
        }
        $posting = app(SourceAccountingPostingService::class);
        $grni = $posting->accountEvidence($company, '2150', 'VND', $maker->id)['id'];
        $ap = $posting->accountEvidence($company, '2100', 'VND', $maker->id)['id'];
        $ppv = (int) DB::table('accounting_accounts')->insertGetId(['uuid' => (string) Str::uuid(), 'company_id' => $company, 'code' => '5190', 'name' => 'Purchase Price Variance', 'account_type' => 'expense', 'normal_balance' => 'debit', 'currency' => 'VND', 'allow_posting' => true, 'status' => 'active', 'effective_from' => '2020-01-01', 'created_by' => $maker->id, 'created_at' => now(), 'updated_at' => now()]);
        $policies = app(AccountingPostingPolicyService::class);
        $policy = $policies->create($company, $maker, ['policy_key' => 'INVENTORY-BILL-PPV-LOSS', 'source_type' => 'vendor_bill', 'business_event' => 'INVENTORY_VENDOR_BILL_PRICE_VARIANCE_LOSS', 'classification' => 'inventory', 'version' => 1, 'currency' => 'VND', 'effective_from' => '2026-01-01', 'lines' => [
            ['entry_side' => 'debit', 'line_role' => 'GRNI', 'account_id' => $grni],
            ['entry_side' => 'debit', 'line_role' => 'PURCHASE_PRICE_VARIANCE_LOSS', 'account_id' => $ppv],
            ['entry_side' => 'credit', 'line_role' => 'AP', 'account_id' => $ap],
        ]]);
        $policies->submit($company, $policy, $maker);
        $policies->approve($company, $policy, $checker);

        $service = app(ProcurementService::class);
        $vendor = $service->mutate('vendor.create', $company, $maker, ['code' => 'FX-INV-VENDOR', 'name' => 'Foreign inventory vendor']);
        $request = $service->mutate('request.create', $company, $maker, ['branch_id' => $branch, 'reference' => 'PR-FX-INV', 'requested_date' => '2026-10-01', 'reason' => 'Foreign stock', 'product_variant_id' => $variant, 'description' => 'Imported inventory', 'quantity' => 2, 'estimated_unit_cost_minor' => 10_000]);
        $service->mutate('request.transition', $company, $maker, ['id' => $request, 'to' => 'submitted']);
        $service->mutate('request.transition', $company, $checker, ['id' => $request, 'to' => 'approved']);
        $order = $service->mutate('order.create', $company, $maker, ['purchase_request_id' => $request, 'vendor_id' => $vendor, 'reference' => 'PO-FX-INV', 'currency' => 'USD', 'order_date' => '2026-10-02']);
        $service->mutate('order.transition', $company, $checker, ['id' => $order, 'to' => 'approved']);
        $orderLine = (int) DB::table('purchase_order_lines')->where('purchase_order_id', $order)->value('id');
        $receipt = $service->mutate('receipt.create', $company, $maker, ['purchase_order_id' => $order, 'purchase_order_line_id' => $orderLine, 'inventory_location_id' => $location, 'reference' => 'GR-FX-INV', 'receipt_date' => '2026-10-03', 'quantity' => 2]);
        $service->mutate('receipt.receive', $company, $checker, ['id' => $receipt]);

        $receiptLine = (int) DB::table('procurement_goods_receipt_lines')->where('goods_receipt_id', $receipt)->value('id');
        $valuation = DB::table('inventory_receipt_fx_valuations')->where('goods_receipt_line_id', $receiptLine)->firstOrFail();
        self::assertSame('USD', $valuation->transaction_currency);
        self::assertSame('VND', $valuation->functional_currency);
        self::assertSame(20_000, (int) $valuation->foreign_amount_minor);
        self::assertSame(4_938_100, (int) $valuation->functional_amount_minor);
        self::assertSame(2_469_050, (int) $valuation->functional_unit_cost_minor);
        self::assertNotNull($valuation->fx_rate_snapshot_id);
        self::assertSame(4_938_100, (int) DB::table('inventory_movements')->where('id', $valuation->inventory_movement_id)->value('movement_value_minor'));
        $receiptJournal = DB::table('payroll_accounting_journals')->where('id', $valuation->accounting_journal_id)->firstOrFail();
        self::assertSame(4_938_100, (int) $receiptJournal->total_debit_minor);
        self::assertSame(4_938_100, (int) $receiptJournal->total_credit_minor);

        $bill = $service->mutate('bill.create', $company, $maker, ['purchase_order_id' => $order, 'purchase_order_line_id' => $orderLine, 'goods_receipt_line_id' => $receiptLine, 'quantity' => 2, 'invoice_number' => 'VB-FX-INV', 'invoice_date' => '2026-10-04', 'due_date' => '2026-11-04']);
        $service->mutate('bill.approve', $company, $checker, ['id' => $bill]);
        $match = DB::table('vendor_bill_receipt_fx_matches')->where('vendor_bill_id', $bill)->firstOrFail();
        self::assertSame(4_938_100, (int) $match->released_grni_functional_minor);
        self::assertSame(5_000_000, (int) $match->ap_functional_minor);
        self::assertSame(61_900, (int) $match->purchase_price_variance_minor);
        self::assertSame(1, DB::table('supplier_invoice_fx_recognitions')->where('vendor_bill_id', $bill)->count());
        $billJournal = DB::table('payroll_accounting_journals')->where('id', $match->accounting_journal_id)->firstOrFail();
        self::assertSame(5_000_000, (int) $billJournal->total_debit_minor);
        self::assertSame(5_000_000, (int) $billJournal->total_credit_minor);
        self::assertSame($bill, $service->mutate('bill.approve', $company, $checker, ['id' => $bill]));
        self::assertSame(1, DB::table('vendor_bill_receipt_fx_matches')->where('vendor_bill_id', $bill)->count());
    }

    public function test_foreign_inventory_receipt_without_direct_spot_rolls_back_all_economic_effects(): void
    {
        [$maker, $checker, $company, $branch, $variant, $location] = $this->runtime();
        $service = app(ProcurementService::class);
        $vendor = $service->mutate('vendor.create', $company, $maker, ['code' => 'FX-NO-RATE', 'name' => 'No direct rate vendor']);
        $request = $service->mutate('request.create', $company, $maker, ['branch_id' => $branch, 'reference' => 'PR-FX-NO-RATE', 'requested_date' => '2026-10-01', 'reason' => 'Fail closed proof', 'product_variant_id' => $variant, 'description' => 'Imported inventory', 'quantity' => 1, 'estimated_unit_cost_minor' => 10_000]);
        $service->mutate('request.transition', $company, $maker, ['id' => $request, 'to' => 'submitted']);
        $service->mutate('request.transition', $company, $checker, ['id' => $request, 'to' => 'approved']);
        $order = $service->mutate('order.create', $company, $maker, ['purchase_request_id' => $request, 'vendor_id' => $vendor, 'reference' => 'PO-FX-NO-RATE', 'currency' => 'USD', 'order_date' => '2026-10-02']);
        $service->mutate('order.transition', $company, $checker, ['id' => $order, 'to' => 'approved']);
        $line = (int) DB::table('purchase_order_lines')->where('purchase_order_id', $order)->value('id');
        $receipt = $service->mutate('receipt.create', $company, $maker, ['purchase_order_id' => $order, 'purchase_order_line_id' => $line, 'inventory_location_id' => $location, 'reference' => 'GR-FX-NO-RATE', 'receipt_date' => '2026-10-03', 'quantity' => 1]);
        $receiptLine = (int) DB::table('procurement_goods_receipt_lines')->where('goods_receipt_id', $receipt)->value('id');

        $this->assertValidation(fn () => $service->mutate('receipt.receive', $company, $checker, ['id' => $receipt]), 'rate', 'FX_RATE_NOT_AVAILABLE');
        self::assertSame('draft', DB::table('procurement_goods_receipts')->where('id', $receipt)->value('status'));
        self::assertSame(0, DB::table('inventory_movements')->where(['source_type' => 'procurement_goods_receipt_line', 'source_id' => $receiptLine])->count());
        self::assertSame(0, DB::table('inventory_receipt_fx_valuations')->where('goods_receipt_line_id', $receiptLine)->count());
        self::assertSame(0, DB::table('finance_fx_rate_snapshots')->where(['source_type' => 'inventory_receipt_valuation', 'source_id' => $receiptLine])->count());
        self::assertSame(0, DB::table('payroll_accounting_journals')->where(['source_type' => 'goods_receipt', 'source_id' => $receipt])->count());
    }

    public function test_inventory_receipt_fx_currency_identity_and_partial_matching_evidence(): void
    {
        foreach ([
            ['currency' => 'JPY', 'rate' => '165.250000000000', 'unit_minor' => 101, 'quantity' => 3],
            ['currency' => 'EUR', 'rate' => '27158.500000000000', 'unit_minor' => 12345, 'quantity' => 3],
            ['currency' => 'VND', 'rate' => null, 'unit_minor' => 12_345, 'quantity' => 3],
        ] as $case) {
            [$maker, $checker, $company, $branch, $variant, $location] = $this->runtime();
            $currency = $case['currency'];
            if (is_string($case['rate'])) {
                $this->activateSpot($company, $maker, $checker, $currency, '2026-10-03', $case['rate']);
                $this->activateSpot($company, $maker, $checker, $currency, '2026-10-04', $case['rate']);
            }
            $this->inventoryBillZeroVariancePolicy($company, $maker, $checker);
            $service = app(ProcurementService::class);
            $vendor = $service->mutate('vendor.create', $company, $maker, ['code' => 'V-'.$currency, 'name' => $currency.' inventory vendor']);
            $request = $service->mutate('request.create', $company, $maker, ['branch_id' => $branch, 'reference' => 'PR-'.$currency, 'requested_date' => '2026-10-01', 'reason' => 'Currency evidence', 'product_variant_id' => $variant, 'description' => $currency.' inventory', 'quantity' => $case['quantity'], 'estimated_unit_cost_minor' => $case['unit_minor']]);
            $service->mutate('request.transition', $company, $maker, ['id' => $request, 'to' => 'submitted']);
            $service->mutate('request.transition', $company, $checker, ['id' => $request, 'to' => 'approved']);
            $order = $service->mutate('order.create', $company, $maker, ['purchase_request_id' => $request, 'vendor_id' => $vendor, 'reference' => 'PO-'.$currency, 'currency' => $currency, 'order_date' => '2026-10-02']);
            $service->mutate('order.transition', $company, $checker, ['id' => $order, 'to' => 'approved']);
            $orderLine = (int) DB::table('purchase_order_lines')->where('purchase_order_id', $order)->value('id');
            $receipt = $service->mutate('receipt.create', $company, $maker, ['purchase_order_id' => $order, 'purchase_order_line_id' => $orderLine, 'inventory_location_id' => $location, 'reference' => 'GR-'.$currency, 'receipt_date' => '2026-10-03', 'quantity' => $case['quantity']]);
            $service->mutate('receipt.receive', $company, $checker, ['id' => $receipt]);
            $receiptLine = (int) DB::table('procurement_goods_receipt_lines')->where('goods_receipt_id', $receipt)->value('id');
            $valuation = DB::table('inventory_receipt_fx_valuations')->where('goods_receipt_line_id', $receiptLine)->firstOrFail();
            $foreign = $case['unit_minor'] * $case['quantity'];
            $expected = is_string($case['rate'])
                ? app(FxMoneyConverter::class)->convertMinor($foreign, $currency, 'VND', $case['rate'])
                : $foreign;
            self::assertSame($currency, $valuation->transaction_currency);
            self::assertSame($expected, (int) $valuation->functional_amount_minor);
            self::assertSame($currency === 'VND' ? 'identity' : 'spot', $valuation->rate_type);
            self::assertSame($currency === 'VND', $valuation->fx_rate_snapshot_id === null);
            self::assertSame($expected, (int) DB::table('inventory_movements')->where('id', $valuation->inventory_movement_id)->value('movement_value_minor'));

            $billOne = $service->mutate('bill.create', $company, $maker, ['purchase_order_id' => $order, 'purchase_order_line_id' => $orderLine, 'goods_receipt_line_id' => $receiptLine, 'quantity' => 1, 'invoice_number' => 'VB-'.$currency.'-1', 'invoice_date' => '2026-10-04', 'due_date' => '2026-11-04']);
            $service->mutate('bill.approve', $company, $checker, ['id' => $billOne]);
            $billTwo = $service->mutate('bill.create', $company, $maker, ['purchase_order_id' => $order, 'purchase_order_line_id' => $orderLine, 'goods_receipt_line_id' => $receiptLine, 'quantity' => 2, 'invoice_number' => 'VB-'.$currency.'-2', 'invoice_date' => '2026-10-04', 'due_date' => '2026-11-04']);
            $service->mutate('bill.approve', $company, $checker, ['id' => $billTwo]);
            $matches = DB::table('vendor_bill_receipt_fx_matches')->where('inventory_receipt_fx_valuation_id', $valuation->id)->get();
            if ($currency === 'VND') {
                self::assertCount(0, $matches);
                self::assertSame(2, DB::table('supplier_invoice_fx_recognitions')->whereIn('vendor_bill_id', [$billOne, $billTwo])->where('rate_type', 'identity')->whereNull('fx_rate_snapshot_id')->count());
                self::assertSame(2, DB::table('payroll_accounting_journals')->where('source_type', 'vendor_bill')->whereIn('source_id', [$billOne, $billTwo])->count());

                continue;
            }
            self::assertCount(2, $matches);
            self::assertSame($expected, (int) $matches->sum('released_grni_functional_minor'));
            self::assertSame(
                (int) $matches->sum('ap_functional_minor') - $expected,
                (int) $matches->sum('purchase_price_variance_minor'),
            );
            self::assertSame($billTwo, $service->mutate('bill.approve', $company, $checker, ['id' => $billTwo]));
            self::assertSame(2, DB::table('vendor_bill_receipt_fx_matches')->where('inventory_receipt_fx_valuation_id', $valuation->id)->count());
        }
    }

    public function test_foreign_goods_return_releases_cumulative_original_functional_basis_with_final_residue(): void
    {
        [$maker, $checker, $company, $branch, $variant, $location] = $this->runtime();
        $this->activateSpot($company, $maker, $checker, 'USD', '2026-10-03', '24690.500000000000');
        $grni = (int) DB::table('accounting_accounts')->where(['company_id' => $company, 'code' => '2150'])->value('id');
        $inventory = (int) DB::table('accounting_accounts')->where(['company_id' => $company, 'code' => '1200'])->value('id');
        $policies = app(AccountingPostingPolicyService::class);
        $returnPolicy = $policies->create($company, $maker, ['policy_key' => 'FX-GOODS-RETURN', 'source_type' => 'goods_return', 'business_event' => 'GOODS_RETURN_TO_VENDOR', 'version' => 1, 'currency' => 'VND', 'effective_from' => '2026-01-01', 'lines' => [
            ['entry_side' => 'debit', 'line_role' => 'GRNI', 'account_id' => $grni],
            ['entry_side' => 'credit', 'line_role' => 'INVENTORY_ASSET', 'account_id' => $inventory],
        ]]);
        $policies->submit($company, $returnPolicy, $maker);
        $policies->approve($company, $returnPolicy, $checker);
        $service = app(ProcurementService::class);
        $vendor = $service->mutate('vendor.create', $company, $maker, ['code' => 'FX-RTV', 'name' => 'FX return vendor']);
        $request = $service->mutate('request.create', $company, $maker, ['branch_id' => $branch, 'reference' => 'PR-FX-RTV', 'requested_date' => '2026-10-01', 'reason' => 'Foreign return evidence', 'product_variant_id' => $variant, 'description' => 'Imported inventory', 'quantity' => 3, 'estimated_unit_cost_minor' => 101]);
        $service->mutate('request.transition', $company, $maker, ['id' => $request, 'to' => 'submitted']);
        $service->mutate('request.transition', $company, $checker, ['id' => $request, 'to' => 'approved']);
        $order = $service->mutate('order.create', $company, $maker, ['purchase_request_id' => $request, 'vendor_id' => $vendor, 'reference' => 'PO-FX-RTV', 'currency' => 'USD', 'order_date' => '2026-10-02']);
        $service->mutate('order.transition', $company, $checker, ['id' => $order, 'to' => 'approved']);
        $orderLine = (int) DB::table('purchase_order_lines')->where('purchase_order_id', $order)->value('id');
        $openingValue = (int) DB::table('inventory_balances')->where(['product_variant_id' => $variant, 'inventory_location_id' => $location])->value('inventory_value_minor');
        $receipt = $service->mutate('receipt.create', $company, $maker, ['purchase_order_id' => $order, 'purchase_order_line_id' => $orderLine, 'inventory_location_id' => $location, 'reference' => 'GR-FX-RTV', 'receipt_date' => '2026-10-03', 'quantity' => 3]);
        $service->mutate('receipt.receive', $company, $checker, ['id' => $receipt]);
        $receiptLine = (int) DB::table('procurement_goods_receipt_lines')->where('goods_receipt_id', $receipt)->value('id');
        $valuation = DB::table('inventory_receipt_fx_valuations')->where('goods_receipt_line_id', $receiptLine)->firstOrFail();
        $returns = app(GoodsReturnService::class);

        foreach ([[1, 'RTV-FX-1', 'rtv-fx-1'], [2, 'RTV-FX-2', 'rtv-fx-2']] as [$quantity, $number, $key]) {
            $id = $returns->create($company, $maker, ['goods_receipt_line_id' => $receiptLine, 'quantity' => $quantity, 'return_number' => $number, 'return_date' => '2026-10-05', 'reason' => 'Return imported inventory', 'idempotency_key' => $key]);
            $returns->submit($company, $id, $maker);
            $returns->approve($company, $id, $checker);
            self::assertSame($id, $returns->post($company, $id, $checker));
            self::assertSame($id, $returns->post($company, $id, $checker));
        }

        $evidence = DB::table('inventory_return_fx_reversals')->where('inventory_receipt_fx_valuation_id', $valuation->id)->orderBy('id')->get();
        self::assertCount(2, $evidence);
        self::assertSame((int) $valuation->functional_amount_minor, (int) $evidence->sum('released_functional_basis_minor'));
        self::assertSame(3, (int) $evidence->last()->cumulative_return_quantity);
        self::assertSame((int) $valuation->functional_amount_minor, (int) $evidence->last()->cumulative_released_basis_minor);
        self::assertSame(2, DB::table('inventory_movements')->where('source_type', 'procurement_goods_return_line')->count());
        self::assertSame(2, DB::table('payroll_accounting_journals')->where('source_type', 'goods_return')->where('currency', 'VND')->count());
        self::assertSame($openingValue, (int) DB::table('inventory_balances')->where(['product_variant_id' => $variant, 'inventory_location_id' => $location])->value('inventory_value_minor'));
    }

    public function test_inventory_receipt_fx_rejects_closing_inverse_and_missing_functional_currency_without_partial_effects(): void
    {
        foreach (['closing', 'inverse', 'missing_functional'] as $mode) {
            [$maker, $checker, $company, $branch, $variant, $location] = $this->runtime();
            if ($mode === 'missing_functional') {
                DB::table('companies')->where('id', $company)->update(['functional_currency_code' => null]);
            } else {
                $rates = app(FxRateService::class);
                $base = $mode === 'inverse' ? 'VND' : 'USD';
                $quote = $mode === 'inverse' ? 'USD' : 'VND';
                $type = $mode === 'closing' ? 'closing' : 'spot';
                $rate = $rates->create($company, $maker, ['base_currency' => $base, 'quote_currency' => $quote, 'rate_decimal' => $mode === 'inverse' ? '0.000040000000' : '25000.000000000000', 'rate_date' => '2026-10-03', 'rate_type' => $type, 'source_reference' => 'REJECT-'.$mode, 'effective_from' => '2026-10-03', 'effective_to' => '2026-10-03', 'idempotency_key' => (string) Str::uuid()]);
                $rates->transition($company, $rate, 'submit', $maker);
                $rates->transition($company, $rate, 'approve', $checker);
                $rates->transition($company, $rate, 'activate', $checker);
            }
            $service = app(ProcurementService::class);
            $vendor = $service->mutate('vendor.create', $company, $maker, ['code' => 'REJECT-'.$mode, 'name' => 'Reject vendor']);
            $request = $service->mutate('request.create', $company, $maker, ['branch_id' => $branch, 'reference' => 'PR-REJECT-'.$mode, 'requested_date' => '2026-10-01', 'reason' => 'Fail closed evidence', 'product_variant_id' => $variant, 'description' => 'Imported inventory', 'quantity' => 1, 'estimated_unit_cost_minor' => 10_000]);
            $service->mutate('request.transition', $company, $maker, ['id' => $request, 'to' => 'submitted']);
            $service->mutate('request.transition', $company, $checker, ['id' => $request, 'to' => 'approved']);
            $order = $service->mutate('order.create', $company, $maker, ['purchase_request_id' => $request, 'vendor_id' => $vendor, 'reference' => 'PO-REJECT-'.$mode, 'currency' => 'USD', 'order_date' => '2026-10-02']);
            $service->mutate('order.transition', $company, $checker, ['id' => $order, 'to' => 'approved']);
            $line = (int) DB::table('purchase_order_lines')->where('purchase_order_id', $order)->value('id');
            $receipt = $service->mutate('receipt.create', $company, $maker, ['purchase_order_id' => $order, 'purchase_order_line_id' => $line, 'inventory_location_id' => $location, 'reference' => 'GR-REJECT-'.$mode, 'receipt_date' => '2026-10-03', 'quantity' => 1]);
            $receiptLine = (int) DB::table('procurement_goods_receipt_lines')->where('goods_receipt_id', $receipt)->value('id');
            $key = $mode === 'missing_functional' ? 'functional_currency' : 'rate';
            $message = $mode === 'missing_functional' ? 'FUNCTIONAL_CURRENCY_NOT_CONFIGURED' : 'FX_RATE_NOT_AVAILABLE';
            $this->assertValidation(fn () => $service->mutate('receipt.receive', $company, $checker, ['id' => $receipt]), $key, $message);
            self::assertSame('draft', DB::table('procurement_goods_receipts')->where('id', $receipt)->value('status'));
            self::assertSame(0, DB::table('inventory_movements')->where(['source_type' => 'procurement_goods_receipt_line', 'source_id' => $receiptLine])->count());
            self::assertSame(0, DB::table('inventory_receipt_fx_valuations')->where('goods_receipt_line_id', $receiptLine)->count());
            self::assertSame(0, DB::table('finance_fx_rate_snapshots')->where(['source_type' => 'inventory_receipt_valuation', 'source_id' => $receiptLine])->count());
        }
    }

    public function test_inventory_receipt_fx_data_quality_rules_detect_each_corrupted_invariant(): void
    {
        $fixture = $this->inventoryFxFixture();
        $audit = app(DataQualityAuditService::class);
        $count = static function (array $snapshot, string $key): int {
            $finding = collect($snapshot['findings'])->firstWhere('key', $key);

            return is_array($finding) ? (int) ($finding['count'] ?? -1) : -1;
        };
        foreach (['inventory_receipt_fx_orphan_or_scope_mismatch', 'inventory_receipt_fx_snapshot_mismatch', 'inventory_receipt_fx_movement_or_journal_mismatch', 'inventory_receipt_fx_bill_match_mismatch'] as $key) {
            self::assertSame(0, $count($audit->snapshot(), $key));
        }

        $order = (array) DB::table('purchase_orders')->where('id', $fixture['order'])->firstOrFail();
        $request = (array) DB::table('purchase_requests')->where('id', $order['purchase_request_id'])->firstOrFail();
        unset($request['id']);
        $request['reference'] = 'PR-DQ-OTHER';
        $otherRequest = (int) DB::table('purchase_requests')->insertGetId($request);
        unset($order['id']);
        $order['reference'] = 'PO-DQ-OTHER';
        $order['purchase_request_id'] = $otherRequest;
        $otherOrder = (int) DB::table('purchase_orders')->insertGetId($order);
        DB::table('inventory_receipt_fx_valuations')->where('id', $fixture['valuation'])->update(['purchase_order_id' => $otherOrder]);
        self::assertSame(1, $count($audit->snapshot(), 'inventory_receipt_fx_orphan_or_scope_mismatch'));
        DB::table('inventory_receipt_fx_valuations')->where('id', $fixture['valuation'])->update(['purchase_order_id' => $fixture['order']]);

        $originalRate = DB::table('finance_fx_rate_snapshots')->where('id', $fixture['snapshot'])->value('rate_decimal');
        DB::table('finance_fx_rate_snapshots')->where('id', $fixture['snapshot'])->update(['rate_decimal' => '1.000000000000']);
        self::assertSame(1, $count($audit->snapshot(), 'inventory_receipt_fx_snapshot_mismatch'));
        DB::table('finance_fx_rate_snapshots')->where('id', $fixture['snapshot'])->update(['rate_decimal' => $originalRate]);

        $originalMovement = (int) DB::table('inventory_movements')->where('id', $fixture['movement'])->value('movement_value_minor');
        DB::table('inventory_movements')->where('id', $fixture['movement'])->update(['movement_value_minor' => $originalMovement + 1]);
        self::assertSame(1, $count($audit->snapshot(), 'inventory_receipt_fx_movement_or_journal_mismatch'));
        DB::table('inventory_movements')->where('id', $fixture['movement'])->update(['movement_value_minor' => $originalMovement]);

        $originalVariance = (int) DB::table('vendor_bill_receipt_fx_matches')->where('id', $fixture['match'])->value('purchase_price_variance_minor');
        DB::table('vendor_bill_receipt_fx_matches')->where('id', $fixture['match'])->update(['purchase_price_variance_minor' => $originalVariance + 1]);
        self::assertSame(1, $count($audit->snapshot(), 'inventory_receipt_fx_bill_match_mismatch'));
        DB::table('vendor_bill_receipt_fx_matches')->where('id', $fixture['match'])->update(['purchase_price_variance_minor' => $originalVariance]);

        DB::table('purchase_orders')->where('id', $otherOrder)->delete();
        DB::table('purchase_requests')->where('id', $otherRequest)->delete();
        foreach (['inventory_receipt_fx_orphan_or_scope_mismatch', 'inventory_receipt_fx_snapshot_mismatch', 'inventory_receipt_fx_movement_or_journal_mismatch', 'inventory_receipt_fx_bill_match_mismatch'] as $key) {
            self::assertSame(0, $count($audit->snapshot(), $key));
        }
    }

    public function test_inventory_receipt_fx_policy_period_and_account_failures_roll_back_every_effect(): void
    {
        foreach (['missing_policy', 'locked_period', 'invalid_account'] as $mode) {
            [$maker, $checker, $company, $branch, $variant, $location] = $this->runtime();
            $this->activateSpot($company, $maker, $checker, 'USD', '2026-10-03', '24690.500000000000');
            if ($mode === 'missing_policy') {
                $policyIds = DB::table('accounting_posting_policies')->where(['company_id' => $company, 'business_event' => 'INVENTORY_RECEIPT'])->pluck('id');
                DB::table('accounting_posting_policy_lines')->whereIn('accounting_posting_policy_id', $policyIds)->delete();
                DB::table('accounting_posting_policies')->whereIn('id', $policyIds)->delete();
            } elseif ($mode === 'locked_period') {
                DB::table('accounting_periods')->insert(['company_id' => $company, 'year' => 2026, 'month' => 10, 'start_date' => '2026-10-01', 'end_date' => '2026-10-31', 'status' => 'closed', 'created_at' => now(), 'updated_at' => now()]);
            } else {
                DB::table('accounting_accounts')->where(['company_id' => $company, 'code' => '1200'])->update(['allow_posting' => false]);
            }
            $service = app(ProcurementService::class);
            $vendor = $service->mutate('vendor.create', $company, $maker, ['code' => 'ROLLBACK-'.$mode, 'name' => 'Rollback vendor']);
            $request = $service->mutate('request.create', $company, $maker, ['branch_id' => $branch, 'reference' => 'PR-'.$mode, 'requested_date' => '2026-10-01', 'reason' => 'Rollback evidence', 'product_variant_id' => $variant, 'description' => 'Imported inventory', 'quantity' => 1, 'estimated_unit_cost_minor' => 10_000]);
            $service->mutate('request.transition', $company, $maker, ['id' => $request, 'to' => 'submitted']);
            $service->mutate('request.transition', $company, $checker, ['id' => $request, 'to' => 'approved']);
            $order = $service->mutate('order.create', $company, $maker, ['purchase_request_id' => $request, 'vendor_id' => $vendor, 'reference' => 'PO-'.$mode, 'currency' => 'USD', 'order_date' => '2026-10-02']);
            $service->mutate('order.transition', $company, $checker, ['id' => $order, 'to' => 'approved']);
            $line = (int) DB::table('purchase_order_lines')->where('purchase_order_id', $order)->value('id');
            $receipt = $service->mutate('receipt.create', $company, $maker, ['purchase_order_id' => $order, 'purchase_order_line_id' => $line, 'inventory_location_id' => $location, 'reference' => 'GR-'.$mode, 'receipt_date' => '2026-10-03', 'quantity' => 1]);
            $receiptLine = (int) DB::table('procurement_goods_receipt_lines')->where('goods_receipt_id', $receipt)->value('id');
            try {
                $service->mutate('receipt.receive', $company, $checker, ['id' => $receipt]);
                self::fail('Receipt accounting governance failure must rollback: '.$mode);
            } catch (ValidationException) {
                self::assertTrue(true);
            }
            self::assertSame('draft', DB::table('procurement_goods_receipts')->where('id', $receipt)->value('status'));
            self::assertSame(0, DB::table('inventory_movements')->where(['source_type' => 'procurement_goods_receipt_line', 'source_id' => $receiptLine])->count());
            self::assertSame(0, DB::table('inventory_receipt_fx_valuations')->where('goods_receipt_line_id', $receiptLine)->count());
            self::assertSame(0, DB::table('finance_fx_rate_snapshots')->where(['source_type' => 'inventory_receipt_valuation', 'source_id' => $receiptLine])->count());
            self::assertSame(0, DB::table('payroll_accounting_journals')->where(['source_type' => 'goods_receipt', 'source_id' => $receipt])->count());
            self::assertSame(0, (int) DB::table('purchase_order_lines')->where('id', $line)->value('received_quantity'));
        }
    }

    private function activateSpot(int $company, User $maker, User $checker, string $currency, string $date, string $rate): void
    {
        $service = app(FxRateService::class);
        $id = $service->create($company, $maker, ['base_currency' => $currency, 'quote_currency' => 'VND', 'rate_decimal' => $rate, 'rate_date' => $date, 'rate_type' => 'spot', 'source_reference' => 'INV-'.$currency.'-'.$date, 'effective_from' => $date, 'effective_to' => $date, 'idempotency_key' => (string) Str::uuid()]);
        $service->transition($company, $id, 'submit', $maker);
        $service->transition($company, $id, 'approve', $checker);
        $service->transition($company, $id, 'activate', $checker);
    }

    private function inventoryBillZeroVariancePolicy(int $company, User $maker, User $checker): void
    {
        $grni = (int) DB::table('accounting_accounts')->where(['company_id' => $company, 'code' => '2150'])->value('id');
        $ap = (int) DB::table('accounting_accounts')->where(['company_id' => $company, 'code' => '2100'])->value('id');
        $loss = (int) DB::table('accounting_accounts')->insertGetId(['uuid' => (string) Str::uuid(), 'company_id' => $company, 'code' => '5190', 'name' => 'Purchase Price Variance Loss', 'account_type' => 'expense', 'normal_balance' => 'debit', 'currency' => 'VND', 'allow_posting' => true, 'status' => 'active', 'effective_from' => '2020-01-01', 'created_by' => $maker->id, 'created_at' => now(), 'updated_at' => now()]);
        $gain = (int) DB::table('accounting_accounts')->insertGetId(['uuid' => (string) Str::uuid(), 'company_id' => $company, 'code' => '4190', 'name' => 'Purchase Price Variance Gain', 'account_type' => 'revenue', 'normal_balance' => 'credit', 'currency' => 'VND', 'allow_posting' => true, 'status' => 'active', 'effective_from' => '2020-01-01', 'created_by' => $maker->id, 'created_at' => now(), 'updated_at' => now()]);
        $service = app(AccountingPostingPolicyService::class);
        foreach ([
            ['INVENTORY-BILL-ZERO', 'INVENTORY_VENDOR_BILL_POSTED', [['entry_side' => 'debit', 'line_role' => 'GRNI', 'account_id' => $grni], ['entry_side' => 'credit', 'line_role' => 'AP', 'account_id' => $ap]]],
            ['INVENTORY-BILL-LOSS', 'INVENTORY_VENDOR_BILL_PRICE_VARIANCE_LOSS', [['entry_side' => 'debit', 'line_role' => 'GRNI', 'account_id' => $grni], ['entry_side' => 'debit', 'line_role' => 'PURCHASE_PRICE_VARIANCE_LOSS', 'account_id' => $loss], ['entry_side' => 'credit', 'line_role' => 'AP', 'account_id' => $ap]]],
            ['INVENTORY-BILL-GAIN', 'INVENTORY_VENDOR_BILL_PRICE_VARIANCE_GAIN', [['entry_side' => 'debit', 'line_role' => 'GRNI', 'account_id' => $grni], ['entry_side' => 'credit', 'line_role' => 'PURCHASE_PRICE_VARIANCE_GAIN', 'account_id' => $gain], ['entry_side' => 'credit', 'line_role' => 'AP', 'account_id' => $ap]]],
        ] as [$key, $event, $lines]) {
            $id = $service->create($company, $maker, ['policy_key' => $key, 'source_type' => 'vendor_bill', 'business_event' => $event, 'classification' => 'inventory', 'version' => 1, 'currency' => 'VND', 'effective_from' => '2026-01-01', 'lines' => $lines]);
            $service->submit($company, $id, $maker);
            $service->approve($company, $id, $checker);
        }
    }

    /** @return array{order:int,valuation:int,snapshot:int,movement:int,match:int} */
    private function inventoryFxFixture(): array
    {
        [$maker, $checker, $company, $branch, $variant, $location] = $this->runtime();
        $this->activateSpot($company, $maker, $checker, 'USD', '2026-10-03', '24690.500000000000');
        $this->activateSpot($company, $maker, $checker, 'USD', '2026-10-04', '25000.000000000000');
        $grni = (int) DB::table('accounting_accounts')->where(['company_id' => $company, 'code' => '2150'])->value('id');
        $ap = (int) DB::table('accounting_accounts')->where(['company_id' => $company, 'code' => '2100'])->value('id');
        $loss = (int) DB::table('accounting_accounts')->insertGetId(['uuid' => (string) Str::uuid(), 'company_id' => $company, 'code' => '5190', 'name' => 'Purchase Price Variance', 'account_type' => 'expense', 'normal_balance' => 'debit', 'currency' => 'VND', 'allow_posting' => true, 'status' => 'active', 'effective_from' => '2020-01-01', 'created_by' => $maker->id, 'created_at' => now(), 'updated_at' => now()]);
        $policies = app(AccountingPostingPolicyService::class);
        $policy = $policies->create($company, $maker, ['policy_key' => 'INVENTORY-DQ-LOSS', 'source_type' => 'vendor_bill', 'business_event' => 'INVENTORY_VENDOR_BILL_PRICE_VARIANCE_LOSS', 'classification' => 'inventory', 'version' => 1, 'currency' => 'VND', 'effective_from' => '2026-01-01', 'lines' => [['entry_side' => 'debit', 'line_role' => 'GRNI', 'account_id' => $grni], ['entry_side' => 'debit', 'line_role' => 'PURCHASE_PRICE_VARIANCE_LOSS', 'account_id' => $loss], ['entry_side' => 'credit', 'line_role' => 'AP', 'account_id' => $ap]]]);
        $policies->submit($company, $policy, $maker);
        $policies->approve($company, $policy, $checker);
        $service = app(ProcurementService::class);
        $vendor = $service->mutate('vendor.create', $company, $maker, ['code' => 'DQ-VENDOR', 'name' => 'DQ vendor']);
        $request = $service->mutate('request.create', $company, $maker, ['branch_id' => $branch, 'reference' => 'DQ-PR', 'requested_date' => '2026-10-01', 'reason' => 'DQ fixture', 'product_variant_id' => $variant, 'description' => 'DQ imported inventory', 'quantity' => 2, 'estimated_unit_cost_minor' => 10_000]);
        $service->mutate('request.transition', $company, $maker, ['id' => $request, 'to' => 'submitted']);
        $service->mutate('request.transition', $company, $checker, ['id' => $request, 'to' => 'approved']);
        $order = $service->mutate('order.create', $company, $maker, ['purchase_request_id' => $request, 'vendor_id' => $vendor, 'reference' => 'DQ-PO', 'currency' => 'USD', 'order_date' => '2026-10-02']);
        $service->mutate('order.transition', $company, $checker, ['id' => $order, 'to' => 'approved']);
        $orderLine = (int) DB::table('purchase_order_lines')->where('purchase_order_id', $order)->value('id');
        $receipt = $service->mutate('receipt.create', $company, $maker, ['purchase_order_id' => $order, 'purchase_order_line_id' => $orderLine, 'inventory_location_id' => $location, 'reference' => 'DQ-GR', 'receipt_date' => '2026-10-03', 'quantity' => 2]);
        $service->mutate('receipt.receive', $company, $checker, ['id' => $receipt]);
        $receiptLine = (int) DB::table('procurement_goods_receipt_lines')->where('goods_receipt_id', $receipt)->value('id');
        $valuation = DB::table('inventory_receipt_fx_valuations')->where('goods_receipt_line_id', $receiptLine)->firstOrFail();
        $bill = $service->mutate('bill.create', $company, $maker, ['purchase_order_id' => $order, 'purchase_order_line_id' => $orderLine, 'goods_receipt_line_id' => $receiptLine, 'quantity' => 2, 'invoice_number' => 'DQ-VB', 'invoice_date' => '2026-10-04', 'due_date' => '2026-11-04']);
        $service->mutate('bill.approve', $company, $checker, ['id' => $bill]);

        return ['order' => $order, 'valuation' => (int) $valuation->id, 'snapshot' => (int) $valuation->fx_rate_snapshot_id, 'movement' => (int) $valuation->inventory_movement_id, 'match' => (int) DB::table('vendor_bill_receipt_fx_matches')->where('vendor_bill_id', $bill)->value('id')];
    }

    private function expectSelfApprovalBlocked(ProcurementService $service, int $company, User $maker, int $request): void
    {
        try {
            $service->mutate('request.transition', $company, $maker, ['id' => $request, 'to' => 'approved']);
            self::fail('Requester self approval should be blocked.');
        } catch (ValidationException $exception) {
            self::assertArrayHasKey('actor', $exception->errors());
        }
    }

    /** @return array{as_of_date:string,vendor_id:null,branch_id:null,cost_center_id:null,due_bucket:null,payment_status:null,currency:string,sort:string,direction:string} */
    private function apFilters(string $asOf): array
    {
        return ['as_of_date' => $asOf, 'vendor_id' => null, 'branch_id' => null, 'cost_center_id' => null, 'due_bucket' => null, 'payment_status' => null, 'currency' => 'VND', 'sort' => 'outstanding', 'direction' => 'desc'];
    }

    private function assertValidation(callable $callback, string $key, string $message): void
    {
        try {
            $callback();
            self::fail('Expected validation exception.');
        } catch (ValidationException $exception) {
            self::assertSame($message, $exception->errors()[$key][0] ?? null);
        }
    }

    private function glNet(int $companyId, string $accountCode): int
    {
        return (int) DB::table('payroll_accounting_journal_lines as l')->join('payroll_accounting_journals as j', 'j.id', '=', 'l.journal_id')->where(['j.company_id' => $companyId, 'l.account_code_snapshot' => $accountCode])->whereIn('j.status', ['internally_posted', 'exported'])->selectRaw('COALESCE(SUM(CAST(l.debit_minor AS SIGNED)-CAST(l.credit_minor AS SIGNED)),0) net')->value('net');
    }

    /** @return array{User,User,int,int,int,int} */
    private function runtime(): array
    {
        $token = Str::lower(Str::random(8));
        $maker = User::factory()->create(['email_verified_at' => now()]);
        $checker = User::factory()->create(['email_verified_at' => now()]);
        $company = (int) DB::table('companies')->insertGetId(['uuid' => (string) Str::uuid(), 'code' => 'PROC-'.$token, 'name' => 'Procurement Company', 'currency_code' => 'VND', 'functional_currency_code' => 'VND', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
        $branch = (int) DB::table('branches')->insertGetId(['company_id' => $company, 'code' => 'HQ-'.$token, 'name' => 'Head Office', 'status' => 'active', 'timezone' => 'Asia/Ho_Chi_Minh', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('company_user_access')->insert([['company_id' => $company, 'user_id' => $maker->id, 'created_at' => now(), 'updated_at' => now()], ['company_id' => $company, 'user_id' => $checker->id, 'created_at' => now(), 'updated_at' => now()]]);
        $profile = (int) DB::table('seller_profiles')->insertGetId(['company_id' => $company, 'user_id' => $maker->id, 'display_name' => 'Procurement Store Owner', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
        $store = (int) DB::table('stores')->insertGetId(['seller_profile_id' => $profile, 'name' => 'Procurement Store', 'slug' => 'procurement-store-'.$token, 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
        $product = (int) DB::table('products')->insertGetId(['store_id' => $store, 'code' => 'PROC-P-'.$token, 'name' => 'Thiết bị', 'slug' => 'thiet-bi-proc-'.$token, 'base_price_minor' => 500, 'currency' => 'VND', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
        $variant = (int) DB::table('product_variants')->insertGetId(['product_id' => $product, 'sku' => 'PROC-SKU-'.$token, 'name' => 'Mặc định', 'price_minor' => 500, 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
        $location = (int) DB::table('inventory_locations')->insertGetId(['store_id' => $store, 'branch_id' => $branch, 'code' => 'HQ-'.$token, 'name' => 'Kho HQ', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('inventory_balances')->insert(['product_variant_id' => $variant, 'inventory_location_id' => $location, 'on_hand' => 10, 'reserved' => 0, 'inventory_value_minor' => 1000, 'average_unit_cost_minor' => 100, 'version' => 1, 'created_at' => now(), 'updated_at' => now()]);
        $posting = app(SourceAccountingPostingService::class);
        $inventory = $posting->accountEvidence($company, '1200', 'VND', $maker->id)['id'];
        $grni = $posting->accountEvidence($company, '2150', 'VND', $maker->id)['id'];
        $ap = $posting->accountEvidence($company, '2100', 'VND', $maker->id)['id'];
        $cash = $posting->accountEvidence($company, '1100', 'VND', $maker->id)['id'];
        $policies = app(AccountingPostingPolicyService::class);
        foreach ([
            ['INVENTORY-RECEIPT', 'goods_receipt', 'INVENTORY_RECEIPT', 'inventory', [['entry_side' => 'debit', 'line_role' => 'INVENTORY', 'account_id' => $inventory, 'requires_cost_center' => true], ['entry_side' => 'credit', 'line_role' => 'GRNI', 'account_id' => $grni]]],
            ['VENDOR-BILL', 'vendor_bill', 'VENDOR_BILL_POSTED', 'inventory', [['entry_side' => 'debit', 'line_role' => 'GRNI', 'account_id' => $grni], ['entry_side' => 'credit', 'line_role' => 'AP', 'account_id' => $ap]]],
            ['VENDOR-PAYMENT', 'vendor_payment', 'VENDOR_PAYMENT', null, [['entry_side' => 'debit', 'line_role' => 'AP', 'account_id' => $ap], ['entry_side' => 'credit', 'line_role' => 'CASH', 'account_id' => $cash]]],
        ] as [$key, $source, $event, $classification, $lines]) {
            $policyId = $policies->create($company, $maker, ['policy_key' => $key, 'source_type' => $source, 'business_event' => $event, 'classification' => $classification, 'version' => 1, 'currency' => 'VND', 'effective_from' => '2026-01-01', 'lines' => $lines]);
            $policies->submit($company, $policyId, $maker);
            $policies->approve($company, $policyId, $checker);
        }

        return [$maker, $checker, $company, $branch, $variant, $location];
    }
}
