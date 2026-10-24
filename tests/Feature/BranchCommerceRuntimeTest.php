<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\Navigation\AdminHelpRegistry;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Database\RecordNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Commerce\Application\Services\SaleReversalService;
use Modules\Finance\Application\Services\BranchFinancialPerformanceService;
use Modules\POS\Domain\Services\BranchPosService;
use Tests\TestCase;

final class BranchCommerceRuntimeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_checkout_reuses_branch_inventory_and_writes_one_canonical_revenue(): void
    {
        [$actor, $company, $branch, $variant, $location] = $this->runtime();
        $customer = (int) DB::table('commerce_customers')->insertGetId(['company_id' => $company, 'full_name' => 'Khách POS', 'phone' => '0900000001', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
        $service = (int) DB::table('company_services')->insertGetId(['company_id' => $company, 'code' => 'DV-01', 'name' => 'Dịch vụ lắp đặt', 'price_minor' => 200000, 'currency' => 'VND', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
        $pos = app(BranchPosService::class);
        $visit = $pos->checkIn($company, $branch, $customer, $actor);
        $pos->addService($company, $visit, $service, 2);
        $pos->addProduct($company, $visit, $variant, $location, 3);
        $receipt = $pos->checkout($company, $visit, 'cash', $actor);

        self::assertSame($receipt, $pos->checkout($company, $visit, 'cash', $actor));
        $this->assertDatabaseHas('inventory_balances', ['product_variant_id' => $variant, 'inventory_location_id' => $location, 'on_hand' => 7]);
        $this->assertDatabaseHas('branch_pos_receipts', ['id' => $receipt, 'service_total_minor' => 400000, 'product_total_minor' => 1500000, 'total_minor' => 1900000]);
        $this->assertDatabaseHas('branch_management_revenues', ['company_id' => $company, 'branch_id' => $branch, 'source_type' => 'branch_pos', 'amount_minor' => 1900000, 'status' => 'recognized']);
        self::assertSame(1, DB::table('branch_pos_receipts')->count());
        self::assertSame(1, DB::table('branch_management_revenues')->where('source_type', 'branch_pos')->count());
        $this->assertDatabaseHas('inventory_movements', ['product_variant_id' => $variant, 'inventory_location_id' => $location, 'movement_type' => 'decrease', 'quantity_delta' => -3, 'on_hand_before' => 10, 'on_hand_after' => 7, 'source_type' => 'branch_pos_visit']);
        self::assertSame(1, DB::table('inventory_movements')->where('source_type', 'branch_pos_visit')->count());
        self::assertSame(-900000, (int) DB::table('inventory_movements')->where('source_type', 'branch_pos_visit')->value('movement_value_minor'));
        self::assertSame(2, DB::table('payroll_accounting_journals')->where('source_type', 'pos_receipt')->count());
        self::assertSame(0, (int) DB::table('payroll_accounting_journals')->where('source_type', 'pos_receipt')->selectRaw('SUM(total_debit_minor-total_credit_minor) difference')->value('difference'));
        $actor->givePermissionTo(['dashboard.view', 'branch-commerce.view']);
        $this->actingAs($actor)->get(route('dashboard', ['company_id' => $company]))->assertOk()->assertSeeText('Nhịp vận hành doanh nghiệp')->assertSeeText('POS Receipts đã thanh toán')->assertSeeText('1,900,000 ₫')->assertSeeText('Sản phẩm POS đã bán');
    }

    public function test_branch_scope_blocks_foreign_inventory_and_navigation_has_specific_help(): void
    {
        [$actor, $company, $branch, $variant, $location] = $this->runtime();
        $foreignCompany = (int) DB::table('companies')->insertGetId(['uuid' => (string) Str::uuid(), 'code' => 'FOREIGN', 'name' => 'Foreign', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
        $foreignBranch = (int) DB::table('branches')->insertGetId(['company_id' => $foreignCompany, 'code' => 'F', 'name' => 'Foreign', 'status' => 'active', 'timezone' => 'Asia/Ho_Chi_Minh', 'created_at' => now(), 'updated_at' => now()]);
        $customer = (int) DB::table('commerce_customers')->insertGetId(['company_id' => $company, 'full_name' => 'Khách Scope', 'phone' => '0900000002', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
        $visit = app(BranchPosService::class)->checkIn($company, $branch, $customer, $actor);

        $this->actingAs($actor)->get(route('admin.branch-commerce.warehouse', ['company_id' => $company, 'branch_id' => $foreignBranch]))->assertNotFound();
        $help = app(AdminHelpRegistry::class)->resolve('admin.branch-commerce.warehouse', $company);
        self::assertSame('branch-warehouse', $help['key']);
        self::assertFalse($help['fallback']);
        self::assertNotEmpty($help['menuFlow']);
        $this->expectException(RecordNotFoundException::class);
        app(BranchPosService::class)->addProduct($company, $visit, $variant, $location + 999, 1);
    }

    public function test_pos_full_reversal_preserves_original_receipt_restocks_and_nets_finance_once(): void
    {
        [$actor, $company, $branch, $variant, $location] = $this->runtime();
        $customer = (int) DB::table('commerce_customers')->insertGetId(['company_id' => $company, 'full_name' => 'Khách Reversal', 'phone' => '0900000003', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
        $pos = app(BranchPosService::class);
        $visit = $pos->checkIn($company, $branch, $customer, $actor);
        $pos->addProduct($company, $visit, $variant, $location, 3);
        $receipt = $pos->checkout($company, $visit, 'cash', $actor);
        $reversals = app(SaleReversalService::class);
        $reversal = $reversals->reverse($actor, $company, 'pos', $receipt, 'Khách trả toàn bộ hàng');

        self::assertSame($reversal, $reversals->reverse($actor, $company, 'pos', $receipt, 'Retry không tạo bản ghi mới'));
        $this->assertDatabaseHas('branch_pos_receipts', ['id' => $receipt, 'status' => 'paid', 'total_minor' => 1500000]);
        $this->assertDatabaseHas('commerce_sale_reversals', ['id' => $reversal, 'source_type' => 'pos', 'source_id' => $receipt, 'amount_minor' => 1500000, 'inventory_restocked' => true]);
        self::assertSame(10, (int) DB::table('inventory_balances')->where(['product_variant_id' => $variant, 'inventory_location_id' => $location])->value('on_hand'));
        self::assertSame(1, DB::table('inventory_movements')->where('source_type', 'sale_reversal')->count());
        self::assertSame(900000, (int) DB::table('inventory_movements')->where('source_type', 'sale_reversal')->value('movement_value_minor'));
        self::assertSame(2, DB::table('payroll_accounting_journals')->where('source_type', 'sale_reversal')->count());
        self::assertSame(1, DB::table('branch_management_revenues')->where('source_type', 'branch_pos')->count());
        $snapshot = app(BranchFinancialPerformanceService::class)->snapshot($company, now()->format('Y-m'));
        self::assertSame(0, $snapshot['totals']['revenue']);
        self::assertSame(1500000, $snapshot['totals']['reversals']);
    }

    /** @return array{User,int,int,int,int} */
    private function runtime(): array
    {
        $actor = User::factory()->create(['email_verified_at' => now()]);
        $actor->assignRole('Seller');
        $company = (int) DB::table('companies')->insertGetId(['uuid' => (string) Str::uuid(), 'code' => 'POS-CO', 'name' => 'POS Company', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('company_user_access')->insert(['company_id' => $company, 'user_id' => $actor->id, 'created_at' => now(), 'updated_at' => now()]);
        $branch = (int) DB::table('branches')->insertGetId(['company_id' => $company, 'code' => 'POS-BR', 'name' => 'POS Branch', 'status' => 'active', 'timezone' => 'Asia/Ho_Chi_Minh', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('branch_user_access')->insert(['branch_id' => $branch, 'user_id' => $actor->id, 'created_at' => now(), 'updated_at' => now()]);
        $profile = (int) DB::table('seller_profiles')->insertGetId(['company_id' => $company, 'user_id' => $actor->id, 'display_name' => 'POS Seller', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
        $store = (int) DB::table('stores')->insertGetId(['seller_profile_id' => $profile, 'name' => 'POS Store', 'slug' => 'pos-store', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
        $product = (int) DB::table('products')->insertGetId(['store_id' => $store, 'code' => 'POS-P', 'name' => 'Sản phẩm POS', 'slug' => 'san-pham-pos', 'base_price_minor' => 500000, 'currency' => 'VND', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
        $variant = (int) DB::table('product_variants')->insertGetId(['product_id' => $product, 'sku' => 'POS-SKU', 'name' => 'Mặc định', 'price_minor' => 500000, 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
        $location = (int) DB::table('inventory_locations')->insertGetId(['store_id' => $store, 'branch_id' => $branch, 'code' => 'BRANCH-'.$branch, 'name' => 'Kho POS', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('inventory_balances')->insert(['product_variant_id' => $variant, 'inventory_location_id' => $location, 'on_hand' => 10, 'reserved' => 0, 'inventory_value_minor' => 3000000, 'average_unit_cost_minor' => 300000, 'version' => 1, 'created_at' => now(), 'updated_at' => now()]);

        return [$actor, $company, $branch, $variant, $location];
    }
}
