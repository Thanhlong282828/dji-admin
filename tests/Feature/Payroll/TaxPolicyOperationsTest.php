<?php

namespace Tests\Feature\Payroll;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Modules\Company\Domain\Models\Company;
use Modules\Employee\Domain\Models\Employee;
use Modules\Payroll\Domain\Services\TaxManagementService;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

final class TaxPolicyOperationsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        foreach (['payroll.tax.view', 'payroll.tax.policy.create', 'payroll.tax.policy.update', 'payroll.tax.profile.manage'] as $p) {
            Permission::findOrCreate($p, 'web');
        }
    }

    public function test_employee_tax_profile_authorized_policy_and_masked_operations_are_tenant_scoped(): void
    {
        [$company,$user,$employee] = $this->fixture('A');
        $user->givePermissionTo(['payroll.tax.view', 'payroll.tax.policy.create', 'payroll.tax.profile.manage']);
        $response = $this->actingAs($user)->post(route('admin.payroll.tax.policies.store'), $this->policyPayload($company->id));
        $raw = DB::table('tax_policies')->where('company_id', $company->id)->value('id');
        self::assertIsNumeric($raw);
        $policy = (int) $raw;
        $response->assertRedirect(route('admin.payroll.tax.policies.show', ['policy' => $policy, 'company_id' => $company->id]));
        self::assertSame(3, DB::table('tax_policy_brackets')->where('tax_policy_id', $policy)->count());
        self::assertSame(3, DB::table('tax_policy_deductions')->where('tax_policy_id', $policy)->count());
        $this->actingAs($user)->post(route('admin.payroll.tax.profiles.store', ['employee' => $employee->id]), ['company_id' => $company->id, 'tax_status' => 'taxable', 'residency_status' => 'resident', 'tax_policy_id' => $policy, 'effective_from' => '2026-01-01', 'tax_identification_number' => 'TAX-PRIVATE-1234', 'personal_deduction_eligible' => 1, 'withholding_method' => 'policy'])->assertRedirect();
        $this->actingAs($user)->get(route('admin.payroll.tax.employees.index', ['company_id' => $company->id]))->assertOk()->assertSee('***1234')->assertDontSee('TAX-PRIVATE-1234');
    }

    public function test_overlap_invalid_brackets_guest_permission_and_foreign_record_are_blocked(): void
    {
        [$a,$userA] = $this->fixture('OA');
        [$b,$userB] = $this->fixture('OB');
        $service = $this->app->make(TaxManagementService::class);
        $policy = $service->savePolicy($a->id, null, $this->policyPayload($a->id), $userA->id);
        $this->get(route('admin.payroll.tax.policies.index', ['company_id' => $a->id]))->assertRedirect('/login');
        $this->actingAs($userB)->get(route('admin.payroll.tax.policies.index', ['company_id' => $b->id]))->assertForbidden();
        $userB->givePermissionTo('payroll.tax.view');
        $this->actingAs($userB)->get(route('admin.payroll.tax.policies.show', ['policy' => $policy, 'company_id' => $b->id]))->assertNotFound();
        $this->expectException(ValidationException::class);
        $service->savePolicy($a->id, null, array_merge($this->policyPayload($a->id), ['code' => 'OVERLAP']), $userA->id);
    }

    /** @return array{Company,User,Employee} */
    private function fixture(string $code): array
    {
        $company = Company::query()->create(['uuid' => (string) Str::uuid(), 'code' => 'TAX-'.$code, 'name' => 'Schema Tax '.$code, 'country_code' => 'VN', 'timezone' => 'Asia/Ho_Chi_Minh', 'currency_code' => 'VND', 'currency' => 'VND', 'status' => 'active', 'is_active' => true]);
        $user = User::factory()->create();
        DB::table('company_user_access')->insert(['company_id' => $company->id, 'user_id' => $user->id]);
        $employee = Employee::query()->create(['company_id' => $company->id, 'employee_code' => 'E-'.$code, 'full_name' => 'Employee '.$code, 'nationality' => 'VN', 'join_date' => '2026-01-01', 'employment_status' => 'active', 'employment_type' => 'full_time']);

        return [$company, $user, $employee];
    }

    /** @return array<string,mixed> */
    private function policyPayload(int $company): array
    {
        return ['company_id' => $company, 'code' => 'INTERNAL-TAX-2026', 'name' => 'Internal Tax Fixture', 'version' => 1, 'effective_from' => '2026-01-01', 'effective_to' => '2026-12-31', 'currency_code' => 'VND', 'tax_method' => 'progressive', 'residency_group' => 'resident', 'rounding_rule' => 'half_up', 'floor_taxable_income_at_zero' => 1, 'status' => 'active', 'brackets' => [['lower_bound_minor' => 0, 'upper_bound_minor' => 1_000_000, 'rate_basis_points' => 500], ['lower_bound_minor' => 1_000_000, 'upper_bound_minor' => 2_000_000, 'rate_basis_points' => 1000], ['lower_bound_minor' => 2_000_000, 'upper_bound_minor' => null, 'rate_basis_points' => 2000]], 'deductions' => [['code' => 'PERSONAL', 'name' => 'Personal', 'deduction_type' => 'personal', 'amount_minor' => 100_000], ['code' => 'DEPENDENT', 'name' => 'Dependent', 'deduction_type' => 'dependent', 'amount_minor' => 50_000], ['code' => 'INSURANCE', 'name' => 'Insurance', 'deduction_type' => 'employee_insurance']]];
    }
}
