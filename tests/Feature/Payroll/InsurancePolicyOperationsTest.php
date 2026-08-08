<?php

namespace Tests\Feature\Payroll;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Modules\Company\Domain\Models\Company;
use Modules\Employee\Domain\Models\Employee;
use Modules\Payroll\Domain\Services\InsuranceManagementService;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

final class InsurancePolicyOperationsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        foreach (['payroll.insurance.view', 'payroll.insurance.policy.create', 'payroll.insurance.policy.update', 'payroll.insurance.profile.manage'] as $permission) {
            Permission::findOrCreate($permission, 'web');
        }
    }

    public function test_authorized_policy_and_profile_operations_are_company_scoped_and_effective_dated(): void
    {
        [$company, $user, $employee] = $this->fixture('A');
        $user->givePermissionTo(['payroll.insurance.view', 'payroll.insurance.policy.create', 'payroll.insurance.profile.manage']);
        $response = $this->actingAs($user)->post(route('admin.payroll.insurance.policies.store'), $this->policyPayload($company->id));
        $rawPolicy = DB::table('insurance_policies')->where('company_id', $company->id)->value('id');
        self::assertIsNumeric($rawPolicy);
        $policy = (int) $rawPolicy;
        $response->assertRedirect(route('admin.payroll.insurance.policies.show', ['policy' => $policy, 'company_id' => $company->id]));
        self::assertSame(3, DB::table('insurance_policy_rates')->where('insurance_policy_id', $policy)->count());
        $this->actingAs($user)->post(route('admin.payroll.insurance.profiles.store', ['employee' => $employee->id]), ['company_id' => $company->id, 'participation_status' => 'participating', 'insurance_policy_id' => $policy, 'effective_from' => '2026-01-01', 'insurance_number' => 'VN-PRIVATE-1234', 'currency_code' => 'VND'])->assertRedirect();
        $this->actingAs($user)->get(route('admin.payroll.insurance.employees.index', ['company_id' => $company->id]))->assertOk()->assertSee('***1234')->assertDontSee('VN-PRIVATE-1234');
        $this->actingAs($user)->get(route('admin.payroll.insurance.policies.show', ['policy' => $policy, 'company_id' => $company->id]))->assertOk()->assertSee('Internal Insurance Fixture');
    }

    public function test_overlap_and_cross_company_records_are_rejected(): void
    {
        [$a, $userA, $employeeA] = $this->fixture('OA');
        [$b, $userB] = $this->fixture('OB');
        $service = $this->app->make(InsuranceManagementService::class);
        $policy = $service->savePolicy($a->id, null, $this->policyPayload($a->id), $userA->id);
        $this->expectException(ValidationException::class);
        $service->savePolicy($a->id, null, array_merge($this->policyPayload($a->id), ['code' => 'OVERLAP']), $userA->id);
        self::assertFalse(DB::table('insurance_policies')->where('company_id', $b->id)->where('id', $policy)->exists());
        self::assertFalse(DB::table('employees')->where('company_id', $b->id)->where('id', $employeeA->id)->exists());
        self::assertNotSame($userA->id, $userB->id);
    }

    public function test_guest_and_missing_permission_are_blocked(): void
    {
        [$company, $user] = $this->fixture('AUTH');
        $this->get(route('admin.payroll.insurance.policies.index', ['company_id' => $company->id]))->assertRedirect('/login');
        $this->actingAs($user)->get(route('admin.payroll.insurance.policies.index', ['company_id' => $company->id]))->assertForbidden();
    }

    /** @return array{Company,User,Employee} */
    private function fixture(string $code): array
    {
        $company = Company::query()->create(['uuid' => (string) Str::uuid(), 'code' => 'INS-'.$code, 'name' => 'Schema Insurance '.$code, 'country_code' => 'VN', 'timezone' => 'Asia/Ho_Chi_Minh', 'currency_code' => 'VND', 'currency' => 'VND', 'status' => 'active', 'is_active' => true]);
        $user = User::factory()->create();
        DB::table('company_user_access')->insert(['company_id' => $company->id, 'user_id' => $user->id]);
        $employee = Employee::query()->create(['company_id' => $company->id, 'employee_code' => 'E-'.$code, 'full_name' => 'Employee '.$code, 'nationality' => 'VN', 'join_date' => '2026-01-01', 'employment_status' => 'active', 'employment_type' => 'full_time']);

        return [$company, $user, $employee];
    }

    /** @return array<string,mixed> */
    private function policyPayload(int $companyId): array
    {
        return ['company_id' => $companyId, 'code' => 'INTERNAL-2026', 'name' => 'Internal Insurance Fixture', 'version' => 1, 'effective_from' => '2026-01-01', 'effective_to' => '2026-12-31', 'status' => 'active', 'currency_code' => 'VND', 'rounding_rule' => 'half_up', 'rates' => [['insurance_type' => 'social', 'employee_rate_basis_points' => 800, 'employer_rate_basis_points' => 1750], ['insurance_type' => 'health', 'employee_rate_basis_points' => 150, 'employer_rate_basis_points' => 300], ['insurance_type' => 'unemployment', 'employee_rate_basis_points' => 100, 'employer_rate_basis_points' => 100]]];
    }
}
