<?php

namespace Tests\Feature\Attendance;

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Company\Domain\Models\Company;
use Modules\Employee\Domain\Models\Employee;
use Modules\Payroll\Domain\Models\PayrollPeriod;
use Tests\TestCase;

final class AttendanceApprovalWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_adjustment_creation_is_tenant_scoped_validated_and_idempotent(): void
    {
        [$company,$employee,$user,$record] = $this->context('ADJ');
        $user->givePermissionTo('attendance.adjust');
        $key = (string) Str::uuid();
        $url = "/admin/attendance/{$record}/adjustments";
        $payload = ['company_id' => $company->id, 'version' => 1, 'idempotency_key' => $key, 'check_in_at' => '2026-07-10 08:00:00', 'check_out_at' => '2026-07-10 17:00:00', 'break_minutes' => 60, 'reason' => 'Quên ghi nhận phiên làm việc hợp lệ'];
        $this->actingAs($user)->post($url, $payload)->assertRedirect();
        $this->actingAs($user)->post($url, $payload)->assertRedirect();
        self::assertSame(1, DB::table('attendance_adjustment_requests')->count());
        $this->assertDatabaseHas('attendance_adjustment_histories', ['action' => 'created']);
        $payload['idempotency_key'] = (string) Str::uuid();
        $payload['check_out_at'] = '2026-07-10 07:00:00';
        $this->actingAs($user)->post($url, $payload)->assertSessionHasErrors('check_out_at');
    }

    public function test_adjustment_submit_approve_recalculates_without_mutating_raw_events(): void
    {
        [$company,$employee,$requester,$record] = $this->context('FLOW');
        $requester->givePermissionTo('attendance.adjust');
        $key = (string) Str::uuid();
        $this->actingAs($requester)->post("/admin/attendance/{$record}/adjustments", ['company_id' => $company->id, 'version' => 1, 'idempotency_key' => $key, 'check_in_at' => '2026-07-10 08:00:00', 'check_out_at' => '2026-07-10 17:00:00', 'break_minutes' => 60, 'reason' => 'Bổ sung phiên làm việc bị thiếu'])->assertRedirect();
        $id = (int) DB::table('attendance_adjustment_requests')->value('id');
        $this->actingAs($requester)->post("/admin/attendance-adjustments/{$id}/submit", ['company_id' => $company->id, 'version' => 1])->assertRedirect();
        $approver = User::factory()->create();
        DB::table('company_user_access')->insert(['company_id' => $company->id, 'user_id' => $approver->id]);
        $approver->givePermissionTo('attendance.approve');
        $eventsBefore = DB::table('attendance_events')->count();
        $this->actingAs($approver)->post("/admin/attendance-adjustments/{$id}/approve", ['company_id' => $company->id, 'version' => 2])->assertRedirect();
        $this->assertDatabaseHas('attendance_records', ['id' => $record, 'worked_minutes' => 480, 'calculation_status' => 'calculated']);
        $this->assertDatabaseHas('attendance_adjustment_requests', ['id' => $id, 'status' => 'approved']);
        self::assertSame($eventsBefore, DB::table('attendance_events')->count());
        self::assertSame(3, DB::table('attendance_adjustment_histories')->count());
    }

    public function test_self_approval_and_stale_version_are_blocked(): void
    {
        [$company,$employee,$user,$record] = $this->context('SAFE');
        $user->givePermissionTo(['attendance.adjust', 'attendance.approve']);
        $this->actingAs($user)->post("/admin/attendance/{$record}/submit", ['company_id' => $company->id, 'version' => 99])->assertSessionHasErrors('version');
        DB::table('attendance_records')->where('id', $record)->update(['approval_status' => 'submitted']);
        $this->actingAs($user)->post("/admin/attendance/{$record}/approve", ['company_id' => $company->id, 'version' => 1])->assertSessionHasErrors('approval');
    }

    public function test_period_readiness_and_atomic_lock(): void
    {
        [$company,$employee,$user,$record] = $this->context('LOCK');
        $user->givePermissionTo('attendance.lock');
        DB::table('attendance_records')->where('id', $record)->update(['approval_status' => 'approved']);
        $period = PayrollPeriod::query()->create(['company_id' => $company->id, 'code' => '2026-07', 'name' => 'Kỳ công 07', 'period_start' => '2026-07-01', 'period_end' => '2026-07-31', 'cutoff_at' => '2026-08-01', 'payment_date' => '2026-08-05', 'status' => 'review', 'version' => 1]);
        $this->actingAs($user)->get("/admin/attendance-periods/{$period->id}/readiness?company_id={$company->id}")->assertOk()->assertSee('sẵn sàng khóa');
        $this->actingAs($user)->post("/admin/attendance-periods/{$period->id}/lock", ['company_id' => $company->id, 'version' => 1, 'reason' => 'Đã hoàn tất kiểm soát kỳ công'])->assertRedirect();
        $this->assertDatabaseHas('attendance_records', ['id' => $record, 'is_locked' => 1, 'payroll_period_id' => $period->id]);
        $this->assertDatabaseHas('payroll_periods', ['id' => $period->id, 'status' => 'locked']);
    }

    public function test_locked_and_cross_company_records_are_blocked(): void
    {
        [$a,$employee,$user] = $this->context('A');
        [$b,$employeeB,$userB,$recordB] = $this->context('B');
        $user->givePermissionTo(['attendance.view', 'attendance.adjust']);
        DB::table('company_user_access')->where('user_id', $user->id)->delete();
        DB::table('company_user_access')->insert(['company_id' => $a->id, 'user_id' => $user->id]);
        $this->actingAs($user)->get("/admin/attendance/{$recordB}?company_id={$a->id}")->assertNotFound();
        DB::table('attendance_records')->where('id', $recordB)->update(['is_locked' => true]);
        $userB->givePermissionTo('attendance.adjust');
        $this->actingAs($userB)->post("/admin/attendance/{$recordB}/adjustments", ['company_id' => $b->id, 'version' => 1, 'idempotency_key' => (string) Str::uuid(), 'reason' => 'Điều chỉnh record đã khóa'])->assertForbidden();
    }

    /** @return array{Company,Employee,User,int} */
    private function context(string $code): array
    {
        $company = Company::query()->create(['uuid' => (string) Str::uuid(), 'code' => 'AT-'.$code, 'name' => 'Schema Attendance '.$code, 'country_code' => 'VN', 'timezone' => 'Asia/Ho_Chi_Minh', 'currency_code' => 'VND', 'currency' => 'VND', 'status' => 'active', 'is_active' => true]);
        $user = User::factory()->create();
        DB::table('company_user_access')->insert(['company_id' => $company->id, 'user_id' => $user->id]);
        $employee = Employee::query()->create(['company_id' => $company->id, 'user_id' => $user->id, 'employee_code' => 'AT-'.$code, 'full_name' => 'Attendance '.$code, 'nationality' => 'VN', 'join_date' => '2026-01-01', 'employment_status' => 'active', 'employment_type' => 'full_time']);
        $record = (int) DB::table('attendance_records')->insertGetId(['company_id' => $company->id, 'employee_id' => $employee->id, 'work_date' => '2026-07-10', 'source' => 'web', 'scheduled_minutes' => 480, 'worked_minutes' => 0, 'break_minutes' => 0, 'attendance_status' => 'incomplete', 'presence_status' => 'unapproved', 'approval_status' => 'draft', 'calculation_status' => 'calculated', 'anomalies' => json_encode([]), 'version' => 1, 'calculation_version' => 1, 'created_at' => now(), 'updated_at' => now()]);

        return [$company, $employee, $user, $record];
    }
}
