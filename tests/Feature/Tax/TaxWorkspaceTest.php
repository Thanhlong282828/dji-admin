<?php

namespace Tests\Feature\Tax;

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Company\Domain\Models\Company;
use Tests\TestCase;

final class TaxWorkspaceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_workspace_exposes_empty_state_process_help_and_source_traceability(): void
    {
        [$company, $user] = $this->context();
        $user->givePermissionTo(['tax-policy.view', 'payroll.accounting.view']);

        $this->actingAs($user)
            ->get(route('admin.finance.tax-workspace', ['company_id' => $company->id, 'from' => '2026-09-01', 'to' => '2026-09-30']))
            ->assertOk()
            ->assertSeeText('Tax / VAT Policy Workspace')
            ->assertSeeText('No operating data available')
            ->assertSeeText('Tax / VAT Policy Flow')
            ->assertSeeText('View source evidence');
    }

    public function test_workspace_rejects_cross_tenant_company_scope(): void
    {
        [$company, $user] = $this->context();
        $other = Company::query()->create(['uuid' => (string) Str::uuid(), 'code' => 'TAX-B', 'name' => 'Tax Company B', 'country_code' => 'VN', 'timezone' => 'Asia/Ho_Chi_Minh', 'currency_code' => 'VND', 'currency' => 'VND', 'status' => 'active', 'is_active' => true]);
        $user->givePermissionTo('tax-policy.view');

        $this->actingAs($user)->get(route('admin.finance.tax-workspace', ['company_id' => $other->id]))->assertNotFound();
        self::assertNotSame($company->id, $other->id);
    }

    /** @return array{Company,User} */
    private function context(): array
    {
        $company = Company::query()->create(['uuid' => (string) Str::uuid(), 'code' => 'TAX-A', 'name' => 'Tax Company A', 'country_code' => 'VN', 'timezone' => 'Asia/Ho_Chi_Minh', 'currency_code' => 'VND', 'currency' => 'VND', 'status' => 'active', 'is_active' => true]);
        $user = User::factory()->create(['email_verified_at' => now()]);
        DB::table('company_user_access')->insert(['company_id' => $company->id, 'user_id' => $user->id, 'created_at' => now(), 'updated_at' => now()]);

        return [$company, $user];
    }
}
