<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Modules\Employee\Application\Services\HrOperationsService;
use Tests\TestCase;

final class HrOperationsWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_renewal_preserves_contract_history_and_resignation_requires_complete_offboarding(): void
    {
        $this->seed(RolePermissionSeeder::class);
        [$maker, $checker, $company, $employee] = $this->runtime();
        $service = app(HrOperationsService::class);
        $old = (int) DB::table('employment_contracts')->insertGetId(['employee_id' => $employee, 'contract_number' => 'OLD-001', 'type' => 'fixed_term', 'starts_at' => '2026-01-01', 'ends_at' => '2026-09-30', 'base_salary' => '20000000.00', 'currency' => 'VND', 'terms' => '{}', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);

        $renewal = $service->mutate('renewal.create', $company, $maker, ['previous_contract_id' => $old, 'reference' => 'REN-001', 'effective_date' => '2026-10-01', 'new_ends_at' => '2027-09-30', 'new_contract_type' => 'fixed_term', 'new_base_salary' => '22000000.00', 'reason' => 'Gia hạn theo kế hoạch nhân sự']);
        $service->mutate('renewal.submit', $company, $maker, ['id' => $renewal]);
        $service->mutate('renewal.approve', $company, $checker, ['id' => $renewal]);
        $newContract = (int) DB::table('employment_contract_renewals')->where('id', $renewal)->value('new_contract_id');
        self::assertNotSame($old, $newContract);
        self::assertSame('active', DB::table('employment_contracts')->where('id', $old)->value('status'));
        self::assertSame('approved', DB::table('employment_contracts')->where('id', $newContract)->value('status'));

        $resignation = $service->mutate('resignation.create', $company, $maker, ['employee_id' => $employee, 'reference' => 'RES-001', 'last_working_date' => now()->toDateString(), 'reason' => 'Nghỉ việc tự nguyện']);
        $service->mutate('resignation.submit', $company, $maker, ['id' => $resignation]);
        self::assertSame('active', DB::table('employees')->where('id', $employee)->value('employment_status'));
        $service->mutate('resignation.approve', $company, $checker, ['id' => $resignation]);
        $case = (int) DB::table('employee_offboarding_cases')->where('resignation_id', $resignation)->value('id');

        $this->expectException(ValidationException::class);
        $service->mutate('offboarding.complete', $company, $checker, ['id' => $case]);
    }

    /** @return array{User,User,int,int} */
    private function runtime(): array
    {
        $maker = User::factory()->create(['email_verified_at' => now()]);
        $checker = User::factory()->create(['email_verified_at' => now()]);
        $company = (int) DB::table('companies')->insertGetId(['uuid' => (string) Str::uuid(), 'code' => 'HROPS', 'name' => 'HR Operations Company', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
        foreach ([$maker, $checker] as $user) {
            DB::table('company_user_access')->insert(['company_id' => $company, 'user_id' => $user->id, 'created_at' => now(), 'updated_at' => now()]);
        }
        $employee = (int) DB::table('employees')->insertGetId(['uuid' => (string) Str::uuid(), 'company_id' => $company, 'employee_code' => 'HROPS-001', 'full_name' => 'Nhân viên kiểm thử HR', 'join_date' => '2026-01-01', 'employment_status' => 'active', 'employment_type' => 'full_time', 'created_at' => now(), 'updated_at' => now()]);

        return [$maker, $checker, $company, $employee];
    }
}
