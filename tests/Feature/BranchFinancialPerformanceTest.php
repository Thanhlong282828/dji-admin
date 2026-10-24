<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Company\Domain\Models\Company;
use Modules\Finance\Application\Services\BranchFinancialPerformanceService;
use Tests\TestCase;

final class BranchFinancialPerformanceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_management_p_and_l_uses_only_eligible_ledgers_and_integer_math(): void
    {
        [$company, $branch, $user] = $this->context();
        DB::table('branch_management_revenues')->insert([
            ['company_id' => $company->id, 'branch_id' => $branch, 'reference' => 'REV-OK', 'amount_minor' => 100_000_000, 'recognized_on' => '2026-08-15', 'status' => 'recognized', 'created_at' => now(), 'updated_at' => now()],
            ['company_id' => $company->id, 'branch_id' => $branch, 'reference' => 'REV-PENDING', 'amount_minor' => 900_000_000, 'recognized_on' => '2026-08-15', 'status' => 'pending', 'created_at' => now(), 'updated_at' => now()],
        ]);
        DB::table('branch_operating_expenses')->insert(['company_id' => $company->id, 'branch_id' => $branch, 'reference' => 'EXP-1', 'category' => 'utilities', 'amount_minor' => 10_000_000, 'incurred_on' => '2026-08-10', 'status' => 'approved', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('branch_rent_contracts')->insert(['company_id' => $company->id, 'branch_id' => $branch, 'contract_reference' => 'RENT-1', 'monthly_amount_minor' => 20_000_000, 'effective_from' => '2026-01-01', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('branch_assets')->insert(['company_id' => $company->id, 'branch_id' => $branch, 'asset_code' => 'ASSET-1', 'name' => 'Thiết bị văn phòng', 'category' => 'equipment', 'acquisition_cost_minor' => 120_000_000, 'acquired_on' => '2026-01-01', 'useful_life_months' => 12, 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);

        $snapshot = app(BranchFinancialPerformanceService::class)->snapshot($company->id, '2026-08');
        self::assertSame(100_000_000, $snapshot['totals']['revenue']);
        self::assertSame(1, $snapshot['totals']['revenue_records']);
        self::assertSame(0, $snapshot['totals']['synthetic_revenue_records']);
        self::assertSame('management_ledger', $snapshot['rows'][0]['revenue_source_types']);
        self::assertSame(10_000_000, $snapshot['totals']['operating']);
        self::assertSame(20_000_000, $snapshot['totals']['rent']);
        self::assertSame(10_000_000, $snapshot['totals']['depreciation']);
        self::assertSame(60_000_000, $snapshot['totals']['profit']);

        $this->actingAs($user)->get('/admin/finance/branch-performance?company_id='.$company->id.'&month=2026-08')->assertOk()->assertSeeText('100.000.000 ₫')->assertSeeText('60.000.000 ₫')->assertSeeText('1 chứng từ');
        $this->actingAs($user)->get('/admin/finance/payroll-reconciliation?company_id='.$company->id)->assertOk()->assertSeeText('Đối soát Payroll và Tài chính');
        $this->actingAs($user)->get('/admin/finance/tax-dashboard?company_id='.$company->id.'&month=2026-08')->assertOk()->assertSeeText('CONFIGURATION_REQUIRED')->assertSeeText('Mở VAT Policy Workspace');
    }

    public function test_financial_dashboard_requires_salary_permission(): void
    {
        [$company, $branch, $user] = $this->context(false);
        $this->actingAs($user)->get('/admin/finance/branch-performance?company_id='.$company->id)->assertForbidden();
    }

    /** @return array{Company,int,User} */
    private function context(bool $financial = true): array
    {
        $company = Company::query()->create(['uuid' => (string) Str::uuid(), 'code' => 'FIN', 'name' => 'Finance Company', 'country_code' => 'VN', 'timezone' => 'Asia/Ho_Chi_Minh', 'currency_code' => 'VND', 'currency' => 'VND', 'status' => 'active', 'is_active' => true]);
        $user = User::factory()->create();
        if ($financial) {
            $user->givePermissionTo(['payroll.assignment.view', 'payroll.accounting.view', 'payroll.tax.view']);
        }
        DB::table('company_user_access')->insert(['company_id' => $company->id, 'user_id' => $user->id]);
        $branch = (int) DB::table('branches')->insertGetId(['company_id' => $company->id, 'code' => 'HCM', 'name' => 'HCM Branch', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);

        return [$company, $branch, $user];
    }
}
