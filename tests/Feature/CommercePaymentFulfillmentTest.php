<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\DataQuality\DataQualityAuditService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Modules\Commerce\Application\Services\CheckoutService;
use Modules\Commerce\Application\Services\CommerceFulfillmentService;
use Modules\Commerce\Application\Services\CommercePaymentService;
use Modules\Commerce\Application\Services\OrderTransitionService;
use Modules\Commerce\Application\Services\SaleReversalService;
use Modules\Company\Domain\Models\Company;
use Tests\TestCase;

final class CommercePaymentFulfillmentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_paid_delivered_order_consumes_inventory_and_recognizes_branch_revenue_once(): void
    {
        [$company, $branch, $buyer, $seller, $finance, $orderId, $balanceId] = $this->fixture();
        $payments = app(CommercePaymentService::class);
        $first = $payments->recordSucceeded($finance, $company->id, $orderId, 'internal_demo', 'SETTLEMENT-DEMO-001', 'payment-key-001');
        $second = $payments->recordSucceeded($finance, $company->id, $orderId, 'internal_demo', 'SETTLEMENT-DEMO-001', 'payment-key-001');
        self::assertSame($first, $second);
        self::assertSame(1, DB::table('commerce_payment_records')->count());
        self::assertSame(0, DB::table('branch_management_revenues')->count());

        app(OrderTransitionService::class)->sellerTransition($seller, $orderId, 'confirm');
        $fulfillments = app(CommerceFulfillmentService::class);
        $fulfillmentId = $fulfillments->create($seller, $orderId, $branch);
        self::assertSame($fulfillmentId, $fulfillments->create($seller, $orderId, $branch));
        $fulfillments->transition($seller, $orderId, 'pack');
        $fulfillments->transition($seller, $orderId, 'ship', 'Local Carrier', 'TRACK-001');
        $this->assertDatabaseHas('inventory_balances', ['id' => $balanceId, 'on_hand' => 3, 'reserved' => 0]);
        $this->assertDatabaseHas('inventory_reservations', ['order_id' => $orderId, 'status' => 'consumed']);
        $this->assertDatabaseHas('inventory_movements', ['source_type' => 'commerce_order', 'source_id' => $orderId, 'movement_type' => 'reservation_consumed', 'quantity_delta' => -2, 'on_hand_before' => 5, 'on_hand_after' => 3]);
        $fulfillments->transition($seller, $orderId, 'deliver');

        $this->assertDatabaseHas('orders', ['id' => $orderId, 'status' => 'fulfilled', 'payment_status' => 'paid']);
        $this->assertDatabaseHas('branch_management_revenues', ['company_id' => $company->id, 'branch_id' => $branch, 'order_id' => $orderId, 'source_type' => 'commerce_order', 'amount_minor' => 300_000]);
        self::assertSame(1, DB::table('branch_management_revenues')->where('order_id', $orderId)->count());
        self::assertSame(330_000, (int) DB::table('commerce_payment_records')->where('order_id', $orderId)->value('amount_minor'));
        self::assertSame(300_000, (int) DB::table('branch_management_revenues')->where('order_id', $orderId)->value('amount_minor'));
        self::assertSame(-160_000, (int) DB::table('inventory_movements')->where(['source_type' => 'commerce_order', 'source_id' => $orderId])->value('movement_value_minor'));
        self::assertSame(2, DB::table('payroll_accounting_journals')->where(['source_type' => 'marketplace_order', 'source_id' => $orderId])->count());
        $this->actingAs($finance)->get(route('admin.commerce.sales-history', ['company_id' => $company->id]))->assertOk()->assertSee('Lịch sử đơn hàng đã bán')->assertSee('Payment Product')->assertSee('300.000')->assertSee('SETTLEMENT-DEMO-001');
        $reversal = app(SaleReversalService::class)->reverse($finance, $company->id, 'marketplace', $orderId, 'Hoàn trả toàn bộ đơn đã giao');
        self::assertSame($reversal, app(SaleReversalService::class)->reverse($finance, $company->id, 'marketplace', $orderId, 'Retry idempotent'));
        self::assertSame(5, (int) DB::table('inventory_balances')->where('id', $balanceId)->value('on_hand'));
        self::assertSame(1, DB::table('commerce_sale_reversals')->where(['source_type' => 'marketplace', 'source_id' => $orderId])->count());
        self::assertSame(1, DB::table('inventory_movements')->where('source_type', 'sale_reversal')->count());
        self::assertSame(160_000, (int) DB::table('inventory_movements')->where('source_type', 'sale_reversal')->value('movement_value_minor'));
        self::assertSame(2, DB::table('payroll_accounting_journals')->where(['source_type' => 'sale_reversal', 'source_id' => $reversal])->count());
        self::assertNotSame($buyer->id, $finance->id);
        self::assertSame(0, app(DataQualityAuditService::class)->snapshot()['commerce_issues']);
    }

    public function test_seller_cannot_record_payment_and_paid_order_cannot_be_buyer_cancelled(): void
    {
        [$company, , $buyer, $seller, $finance, $orderId] = $this->fixture();
        $this->actingAs($seller)->get(route('admin.commerce.payments.index', ['company_id' => $company->id]))->assertForbidden();
        $this->actingAs($seller)->post(route('admin.commerce.payments.store'), ['company_id' => $company->id, 'order_id' => $orderId, 'method' => 'internal_demo', 'settlement_reference' => 'BAD', 'idempotency_key' => 'bad'])->assertForbidden();
        app(CommercePaymentService::class)->recordSucceeded($finance, $company->id, $orderId, 'internal_demo', 'SETTLEMENT-DEMO-002', 'payment-key-002');
        $this->actingAs($buyer)->post(route('orders.cancel', $orderId))->assertStatus(409);
        $this->assertDatabaseHas('inventory_reservations', ['order_id' => $orderId, 'status' => 'reserved']);
    }

    public function test_payment_rejects_a_foreign_company_scope_even_when_seller_has_both_company_accesses(): void
    {
        [$company, , , $seller, $finance, $orderId] = $this->fixture();
        $foreign = Company::query()->create(['uuid' => (string) Str::uuid(), 'code' => 'COM-FOREIGN', 'name' => 'Foreign Commerce Company', 'country_code' => 'VN', 'timezone' => 'Asia/Ho_Chi_Minh', 'currency_code' => 'VND', 'currency' => 'VND', 'status' => 'active', 'is_active' => true]);
        DB::table('company_user_access')->insert([['company_id' => $foreign->id, 'user_id' => $seller->id], ['company_id' => $foreign->id, 'user_id' => $finance->id]]);

        $this->actingAs($finance)->post(route('admin.commerce.payments.store'), ['company_id' => $foreign->id, 'order_id' => $orderId, 'method' => 'internal_demo', 'settlement_reference' => 'FOREIGN', 'idempotency_key' => 'foreign-payment'])->assertNotFound();
        $this->assertDatabaseMissing('commerce_payment_records', ['order_id' => $orderId]);
        $this->assertDatabaseHas('orders', ['id' => $orderId, 'payment_status' => 'unpaid']);
        self::assertNotSame($company->id, $foreign->id);
    }

    public function test_checkout_rolls_back_when_one_store_order_would_span_multiple_fulfillment_branches(): void
    {
        [$company, , $buyer, , , , $balanceId] = $this->fixture();
        $balance = DB::table('inventory_balances')->where('id', $balanceId)->firstOrFail();
        DB::table('inventory_balances')->where('id', $balanceId)->update(['on_hand' => 3, 'reserved' => 2]);
        $foreignBranch = (int) DB::table('branches')->insertGetId(['company_id' => $company->id, 'code' => 'HN', 'name' => 'HN Fulfillment', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
        $foreignLocation = (int) DB::table('inventory_locations')->insertGetId(['store_id' => DB::table('inventory_locations')->where('id', $balance->inventory_location_id)->value('store_id'), 'branch_id' => $foreignBranch, 'code' => 'HN-WH', 'name' => 'Kho HN', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('inventory_balances')->insert(['product_variant_id' => $balance->product_variant_id, 'inventory_location_id' => $foreignLocation, 'on_hand' => 10, 'reserved' => 0, 'version' => 1, 'created_at' => now(), 'updated_at' => now()]);
        $cart = (int) DB::table('carts')->where('user_id', $buyer->id)->value('id');
        $product = (int) DB::table('product_variants')->where('id', $balance->product_variant_id)->value('product_id');
        DB::table('cart_items')->insert(['cart_id' => $cart, 'product_id' => $product, 'product_variant_id' => $balance->product_variant_id, 'quantity' => 2, 'unit_price_minor' => 150_000, 'selected' => true, 'created_at' => now(), 'updated_at' => now()]);
        $address = (int) DB::table('buyer_addresses')->where('user_id', $buyer->id)->value('id');

        try {
            app(CheckoutService::class)->place($buyer, $address, 'standard', (string) Str::uuid());
            self::fail('Cross-Branch Store Order phải rollback.');
        } catch (ValidationException $exception) {
            self::assertArrayHasKey('inventory', $exception->errors());
        }
        self::assertSame(1, DB::table('orders')->count());
        self::assertSame(2, (int) DB::table('inventory_balances')->where('id', $balanceId)->value('reserved'));
        self::assertSame(0, (int) DB::table('inventory_balances')->where('id', '<>', $balanceId)->sum('reserved'));
    }

    /** @return array{Company,int,User,User,User,int,int} */
    private function fixture(): array
    {
        $company = Company::query()->create(['uuid' => (string) Str::uuid(), 'code' => 'COM-PAY', 'name' => 'Commerce Payment Company', 'country_code' => 'VN', 'timezone' => 'Asia/Ho_Chi_Minh', 'currency_code' => 'VND', 'currency' => 'VND', 'status' => 'active', 'is_active' => true]);
        $branch = (int) DB::table('branches')->insertGetId(['company_id' => $company->id, 'code' => 'HCM', 'name' => 'HCM Fulfillment', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
        $seller = User::factory()->create(['email_verified_at' => now(), 'access_enabled' => true]);
        $seller->assignRole('Seller');
        $finance = User::factory()->create(['email_verified_at' => now(), 'access_enabled' => true]);
        $finance->assignRole('Finance');
        $buyer = User::factory()->create(['email_verified_at' => now(), 'access_enabled' => true]);
        DB::table('company_user_access')->insert([['company_id' => $company->id, 'user_id' => $seller->id], ['company_id' => $company->id, 'user_id' => $finance->id]]);
        DB::table('buyer_profiles')->insert(['user_id' => $buyer->id, 'display_name' => $buyer->name, 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
        $profile = (int) DB::table('seller_profiles')->insertGetId(['company_id' => $company->id, 'user_id' => $seller->id, 'display_name' => 'Seller Payment', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
        $store = (int) DB::table('stores')->insertGetId(['seller_profile_id' => $profile, 'name' => 'Payment Store', 'slug' => 'payment-store', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
        $category = (int) DB::table('product_categories')->insertGetId(['name' => 'Payment Category', 'slug' => 'payment-category', 'status' => 'active', 'sort_order' => 1, 'created_at' => now(), 'updated_at' => now()]);
        $product = (int) DB::table('products')->insertGetId(['store_id' => $store, 'product_category_id' => $category, 'code' => 'PAY-P', 'name' => 'Payment Product', 'slug' => 'payment-product', 'base_price_minor' => 150_000, 'currency' => 'VND', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
        $variant = (int) DB::table('product_variants')->insertGetId(['product_id' => $product, 'sku' => 'PAY-SKU', 'name' => 'Default', 'price_minor' => 150_000, 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
        $location = (int) DB::table('inventory_locations')->insertGetId(['store_id' => $store, 'branch_id' => $branch, 'code' => 'HCM-WH', 'name' => 'Kho HCM', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
        $balance = (int) DB::table('inventory_balances')->insertGetId(['product_variant_id' => $variant, 'inventory_location_id' => $location, 'on_hand' => 5, 'reserved' => 0, 'inventory_value_minor' => 400_000, 'average_unit_cost_minor' => 80_000, 'version' => 1, 'created_at' => now(), 'updated_at' => now()]);
        $address = (int) DB::table('buyer_addresses')->insertGetId(['user_id' => $buyer->id, 'label' => 'Nhà', 'recipient_name' => 'Buyer', 'phone' => '0900000000', 'province' => 'TP.HCM', 'district' => 'Quận 1', 'ward' => 'Bến Nghé', 'address_line' => '1 Demo', 'is_default' => true, 'created_at' => now(), 'updated_at' => now()]);
        $cart = (int) DB::table('carts')->insertGetId(['user_id' => $buyer->id, 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('cart_items')->insert(['cart_id' => $cart, 'product_id' => $product, 'product_variant_id' => $variant, 'quantity' => 2, 'unit_price_minor' => 150_000, 'selected' => true, 'created_at' => now(), 'updated_at' => now()]);
        $orderId = app(CheckoutService::class)->place($buyer, $address, 'standard', (string) Str::uuid())[0];

        return [$company, $branch, $buyer, $seller, $finance, $orderId, $balance];
    }
}
