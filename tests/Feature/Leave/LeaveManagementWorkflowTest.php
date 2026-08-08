<?php

namespace Tests\Feature\Leave;

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Company\Domain\Models\Company;
use Modules\Employee\Domain\Models\Employee;
use Tests\TestCase;

final class LeaveManagementWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_guest_and_missing_permission_are_blocked(): void
    {
        [$c,$e,$u] = $this->context('AUTH');
        $this->get("/admin/leave-management?company_id={$c->id}")->assertRedirect('/login');
        $this->actingAs($u)->get("/admin/leave-management?company_id={$c->id}")->assertForbidden();
    }

    public function test_policy_create_duplicate_and_cross_company_are_protected(): void
    {
        [$c,$e,$u,$type] = $this->context('POL');
        $u->givePermissionTo('leave.manage-policy');
        $payload = $this->policyPayload($c->id, $type);
        $this->actingAs($u)->post('/admin/leave-management/policy.create', $payload)->assertRedirect();
        $this->assertDatabaseHas('leave_policies', ['company_id' => $c->id, 'code' => 'ANNUAL']);
        $payload['idempotency_key'] = (string) Str::uuid();
        $this->actingAs($u)->post('/admin/leave-management/policy.create', $payload)->assertSessionHasErrors('code');
        [$other,$oe,$ou,$otherType] = $this->context('OTHER');
        $payload['code'] = 'OTHER';
        $payload['idempotency_key'] = (string) Str::uuid();
        $payload['leave_type_id'] = $otherType;
        $this->actingAs($u)->post('/admin/leave-management/policy.create', $payload)->assertNotFound();
    }

    public function test_assignment_opening_balance_and_idempotency(): void
    {
        [$c,$e,$u,$type] = $this->context('ASSIGN');
        $u->givePermissionTo('leave.manage-policy');
        $policy = $this->policy($c->id, $type);
        $key = (string) Str::uuid();
        $payload = ['company_id' => $c->id, 'idempotency_key' => $key, 'employee_id' => $e->id, 'leave_policy_id' => $policy, 'effective_from' => '2026-01-01', 'opening_minutes' => 960];
        $this->actingAs($u)->post('/admin/leave-management/assignment.create', $payload)->assertRedirect();
        $this->actingAs($u)->post('/admin/leave-management/assignment.create', $payload)->assertRedirect();
        self::assertSame(1, DB::table('employee_leave_policy_assignments')->count());
        self::assertSame(960, (int) DB::table('leave_balance_transactions')->sum('minutes'));
    }

    public function test_manual_adjustment_and_monthly_accrual_are_ledger_idempotent(): void
    {
        [$c,$e,$u,$type] = $this->context('LEDGER');
        $u->givePermissionTo(['leave.manage-policy', 'leave.adjust-balance']);
        $policy = $this->policy($c->id, $type);
        $key = (string) Str::uuid();
        $payload = ['company_id' => $c->id, 'idempotency_key' => $key, 'employee_id' => $e->id, 'leave_policy_id' => $policy, 'minutes' => 480, 'effective_date' => '2026-07-01', 'reason' => 'Điều chỉnh số dư đã phê duyệt'];
        $this->actingAs($u)->post('/admin/leave-management/balance.adjust', $payload)->assertRedirect();
        $this->actingAs($u)->post('/admin/leave-management/balance.adjust', $payload)->assertRedirect();
        self::assertSame(1, DB::table('leave_balance_transactions')->count());
        self::assertSame(480, (int) DB::table('leave_balance_transactions')->sum('minutes'));
        $payload['idempotency_key'] = (string) Str::uuid();
        $payload['minutes'] = 120;
        $this->actingAs($u)->post('/admin/leave-management/accrual.monthly', $payload)->assertRedirect();
        self::assertSame(600, (int) DB::table('leave_balance_transactions')->sum('minutes'));
    }

    /** @return array{Company,Employee,User,int} */
    private function context(string $code): array
    {
        $c = Company::query()->create(['uuid' => (string) Str::uuid(), 'code' => 'LV-'.$code, 'name' => 'Schema Leave '.$code, 'country_code' => 'VN', 'timezone' => 'Asia/Ho_Chi_Minh', 'currency_code' => 'VND', 'currency' => 'VND', 'status' => 'active', 'is_active' => true]);
        $u = User::factory()->create();
        DB::table('company_user_access')->insert(['company_id' => $c->id, 'user_id' => $u->id]);
        $e = Employee::query()->create(['company_id' => $c->id, 'user_id' => $u->id, 'employee_code' => 'LV-'.$code, 'full_name' => 'Leave '.$code, 'nationality' => 'VN', 'join_date' => '2026-01-01', 'employment_status' => 'active', 'employment_type' => 'full_time']);
        $type = (int) DB::table('leave_types')->insertGetId(['company_id' => $c->id, 'code' => 'ANNUAL', 'name' => 'Phép năm', 'accrual_policy' => json_encode([]), 'is_paid' => true, 'created_at' => now(), 'updated_at' => now()]);

        return [$c, $e, $u, $type];
    }

    /** @return array<string,int|string> */
    private function policyPayload(int $c, int $type): array
    {
        return ['company_id' => $c, 'idempotency_key' => (string) Str::uuid(), 'leave_type_id' => $type, 'code' => 'ANNUAL', 'name' => 'Chính sách phép năm', 'accrual_method' => 'monthly', 'annual_entitlement_minutes' => 5760, 'maximum_carry_forward_minutes' => 960, 'expiry_months' => 3, 'status' => 'active', 'effective_from' => '2026-01-01'];
    }

    private function policy(int $c, int $type): int
    {
        return (int) DB::table('leave_policies')->insertGetId(['company_id' => $c, 'leave_type_id' => $type, 'code' => 'ANNUAL', 'name' => 'Phép năm', 'unit' => 'minutes', 'accrual_method' => 'monthly', 'annual_entitlement_minutes' => 5760, 'maximum_carry_forward_minutes' => 960, 'negative_balance_allowed' => false, 'requires_attachment' => false, 'status' => 'active', 'effective_from' => '2026-01-01', 'version' => 1, 'created_at' => now(), 'updated_at' => now()]);
    }
}
