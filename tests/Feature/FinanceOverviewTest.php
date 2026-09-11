<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

final class FinanceOverviewTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_overview_is_permission_and_tenant_scoped_and_contains_no_fabricated_completeness(): void
    {
        $company = $this->company('A');
        $foreign = $this->company('B');
        $user = User::factory()->create(['email_verified_at' => now()]);
        $user->givePermissionTo('payroll.accounting.view');
        DB::table('company_user_access')->insert(['company_id' => $company, 'user_id' => $user->id, 'created_at' => now(), 'updated_at' => now()]);

        $this->get(route('admin.finance.overview', ['company_id' => $company]))->assertRedirect();
        $this->actingAs($user)->get(route('admin.finance.overview', ['company_id' => $foreign]))->assertNotFound();
        $this->actingAs($user)->get(route('admin.finance.overview', ['company_id' => $company]))
            ->assertOk()
            ->assertSee('Tổng quan tài chính')
            ->assertSee('PARTIAL')
            ->assertSee('VAT: CONFIGURATION_REQUIRED')
            ->assertSee('Không lưu bảng KPI sao chép');
    }

    private function company(string $suffix): int
    {
        return (int) DB::table('companies')->insertGetId(['uuid' => (string) Str::uuid(), 'code' => 'FIN-OV-'.$suffix, 'name' => 'Finance Overview '.$suffix, 'country_code' => 'VN', 'timezone' => 'Asia/Ho_Chi_Minh', 'currency_code' => 'VND', 'currency' => 'VND', 'status' => 'active', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
    }
}
