<?php

namespace Tests\Feature;

use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Company\Domain\Models\Company;
use Modules\Employee\Domain\Models\Employee;
use Modules\Performance\Application\DTOs\AnalyticsFilter;
use Modules\Performance\Domain\Services\EnterpriseAnalyticsService;
use Tests\TestCase;

final class EnterpriseAnalyticsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_dashboard_is_tenant_scoped_filterable_and_salary_is_server_side_protected(): void
    {
        $company = $this->company('A');
        $foreign = $this->company('B');
        $viewer = $this->user($company, ['analytics.view']);

        $response = $this->actingAs($viewer)->get(route('admin.analytics.workforce', [
            'company_id' => $company->id,
            'career_level' => 3,
            'date_from' => '2026-08-01',
            'date_to' => '2026-08-31',
        ]));
        $response->assertOk()->assertSee('Executive Summary')->assertSee('được bảo vệ bởi quyền')->assertDontSee('Average current compensation by department');
        $this->actingAs($viewer)->get(route('admin.analytics.workforce', ['company_id' => $foreign->id]))->assertNotFound();
        $this->actingAs($viewer)->get(route('admin.analytics.workforce', ['company_id' => $company->id, 'career_level' => 7]))->assertSessionHasErrors('career_level');
    }

    public function test_salary_authorized_dashboard_and_aggregate_export_are_available(): void
    {
        $company = $this->company('C');
        $user = $this->user($company, ['analytics.view', 'analytics.export', 'payroll.assignment.view']);

        $this->actingAs($user)->get(route('admin.analytics.workforce', ['company_id' => $company->id]))
            ->assertOk()->assertSee('Compensation theo đơn vị')->assertSee('Target Gross theo vị trí')->assertDontSee('employee_code');
        $this->actingAs($user)->get(route('admin.analytics.export', ['company_id' => $company->id]))
            ->assertOk()->assertHeader('content-type', 'text/csv; charset=UTF-8');
    }

    public function test_query_count_does_not_grow_with_employee_count(): void
    {
        $company = $this->company('Q');
        $filter = new AnalyticsFilter((int) $company->id, null, null, null, new CarbonImmutable('2026-08-01'), new CarbonImmutable('2026-08-31'));
        $service = app(EnterpriseAnalyticsService::class);
        DB::enableQueryLog();
        $service->build($filter, false);
        $before = count(DB::getQueryLog());

        foreach (range(1, 50) as $number) {
            Employee::query()->create(['company_id' => $company->id, 'employee_code' => 'AN-Q-'.$number, 'full_name' => 'Analytics Employee '.$number, 'nationality' => 'VN', 'join_date' => '2026-08-01', 'employment_status' => 'active', 'employment_type' => 'full_time']);
        }
        DB::flushQueryLog();
        $service->build($filter, false);
        $after = count(DB::getQueryLog());
        DB::disableQueryLog();

        self::assertSame($before, $after);
        self::assertLessThanOrEqual(55, $after);
    }

    private function company(string $suffix): Company
    {
        return Company::query()->create(['uuid' => (string) Str::uuid(), 'code' => 'EA-'.$suffix, 'name' => 'Enterprise Analytics '.$suffix, 'country_code' => 'VN', 'timezone' => 'Asia/Ho_Chi_Minh', 'currency_code' => 'VND', 'currency' => 'VND', 'status' => 'active', 'is_active' => true]);
    }

    /** @param list<string> $permissions */
    private function user(Company $company, array $permissions): User
    {
        $user = User::factory()->create();
        $user->givePermissionTo($permissions);
        DB::table('company_user_access')->insert(['company_id' => $company->id, 'user_id' => $user->id]);

        return $user;
    }
}
