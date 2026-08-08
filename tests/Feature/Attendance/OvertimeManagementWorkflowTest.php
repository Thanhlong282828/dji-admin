<?php

namespace Tests\Feature\Attendance;

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Company\Domain\Models\Company;
use Modules\Employee\Domain\Models\Employee;
use Tests\TestCase;

final class OvertimeManagementWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_guest_and_user_without_permission_cannot_access_overtime(): void
    {
        [$company, , $user] = $this->context('AUTH');
        $this->get('/admin/overtime?company_id='.$company->id)->assertRedirect('/login');
        $this->actingAs($user)->get('/admin/overtime?company_id='.$company->id)->assertForbidden();
        $user->givePermissionTo('overtime.view');
        $this->actingAs($user)->get('/admin/overtime?company_id='.$company->id)->assertOk()->assertSeeText('Overtime Request & Reconciliation');
    }

    public function test_policy_assignment_is_idempotent_and_overlap_is_blocked(): void
    {
        [$company, $employee, $user] = $this->context('POL');
        $user->givePermissionTo('overtime.manage-policy');
        $policyPayload = $this->policyPayload($company->id);
        $this->actingAs($user)->post('/admin/overtime/policy.create', $policyPayload)->assertRedirect();
        $policyId = (int) DB::table('overtime_policies')->value('id');
        $key = (string) Str::uuid();
        $assignment = ['company_id' => $company->id, 'employee_id' => $employee->id, 'overtime_policy_id' => $policyId, 'effective_from' => '2026-01-01', 'idempotency_key' => $key];
        $this->actingAs($user)->post('/admin/overtime/assignment.create', $assignment)->assertRedirect();
        $this->actingAs($user)->post('/admin/overtime/assignment.create', $assignment)->assertRedirect();
        self::assertSame(1, DB::table('employee_overtime_policy_assignments')->count());
        $assignment['idempotency_key'] = (string) Str::uuid();
        $this->actingAs($user)->post('/admin/overtime/assignment.create', $assignment)->assertSessionHasErrors('assignment');
        $this->assertDatabaseHas('activity_log', ['log_name' => 'overtime', 'event' => 'assignment.create']);
    }

    public function test_request_workflow_blocks_self_approval_and_reconciles_approved_attendance(): void
    {
        [$company, $employee, $requester] = $this->context('FLOW');
        $requester->givePermissionTo(['overtime.manage-policy', 'overtime.request', 'overtime.approve']);
        $policyId = $this->policyAndAssignment($company, $employee, $requester);
        $payload = ['company_id' => $company->id, 'employee_id' => $employee->id, 'work_date' => '2026-07-10', 'starts_at' => '2026-07-10 18:00', 'ends_at' => '2026-07-10 20:00', 'reason' => 'Hoàn tất phát hành nền tảng', 'idempotency_key' => (string) Str::uuid()];
        $this->actingAs($requester)->post('/admin/overtime/request.create', $payload)->assertRedirect();
        $requestId = (int) DB::table('overtime_requests')->value('id');
        $this->assertDatabaseHas('overtime_requests', ['id' => $requestId, 'overtime_policy_id' => $policyId, 'requested_minutes' => 120, 'status' => 'draft']);
        $this->actingAs($requester)->put("/admin/overtime/request.submit/{$requestId}", ['company_id' => $company->id, 'version' => 1])->assertRedirect();
        $this->actingAs($requester)->put("/admin/overtime/request.approve/{$requestId}", ['company_id' => $company->id, 'version' => 2])->assertForbidden();
        $approver = User::factory()->create();
        DB::table('company_user_access')->insert(['company_id' => $company->id, 'user_id' => $approver->id]);
        $approver->givePermissionTo('overtime.approve');
        $this->actingAs($approver)->put("/admin/overtime/request.approve/{$requestId}", ['company_id' => $company->id, 'version' => 2])->assertRedirect();
        $this->assertDatabaseHas('overtime_requests', ['id' => $requestId, 'approved_minutes' => 120, 'actual_minutes' => 0, 'payable_minutes' => 0, 'status' => 'approved']);
        DB::table('attendance_records')->insert(['company_id' => $company->id, 'employee_id' => $employee->id, 'work_date' => '2026-07-10', 'source' => 'web', 'scheduled_minutes' => 480, 'worked_minutes' => 600, 'break_minutes' => 60, 'late_minutes' => 0, 'early_leave_minutes' => 0, 'overtime_minutes' => 95, 'attendance_status' => 'present', 'presence_status' => 'approved', 'approval_status' => 'approved', 'calculation_status' => 'calculated', 'calculation_version' => 1, 'version' => 1, 'created_at' => now(), 'updated_at' => now()]);
        $this->actingAs($approver)->put("/admin/overtime/request.reconcile/{$requestId}", ['company_id' => $company->id, 'version' => 3])->assertRedirect();
        $this->assertDatabaseHas('overtime_requests', ['id' => $requestId, 'actual_minutes' => 95, 'payable_minutes' => 90, 'calculation_status' => 'reconciled', 'version' => 4]);
        self::assertSame(4, DB::table('overtime_request_histories')->where('overtime_request_id', $requestId)->count());
    }

    public function test_cross_tenant_access_and_locked_payroll_period_are_blocked(): void
    {
        [$companyA, , $userA] = $this->context('A');
        [$companyB, $employeeB, $userB] = $this->context('B');
        $userA->givePermissionTo('overtime.view');
        $this->actingAs($userA)->get('/admin/overtime?company_id='.$companyB->id)->assertForbidden();
        $userB->givePermissionTo(['overtime.manage-policy', 'overtime.request']);
        $this->policyAndAssignment($companyB, $employeeB, $userB);
        DB::table('payroll_periods')->insert(['company_id' => $companyB->id, 'code' => 'LOCK-07', 'name' => 'Kỳ khóa 07', 'period_start' => '2026-07-01', 'period_end' => '2026-07-31', 'cutoff_at' => '2026-08-01', 'payment_date' => '2026-08-05', 'status' => 'locked', 'version' => 1, 'created_at' => now(), 'updated_at' => now()]);
        $payload = ['company_id' => $companyB->id, 'employee_id' => $employeeB->id, 'work_date' => '2026-07-10', 'starts_at' => '2026-07-10 18:00', 'ends_at' => '2026-07-10 20:00', 'reason' => 'Công việc trong kỳ đã khóa', 'idempotency_key' => (string) Str::uuid()];
        $this->actingAs($userB)->post('/admin/overtime/request.create', $payload)->assertSessionHasErrors('work_date');
    }

    private function policyAndAssignment(Company $company, Employee $employee, User $user): int
    {
        $this->actingAs($user)->post('/admin/overtime/policy.create', $this->policyPayload($company->id))->assertRedirect();
        $policyId = (int) DB::table('overtime_policies')->where('company_id', $company->id)->value('id');
        $this->actingAs($user)->post('/admin/overtime/assignment.create', ['company_id' => $company->id, 'employee_id' => $employee->id, 'overtime_policy_id' => $policyId, 'effective_from' => '2026-01-01', 'idempotency_key' => (string) Str::uuid()])->assertRedirect();

        return $policyId;
    }

    /** @return array<string, int|string|list<string>> */
    private function policyPayload(int $companyId): array
    {
        return ['company_id' => $companyId, 'code' => 'OT-STANDARD', 'name' => 'Overtime tiêu chuẩn', 'minimum_minutes' => 30, 'rounding_rule' => 'floor_15', 'daily_limit_minutes' => 240, 'weekly_limit_minutes' => 720, 'eligible_day_types' => ['workday', 'weekend', 'holiday'], 'status' => 'active', 'effective_from' => '2026-01-01'];
    }

    /** @return array{Company,Employee,User} */
    private function context(string $code): array
    {
        $company = Company::query()->create(['uuid' => (string) Str::uuid(), 'code' => 'OT-'.$code, 'name' => 'Schema Overtime '.$code, 'country_code' => 'VN', 'timezone' => 'Asia/Ho_Chi_Minh', 'currency_code' => 'VND', 'currency' => 'VND', 'status' => 'active', 'is_active' => true]);
        $user = User::factory()->create();
        DB::table('company_user_access')->insert(['company_id' => $company->id, 'user_id' => $user->id]);
        $employee = Employee::query()->create(['company_id' => $company->id, 'user_id' => $user->id, 'employee_code' => 'OT-'.$code, 'full_name' => 'Overtime '.$code, 'nationality' => 'VN', 'join_date' => '2026-01-01', 'employment_status' => 'active', 'employment_type' => 'full_time']);

        return [$company, $employee, $user];
    }
}
