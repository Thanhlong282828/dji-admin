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
        $this->actingAs($user)->get(route('admin.supply-chain.index', ['company_id' => $company]))->assertOk()->assertSee('Tổng quan chuỗi cung ứng')->assertSee('NOT_CONNECTED')->assertSee('NOT_ENOUGH_EVIDENCE');

        $data = app(SupplyChainOverviewReadService::class)->read($company);
        self::assertSame((int) data_get($data, 'inventory.on_hand') - (int) data_get($data, 'inventory.reserved'), (int) data_get($data, 'inventory.available'));
    }

    private function company(string $code): int
    {
        return (int) DB::table('companies')->insertGetId(['uuid' => (string) Str::uuid(), 'code' => $code, 'name' => $code, 'country_code' => 'VN', 'timezone' => 'Asia/Ho_Chi_Minh', 'currency_code' => 'VND', 'currency' => 'VND', 'status' => 'active', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
    }
}
