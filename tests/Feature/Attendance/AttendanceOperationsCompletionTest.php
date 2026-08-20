<?php

namespace Tests\Feature\Attendance;

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Attendance\Application\Exports\AttendanceXlsxExport;
use Modules\Attendance\Application\Services\AttendanceOperationsReadService;
use Modules\Company\Domain\Models\Company;
use Modules\Employee\Domain\Models\Employee;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

final class AttendanceOperationsCompletionTest extends TestCase
{
    use RefreshDatabase;

    public function test_day_week_metrics_and_xlsx_are_tenant_scoped_and_authorized(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $company = Company::query()->create(['uuid' => (string) Str::uuid(), 'code' => 'ATT-XLSX', 'name' => 'Attendance XLSX', 'country_code' => 'VN', 'timezone' => 'Asia/Ho_Chi_Minh', 'currency_code' => 'VND', 'currency' => 'VND', 'status' => 'active', 'is_active' => true]);
        $employee = Employee::query()->create(['company_id' => $company->id, 'employee_code' => '=FORMULA', 'full_name' => '+Synthetic Employee', 'nationality' => 'VN', 'join_date' => today(), 'employment_status' => 'active', 'employment_type' => 'full_time']);
        DB::table('attendance_records')->insert(['company_id' => $company->id, 'employee_id' => $employee->id, 'work_date' => today(), 'scheduled_minutes' => 480, 'worked_minutes' => 465, 'break_minutes' => 60, 'late_minutes' => 15, 'early_leave_minutes' => 0, 'overtime_minutes' => 60, 'attendance_status' => 'late', 'presence_status' => 'present', 'approval_status' => 'approved', 'calculation_status' => 'calculated', 'calculation_version' => 1, 'version' => 1, 'worked_hours' => 7.75, 'overtime_hours' => 1, 'is_locked' => false, 'created_at' => now(), 'updated_at' => now()]);
        $day = app(AttendanceOperationsReadService::class)->snapshot($company->id, ['range' => 'day']);
        $week = app(AttendanceOperationsReadService::class)->snapshot($company->id, ['range' => 'week']);
        self::assertSame(1, $day['metrics']['late_occurrences']);
        self::assertSame(1, count($day['chart']));
        self::assertSame(7, count($week['chart']));
        $mapped = (new AttendanceXlsxExport($company->id))->map((object) ['full_name' => '+Synthetic Employee', 'employee_code' => '=FORMULA']);
        self::assertSame("'+Synthetic Employee", $mapped[0]);
        self::assertSame("'=FORMULA", $mapped[1]);

        $user = User::factory()->create(['email_verified_at' => now()]);
        DB::table('company_user_access')->insert(['company_id' => $company->id, 'user_id' => $user->id]);
        $this->actingAs($user)->get(route('admin.attendance.export.xlsx', ['company_id' => $company->id]))->assertForbidden();
        $user->givePermissionTo(Permission::findOrCreate('attendance.export', 'web'));
        $response = $this->actingAs($user)->get(route('admin.attendance.export.xlsx', ['company_id' => $company->id]));
        $response->assertOk();
        self::assertStringContainsString('application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', (string) $response->headers->get('content-type'));
    }
}
