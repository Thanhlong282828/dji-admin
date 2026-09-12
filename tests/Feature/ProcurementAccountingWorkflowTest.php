<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Database\RecordNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Modules\Finance\Application\Services\CostCenterCostManagementReadService;
use Modules\Finance\Application\Services\SourceAccountingPostingService;
use Modules\Payroll\Domain\Repositories\AccountingReadRepositoryInterface;
use Modules\Procurement\Application\Services\ProcurementService;
use Tests\TestCase;

final class ProcurementAccountingWorkflowTest extends TestCase
{
    use RefreshDatabase;

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

    private function expectSelfApprovalBlocked(ProcurementService $service, int $company, User $maker, int $request): void
    {
        try {
            $service->mutate('request.transition', $company, $maker, ['id' => $request, 'to' => 'approved']);
            self::fail('Requester self approval should be blocked.');
        } catch (ValidationException $exception) {
            self::assertArrayHasKey('actor', $exception->errors());
        }
    }

    private function glNet(int $companyId, string $accountCode): int
    {
        return (int) DB::table('payroll_accounting_journal_lines as l')->join('payroll_accounting_journals as j', 'j.id', '=', 'l.journal_id')->where(['j.company_id' => $companyId, 'l.account_code_snapshot' => $accountCode])->whereIn('j.status', ['internally_posted', 'exported'])->selectRaw('COALESCE(SUM(CAST(l.debit_minor AS SIGNED)-CAST(l.credit_minor AS SIGNED)),0) net')->value('net');
    }

    /** @return array{User,User,int,int,int,int} */
    private function runtime(): array
    {
        $maker = User::factory()->create(['email_verified_at' => now()]);
        $checker = User::factory()->create(['email_verified_at' => now()]);
        $company = (int) DB::table('companies')->insertGetId(['uuid' => (string) Str::uuid(), 'code' => 'PROC', 'name' => 'Procurement Company', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
        $branch = (int) DB::table('branches')->insertGetId(['company_id' => $company, 'code' => 'HQ', 'name' => 'Head Office', 'status' => 'active', 'timezone' => 'Asia/Ho_Chi_Minh', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('company_user_access')->insert([['company_id' => $company, 'user_id' => $maker->id, 'created_at' => now(), 'updated_at' => now()], ['company_id' => $company, 'user_id' => $checker->id, 'created_at' => now(), 'updated_at' => now()]]);
        $profile = (int) DB::table('seller_profiles')->insertGetId(['company_id' => $company, 'user_id' => $maker->id, 'display_name' => 'Procurement Store Owner', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
        $store = (int) DB::table('stores')->insertGetId(['seller_profile_id' => $profile, 'name' => 'Procurement Store', 'slug' => 'procurement-store', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
        $product = (int) DB::table('products')->insertGetId(['store_id' => $store, 'code' => 'PROC-P', 'name' => 'Thiết bị', 'slug' => 'thiet-bi-proc', 'base_price_minor' => 500, 'currency' => 'VND', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
        $variant = (int) DB::table('product_variants')->insertGetId(['product_id' => $product, 'sku' => 'PROC-SKU', 'name' => 'Mặc định', 'price_minor' => 500, 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
        $location = (int) DB::table('inventory_locations')->insertGetId(['store_id' => $store, 'branch_id' => $branch, 'code' => 'HQ', 'name' => 'Kho HQ', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('inventory_balances')->insert(['product_variant_id' => $variant, 'inventory_location_id' => $location, 'on_hand' => 10, 'reserved' => 0, 'inventory_value_minor' => 1000, 'average_unit_cost_minor' => 100, 'version' => 1, 'created_at' => now(), 'updated_at' => now()]);

        return [$maker, $checker, $company, $branch, $variant, $location];
    }
}
