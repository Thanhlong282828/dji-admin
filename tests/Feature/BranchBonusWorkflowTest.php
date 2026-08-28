<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\Payroll\BranchBonusService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Modules\Company\Domain\Models\Company;
use Modules\Employee\Domain\Models\Employee;
use Tests\TestCase;

final class BranchBonusWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_branch_leader_proposes_checker_approves_and_payroll_assignment_is_effective_dated(): void
    {
        [$company, $branch, $leader, $employee] = $this->context();
        $service = app(BranchBonusService::class);
        $id = $service->create($leader, [
            'company_id' => $company->id, 'branch_id' => $branch, 'employee_id' => $employee->id,
            'bonus_reference' => 'BONUS-2026-08-001', 'amount_minor' => 5_000_000,
            'reason_category' => 'performance', 'reason' => 'Hoàn thành KPI chi nhánh tháng', 'effective_period' => '2026-08-15',
        ]);
        $service->transition($leader, $company->id, $id, 'submit', 1, null);

        try {
            $leader->givePermissionTo('branch.bonus.approve');
            $service->transition($leader, $company->id, $id, 'approve', 2, null);
            self::fail('Maker tự duyệt phải bị chặn.');
        } catch (ValidationException $exception) {
            self::assertArrayHasKey('approval', $exception->errors());
        }

        $checker = User::factory()->create();
        $checker->assignRole('C&B');
        DB::table('company_user_access')->insert(['company_id' => $company->id, 'user_id' => $checker->id]);
        $service->transition($checker, $company->id, $id, 'approve', 2, 'Đã đối chiếu ngân sách');

        $this->assertDatabaseHas('branch_bonus_proposals', ['id' => $id, 'status' => 'approved', 'approved_by' => $checker->id]);
        $this->assertDatabaseHas('employee_salary_component_assignments', [
            'employee_id' => $employee->id, 'amount_minor' => 5_000_000, 'source' => 'branch_bonus',
            'effective_from' => '2026-08-01', 'effective_to' => '2026-08-31',
        ]);
        self::assertSame(3, DB::table('branch_bonus_histories')->where('branch_bonus_proposal_id', $id)->count());
    }

    public function test_leader_cannot_propose_bonus_for_employee_outside_branch(): void
    {
        [$company, $branch, $leader] = $this->context();
        $outside = Employee::query()->create(['company_id' => $company->id, 'employee_code' => 'OUTSIDE', 'full_name' => 'Outside Employee', 'nationality' => 'VN', 'join_date' => '2026-01-01', 'employment_status' => 'active', 'employment_type' => 'full_time']);

        $this->expectException(ValidationException::class);
        app(BranchBonusService::class)->create($leader, ['company_id' => $company->id, 'branch_id' => $branch, 'employee_id' => $outside->id, 'bonus_reference' => 'OUTSIDE-1', 'amount_minor' => 1_000_000, 'reason_category' => 'other', 'reason' => 'Không thuộc chi nhánh', 'effective_period' => '2026-08-01']);
    }

    /** @return array{Company,int,User,Employee} */
    private function context(): array
    {
        $company = Company::query()->create(['uuid' => (string) Str::uuid(), 'code' => 'BONUS', 'name' => 'Bonus Company', 'country_code' => 'VN', 'timezone' => 'Asia/Ho_Chi_Minh', 'currency_code' => 'VND', 'currency' => 'VND', 'status' => 'active', 'is_active' => true]);
        $leader = User::factory()->create();
        $leader->assignRole('Manager');
        DB::table('company_user_access')->insert(['company_id' => $company->id, 'user_id' => $leader->id]);
        $leaderEmployee = Employee::query()->create(['company_id' => $company->id, 'user_id' => $leader->id, 'employee_code' => 'LEADER', 'full_name' => 'Branch Leader', 'nationality' => 'VN', 'join_date' => '2026-01-01', 'employment_status' => 'active', 'employment_type' => 'full_time']);
        $branch = (int) DB::table('branches')->insertGetId(['company_id' => $company->id, 'leader_employee_id' => $leaderEmployee->id, 'code' => 'HCM', 'name' => 'HCM Branch', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
        $employeeUser = User::factory()->create();
        $employee = Employee::query()->create(['company_id' => $company->id, 'user_id' => $employeeUser->id, 'employee_code' => 'STAFF', 'full_name' => 'Branch Staff', 'nationality' => 'VN', 'join_date' => '2026-01-01', 'employment_status' => 'active', 'employment_type' => 'full_time']);
        DB::table('branch_user_access')->insert([['branch_id' => $branch, 'user_id' => $leader->id, 'created_at' => now(), 'updated_at' => now()], ['branch_id' => $branch, 'user_id' => $employeeUser->id, 'created_at' => now(), 'updated_at' => now()]]);
        DB::table('employee_branch_assignments')->insert(['company_id' => $company->id, 'employee_id' => $employee->id, 'branch_id' => $branch, 'assignment_type' => 'primary', 'effective_from' => '2026-01-01', 'source' => 'branch_access', 'created_at' => now(), 'updated_at' => now()]);

        return [$company, $branch, $leader, $employee];
    }
}
