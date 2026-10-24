<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Attendance\Domain\Models\EmployeeWorkProfile;
use Modules\Attendance\Domain\Models\WorkCalendar;
use Modules\Company\Domain\Models\Company;
use Modules\Employee\Domain\Models\Employee;
use Modules\Payroll\Domain\Models\PayrollPeriod;
use Tests\TestCase;

final class WorkforceOperationsFoundationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_guest_and_missing_permission_are_blocked(): void
    {
        [$company] = $this->context('AUTH');
        $this->get('/admin/attendance?company_id='.$company->id)->assertRedirect('/login');
        $user = User::factory()->create();
        DB::table('company_user_access')->insert(['company_id' => $company->id, 'user_id' => $user->id]);
        $this->actingAs($user)->get('/admin/attendance?company_id='.$company->id)->assertForbidden();
    }

    public function test_self_checkin_checkout_is_idempotent_and_server_timestamp_authoritative(): void
    {
        [$company, $employee, $user] = $this->context('CAPTURE');
        $user->givePermissionTo(['attendance.view', 'attendance.check-in', 'attendance.check-out']);
        $key = (string) Str::uuid();
        $payload = ['company_id' => $company->id, 'source' => 'web', 'deduplication_key' => $key, 'client_occurred_at' => '2000-01-01T00:00:00Z'];
        $this->actingAs($user)->post('/admin/attendance/check-in', $payload)->assertRedirect();
        $this->actingAs($user)->post('/admin/attendance/check-in', $payload)->assertRedirect();
        self::assertSame(1, DB::table('attendance_events')->where('employee_id', $employee->id)->count());
        self::assertNotSame('2000-01-01', substr((string) DB::table('attendance_events')->value('occurred_at'), 0, 10));
        $this->actingAs($user)->post('/admin/attendance/check-out', ['company_id' => $company->id, 'source' => 'web', 'deduplication_key' => (string) Str::uuid()])->assertRedirect();
        self::assertSame(2, DB::table('attendance_events')->count());
    }

    public function test_cross_company_employee_capture_is_blocked(): void
    {
        [$companyA] = $this->context('A');
        [$companyB, $employeeB, $userA] = $this->context('B');
        $userA->givePermissionTo(['attendance.check-in', 'attendance.adjust']);
        DB::table('company_user_access')->where('user_id', $userA->id)->delete();
        DB::table('company_user_access')->insert(['company_id' => $companyA->id, 'user_id' => $userA->id]);
        $this->actingAs($userA)->post('/admin/attendance/check-in', ['company_id' => $companyA->id, 'employee_id' => $employeeB->id, 'source' => 'manual', 'reason' => 'HR correction', 'deduplication_key' => (string) Str::uuid()])->assertNotFound();
        self::assertSame(0, DB::table('attendance_events')->count());
    }

    public function test_employee_view_own_scope_never_exposes_another_employee_attendance(): void
    {
        [$company, $employee, $user] = $this->context('SELF-VIEW');
        $other = Employee::query()->create(['company_id' => $company->id, 'employee_code' => 'EMP-OTHER', 'full_name' => 'Không Được Hiển Thị', 'nationality' => 'VN', 'join_date' => '2026-01-01', 'employment_status' => 'active', 'employment_type' => 'full_time']);
        $ownRecord = DB::table('attendance_records')->insertGetId(['company_id' => $company->id, 'employee_id' => $employee->id, 'work_date' => today(), 'source' => 'web', 'scheduled_minutes' => 480, 'worked_minutes' => 450, 'late_minutes' => 30, 'attendance_status' => 'late', 'presence_status' => 'present', 'approval_status' => 'approved', 'calculation_status' => 'calculated', 'created_at' => now(), 'updated_at' => now()]);
        $otherRecord = DB::table('attendance_records')->insertGetId(['company_id' => $company->id, 'employee_id' => $other->id, 'work_date' => today(), 'source' => 'web', 'scheduled_minutes' => 480, 'worked_minutes' => 480, 'attendance_status' => 'present', 'presence_status' => 'present', 'approval_status' => 'approved', 'calculation_status' => 'calculated', 'created_at' => now(), 'updated_at' => now()]);
        $user->givePermissionTo('attendance.view-own');

        $this->actingAs($user)->get('/admin/attendance?company_id='.$company->id)
            ->assertOk()->assertSeeText($employee->full_name)->assertDontSeeText($other->full_name);
        $this->actingAs($user)->get('/admin/attendance/'.$ownRecord.'?company_id='.$company->id)->assertOk();
        $this->actingAs($user)->get('/admin/attendance/'.$otherRecord.'?company_id='.$company->id)->assertForbidden();
    }

    public function test_payroll_snapshot_contains_inputs_only_and_locks_attendance(): void
    {
        [$company, $employee, $user] = $this->context('PAY');
        $period = PayrollPeriod::query()->create(['company_id' => $company->id, 'code' => '2026-07', 'name' => 'Kỳ công tháng 07/2026', 'period_start' => '2026-07-01', 'period_end' => '2026-07-31', 'cutoff_at' => '2026-08-01 00:00:00', 'payment_date' => '2026-08-05', 'status' => 'collecting', 'version' => 1]);
        DB::table('attendance_records')->insert(['company_id' => $company->id, 'employee_id' => $employee->id, 'work_date' => '2026-07-27', 'source' => 'web', 'scheduled_minutes' => 480, 'worked_minutes' => 450, 'late_minutes' => 15, 'early_leave_minutes' => 15, 'attendance_status' => 'present', 'presence_status' => 'present', 'approval_status' => 'approved', 'calculation_status' => 'calculated', 'created_at' => now(), 'updated_at' => now()]);
        $user->givePermissionTo(['payroll-input.generate', 'payroll-period.lock']);
        $this->actingAs($user)->post("/admin/payroll-periods/{$period->id}/generate-inputs", ['company_id' => $company->id, 'version' => 1])->assertRedirect();
        $this->assertDatabaseHas('payroll_employee_inputs', ['employee_id' => $employee->id, 'worked_minutes' => 450, 'late_minutes' => 15]);
        self::assertFalse(DB::getSchemaBuilder()->hasColumn('payroll_employee_inputs', 'net_salary'));
        $this->actingAs($user)->post("/admin/payroll-periods/{$period->id}/lock", ['company_id' => $company->id, 'version' => 2, 'reason' => 'Đã kiểm tra dữ liệu đầu vào'])->assertRedirect();
        $this->assertDatabaseHas('attendance_records', ['employee_id' => $employee->id, 'is_locked' => 1, 'approval_status' => 'locked']);
    }

    /** @return array{Company, Employee, User} */
    private function context(string $code): array
    {
        $company = Company::query()->create(['uuid' => (string) Str::uuid(), 'code' => 'WF-'.$code, 'name' => 'Schema Workforce '.$code, 'country_code' => 'VN', 'timezone' => 'Asia/Ho_Chi_Minh', 'currency_code' => 'VND', 'currency' => 'VND', 'status' => 'active', 'is_active' => true]);
        $user = User::factory()->create();
        DB::table('company_user_access')->insert(['company_id' => $company->id, 'user_id' => $user->id]);
        $employee = Employee::query()->create(['company_id' => $company->id, 'user_id' => $user->id, 'employee_code' => 'EMP-'.$code, 'full_name' => 'Workforce '.$code, 'nationality' => 'VN', 'join_date' => '2026-01-01', 'employment_status' => 'active', 'employment_type' => 'full_time']);
        $calendar = WorkCalendar::query()->create(['company_id' => $company->id, 'code' => 'STD', 'name' => 'Lịch chuẩn', 'timezone' => 'Asia/Ho_Chi_Minh', 'weekly_work_minutes' => 2400, 'effective_from' => '2026-01-01']);
        foreach ([1, 2, 3, 4, 5, 6, 7] as $weekday) {
            DB::table('work_calendar_days')->insert(['work_calendar_id' => $calendar->id, 'weekday' => $weekday, 'is_working_day' => $weekday <= 5, 'planned_minutes' => $weekday <= 5 ? 480 : 0, 'created_at' => now(), 'updated_at' => now()]);
        }
        EmployeeWorkProfile::query()->create(['company_id' => $company->id, 'employee_id' => $employee->id, 'timezone' => 'Asia/Ho_Chi_Minh', 'work_calendar_id' => $calendar->id, 'weekly_minutes' => 2400, 'daily_minutes' => 480, 'fte_basis_points' => 10000, 'effective_from' => '2026-01-01']);

        return [$company, $employee, $user];
    }
}
