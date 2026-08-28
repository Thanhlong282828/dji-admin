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
use Modules\Organization\Domain\Models\JobPosition;
use Modules\Organization\Domain\Models\OrganizationUnit;
use Modules\Performance\Application\DTOs\PlanningFilter;
use Modules\Performance\Domain\Services\ExecutivePlanningService;
use Tests\TestCase;

final class ExecutivePlanningTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_route_requires_auth_permission_and_tenant_access(): void
    {
        $company = $this->company('A');
        $foreign = $this->company('B');
        $this->get(route('admin.planning.executive', ['company_id' => $company->id]))->assertRedirect();
        $this->actingAs($this->user($company, []))->get(route('admin.planning.executive', ['company_id' => $company->id]))->assertForbidden();
        $viewer = $this->user($company, ['analytics.view']);
        $this->actingAs($viewer)->get(route('admin.planning.executive', ['company_id' => $foreign->id]))->assertNotFound();
    }

    public function test_cockpit_is_read_only_and_salary_is_omitted_server_side_without_permission(): void
    {
        $company = $this->company('C');
        [, $position] = $this->position($company, 3, 10, '25000000.00');
        $viewer = $this->user($company, ['analytics.view']);
        $before = ['runs' => DB::table('payroll_runs')->count(), 'items' => DB::table('payroll_items')->count(), 'positions' => DB::table('job_positions')->count()];

        $this->actingAs($viewer)->get(route('admin.planning.executive', ['company_id' => $company->id, 'period' => '2026-08']))
            ->assertOk()->assertSee('Executive Planning')->assertSee('Financial chart requires payroll.assignment.view')
            ->assertDontSee('25000000');

        self::assertSame($before, ['runs' => DB::table('payroll_runs')->count(), 'items' => DB::table('payroll_items')->count(), 'positions' => DB::table('job_positions')->count()]);
        self::assertSame(10, $position->approved_headcount);
    }

    public function test_authorized_cockpit_and_formula_safe_aggregate_export(): void
    {
        $company = $this->company('D');
        [, $position] = $this->position($company, 4, 5, '30000000.00', '=Injected');
        $user = $this->user($company, ['analytics.view', 'analytics.export', 'payroll.assignment.view']);

        $this->actingAs($user)->get(route('admin.planning.executive', ['company_id' => $company->id, 'period' => '2026-08']))
            ->assertOk()->assertSee('Budget Payroll')->assertSee('Cost Variance by Unit')->assertSee('30.000.000');
        $response = $this->actingAs($user)->get(route('admin.planning.export', ['company_id' => $company->id, 'period' => '2026-08']));
        $response->assertOk()->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $csv = $response->streamedContent();
        self::assertStringContainsString("'=Injected", $csv);
        self::assertStringNotContainsString('employee_code', $csv);
        self::assertSame('=Injected', $position->title);
    }

    public function test_query_count_is_bounded_when_employee_volume_grows(): void
    {
        $company = $this->company('Q');
        [, $position] = $this->position($company, 3, 100, '20000000.00');
        $filter = new PlanningFilter((int) $company->id, new CarbonImmutable('2026-08-01'), null, null, null, null);
        $service = app(ExecutivePlanningService::class);
        DB::enableQueryLog();
        $service->build($filter, true);
        $before = count(DB::getQueryLog());

        foreach (range(1, 50) as $number) {
            $employee = Employee::query()->create(['company_id' => $company->id, 'employee_code' => 'PLAN-'.$number, 'full_name' => 'Planning Employee '.$number, 'nationality' => 'VN', 'join_date' => '2026-01-01', 'employment_status' => 'active', 'employment_type' => 'full_time']);
            DB::table('employee_assignments')->insert(['employee_id' => $employee->id, 'organization_unit_id' => $position->organization_unit_id, 'job_position_id' => $position->id, 'assignment_type' => 'primary', 'allocation_percent' => 100, 'effective_from' => '2026-01-01', 'created_at' => now(), 'updated_at' => now()]);
        }
        DB::flushQueryLog();
        $service->build($filter, true);
        $after = count(DB::getQueryLog());
        DB::disableQueryLog();

        self::assertSame($before, $after);
        // Keep the current fixed read-model ceiling; before/after equality is the N+1 gate.
        // C4/C5 checks plus eight C6 Checkout/Order/Reservation checks remain fixed as Employee volume grows.
        self::assertLessThanOrEqual(55, $after);
    }

    private function company(string $suffix): Company
    {
        return Company::query()->create(['uuid' => (string) Str::uuid(), 'code' => 'PLAN-'.$suffix, 'name' => 'Planning '.$suffix, 'country_code' => 'VN', 'timezone' => 'Asia/Ho_Chi_Minh', 'currency_code' => 'VND', 'currency' => 'VND', 'status' => 'active', 'is_active' => true]);
    }

    /** @return array{OrganizationUnit,JobPosition} */
    private function position(Company $company, int $level, int $headcount, string $budget, string $title = 'Developer'): array
    {
        $unit = OrganizationUnit::query()->create(['company_id' => $company->id, 'type' => 'department', 'code' => 'PLAN-U-'.$company->id, 'name' => 'Engineering', 'effective_from' => '2026-01-01']);
        $position = JobPosition::query()->create(['company_id' => $company->id, 'organization_unit_id' => $unit->id, 'code' => 'PLAN-P-'.$company->id, 'title' => $title, 'career_level' => $level, 'approved_headcount' => $headcount, 'budgeted_cost' => $budget]);

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
