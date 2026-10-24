<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Modules\Dashboard\Application\Services\EnterpriseAnalyticsReadService;
use Modules\Dashboard\Application\Services\EnterpriseExecutiveDashboardReadService;
use Modules\Dashboard\Application\Services\EnterpriseKpiCatalog;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

final class EnterpriseAnalyticsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_catalog_has_unique_codes_and_valid_drilldown_routes(): void
    {
        $definitions = app(EnterpriseKpiCatalog::class)->definitions();
        self::assertCount(count($definitions), array_unique(array_column($definitions, 'code')));
        foreach ($definitions as $definition) {
            self::assertTrue(Route::has($definition['route']), $definition['code']);
            self::assertNotSame('', $definition['source']);
        }
    }

    public function test_authorized_company_analytics_is_db_backed_and_query_count_is_bounded(): void
    {
        $company = $this->company('BI-A');
        $user = User::factory()->create();
        $permission = Permission::findOrCreate('analytics.view', 'web');
        $user->givePermissionTo($permission);
        DB::table('company_user_access')->insert(['company_id' => $company, 'user_id' => $user->id, 'created_at' => now(), 'updated_at' => now()]);
        DB::flushQueryLog();
        DB::enableQueryLog();
        $data = app(EnterpriseAnalyticsReadService::class)->read($company, null, '2026-01-01', '2026-12-31');
        $queries = count(DB::getQueryLog());
        DB::disableQueryLog();
        self::assertCount(12, $data['series']);
        self::assertNull(data_get($data, 'period.revenue_variance_bp'));
        self::assertLessThanOrEqual(80, $queries);
        $this->actingAs($user)->get(route('admin.analytics.enterprise', ['company_id' => $company, 'date_from' => '2026-01-01', 'date_to' => '2026-12-31']))->assertOk()->assertSee('Phân tích điều hành')->assertSee('Không tính % vì kỳ trước bằng 0');
    }

    public function test_foreign_company_and_branch_are_not_visible(): void
    {
        $owned = $this->company('BI-B');
        $foreign = $this->company('BI-C');
        $foreignBranch = (int) DB::table('branches')->insertGetId(['company_id' => $foreign, 'code' => 'BI-C-HQ', 'name' => 'BI C HQ', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
        $user = User::factory()->create();
        $user->givePermissionTo(Permission::findOrCreate('analytics.view', 'web'));
        DB::table('company_user_access')->insert(['company_id' => $owned, 'user_id' => $user->id, 'created_at' => now(), 'updated_at' => now()]);
        $this->actingAs($user)->get(route('admin.analytics.enterprise', ['company_id' => $foreign]))->assertNotFound();
        $this->actingAs($user)->get(route('admin.analytics.enterprise', ['company_id' => $owned, 'branch_id' => $foreignBranch]))->assertNotFound();
    }

    public function test_executive_dashboard_composes_canonical_reads_and_enforces_scope(): void
    {
        $owned = $this->company('EXEC-A');
        $foreign = $this->company('EXEC-B');
        $user = User::factory()->create();
        $user->givePermissionTo(Permission::findOrCreate('analytics.view', 'web'));
        DB::table('company_user_access')->insert(['company_id' => $owned, 'user_id' => $user->id, 'created_at' => now(), 'updated_at' => now()]);

        DB::flushQueryLog();
        DB::enableQueryLog();
        $data = app(EnterpriseExecutiveDashboardReadService::class)->read($owned, $user->id, null, '2026-01-01', '2026-06-30');
        $queries = count(DB::getQueryLog());
        DB::disableQueryLog();

        self::assertCount(8, $data['kpis']);
        self::assertCount(6, $data['financial_series']);
        self::assertSame('PARTIAL', $data['kpis'][3]['status']);
        self::assertSame('NOT_ENOUGH_EVIDENCE', $data['limitations']['inventory']);
        self::assertArrayHasKey('branch_profitability', $data['phase3']);
        self::assertArrayHasKey('budget', $data['phase3']);
        self::assertCount(4, $data['phase3']['outlook']['buckets']);
        self::assertSame('NOT_ENOUGH_EVIDENCE', $data['phase3']['outlook']['payroll_future_status']);
        self::assertSame('NOT_ENOUGH_EVIDENCE', $data['phase3']['outlook']['projected_closing_cash_status']);
        self::assertLessThanOrEqual(90, $queries);
        $this->actingAs($user)->get(route('admin.executive.index', ['company_id' => $owned, 'range' => '6m']))->assertOk()->assertSee('Executive Dashboard')->assertSee('Project Revenue/Billing')->assertSee('Hiệu quả chi nhánh')->assertSee('Ngân sách so với thực tế')->assertSee('AR / AP sắp đến hạn')->assertDontSee('Mở báo cáo chi nhánh')->assertDontSee('Mở Budget');
        $this->actingAs($user)->get(route('admin.executive.index', ['company_id' => $foreign]))->assertNotFound();
    }

    private function company(string $code): int
    {
        return (int) DB::table('companies')->insertGetId(['uuid' => (string) Str::uuid(), 'code' => $code, 'name' => $code, 'country_code' => 'VN', 'timezone' => 'Asia/Ho_Chi_Minh', 'currency_code' => 'VND', 'currency' => 'VND', 'status' => 'active', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
    }
}
