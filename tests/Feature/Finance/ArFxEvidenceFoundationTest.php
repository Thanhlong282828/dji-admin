<?php

namespace Tests\Feature\Finance;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Modules\Company\Domain\Models\Company;
use Modules\Finance\Application\Services\ArFxSettlementEvidenceService;
use Modules\Finance\Application\Services\CustomerInvoiceFxRecognitionService;
use Modules\Finance\Application\Services\FxRateService;
use Modules\Finance\Domain\Enums\FxRateType;
use Tests\TestCase;

final class ArFxEvidenceFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_schema_and_spot_registry_are_canonical(): void
    {
        self::assertTrue(Schema::hasTable('customer_invoice_fx_recognitions'));
        self::assertTrue(Schema::hasTable('customer_collection_allocation_fx_settlements'));
        self::assertSame(['closing', 'spot'], FxRateType::values());
    }

    public function test_foreign_recognition_uses_spot_conversion_and_immutable_snapshot(): void
    {
        [$company, $maker, $checker, $invoice] = $this->scope('USD', 'VND', 10_000);
        $this->activeRate($company, $maker, $checker, 'USD', 'VND', 'spot');
        $service = app(CustomerInvoiceFxRecognitionService::class);
        $result = $service->recognize($company->id, $invoice, $maker->id);

        self::assertSame(2_469_050, $result->functionalAmountMinor);
        self::assertSame('spot', $result->rateType);
        self::assertNotNull($result->snapshotId);
        self::assertSame($result->recognitionId, $service->recognize($company->id, $invoice, $maker->id)->recognitionId);
        self::assertDatabaseHas('finance_fx_rate_snapshots', ['company_id' => $company->id, 'source_type' => 'customer_invoice_recognition', 'source_id' => $invoice, 'rate_type' => 'spot']);
        self::assertSame(1, DB::table('customer_invoice_fx_recognitions')->where('customer_invoice_id', $invoice)->count());

        DB::table('customer_invoices')->where('id', $invoice)->update(['total_minor' => 10_001]);
        $this->assertValidation(fn () => $service->recognize($company->id, $invoice, $maker->id), 'AR_FX_RECOGNITION_IMMUTABLE');
    }

    public function test_identity_recognition_needs_no_rate_or_snapshot(): void
    {
        [$company, $maker, , $invoice] = $this->scope('VND', 'VND', 5_250_000);
        $result = app(CustomerInvoiceFxRecognitionService::class)->recognize($company->id, $invoice, $maker->id);

        self::assertTrue($result->identity);
        self::assertSame(5_250_000, $result->functionalAmountMinor);
        self::assertSame('1.000000000000', $result->rateDecimal);
        self::assertNull($result->snapshotId);
        self::assertSame(0, DB::table('finance_fx_rates')->count());
        self::assertSame(0, DB::table('finance_fx_rate_snapshots')->count());
    }

    public function test_missing_functional_currency_fails_atomically(): void
    {
        [$company, $maker, , $invoice] = $this->scope('USD', null, 10_000);
        $this->assertValidation(fn () => app(CustomerInvoiceFxRecognitionService::class)->recognize($company->id, $invoice, $maker->id), 'FUNCTIONAL_CURRENCY_NOT_CONFIGURED');
        self::assertSame(0, DB::table('customer_invoice_fx_recognitions')->count());
    }

    public function test_missing_spot_wrong_type_and_inverse_pair_fail_closed(): void
    {
        [$company, $maker, $checker, $invoice] = $this->scope('USD', 'VND', 10_000);
        $service = app(CustomerInvoiceFxRecognitionService::class);
        $this->assertValidation(fn () => $service->recognize($company->id, $invoice, $maker->id), 'FX_RATE_NOT_AVAILABLE');
        $this->activeRate($company, $maker, $checker, 'USD', 'VND', 'closing');
        $this->assertValidation(fn () => $service->recognize($company->id, $invoice, $maker->id), 'FX_RATE_NOT_AVAILABLE');
        $this->activeRate($company, $maker, $checker, 'VND', 'USD', 'spot', '0.000040500000');
        $this->assertValidation(fn () => $service->recognize($company->id, $invoice, $maker->id), 'FX_RATE_NOT_AVAILABLE');
        self::assertSame(0, DB::table('customer_invoice_fx_recognitions')->count());
        self::assertSame(0, DB::table('finance_fx_rate_snapshots')->count());
    }

    public function test_invoice_is_tenant_and_branch_derived(): void
    {
        [$company, $maker, $checker, $invoice, $branch] = $this->scope('USD', 'VND', 10_000);
        $other = Company::factory()->create(['functional_currency_code' => 'VND']);
        $this->activeRate($company, $maker, $checker, 'USD', 'VND', 'spot');
        $this->assertValidation(fn () => app(CustomerInvoiceFxRecognitionService::class)->recognize($other->id, $invoice, $maker->id), 'AR_FX_INVOICE_NOT_FOUND');
        $result = app(CustomerInvoiceFxRecognitionService::class)->recognize($company->id, $invoice, $maker->id);
        self::assertSame($branch, (int) DB::table('customer_invoice_fx_recognitions')->where('id', $result->recognitionId)->value('branch_id'));
    }

    public function test_settlement_evidence_is_allocation_scoped_signed_and_create_once(): void
    {
        [$company, $maker, $checker, $invoice, $branch] = $this->scope('USD', 'VND', 10_000);
        $this->activeRate($company, $maker, $checker, 'USD', 'VND', 'spot');
        app(CustomerInvoiceFxRecognitionService::class)->recognize($company->id, $invoice, $maker->id);
        $customer = (int) DB::table('customer_invoices')->where('id', $invoice)->value('customer_id');
        $collection = (int) DB::table('customer_collections')->insertGetId(['company_id' => $company->id, 'branch_id' => $branch, 'customer_id' => $customer, 'reference' => 'ARFX-COL-'.$company->id, 'collection_date' => '2026-10-03', 'amount_minor' => 4_000, 'allocated_minor' => 4_000, 'currency' => 'USD', 'method' => 'bank_transfer', 'status' => 'recorded', 'created_by' => $maker->id, 'created_at' => now(), 'updated_at' => now()]);
        $allocation = (int) DB::table('customer_collection_allocations')->insertGetId(['customer_collection_id' => $collection, 'customer_invoice_id' => $invoice, 'allocated_minor' => 4_000, 'created_at' => now(), 'updated_at' => now()]);
        $service = app(ArFxSettlementEvidenceService::class);
        $id = $service->record($company->id, $allocation, $maker->id);
        self::assertSame($id, $service->record($company->id, $allocation, $maker->id));
        self::assertDatabaseHas('customer_collection_allocation_fx_settlements', ['id' => $id, 'foreign_settled_minor' => 4_000, 'settlement_functional_minor' => 987_620, 'released_carrying_minor' => 987_620, 'realized_fx_minor' => 0, 'rate_type' => 'spot']);
        self::assertSame(1, DB::table('customer_collection_allocation_fx_settlements')->where('customer_collection_allocation_id', $allocation)->count());
    }

    /** @return array{Company,User,User,int,int} */
    private function scope(string $currency, ?string $functional, int $amount): array
    {
        $company = Company::factory()->create(['functional_currency_code' => $functional]);
        $maker = User::factory()->create();
        $checker = User::factory()->create();
        $branch = (int) DB::table('branches')->insertGetId(['company_id' => $company->id, 'code' => 'ARFX-HQ-'.$company->id, 'name' => 'AR FX HQ', 'status' => 'active', 'timezone' => 'Asia/Ho_Chi_Minh', 'created_at' => now(), 'updated_at' => now()]);
        $customer = (int) DB::table('commerce_customers')->insertGetId(['company_id' => $company->id, 'full_name' => 'AR FX Customer', 'phone' => '090'.str_pad((string) $company->id, 7, '0', STR_PAD_LEFT), 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
        $invoice = (int) DB::table('customer_invoices')->insertGetId(['company_id' => $company->id, 'branch_id' => $branch, 'customer_id' => $customer, 'sales_order_id' => null, 'invoice_number' => 'ARFX-'.$company->id, 'invoice_date' => '2026-10-03', 'due_date' => '2026-11-03', 'currency' => $currency, 'subtotal_minor' => $amount, 'discount_minor' => 0, 'tax_minor' => 0, 'total_minor' => $amount, 'paid_minor' => 0, 'tax_status' => 'configuration_required', 'status' => 'draft', 'created_by' => $maker->id, 'created_at' => now(), 'updated_at' => now()]);

        return [$company, $maker, $checker, $invoice, $branch];
    }

    private function activeRate(Company $company, User $maker, User $checker, string $base, string $quote, string $type, string $rate = '24690.500000000000'): void
    {
        $service = app(FxRateService::class);
        $id = $service->create($company->id, $maker, ['base_currency' => $base, 'quote_currency' => $quote, 'rate_decimal' => $rate, 'rate_date' => '2026-10-03', 'rate_type' => $type, 'source_reference' => 'AR-FX-'.$base.'-'.$quote.'-'.$type, 'effective_from' => '2026-10-03', 'effective_to' => null, 'idempotency_key' => fake()->uuid()]);
        $service->transition($company->id, $id, 'submit', $maker);
        $service->transition($company->id, $id, 'approve', $checker);
        $service->transition($company->id, $id, 'activate', $checker);
    }

    private function assertValidation(callable $callback, string $message): void
    {
        try {
            $callback();
            self::fail('Expected validation error '.$message);
        } catch (ValidationException $exception) {
            self::assertContains($message, collect($exception->errors())->flatten()->all());
        }
    }
}
