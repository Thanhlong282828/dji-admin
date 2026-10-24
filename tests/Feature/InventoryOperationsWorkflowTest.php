<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Inventory\Application\Services\InventoryOperationsService;
use Tests\TestCase;

final class InventoryOperationsWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_stock_receipt_and_transfer_are_idempotent_and_reconcile_movements(): void
    {
        [$actor, $company, $variant, $source, $destination] = $this->runtime();
        $service = app(InventoryOperationsService::class);
        $receipt = $service->mutate('receipt.create', $company, $actor, ['inventory_location_id' => $source, 'product_variant_id' => $variant, 'reference' => 'GRN-001', 'supplier_name' => 'Nhà cung cấp A', 'quantity' => 5]);
        self::assertSame($receipt, $service->mutate('receipt.receive', $company, $actor, resourceId: $receipt));
        self::assertSame($receipt, $service->mutate('receipt.receive', $company, $actor, resourceId: $receipt));
        self::assertSame(15, (int) DB::table('inventory_balances')->where(['product_variant_id' => $variant, 'inventory_location_id' => $source])->value('on_hand'));
        self::assertSame(1, DB::table('inventory_movements')->where('idempotency_key', 'stock-receipt-'.$receipt)->count());

        $transfer = $service->mutate('transfer.create', $company, $actor, ['source_location_id' => $source, 'destination_location_id' => $destination, 'product_variant_id' => $variant, 'reference' => 'TRF-001', 'quantity' => 4]);
        self::assertSame($transfer, $service->mutate('transfer.create', $company, $actor, ['source_location_id' => $source, 'destination_location_id' => $destination, 'product_variant_id' => $variant, 'reference' => 'TRF-001', 'quantity' => 4]));
        self::assertSame(1, DB::table('inventory_stock_transfers')->where(['company_id' => $company, 'reference' => 'TRF-001'])->count());
        self::assertSame($transfer, $service->mutate('transfer.dispatch', $company, $actor, resourceId: $transfer));
        self::assertSame($transfer, $service->mutate('transfer.dispatch', $company, $actor, resourceId: $transfer));
        self::assertSame($transfer, $service->mutate('transfer.receive', $company, $actor, resourceId: $transfer));
        self::assertSame($transfer, $service->mutate('transfer.receive', $company, $actor, resourceId: $transfer));

        self::assertSame(11, (int) DB::table('inventory_balances')->where(['product_variant_id' => $variant, 'inventory_location_id' => $source])->value('on_hand'));
        self::assertSame(4, (int) DB::table('inventory_balances')->where(['product_variant_id' => $variant, 'inventory_location_id' => $destination])->value('on_hand'));
        self::assertSame(1, DB::table('inventory_movements')->where('idempotency_key', 'stock-transfer-out-'.$transfer)->count());
        self::assertSame(1, DB::table('inventory_movements')->where('idempotency_key', 'stock-transfer-in-'.$transfer)->count());
        self::assertSame(15, (int) DB::table('inventory_balances')->where('product_variant_id', $variant)->sum('on_hand'));
    }

    /** @return array{User,int,int,int,int} */
    private function runtime(): array
    {
        $actor = User::factory()->create(['email_verified_at' => now()]);
        $company = (int) DB::table('companies')->insertGetId(['uuid' => (string) Str::uuid(), 'code' => 'INV-OPS', 'name' => 'Inventory Operations', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
        $branchA = (int) DB::table('branches')->insertGetId(['company_id' => $company, 'code' => 'A', 'name' => 'Chi nhánh A', 'status' => 'active', 'timezone' => 'Asia/Ho_Chi_Minh', 'created_at' => now(), 'updated_at' => now()]);
        $branchB = (int) DB::table('branches')->insertGetId(['company_id' => $company, 'code' => 'B', 'name' => 'Chi nhánh B', 'status' => 'active', 'timezone' => 'Asia/Ho_Chi_Minh', 'created_at' => now(), 'updated_at' => now()]);
        $profile = (int) DB::table('seller_profiles')->insertGetId(['company_id' => $company, 'user_id' => $actor->id, 'display_name' => 'Inventory Seller', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
        $store = (int) DB::table('stores')->insertGetId(['seller_profile_id' => $profile, 'name' => 'Inventory Store', 'slug' => 'inventory-store', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
        $product = (int) DB::table('products')->insertGetId(['store_id' => $store, 'code' => 'INV-P', 'name' => 'Sản phẩm kho', 'slug' => 'san-pham-kho', 'base_price_minor' => 100000, 'currency' => 'VND', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
        $variant = (int) DB::table('product_variants')->insertGetId(['product_id' => $product, 'sku' => 'INV-SKU', 'name' => 'Mặc định', 'price_minor' => 100000, 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
        $source = (int) DB::table('inventory_locations')->insertGetId(['store_id' => $store, 'branch_id' => $branchA, 'code' => 'A', 'name' => 'Kho A', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
        $destination = (int) DB::table('inventory_locations')->insertGetId(['store_id' => $store, 'branch_id' => $branchB, 'code' => 'B', 'name' => 'Kho B', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('inventory_balances')->insert(['product_variant_id' => $variant, 'inventory_location_id' => $source, 'on_hand' => 10, 'reserved' => 0, 'version' => 1, 'created_at' => now(), 'updated_at' => now()]);

        return [$actor, $company, $variant, $source, $destination];
    }
}
