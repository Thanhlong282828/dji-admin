<?php

namespace Tests\Concurrency\Inventory;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Company\Domain\Models\Company;
use Modules\Finance\Application\Services\AccountingPostingPolicyService;
use Modules\Finance\Application\Services\FxRateService;
use Modules\Finance\Application\Services\SourceAccountingPostingService;
use Modules\Finance\Application\Services\TreasuryService;
use Modules\Procurement\Application\Services\GoodsReturnService;
use Modules\Procurement\Application\Services\ProcurementService;
use Modules\Procurement\Application\Services\SupplierCreditService;
use Modules\Procurement\Application\Services\VendorRefundService;
use PHPUnit\Framework\Attributes\Group;
use Tests\Support\MysqlProcessRaceRunner;
use Tests\TestCase;

#[Group('mysql-concurrency')]
final class InventoryFxSupplierCreditProcessConcurrencyTest extends TestCase
{
    public function test_supplier_credit_and_vendor_refunds_are_exactly_once_under_real_process_races(): void
    {
        self::assertSame('schema_admin_testing', DB::connection()->getDatabaseName());
        $fixture = $this->fixture();

        try {
            $creditPayload = [
                'company_id' => $fixture['company']->id,
                'supplier_credit_id' => $fixture['credit'],
                'actor_id' => $fixture['checker']->id,
            ];
            $approveRace = (new MysqlProcessRaceRunner)->run('inventory_fx_supplier_credit_approve', $creditPayload, $creditPayload);
            self::assertSame([0, 0], $approveRace['exit_codes'], implode("\n", $approveRace['outputs']));
            self::assertSame('approved', DB::table('supplier_credit_notes')->where('id', $fixture['credit'])->value('status'));
            $creditRace = (new MysqlProcessRaceRunner)->run('inventory_fx_supplier_credit_post', $creditPayload, $creditPayload);
            self::assertSame([0, 0], $creditRace['exit_codes'], implode("\n", $creditRace['outputs']));
            self::assertSame(1, DB::table('supplier_credit_fx_evidence')->where('supplier_credit_note_id', $fixture['credit'])->count());
            self::assertSame(1, DB::table('inventory_return_fx_reversals')->where('goods_return_id', $fixture['return'])->count());
            self::assertSame(1, DB::table('inventory_movements')->where('source_type', 'procurement_goods_return_line')->where('source_id', $fixture['returnLine'])->count());
            self::assertSame(1, DB::table('payroll_accounting_journals')->where(['source_type' => 'supplier_credit', 'source_id' => $fixture['credit']])->count());
            $receivable = DB::table('vendor_refund_receivables')->where('supplier_credit_note_id', $fixture['credit'])->firstOrFail();
            self::assertSame(400, (int) $receivable->original_minor);

            $refunds = app(VendorRefundService::class);
            $same = $this->approvedRefund($refunds, $fixture, (int) $receivable->id, 100, 'SAME');
            $samePayload = [
                'company_id' => $fixture['company']->id,
                'supplier_refund_receipt_id' => $same,
                'actor_id' => $fixture['checker']->id,
            ];
            $sameRace = (new MysqlProcessRaceRunner)->run('inventory_fx_vendor_refund_post', $samePayload, $samePayload);
            self::assertSame([0, 0], $sameRace['exit_codes'], implode("\n", $sameRace['outputs']));
            self::assertSame(1, DB::table('supplier_refund_fx_settlements')->where('supplier_refund_receipt_id', $same)->count());
            self::assertSame(1, DB::table('treasury_transactions')->where(['source_type' => 'supplier_refund_receipt', 'source_id' => $same])->count());
            self::assertSame(1, DB::table('payroll_accounting_journals')->where(['source_type' => 'supplier_refund_receipt', 'source_id' => $same])->count());

            $partialA = $this->approvedRefund($refunds, $fixture, (int) $receivable->id, 150, 'A');
            $partialB = $this->approvedRefund($refunds, $fixture, (int) $receivable->id, 150, 'B');
            $partialRace = (new MysqlProcessRaceRunner)->run(
                'inventory_fx_vendor_refund_post',
                ['company_id' => $fixture['company']->id, 'supplier_refund_receipt_id' => $partialA, 'actor_id' => $fixture['checker']->id],
                ['company_id' => $fixture['company']->id, 'supplier_refund_receipt_id' => $partialB, 'actor_id' => $fixture['checker']->id],
            );
            self::assertSame([0, 0], $partialRace['exit_codes'], implode("\n", $partialRace['outputs']));
            self::assertSame(400, (int) DB::table('vendor_refund_receivables')->where('id', $receivable->id)->value('refunded_minor'));
            self::assertSame('refunded', DB::table('vendor_refund_receivables')->where('id', $receivable->id)->value('status'));
            self::assertSame(3, DB::table('supplier_refund_fx_settlements')->where('vendor_refund_receivable_id', $receivable->id)->count());
            self::assertSame(3, DB::table('finance_fx_rate_snapshots')->where(['company_id' => $fixture['company']->id, 'source_type' => 'supplier_refund_receipt_settlement'])->count());
            self::assertSame(3, DB::table('treasury_transactions')->where(['company_id' => $fixture['company']->id, 'source_type' => 'supplier_refund_receipt'])->count());
            self::assertSame(3, DB::table('payroll_accounting_journals')->where(['company_id' => $fixture['company']->id, 'source_type' => 'supplier_refund_receipt'])->count());
            self::assertSame(100_000, (int) DB::table('supplier_refund_fx_settlements')->where('vendor_refund_receivable_id', $receivable->id)->sum('released_carrying_minor'));
            self::assertSame(0, (int) DB::table('supplier_refund_fx_settlements')->where('vendor_refund_receivable_id', $receivable->id)->sum('realized_fx_minor'));
        } finally {
            $this->cleanup($fixture);
        }
    }

    /** @return array<string,mixed> */
    private function fixture(): array
    {
        $token = Str::lower(Str::random(10));
        $maker = User::factory()->create(['email' => "ifx2-maker-{$token}@example.test"]);
        $checker = User::factory()->create(['email' => "ifx2-checker-{$token}@example.test"]);
        $company = Company::factory()->create(['code' => 'IFX2-'.$token, 'currency_code' => 'VND', 'functional_currency_code' => 'VND']);
        $branch = (int) DB::table('branches')->insertGetId(['company_id' => $company->id, 'code' => 'HQ-'.$token, 'name' => 'Inventory FX2 HQ', 'status' => 'active', 'timezone' => 'Asia/Ho_Chi_Minh', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('company_user_access')->insert([['company_id' => $company->id, 'user_id' => $maker->id, 'created_at' => now(), 'updated_at' => now()], ['company_id' => $company->id, 'user_id' => $checker->id, 'created_at' => now(), 'updated_at' => now()]]);
        $profile = (int) DB::table('seller_profiles')->insertGetId(['company_id' => $company->id, 'user_id' => $maker->id, 'display_name' => 'Inventory FX2', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
        $store = (int) DB::table('stores')->insertGetId(['seller_profile_id' => $profile, 'name' => 'Inventory FX2 Store', 'slug' => 'inventory-fx2-'.$token, 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
        $product = (int) DB::table('products')->insertGetId(['store_id' => $store, 'code' => 'IFX2-'.$token, 'name' => 'Imported stock', 'slug' => 'imported-fx2-'.$token, 'base_price_minor' => 200, 'currency' => 'USD', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
        $variant = (int) DB::table('product_variants')->insertGetId(['product_id' => $product, 'sku' => 'IFX2-'.$token, 'name' => 'Default', 'price_minor' => 200, 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
        $location = (int) DB::table('inventory_locations')->insertGetId(['store_id' => $store, 'branch_id' => $branch, 'code' => 'IFX2-'.$token, 'name' => 'Inventory FX2 Location', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('inventory_balances')->insert(['product_variant_id' => $variant, 'inventory_location_id' => $location, 'on_hand' => 0, 'reserved' => 0, 'inventory_value_minor' => 0, 'average_unit_cost_minor' => 0, 'version' => 1, 'created_at' => now(), 'updated_at' => now()]);

        $posting = app(SourceAccountingPostingService::class);
        $inventory = $posting->accountEvidence($company->id, '1200', 'VND', $maker->id)['id'];
        $grni = $posting->accountEvidence($company->id, '2150', 'VND', $maker->id)['id'];
        $ap = $posting->accountEvidence($company->id, '2100', 'VND', $maker->id)['id'];
        $cash = $posting->accountEvidence($company->id, '1100', 'VND', $maker->id)['id'];
        $receivable = $posting->accountEvidence($company->id, '1150', 'VND', $maker->id)['id'];
        foreach ([
            ['INVENTORY_RECEIPT', 'goods_receipt', 'inventory', [['debit', 'INVENTORY_ASSET', $inventory], ['credit', 'GRNI', $grni]]],
            ['GOODS_RETURN_TO_VENDOR', 'goods_return', null, [['debit', 'GRNI', $grni], ['credit', 'INVENTORY_ASSET', $inventory]]],
            ['INVENTORY_VENDOR_BILL_POSTED', 'vendor_bill', 'inventory', [['debit', 'GRNI', $grni], ['credit', 'AP', $ap]]],
            ['VENDOR_PAYMENT', 'vendor_payment', null, [['debit', 'AP', $ap], ['credit', 'CASH', $cash]]],
            ['SUPPLIER_CREDIT_PAID_PORTION', 'supplier_refund', 'vendor_refund', [['debit', 'RECEIVABLE', $receivable], ['credit', 'GRNI', $grni]]],
            ['SUPPLIER_REFUND_RECEIVED', 'vendor_refund', 'vendor_refund', [['debit', 'CASH', $cash], ['credit', 'RECEIVABLE', $receivable]]],
        ] as [$event, $source, $classification, $definitions]) {
            $lines = array_map(static fn (array $line): array => ['entry_side' => $line[0], 'line_role' => $line[1], 'account_id' => $line[2]], $definitions);
            $this->policy($company->id, $maker, $checker, $event, $source, $classification, $lines);
        }
        $rates = app(FxRateService::class);
        $rate = $rates->create($company->id, $maker, ['base_currency' => 'USD', 'quote_currency' => 'VND', 'rate_decimal' => '25000.000000000000', 'rate_date' => '2026-09-01', 'rate_type' => 'spot', 'source_reference' => 'IFX2-'.$token, 'effective_from' => '2026-09-01', 'effective_to' => '2026-09-30', 'idempotency_key' => 'ifx2-rate-'.$token]);
        $rates->transition($company->id, $rate, 'submit', $maker);
        $rates->transition($company->id, $rate, 'approve', $checker);
        $rates->transition($company->id, $rate, 'activate', $checker);

        DB::table('accounting_accounts')->where('id', $cash)->update(['currency' => null]);
        $usdAccount = app(TreasuryService::class)->mutate('account.create', $company->id, $maker, ['branch_id' => $branch, 'accounting_account_id' => $cash, 'code' => 'USD-'.$token, 'name' => 'USD settlement', 'type' => 'bank']);
        DB::table('treasury_financial_accounts')->where('id', $usdAccount)->update(['currency' => 'USD']);

        $service = app(ProcurementService::class);
        $vendor = $service->mutate('vendor.create', $company->id, $maker, ['code' => 'V-'.$token, 'name' => 'Foreign vendor']);
        $request = $service->mutate('request.create', $company->id, $maker, ['branch_id' => $branch, 'reference' => 'PR-'.$token, 'requested_date' => '2026-09-01', 'reason' => 'Inventory FX2 race', 'product_variant_id' => $variant, 'description' => 'Imported stock', 'quantity' => 5, 'estimated_unit_cost_minor' => 200]);
        $service->mutate('request.transition', $company->id, $maker, ['id' => $request, 'to' => 'submitted']);
        $service->mutate('request.transition', $company->id, $checker, ['id' => $request, 'to' => 'approved']);
        $order = $service->mutate('order.create', $company->id, $maker, ['purchase_request_id' => $request, 'vendor_id' => $vendor, 'reference' => 'PO-'.$token, 'currency' => 'USD', 'order_date' => '2026-09-02']);
        $service->mutate('order.transition', $company->id, $checker, ['id' => $order, 'to' => 'approved']);
        $orderLine = (int) DB::table('purchase_order_lines')->where('purchase_order_id', $order)->value('id');
        $receipt = $service->mutate('receipt.create', $company->id, $maker, ['purchase_order_id' => $order, 'purchase_order_line_id' => $orderLine, 'inventory_location_id' => $location, 'reference' => 'GR-'.$token, 'receipt_date' => '2026-09-10', 'quantity' => 5]);
        $service->mutate('receipt.receive', $company->id, $checker, ['id' => $receipt]);
        $receiptLine = (int) DB::table('procurement_goods_receipt_lines')->where('goods_receipt_id', $receipt)->value('id');
        $bill = $service->mutate('bill.create', $company->id, $maker, ['purchase_order_id' => $order, 'purchase_order_line_id' => $orderLine, 'goods_receipt_line_id' => $receiptLine, 'quantity' => 2, 'invoice_number' => 'INV-'.$token, 'invoice_date' => '2026-09-15', 'due_date' => '2026-10-15']);
        $service->mutate('bill.approve', $company->id, $checker, ['id' => $bill]);
        $payment = $service->mutate('payment.allocate', $company->id, $checker, ['vendor_bill_id' => $bill, 'financial_account_id' => $usdAccount, 'payment_date' => '2026-09-16', 'amount_minor' => 400, 'method' => 'bank_transfer', 'reference' => 'PAY-'.$token]);
        $returns = app(GoodsReturnService::class);
        $return = $returns->create($company->id, $maker, ['goods_receipt_line_id' => $receiptLine, 'quantity' => 2, 'return_number' => 'RTV-'.$token, 'return_date' => '2026-09-20', 'reason' => 'Foreign return race', 'idempotency_key' => 'rtv-'.$token]);
        $returns->submit($company->id, $return, $maker);
        $returns->approve($company->id, $return, $checker);
        $returnLine = (int) DB::table('procurement_goods_return_lines')->where('goods_return_id', $return)->value('id');
        $allocation = (int) DB::table('vendor_bill_receipt_allocations')->whereIn('vendor_bill_line_id', DB::table('vendor_bill_lines')->where('vendor_bill_id', $bill)->select('id'))->value('id');
        $credits = app(SupplierCreditService::class);
        $credit = $credits->create($company->id, $maker, ['goods_return_line_id' => $returnLine, 'vendor_bill_receipt_allocation_id' => $allocation, 'quantity' => 2, 'credit_number' => 'SCN-'.$token, 'credit_date' => '2026-09-20', 'reason' => 'Foreign credit race', 'idempotency_key' => 'scn-'.$token]);
        $credits->submit($company->id, $credit, $maker);

        return compact('token', 'maker', 'checker', 'company', 'branch', 'profile', 'store', 'product', 'variant', 'location', 'vendor', 'request', 'order', 'orderLine', 'receipt', 'receiptLine', 'bill', 'payment', 'return', 'returnLine', 'credit', 'usdAccount');
    }

    /** @param list<array{entry_side:string,line_role:string,account_id:int}> $lines */
    private function policy(int $company, User $maker, User $checker, string $event, string $source, ?string $classification, array $lines): void
    {
        $service = app(AccountingPostingPolicyService::class);
        $id = $service->create($company, $maker, ['policy_key' => $event.'-'.Str::lower(Str::random(6)), 'source_type' => $source, 'business_event' => $event, 'classification' => $classification, 'version' => 1, 'currency' => 'VND', 'effective_from' => '2026-01-01', 'lines' => $lines]);
        $service->submit($company, $id, $maker);
        $service->approve($company, $id, $checker);
    }

    /** @param array<string,mixed> $fixture */
    private function approvedRefund(VendorRefundService $service, array $fixture, int $receivableId, int $amount, string $suffix): int
    {
        $id = $service->create($fixture['company']->id, $fixture['maker'], ['vendor_refund_receivable_id' => $receivableId, 'financial_account_id' => $fixture['usdAccount'], 'reference' => 'REFUND-'.$suffix.'-'.$fixture['token'], 'refund_date' => '2026-09-21', 'amount_minor' => $amount, 'idempotency_key' => 'refund-'.strtolower($suffix).'-'.$fixture['token']]);
        $service->submit($fixture['company']->id, $id, $fixture['maker']);
        $service->approve($fixture['company']->id, $id, $fixture['checker']);

        return $id;
    }

    /** @param array<string,mixed> $fixture */
    private function cleanup(array $fixture): void
    {
        $company = $fixture['company']->id;
        $journals = DB::table('payroll_accounting_journals')->where('company_id', $company)->pluck('id');
        $payments = DB::table('ap_payments')->where('company_id', $company)->pluck('id');
        $paymentAllocations = DB::table('ap_payment_allocations')->whereIn('payment_id', $payments)->pluck('id');
        $bills = DB::table('vendor_bills')->where('company_id', $company)->pluck('id');
        $billLines = DB::table('vendor_bill_lines')->whereIn('vendor_bill_id', $bills)->pluck('id');
        $returns = DB::table('procurement_goods_returns')->where('company_id', $company)->pluck('id');
        $credits = DB::table('supplier_credit_notes')->where('company_id', $company)->pluck('id');
        DB::table('supplier_refund_fx_settlements')->where('company_id', $company)->delete();
        DB::table('supplier_refund_receipts')->where('company_id', $company)->delete();
        DB::table('vendor_refund_receivables')->where('company_id', $company)->delete();
        DB::table('supplier_credit_fx_evidence')->where('company_id', $company)->delete();
        DB::table('supplier_credit_note_lines')->whereIn('supplier_credit_note_id', $credits)->delete();
        DB::table('supplier_credit_notes')->whereIn('id', $credits)->delete();
        DB::table('inventory_return_fx_reversals')->where('company_id', $company)->delete();
        DB::table('procurement_goods_return_lines')->whereIn('goods_return_id', $returns)->delete();
        DB::table('procurement_goods_returns')->whereIn('id', $returns)->delete();
        DB::table('supplier_payment_fx_settlements')->whereIn('ap_payment_allocation_id', $paymentAllocations)->delete();
        DB::table('ap_payment_allocations')->whereIn('id', $paymentAllocations)->delete();
        DB::table('ap_payments')->whereIn('id', $payments)->delete();
        DB::table('vendor_bill_receipt_fx_matches')->where('company_id', $company)->delete();
        DB::table('supplier_invoice_fx_recognitions')->whereIn('vendor_bill_id', $bills)->delete();
        DB::table('vendor_bill_receipt_allocations')->whereIn('vendor_bill_line_id', $billLines)->delete();
        DB::table('vendor_bill_lines')->whereIn('id', $billLines)->delete();
        DB::table('vendor_bills')->whereIn('id', $bills)->delete();
        DB::table('inventory_receipt_fx_valuations')->where('company_id', $company)->delete();
        DB::table('finance_fx_rate_snapshots')->where('company_id', $company)->delete();
        DB::table('treasury_transactions')->where('company_id', $company)->delete();
        DB::table('accounting_journal_source_evidence')->whereIn('journal_id', $journals)->delete();
        DB::table('payroll_accounting_journal_lines')->whereIn('journal_id', $journals)->delete();
        DB::table('payroll_accounting_journals')->whereIn('id', $journals)->delete();
        DB::table('procurement_goods_receipt_lines')->where('goods_receipt_id', $fixture['receipt'])->update(['inventory_movement_id' => null]);
        DB::table('inventory_movements')->where('product_variant_id', $fixture['variant'])->delete();
        DB::table('procurement_goods_receipt_lines')->where('goods_receipt_id', $fixture['receipt'])->delete();
        DB::table('procurement_goods_receipts')->where('id', $fixture['receipt'])->delete();
        DB::table('treasury_financial_accounts')->where('company_id', $company)->delete();
        DB::table('accounting_posting_policy_lines')->whereIn('accounting_posting_policy_id', DB::table('accounting_posting_policies')->where('company_id', $company)->select('id'))->delete();
        DB::table('accounting_posting_policies')->where('company_id', $company)->delete();
        DB::table('accounting_periods')->where('company_id', $company)->delete();
        DB::table('finance_fx_rates')->where('company_id', $company)->delete();
        DB::table('purchase_order_lines')->where('purchase_order_id', $fixture['order'])->delete();
        DB::table('purchase_orders')->where('id', $fixture['order'])->delete();
        DB::table('purchase_request_lines')->where('purchase_request_id', $fixture['request'])->delete();
        DB::table('purchase_requests')->where('id', $fixture['request'])->delete();
        DB::table('procurement_exception_cases')->where('company_id', $company)->delete();
        DB::table('procurement_vendors')->where('id', $fixture['vendor'])->delete();
        DB::table('inventory_balances')->where(['product_variant_id' => $fixture['variant'], 'inventory_location_id' => $fixture['location']])->delete();
        DB::table('inventory_locations')->where('id', $fixture['location'])->delete();
        DB::table('product_variants')->where('id', $fixture['variant'])->delete();
        DB::table('products')->where('id', $fixture['product'])->delete();
        DB::table('stores')->where('id', $fixture['store'])->delete();
        DB::table('seller_profiles')->where('id', $fixture['profile'])->delete();
        DB::table('accounting_accounts')->where('company_id', $company)->delete();
        DB::table('company_user_access')->where('company_id', $company)->delete();
        DB::table('branches')->where('id', $fixture['branch'])->delete();
        $fixture['company']->delete();
        $fixture['maker']->forceDelete();
        $fixture['checker']->forceDelete();
    }
}
