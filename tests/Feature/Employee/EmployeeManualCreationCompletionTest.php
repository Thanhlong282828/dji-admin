<?php

namespace Tests\Feature\Employee;

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
use Tests\TestCase;

final class EmployeeManualCreationCompletionTest extends TestCase
{
    use RefreshDatabase;

    public function test_manual_create_initializes_account_assignment_and_lifecycle_and_renders_complete_tables(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $company = Company::query()->create(['uuid' => (string) Str::uuid(), 'code' => 'MANUAL', 'name' => 'Manual Employee Test', 'country_code' => 'VN', 'timezone' => 'Asia/Ho_Chi_Minh', 'currency_code' => 'VND', 'currency' => 'VND', 'status' => 'active', 'is_active' => true]);
        $unit = OrganizationUnit::query()->create(['company_id' => $company->id, 'code' => 'ENG', 'name' => 'Phòng Kỹ thuật', 'type' => 'department', 'effective_from' => '2026-01-01']);
        $position = JobPosition::query()->create(['company_id' => $company->id, 'organization_unit_id' => $unit->id, 'code' => 'BE', 'title' => 'Backend Developer', 'career_level' => 3, 'approved_headcount' => 10, 'budgeted_cost' => 0, 'is_critical' => false]);
        $lead = JobPosition::query()->create(['company_id' => $company->id, 'organization_unit_id' => $unit->id, 'code' => 'LEAD', 'title' => 'Backend Lead', 'career_level' => 5, 'approved_headcount' => 2, 'budgeted_cost' => 0, 'is_critical' => false]);
        $junior = JobPosition::query()->create(['company_id' => $company->id, 'organization_unit_id' => $unit->id, 'code' => 'JR', 'title' => 'Junior Developer', 'career_level' => 1, 'approved_headcount' => 4, 'budgeted_cost' => 0, 'is_critical' => false]);
        $actor = User::factory()->create(['email_verified_at' => now()]);
        $actor->assignRole('Super Admin');
        DB::table('company_user_access')->insert(['company_id' => $company->id, 'user_id' => $actor->id]);

        $response = $this->actingAs($actor)->post(route('admin.employees.store'), [
            'company_id' => $company->id,
            'employee_code' => 'NV-MANUAL-001',
            'full_name' => 'Nguyễn Nhân Viên Demo',
            'date_of_birth' => '1994-05-12',
            'gender' => 'female',
            'work_email' => 'manual.employee@example.test',
            'join_date' => '2026-08-01',
            'employment_status' => 'active',
            'employment_type' => 'full_time',
            'nationality' => 'VN',
            'organization_unit_id' => $unit->id,
            'job_position_id' => $position->id,
            'create_account' => '1',
            'account_email' => 'manual.login@example.test',
            'account_password' => 'ManualEmployee123!',
            'account_password_confirmation' => 'ManualEmployee123!',
            'account_role' => 'Employee',
            'account_access_enabled' => '1',
        ]);

        $employee = Employee::query()->where('employee_code', 'NV-MANUAL-001')->firstOrFail();
        $response->assertRedirect(route('admin.employees.show', ['employee' => $employee->id, 'company_id' => $company->id]));
        $this->assertDatabaseHas('employee_assignments', ['employee_id' => $employee->id, 'organization_unit_id' => $unit->id, 'job_position_id' => $position->id, 'assignment_type' => 'primary']);
        $this->assertDatabaseHas('employee_lifecycle_events', ['employee_id' => $employee->id, 'to_state' => 'active']);
        $this->assertDatabaseHas('employment_transitions', ['employee_id' => $employee->id, 'transition_type' => 'employee.hired']);
        $this->assertDatabaseHas('employee_positions_history', ['employee_id' => $employee->id, 'position_id' => $position->id]);
        self::assertTrue($employee->user?->hasRole('Employee') ?? false);

        $this->actingAs($actor)->get(route('admin.employees.index', ['company_id' => $company->id]))
            ->assertOk()->assertSee('Avatar')->assertSee('Đơn vị / Vị trí')->assertSee('Chức vụ')->assertSee('Loại tài khoản')->assertSee('Xóa (lưu trữ)')->assertSee('Backend Developer')->assertSee('Employee');
        $this->actingAs($actor)->get(route('admin.employee-lifecycle.index', ['company_id' => $company->id]))
            ->assertOk()->assertSee('Chức vụ')->assertSee('Backend Developer');
        $this->actingAs($actor)->get(route('admin.employee-lifecycle.show', ['employee' => $employee->id, 'company_id' => $company->id]))
            ->assertOk()->assertSee('Thăng chức')->assertSee('Giáng chức')->assertSee('Backend Developer')->assertDontSee('Lifecycle chưa được khởi tạo.');

        $this->actingAs($actor)->put(route('admin.employees.update', ['employee' => $employee->id]), [
            'company_id' => $company->id,
            'employee_code' => 'NV-MANUAL-001',
            'full_name' => 'Nguyễn Nhân Viên Demo',
            'date_of_birth' => '1994-05-12',
            'gender' => 'female',
            'work_email' => 'manual.employee@example.test',
            'join_date' => '2026-08-01',
            'employment_status' => 'active',
            'employment_type' => 'full_time',
            'nationality' => 'VN',
            'organization_unit_id' => $unit->id,
            'job_position_id' => $lead->id,
        ])->assertRedirect();
        $this->assertDatabaseHas('employee_lifecycle_events', ['employee_id' => $employee->id, 'event_type' => 'employee.promoted']);
        $this->assertDatabaseHas('employee_positions_history', ['employee_id' => $employee->id, 'position_id' => $lead->id]);
        $linkedUser = User::query()->findOrFail($employee->user_id);
        self::assertTrue($linkedUser->hasRole('Employee'));
        $this->actingAs($actor)->get(route('admin.employees.index', ['company_id' => $company->id]))->assertOk()->assertSee('Backend Lead');

        $current = $employee->assignments()->where('assignment_type', 'primary')->whereNull('effective_to')->firstOrFail();
        $tomorrow = CarbonImmutable::today()->addDay();
        $this->actingAs($actor)->post(route('admin.employee-lifecycle.demote', ['employee' => $employee->id]), [
            'company_id' => $company->id,
            'current_assignment_id' => $current->id,
            'organization_unit_id' => $unit->id,
            'position_id' => $junior->id,
            'effective_at' => $tomorrow->format('Y-m-d\TH:i'),
            'reason' => 'Điều chỉnh cấp bậc kiểm thử',
            'idempotency_key' => (string) Str::uuid(),
        ])->assertRedirect();
        CarbonImmutable::setTestNow($tomorrow->addHour());
        $this->actingAs($actor)->get(route('admin.employees.index', ['company_id' => $company->id]))->assertOk()->assertSee('Junior Developer');
        $this->actingAs($actor)->get(route('admin.employee-lifecycle.index', ['company_id' => $company->id]))->assertOk()->assertSee('Junior Developer');
        self::assertSame(1, $employee->assignments()->where('assignment_type', 'primary')->whereDate('effective_from', '<=', today())->where(fn ($query) => $query->whereNull('effective_to')->orWhereDate('effective_to', '>=', today()))->count());
        CarbonImmutable::setTestNow();

        $otherUnit = OrganizationUnit::query()->create(['company_id' => $company->id, 'code' => 'FIN', 'name' => 'Phòng Tài chính', 'type' => 'department', 'effective_from' => '2026-01-01']);
        $this->actingAs($actor)->from(route('admin.employees.create', ['company_id' => $company->id]))->post(route('admin.employees.store'), [
            'company_id' => $company->id,
            'employee_code' => 'NV-MISMATCH-001',
            'full_name' => 'Nhân viên sai đơn vị',
            'join_date' => '2026-08-01',
            'employment_status' => 'active',
            'employment_type' => 'full_time',
            'nationality' => 'VN',
            'organization_unit_id' => $otherUnit->id,
            'job_position_id' => $position->id,
        ])->assertSessionHasErrors('job_position_id');
        $this->assertDatabaseMissing('employees', ['employee_code' => 'NV-MISMATCH-001']);
    }
}
