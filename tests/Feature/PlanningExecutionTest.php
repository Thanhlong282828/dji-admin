<?php

namespace Tests\Feature;

use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Company\Domain\Models\Company;
use Modules\Employee\Domain\Models\Employee;
use Modules\Organization\Domain\Models\JobPosition;
use Modules\Organization\Domain\Models\OrganizationUnit;
use Modules\Performance\Application\DTOs\PlanningExecutionFilter;
use Modules\Performance\Application\DTOs\PlanningFilter;
use Modules\Performance\Domain\Services\PlanningExecutionService;
use Tests\TestCase;

final class PlanningExecutionTest extends TestCase
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
        $this->get(route('admin.planning.execution', ['company_id' => $company->id]))->assertRedirect();
        $this->actingAs($this->user($company, []))->get(route('admin.planning.execution', ['company_id' => $company->id]))->assertForbidden();
        $this->actingAs($this->user($company, ['analytics.view']))->get(route('admin.planning.execution', ['company_id' => $foreign->id]))->assertNotFound();
    }

    public function test_execution_is_read_only_and_financial_values_are_absent_without_salary_permission(): void
    {
        $company = $this->company('C');
        $this->position($company, 3, 10, '25123456.00');
        $before = ['runs' => DB::table('payroll_runs')->count(), 'positions' => DB::table('job_positions')->count()];

        $this->actingAs($this->user($company, ['analytics.view']))->get(route('admin.planning.execution', ['company_id' => $company->id, 'period' => '2026-08', 'reforecast_headcount_delta' => 3]))
            ->assertOk()->assertSee('Planning Execution')->assertSee('Reforecast HC v2')->assertDontSee('25123456');

        self::assertSame($before, ['runs' => DB::table('payroll_runs')->count(), 'positions' => DB::table('job_positions')->count()]);
    }

    public function test_authorized_export_is_aggregate_and_formula_safe(): void
    {
        $company = $this->company('D');
        $this->position($company, 4, 5, '30000000.00', '=Injected');
        $user = $this->user($company, ['analytics.view', 'analytics.export', 'payroll.assignment.view']);

        $response = $this->actingAs($user)->get(route('admin.planning.execution.export', ['company_id' => $company->id, 'period' => '2026-08']));
        $response->assertOk()->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $csv = $response->streamedContent();
        self::assertStringContainsString("'=Injected", $csv);
        self::assertStringNotContainsString('employee_code', $csv);
    }

    public function test_query_count_remains_bounded_with_fifty_more_employees(): void
    {
        $company = $this->company('Q');
        [, $position] = $this->position($company, 3, 100, '20000000.00');
        $baselineEmployee = Employee::query()->create(['company_id' => $company->id, 'employee_code' => 'EXEC-BASE', 'full_name' => 'Execution Baseline Employee', 'nationality' => 'VN', 'join_date' => '2026-01-01', 'employment_status' => 'active', 'employment_type' => 'full_time']);
        DB::table('employee_assignments')->insert(['employee_id' => $baselineEmployee->id, 'organization_unit_id' => $position->organization_unit_id, 'job_position_id' => $position->id, 'assignment_type' => 'primary', 'allocation_percent' => 100, 'effective_from' => '2026-01-01', 'created_at' => now(), 'updated_at' => now()]);
        $filter = new PlanningExecutionFilter(new PlanningFilter((int) $company->id, new CarbonImmutable('2026-08-01'), null, null, null, null), 0, 0);
        $service = app(PlanningExecutionService::class);
        Cache::flush();
        DB::enableQueryLog();
        DB::flushQueryLog();
        $service->build($filter, true);
        $before = count(DB::getQueryLog());
        foreach (range(1, 50) as $number) {
            $employee = Employee::query()->create(['company_id' => $company->id, 'employee_code' => 'EXEC-'.$number, 'full_name' => 'Execution Employee '.$number, 'nationality' => 'VN', 'join_date' => '2026-01-01', 'employment_status' => 'active', 'employment_type' => 'full_time']);
            DB::table('employee_assignments')->insert(['employee_id' => $employee->id, 'organization_unit_id' => $position->organization_unit_id, 'job_position_id' => $position->id, 'assignment_type' => 'primary', 'allocation_percent' => 100, 'effective_from' => '2026-01-01', 'created_at' => now(), 'updated_at' => now()]);
        }
        Cache::flush();
        DB::flushQueryLog();
        $service->build($filter, true);
        $after = count(DB::getQueryLog());
        foreach (range(51, 100) as $number) {
            $employee = Employee::query()->create(['company_id' => $company->id, 'employee_code' => 'EXEC-'.$number, 'full_name' => 'Execution Employee '.$number, 'nationality' => 'VN', 'join_date' => '2026-01-01', 'employment_status' => 'active', 'employment_type' => 'full_time']);
            DB::table('employee_assignments')->insert(['employee_id' => $employee->id, 'organization_unit_id' => $position->organization_unit_id, 'job_position_id' => $position->id, 'assignment_type' => 'primary', 'allocation_percent' => 100, 'effective_from' => '2026-01-01', 'created_at' => now(), 'updated_at' => now()]);
        }
        Cache::flush();
        DB::flushQueryLog();
        $service->build($filter, true);
        $afterGrowth = count(DB::getQueryLog());
        DB::disableQueryLog();
        self::assertLessThanOrEqual($after, $afterGrowth, "Query growth must remain O(1): cold={$before}, volume50={$after}, volume100={$afterGrowth}");
        // C4 Commerce quality ownership checks add fixed work, never per Employee.
        // Finance plus Payment/Fulfillment/Seller-Company and POS receipt/inventory movement
        // reconciliation plus Product-media, Recruitment-hire and Procurement/accounting
        // invariants remain fixed.
        // Three Treasury/close Data Quality invariants are fixed per request and do not
        // scale with Employee volume; equality above remains the governing regression.
        // Four fixed HR separation/accounting-adjustment integrity rules add four
        // bounded queries and remain independent of Employee volume.
        // Sell-side ERP adds five fixed Sales/AR integrity checks and one fixed
        // canonical Sales summary query. Equality above remains the N+1 gate.
        // DMS contributes two fixed integrity queries (current version and SHA-256).
        // Three cross-domain source rules add nine fixed lookups, independent of volume.
        // Procurement realization adds three fixed grouped DQ queries,
        // while the before/after growth assertion above remains the N+1 guard.
        // Treasury opening-balance lineage/posting adds two fixed DQ queries.
        // Treasury internal-transfer integrity adds three grouped queries, fixed
        // per request and independent of Employee volume.
        // Customer Credit Note adds five fixed grouped DQ checks; the growth gate above
        // still proves none of them scales with Employee volume.
    }

    private function company(string $suffix): Company
    {
        return Company::query()->create(['uuid' => (string) Str::uuid(), 'code' => 'EXEC-'.$suffix, 'name' => 'Execution '.$suffix, 'country_code' => 'VN', 'timezone' => 'Asia/Ho_Chi_Minh', 'currency_code' => 'VND', 'currency' => 'VND', 'status' => 'active', 'is_active' => true]);
    }

    /** @return array{OrganizationUnit,JobPosition} */
    private function position(Company $company, int $level, int $headcount, string $budget, string $title = 'Developer'): array
    {
        $unit = OrganizationUnit::query()->create(['company_id' => $company->id, 'type' => 'department', 'code' => 'EXEC-U-'.$company->id, 'name' => 'Engineering', 'effective_from' => '2026-01-01']);
        $position = JobPosition::query()->create(['company_id' => $company->id, 'organization_unit_id' => $unit->id, 'code' => 'EXEC-P-'.$company->id, 'title' => $title, 'career_level' => $level, 'approved_headcount' => $headcount, 'budgeted_cost' => $budget]);

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
