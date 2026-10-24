<?php

namespace Tests\Concurrency\Inventory;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Company\Domain\Models\Company;
use Modules\Finance\Application\Services\AccountingPostingPolicyService;
use Modules\Finance\Application\Services\FxRateService;
use Modules\Finance\Application\Services\SourceAccountingPostingService;
use Modules\Procurement\Application\Services\GoodsReturnService;
use Modules\Procurement\Application\Services\ProcurementService;
use PHPUnit\Framework\Attributes\Group;
use Tests\Support\MysqlProcessRaceRunner;
use Tests\TestCase;

#[Group('mysql-concurrency')]
final class InventoryFxReceiptProcessConcurrencyTest extends TestCase
{
    public function test_two_workers_receive_one_foreign_receipt_exactly_once(): void
    {
        self::assertSame('schema_admin_testing', DB::connection()->getDatabaseName());
        $fixture = $this->fixture();

        try {
            $payload = ['company_id' => $fixture['company']->id, 'receipt_id' => $fixture['receipt'], 'actor_id' => $fixture['checker']->id];
            $race = (new MysqlProcessRaceRunner)->run('inventory_fx_receipt_receive', $payload, $payload);

            self::assertSame([0, 0], $race['exit_codes'], implode("\n", $race['outputs']));
            self::assertSame('received', DB::table('procurement_goods_receipts')->where('id', $fixture['receipt'])->value('status'));
            self::assertSame(1, DB::table('inventory_movements')->where(['source_type' => 'procurement_goods_receipt_line', 'source_id' => $fixture['receiptLine']])->count());
            self::assertSame(1, DB::table('inventory_receipt_fx_valuations')->where('goods_receipt_line_id', $fixture['receiptLine'])->count());
            self::assertSame(1, DB::table('finance_fx_rate_snapshots')->where(['source_type' => 'inventory_receipt_valuation', 'source_id' => $fixture['receiptLine']])->count());
            self::assertSame(1, DB::table('payroll_accounting_journals')->where(['source_type' => 'goods_receipt', 'source_id' => $fixture['receipt']])->count());
            self::assertSame(2, (int) DB::table('inventory_balances')->where(['product_variant_id' => $fixture['variant'], 'inventory_location_id' => $fixture['location']])->value('on_hand'));
            self::assertSame(4_938_100, (int) DB::table('inventory_balances')->where(['product_variant_id' => $fixture['variant'], 'inventory_location_id' => $fixture['location']])->value('inventory_value_minor'));

            app(ProcurementService::class)->mutate('receipt.receive', $fixture['company']->id, $fixture['checker'], ['id' => $fixture['receipt']]);
            self::assertSame(1, DB::table('inventory_receipt_fx_valuations')->where('goods_receipt_line_id', $fixture['receiptLine'])->count());
        } finally {
            $this->cleanup($fixture);
        }
    }

    public function test_bill_creation_racing_receipt_never_consumes_uncommitted_evidence(): void
    {
        self::assertSame('schema_admin_testing', DB::connection()->getDatabaseName());
        $fixture = $this->fixture();

        try {
            $receive = ['company_id' => $fixture['company']->id, 'receipt_id' => $fixture['receipt'], 'actor_id' => $fixture['checker']->id];
            $bill = ['company_id' => $fixture['company']->id, 'actor_id' => $fixture['maker']->id, 'purchase_order_id' => $fixture['order'], 'purchase_order_line_id' => $fixture['orderLine'], 'goods_receipt_line_id' => $fixture['receiptLine'], 'quantity' => 2, 'invoice_number' => 'VB-RACE-'.$fixture['token'], 'invoice_date' => '2026-10-04', 'due_date' => '2026-11-04'];
            $race = (new MysqlProcessRaceRunner)->run('inventory_fx_receipt_receive', $receive, $bill, 'inventory_fx_bill_create');

            self::assertContains($race['exit_codes'], [[0, 0], [0, 2]], implode("\n", $race['outputs']));
            self::assertSame(1, DB::table('inventory_receipt_fx_valuations')->where('goods_receipt_line_id', $fixture['receiptLine'])->count());
            self::assertSame(1, DB::table('payroll_accounting_journals')->where(['source_type' => 'goods_receipt', 'source_id' => $fixture['receipt']])->count());
            $billId = DB::table('vendor_bills')->where(['company_id' => $fixture['company']->id, 'invoice_number' => $bill['invoice_number']])->value('id');
            if ($billId === null) {
                self::assertSame(0, DB::table('vendor_bill_receipt_allocations')->where('goods_receipt_line_id', $fixture['receiptLine'])->count());
            } else {
                self::assertSame(1, DB::table('vendor_bill_receipt_allocations')->where('goods_receipt_line_id', $fixture['receiptLine'])->count());
            }
            self::assertSame(0, DB::table('vendor_bill_receipt_fx_matches')->where('inventory_receipt_fx_valuation_id', DB::table('inventory_receipt_fx_valuations')->where('goods_receipt_line_id', $fixture['receiptLine'])->value('id'))->count());
        } finally {
            $this->cleanup($fixture);
        }
    }

    public function test_two_partial_goods_returns_cannot_over_return_one_foreign_receipt(): void
    {
        self::assertSame('schema_admin_testing', DB::connection()->getDatabaseName());
        $fixture = $this->fixture(5);

        try {
            app(ProcurementService::class)->mutate('receipt.receive', $fixture['company']->id, $fixture['checker'], ['id' => $fixture['receipt']]);
            $returns = app(GoodsReturnService::class);
            $ids = [];
            foreach (['A', 'B'] as $suffix) {
                $id = $returns->create($fixture['company']->id, $fixture['maker'], [
                    'goods_receipt_line_id' => $fixture['receiptLine'], 'quantity' => 3,
                    'return_number' => 'RTV-RACE-'.$suffix.'-'.$fixture['token'], 'return_date' => '2026-10-03',
                    'reason' => 'Concurrent partial foreign return', 'idempotency_key' => 'rtv-race-'.strtolower($suffix).'-'.$fixture['token'],
                ]);
                $returns->submit($fixture['company']->id, $id, $fixture['maker']);
                $returns->approve($fixture['company']->id, $id, $fixture['checker']);
                $ids[] = $id;
            }
            $race = (new MysqlProcessRaceRunner)->run(
                'inventory_fx_goods_return_post',
                ['company_id' => $fixture['company']->id, 'goods_return_id' => $ids[0], 'actor_id' => $fixture['checker']->id],
                ['company_id' => $fixture['company']->id, 'goods_return_id' => $ids[1], 'actor_id' => $fixture['checker']->id],
            );
            $exitCodes = $race['exit_codes'];
            sort($exitCodes);
            self::assertSame([0, 2], $exitCodes, implode("\n", $race['outputs']));
            self::assertSame(1, DB::table('procurement_goods_returns')->whereIn('id', $ids)->where('status', 'posted')->count());
            self::assertSame(1, DB::table('procurement_goods_returns')->whereIn('id', $ids)->where('status', 'approved')->count());
            $lines = DB::table('procurement_goods_return_lines')->whereIn('goods_return_id', $ids)->pluck('id');
            self::assertSame(1, DB::table('inventory_movements')->where('source_type', 'procurement_goods_return_line')->whereIn('source_id', $lines)->count());
            self::assertSame(1, DB::table('inventory_return_fx_reversals')->whereIn('goods_return_id', $ids)->count());
            self::assertSame(1, DB::table('payroll_accounting_journals')->where('source_type', 'goods_return')->whereIn('source_id', $ids)->count());
            self::assertSame(2, (int) DB::table('inventory_balances')->where(['product_variant_id' => $fixture['variant'], 'inventory_location_id' => $fixture['location']])->value('on_hand'));
            self::assertSame(2, (int) DB::table('purchase_order_lines')->where('id', $fixture['orderLine'])->value('received_quantity'));
            self::assertSame(7_407_150, (int) DB::table('inventory_return_fx_reversals')->whereIn('goods_return_id', $ids)->sum('released_functional_basis_minor'));
        } finally {
            $this->cleanup($fixture);
        }
    }

    public function test_goods_return_racing_vendor_bill_approval_fails_closed_without_orphan_return_effect(): void
    {
        self::assertSame('schema_admin_testing', DB::connection()->getDatabaseName());
        $fixture = $this->fixture(5);

        try {
            $service = app(ProcurementService::class);
            $service->mutate('receipt.receive', $fixture['company']->id, $fixture['checker'], ['id' => $fixture['receipt']]);
            $returns = app(GoodsReturnService::class);
            $return = $returns->create($fixture['company']->id, $fixture['maker'], [
                'goods_receipt_line_id' => $fixture['receiptLine'], 'quantity' => 2,
                'return_number' => 'RTV-BILL-RACE-'.$fixture['token'], 'return_date' => '2026-10-04',
                'reason' => 'Return races vendor bill approval', 'idempotency_key' => 'rtv-bill-race-'.$fixture['token'],
            ]);
            $returns->submit($fixture['company']->id, $return, $fixture['maker']);
            $returns->approve($fixture['company']->id, $return, $fixture['checker']);
            $bill = $service->mutate('bill.create', $fixture['company']->id, $fixture['maker'], [
                'purchase_order_id' => $fixture['order'], 'purchase_order_line_id' => $fixture['orderLine'],
                'goods_receipt_line_id' => $fixture['receiptLine'], 'quantity' => 2,
                'invoice_number' => 'VB-RETURN-RACE-'.$fixture['token'], 'invoice_date' => '2026-10-04', 'due_date' => '2026-11-04',
            ]);
            $race = (new MysqlProcessRaceRunner)->run(
                'inventory_fx_goods_return_post',
                ['company_id' => $fixture['company']->id, 'goods_return_id' => $return, 'actor_id' => $fixture['checker']->id],
                ['company_id' => $fixture['company']->id, 'bill_id' => $bill, 'actor_id' => $fixture['checker']->id],
                'ap_fx_bill_approve',
            );
            $exitCodes = $race['exit_codes'];
            sort($exitCodes);
            self::assertSame([0, 2], $exitCodes, implode("\n", $race['outputs']));
            self::assertSame('approved', DB::table('vendor_bills')->where('id', $bill)->value('status'));
            self::assertSame('approved', DB::table('procurement_goods_returns')->where('id', $return)->value('status'));
            self::assertSame(0, DB::table('inventory_movements')->where('source_type', 'procurement_goods_return_line')->whereIn('source_id', DB::table('procurement_goods_return_lines')->where('goods_return_id', $return)->select('id'))->count());
            self::assertSame(0, DB::table('inventory_return_fx_reversals')->where('goods_return_id', $return)->count());
            self::assertSame(1, DB::table('supplier_invoice_fx_recognitions')->where('vendor_bill_id', $bill)->count());
            self::assertSame(1, DB::table('vendor_bill_receipt_fx_matches')->where('vendor_bill_id', $bill)->count());
        } finally {
            $this->cleanup($fixture);
        }
    }

    /** @return array<string,mixed> */
    private function fixture(int $quantity = 2): array
    {
        $token = Str::lower(Str::random(10));
        $maker = User::factory()->create(['email' => "inv-fx-maker-{$token}@example.test"]);
        $checker = User::factory()->create(['email' => "inv-fx-checker-{$token}@example.test"]);
        $company = Company::factory()->create(['code' => 'IFX-'.$token, 'currency_code' => 'VND', 'functional_currency_code' => 'VND']);
        $branch = (int) DB::table('branches')->insertGetId(['company_id' => $company->id, 'code' => 'HQ-'.$token, 'name' => 'Inventory FX HQ', 'status' => 'active', 'timezone' => 'Asia/Ho_Chi_Minh', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('company_user_access')->insert([['company_id' => $company->id, 'user_id' => $maker->id, 'created_at' => now(), 'updated_at' => now()], ['company_id' => $company->id, 'user_id' => $checker->id, 'created_at' => now(), 'updated_at' => now()]]);
        $profile = (int) DB::table('seller_profiles')->insertGetId(['company_id' => $company->id, 'user_id' => $maker->id, 'display_name' => 'Inventory FX', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
        $store = (int) DB::table('stores')->insertGetId(['seller_profile_id' => $profile, 'name' => 'Inventory FX Store', 'slug' => 'inventory-fx-'.$token, 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
        $product = (int) DB::table('products')->insertGetId(['store_id' => $store, 'code' => 'IFX-'.$token, 'name' => 'Imported stock', 'slug' => 'imported-'.$token, 'base_price_minor' => 10_000, 'currency' => 'USD', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
        $variant = (int) DB::table('product_variants')->insertGetId(['product_id' => $product, 'sku' => 'IFX-'.$token, 'name' => 'Default', 'price_minor' => 10_000, 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
        $location = (int) DB::table('inventory_locations')->insertGetId(['store_id' => $store, 'branch_id' => $branch, 'code' => 'IFX-'.$token, 'name' => 'Inventory FX Location', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('inventory_balances')->insert(['product_variant_id' => $variant, 'inventory_location_id' => $location, 'on_hand' => 0, 'reserved' => 0, 'inventory_value_minor' => 0, 'average_unit_cost_minor' => 0, 'version' => 1, 'created_at' => now(), 'updated_at' => now()]);

        $posting = app(SourceAccountingPostingService::class);
        $inventory = $posting->accountEvidence($company->id, '1200', 'VND', $maker->id)['id'];
        $grni = $posting->accountEvidence($company->id, '2150', 'VND', $maker->id)['id'];
        $ap = $posting->accountEvidence($company->id, '2100', 'VND', $maker->id)['id'];
        $policyService = app(AccountingPostingPolicyService::class);
        $policy = $policyService->create($company->id, $maker, ['policy_key' => 'IFX-RECEIPT-'.$token, 'source_type' => 'goods_receipt', 'business_event' => 'INVENTORY_RECEIPT', 'classification' => 'inventory', 'version' => 1, 'currency' => 'VND', 'effective_from' => '2026-01-01', 'lines' => [['entry_side' => 'debit', 'line_role' => 'INVENTORY', 'account_id' => $inventory], ['entry_side' => 'credit', 'line_role' => 'GRNI', 'account_id' => $grni]]]);
        $policyService->submit($company->id, $policy, $maker);
        $policyService->approve($company->id, $policy, $checker);
        foreach ([
            ['IFX-RETURN-'.$token, 'goods_return', 'GOODS_RETURN_TO_VENDOR', null, [['entry_side' => 'debit', 'line_role' => 'GRNI', 'account_id' => $grni], ['entry_side' => 'credit', 'line_role' => 'INVENTORY_ASSET', 'account_id' => $inventory]]],
            ['IFX-BILL-'.$token, 'vendor_bill', 'INVENTORY_VENDOR_BILL_POSTED', 'inventory', [['entry_side' => 'debit', 'line_role' => 'GRNI', 'account_id' => $grni], ['entry_side' => 'credit', 'line_role' => 'AP', 'account_id' => $ap]]],
        ] as [$key, $source, $event, $classification, $lines]) {
            $configured = $policyService->create($company->id, $maker, ['policy_key' => $key, 'source_type' => $source, 'business_event' => $event, 'classification' => $classification, 'version' => 1, 'currency' => 'VND', 'effective_from' => '2026-01-01', 'lines' => $lines]);
            $policyService->submit($company->id, $configured, $maker);
            $policyService->approve($company->id, $configured, $checker);
        }
        $rateService = app(FxRateService::class);
        $rate = $rateService->create($company->id, $maker, ['base_currency' => 'USD', 'quote_currency' => 'VND', 'rate_decimal' => '24690.500000000000', 'rate_date' => '2026-10-03', 'rate_type' => 'spot', 'source_reference' => 'IFX-'.$token, 'effective_from' => '2026-10-03', 'effective_to' => '2026-10-03', 'idempotency_key' => (string) Str::uuid()]);
        $rateService->transition($company->id, $rate, 'submit', $maker);
        $rateService->transition($company->id, $rate, 'approve', $checker);
        $rateService->transition($company->id, $rate, 'activate', $checker);
        $billRate = $rateService->create($company->id, $maker, ['base_currency' => 'USD', 'quote_currency' => 'VND', 'rate_decimal' => '24690.500000000000', 'rate_date' => '2026-10-04', 'rate_type' => 'spot', 'source_reference' => 'IFX-BILL-'.$token, 'effective_from' => '2026-10-04', 'effective_to' => '2026-10-04', 'idempotency_key' => (string) Str::uuid()]);
        $rateService->transition($company->id, $billRate, 'submit', $maker);
        $rateService->transition($company->id, $billRate, 'approve', $checker);
        $rateService->transition($company->id, $billRate, 'activate', $checker);

        $service = app(ProcurementService::class);
        $vendor = $service->mutate('vendor.create', $company->id, $maker, ['code' => 'V-'.$token, 'name' => 'Foreign vendor']);
        $request = $service->mutate('request.create', $company->id, $maker, ['branch_id' => $branch, 'reference' => 'PR-'.$token, 'requested_date' => '2026-10-01', 'reason' => 'Inventory FX race', 'product_variant_id' => $variant, 'description' => 'Imported stock', 'quantity' => $quantity, 'estimated_unit_cost_minor' => 10_000]);
        $service->mutate('request.transition', $company->id, $maker, ['id' => $request, 'to' => 'submitted']);
        $service->mutate('request.transition', $company->id, $checker, ['id' => $request, 'to' => 'approved']);
        $order = $service->mutate('order.create', $company->id, $maker, ['purchase_request_id' => $request, 'vendor_id' => $vendor, 'reference' => 'PO-'.$token, 'currency' => 'USD', 'order_date' => '2026-10-02']);
        $service->mutate('order.transition', $company->id, $checker, ['id' => $order, 'to' => 'approved']);
        $orderLine = (int) DB::table('purchase_order_lines')->where('purchase_order_id', $order)->value('id');
        $receipt = $service->mutate('receipt.create', $company->id, $maker, ['purchase_order_id' => $order, 'purchase_order_line_id' => $orderLine, 'inventory_location_id' => $location, 'reference' => 'GR-'.$token, 'receipt_date' => '2026-10-03', 'quantity' => $quantity]);
        $receiptLine = (int) DB::table('procurement_goods_receipt_lines')->where('goods_receipt_id', $receipt)->value('id');

        return compact('token', 'maker', 'checker', 'company', 'branch', 'profile', 'store', 'product', 'variant', 'location', 'vendor', 'request', 'order', 'orderLine', 'receipt', 'receiptLine');
    }

    /** @param array<string,mixed> $f */
    private function cleanup(array $f): void
    {
        $company = $f['company']->id;
        $journals = DB::table('payroll_accounting_journals')->where('company_id', $company)->pluck('id');
        $returns = DB::table('procurement_goods_returns')->where('company_id', $company)->pluck('id');
        DB::table('inventory_return_fx_reversals')->where('company_id', $company)->delete();
        DB::table('procurement_exception_cases')->where('company_id', $company)->delete();
        DB::table('procurement_goods_return_lines')->whereIn('goods_return_id', $returns)->delete();
        DB::table('procurement_goods_returns')->whereIn('id', $returns)->delete();
        DB::table('vendor_bill_receipt_fx_matches')->where('company_id', $company)->delete();
        DB::table('supplier_invoice_fx_recognitions')->where('company_id', $company)->delete();
        DB::table('vendor_bill_receipt_allocations')->whereIn('vendor_bill_line_id', DB::table('vendor_bill_lines')->whereIn('vendor_bill_id', DB::table('vendor_bills')->where('company_id', $company)->select('id'))->select('id'))->delete();
        DB::table('vendor_bill_lines')->whereIn('vendor_bill_id', DB::table('vendor_bills')->where('company_id', $company)->select('id'))->delete();
        DB::table('vendor_bills')->where('company_id', $company)->delete();
        DB::table('inventory_receipt_fx_valuations')->where('company_id', $company)->delete();
        DB::table('finance_fx_rate_snapshots')->where('company_id', $company)->delete();
        DB::table('accounting_journal_source_evidence')->whereIn('journal_id', $journals)->delete();
        DB::table('payroll_accounting_journal_lines')->whereIn('journal_id', $journals)->delete();
        DB::table('payroll_accounting_journals')->whereIn('id', $journals)->delete();
        DB::table('procurement_goods_receipt_lines')->where('goods_receipt_id', $f['receipt'])->update(['inventory_movement_id' => null]);
        DB::table('inventory_movements')->where('product_variant_id', $f['variant'])->delete();
        DB::table('procurement_goods_receipt_lines')->where('goods_receipt_id', $f['receipt'])->delete();
        DB::table('procurement_goods_receipts')->where('id', $f['receipt'])->delete();
        DB::table('accounting_posting_policy_lines')->whereIn('accounting_posting_policy_id', DB::table('accounting_posting_policies')->where('company_id', $company)->select('id'))->delete();
        DB::table('accounting_posting_policies')->where('company_id', $company)->delete();
        DB::table('finance_fx_rates')->where('company_id', $company)->delete();
        DB::table('purchase_order_lines')->where('purchase_order_id', $f['order'])->delete();
        DB::table('purchase_orders')->where('id', $f['order'])->delete();
        DB::table('purchase_request_lines')->where('purchase_request_id', $f['request'])->delete();
        DB::table('purchase_requests')->where('id', $f['request'])->delete();
        DB::table('procurement_vendors')->where('id', $f['vendor'])->delete();
        DB::table('inventory_balances')->where(['product_variant_id' => $f['variant'], 'inventory_location_id' => $f['location']])->delete();
        DB::table('inventory_locations')->where('id', $f['location'])->delete();
        DB::table('product_variants')->where('id', $f['variant'])->delete();
        DB::table('products')->where('id', $f['product'])->delete();
        DB::table('stores')->where('id', $f['store'])->delete();
        DB::table('seller_profiles')->where('id', $f['profile'])->delete();
        DB::table('accounting_accounts')->where('company_id', $company)->delete();
        DB::table('accounting_periods')->where('company_id', $company)->delete();
        DB::table('company_user_access')->where('company_id', $company)->delete();
        DB::table('branches')->where('id', $f['branch'])->delete();
        $f['company']->delete();
        $f['maker']->forceDelete();
        $f['checker']->forceDelete();
    }
}
