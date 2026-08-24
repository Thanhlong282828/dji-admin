<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Company\Domain\Models\Company;
use Modules\Organization\Domain\Models\JobPosition;
use Modules\Organization\Domain\Models\OrganizationUnit;
use Tests\TestCase;

final class EnterpriseForecastingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_forecasting_requires_auth_permission_and_tenant_access(): void
    {
        $company = $this->company('A');
        $foreign = $this->company('B');
        $this->get(route('admin.analytics.forecasting', ['company_id' => $company->id]))->assertRedirect();
        $without = $this->user($company, []);
        $this->actingAs($without)->get(route('admin.analytics.forecasting', ['company_id' => $company->id]))->assertForbidden();
        $viewer = $this->user($company, ['analytics.view']);
        $this->actingAs($viewer)->get(route('admin.analytics.forecasting', ['company_id' => $foreign->id]))->assertNotFound();
    }

    public function test_scenario_is_read_only_bounded_and_salary_is_server_side_masked(): void
    {
        $company = $this->company('C');
        [$unit, $position] = $this->position($company, 3);
        $viewer = $this->user($company, ['analytics.view']);
        $counts = ['employees' => DB::table('employees')->count(), 'assignments' => DB::table('employee_assignments')->count(), 'payroll' => DB::table('payroll_items')->count()];
        $response = $this->actingAs($viewer)->get(route('admin.analytics.forecasting', [
            'company_id' => $company->id,
            'forecast_month' => '2026-10',
            'organization_unit_id' => $unit->id,
            'scenario' => [['action' => 'add', 'position_id' => $position->id, 'quantity' => 5, 'effective_month' => '2026-10']],
        ]));
        $response->assertOk()->assertSee('Enterprise Workforce Forecasting')->assertSee('Ending headcount')->assertSee('Số tiền bị ẩn server-side')->assertDontSee('Monthly baseline:');
        self::assertSame($counts, ['employees' => DB::table('employees')->count(), 'assignments' => DB::table('employee_assignments')->count(), 'payroll' => DB::table('payroll_items')->count()]);

        $tooMany = array_fill(0, 51, ['action' => 'add', 'position_id' => $position->id, 'quantity' => 1, 'effective_month' => '2026-10']);
        $this->actingAs($viewer)->get(route('admin.analytics.forecasting', ['company_id' => $company->id, 'scenario' => $tooMany]))->assertSessionHasErrors('scenario');
    }

    public function test_authorized_aggregate_export_contains_assumptions_without_employee_detail(): void
    {
        $company = $this->company('D');
        [, $position] = $this->position($company, 4);
        $user = $this->user($company, ['analytics.view', 'analytics.export', 'payroll.assignment.view']);
        $response = $this->actingAs($user)->post(route('admin.analytics.forecasting.export'), [
            'company_id' => $company->id,
            'forecast_month' => '2026-10',
            'scenario' => [['action' => 'add', 'position_id' => $position->id, 'quantity' => 2, 'effective_month' => '2026-10']],
        ]);
        $response->assertOk()->assertHeader('content-type', 'text/csv; charset=UTF-8');
        self::assertStringNotContainsString('employee_code', $response->streamedContent());
    }

    public function test_scenario_a_and_b_are_compared_independently(): void
    {
        $company = $this->company('E');
        [, $position] = $this->position($company, 3);
        $user = $this->user($company, ['analytics.view', 'payroll.assignment.view']);

        $this->actingAs($user)->get(route('admin.analytics.forecasting', [
            'company_id' => $company->id,
            'forecast_month' => '2026-10',
            'scenario' => [
                ['scenario_name' => 'A', 'action' => 'add', 'position_id' => $position->id, 'quantity' => 2, 'effective_month' => '2026-10'],
                ['scenario_name' => 'B', 'action' => 'add', 'position_id' => $position->id, 'quantity' => 4, 'effective_month' => '2026-10'],
            ],
        ]))->assertOk()->assertSeeInOrder(['Scenario A', 'Headcount delta:', '>2<', 'Scenario B', 'Headcount delta:', '>4<'], false);
    }

    private function company(string $suffix): Company
    {
        return Company::query()->create(['uuid' => (string) Str::uuid(), 'code' => 'FC-'.$suffix, 'name' => 'Forecast '.$suffix, 'country_code' => 'VN', 'timezone' => 'Asia/Ho_Chi_Minh', 'currency_code' => 'VND', 'currency' => 'VND', 'status' => 'active', 'is_active' => true]);
    }

    /** @return array{OrganizationUnit,JobPosition} */
    private function position(Company $company, int $level): array
    {
        $unit = OrganizationUnit::query()->create(['company_id' => $company->id, 'type' => 'department', 'code' => 'UNIT-'.$company->id, 'name' => 'Engineering', 'effective_from' => '2026-01-01']);
        $position = JobPosition::query()->create(['company_id' => $company->id, 'organization_unit_id' => $unit->id, 'code' => 'POS-'.$company->id, 'title' => 'Developer', 'career_level' => $level, 'approved_headcount' => 10, 'budgeted_cost' => 0]);

        return [$unit, $position];
    }

    /** @param list<string> $permissions */
    private function user(Company $company, array $permissions): User
    {
        $user = User::factory()->create();
        if ($permissions !== []) {
            $user->givePermissionTo($permissions);
        }
        DB::table('company_user_access')->insert(['company_id' => $company->id, 'user_id' => $user->id]);

        return $user;
    }
}
