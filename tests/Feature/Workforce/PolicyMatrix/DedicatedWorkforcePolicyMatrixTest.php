<?php

namespace Tests\Feature\Workforce\PolicyMatrix;

use App\Models\User;
use App\Policies\Workforce\AttendanceAdjustmentPolicy;
use App\Policies\Workforce\AttendancePeriodPolicy;
use App\Policies\Workforce\AttendanceRecordPolicy;
use App\Policies\Workforce\EmployeeLeavePolicyAssignmentPolicy;
use App\Policies\Workforce\EmployeeOvertimePolicyAssignmentPolicy;
use App\Policies\Workforce\EmployeeShiftAssignmentPolicy;
use App\Policies\Workforce\HolidayPolicy;
use App\Policies\Workforce\LeaveAttachmentPolicy;
use App\Policies\Workforce\LeaveBalancePolicy;
use App\Policies\Workforce\LeavePolicyManagementPolicy;
use App\Policies\Workforce\LeaveRequestPolicy;
use App\Policies\Workforce\OvertimePolicyManagementPolicy;
use App\Policies\Workforce\OvertimeReconciliationPolicy;
use App\Policies\Workforce\OvertimeRequestPolicy;
use App\Policies\Workforce\PayrollInputPolicy;
use App\Policies\Workforce\PayrollPeriodPolicy;
use App\Policies\Workforce\ShiftPolicy;
use App\Policies\Workforce\WorkCalendarPolicy;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Modules\Company\Domain\Models\Company;
use Modules\Employee\Domain\Models\Employee;
use ReflectionClass;
use Tests\TestCase;

final class DedicatedWorkforcePolicyMatrixTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_all_dedicated_policy_contracts_exist_with_required_action_methods(): void
    {
        $matrix = [
            WorkCalendarPolicy::class => ['viewAny', 'view', 'create', 'update', 'activate', 'deactivate', 'archive', 'manageDays', 'export'],
            HolidayPolicy::class => ['viewAny', 'view', 'create', 'update', 'archive', 'export'],
            ShiftPolicy::class => ['viewAny', 'view', 'create', 'update', 'activate', 'deactivate', 'archive', 'manageBreakRules', 'export'],
            EmployeeShiftAssignmentPolicy::class => ['viewAny', 'view', 'assign', 'change', 'end', 'export'],
            AttendanceRecordPolicy::class => ['viewAny', 'view', 'viewOwn', 'checkIn', 'checkOut', 'submit', 'approve', 'reject', 'export'],
            AttendanceAdjustmentPolicy::class => ['view', 'create', 'update', 'submit', 'approve', 'reject', 'cancel'],
            AttendancePeriodPolicy::class => ['viewReadiness', 'lock'],
            LeavePolicyManagementPolicy::class => ['viewAny', 'view', 'create', 'update', 'activate', 'deactivate', 'assign'],
            EmployeeLeavePolicyAssignmentPolicy::class => ['view', 'assign', 'change', 'end'],
            LeaveRequestPolicy::class => ['viewAny', 'view', 'viewOwn', 'create', 'update', 'submit', 'approve', 'reject', 'cancel', 'withdraw', 'export'],
            LeaveBalancePolicy::class => ['viewOwn', 'viewAny', 'viewLedger', 'adjust', 'export'],
            LeaveAttachmentPolicy::class => ['upload', 'replace', 'remove', 'download'],
            OvertimePolicyManagementPolicy::class => ['viewAny', 'view', 'create', 'update', 'activate', 'deactivate', 'assign'],
            EmployeeOvertimePolicyAssignmentPolicy::class => ['view', 'assign', 'change', 'end'],
            OvertimeRequestPolicy::class => ['viewAny', 'view', 'viewOwn', 'create', 'update', 'submit', 'approve', 'reject', 'cancel', 'export'],
            OvertimeReconciliationPolicy::class => ['viewCalculation', 'reconcile'],
            PayrollInputPolicy::class => ['viewAny', 'viewReadiness', 'generate', 'viewSnapshot', 'export'],
            PayrollPeriodPolicy::class => ['view', 'lock'],
        ];

        foreach ($matrix as $policy => $methods) {
            $reflection = new ReflectionClass($policy);
            self::assertFalse($reflection->isAbstract());
            foreach ($methods as $method) {
                self::assertTrue($reflection->hasMethod($method), $policy.'::'.$method.' is missing.');
                self::assertTrue($reflection->getMethod($method)->isPublic());
            }
        }
    }

    public function test_registered_policy_gates_enforce_permission_and_company_access(): void
    {
        [$company, $employee, $allowed] = $this->context('POL-A');
        [,,$missingPermission] = $this->context('POL-B');
        $allowed->givePermissionTo(['work-calendar.manage', 'shift.manage', 'shift.assign', 'attendance.view', 'attendance.approve', 'attendance.lock', 'leave.manage-policy', 'leave.adjust-balance', 'overtime.manage-policy', 'overtime.approve', 'payroll-period.view', 'payroll-input.generate', 'payroll-period.lock']);
        $abilities = [
            ['work-calendar.create-record', [$company->id]], ['shift.create-record', [$company->id]], ['shift-assignment.assign', [$company->id, $employee->id, $this->shift($company->id)]],
            ['attendance.view-any', [$company->id]], ['leave-policy.create-record', [$company->id]], ['leave-balance.adjust-record', [$company->id, $employee->id]], ['overtime-policy.create-record', [$company->id]], ['payroll-input.view-any', [$company->id]],
        ];
        foreach ($abilities as [$ability, $arguments]) {
            self::assertTrue(Gate::forUser($allowed)->allows($ability, $arguments), $ability.' should allow the scoped actor.');
            self::assertFalse(Gate::forUser($missingPermission)->allows($ability, $arguments), $ability.' must deny missing permission/company access.');
        }
    }

    public function test_wrong_state_and_locked_state_are_denied_before_command_dispatch(): void
    {
        [$company, $employee, $owner] = $this->context('STATE');
        $approver = User::factory()->create();
        DB::table('company_user_access')->insert(['company_id' => $company->id, 'user_id' => $approver->id]);
        $approver->givePermissionTo(['attendance.approve', 'attendance.lock', 'leave.approve', 'overtime.approve', 'payroll-input.generate', 'payroll-period.lock']);
        $record = $this->attendance($company->id, $employee->id, 'submitted', true);
        $period = $this->period($company->id, 'locked');
        [$leaveRequest, $overtimeRequest] = $this->workflowRequests($company->id, $employee->id, 'draft');

        self::assertFalse(Gate::forUser($approver)->allows('attendance.approve-record', [$company->id, $record]));
        self::assertFalse(Gate::forUser($approver)->allows('attendance-period.lock-record', [$company->id, $period]));
        self::assertFalse(Gate::forUser($approver)->allows('payroll-input.generate-record', [$company->id, $period]));
        self::assertFalse(Gate::forUser($approver)->allows('leave-request.approve-record', [$company->id, $leaveRequest]));
        self::assertFalse(Gate::forUser($approver)->allows('overtime-request.approve-record', [$company->id, $overtimeRequest]));

        $before = ['leave' => DB::table('leave_request_histories')->count(), 'overtime' => DB::table('overtime_request_histories')->count(), 'activity' => DB::table('activity_log')->count(), 'notification' => DB::table('notification_deliveries')->count()];
        $this->actingAs($approver)->patch("/admin/leave/{$leaveRequest}/approved", ['company_id' => $company->id, 'version' => 1])->assertForbidden();
        $this->actingAs($approver)->put("/admin/overtime/request.approve/{$overtimeRequest}", ['company_id' => $company->id, 'version' => 1, 'idempotency_key' => (string) Str::uuid()])->assertForbidden();
        self::assertSame($before, ['leave' => DB::table('leave_request_histories')->count(), 'overtime' => DB::table('overtime_request_histories')->count(), 'activity' => DB::table('activity_log')->count(), 'notification' => DB::table('notification_deliveries')->count()]);
        self::assertSame('draft', DB::table('leave_requests')->where('id', $leaveRequest)->value('status'));
        self::assertSame('draft', DB::table('overtime_requests')->where('id', $overtimeRequest)->value('status'));
        self::assertNotSame(0, $owner->id);
    }

    /** @return array{Company,Employee,User} */
    private function context(string $code): array
    {
        $company = Company::query()->create(['uuid' => (string) Str::uuid(), 'code' => $code, 'name' => 'Schema '.$code, 'country_code' => 'VN', 'timezone' => 'Asia/Ho_Chi_Minh', 'currency_code' => 'VND', 'currency' => 'VND', 'status' => 'active', 'is_active' => true]);
        $user = User::factory()->create();
        DB::table('company_user_access')->insert(['company_id' => $company->id, 'user_id' => $user->id]);
        $employee = Employee::query()->create(['company_id' => $company->id, 'user_id' => $user->id, 'employee_code' => $code, 'full_name' => 'Employee '.$code, 'nationality' => 'VN', 'join_date' => '2026-01-01', 'employment_status' => 'active', 'employment_type' => 'full_time']);

        return [$company, $employee, $user];
    }

    private function shift(int $company): int
    {
        return (int) DB::table('work_shifts')->insertGetId(['company_id' => $company, 'code' => 'S-'.Str::upper(Str::random(5)), 'name' => 'Shift', 'timezone' => 'Asia/Ho_Chi_Minh', 'starts_at' => '08:00', 'ends_at' => '17:00', 'crosses_midnight' => false, 'planned_work_minutes' => 480, 'grace_in_minutes' => 0, 'grace_out_minutes' => 0, 'rounding_rule' => 'none', 'minimum_half_day_minutes' => 240, 'minimum_full_day_minutes' => 480, 'status' => 'active', 'rules' => '{}', 'version' => 1, 'created_at' => now(), 'updated_at' => now()]);
    }

    private function attendance(int $company, int $employee, string $approval, bool $locked): int
    {
        return (int) DB::table('attendance_records')->insertGetId(['company_id' => $company, 'employee_id' => $employee, 'work_date' => '2027-01-15', 'scheduled_minutes' => 480, 'worked_minutes' => 480, 'break_minutes' => 0, 'late_minutes' => 0, 'early_leave_minutes' => 0, 'overtime_minutes' => 0, 'attendance_status' => 'present', 'presence_status' => 'present', 'approval_status' => $approval, 'calculation_status' => 'calculated', 'calculation_version' => 1, 'version' => 1, 'is_locked' => $locked, 'created_at' => now(), 'updated_at' => now()]);
    }

    private function period(int $company, string $status): int
    {
        return (int) DB::table('payroll_periods')->insertGetId(['company_id' => $company, 'code' => 'P-'.Str::upper(Str::random(5)), 'name' => 'Period', 'period_start' => '2027-01-01', 'period_end' => '2027-01-31', 'cutoff_at' => '2027-02-01', 'payment_date' => '2027-02-05', 'status' => $status, 'version' => 1, 'created_at' => now(), 'updated_at' => now()]);
    }

    /** @return array{int,int} */
    private function workflowRequests(int $company, int $employee, string $status): array
    {
        $type = (int) DB::table('leave_types')->insertGetId(['company_id' => $company, 'code' => 'AL', 'name' => 'Annual', 'accrual_policy' => '{}', 'is_paid' => true, 'created_at' => now(), 'updated_at' => now()]);
        $leavePolicy = (int) DB::table('leave_policies')->insertGetId(['company_id' => $company, 'leave_type_id' => $type, 'code' => 'AL', 'name' => 'Annual', 'unit' => 'minutes', 'accrual_method' => 'annual_grant', 'annual_entitlement_minutes' => 5760, 'maximum_carry_forward_minutes' => 0, 'negative_balance_allowed' => false, 'requires_attachment' => false, 'status' => 'active', 'effective_from' => '2026-01-01', 'version' => 1, 'created_at' => now(), 'updated_at' => now()]);
        $leave = (int) DB::table('leave_requests')->insertGetId(['company_id' => $company, 'employee_id' => $employee, 'leave_type_id' => $type, 'leave_policy_id' => $leavePolicy, 'starts_at' => '2027-01-15', 'ends_at' => '2027-01-15', 'timezone' => 'Asia/Ho_Chi_Minh', 'requested_minutes' => 480, 'days' => 1, 'reason' => 'Policy state', 'status' => $status, 'version' => 1, 'idempotency_key' => (string) Str::uuid(), 'created_at' => now(), 'updated_at' => now()]);
        $overtimePolicy = (int) DB::table('overtime_policies')->insertGetId(['company_id' => $company, 'code' => 'OT', 'name' => 'Overtime', 'minimum_minutes' => 30, 'rounding_rule' => 'none', 'daily_limit_minutes' => 240, 'weekly_limit_minutes' => 600, 'requires_preapproval' => true, 'eligible_day_types' => '["working_day"]', 'status' => 'active', 'effective_from' => '2026-01-01', 'version' => 1, 'created_at' => now(), 'updated_at' => now()]);
        $overtime = (int) DB::table('overtime_requests')->insertGetId(['company_id' => $company, 'employee_id' => $employee, 'overtime_policy_id' => $overtimePolicy, 'work_date' => '2027-01-15', 'starts_at' => '2027-01-15 18:00:00', 'ends_at' => '2027-01-15 19:00:00', 'requested_minutes' => 60, 'approved_minutes' => 0, 'actual_minutes' => 0, 'payable_minutes' => 0, 'calculation_status' => 'pending', 'calculation_version' => 1, 'reason' => 'Policy state', 'status' => $status, 'version' => 1, 'idempotency_key' => (string) Str::uuid(), 'created_at' => now(), 'updated_at' => now()]);

        return [$leave, $overtime];
    }
}
