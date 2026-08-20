<?php

namespace Tests\Feature\Employee;

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Company\Domain\Models\Company;
use Modules\Employee\Domain\Models\Employee;
use Modules\Organization\Domain\Models\JobPosition;
use Modules\Organization\Domain\Models\OrganizationUnit;
use Tests\TestCase;

final class EmployeeLifecycleCompensationTest extends TestCase
{
    use RefreshDatabase;

    public function test_lifecycle_compensation_is_batched_permission_protected_and_projected(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $company = Company::query()->create(['uuid' => (string) Str::uuid(), 'code' => 'COMP', 'name' => 'Compensation Test', 'country_code' => 'VN', 'timezone' => 'Asia/Ho_Chi_Minh', 'currency_code' => 'VND', 'currency' => 'VND', 'status' => 'active', 'is_active' => true]);
        $unit = OrganizationUnit::query()->create(['company_id' => $company->id, 'code' => 'ENG', 'name' => 'Phòng Backend', 'type' => 'department', 'effective_from' => '2026-01-01']);
        $position = JobPosition::query()->create(['company_id' => $company->id, 'organization_unit_id' => $unit->id, 'code' => 'BE', 'title' => 'Backend Developer', 'career_level' => 3, 'approved_headcount' => 5, 'budgeted_cost' => 0, 'is_critical' => false]);
        JobPosition::query()->create(['company_id' => $company->id, 'organization_unit_id' => $unit->id, 'code' => 'LEAD', 'title' => 'Backend Lead', 'career_level' => 5, 'approved_headcount' => 2, 'budgeted_cost' => 0, 'is_critical' => false]);
        $employee = Employee::query()->create(['company_id' => $company->id, 'employee_code' => 'COMP-001', 'full_name' => 'Nhân viên Compensation', 'join_date' => '2026-01-01', 'employment_status' => 'active', 'employment_type' => 'full_time', 'nationality' => 'VN']);
        DB::table('employee_assignments')->insert(['employee_id' => $employee->id, 'organization_unit_id' => $unit->id, 'job_position_id' => $position->id, 'assignment_type' => 'primary', 'allocation_percent' => 100, 'effective_from' => '2026-01-01', 'created_at' => now(), 'updated_at' => now()]);
        $actor = User::factory()->create(['email_verified_at' => now()]);
        DB::table('company_user_access')->insert(['company_id' => $company->id, 'user_id' => $actor->id]);
        $actor->givePermissionTo(['hr.lifecycle.view', 'hr.lifecycle.promote']);
        $componentIds = ['BASE' => 0, 'KPI' => 0, 'ALLOWANCE' => 0];
        foreach ([['BASE', 'Base', 10], ['KPI', 'KPI', 20], ['ALLOWANCE', 'Allowance', 30]] as [$code, $name, $order]) {
            $componentIds[$code] = DB::table('salary_components')->insertGetId(['company_id' => $company->id, 'code' => $code, 'name' => $name, 'category' => 'earning', 'calculation_type' => 'fixed', 'value_type' => 'money', 'currency_code' => 'VND', 'affects_gross' => true, 'affects_net' => true, 'display_order' => $order, 'status' => 'active', 'version' => 1, 'created_by' => $actor->id, 'created_at' => now(), 'updated_at' => now()]);
        }
        foreach ([['BASE', 25_000_000], ['KPI', 4_000_000], ['ALLOWANCE', 2_000_000]] as [$code, $amount]) {
            DB::table('employee_salary_component_assignments')->insert(['company_id' => $company->id, 'employee_id' => $employee->id, 'salary_component_id' => $componentIds[$code], 'amount_minor' => $amount, 'currency_code' => 'VND', 'effective_from' => '2026-01-01', 'source' => 'test', 'status' => 'active', 'version' => 1, 'idempotency_key' => (string) Str::uuid(), 'created_by' => $actor->id, 'created_at' => now(), 'updated_at' => now()]);
        }

        $this->actingAs($actor)->get(route('admin.employee-lifecycle.index', ['company_id' => $company->id]))->assertOk()->assertSee('•••••••• ₫')->assertDontSee('31.000.000');
        $actor->givePermissionTo('payroll.assignment.view');
        $this->actingAs($actor)->get(route('admin.employee-lifecycle.index', ['company_id' => $company->id]))->assertOk()->assertSee('25.000.000')->assertSee('31.000.000')->assertSee('Compensation Reference');
        $this->actingAs($actor)->get(route('admin.employee-lifecycle.show', ['employee' => $employee->id, 'company_id' => $company->id]))->assertOk()->assertSee('Compensation hiện tại')->assertSee('Projected Target Compensation')->assertSee('Backend Lead')->assertSee('EmployeeLifecycleController');
        $this->actingAs($actor)->get(route('admin.compensation-reference', ['company_id' => $company->id]))->assertOk()->assertSee('Backend Developer')->assertSee('Target Gross');
    }

    public function test_compensation_reference_requires_salary_permission(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $company = Company::query()->create(['uuid' => (string) Str::uuid(), 'code' => 'DENY', 'name' => 'Denied', 'country_code' => 'VN', 'timezone' => 'Asia/Ho_Chi_Minh', 'currency_code' => 'VND', 'currency' => 'VND', 'status' => 'active', 'is_active' => true]);
        $user = User::factory()->create(['email_verified_at' => now()]);
        DB::table('company_user_access')->insert(['company_id' => $company->id, 'user_id' => $user->id]);

        $this->actingAs($user)->get(route('admin.compensation-reference', ['company_id' => $company->id]))->assertForbidden();
    }
}
