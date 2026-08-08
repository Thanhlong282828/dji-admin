<?php

namespace Tests\Feature\Payroll;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Company\Domain\Models\Company;
use Modules\Employee\Domain\Models\Employee;
use Modules\Payroll\Domain\Services\PayrollOvertimeClassificationService;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

final class PayrollInputOperationsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        foreach (['payroll-period.view', 'payroll-input.generate', 'payroll-input.validate', 'payroll-input.create-version', 'payroll.run.create'] as $permission) {
            Permission::findOrCreate($permission, 'web');
        }
    }

    public function test_operational_list_has_correct_empty_state_and_no_article_leakage(): void
    {
        $fixture = $this->fixture('LIST', false);
        $fixture['user']->givePermissionTo(['payroll-period.view', 'payroll-input.generate']);
        $this->actingAs($fixture['user'])->get(route('admin.payroll.inputs.index', ['company_id' => $fixture['company']]))->assertOk()
            ->assertSee('Chưa có Payroll Input Snapshot')->assertSee('+ Tạo Payroll Input')->assertDontSee('+ Tạo bài viết')->assertDontSee('Article');
    }

    public function test_capture_uses_only_approved_sources_is_immutable_and_versions_atomically(): void
    {
        $fixture = $this->fixture('CAPTURE', true);
        $fixture['user']->givePermissionTo(['payroll-period.view', 'payroll-input.generate', 'payroll-input.validate', 'payroll-input.create-version']);
        $key = (string) Str::uuid();
        $response = $this->actingAs($fixture['user'])->post(route('admin.payroll.inputs.store'), ['company_id' => $fixture['company'], 'payroll_period_id' => $fixture['period'], 'idempotency_key' => $key, 'note' => 'Monthly approved source capture'])->assertRedirect();
        $snapshot = DB::table('payroll_input_snapshots')->where('company_id', $fixture['company'])->first();
        self::assertNotNull($snapshot);
        $snapshotId = $this->integer($snapshot->id);
        self::assertSame(1, $this->integer($snapshot->snapshot_version));
        self::assertSame('ready', $snapshot->status);
        self::assertSame(1, $this->integer($snapshot->attendance_record_count));
        self::assertSame(1, $this->integer($snapshot->leave_record_count));
        self::assertSame(1, $this->integer($snapshot->overtime_record_count));
        $row = DB::table('payroll_employee_inputs')->where('payroll_input_snapshot_id', $snapshotId)->first();
        self::assertNotNull($row);
        self::assertSame(480, $this->integer($row->scheduled_minutes));
        self::assertSame(450, $this->integer($row->worked_minutes));
        self::assertSame(120, $this->integer($row->paid_leave_minutes));
        self::assertSame(90, $this->integer($row->approved_overtime_minutes));
        self::assertSame(0, $this->integer($row->weekday_overtime_minutes));
        self::assertSame(90, $this->integer($row->weekend_overtime_minutes));
        self::assertSame(0, $this->integer($row->holiday_overtime_minutes));
        self::assertSame(3, DB::table('payroll_input_source_records')->where('payroll_employee_input_id', $row->id)->count());
        $this->actingAs($fixture['user'])->get(route('admin.payroll.inputs.employees.index', ['snapshot' => $snapshotId, 'company_id' => $fixture['company']]))->assertOk()->assertSee('Employee CAPTURE');
        $this->actingAs($fixture['user'])->get(route('admin.payroll.inputs.employees.show', ['snapshot' => $snapshotId, 'employeeInput' => $row->id, 'company_id' => $fixture['company']]))->assertOk()->assertSee('Weekday OT')->assertSee('weekend');
        self::assertSame(1, DB::table('payroll_input_histories')->where('payroll_input_snapshot_id', $snapshotId)->where('action', 'captured')->count());
        $this->actingAs($fixture['user'])->post(route('admin.payroll.inputs.store'), ['company_id' => $fixture['company'], 'payroll_period_id' => $fixture['period'], 'idempotency_key' => $key])->assertRedirect();
        self::assertSame(1, DB::table('payroll_input_snapshots')->where('company_id', $fixture['company'])->count());
        DB::table('attendance_records')->where('company_id', $fixture['company'])->update(['worked_minutes' => 300]);
        $this->actingAs($fixture['user'])->get(route('admin.payroll.inputs.show', ['snapshot' => $snapshotId, 'company_id' => $fixture['company']]))->assertOk()->assertSee('450');
        self::assertSame(450, $this->integer(DB::table('payroll_employee_inputs')->where('id', $row->id)->value('worked_minutes')));
        $this->actingAs($fixture['user'])->post(route('admin.payroll.inputs.versions.store', ['snapshot' => $snapshotId]), ['company_id' => $fixture['company'], 'idempotency_key' => (string) Str::uuid(), 'note' => 'Updated approved inputs'])->assertRedirect();
        self::assertSame([1, 2], DB::table('payroll_input_snapshots')->where('company_id', $fixture['company'])->orderBy('snapshot_version')->pluck('snapshot_version')->map(fn ($value): int => $this->integer($value))->all());
        self::assertSame(450, $this->integer(DB::table('payroll_employee_inputs')->where('payroll_input_snapshot_id', $snapshotId)->value('worked_minutes')));
        self::assertSame(300, $this->integer(DB::table('payroll_employee_inputs')->where('payroll_input_snapshot_id', '<>', $snapshotId)->value('worked_minutes')));
        $this->get('/admin/payroll/inputs/'.$snapshotId.'/edit?company_id='.$fixture['company'])->assertNotFound();
    }

    public function test_ready_snapshot_creates_exact_run_and_security_blocks_foreign_company(): void
    {
        $a = $this->fixture('RUNA', true);
        $b = $this->fixture('RUNB', true);
        $a['user']->givePermissionTo(['payroll-period.view', 'payroll-input.generate', 'payroll.run.create']);
        $this->actingAs($a['user'])->post(route('admin.payroll.inputs.store'), ['company_id' => $a['company'], 'payroll_period_id' => $a['period'], 'idempotency_key' => (string) Str::uuid()])->assertRedirect();
        $snapshot = $this->integer(DB::table('payroll_input_snapshots')->where('company_id', $a['company'])->value('id'));
        $this->actingAs($b['user'])->get(route('admin.payroll.inputs.show', ['snapshot' => $snapshot, 'company_id' => $a['company']]))->assertForbidden();
        $this->actingAs($a['user'])->get(route('admin.payroll.inputs.show', ['snapshot' => $snapshot, 'company_id' => $b['company']]))->assertForbidden();
        $this->actingAs($a['user'])->post(route('admin.payroll.inputs.create-run', ['snapshot' => $snapshot]), ['company_id' => $a['company'], 'idempotency_key' => (string) Str::uuid()])->assertRedirect();
        $run = DB::table('payroll_runs')->where('company_id', $a['company'])->first();
        self::assertNotNull($run);
        self::assertSame($snapshot, $this->integer($run->payroll_input_snapshot_id));
        self::assertSame($a['period'], $this->integer($run->payroll_period_id));
    }

    public function test_overtime_classification_uses_effective_calendar_splits_midnight_and_prioritizes_holiday(): void
    {
        $fixture = $this->fixture('CLASSIFY', false);
        $service = $this->app->make(PayrollOvertimeClassificationService::class);
        $split = $service->classify($fixture['company'], $fixture['employee'], '2026-07-03 23:00:00', '2026-07-04 01:00:00', 120);
        self::assertSame(60, $split->weekdayMinutes);
        self::assertSame(60, $split->weekendMinutes);
        self::assertSame(0, $split->holidayMinutes);
        self::assertCount(2, $split->segments);

        DB::table('company_holidays')->insert(['company_id' => $fixture['company'], 'holiday_date' => '2026-07-04', 'name' => 'Configured Company Holiday', 'is_paid' => true, 'is_working_override' => false, 'recurring' => false, 'created_at' => now(), 'updated_at' => now()]);
        $holiday = $service->classify($fixture['company'], $fixture['employee'], '2026-07-04 18:00:00', '2026-07-04 19:30:00', 90);
        self::assertSame(0, $holiday->weekdayMinutes);
        self::assertSame(0, $holiday->weekendMinutes);
        self::assertSame(90, $holiday->holidayMinutes);
    }

    /** @return array{company:int,user:User,employee:int,period:int} */
    private function fixture(string $code, bool $eligible): array
    {
        $company = Company::query()->create(['uuid' => (string) Str::uuid(), 'code' => 'PI-'.$code, 'name' => 'Schema Payroll Input '.$code, 'country_code' => 'VN', 'timezone' => 'Asia/Ho_Chi_Minh', 'currency_code' => 'VND', 'currency' => 'VND', 'status' => 'active', 'is_active' => true]);
        $user = User::factory()->create();
        DB::table('company_user_access')->insert(['company_id' => $company->id, 'user_id' => $user->id]);
        $employee = Employee::query()->create(['company_id' => $company->id, 'user_id' => $user->id, 'employee_code' => $code, 'full_name' => 'Employee '.$code, 'nationality' => 'VN', 'join_date' => '2026-01-01', 'employment_status' => 'active', 'employment_type' => 'full_time']);
        $calendar = (int) DB::table('work_calendars')->insertGetId(['company_id' => $company->id, 'code' => 'STANDARD-'.$code, 'name' => 'Standard Calendar '.$code, 'timezone' => 'Asia/Ho_Chi_Minh', 'weekly_work_minutes' => 2400, 'status' => 'active', 'effective_from' => '2026-01-01', 'version' => 1, 'created_at' => now(), 'updated_at' => now()]);
        foreach (range(1, 7) as $weekday) {
            DB::table('work_calendar_days')->insert(['work_calendar_id' => $calendar, 'weekday' => $weekday, 'is_working_day' => $weekday <= 5, 'planned_minutes' => $weekday <= 5 ? 480 : 0, 'created_at' => now(), 'updated_at' => now()]);
        }
        DB::table('employee_work_profiles')->insert(['company_id' => $company->id, 'employee_id' => $employee->id, 'timezone' => 'Asia/Ho_Chi_Minh', 'work_calendar_id' => $calendar, 'weekly_minutes' => 2400, 'daily_minutes' => 480, 'fte_basis_points' => 10000, 'attendance_required' => true, 'overtime_eligible' => true, 'leave_eligible' => true, 'effective_from' => '2026-01-01', 'version' => 1, 'created_at' => now(), 'updated_at' => now()]);
        $period = (int) DB::table('payroll_periods')->insertGetId(['company_id' => $company->id, 'code' => '2026-07-'.$code, 'name' => 'July '.$code, 'period_start' => '2026-07-01', 'period_end' => '2026-07-31', 'cutoff_at' => '2026-08-01', 'payment_date' => '2026-08-05', 'status' => 'collecting', 'version' => 1, 'created_at' => now(), 'updated_at' => now()]);
        if ($eligible) {
            $unit = (int) DB::table('organization_units')->insertGetId(['company_id' => $company->id, 'type' => 'department', 'code' => 'TECH-'.$code, 'name' => 'Technology '.$code, 'headcount_limit' => 10, 'effective_from' => '2026-01-01', 'created_at' => now(), 'updated_at' => now()]);
            $position = (int) DB::table('job_positions')->insertGetId(['company_id' => $company->id, 'organization_unit_id' => $unit, 'code' => 'DEV-'.$code, 'title' => 'Developer '.$code, 'approved_headcount' => 10, 'budgeted_cost' => 0, 'is_critical' => false, 'created_at' => now(), 'updated_at' => now()]);
            DB::table('employee_assignments')->insert(['employee_id' => $employee->id, 'organization_unit_id' => $unit, 'job_position_id' => $position, 'assignment_type' => 'primary', 'allocation_percent' => 100, 'effective_from' => '2026-01-01', 'created_at' => now(), 'updated_at' => now()]);
            DB::table('employment_contracts')->insert(['employee_id' => $employee->id, 'contract_number' => 'CON-'.$code, 'type' => 'indefinite', 'starts_at' => '2026-01-01', 'base_salary' => 0, 'currency' => 'VND', 'terms' => '{}', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
            DB::table('attendance_records')->insert(['company_id' => $company->id, 'employee_id' => $employee->id, 'work_date' => '2026-07-01', 'scheduled_minutes' => 480, 'worked_minutes' => 450, 'break_minutes' => 0, 'late_minutes' => 30, 'early_leave_minutes' => 0, 'overtime_minutes' => 0, 'attendance_status' => 'present', 'presence_status' => 'present', 'approval_status' => 'approved', 'calculation_status' => 'calculated', 'calculation_version' => 1, 'version' => 1, 'is_locked' => false, 'created_at' => now(), 'updated_at' => now()]);
            DB::table('attendance_records')->insert(['company_id' => $company->id, 'employee_id' => $employee->id, 'work_date' => '2026-07-02', 'scheduled_minutes' => 480, 'worked_minutes' => 480, 'break_minutes' => 0, 'late_minutes' => 0, 'early_leave_minutes' => 0, 'overtime_minutes' => 0, 'attendance_status' => 'present', 'presence_status' => 'present', 'approval_status' => 'pending', 'calculation_status' => 'calculated', 'calculation_version' => 1, 'version' => 1, 'is_locked' => false, 'created_at' => now(), 'updated_at' => now()]);
            $leaveType = (int) DB::table('leave_types')->insertGetId(['company_id' => $company->id, 'code' => 'ANNUAL-'.$code, 'name' => 'Annual Leave', 'accrual_policy' => '{}', 'carry_forward_policy' => '{}', 'is_paid' => true, 'created_at' => now(), 'updated_at' => now()]);
            DB::table('leave_requests')->insert(['company_id' => $company->id, 'employee_id' => $employee->id, 'leave_type_id' => $leaveType, 'starts_at' => '2026-07-03 08:00:00', 'ends_at' => '2026-07-03 10:00:00', 'timezone' => 'Asia/Ho_Chi_Minh', 'requested_minutes' => 120, 'duration_type' => 'hours', 'days' => 0.25, 'reason' => 'Personal', 'status' => 'approved', 'version' => 1, 'idempotency_key' => (string) Str::uuid(), 'created_at' => now(), 'updated_at' => now()]);
            DB::table('overtime_requests')->insert(['company_id' => $company->id, 'employee_id' => $employee->id, 'work_date' => '2026-07-04', 'starts_at' => '2026-07-04 18:00:00', 'ends_at' => '2026-07-04 19:30:00', 'requested_minutes' => 90, 'approved_minutes' => 90, 'actual_minutes' => 90, 'payable_minutes' => 90, 'calculation_status' => 'calculated', 'calculation_version' => 1, 'reason' => 'Release', 'status' => 'approved', 'version' => 1, 'idempotency_key' => (string) Str::uuid(), 'created_at' => now(), 'updated_at' => now()]);
        }

        return ['company' => $company->id, 'user' => $user, 'employee' => $employee->id, 'period' => $period];
    }

    private function integer(mixed $value): int
    {
        return is_numeric($value) ? (int) $value : 0;
    }
}
