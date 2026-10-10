<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Database\RecordNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Modules\Finance\Application\Services\AccountingPostingPolicyService;
use Modules\Finance\Application\Services\AccountsPayablePlanningReadService;
use Modules\Finance\Application\Services\CostCenterCostManagementReadService;
use Modules\Finance\Application\Services\SourceAccountingPostingService;
use Modules\Finance\Application\Services\TreasuryService;
use Modules\Procurement\Application\Services\GoodsReturnService;
use Modules\Procurement\Application\Services\ProcurementReconciliationReadService;
use Modules\Procurement\Application\Services\ProcurementService;
use Modules\Procurement\Application\Services\SupplierCreditService;
use Modules\Procurement\Application\Services\VendorPerformanceReadService;
use Modules\Procurement\Application\Services\VendorRefundConfigurationReadinessService;
use Modules\Procurement\Application\Services\VendorRefundService;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

final class GoodsReturnToVendorTest extends TestCase
{
    use RefreshDatabase;

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

        $this->actingAs($maker)->get(route('admin.procurement.goods-returns', ['company_id' => $company]))->assertForbidden();
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

        $this->actingAs($maker)->get($route)->assertOk()->assertSee('Supplier Credits')->assertSee('Tạo Supplier Credit');
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

    /** @return array{as_of_date:string,vendor_id:null,branch_id:null,cost_center_id:null,due_bucket:null,payment_status:null,currency:string,sort:string,direction:string} */
    private function apFilters(string $date): array
    {
        return ['as_of_date' => $date, 'vendor_id' => null, 'branch_id' => null, 'cost_center_id' => null, 'due_bucket' => null, 'payment_status' => null, 'currency' => 'VND', 'sort' => 'due_date', 'direction' => 'asc'];
    }

    /** @return array{int,int,int,int} */
    private function receivedGoods(User $maker, User $checker, int $company, int $branch, int $variant, int $location): array
    {
        $service = app(ProcurementService::class);
        $vendor = $service->mutate('vendor.create', $company, $maker, ['code' => 'RTV-V', 'name' => 'Return Vendor']);
        $costCenter = (int) DB::table('cost_centers')->insertGetId(['company_id' => $company, 'code' => 'RTV-CC', 'name' => 'RTV Cost Center', 'created_at' => now(), 'updated_at' => now()]);
        $request = $service->mutate('request.create', $company, $maker, ['branch_id' => $branch, 'cost_center_id' => $costCenter, 'reference' => 'PR-RTV', 'requested_date' => '2026-09-01', 'reason' => 'Stock', 'product_variant_id' => $variant, 'description' => 'Returnable goods', 'quantity' => 5, 'estimated_unit_cost_minor' => 200]);
        $service->mutate('request.transition', $company, $maker, ['id' => $request, 'to' => 'submitted']);
        $service->mutate('request.transition', $company, $checker, ['id' => $request, 'to' => 'approved']);
        $order = $service->mutate('order.create', $company, $maker, ['purchase_request_id' => $request, 'vendor_id' => $vendor, 'reference' => 'PO-RTV', 'order_date' => '2026-09-02']);
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
