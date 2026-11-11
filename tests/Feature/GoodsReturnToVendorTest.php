<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\DataQuality\DataQualityAuditService;
use Illuminate\Database\RecordNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Modules\Finance\Application\Services\AccountingPostingPolicyService;
use Modules\Finance\Application\Services\AccountsPayablePlanningReadService;
use Modules\Finance\Application\Services\CostCenterCostManagementReadService;
use Modules\Finance\Application\Services\FxRateService;
use Modules\Finance\Application\Services\SourceAccountingPostingService;
use Modules\Finance\Application\Services\TreasuryService;
use Modules\Procurement\Application\Services\GoodsReturnService;
use Modules\Procurement\Application\Services\ProcurementReconciliationReadService;
use Modules\Procurement\Application\Services\ProcurementService;
use Modules\Procurement\Application\Services\SupplierCreditService;
use Modules\Procurement\Application\Services\VendorPerformanceReadService;
use Modules\Procurement\Application\Services\VendorRefundConfigurationReadinessService;
use Modules\Procurement\Application\Services\VendorRefundService;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

final class GoodsReturnToVendorTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function test_unbilled_partial_return_is_append_only_balanced_and_idempotent(): void
    {
        [$maker, $checker, $company, $branch, $variant, $location] = $this->runtime();
        [$order, $orderLine, $receipt, $receiptLine] = $this->receivedGoods($maker, $checker, $company, $branch, $variant, $location);
        $originalReceipt = DB::table('procurement_goods_receipts')->where('id', $receipt)->firstOrFail();
        $originalMovement = DB::table('inventory_movements')->where('id', DB::table('procurement_goods_receipt_lines')->where('id', $receiptLine)->value('inventory_movement_id'))->firstOrFail();
        $originalJournal = DB::table('payroll_accounting_journals')->where(['source_type' => 'goods_receipt', 'source_id' => $receipt])->firstOrFail();
        $service = app(GoodsReturnService::class);

        $return = $service->create($company, $maker, ['goods_receipt_line_id' => $receiptLine, 'quantity' => 2, 'return_number' => 'RTV-001', 'return_date' => '2026-09-20', 'reason' => 'Damaged goods', 'idempotency_key' => 'rtv-001']);
        self::assertSame($return, $service->create($company, $maker, ['goods_receipt_line_id' => $receiptLine, 'quantity' => 2, 'return_number' => 'RTV-001', 'return_date' => '2026-09-20', 'reason' => 'Damaged goods', 'idempotency_key' => 'rtv-001']));
        $service->submit($company, $return, $maker);
        $this->assertValidation(fn () => $service->approve($company, $return, $maker), 'actor', 'GOODS_RETURN_MAKER_CHECKER_REQUIRED');
        $service->approve($company, $return, $checker);
        self::assertSame($return, $service->post($company, $return, $checker));
        self::assertSame($return, $service->post($company, $return, $checker));

        self::assertSame(3, (int) DB::table('purchase_order_lines')->where('id', $orderLine)->value('received_quantity'));
        self::assertSame(13, (int) DB::table('inventory_balances')->where(['product_variant_id' => $variant, 'inventory_location_id' => $location])->value('on_hand'));
        self::assertSame(-2, (int) DB::table('inventory_movements')->where('source_type', 'procurement_goods_return_line')->value('quantity_delta'));
        self::assertSame(1, DB::table('inventory_movements')->where('source_type', 'procurement_goods_return_line')->count());
        $journal = DB::table('payroll_accounting_journals')->where(['source_type' => 'goods_return', 'source_id' => $return])->firstOrFail();
        self::assertSame(400, (int) $journal->total_debit_minor);
        self::assertSame(400, (int) $journal->total_credit_minor);
        self::assertSame('partially_received', DB::table('purchase_orders')->where('id', $order)->value('status'));
        self::assertEquals($originalReceipt, DB::table('procurement_goods_receipts')->where('id', $receipt)->firstOrFail());
        self::assertEquals($originalMovement, DB::table('inventory_movements')->where('id', $originalMovement->id)->firstOrFail());
        self::assertEquals($originalJournal, DB::table('payroll_accounting_journals')->where('id', $originalJournal->id)->firstOrFail());
        $filters = ['date_from' => '2026-09-01', 'date_to' => '2026-09-30', 'branch_id' => null, 'cost_center_id' => null, 'vendor_id' => null, 'classification' => null, 'currency' => 'VND'];
        $report = app(ProcurementReconciliationReadService::class)->read($company, $filters);
        self::assertSame(1_000, $report['goods']['gross_received']);
        self::assertSame(400, $report['goods']['returned']);
        self::assertSame(600, $report['goods']['net_received'], 'net received reporting');
        self::assertSame(600, $report['goods']['grni'], 'GRNI reporting');
        $cost = app(CostCenterCostManagementReadService::class)->read($company, null, '2026-09-01', '2026-09-30')['totals'];
        self::assertSame(600, $cost['actual'], 'budget actual correction');
        self::assertSame(400, $cost['commitment']);
        self::assertSame(1_000, $cost['consumed']);
        $performance = app(VendorPerformanceReadService::class)->read($company, $filters + ['sort' => 'ordered', 'direction' => 'desc'], []);
        self::assertSame(600, $performance['rows'][0]['goods_received'], 'vendor performance net delivery');

        $second = $service->create($company, $maker, ['goods_receipt_line_id' => $receiptLine, 'quantity' => 3, 'return_number' => 'RTV-002', 'return_date' => '2026-09-20', 'reason' => 'Remaining damaged goods', 'idempotency_key' => 'rtv-002']);
        $service->submit($company, $second, $maker);
        $service->approve($company, $second, $checker);
        $service->post($company, $second, $checker);
        self::assertSame(0, (int) DB::table('purchase_order_lines')->where('id', $orderLine)->value('received_quantity'));
        $finalCost = app(CostCenterCostManagementReadService::class)->read($company, null, '2026-09-01', '2026-09-30')['totals'];
        self::assertSame(0, $finalCost['actual']);
        self::assertSame(1_000, $finalCost['commitment']);
        self::assertSame(1_000, $finalCost['consumed']);
        $this->assertValidation(fn () => $service->create($company, $maker, ['goods_receipt_line_id' => $receiptLine, 'quantity' => 1, 'return_number' => 'RTV-OVER', 'return_date' => '2026-09-20', 'reason' => 'Over', 'idempotency_key' => 'rtv-over']), 'quantity', 'GOODS_RETURN_QUANTITY_EXCEEDS_RETURNABLE');
    }

    public function test_cross_tenant_and_unauthorized_access_are_rejected(): void
    {
        [$maker, $checker, $company, $branch, $variant, $location] = $this->runtime();
        [, , , $receiptLine] = $this->receivedGoods($maker, $checker, $company, $branch, $variant, $location);
        $foreign = (int) DB::table('companies')->insertGetId(['uuid' => (string) Str::uuid(), 'code' => 'FOREIGN-RTV', 'name' => 'Foreign RTV', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);

        $this->expectException(RecordNotFoundException::class);
        app(GoodsReturnService::class)->create($foreign, $maker, ['goods_receipt_line_id' => $receiptLine, 'quantity' => 1, 'return_number' => 'RTV-FOREIGN', 'return_date' => '2026-09-20', 'reason' => 'Foreign', 'idempotency_key' => 'rtv-foreign']);
    }

    public function test_foreign_goods_return_requires_canonical_original_fx_valuation_without_creating_economic_effect(): void
    {
        [$maker, $checker, $company, $branch, $variant, $location] = $this->runtime();
        [$order, , , $receiptLine] = $this->receivedGoods($maker, $checker, $company, $branch, $variant, $location);
        DB::table('purchase_orders')->where('id', $order)->update(['currency' => 'USD']);

        $this->assertValidation(
            fn () => app(GoodsReturnService::class)->create($company, $maker, ['goods_receipt_line_id' => $receiptLine, 'quantity' => 1, 'return_number' => 'RTV-FX-BLOCKED', 'return_date' => '2026-09-20', 'reason' => 'Foreign return remains deferred', 'idempotency_key' => 'rtv-fx-blocked']),
            'valuation',
            'FOREIGN_GOODS_RETURN_ORIGINAL_FX_VALUATION_REQUIRED',
        );
        self::assertSame(0, DB::table('procurement_goods_returns')->where(['company_id' => $company, 'return_number' => 'RTV-FX-BLOCKED'])->count());
        self::assertSame(0, DB::table('inventory_movements')->where('source_type', 'procurement_goods_return_line')->count());
        self::assertSame(0, DB::table('payroll_accounting_journals')->where('source_type', 'goods_return')->count());
    }

    public function test_goods_return_workspace_requires_exact_permission(): void
    {
        [$maker, , $company] = $this->runtime();
        $route = route('admin.procurement.goods-returns', ['company_id' => $company]);

        $this->actingAs($maker)->get($route)->assertForbidden();
        $maker->givePermissionTo(Permission::findOrCreate('procurement-goods-return.view', 'web'));
        $this->actingAs($maker)->get($route)->assertOk()->assertSee('FX reversal trace')->assertSee('Goods Return FX Reversal');
    }

    public function test_supplier_credit_workspace_requires_exact_permission_and_is_usable_when_authorized(): void
    {
        [$maker, , $company] = $this->runtime();
        $route = route('admin.procurement.supplier-credits', ['company_id' => $company]);

        $this->actingAs($maker)->get($route)->assertForbidden();
        $maker->givePermissionTo([
            Permission::findOrCreate('supplier-credit.view', 'web'),
            Permission::findOrCreate('supplier-credit.create', 'web'),
        ]);

        $this->actingAs($maker)->get($route)->assertOk()
            ->assertSee('AP / Receivable / PPV trace')
            ->assertSee('spot / realized FX trace')
            ->assertSee('Supplier Credit')
            ->assertSee('Vendor Refund')
            ->assertSee('Tạo Supplier Credit');
    }

    public function test_billing_and_valuation_safety_gates_block_posting(): void
    {
        [$maker, $checker, $company, $branch, $variant, $location] = $this->runtime();
        [$order, $orderLine, , $receiptLine] = $this->receivedGoods($maker, $checker, $company, $branch, $variant, $location);
        $service = app(GoodsReturnService::class);
        $return = $service->create($company, $maker, ['goods_receipt_line_id' => $receiptLine, 'quantity' => 1, 'return_number' => 'RTV-BILL', 'return_date' => '2026-09-20', 'reason' => 'Billed', 'idempotency_key' => 'rtv-bill']);
        $service->submit($company, $return, $maker);
        $service->approve($company, $return, $checker);
        $bill = app(ProcurementService::class)->mutate('bill.create', $company, $maker, ['purchase_order_id' => $order, 'purchase_order_line_id' => $orderLine, 'quantity' => 1, 'invoice_number' => 'INV-RTV', 'invoice_date' => '2026-09-20', 'due_date' => '2026-10-20']);
        self::assertGreaterThan(0, $bill);
        $this->assertValidation(fn () => $service->post($company, $return, $checker), 'billing', 'GOODS_RETURN_TO_VENDOR_BLOCKED_BY_BILLING_EVIDENCE');
        DB::table('vendor_bills')->where('id', $bill)->update(['status' => 'cancelled']);
        DB::table('inventory_movements')->where('id', DB::table('procurement_goods_receipt_lines')->where('id', $receiptLine)->value('inventory_movement_id'))->update(['unit_cost_minor' => 201]);
        $this->assertValidation(fn () => $service->post($company, $return, $checker), 'valuation', 'GOODS_RETURN_TO_VENDOR_BLOCKED_BY_INVENTORY_VALUATION');
    }

    public function test_allocated_billed_return_posts_supplier_credit_and_reduces_ap_append_only(): void
    {
        [$maker, $checker, $company, $branch, $variant, $location] = $this->runtime();
        [$order, $orderLine, $receipt, $receiptLine] = $this->receivedGoods($maker, $checker, $company, $branch, $variant, $location);
        $goodsReturns = app(GoodsReturnService::class);
        $return = $goodsReturns->create($company, $maker, ['goods_receipt_line_id' => $receiptLine, 'quantity' => 2, 'return_number' => 'RTV-CREDIT', 'return_date' => '2026-09-20', 'reason' => 'Billed damaged goods', 'idempotency_key' => 'rtv-credit']);
        $goodsReturns->submit($company, $return, $maker);
        $goodsReturns->approve($company, $return, $checker);

        $procurement = app(ProcurementService::class);
        $bill = $procurement->mutate('bill.create', $company, $maker, ['purchase_order_id' => $order, 'purchase_order_line_id' => $orderLine, 'goods_receipt_line_id' => $receiptLine, 'quantity' => 2, 'invoice_number' => 'INV-CREDIT', 'invoice_date' => '2026-09-15', 'due_date' => '2026-10-15']);
        $procurement->mutate('bill.approve', $company, $checker, ['id' => $bill]);
        $allocation = (int) DB::table('vendor_bill_receipt_allocations')->value('id');
        self::assertGreaterThan(0, $allocation);
        $returnLine = (int) DB::table('procurement_goods_return_lines')->where('goods_return_id', $return)->value('id');
        $originalBill = DB::table('vendor_bills')->where('id', $bill)->firstOrFail();
        $originalBillJournal = DB::table('payroll_accounting_journals')->where(['source_type' => 'vendor_bill', 'source_id' => $bill])->firstOrFail();
        $originalReceipt = DB::table('procurement_goods_receipts')->where('id', $receipt)->firstOrFail();
        $originalReceiptJournal = DB::table('payroll_accounting_journals')->where(['source_type' => 'goods_receipt', 'source_id' => $receipt])->firstOrFail();

        $credits = app(SupplierCreditService::class);
        $payload = ['goods_return_line_id' => $returnLine, 'vendor_bill_receipt_allocation_id' => $allocation, 'quantity' => 2, 'credit_number' => 'SCN-001', 'credit_date' => '2026-09-20', 'reason' => 'Credit for returned damaged goods', 'idempotency_key' => 'scn-001'];
        $credit = $credits->create($company, $maker, $payload);
        self::assertSame($credit, $credits->create($company, $maker, $payload));
        $this->assertValidation(fn () => $credits->create($company, $maker, [...$payload, 'credit_number' => 'SCN-CONFLICT']), 'idempotency_key', 'SUPPLIER_CREDIT_IDEMPOTENCY_CONFLICT');
        $credits->submit($company, $credit, $maker);
        $this->assertValidation(fn () => $credits->approve($company, $credit, $maker), 'actor', 'SUPPLIER_CREDIT_MAKER_CHECKER_REQUIRED');
        $credits->approve($company, $credit, $checker);
        self::assertSame($credit, $credits->post($company, $credit, $checker));
        self::assertSame($credit, $credits->post($company, $credit, $checker));

        self::assertSame('posted', DB::table('procurement_goods_returns')->where('id', $return)->value('status'));
        self::assertSame('posted', DB::table('supplier_credit_notes')->where('id', $credit)->value('status'));
        self::assertSame(1, DB::table('payroll_accounting_journals')->where(['source_type' => 'supplier_credit', 'source_id' => $credit])->count());
        $journal = DB::table('payroll_accounting_journals')->where(['source_type' => 'supplier_credit', 'source_id' => $credit])->firstOrFail();
        self::assertSame(400, (int) $journal->total_debit_minor);
        self::assertSame(400, (int) $journal->total_credit_minor);
        self::assertEquals($originalBill, DB::table('vendor_bills')->where('id', $bill)->firstOrFail());
        self::assertEquals($originalBillJournal, DB::table('payroll_accounting_journals')->where('id', $originalBillJournal->id)->firstOrFail());
        self::assertEquals($originalReceipt, DB::table('procurement_goods_receipts')->where('id', $receipt)->firstOrFail());
        self::assertEquals($originalReceiptJournal, DB::table('payroll_accounting_journals')->where('id', $originalReceiptJournal->id)->firstOrFail());

        $before = app(AccountsPayablePlanningReadService::class)->read($company, $this->apFilters('2026-09-19'));
        $after = app(AccountsPayablePlanningReadService::class)->read($company, $this->apFilters('2026-09-20'));
        self::assertSame(400, $before['totals']['outstanding']);
        self::assertSame(0, $after['totals']['outstanding']);
        self::assertSame(400, $after['totals']['credits']);
    }

    public function test_fully_paid_supplier_credit_creates_receivable_and_supports_partial_then_full_refund(): void
    {
        [$maker, $checker, $company, $branch, $variant, $location] = $this->runtime();
        [$order, $orderLine, , $receiptLine] = $this->receivedGoods($maker, $checker, $company, $branch, $variant, $location);
        $goodsReturns = app(GoodsReturnService::class);
        $return = $goodsReturns->create($company, $maker, ['goods_receipt_line_id' => $receiptLine, 'quantity' => 2, 'return_number' => 'RTV-PAID', 'return_date' => '2026-09-20', 'reason' => 'Paid billed goods', 'idempotency_key' => 'rtv-paid']);
        $goodsReturns->submit($company, $return, $maker);
        $goodsReturns->approve($company, $return, $checker);
        $procurement = app(ProcurementService::class);
        $bill = $procurement->mutate('bill.create', $company, $maker, ['purchase_order_id' => $order, 'purchase_order_line_id' => $orderLine, 'goods_receipt_line_id' => $receiptLine, 'quantity' => 2, 'invoice_number' => 'INV-PAID', 'invoice_date' => '2026-09-15', 'due_date' => '2026-10-15']);
        $procurement->mutate('bill.approve', $company, $checker, ['id' => $bill]);
        $procurement->mutate('payment.allocate', $company, $maker, ['vendor_bill_id' => $bill, 'payment_date' => '2026-09-16', 'amount_minor' => 400, 'method' => 'bank_clearing', 'reference' => 'PAY-PAID']);
        $returnLine = (int) DB::table('procurement_goods_return_lines')->where('goods_return_id', $return)->value('id');
        $allocation = (int) DB::table('vendor_bill_receipt_allocations')->value('id');
        $credits = app(SupplierCreditService::class);
        $credit = $credits->create($company, $maker, ['goods_return_line_id' => $returnLine, 'vendor_bill_receipt_allocation_id' => $allocation, 'quantity' => 2, 'credit_number' => 'SCN-PAID', 'credit_date' => '2026-09-20', 'reason' => 'Paid return creates receivable', 'idempotency_key' => 'scn-paid']);
        $credits->submit($company, $credit, $maker);
        $credits->approve($company, $credit, $checker);
        $this->assertValidation(fn () => $credits->post($company, $credit, $checker), 'accounting_policy', 'SUPPLIER_CREDIT_PAID_PORTION_POLICY_MISSING');
        self::assertSame('approved', DB::table('supplier_credit_notes')->where('id', $credit)->value('status'));
        self::assertSame('approved', DB::table('procurement_goods_returns')->where('id', $return)->value('status'));
        self::assertSame(0, DB::table('vendor_refund_receivables')->where('supplier_credit_note_id', $credit)->count());
        self::assertSame(0, DB::table('payroll_accounting_journals')->where(['source_type' => 'supplier_credit', 'source_id' => $credit])->count());
        $this->configureVendorRefundPolicies($company, $maker, $checker);
        $credits->post($company, $credit, $checker);
        $creditRow = DB::table('supplier_credit_notes')->where('id', $credit)->firstOrFail();
        self::assertSame(0, (int) $creditRow->ap_reduction_minor);
        self::assertSame(400, (int) $creditRow->vendor_receivable_minor);
        $due = DB::table('vendor_refund_receivables')->where('supplier_credit_note_id', $credit)->firstOrFail();
        self::assertSame(400, (int) $due->original_minor);
        self::assertSame(0, app(AccountsPayablePlanningReadService::class)->read($company, $this->apFilters('2026-09-20'))['totals']['outstanding']);

        $financialAccount = (int) DB::table('treasury_financial_accounts')->where('company_id', $company)->value('id');
        $refunds = app(VendorRefundService::class);
        $foreignCompany = (int) DB::table('companies')->insertGetId(['uuid' => (string) Str::uuid(), 'code' => 'FOREIGN-REFUND', 'name' => 'Foreign Refund', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
        $foreignLedger = app(SourceAccountingPostingService::class)->accountEvidence($foreignCompany, '1100', 'VND', (int) $maker->id)['id'];
        $foreignAccount = app(TreasuryService::class)->mutate('account.create', $foreignCompany, $maker, ['accounting_account_id' => $foreignLedger, 'code' => 'FOREIGN-BANK', 'name' => 'Foreign Bank', 'type' => 'bank']);
        $this->assertValidation(fn () => $refunds->create($company, $maker, ['vendor_refund_receivable_id' => $due->id, 'financial_account_id' => $foreignAccount, 'reference' => 'REFUND-FOREIGN', 'refund_date' => '2026-09-21', 'amount_minor' => 1, 'idempotency_key' => 'refund-foreign']), 'financial_account_id', 'SUPPLIER_REFUND_TREASURY_ACCOUNT_INVALID');
        $cashLedger = (int) DB::table('treasury_financial_accounts')->where('id', $financialAccount)->value('accounting_account_id');
        DB::table('accounting_accounts')->where('id', $cashLedger)->update(['currency' => null]);
        $usdAccount = (int) DB::table('treasury_financial_accounts')->insertGetId(['company_id' => $company, 'branch_id' => $branch, 'accounting_account_id' => $cashLedger, 'code' => 'USD-REFUND', 'name' => 'USD Refund', 'type' => 'bank', 'currency' => 'USD', 'status' => 'active', 'created_by' => $maker->id, 'created_at' => now(), 'updated_at' => now()]);
        $this->assertValidation(fn () => $refunds->create($company, $maker, ['vendor_refund_receivable_id' => $due->id, 'financial_account_id' => $usdAccount, 'reference' => 'REFUND-USD', 'refund_date' => '2026-09-21', 'amount_minor' => 1, 'idempotency_key' => 'refund-usd']), 'financial_account_id', 'SUPPLIER_REFUND_CURRENCY_MISMATCH');
        DB::table('accounting_accounts')->where('id', $cashLedger)->update(['currency' => 'VND']);
        $first = $refunds->create($company, $maker, ['vendor_refund_receivable_id' => $due->id, 'financial_account_id' => $financialAccount, 'reference' => 'REFUND-1', 'refund_date' => '2026-09-21', 'amount_minor' => 150, 'idempotency_key' => 'refund-1']);
        self::assertSame($first, $refunds->create($company, $maker, ['vendor_refund_receivable_id' => $due->id, 'financial_account_id' => $financialAccount, 'reference' => 'REFUND-1', 'refund_date' => '2026-09-21', 'amount_minor' => 150, 'idempotency_key' => 'refund-1']));
        $this->assertValidation(fn () => $refunds->create($company, $maker, ['vendor_refund_receivable_id' => $due->id, 'financial_account_id' => $financialAccount, 'reference' => 'REFUND-CONFLICT', 'refund_date' => '2026-09-21', 'amount_minor' => 150, 'idempotency_key' => 'refund-1']), 'idempotency_key', 'SUPPLIER_REFUND_IDEMPOTENCY_CONFLICT');
        $refunds->submit($company, $first, $maker);
        $this->assertValidation(fn () => $refunds->approve($company, $first, $maker), 'actor', 'SUPPLIER_REFUND_MAKER_CHECKER_REQUIRED');
        $refunds->approve($company, $first, $checker);
        DB::table('accounting_posting_policies')->where(['company_id' => $company, 'business_event' => 'SUPPLIER_REFUND_RECEIVED'])->update(['status' => 'draft']);
        $this->assertValidation(fn () => $refunds->post($company, $first, $checker), 'accounting_policy', 'SUPPLIER_REFUND_POSTING_POLICY_MISSING');
        self::assertSame('approved', DB::table('supplier_refund_receipts')->where('id', $first)->value('status'));
        self::assertSame(0, DB::table('treasury_transactions')->where(['source_type' => 'supplier_refund_receipt', 'source_id' => $first])->count());
        self::assertSame(0, DB::table('payroll_accounting_journals')->where(['source_type' => 'supplier_refund_receipt', 'source_id' => $first])->count());
        self::assertSame(0, (int) DB::table('vendor_refund_receivables')->where('id', $due->id)->value('refunded_minor'));
        DB::table('accounting_posting_policies')->where(['company_id' => $company, 'business_event' => 'SUPPLIER_REFUND_RECEIVED'])->update(['status' => 'approved']);
        $refunds->post($company, $first, $checker);
        self::assertSame('partially_refunded', DB::table('vendor_refund_receivables')->where('id', $due->id)->value('status'));
        $this->assertValidation(fn () => $refunds->create($company, $maker, ['vendor_refund_receivable_id' => $due->id, 'financial_account_id' => $financialAccount, 'reference' => 'REFUND-OVER', 'refund_date' => '2026-09-22', 'amount_minor' => 251, 'idempotency_key' => 'refund-over']), 'amount_minor', 'SUPPLIER_REFUND_EXCEEDS_REMAINING_RECEIVABLE');
        $second = $refunds->create($company, $maker, ['vendor_refund_receivable_id' => $due->id, 'financial_account_id' => $financialAccount, 'reference' => 'REFUND-2', 'refund_date' => '2026-09-22', 'amount_minor' => 250, 'idempotency_key' => 'refund-2']);
        $refunds->submit($company, $second, $maker);
        $refunds->approve($company, $second, $checker);
        $refunds->post($company, $second, $checker);
        $settled = DB::table('vendor_refund_receivables')->where('id', $due->id)->firstOrFail();
        self::assertSame(400, (int) $settled->refunded_minor);
        self::assertSame('refunded', $settled->status);
        self::assertSame(2, DB::table('treasury_transactions')->where(['company_id' => $company, 'source_type' => 'supplier_refund_receipt', 'direction' => 'inflow'])->count());
        self::assertSame(2, DB::table('payroll_accounting_journals')->where('source_type', 'supplier_refund_receipt')->count());
    }

    public function test_partially_paid_supplier_credit_splits_ap_and_vendor_receivable_without_negative_ap(): void
    {
        [$maker, $checker, $company, $branch, $variant, $location] = $this->runtime();
        [$order, $orderLine, , $receiptLine] = $this->receivedGoods($maker, $checker, $company, $branch, $variant, $location);
        $returns = app(GoodsReturnService::class);
        $return = $returns->create($company, $maker, ['goods_receipt_line_id' => $receiptLine, 'quantity' => 2, 'return_number' => 'RTV-PART-PAID', 'return_date' => '2026-09-20', 'reason' => 'Part paid return', 'idempotency_key' => 'rtv-part-paid']);
        $returns->submit($company, $return, $maker);
        $returns->approve($company, $return, $checker);
        $procurement = app(ProcurementService::class);
        $bill = $procurement->mutate('bill.create', $company, $maker, ['purchase_order_id' => $order, 'purchase_order_line_id' => $orderLine, 'goods_receipt_line_id' => $receiptLine, 'quantity' => 2, 'invoice_number' => 'INV-PART-PAID', 'invoice_date' => '2026-09-15', 'due_date' => '2026-10-15']);
        $procurement->mutate('bill.approve', $company, $checker, ['id' => $bill]);
        $procurement->mutate('payment.allocate', $company, $maker, ['vendor_bill_id' => $bill, 'payment_date' => '2026-09-16', 'amount_minor' => 100, 'method' => 'bank_clearing', 'reference' => 'PAY-PART']);
        $this->configureVendorRefundPolicies($company, $maker, $checker);
        $credits = app(SupplierCreditService::class);
        $credit = $credits->create($company, $maker, ['goods_return_line_id' => DB::table('procurement_goods_return_lines')->where('goods_return_id', $return)->value('id'), 'vendor_bill_receipt_allocation_id' => DB::table('vendor_bill_receipt_allocations')->value('id'), 'quantity' => 2, 'credit_number' => 'SCN-PART', 'credit_date' => '2026-09-20', 'reason' => 'Split paid and outstanding', 'idempotency_key' => 'scn-part']);
        $credits->submit($company, $credit, $maker);
        $credits->approve($company, $credit, $checker);
        $credits->post($company, $credit, $checker);
        $posted = DB::table('supplier_credit_notes')->where('id', $credit)->firstOrFail();
        self::assertSame(300, (int) $posted->ap_reduction_minor);
        self::assertSame(100, (int) $posted->vendor_receivable_minor);
        self::assertSame(0, app(AccountsPayablePlanningReadService::class)->read($company, $this->apFilters('2026-09-20'))['totals']['outstanding']);
        $journalId = (int) $posted->accounting_journal_id;
        self::assertSame(300, (int) DB::table('payroll_accounting_journal_lines')->where(['journal_id' => $journalId, 'account_code_snapshot' => '2100'])->value('debit_minor'));
        self::assertSame(100, (int) DB::table('payroll_accounting_journal_lines')->where(['journal_id' => $journalId, 'account_code_snapshot' => '1150'])->value('debit_minor'));
    }

    public function test_foreign_unpaid_supplier_credit_releases_canonical_ap_carrying_basis_without_ppv(): void
    {
        [$maker, $checker, $company, $branch, $variant, $location] = $this->runtime();
        $this->configureSpotRate($company, $maker, $checker, 'USD', '25000.000000000000');
        [$order, $orderLine, , $receiptLine] = $this->receivedGoods($maker, $checker, $company, $branch, $variant, $location, 'USD');
        $procurement = app(ProcurementService::class);
        $bill = $procurement->mutate('bill.create', $company, $maker, [
            'purchase_order_id' => $order,
            'purchase_order_line_id' => $orderLine,
            'goods_receipt_line_id' => $receiptLine,
            'quantity' => 2,
            'invoice_number' => 'INV-FX-CREDIT',
            'invoice_date' => '2026-09-15',
            'due_date' => '2026-10-15',
        ]);
        $procurement->mutate('bill.approve', $company, $checker, ['id' => $bill]);
        $recognition = DB::table('supplier_invoice_fx_recognitions')->where('vendor_bill_id', $bill)->firstOrFail();
        self::assertSame(400, (int) $recognition->transaction_amount_minor);
        self::assertSame(100_000, (int) $recognition->functional_amount_minor);

        $returns = app(GoodsReturnService::class);
        $return = $returns->create($company, $maker, [
            'goods_receipt_line_id' => $receiptLine,
            'quantity' => 2,
            'return_number' => 'RTV-FX-CREDIT',
            'return_date' => '2026-09-20',
            'reason' => 'Foreign damaged goods',
            'idempotency_key' => 'rtv-fx-credit',
        ]);
        $returns->submit($company, $return, $maker);
        $returns->approve($company, $return, $checker);
        $creditPayload = [
            'goods_return_line_id' => DB::table('procurement_goods_return_lines')->where('goods_return_id', $return)->value('id'),
            'vendor_bill_receipt_allocation_id' => DB::table('vendor_bill_receipt_allocations')->whereIn('vendor_bill_line_id', DB::table('vendor_bill_lines')->where('vendor_bill_id', $bill)->select('id'))->value('id'),
            'quantity' => 2,
            'credit_number' => 'SCN-FX-001',
            'credit_date' => '2026-09-20',
            'reason' => 'Foreign supplier credit',
            'idempotency_key' => 'scn-fx-001',
        ];
        $credits = app(SupplierCreditService::class);
        $credit = $credits->create($company, $maker, $creditPayload);
        self::assertSame($credit, $credits->create($company, $maker, $creditPayload));
        $this->assertValidation(fn () => $credits->create($company, $maker, [...$creditPayload, 'credit_number' => 'SCN-FX-CONFLICT']), 'idempotency_key', 'SUPPLIER_CREDIT_IDEMPOTENCY_CONFLICT');
        $credits->submit($company, $credit, $maker);
        $credits->approve($company, $credit, $checker);
        $credits->post($company, $credit, $checker);
        $credits->post($company, $credit, $checker);

        $posted = DB::table('supplier_credit_notes')->where('id', $credit)->firstOrFail();
        $evidence = DB::table('supplier_credit_fx_evidence')->where('supplier_credit_note_id', $credit)->firstOrFail();
        self::assertSame(400, (int) $posted->ap_reduction_minor);
        self::assertSame(0, (int) $posted->vendor_receivable_minor);
        self::assertSame(100_000, (int) $evidence->ap_carrying_release_minor);
        self::assertSame(100_000, (int) DB::table('inventory_return_fx_reversals')->where('goods_return_id', $return)->value('released_functional_basis_minor'));
        self::assertSame(0, (int) $evidence->purchase_price_variance_minor);
        self::assertSame(1, DB::table('supplier_credit_fx_evidence')->where('supplier_credit_note_id', $credit)->count());
        self::assertSame(1, DB::table('inventory_return_fx_reversals')->where('goods_return_id', $return)->count());
        self::assertSame(1, DB::table('payroll_accounting_journals')->where(['source_type' => 'supplier_credit', 'source_id' => $credit])->count());
        self::assertSame(100_000, (int) DB::table('payroll_accounting_journals')->where(['source_type' => 'supplier_credit', 'source_id' => $credit])->value('total_debit_minor'));
        self::assertSame(100_000, (int) DB::table('payroll_accounting_journals')->where(['source_type' => 'supplier_credit', 'source_id' => $credit])->value('total_credit_minor'));
        self::assertSame(0, DB::table('vendor_refund_receivables')->where('supplier_credit_note_id', $credit)->count());
    }

    #[DataProvider('supplierCreditCurrencyCases')]
    public function test_supplier_credit_preserves_currency_exponents_and_exact_functional_basis(string $currency, ?string $rate, int $expectedFunctional, int $expectedReceiptFunctional): void
    {
        [$maker, $checker, $company, $branch, $variant, $location] = $this->runtime();
        if ($rate !== null) {
            $this->configureSpotRate($company, $maker, $checker, $currency, $rate);
        }
        [$order, $orderLine, , $receiptLine] = $this->receivedGoods($maker, $checker, $company, $branch, $variant, $location, $currency);
        $procurement = app(ProcurementService::class);
        $bill = $procurement->mutate('bill.create', $company, $maker, [
            'purchase_order_id' => $order, 'purchase_order_line_id' => $orderLine, 'goods_receipt_line_id' => $receiptLine,
            'quantity' => 2, 'invoice_number' => 'INV-'.$currency, 'invoice_date' => '2026-09-15', 'due_date' => '2026-10-15',
        ]);
        $procurement->mutate('bill.approve', $company, $checker, ['id' => $bill]);
        $recognition = DB::table('supplier_invoice_fx_recognitions')->where('vendor_bill_id', $bill)->firstOrFail();
        self::assertSame(400, (int) $recognition->transaction_amount_minor);
        self::assertSame($expectedFunctional, (int) $recognition->functional_amount_minor);
        self::assertSame($expectedReceiptFunctional, (int) DB::table('inventory_receipt_fx_valuations')->where('goods_receipt_line_id', $receiptLine)->value('functional_amount_minor'));

        [$return, $credit] = $this->approvedReturnAndCredit($maker, $checker, $company, $receiptLine, $bill, $currency);
        app(SupplierCreditService::class)->post($company, $credit, $checker);
        $journal = DB::table('payroll_accounting_journals')->where(['source_type' => 'supplier_credit', 'source_id' => $credit])->firstOrFail();
        self::assertSame($expectedFunctional, (int) $journal->total_debit_minor);
        self::assertSame($expectedFunctional, (int) $journal->total_credit_minor);
        if ($currency === 'VND') {
            self::assertSame(0, DB::table('supplier_credit_fx_evidence')->where('supplier_credit_note_id', $credit)->count());
            $returnLine = (int) DB::table('procurement_goods_return_lines')->where('goods_return_id', $return)->value('id');
            self::assertSame(-$expectedFunctional, (int) DB::table('inventory_movements')->where(['source_type' => 'procurement_goods_return_line', 'source_id' => $returnLine])->value('movement_value_minor'));
        } else {
            $evidence = DB::table('supplier_credit_fx_evidence')->where('supplier_credit_note_id', $credit)->firstOrFail();
            self::assertSame($expectedFunctional, (int) $evidence->ap_carrying_release_minor);
            self::assertSame(0, (int) $evidence->purchase_price_variance_minor);
            self::assertSame($expectedFunctional, (int) DB::table('inventory_return_fx_reversals')->where('goods_return_id', $return)->value('released_functional_basis_minor'));
        }
    }

    /** @return iterable<string,array{string,?string,int,int}> */
    public static function supplierCreditCurrencyCases(): iterable
    {
        yield 'EUR has two decimal minor units' => ['EUR', '27000.000000000000', 108_000, 270_000];
        yield 'JPY has zero decimal minor units' => ['JPY', '160.000000000000', 64_000, 160_000];
        yield 'VND identity regression' => ['VND', null, 400, 1_000];
    }

    #[DataProvider('supplierCreditPpvCases')]
    public function test_foreign_supplier_credit_posts_policy_backed_ap_ppv(string $invoiceRate, string $event, string $ppvRole, int $expectedCarrying, int $expectedPpv, string $ppvSide): void
    {
        [$maker, $checker, $company, $branch, $variant, $location] = $this->runtime();
        $this->configureDatedSpotRate($company, $maker, $checker, 'USD', '25000.000000000000', '2026-09-01', '2026-09-10', 'receipt');
        $this->configureDatedSpotRate($company, $maker, $checker, 'USD', $invoiceRate, '2026-09-11', '2026-09-30', 'invoice');
        $this->configurePpvPolicy($company, $maker, $checker, $event, $ppvRole, $ppvSide);
        [$order, $orderLine, , $receiptLine] = $this->receivedGoods($maker, $checker, $company, $branch, $variant, $location, 'USD');
        $procurement = app(ProcurementService::class);
        $bill = $procurement->mutate('bill.create', $company, $maker, [
            'purchase_order_id' => $order, 'purchase_order_line_id' => $orderLine, 'goods_receipt_line_id' => $receiptLine,
            'quantity' => 2, 'invoice_number' => 'INV-PPV', 'invoice_date' => '2026-09-15', 'due_date' => '2026-10-15',
        ]);
        $procurement->mutate('bill.approve', $company, $checker, ['id' => $bill]);
        [$return, $credit] = $this->approvedReturnAndCredit($maker, $checker, $company, $receiptLine, $bill, 'PPV');
        app(SupplierCreditService::class)->post($company, $credit, $checker);

        $evidence = DB::table('supplier_credit_fx_evidence')->where('supplier_credit_note_id', $credit)->firstOrFail();
        self::assertSame($expectedCarrying, (int) $evidence->ap_carrying_release_minor);
        self::assertSame($expectedPpv, (int) $evidence->purchase_price_variance_minor);
        self::assertSame(100_000, (int) $evidence->ap_carrying_release_minor + (int) $evidence->purchase_price_variance_minor);
        $journalId = (int) $evidence->accounting_journal_id;
        self::assertSame(100_000 + max(0, -$expectedPpv), (int) DB::table('payroll_accounting_journals')->where('id', $journalId)->value('total_debit_minor'));
        self::assertSame(100_000 + max(0, -$expectedPpv), (int) DB::table('payroll_accounting_journals')->where('id', $journalId)->value('total_credit_minor'));
        $ppvLine = DB::table('payroll_accounting_journal_lines')->where('journal_id', $journalId)->where('description', 'like', 'Purchase price variance%')->firstOrFail();
        self::assertSame(abs($expectedPpv), (int) data_get($ppvLine, $ppvSide.'_minor'));
        self::assertSame(0, (int) data_get($ppvLine, ($ppvSide === 'debit' ? 'credit' : 'debit').'_minor'));
        self::assertSame($event, DB::table('accounting_posting_policies')->where('id', $evidence->accounting_policy_id)->value('business_event'));
        self::assertSame(100_000, (int) DB::table('inventory_return_fx_reversals')->where('goods_return_id', $return)->value('released_functional_basis_minor'));
    }

    /** @return iterable<string,array{string,string,string,int,int,string}> */
    public static function supplierCreditPpvCases(): iterable
    {
        yield 'AP PPV loss' => ['24000.000000000000', 'FOREIGN_SUPPLIER_CREDIT_AP_PPV_LOSS', 'PURCHASE_PRICE_VARIANCE_LOSS', 96_000, 4_000, 'debit'];
        yield 'AP PPV gain' => ['26000.000000000000', 'FOREIGN_SUPPLIER_CREDIT_AP_PPV_GAIN', 'PURCHASE_PRICE_VARIANCE_GAIN', 104_000, -4_000, 'credit'];
    }

    public function test_missing_foreign_supplier_credit_ppv_policy_rolls_back_every_economic_effect(): void
    {
        [$maker, $checker, $company, $branch, $variant, $location] = $this->runtime();
        $this->configureDatedSpotRate($company, $maker, $checker, 'USD', '25000.000000000000', '2026-09-01', '2026-09-10', 'receipt');
        $this->configureDatedSpotRate($company, $maker, $checker, 'USD', '24000.000000000000', '2026-09-11', '2026-09-30', 'invoice');
        $event = 'FOREIGN_SUPPLIER_CREDIT_AP_PPV_LOSS';
        $this->configurePpvPolicy($company, $maker, $checker, $event, 'PURCHASE_PRICE_VARIANCE_LOSS', 'debit');
        [$order, $orderLine, , $receiptLine] = $this->receivedGoods($maker, $checker, $company, $branch, $variant, $location, 'USD');
        $procurement = app(ProcurementService::class);
        $bill = $procurement->mutate('bill.create', $company, $maker, [
            'purchase_order_id' => $order, 'purchase_order_line_id' => $orderLine, 'goods_receipt_line_id' => $receiptLine,
            'quantity' => 2, 'invoice_number' => 'INV-PPV-MISSING', 'invoice_date' => '2026-09-15', 'due_date' => '2026-10-15',
        ]);
        $procurement->mutate('bill.approve', $company, $checker, ['id' => $bill]);
        [$return, $credit] = $this->approvedReturnAndCredit($maker, $checker, $company, $receiptLine, $bill, 'PPV-MISSING');
        $policyId = (int) DB::table('accounting_posting_policies')->where(['company_id' => $company, 'business_event' => $event])->value('id');
        DB::table('accounting_posting_policy_lines')->where('accounting_posting_policy_id', $policyId)->delete();
        DB::table('accounting_posting_policies')->where('id', $policyId)->delete();
        $balanceBefore = DB::table('inventory_balances')->where(['product_variant_id' => $variant, 'inventory_location_id' => $location])->firstOrFail();

        $this->assertValidation(fn () => app(SupplierCreditService::class)->post($company, $credit, $checker), 'accounting_policy', 'FOREIGN_SUPPLIER_CREDIT_PPV_POLICY_REQUIRED');
        self::assertSame('approved', DB::table('supplier_credit_notes')->where('id', $credit)->value('status'));
        self::assertSame('approved', DB::table('procurement_goods_returns')->where('id', $return)->value('status'));
        self::assertSame((int) $balanceBefore->on_hand, (int) DB::table('inventory_balances')->where(['product_variant_id' => $variant, 'inventory_location_id' => $location])->value('on_hand'));
        self::assertSame((int) $balanceBefore->inventory_value_minor, (int) DB::table('inventory_balances')->where(['product_variant_id' => $variant, 'inventory_location_id' => $location])->value('inventory_value_minor'));
        self::assertSame(0, DB::table('supplier_credit_fx_evidence')->where('supplier_credit_note_id', $credit)->count());
        self::assertSame(0, DB::table('inventory_return_fx_reversals')->where('goods_return_id', $return)->count());
        self::assertSame(0, DB::table('inventory_movements')->where('source_type', 'procurement_goods_return_line')->whereIn('source_id', DB::table('procurement_goods_return_lines')->where('goods_return_id', $return)->select('id'))->count());
        self::assertSame(0, DB::table('payroll_accounting_journals')->where(['source_type' => 'supplier_credit', 'source_id' => $credit])->count());
    }

    #[DataProvider('supplierCreditPaidPpvCases')]
    public function test_paid_and_partially_paid_foreign_supplier_credit_split_ppv_without_rewriting_payment_evidence(
        string $exposure,
        int $paymentMinor,
        string $invoiceRate,
        string $event,
        string $ppvRole,
        int $expectedApForeign,
        int $expectedApFunctional,
        int $expectedReceivableForeign,
        int $expectedReceivableFunctional,
        int $expectedPpv,
        string $ppvSide,
    ): void {
        [$maker, $checker, $company, $branch, $variant, $location] = $this->runtime();
        $this->configureDatedSpotRate($company, $maker, $checker, 'USD', '25000.000000000000', '2026-09-01', '2026-09-10', 'receipt');
        $this->configureDatedSpotRate($company, $maker, $checker, 'USD', $invoiceRate, '2026-09-11', '2026-09-20', 'invoice');
        $this->configureDatedSpotRate($company, $maker, $checker, 'USD', '25000.000000000000', '2026-09-21', '2026-09-30', 'refund');
        $this->configurePpvPolicy($company, $maker, $checker, $event, $ppvRole, $ppvSide, $exposure);
        $this->configureVendorRefundFxPolicies($company, $maker, $checker);
        [$order, $orderLine, , $receiptLine] = $this->receivedGoods($maker, $checker, $company, $branch, $variant, $location, 'USD');
        $procurement = app(ProcurementService::class);
        $bill = $procurement->mutate('bill.create', $company, $maker, [
            'purchase_order_id' => $order, 'purchase_order_line_id' => $orderLine, 'goods_receipt_line_id' => $receiptLine,
            'quantity' => 2, 'invoice_number' => 'INV-'.$exposure.'-'.$ppvSide, 'invoice_date' => '2026-09-15', 'due_date' => '2026-10-15',
        ]);
        $procurement->mutate('bill.approve', $company, $checker, ['id' => $bill]);
        $usdAccount = $this->foreignTreasuryAccount($company, $branch, $maker, 'USD-'.$exposure.'-'.$ppvSide);
        $payment = $procurement->mutate('payment.allocate', $company, $checker, [
            'vendor_bill_id' => $bill, 'financial_account_id' => $usdAccount, 'payment_date' => '2026-09-16',
            'amount_minor' => $paymentMinor, 'method' => 'bank_transfer', 'reference' => 'PAY-'.$exposure.'-'.$ppvSide,
        ]);
        $paymentEvidenceBefore = DB::table('supplier_payment_fx_settlements')->whereIn('ap_payment_allocation_id', DB::table('ap_payment_allocations')->where('payment_id', $payment)->select('id'))->firstOrFail();
        [$return, $credit] = $this->approvedReturnAndCredit($maker, $checker, $company, $receiptLine, $bill, $exposure.'-'.$ppvSide);
        app(SupplierCreditService::class)->post($company, $credit, $checker);

        $evidence = DB::table('supplier_credit_fx_evidence')->where('supplier_credit_note_id', $credit)->firstOrFail();
        $posted = DB::table('supplier_credit_notes')->where('id', $credit)->firstOrFail();
        self::assertSame($expectedApForeign, (int) $posted->ap_reduction_minor);
        self::assertSame($expectedReceivableForeign, (int) $posted->vendor_receivable_minor);
        self::assertSame($expectedApFunctional, (int) $evidence->ap_carrying_release_minor);
        self::assertSame($expectedReceivableFunctional, (int) $evidence->supplier_receivable_functional_minor);
        self::assertSame($expectedPpv, (int) $evidence->purchase_price_variance_minor);
        self::assertSame(100_000, $expectedApFunctional + $expectedReceivableFunctional + $expectedPpv);
        self::assertSame($expectedReceivableForeign > 0 ? 1 : 0, DB::table('vendor_refund_receivables')->where('supplier_credit_note_id', $credit)->count());
        self::assertSame($event, DB::table('accounting_posting_policies')->where('id', $evidence->accounting_policy_id)->value('business_event'));
        $journal = DB::table('payroll_accounting_journals')->where('id', $evidence->accounting_journal_id)->firstOrFail();
        self::assertSame((int) $journal->total_debit_minor, (int) $journal->total_credit_minor);
        self::assertSame(abs($expectedPpv), (int) DB::table('payroll_accounting_journal_lines')->where('journal_id', $journal->id)->where('description', 'like', 'Purchase price variance%')->value($ppvSide.'_minor'));
        self::assertSame(100_000, (int) DB::table('inventory_return_fx_reversals')->where('goods_return_id', $return)->value('released_functional_basis_minor'));
        $paymentEvidenceAfter = DB::table('supplier_payment_fx_settlements')->where('id', $paymentEvidenceBefore->id)->firstOrFail();
        self::assertEquals($paymentEvidenceBefore, $paymentEvidenceAfter);

        $receivable = DB::table('vendor_refund_receivables')->where('supplier_credit_note_id', $credit)->firstOrFail();
        $refunds = app(VendorRefundService::class);
        $firstAmount = intdiv($expectedReceivableForeign, 2);
        $refundIds = [];
        foreach ([[$firstAmount, '2026-09-21', 'A'], [$expectedReceivableForeign - $firstAmount, '2026-09-22', 'B']] as [$amount, $date, $suffix]) {
            $refund = $refunds->create($company, $maker, [
                'vendor_refund_receivable_id' => $receivable->id, 'financial_account_id' => $usdAccount,
                'reference' => 'REFUND-'.$exposure.'-'.$ppvSide.'-'.$suffix, 'refund_date' => $date,
                'amount_minor' => $amount, 'idempotency_key' => 'refund-'.strtolower($exposure.'-'.$ppvSide.'-'.$suffix),
            ]);
            $refunds->submit($company, $refund, $maker);
            $refunds->approve($company, $refund, $checker);
            $refunds->post($company, $refund, $checker);
            $refundIds[] = $refund;
        }
        $expectedCash = $expectedReceivableForeign * 250;
        $expectedRealized = $expectedCash - $expectedReceivableFunctional;
        self::assertSame($expectedCash, (int) DB::table('supplier_refund_fx_settlements')->whereIn('supplier_refund_receipt_id', $refundIds)->sum('functional_cash_minor'));
        self::assertSame($expectedReceivableFunctional, (int) DB::table('supplier_refund_fx_settlements')->whereIn('supplier_refund_receipt_id', $refundIds)->sum('released_carrying_minor'));
        self::assertSame($expectedRealized, (int) DB::table('supplier_refund_fx_settlements')->whereIn('supplier_refund_receipt_id', $refundIds)->sum('realized_fx_minor'));
        self::assertSame('refunded', DB::table('vendor_refund_receivables')->where('id', $receivable->id)->value('status'));
        self::assertSame(2, DB::table('treasury_transactions')->whereIn('source_id', $refundIds)->where('source_type', 'supplier_refund_receipt')->count());
        self::assertSame(2, DB::table('payroll_accounting_journals')->whereIn('source_id', $refundIds)->where('source_type', 'supplier_refund_receipt')->count());
        self::assertEquals($paymentEvidenceBefore, DB::table('supplier_payment_fx_settlements')->where('id', $paymentEvidenceBefore->id)->firstOrFail());
    }

    /** @return iterable<string,array{string,int,string,string,string,int,int,int,int,int,string}> */
    public static function supplierCreditPaidPpvCases(): iterable
    {
        yield 'supplier receivable PPV loss' => ['RECEIVABLE', 400, '24000.000000000000', 'FOREIGN_SUPPLIER_CREDIT_RECEIVABLE_PPV_LOSS', 'PURCHASE_PRICE_VARIANCE_LOSS', 0, 0, 400, 96_000, 4_000, 'debit'];
        yield 'supplier receivable PPV gain' => ['RECEIVABLE', 400, '26000.000000000000', 'FOREIGN_SUPPLIER_CREDIT_RECEIVABLE_PPV_GAIN', 'PURCHASE_PRICE_VARIANCE_GAIN', 0, 0, 400, 104_000, -4_000, 'credit'];
        yield 'mixed AP and receivable PPV loss' => ['MIXED', 200, '24000.000000000000', 'FOREIGN_SUPPLIER_CREDIT_MIXED_PPV_LOSS', 'PURCHASE_PRICE_VARIANCE_LOSS', 200, 48_000, 200, 48_000, 4_000, 'debit'];
        yield 'mixed AP and receivable PPV gain' => ['MIXED', 200, '26000.000000000000', 'FOREIGN_SUPPLIER_CREDIT_MIXED_PPV_GAIN', 'PURCHASE_PRICE_VARIANCE_GAIN', 200, 52_000, 200, 52_000, -4_000, 'credit'];
    }

    #[DataProvider('multipleSupplierCreditCurrencyCases')]
    public function test_multiple_foreign_supplier_credits_release_final_carrying_residue_exactly_once(
        string $currency,
        string $rate,
        int $expectedRecognition,
        int $expectedPaymentCarrying,
        int $expectedApCarrying,
        int $expectedReceivableCarrying,
    ): void {
        [$maker, $checker, $company, $branch, $variant, $location] = $this->runtime();
        $this->configureSpotRate($company, $maker, $checker, $currency, $rate);
        $this->configureVendorRefundPolicies($company, $maker, $checker);
        [$order, $orderLine, , $receiptLine] = $this->receivedGoods($maker, $checker, $company, $branch, $variant, $location, $currency);
        $procurement = app(ProcurementService::class);
        $bill = $procurement->mutate('bill.create', $company, $maker, [
            'purchase_order_id' => $order, 'purchase_order_line_id' => $orderLine, 'goods_receipt_line_id' => $receiptLine,
            'quantity' => 5, 'invoice_number' => 'INV-MULTI-'.$currency, 'invoice_date' => '2026-09-15', 'due_date' => '2026-10-15',
        ]);
        $procurement->mutate('bill.approve', $company, $checker, ['id' => $bill]);
        $account = $this->foreignTreasuryAccount($company, $branch, $maker, $currency.'-MULTI', $currency);
        $payment = $procurement->mutate('payment.allocate', $company, $checker, [
            'vendor_bill_id' => $bill, 'financial_account_id' => $account, 'payment_date' => '2026-09-16',
            'amount_minor' => 400, 'method' => 'bank_transfer', 'reference' => 'PAY-MULTI-'.$currency,
        ]);
        $paymentEvidence = DB::table('supplier_payment_fx_settlements')->whereIn('ap_payment_allocation_id', DB::table('ap_payment_allocations')->where('payment_id', $payment)->select('id'))->firstOrFail();
        self::assertSame($expectedRecognition, (int) DB::table('supplier_invoice_fx_recognitions')->where('vendor_bill_id', $bill)->value('functional_amount_minor'));
        self::assertSame($expectedPaymentCarrying, (int) $paymentEvidence->released_carrying_minor);

        $credits = app(SupplierCreditService::class);
        $creditIds = [];
        $returnIds = [];
        foreach ([[2, 'A'], [2, 'B'], [1, 'C']] as [$quantity, $suffix]) {
            $returns = app(GoodsReturnService::class);
            $return = $returns->create($company, $maker, [
                'goods_receipt_line_id' => $receiptLine, 'quantity' => $quantity,
                'return_number' => 'RTV-MULTI-'.$currency.'-'.$suffix, 'return_date' => '2026-09-20',
                'reason' => 'Sequential canonical foreign return', 'idempotency_key' => 'rtv-multi-'.strtolower($currency.'-'.$suffix),
            ]);
            $returns->submit($company, $return, $maker);
            $returns->approve($company, $return, $checker);
            $payload = [
                'goods_return_line_id' => DB::table('procurement_goods_return_lines')->where('goods_return_id', $return)->value('id'),
                'vendor_bill_receipt_allocation_id' => DB::table('vendor_bill_receipt_allocations')->whereIn('vendor_bill_line_id', DB::table('vendor_bill_lines')->where('vendor_bill_id', $bill)->select('id'))->value('id'),
                'quantity' => $quantity, 'credit_number' => 'SCN-MULTI-'.$currency.'-'.$suffix, 'credit_date' => '2026-09-20',
                'reason' => 'Sequential canonical supplier credit', 'idempotency_key' => 'scn-multi-'.strtolower($currency.'-'.$suffix),
            ];
            $credit = $credits->create($company, $maker, $payload);
            self::assertSame($credit, $credits->create($company, $maker, $payload));
            $credits->submit($company, $credit, $maker);
            $credits->approve($company, $credit, $checker);
            $credits->post($company, $credit, $checker);
            $returnIds[] = $return;
            $creditIds[] = $credit;
        }

        self::assertSame(1_000, (int) DB::table('supplier_credit_fx_evidence')->whereIn('supplier_credit_note_id', $creditIds)->sum('foreign_credit_minor'));
        self::assertSame(1_000, (int) DB::table('supplier_credit_fx_evidence')->where('supplier_credit_note_id', end($creditIds))->value('cumulative_foreign_credit_minor'));
        self::assertSame(600, (int) DB::table('supplier_credit_notes')->whereIn('id', $creditIds)->sum('ap_reduction_minor'));
        self::assertSame(400, (int) DB::table('supplier_credit_notes')->whereIn('id', $creditIds)->sum('vendor_receivable_minor'));
        self::assertSame($expectedApCarrying, (int) DB::table('supplier_credit_fx_evidence')->whereIn('supplier_credit_note_id', $creditIds)->sum('ap_carrying_release_minor'));
        self::assertSame($expectedReceivableCarrying, (int) DB::table('supplier_credit_fx_evidence')->whereIn('supplier_credit_note_id', $creditIds)->sum('supplier_receivable_functional_minor'));
        self::assertSame($expectedRecognition, $expectedApCarrying + $expectedReceivableCarrying);
        self::assertSame($expectedRecognition, (int) DB::table('inventory_return_fx_reversals')->whereIn('goods_return_id', $returnIds)->sum('released_functional_basis_minor'));
        self::assertSame(0, (int) DB::table('supplier_credit_fx_evidence')->whereIn('supplier_credit_note_id', $creditIds)->sum('purchase_price_variance_minor'));
        self::assertSame(3, DB::table('payroll_accounting_journals')->where('source_type', 'supplier_credit')->whereIn('source_id', $creditIds)->count());
        self::assertSame(10, (int) DB::table('inventory_balances')->where(['product_variant_id' => $variant, 'inventory_location_id' => $location])->value('on_hand'));
        self::assertEquals($paymentEvidence, DB::table('supplier_payment_fx_settlements')->where('id', $paymentEvidence->id)->firstOrFail());
        $this->assertValidation(fn () => app(GoodsReturnService::class)->create($company, $maker, [
            'goods_receipt_line_id' => $receiptLine, 'quantity' => 1, 'return_number' => 'RTV-OVER-'.$currency,
            'return_date' => '2026-09-20', 'reason' => 'Must reject over return', 'idempotency_key' => 'rtv-over-'.strtolower($currency),
        ]), 'quantity', 'GOODS_RETURN_QUANTITY_EXCEEDS_RETURNABLE');
    }

    /** @return iterable<string,array{string,string,int,int,int,int}> */
    public static function multipleSupplierCreditCurrencyCases(): iterable
    {
        yield 'USD final residue' => ['USD', '25000.000000000000', 250_000, 100_000, 150_000, 100_000];
        yield 'EUR final residue' => ['EUR', '27000.000000000000', 270_000, 108_000, 162_000, 108_000];
        yield 'JPY final residue' => ['JPY', '160.000000000000', 160_000, 64_000, 96_000, 64_000];
    }

    public function test_locked_period_rolls_back_supplier_credit_preliminary_work_and_retry_posts_once(): void
    {
        $fixture = $this->approvedForeignCreditFixture('LOCKED');
        DB::table('accounting_periods')->where('company_id', $fixture['company'])->whereDate('start_date', '<=', '2026-09-20')->whereDate('end_date', '>=', '2026-09-20')->update(['status' => 'closed']);

        $this->assertValidation(fn () => app(SupplierCreditService::class)->post($fixture['company'], $fixture['credit'], $fixture['checker']), 'period', 'ACCOUNTING_PERIOD_CLOSED');
        $this->assertCreditPostingRolledBack($fixture);

        DB::table('accounting_periods')->where('company_id', $fixture['company'])->update(['status' => 'open']);
        app(SupplierCreditService::class)->post($fixture['company'], $fixture['credit'], $fixture['checker']);
        app(SupplierCreditService::class)->post($fixture['company'], $fixture['credit'], $fixture['checker']);
        self::assertSame('posted', DB::table('supplier_credit_notes')->where('id', $fixture['credit'])->value('status'));
        self::assertSame(1, DB::table('supplier_credit_fx_evidence')->where('supplier_credit_note_id', $fixture['credit'])->count());
        self::assertSame(1, DB::table('inventory_return_fx_reversals')->where('goods_return_id', $fixture['return'])->count());
        self::assertSame(1, DB::table('payroll_accounting_journals')->where(['source_type' => 'supplier_credit', 'source_id' => $fixture['credit']])->count());
    }

    public function test_missing_ap_carrying_evidence_rolls_back_goods_return_and_supplier_credit(): void
    {
        $fixture = $this->approvedForeignCreditFixture('MISSING-CARRYING');
        $recognitionId = (int) DB::table('supplier_invoice_fx_recognitions')->where('vendor_bill_id', $fixture['bill'])->value('id');
        DB::table('vendor_bill_receipt_fx_matches')->where('supplier_invoice_fx_recognition_id', $recognitionId)->delete();
        DB::table('supplier_invoice_fx_recognitions')->where('vendor_bill_id', $fixture['bill'])->delete();

        $this->assertValidation(fn () => app(SupplierCreditService::class)->post($fixture['company'], $fixture['credit'], $fixture['checker']), 'fx_evidence', 'SUPPLIER_CREDIT_AP_FX_RECOGNITION_REQUIRED');
        $this->assertCreditPostingRolledBack($fixture);
    }

    public function test_inventory_fx_data_quality_rules_detect_and_clear_controlled_corruption(): void
    {
        $fixture = $this->postedForeignRefundFixture('DQ');
        $rules = [
            'inventory_return_fx_lineage_mismatch',
            'supplier_credit_fx_carrying_mismatch',
            'supplier_credit_fx_policy_journal_mismatch',
            'supplier_refund_fx_snapshot_lineage_mismatch',
            'supplier_refund_fx_accounting_treasury_mismatch',
        ];
        foreach ($rules as $rule) {
            self::assertSame(0, $this->dataQualityCount($rule));
        }

        $reversal = DB::table('inventory_return_fx_reversals')->where('goods_return_id', $fixture['return'])->firstOrFail();
        DB::table('inventory_return_fx_reversals')->where('id', $reversal->id)->update(['released_functional_basis_minor' => (int) $reversal->released_functional_basis_minor + 1]);
        self::assertGreaterThan(0, $this->dataQualityCount('inventory_return_fx_lineage_mismatch'));
        DB::table('inventory_return_fx_reversals')->where('id', $reversal->id)->update(['released_functional_basis_minor' => $reversal->released_functional_basis_minor]);
        self::assertSame(0, $this->dataQualityCount('inventory_return_fx_lineage_mismatch'));

        $creditEvidence = DB::table('supplier_credit_fx_evidence')->where('supplier_credit_note_id', $fixture['credit'])->firstOrFail();
        DB::table('supplier_credit_fx_evidence')->where('id', $creditEvidence->id)->update(['ap_carrying_release_minor' => (int) $creditEvidence->ap_carrying_release_minor + 1]);
        self::assertGreaterThan(0, $this->dataQualityCount('supplier_credit_fx_carrying_mismatch'));
        DB::table('supplier_credit_fx_evidence')->where('id', $creditEvidence->id)->update(['ap_carrying_release_minor' => $creditEvidence->ap_carrying_release_minor]);
        self::assertSame(0, $this->dataQualityCount('supplier_credit_fx_carrying_mismatch'));

        $creditJournal = DB::table('payroll_accounting_journals')->where('id', $creditEvidence->accounting_journal_id)->firstOrFail();
        DB::table('payroll_accounting_journals')->where('id', $creditJournal->id)->update(['total_credit_minor' => (int) $creditJournal->total_credit_minor + 1]);
        self::assertGreaterThan(0, $this->dataQualityCount('supplier_credit_fx_policy_journal_mismatch'));
        DB::table('payroll_accounting_journals')->where('id', $creditJournal->id)->update(['total_credit_minor' => $creditJournal->total_credit_minor]);
        self::assertSame(0, $this->dataQualityCount('supplier_credit_fx_policy_journal_mismatch'));

        $settlement = DB::table('supplier_refund_fx_settlements')->where('supplier_refund_receipt_id', $fixture['refund'])->firstOrFail();
        DB::table('supplier_refund_fx_settlements')->where('id', $settlement->id)->update(['realized_fx_minor' => (int) $settlement->realized_fx_minor + 1]);
        self::assertGreaterThan(0, $this->dataQualityCount('supplier_refund_fx_snapshot_lineage_mismatch'));
        DB::table('supplier_refund_fx_settlements')->where('id', $settlement->id)->update(['realized_fx_minor' => $settlement->realized_fx_minor]);
        self::assertSame(0, $this->dataQualityCount('supplier_refund_fx_snapshot_lineage_mismatch'));

        $treasury = DB::table('treasury_transactions')->where('id', $settlement->treasury_transaction_id)->firstOrFail();
        DB::table('treasury_transactions')->where('id', $treasury->id)->update(['amount_minor' => (int) $treasury->amount_minor + 1]);
        self::assertGreaterThan(0, $this->dataQualityCount('supplier_refund_fx_accounting_treasury_mismatch'));
        DB::table('treasury_transactions')->where('id', $treasury->id)->update(['amount_minor' => $treasury->amount_minor]);
        foreach ($rules as $rule) {
            self::assertSame(0, $this->dataQualityCount($rule));
        }
    }

    public function test_vendor_refund_journal_boundary_rolls_back_snapshot_and_retries_exactly_once(): void
    {
        $fixture = $this->approvedForeignRefundFixture('LOCKED-REFUND');
        DB::table('accounting_periods')->where('company_id', $fixture['company'])->whereDate('start_date', '<=', '2026-09-21')->whereDate('end_date', '>=', '2026-09-21')->update(['status' => 'closed']);

        $this->assertValidation(fn () => app(VendorRefundService::class)->post($fixture['company'], $fixture['refund'], $fixture['checker']), 'period', 'ACCOUNTING_PERIOD_CLOSED');
        self::assertSame('approved', DB::table('supplier_refund_receipts')->where('id', $fixture['refund'])->value('status'));
        self::assertSame(0, (int) DB::table('vendor_refund_receivables')->where('id', $fixture['receivable'])->value('refunded_minor'));
        self::assertSame(0, DB::table('finance_fx_rate_snapshots')->where(['source_type' => 'supplier_refund_receipt_settlement', 'source_id' => $fixture['refund']])->count());
        self::assertSame(0, DB::table('payroll_accounting_journals')->where(['source_type' => 'supplier_refund_receipt', 'source_id' => $fixture['refund']])->count());
        self::assertSame(0, DB::table('treasury_transactions')->where(['source_type' => 'supplier_refund_receipt', 'source_id' => $fixture['refund']])->count());
        self::assertSame(0, DB::table('supplier_refund_fx_settlements')->where('supplier_refund_receipt_id', $fixture['refund'])->count());

        DB::table('accounting_periods')->where('company_id', $fixture['company'])->update(['status' => 'open']);
        $refunds = app(VendorRefundService::class);
        $refunds->post($fixture['company'], $fixture['refund'], $fixture['checker']);
        $refunds->post($fixture['company'], $fixture['refund'], $fixture['checker']);
        self::assertSame(400, (int) DB::table('vendor_refund_receivables')->where('id', $fixture['receivable'])->value('refunded_minor'));
        self::assertSame(1, DB::table('finance_fx_rate_snapshots')->where(['source_type' => 'supplier_refund_receipt_settlement', 'source_id' => $fixture['refund']])->count());
        self::assertSame(1, DB::table('payroll_accounting_journals')->where(['source_type' => 'supplier_refund_receipt', 'source_id' => $fixture['refund']])->count());
        self::assertSame(1, DB::table('treasury_transactions')->where(['source_type' => 'supplier_refund_receipt', 'source_id' => $fixture['refund']])->count());
        self::assertSame(1, DB::table('supplier_refund_fx_settlements')->where('supplier_refund_receipt_id', $fixture['refund'])->count());
    }

    public function test_vendor_refund_missing_direct_spot_fails_without_economic_effect(): void
    {
        $fixture = $this->approvedForeignRefundFixture('NO-SPOT');
        DB::table('finance_fx_rates')->where('company_id', $fixture['company'])->update(['status' => 'retired']);

        $this->assertValidation(fn () => app(VendorRefundService::class)->post($fixture['company'], $fixture['refund'], $fixture['checker']), 'rate', 'FX_RATE_NOT_AVAILABLE');
        self::assertSame('approved', DB::table('supplier_refund_receipts')->where('id', $fixture['refund'])->value('status'));
        self::assertSame(0, (int) DB::table('vendor_refund_receivables')->where('id', $fixture['receivable'])->value('refunded_minor'));
        self::assertSame(0, DB::table('finance_fx_rate_snapshots')->where(['source_type' => 'supplier_refund_receipt_settlement', 'source_id' => $fixture['refund']])->count());
        self::assertSame(0, DB::table('payroll_accounting_journals')->where(['source_type' => 'supplier_refund_receipt', 'source_id' => $fixture['refund']])->count());
        self::assertSame(0, DB::table('treasury_transactions')->where(['source_type' => 'supplier_refund_receipt', 'source_id' => $fixture['refund']])->count());
        self::assertSame(0, DB::table('supplier_refund_fx_settlements')->where('supplier_refund_receipt_id', $fixture['refund'])->count());
    }

    public function test_supplier_credit_workspace_renders_fx_lineage_with_permission_aware_sources(): void
    {
        $fixture = $this->postedForeignRefundFixture('UI');
        $fixture['maker']->givePermissionTo(collect([
            'supplier-credit.view', 'procurement-goods-return.view', 'vendor-bill.view', 'payroll.accounting.view', 'treasury.view',
        ])->map(fn (string $permission) => Permission::findOrCreate($permission, 'web')));

        $response = $this->actingAs($fixture['maker'])->get(route('admin.procurement.supplier-credits', ['company_id' => $fixture['company']]));
        $response->assertOk()
            ->assertSee('SCN-UI')
            ->assertSee('REFUND-UI')
            ->assertSee('AP / Receivable / PPV trace')
            ->assertSee('spot / realized FX trace')
            ->assertSee('Mở Vendor Bill')
            ->assertSee('Mở Goods Return')
            ->assertSee('Mở journal')
            ->assertSee('Mở Treasury inflow')
            ->assertDontSee('NOT_ENOUGH_EVIDENCE');
    }

    public function test_fully_paid_foreign_supplier_credit_creates_receivable_and_refunds_it_exactly_once(): void
    {
        [$maker, $checker, $company, $branch, $variant, $location] = $this->runtime();
        $this->configureSpotRate($company, $maker, $checker, 'USD', '25000.000000000000');
        $this->configureVendorRefundPolicies($company, $maker, $checker);
        [$order, $orderLine, , $receiptLine] = $this->receivedGoods($maker, $checker, $company, $branch, $variant, $location, 'USD');
        $procurement = app(ProcurementService::class);
        $bill = $procurement->mutate('bill.create', $company, $maker, [
            'purchase_order_id' => $order,
            'purchase_order_line_id' => $orderLine,
            'goods_receipt_line_id' => $receiptLine,
            'quantity' => 2,
            'invoice_number' => 'INV-FX-PAID-CREDIT',
            'invoice_date' => '2026-09-15',
            'due_date' => '2026-10-15',
        ]);
        $procurement->mutate('bill.approve', $company, $checker, ['id' => $bill]);
        $cashLedger = (int) DB::table('accounting_accounts')->where(['company_id' => $company, 'code' => '1100'])->value('id');
        DB::table('accounting_accounts')->where('id', $cashLedger)->update(['currency' => null]);
        $usdAccount = app(TreasuryService::class)->mutate('account.create', $company, $maker, [
            'branch_id' => $branch,
            'accounting_account_id' => $cashLedger,
            'code' => 'USD-FX-REFUND',
            'name' => 'USD settlement account',
            'type' => 'bank',
        ]);
        DB::table('treasury_financial_accounts')->where('id', $usdAccount)->update(['currency' => 'USD']);
        $payment = $procurement->mutate('payment.allocate', $company, $checker, [
            'vendor_bill_id' => $bill,
            'financial_account_id' => $usdAccount,
            'payment_date' => '2026-09-16',
            'amount_minor' => 400,
            'method' => 'bank_transfer',
            'reference' => 'PAY-FX-FULL',
        ]);
        self::assertGreaterThan(0, $payment);
        self::assertSame('paid', DB::table('vendor_bills')->where('id', $bill)->value('status'));
        self::assertSame(1, DB::table('supplier_payment_fx_settlements')->whereIn('ap_payment_allocation_id', DB::table('ap_payment_allocations')->where('payment_id', $payment)->select('id'))->count());

        $returns = app(GoodsReturnService::class);
        $return = $returns->create($company, $maker, [
            'goods_receipt_line_id' => $receiptLine,
            'quantity' => 2,
            'return_number' => 'RTV-FX-PAID',
            'return_date' => '2026-09-20',
            'reason' => 'Paid foreign goods returned',
            'idempotency_key' => 'rtv-fx-paid',
        ]);
        $returns->submit($company, $return, $maker);
        $returns->approve($company, $return, $checker);
        $creditPayload = [
            'goods_return_line_id' => DB::table('procurement_goods_return_lines')->where('goods_return_id', $return)->value('id'),
            'vendor_bill_receipt_allocation_id' => DB::table('vendor_bill_receipt_allocations')->whereIn('vendor_bill_line_id', DB::table('vendor_bill_lines')->where('vendor_bill_id', $bill)->select('id'))->value('id'),
            'quantity' => 2,
            'credit_number' => 'SCN-FX-PAID',
            'credit_date' => '2026-09-20',
            'reason' => 'Paid foreign supplier credit',
            'idempotency_key' => 'scn-fx-paid',
        ];
        $credits = app(SupplierCreditService::class);
        $credit = $credits->create($company, $maker, $creditPayload);
        $credits->submit($company, $credit, $maker);
        $credits->approve($company, $credit, $checker);
        $credits->post($company, $credit, $checker);
        $creditEvidence = DB::table('supplier_credit_fx_evidence')->where('supplier_credit_note_id', $credit)->firstOrFail();
        $receivable = DB::table('vendor_refund_receivables')->where('supplier_credit_note_id', $credit)->firstOrFail();
        self::assertSame(0, (int) DB::table('supplier_credit_notes')->where('id', $credit)->value('ap_reduction_minor'));
        self::assertSame(400, (int) $receivable->original_minor);
        self::assertSame(100_000, (int) $creditEvidence->supplier_receivable_functional_minor);
        self::assertSame(0, (int) $creditEvidence->purchase_price_variance_minor);
        self::assertSame(0, DB::table('treasury_transactions')->where('source_type', 'supplier_refund_receipt')->count());

        $refunds = app(VendorRefundService::class);
        $firstPayload = [
            'vendor_refund_receivable_id' => $receivable->id,
            'financial_account_id' => $usdAccount,
            'reference' => 'REFUND-FX-1',
            'refund_date' => '2026-09-21',
            'amount_minor' => 150,
            'idempotency_key' => 'refund-fx-1',
        ];
        $first = $refunds->create($company, $maker, $firstPayload);
        self::assertSame($first, $refunds->create($company, $maker, $firstPayload));
        $this->assertValidation(fn () => $refunds->create($company, $maker, [...$firstPayload, 'amount_minor' => 151]), 'idempotency_key', 'SUPPLIER_REFUND_IDEMPOTENCY_CONFLICT');
        $refunds->submit($company, $first, $maker);
        $refunds->approve($company, $first, $checker);
        $refunds->post($company, $first, $checker);
        $refunds->post($company, $first, $checker);
        $firstEvidence = DB::table('supplier_refund_fx_settlements')->where('supplier_refund_receipt_id', $first)->firstOrFail();
        self::assertSame(37_500, (int) $firstEvidence->functional_cash_minor);
        self::assertSame(37_500, (int) $firstEvidence->released_carrying_minor);
        self::assertSame(0, (int) $firstEvidence->realized_fx_minor);

        $second = $refunds->create($company, $maker, [
            'vendor_refund_receivable_id' => $receivable->id,
            'financial_account_id' => $usdAccount,
            'reference' => 'REFUND-FX-2',
            'refund_date' => '2026-09-22',
            'amount_minor' => 250,
            'idempotency_key' => 'refund-fx-2',
        ]);
        $refunds->submit($company, $second, $maker);
        $refunds->approve($company, $second, $checker);
        $refunds->post($company, $second, $checker);
        $secondEvidence = DB::table('supplier_refund_fx_settlements')->where('supplier_refund_receipt_id', $second)->firstOrFail();
        self::assertSame(62_500, (int) $secondEvidence->functional_cash_minor);
        self::assertSame(62_500, (int) $secondEvidence->released_carrying_minor);
        self::assertSame(0, (int) $secondEvidence->realized_fx_minor);
        self::assertSame('refunded', DB::table('vendor_refund_receivables')->where('id', $receivable->id)->value('status'));
        self::assertSame(2, DB::table('supplier_refund_fx_settlements')->where('vendor_refund_receivable_id', $receivable->id)->count());
        self::assertSame(2, DB::table('finance_fx_rate_snapshots')->where('source_type', 'supplier_refund_receipt_settlement')->count());
        self::assertSame(2, DB::table('treasury_transactions')->where(['source_type' => 'supplier_refund_receipt', 'direction' => 'inflow'])->count());
        self::assertSame(2, DB::table('payroll_accounting_journals')->where('source_type', 'supplier_refund_receipt')->count());
        self::assertSame(1, DB::table('supplier_payment_fx_settlements')->whereIn('ap_payment_allocation_id', DB::table('ap_payment_allocations')->where('payment_id', $payment)->select('id'))->count());
    }

    public function test_company_configuration_readiness_distinguishes_missing_ready_and_invalid(): void
    {
        [$maker, $checker, $company, $branch] = $this->runtime();
        $readiness = app(VendorRefundConfigurationReadinessService::class);

        $missing = $readiness->forCompany($company, '2026-09-20');
        self::assertSame('MISSING', $missing['paid_credit_policy']['status']);
        self::assertSame('MISSING', $missing['refund_policy']['status']);
        self::assertSame('MISSING', $missing['treasury']['status']);
        self::assertSame('CONFIGURATION_REQUIRED', $missing['overall']);

        $this->configureVendorRefundPolicies($company, $maker, $checker);
        $cashLedger = (int) DB::table('accounting_accounts')->where(['company_id' => $company, 'code' => '1100'])->value('id');
        app(TreasuryService::class)->mutate('account.create', $company, $maker, ['branch_id' => $branch, 'accounting_account_id' => $cashLedger, 'code' => 'REFUND-BANK', 'name' => 'Supplier Refund Bank', 'type' => 'bank']);
        $ready = $readiness->forCompany($company, '2026-09-20');
        self::assertSame('READY', $ready['paid_credit_policy']['status']);
        self::assertSame('READY', $ready['refund_policy']['status']);
        self::assertSame('READY', $ready['treasury']['status']);
        self::assertSame('READY', $ready['overall']);

        DB::table('accounting_accounts')->where(['company_id' => $company, 'code' => '1150'])->update(['status' => 'inactive']);
        $invalid = $readiness->forCompany($company, '2026-09-20');
        self::assertSame('INVALID', $invalid['paid_credit_policy']['status']);
        self::assertSame('INVALID', $invalid['refund_policy']['status']);
        self::assertSame('CONFIGURATION_REQUIRED', $invalid['overall']);
    }

    private function configureVendorRefundPolicies(int $company, User $maker, User $checker): void
    {
        $posting = app(SourceAccountingPostingService::class);
        $receivable = $posting->accountEvidence($company, '1150', 'VND', (int) $maker->id)['id'];
        $grni = $posting->accountEvidence($company, '2150', 'VND', (int) $maker->id)['id'];
        $cash = $posting->accountEvidence($company, '1100', 'VND', (int) $maker->id)['id'];
        $policies = app(AccountingPostingPolicyService::class);
        foreach ([
            ['key' => 'SUPPLIER-CREDIT-PAID', 'event' => 'SUPPLIER_CREDIT_PAID_PORTION', 'lines' => [['entry_side' => 'debit', 'line_role' => 'RECEIVABLE', 'account_id' => $receivable], ['entry_side' => 'credit', 'line_role' => 'GRNI', 'account_id' => $grni]]],
            ['key' => 'SUPPLIER-CREDIT-MIXED', 'event' => 'SUPPLIER_CREDIT_MIXED_ADJUSTMENT', 'lines' => [['entry_side' => 'debit', 'line_role' => 'AP', 'account_id' => (int) DB::table('accounting_accounts')->where(['company_id' => $company, 'code' => '2100'])->value('id')], ['entry_side' => 'debit', 'line_role' => 'RECEIVABLE', 'account_id' => $receivable], ['entry_side' => 'credit', 'line_role' => 'GRNI', 'account_id' => $grni]]],
            ['key' => 'SUPPLIER-REFUND-RECEIVED', 'event' => 'SUPPLIER_REFUND_RECEIVED', 'lines' => [['entry_side' => 'debit', 'line_role' => 'CASH', 'account_id' => $cash], ['entry_side' => 'credit', 'line_role' => 'RECEIVABLE', 'account_id' => $receivable]]],
        ] as $definition) {
            $mixed = $definition['event'] === 'SUPPLIER_CREDIT_MIXED_ADJUSTMENT';
            $id = $policies->create($company, $maker, ['policy_key' => $definition['key'], 'source_type' => $mixed ? 'supplier_credit' : 'supplier_refund', 'business_event' => $definition['event'], 'classification' => $mixed ? 'supplier_credit' : 'vendor_refund', 'version' => 1, 'currency' => 'VND', 'effective_from' => '2026-01-01', 'lines' => $definition['lines']]);
            $policies->submit($company, $id, $maker);
            $policies->approve($company, $id, $checker);
        }
    }

    private function configureVendorRefundFxPolicies(int $company, User $maker, User $checker): void
    {
        $this->configureVendorRefundPolicies($company, $maker, $checker);
        $posting = app(SourceAccountingPostingService::class);
        $cash = $posting->accountEvidence($company, '1100', 'VND', (int) $maker->id)['id'];
        $receivable = $posting->accountEvidence($company, '1150', 'VND', (int) $maker->id)['id'];
        $loss = (int) DB::table('accounting_accounts')->insertGetId([
            'uuid' => (string) Str::uuid(), 'company_id' => $company, 'code' => '6790', 'name' => 'Supplier Refund FX Loss',
            'account_type' => 'expense', 'normal_balance' => 'debit', 'currency' => 'VND', 'allow_posting' => true,
            'status' => 'active', 'effective_from' => '2020-01-01', 'created_by' => $maker->id, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $gain = (int) DB::table('accounting_accounts')->insertGetId([
            'uuid' => (string) Str::uuid(), 'company_id' => $company, 'code' => '4790', 'name' => 'Supplier Refund FX Gain',
            'account_type' => 'revenue', 'normal_balance' => 'credit', 'currency' => 'VND', 'allow_posting' => true,
            'status' => 'active', 'effective_from' => '2020-01-01', 'created_by' => $maker->id, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $policies = app(AccountingPostingPolicyService::class);
        foreach ([
            ['SUPPLIER_REFUND_FX_GAIN', [
                ['entry_side' => 'debit', 'line_role' => 'CASH', 'account_id' => $cash],
                ['entry_side' => 'credit', 'line_role' => 'RECEIVABLE', 'account_id' => $receivable],
                ['entry_side' => 'credit', 'line_role' => 'FX_GAIN', 'account_id' => $gain],
            ]],
            ['SUPPLIER_REFUND_FX_LOSS', [
                ['entry_side' => 'debit', 'line_role' => 'CASH', 'account_id' => $cash],
                ['entry_side' => 'debit', 'line_role' => 'FX_LOSS', 'account_id' => $loss],
                ['entry_side' => 'credit', 'line_role' => 'RECEIVABLE', 'account_id' => $receivable],
            ]],
        ] as [$event, $lines]) {
            $id = $policies->create($company, $maker, [
                'policy_key' => $event, 'source_type' => 'vendor_refund', 'business_event' => $event,
                'classification' => 'vendor_refund', 'version' => 1, 'currency' => 'VND', 'effective_from' => '2026-01-01', 'lines' => $lines,
            ]);
            $policies->submit($company, $id, $maker);
            $policies->approve($company, $id, $checker);
        }
    }

    /** @return array{as_of_date:string,vendor_id:null,branch_id:null,cost_center_id:null,due_bucket:null,payment_status:null,currency:string,sort:string,direction:string} */
    private function apFilters(string $date): array
    {
        return ['as_of_date' => $date, 'vendor_id' => null, 'branch_id' => null, 'cost_center_id' => null, 'due_bucket' => null, 'payment_status' => null, 'currency' => 'VND', 'sort' => 'due_date', 'direction' => 'asc'];
    }

    /** @return array{int,int,int,int} */
    private function receivedGoods(User $maker, User $checker, int $company, int $branch, int $variant, int $location, string $currency = 'VND'): array
    {
        $service = app(ProcurementService::class);
        $vendor = $service->mutate('vendor.create', $company, $maker, ['code' => 'RTV-V', 'name' => 'Return Vendor']);
        $costCenter = (int) DB::table('cost_centers')->insertGetId(['company_id' => $company, 'code' => 'RTV-CC', 'name' => 'RTV Cost Center', 'created_at' => now(), 'updated_at' => now()]);
        $request = $service->mutate('request.create', $company, $maker, ['branch_id' => $branch, 'cost_center_id' => $costCenter, 'reference' => 'PR-RTV', 'requested_date' => '2026-09-01', 'reason' => 'Stock', 'product_variant_id' => $variant, 'description' => 'Returnable goods', 'quantity' => 5, 'estimated_unit_cost_minor' => 200]);
        $service->mutate('request.transition', $company, $maker, ['id' => $request, 'to' => 'submitted']);
        $service->mutate('request.transition', $company, $checker, ['id' => $request, 'to' => 'approved']);
        $order = $service->mutate('order.create', $company, $maker, ['purchase_request_id' => $request, 'vendor_id' => $vendor, 'reference' => 'PO-RTV', 'currency' => $currency, 'order_date' => '2026-09-02']);
        $service->mutate('order.transition', $company, $checker, ['id' => $order, 'to' => 'approved']);
        $orderLine = (int) DB::table('purchase_order_lines')->where('purchase_order_id', $order)->value('id');
        $receipt = $service->mutate('receipt.create', $company, $maker, ['purchase_order_id' => $order, 'purchase_order_line_id' => $orderLine, 'inventory_location_id' => $location, 'reference' => 'GR-RTV', 'receipt_date' => '2026-09-10', 'quantity' => 5]);
        $service->mutate('receipt.receive', $company, $checker, ['id' => $receipt]);

        return [$order, $orderLine, $receipt, (int) DB::table('procurement_goods_receipt_lines')->where('goods_receipt_id', $receipt)->value('id')];
    }

    /** @return array{User,User,int,int,int,int} */
    private function runtime(): array
    {
        $maker = User::factory()->create(['email_verified_at' => now()]);
        $checker = User::factory()->create(['email_verified_at' => now()]);
        $company = (int) DB::table('companies')->insertGetId(['uuid' => (string) Str::uuid(), 'code' => 'RTV', 'name' => 'RTV Company', 'functional_currency_code' => 'VND', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
        $branch = (int) DB::table('branches')->insertGetId(['company_id' => $company, 'code' => 'HQ', 'name' => 'HQ', 'status' => 'active', 'timezone' => 'Asia/Ho_Chi_Minh', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('company_user_access')->insert([['company_id' => $company, 'user_id' => $maker->id, 'created_at' => now(), 'updated_at' => now()], ['company_id' => $company, 'user_id' => $checker->id, 'created_at' => now(), 'updated_at' => now()]]);
        $profile = (int) DB::table('seller_profiles')->insertGetId(['company_id' => $company, 'user_id' => $maker->id, 'display_name' => 'RTV Owner', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
        $store = (int) DB::table('stores')->insertGetId(['seller_profile_id' => $profile, 'name' => 'RTV Store', 'slug' => 'rtv-store', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
        $product = (int) DB::table('products')->insertGetId(['store_id' => $store, 'code' => 'RTV-P', 'name' => 'RTV Product', 'slug' => 'rtv-product', 'base_price_minor' => 500, 'currency' => 'VND', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
        $variant = (int) DB::table('product_variants')->insertGetId(['product_id' => $product, 'sku' => 'RTV-SKU', 'name' => 'Default', 'price_minor' => 500, 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
        $location = (int) DB::table('inventory_locations')->insertGetId(['store_id' => $store, 'branch_id' => $branch, 'code' => 'HQ', 'name' => 'HQ Stock', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('inventory_balances')->insert(['product_variant_id' => $variant, 'inventory_location_id' => $location, 'on_hand' => 10, 'reserved' => 0, 'inventory_value_minor' => 1000, 'average_unit_cost_minor' => 100, 'version' => 1, 'created_at' => now(), 'updated_at' => now()]);
        $this->configureGoodsReturnPolicy($company, $maker, $checker);

        return [$maker, $checker, $company, $branch, $variant, $location];
    }

    private function configureGoodsReturnPolicy(int $company, User $maker, User $checker): void
    {
        $posting = app(SourceAccountingPostingService::class);
        $grni = $posting->accountEvidence($company, '2150', 'VND', (int) $maker->id)['id'];
        $inventory = $posting->accountEvidence($company, '1200', 'VND', (int) $maker->id)['id'];
        $ap = $posting->accountEvidence($company, '2100', 'VND', (int) $maker->id)['id'];
        $cash = $posting->accountEvidence($company, '1100', 'VND', (int) $maker->id)['id'];
        $policies = app(AccountingPostingPolicyService::class);
        foreach ([
            ['key' => 'INVENTORY-RECEIPT', 'source' => 'goods_receipt', 'event' => 'INVENTORY_RECEIPT', 'classification' => 'inventory', 'lines' => [
                ['entry_side' => 'debit', 'line_role' => 'INVENTORY_ASSET', 'account_id' => $inventory, 'requires_cost_center' => true],
                ['entry_side' => 'credit', 'line_role' => 'GRNI', 'account_id' => $grni],
            ]],
            ['key' => 'GOODS-RETURN-TO-VENDOR', 'source' => 'goods_return', 'event' => 'GOODS_RETURN_TO_VENDOR', 'classification' => null, 'lines' => [
                ['entry_side' => 'debit', 'line_role' => 'GRNI', 'account_id' => $grni],
                ['entry_side' => 'credit', 'line_role' => 'INVENTORY_ASSET', 'account_id' => $inventory],
            ]],
            ['key' => 'VENDOR-BILL', 'source' => 'vendor_bill', 'event' => 'VENDOR_BILL_POSTED', 'classification' => 'inventory', 'lines' => [
                ['entry_side' => 'debit', 'line_role' => 'GRNI', 'account_id' => $grni],
                ['entry_side' => 'credit', 'line_role' => 'AP', 'account_id' => $ap],
            ]],
            ['key' => 'INVENTORY-VENDOR-BILL', 'source' => 'vendor_bill', 'event' => 'INVENTORY_VENDOR_BILL_POSTED', 'classification' => 'inventory', 'lines' => [
                ['entry_side' => 'debit', 'line_role' => 'GRNI', 'account_id' => $grni],
                ['entry_side' => 'credit', 'line_role' => 'AP', 'account_id' => $ap],
            ]],
            ['key' => 'VENDOR-PAYMENT', 'source' => 'vendor_payment', 'event' => 'VENDOR_PAYMENT', 'classification' => null, 'lines' => [
                ['entry_side' => 'debit', 'line_role' => 'AP', 'account_id' => $ap],
                ['entry_side' => 'credit', 'line_role' => 'CASH', 'account_id' => $cash],
            ]],
            ['key' => 'SUPPLIER-CREDIT-AP', 'source' => 'supplier_credit', 'event' => 'SUPPLIER_CREDIT_AP_ADJUSTMENT', 'classification' => 'supplier_credit', 'lines' => [
                ['entry_side' => 'debit', 'line_role' => 'AP', 'account_id' => $ap],
                ['entry_side' => 'credit', 'line_role' => 'GRNI', 'account_id' => $grni],
            ]],
        ] as $definition) {
            $id = $policies->create($company, $maker, [
                'policy_key' => $definition['key'], 'source_type' => $definition['source'],
                'business_event' => $definition['event'], 'classification' => $definition['classification'],
                'version' => 1, 'currency' => 'VND', 'effective_from' => '2026-01-01', 'lines' => $definition['lines'],
            ]);
            $policies->submit($company, $id, $maker);
            $policies->approve($company, $id, $checker);
        }
    }

    /**
     * @return array{
     *     maker:User,
     *     checker:User,
     *     company:int,
     *     variant:int,
     *     location:int,
     *     bill:int,
     *     return:int,
     *     return_line:int,
     *     credit:int,
     *     on_hand:int,
     *     inventory_value_minor:int
     * }
     */
    private function approvedForeignCreditFixture(string $suffix): array
    {
        [$maker, $checker, $company, $branch, $variant, $location] = $this->runtime();
        $this->configureSpotRate($company, $maker, $checker, 'USD', '25000.000000000000');
        [$order, $orderLine, , $receiptLine] = $this->receivedGoods($maker, $checker, $company, $branch, $variant, $location, 'USD');
        $procurement = app(ProcurementService::class);
        $bill = $procurement->mutate('bill.create', $company, $maker, [
            'purchase_order_id' => $order,
            'purchase_order_line_id' => $orderLine,
            'goods_receipt_line_id' => $receiptLine,
            'quantity' => 2,
            'invoice_number' => 'INV-FI-'.$suffix,
            'invoice_date' => '2026-09-15',
            'due_date' => '2026-10-15',
        ]);
        $procurement->mutate('bill.approve', $company, $checker, ['id' => $bill]);
        [$return, $credit] = $this->approvedReturnAndCredit($maker, $checker, $company, $receiptLine, $bill, 'FI-'.$suffix);
        $balance = DB::table('inventory_balances')->where([
            'product_variant_id' => $variant,
            'inventory_location_id' => $location,
        ])->firstOrFail();

        return [
            'maker' => $maker,
            'checker' => $checker,
            'company' => $company,
            'variant' => $variant,
            'location' => $location,
            'bill' => $bill,
            'return' => $return,
            'return_line' => (int) DB::table('procurement_goods_return_lines')->where('goods_return_id', $return)->value('id'),
            'credit' => $credit,
            'on_hand' => (int) $balance->on_hand,
            'inventory_value_minor' => (int) $balance->inventory_value_minor,
        ];
    }

    /** @return array{maker:User,company:int,checker:User,return:int,credit:int,refund:int,receivable:int} */
    private function approvedForeignRefundFixture(string $suffix): array
    {
        [$maker, $checker, $company, $branch, $variant, $location] = $this->runtime();
        $this->configureSpotRate($company, $maker, $checker, 'USD', '25000.000000000000');
        $this->configureVendorRefundFxPolicies($company, $maker, $checker);
        [$order, $orderLine, , $receiptLine] = $this->receivedGoods($maker, $checker, $company, $branch, $variant, $location, 'USD');
        $procurement = app(ProcurementService::class);
        $bill = $procurement->mutate('bill.create', $company, $maker, [
            'purchase_order_id' => $order, 'purchase_order_line_id' => $orderLine,
            'goods_receipt_line_id' => $receiptLine, 'quantity' => 2,
            'invoice_number' => 'INV-'.$suffix, 'invoice_date' => '2026-09-15', 'due_date' => '2026-10-15',
        ]);
        $procurement->mutate('bill.approve', $company, $checker, ['id' => $bill]);
        $account = $this->foreignTreasuryAccount($company, $branch, $maker, 'USD-'.$suffix);
        $procurement->mutate('payment.allocate', $company, $checker, [
            'vendor_bill_id' => $bill, 'financial_account_id' => $account, 'payment_date' => '2026-09-16',
            'amount_minor' => 400, 'method' => 'bank_transfer', 'reference' => 'PAY-'.$suffix,
        ]);
        [$return, $credit] = $this->approvedReturnAndCredit($maker, $checker, $company, $receiptLine, $bill, $suffix);
        app(SupplierCreditService::class)->post($company, $credit, $checker);
        $receivable = DB::table('vendor_refund_receivables')->where('supplier_credit_note_id', $credit)->firstOrFail();
        $refunds = app(VendorRefundService::class);
        $refund = $refunds->create($company, $maker, [
            'vendor_refund_receivable_id' => $receivable->id, 'financial_account_id' => $account,
            'reference' => 'REFUND-'.$suffix, 'refund_date' => '2026-09-21', 'amount_minor' => 400,
            'idempotency_key' => 'refund-'.strtolower($suffix),
        ]);
        $refunds->submit($company, $refund, $maker);
        $refunds->approve($company, $refund, $checker);

        return ['maker' => $maker, 'company' => $company, 'checker' => $checker, 'return' => $return, 'credit' => $credit, 'refund' => $refund, 'receivable' => (int) $receivable->id];
    }

    /** @return array{maker:User,company:int,return:int,credit:int,refund:int} */
    private function postedForeignRefundFixture(string $suffix): array
    {
        $fixture = $this->approvedForeignRefundFixture($suffix);
        app(VendorRefundService::class)->post($fixture['company'], $fixture['refund'], $fixture['checker']);

        return ['maker' => $fixture['maker'], 'company' => $fixture['company'], 'return' => $fixture['return'], 'credit' => $fixture['credit'], 'refund' => $fixture['refund']];
    }

    private function dataQualityCount(string $key): int
    {
        $finding = collect(app(DataQualityAuditService::class)->snapshot()['findings'])->firstWhere('key', $key);

        return (int) data_get($finding, 'count', -1);
    }

    /** @param array{company:int,variant:int,location:int,return:int,return_line:int,credit:int,on_hand:int,inventory_value_minor:int} $fixture */
    private function assertCreditPostingRolledBack(array $fixture): void
    {
        self::assertSame('approved', DB::table('supplier_credit_notes')->where('id', $fixture['credit'])->value('status'));
        self::assertSame('approved', DB::table('procurement_goods_returns')->where('id', $fixture['return'])->value('status'));
        self::assertSame($fixture['on_hand'], (int) DB::table('inventory_balances')->where([
            'product_variant_id' => $fixture['variant'],
            'inventory_location_id' => $fixture['location'],
        ])->value('on_hand'));
        self::assertSame($fixture['inventory_value_minor'], (int) DB::table('inventory_balances')->where([
            'product_variant_id' => $fixture['variant'],
            'inventory_location_id' => $fixture['location'],
        ])->value('inventory_value_minor'));
        self::assertSame(0, DB::table('inventory_movements')->where([
            'source_type' => 'procurement_goods_return_line',
            'source_id' => $fixture['return_line'],
        ])->count());
        self::assertSame(0, DB::table('inventory_return_fx_reversals')->where('goods_return_id', $fixture['return'])->count());
        self::assertSame(0, DB::table('supplier_credit_fx_evidence')->where('supplier_credit_note_id', $fixture['credit'])->count());
        self::assertSame(0, DB::table('payroll_accounting_journals')->where([
            'source_type' => 'supplier_credit',
            'source_id' => $fixture['credit'],
        ])->count());
    }

    /** @return array{int,int} */
    private function approvedReturnAndCredit(User $maker, User $checker, int $company, int $receiptLine, int $bill, string $suffix): array
    {
        $returns = app(GoodsReturnService::class);
        $return = $returns->create($company, $maker, [
            'goods_receipt_line_id' => $receiptLine, 'quantity' => 2, 'return_number' => 'RTV-'.$suffix,
            'return_date' => '2026-09-20', 'reason' => 'Canonical supplier credit integration', 'idempotency_key' => 'rtv-'.strtolower($suffix),
        ]);
        $returns->submit($company, $return, $maker);
        $returns->approve($company, $return, $checker);
        $credits = app(SupplierCreditService::class);
        $credit = $credits->create($company, $maker, [
            'goods_return_line_id' => DB::table('procurement_goods_return_lines')->where('goods_return_id', $return)->value('id'),
            'vendor_bill_receipt_allocation_id' => DB::table('vendor_bill_receipt_allocations')->whereIn('vendor_bill_line_id', DB::table('vendor_bill_lines')->where('vendor_bill_id', $bill)->select('id'))->value('id'),
            'quantity' => 2, 'credit_number' => 'SCN-'.$suffix, 'credit_date' => '2026-09-20',
            'reason' => 'Canonical supplier credit integration', 'idempotency_key' => 'scn-'.strtolower($suffix),
        ]);
        $credits->submit($company, $credit, $maker);
        $credits->approve($company, $credit, $checker);

        return [$return, $credit];
    }

    private function configurePpvPolicy(int $company, User $maker, User $checker, string $event, string $ppvRole, string $ppvSide, string $exposure = 'AP'): void
    {
        $posting = app(SourceAccountingPostingService::class);
        $ap = $posting->accountEvidence($company, '2100', 'VND', (int) $maker->id)['id'];
        $grni = $posting->accountEvidence($company, '2150', 'VND', (int) $maker->id)['id'];
        $receivable = $posting->accountEvidence($company, '1150', 'VND', (int) $maker->id)['id'];
        $loss = (int) DB::table('accounting_accounts')->insertGetId([
            'uuid' => (string) Str::uuid(), 'company_id' => $company, 'code' => '5190', 'name' => 'Purchase Price Variance Loss',
            'account_type' => 'expense', 'normal_balance' => 'debit', 'currency' => 'VND', 'allow_posting' => true,
            'status' => 'active', 'effective_from' => '2020-01-01', 'created_by' => $maker->id, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $gain = (int) DB::table('accounting_accounts')->insertGetId([
            'uuid' => (string) Str::uuid(), 'company_id' => $company, 'code' => '4190', 'name' => 'Purchase Price Variance Gain',
            'account_type' => 'revenue', 'normal_balance' => 'credit', 'currency' => 'VND', 'allow_posting' => true,
            'status' => 'active', 'effective_from' => '2020-01-01', 'created_by' => $maker->id, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $ppv = $ppvSide === 'debit' ? $loss : $gain;
        $lines = [];
        if (in_array($exposure, ['AP', 'MIXED'], true)) {
            $lines[] = ['entry_side' => 'debit', 'line_role' => 'AP', 'account_id' => $ap];
        }
        if (in_array($exposure, ['RECEIVABLE', 'MIXED'], true)) {
            $lines[] = ['entry_side' => 'debit', 'line_role' => 'RECEIVABLE', 'account_id' => $receivable];
        }
        if ($ppvSide === 'debit') {
            $lines[] = ['entry_side' => 'debit', 'line_role' => $ppvRole, 'account_id' => $ppv];
        }
        $lines[] = ['entry_side' => 'credit', 'line_role' => 'GRNI', 'account_id' => $grni];
        if ($ppvSide === 'credit') {
            $lines[] = ['entry_side' => 'credit', 'line_role' => $ppvRole, 'account_id' => $ppv];
        }
        $policies = app(AccountingPostingPolicyService::class);
        $id = $policies->create($company, $maker, [
            'policy_key' => $event, 'source_type' => 'supplier_credit', 'business_event' => $event,
            'classification' => 'supplier_credit', 'version' => 1, 'currency' => 'VND', 'effective_from' => '2026-01-01', 'lines' => $lines,
        ]);
        $policies->submit($company, $id, $maker);
        $policies->approve($company, $id, $checker);

        $billLoss = $ppvSide === 'credit';
        $billEvent = $billLoss ? 'INVENTORY_VENDOR_BILL_PRICE_VARIANCE_LOSS' : 'INVENTORY_VENDOR_BILL_PRICE_VARIANCE_GAIN';
        $billLines = [
            ['entry_side' => 'debit', 'line_role' => 'GRNI', 'account_id' => $grni],
            ['entry_side' => 'credit', 'line_role' => 'AP', 'account_id' => $ap],
            ['entry_side' => $billLoss ? 'debit' : 'credit', 'line_role' => $billLoss ? 'PURCHASE_PRICE_VARIANCE_LOSS' : 'PURCHASE_PRICE_VARIANCE_GAIN', 'account_id' => $billLoss ? $loss : $gain],
        ];
        $billPolicy = $policies->create($company, $maker, [
            'policy_key' => $billEvent, 'source_type' => 'vendor_bill', 'business_event' => $billEvent,
            'classification' => 'inventory', 'version' => 1, 'currency' => 'VND', 'effective_from' => '2026-01-01', 'lines' => $billLines,
        ]);
        $policies->submit($company, $billPolicy, $maker);
        $policies->approve($company, $billPolicy, $checker);
    }

    private function configureDatedSpotRate(int $company, User $maker, User $checker, string $currency, string $rate, string $from, string $to, string $suffix): void
    {
        $rates = app(FxRateService::class);
        $id = $rates->create($company, $maker, [
            'base_currency' => $currency, 'quote_currency' => 'VND', 'rate_decimal' => $rate,
            'rate_date' => $from, 'rate_type' => 'spot', 'source_reference' => 'TEST-'.$currency.'-'.$suffix,
            'effective_from' => $from, 'effective_to' => $to, 'idempotency_key' => 'test-rate-'.strtolower($currency).'-'.$suffix,
        ]);
        $rates->transition($company, $id, 'submit', $maker);
        $rates->transition($company, $id, 'approve', $checker);
        $rates->transition($company, $id, 'activate', $checker);
    }

    private function foreignTreasuryAccount(int $company, int $branch, User $maker, string $code, string $currency = 'USD'): int
    {
        $cashLedger = (int) DB::table('accounting_accounts')->where(['company_id' => $company, 'code' => '1100'])->value('id');
        DB::table('accounting_accounts')->where('id', $cashLedger)->update(['currency' => null]);
        $account = app(TreasuryService::class)->mutate('account.create', $company, $maker, [
            'branch_id' => $branch, 'accounting_account_id' => $cashLedger, 'code' => $code,
            'name' => 'Foreign settlement account', 'type' => 'bank',
        ]);
        DB::table('treasury_financial_accounts')->where('id', $account)->update(['currency' => $currency]);

        return $account;
    }

    private function configureSpotRate(int $company, User $maker, User $checker, string $currency, string $rate): void
    {
        $rates = app(FxRateService::class);
        $id = $rates->create($company, $maker, [
            'base_currency' => $currency,
            'quote_currency' => 'VND',
            'rate_decimal' => $rate,
            'rate_date' => '2026-09-01',
            'rate_type' => 'spot',
            'source_reference' => 'TEST-'.$currency.'-VND',
            'effective_from' => '2026-09-01',
            'effective_to' => '2026-09-30',
            'idempotency_key' => 'test-rate-'.strtolower($currency),
        ]);
        $rates->transition($company, $id, 'submit', $maker);
        $rates->transition($company, $id, 'approve', $checker);
        $rates->transition($company, $id, 'activate', $checker);
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
}
