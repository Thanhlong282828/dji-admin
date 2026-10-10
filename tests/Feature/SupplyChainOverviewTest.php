<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Dashboard\Application\Services\SupplyChainOverviewReadService;
use Tests\TestCase;

final class SupplyChainOverviewTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_overview_is_permission_and_company_scoped_and_uses_canonical_availability(): void
    {
        $company = $this->company('SCM-A');
        $foreign = $this->company('SCM-B');
        $user = User::factory()->create(['email_verified_at' => now()]);
        $user->givePermissionTo('inventory.view');
        DB::table('company_user_access')->insert(['company_id' => $company, 'user_id' => $user->id, 'created_at' => now(), 'updated_at' => now()]);

        $this->get(route('admin.supply-chain.index', ['company_id' => $company]))->assertRedirect();
        $this->actingAs($user)->get(route('admin.supply-chain.index', ['company_id' => $foreign]))->assertNotFound();
        $response = $this->actingAs($user)->get(route('admin.supply-chain.index', ['company_id' => $company]));
        $response->assertOk()->assertSee('Tổng quan chuỗi cung ứng')->assertSee('Trạng thái vận hành')->assertSee('NOT_CONNECTED')->assertSee('NOT_ENOUGH_EVIDENCE')->assertSee('erp-kpi-grid', false);
        self::assertSame(1, preg_match_all('/<h1(?:\s[^>]*)?>/i', $response->getContent()), 'Supply Chain must render exactly one page-level H1.');
        $response->assertSee(route('admin.seller.inventory', ['company_id' => $company]), false);
        $response->assertDontSee(route('admin.procurement.orders', ['company_id' => $company]), false);

        $user->givePermissionTo(['purchase-order.view', 'goods-receipt.view']);
        $authorized = $this->actingAs($user)->get(route('admin.supply-chain.index', ['company_id' => $company]));
        $authorized->assertOk()
            ->assertSee(route('admin.procurement.orders', ['company_id' => $company]), false)
            ->assertSee(route('admin.procurement.receipts', ['company_id' => $company]), false);

        $data = app(SupplyChainOverviewReadService::class)->read($company);
        self::assertSame((int) data_get($data, 'inventory.on_hand') - (int) data_get($data, 'inventory.reserved'), (int) data_get($data, 'inventory.available'));
    }

    private function company(string $code): int
    {
        return (int) DB::table('companies')->insertGetId(['uuid' => (string) Str::uuid(), 'code' => $code, 'name' => $code, 'country_code' => 'VN', 'timezone' => 'Asia/Ho_Chi_Minh', 'currency_code' => 'VND', 'currency' => 'VND', 'status' => 'active', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
    }
}
