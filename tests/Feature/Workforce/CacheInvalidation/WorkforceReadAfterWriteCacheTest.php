<?php

namespace Tests\Feature\Workforce\CacheInvalidation;

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Attendance\Application\Commands\ManageOvertimeCommand;
use Modules\Attendance\Application\Commands\ManageWorkforceResourceCommand;
use Modules\Attendance\Application\Commands\ProcessAttendanceWorkflowCommand;
use Modules\Attendance\Domain\Repositories\AttendanceWorkflowRepositoryInterface;
use Modules\Attendance\Domain\Repositories\OvertimeReadRepositoryInterface;
use Modules\Attendance\Domain\Repositories\WorkforceReadRepositoryInterface;
use Modules\Attendance\Domain\Services\AttendanceWorkflowService;
use Modules\Attendance\Domain\Services\OvertimeManagementService;
use Modules\Attendance\Domain\Services\WorkforceManagementService;
use Modules\Company\Domain\Models\Company;
use Modules\Employee\Domain\Models\Employee;
use Modules\Leave\Application\Commands\ManageLeaveCommand;
use Modules\Leave\Domain\Repositories\LeaveReadRepositoryInterface;
use Modules\Leave\Domain\Services\LeaveManagementService;
use Modules\Payroll\Domain\Repositories\PayrollDeliveryReadRepositoryInterface;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

#[Group('workforce-cache')]
final class WorkforceReadAfterWriteCacheTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_workforce_attendance_leave_and_overtime_queries_return_fresh_state_after_events(): void
    {
        [$company, $employee, $user] = $this->context('CACHE-A');
        [$other] = $this->context('CACHE-B');
        $calendar = (int) DB::table('work_calendars')->insertGetId(['company_id' => $company->id, 'code' => 'CAL', 'name' => 'Old Calendar', 'timezone' => 'Asia/Ho_Chi_Minh', 'weekly_work_minutes' => 2400, 'status' => 'active', 'effective_from' => '2026-01-01', 'version' => 1, 'created_at' => now(), 'updated_at' => now()]);
        $otherCalendar = (int) DB::table('work_calendars')->insertGetId(['company_id' => $other->id, 'code' => 'CAL', 'name' => 'Other Cached', 'timezone' => 'Asia/Ho_Chi_Minh', 'weekly_work_minutes' => 2400, 'status' => 'active', 'effective_from' => '2026-01-01', 'version' => 1, 'created_at' => now(), 'updated_at' => now()]);
        $workforce = app(WorkforceReadRepositoryInterface::class);
        self::assertSame('Old Calendar', $workforce->management($company->id, 'calendars', null)->rows[0]['name']);
        self::assertSame('Other Cached', $workforce->management($other->id, 'calendars', null)->rows[0]['name']);
        app(WorkforceManagementService::class)->execute(new ManageWorkforceResourceCommand('calendar', 'updated', $company->id, $calendar, ['code' => 'CAL', 'name' => 'Fresh Calendar', 'timezone' => 'Asia/Ho_Chi_Minh', 'weekly_work_minutes' => 2400, 'status' => 'active', 'effective_from' => '2026-01-01', 'effective_to' => null], $user->id));
        DB::table('work_calendars')->where('id', $otherCalendar)->update(['name' => 'Other DB Changed']);
        self::assertSame('Fresh Calendar', $workforce->management($company->id, 'calendars', null)->rows[0]['name']);
        self::assertSame('Other Cached', $workforce->management($other->id, 'calendars', null)->rows[0]['name']);

        $record = $this->attendance($company->id, $employee->id);
        $attendance = app(AttendanceWorkflowRepositoryInterface::class);
        self::assertSame('draft', $attendance->detail($company->id, $record)->approvalStatus);
        app(AttendanceWorkflowService::class)->execute(new ProcessAttendanceWorkflowCommand('attendance.submit', $company->id, $record, $user->id, 1));
        self::assertSame('submitted', $attendance->detail($company->id, $record)->approvalStatus);

        $type = (int) DB::table('leave_types')->insertGetId(['company_id' => $company->id, 'code' => 'AL', 'name' => 'Annual', 'accrual_policy' => '{}', 'is_paid' => true, 'created_at' => now(), 'updated_at' => now()]);
        $leave = app(LeaveReadRepositoryInterface::class);
        self::assertCount(0, $leave->management($company->id)->policies);
        app(LeaveManagementService::class)->execute(new ManageLeaveCommand('policy.create', $company->id, null, $user->id, 1, (string) Str::uuid(), ['leave_type_id' => $type, 'code' => 'AL', 'name' => 'Annual', 'accrual_method' => 'annual_grant', 'annual_entitlement_minutes' => 5760, 'maximum_carry_forward_minutes' => 0, 'expiry_months' => 3, 'negative_balance_allowed' => false, 'requires_attachment' => false, 'status' => 'active', 'effective_from' => '2026-01-01', 'effective_to' => null]));
        self::assertCount(1, $leave->management($company->id)->policies);

        [$overtimePolicy, $overtimeRequest] = $this->overtime($company->id, $employee->id);
        $overtime = app(OvertimeReadRepositoryInterface::class);
        self::assertSame('draft', $overtime->management($company->id)->requests[0]['status']);
        app(OvertimeManagementService::class)->execute(new ManageOvertimeCommand('request.submit', $company->id, $overtimeRequest, $user->id, 1, (string) Str::uuid(), []));
        self::assertSame('submitted', $overtime->management($company->id)->requests[0]['status']);
        self::assertGreaterThan(0, $overtimePolicy);
    }

    public function test_workforce_management_replaces_legacy_object_cache_with_serialization_safe_json(): void
    {
        [$company] = $this->context('CACHE-LEGACY');
        DB::table('work_shifts')->insert(['company_id' => $company->id, 'code' => 'DAY', 'name' => 'Day shift', 'timezone' => 'Asia/Ho_Chi_Minh', 'starts_at' => '08:00:00', 'ends_at' => '17:00:00', 'crosses_midnight' => false, 'planned_work_minutes' => 480, 'grace_in_minutes' => 0, 'grace_out_minutes' => 0, 'rounding_rule' => 'none', 'minimum_half_day_minutes' => 240, 'minimum_full_day_minutes' => 480, 'status' => 'active', 'version' => 1, 'rules' => '{}', 'created_at' => now(), 'updated_at' => now()]);
        $filterHash = hash('sha256', "shifts\0");
        $legacyKey = "schema:workforce:{$company->id}:workforce:v0:management:{$filterHash}";
        Cache::put($legacyKey, new \stdClass, 300);

        $result = app(WorkforceReadRepositoryInterface::class)->management($company->id, 'shifts', null);

        self::assertSame('Day shift', $result->rows[0]['name']);
        self::assertIsString(Cache::get("schema:workforce:{$company->id}:workforce:v1:management:{$filterHash}"));
    }

    public function test_leave_management_replaces_legacy_and_corrupt_cache_with_serialization_safe_json(): void
    {
        [$legacyCompany] = $this->context('LEAVE-LEGACY');
        Cache::put("schema:workforce:{$legacyCompany->id}:leave:v0:management", new \stdClass, 120);

        $leave = app(LeaveReadRepositoryInterface::class);
        self::assertCount(0, $leave->management($legacyCompany->id)->policies);
        self::assertIsString(Cache::get("schema:workforce:{$legacyCompany->id}:leave:v1:management"));

        [$corruptCompany] = $this->context('LEAVE-CORRUPT');
        Cache::put("schema:workforce:{$corruptCompany->id}:leave:v0:management", '{not-json', 120);

        self::assertCount(0, $leave->management($corruptCompany->id)->policies);
        self::assertIsString(Cache::get("schema:workforce:{$corruptCompany->id}:leave:v1:management"));
    }

    public function test_payroll_delivery_replaces_legacy_object_cache_with_serialization_safe_json(): void
    {
        [$company] = $this->context('PAYROLL-LEGACY');
        Cache::put("schema:workforce:{$company->id}:payroll-run:v0:delivery-dashboard", new \stdClass, 300);

        $result = app(PayrollDeliveryReadRepositoryInterface::class)->dashboard($company->id);

        self::assertCount(0, $result->components);
        self::assertCount(0, $result->assignments);
        self::assertCount(0, $result->runs);
        self::assertIsString(Cache::get("schema:workforce:{$company->id}:payroll-run:v1:delivery-dashboard"));
    }

    public function test_overtime_management_replaces_legacy_object_cache_with_serialization_safe_json(): void
    {
        [$company] = $this->context('OVERTIME-LEGACY');
        Cache::put("schema:workforce:{$company->id}:overtime:v0:management", new \stdClass, 120);

        $result = app(OvertimeReadRepositoryInterface::class)->management($company->id);

        self::assertCount(0, $result->requests);
        self::assertIsString(Cache::get("schema:workforce:{$company->id}:overtime:v1:management"));
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

    private function attendance(int $company, int $employee): int
    {
        return (int) DB::table('attendance_records')->insertGetId(['company_id' => $company, 'employee_id' => $employee, 'work_date' => '2027-01-15', 'scheduled_minutes' => 480, 'worked_minutes' => 480, 'break_minutes' => 0, 'late_minutes' => 0, 'early_leave_minutes' => 0, 'overtime_minutes' => 0, 'attendance_status' => 'present', 'presence_status' => 'present', 'approval_status' => 'draft', 'calculation_status' => 'calculated', 'calculation_version' => 1, 'version' => 1, 'is_locked' => false, 'created_at' => now(), 'updated_at' => now()]);
    }

    /** @return array{int,int} */
    private function overtime(int $company, int $employee): array
    {
        $policy = (int) DB::table('overtime_policies')->insertGetId(['company_id' => $company, 'code' => 'OT', 'name' => 'Overtime', 'minimum_minutes' => 30, 'rounding_rule' => 'none', 'daily_limit_minutes' => 240, 'weekly_limit_minutes' => 600, 'requires_preapproval' => true, 'eligible_day_types' => '["working_day"]', 'status' => 'active', 'effective_from' => '2026-01-01', 'version' => 1, 'created_at' => now(), 'updated_at' => now()]);
        $request = (int) DB::table('overtime_requests')->insertGetId(['company_id' => $company, 'employee_id' => $employee, 'overtime_policy_id' => $policy, 'work_date' => '2027-01-15', 'starts_at' => '2027-01-15 18:00:00', 'ends_at' => '2027-01-15 19:00:00', 'requested_minutes' => 60, 'approved_minutes' => 0, 'actual_minutes' => 0, 'payable_minutes' => 0, 'calculation_status' => 'pending', 'calculation_version' => 1, 'reason' => 'Cache test', 'status' => 'draft', 'version' => 1, 'idempotency_key' => (string) Str::uuid(), 'created_at' => now(), 'updated_at' => now()]);

        return [$policy, $request];
    }
}
