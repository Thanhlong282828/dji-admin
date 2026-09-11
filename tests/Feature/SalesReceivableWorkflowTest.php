<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Database\RecordNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Modules\Commerce\Application\Services\SalesReceivableService;
use Modules\Finance\Application\Services\FinancialStatementReadService;
use Tests\TestCase;

final class SalesReceivableWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_sales_to_ar_collection_treasury_and_accounting_is_canonical_and_idempotent(): void
    {
        [$maker, $checker, $company, $branch, $customer, $variant, $location] = $this->runtime();
        $service = app(SalesReceivableService::class);
        $quotation = $service->createQuotation($company, $maker, ['branch_id' => $branch, 'customer_id' => $customer, 'product_variant_id' => $variant, 'reference' => 'QT-SALES-001', 'quotation_date' => '2026-09-01', 'valid_until' => '2026-09-30', 'description' => 'Thiết bị bán doanh nghiệp', 'quantity' => 4, 'unit_price_minor' => 500, 'discount_minor' => 100]);
        $this->selfApprovalIsBlocked($service, $company, $maker, 'quotation.accept', ['id' => $quotation]);
        $service->acceptQuotation($company, $checker, ['id' => $quotation]);

        $order = $service->convertQuotationToSalesOrder($company, $maker, ['quotation_id' => $quotation, 'reference' => 'SO-SALES-001', 'order_date' => '2026-09-02']);
        self::assertSame($order, $service->convertQuotationToSalesOrder($company, $maker, ['quotation_id' => $quotation, 'reference' => 'SO-SALES-RETRY', 'order_date' => '2026-09-02']));
        $service->confirmSalesOrder($company, $maker, ['id' => $order]);
        self::assertSame(10, (int) DB::table('inventory_balances')->where(['product_variant_id' => $variant, 'inventory_location_id' => $location])->value('on_hand'));

        $line = (int) DB::table('sales_order_lines')->where('sales_order_id', $order)->value('id');
        $fulfillment = $service->fulfillSalesOrder($company, $maker, ['sales_order_line_id' => $line, 'inventory_location_id' => $location, 'quantity' => 4, 'reference' => 'FUL-SALES-001', 'fulfilled_on' => '2026-09-03']);
        self::assertSame(6, (int) DB::table('inventory_balances')->where(['product_variant_id' => $variant, 'inventory_location_id' => $location])->value('on_hand'));
        self::assertSame(400, (int) DB::table('sales_fulfillments')->where('id', $fulfillment)->value('total_cogs_minor'));
        self::assertSame(1, DB::table('inventory_movements')->where('idempotency_key', 'sales-fulfillment-'.$fulfillment)->count());

        $invoice = $service->createCustomerInvoice($company, $maker, ['sales_order_id' => $order, 'invoice_number' => 'CI-SALES-001', 'invoice_date' => '2026-09-04', 'due_date' => '2026-10-04']);
        $this->selfApprovalIsBlocked($service, $company, $maker, 'invoice.issue', ['id' => $invoice]);
        $service->issueCustomerInvoice($company, $checker, ['id' => $invoice]);
        self::assertSame('configuration_required', DB::table('customer_invoices')->where('id', $invoice)->value('tax_status'));

        $first = $service->allocateCustomerCollection($company, $checker, ['customer_invoice_id' => $invoice, 'reference' => 'COL-SALES-001', 'collection_date' => '2026-09-10', 'amount_minor' => 700, 'method' => 'bank_transfer']);
        self::assertSame($first, $service->allocateCustomerCollection($company, $checker, ['customer_invoice_id' => $invoice, 'reference' => 'COL-SALES-001', 'collection_date' => '2026-09-10', 'amount_minor' => 700, 'method' => 'bank_transfer']));
        $service->allocateCustomerCollection($company, $checker, ['customer_invoice_id' => $invoice, 'reference' => 'COL-SALES-002', 'collection_date' => '2026-09-15', 'amount_minor' => 1200, 'method' => 'bank_transfer']);

        self::assertSame('paid', DB::table('customer_invoices')->where('id', $invoice)->value('status'));
        self::assertSame(1900, (int) DB::table('customer_collection_allocations')->where('customer_invoice_id', $invoice)->sum('allocated_minor'));
        self::assertSame(2, DB::table('customer_collections')->where('company_id', $company)->count());
        self::assertSame(2, DB::table('treasury_transactions')->where(['company_id' => $company, 'source_type' => 'customer_collection', 'direction' => 'inflow'])->count());
        self::assertSame(0, (int) DB::table('payroll_accounting_journals')->where('company_id', $company)->selectRaw('SUM(total_debit_minor-total_credit_minor) difference')->value('difference'));
        $statements = app(FinancialStatementReadService::class)->report($company, null, null, null, 1);
        self::assertSame(0, $statements['totals']['difference']);
        self::assertSame(1900, $statements['cash_flow']['customer_collection_inflow']);
        self::assertSame(1900, $statements['cash_flow']['operating_inflow']);
        self::assertSame(0, $statements['cash_flow']['unclassified']);
        self::assertSame(1500, $statements['income_statement']['gross_profit']);
    }

    public function test_cross_company_product_variant_is_rejected(): void
    {
        [$maker, , $company, $branch, $customer] = $this->runtime();
        [, , , , , $foreignVariant] = $this->runtime('OTHER');

        $this->expectException(RecordNotFoundException::class);
        app(SalesReceivableService::class)->createQuotation($company, $maker, ['branch_id' => $branch, 'customer_id' => $customer, 'product_variant_id' => $foreignVariant, 'reference' => 'QT-CROSS', 'quotation_date' => '2026-09-01', 'valid_until' => '2026-09-30', 'description' => 'Cross tenant', 'quantity' => 1, 'unit_price_minor' => 500, 'discount_minor' => 0]);
    }

    /** @param array<string,mixed> $data */
    private function selfApprovalIsBlocked(SalesReceivableService $service, int $company, User $maker, string $action, array $data): void
    {
        try {
            match ($action) {
                'quotation.accept' => $service->acceptQuotation($company, $maker, $data),
                'invoice.issue' => $service->issueCustomerInvoice($company, $maker, $data),
                default => self::fail('Unsupported maker-checker test intent.'),
            };
            self::fail('Maker-checker must block self approval.');
        } catch (ValidationException $exception) {
            self::assertNotEmpty($exception->errors());
        }
    }

    /** @return array{User,User,int,int,int,int,int} */
    private function runtime(string $suffix = 'MAIN'): array
    {
        $maker = User::factory()->create(['email_verified_at' => now()]);
        $checker = User::factory()->create(['email_verified_at' => now()]);
        $company = (int) DB::table('companies')->insertGetId(['uuid' => (string) Str::uuid(), 'code' => 'SALES-'.$suffix, 'name' => 'Sales Company '.$suffix, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
        $branch = (int) DB::table('branches')->insertGetId(['company_id' => $company, 'code' => 'HQ-'.$suffix, 'name' => 'Head Office '.$suffix, 'status' => 'active', 'timezone' => 'Asia/Ho_Chi_Minh', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('company_user_access')->insert([['company_id' => $company, 'user_id' => $maker->id, 'created_at' => now(), 'updated_at' => now()], ['company_id' => $company, 'user_id' => $checker->id, 'created_at' => now(), 'updated_at' => now()]]);
        $customer = (int) DB::table('commerce_customers')->insertGetId(['company_id' => $company, 'full_name' => 'Khách hàng '.$suffix, 'phone' => '090'.str_pad((string) $company, 7, '0', STR_PAD_LEFT), 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
        $profile = (int) DB::table('seller_profiles')->insertGetId(['company_id' => $company, 'user_id' => $maker->id, 'display_name' => 'Sales Store '.$suffix, 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
        $store = (int) DB::table('stores')->insertGetId(['seller_profile_id' => $profile, 'name' => 'Sales Store '.$suffix, 'slug' => 'sales-store-'.strtolower($suffix), 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
        $product = (int) DB::table('products')->insertGetId(['store_id' => $store, 'code' => 'SALES-P-'.$suffix, 'name' => 'Thiết bị '.$suffix, 'slug' => 'thiet-bi-'.strtolower($suffix), 'base_price_minor' => 500, 'currency' => 'VND', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
        $variant = (int) DB::table('product_variants')->insertGetId(['product_id' => $product, 'sku' => 'SALES-SKU-'.$suffix, 'name' => 'Mặc định', 'price_minor' => 500, 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
        $location = (int) DB::table('inventory_locations')->insertGetId(['store_id' => $store, 'branch_id' => $branch, 'code' => 'WH-'.$suffix, 'name' => 'Kho '.$suffix, 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('inventory_balances')->insert(['product_variant_id' => $variant, 'inventory_location_id' => $location, 'on_hand' => 10, 'reserved' => 0, 'inventory_value_minor' => 1000, 'average_unit_cost_minor' => 100, 'version' => 1, 'created_at' => now(), 'updated_at' => now()]);

        return [$maker, $checker, $company, $branch, $customer, $variant, $location];
    }
}
