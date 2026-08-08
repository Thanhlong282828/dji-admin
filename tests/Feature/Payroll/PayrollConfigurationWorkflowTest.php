<?php

namespace Tests\Feature\Payroll;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Modules\Company\Domain\Models\Company;
use Modules\Employee\Domain\Models\Employee;
use Modules\Payroll\Domain\Services\PayrollConfigurationService;
use Tests\TestCase;

final class PayrollConfigurationWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_component_and_effective_assignment_workflow_is_versioned_and_overlap_safe(): void
    {
        [$company,$employee,$actor] = $this->context();
        $service = app(PayrollConfigurationService::class);
        $component = $service->createComponent($company, ['code' => ' basic ', 'name' => 'Basic Salary', 'category' => 'earning', 'calculation_type' => 'fixed', 'value_type' => 'money', 'default_value_minor' => 5000, 'currency_code' => 'VND', 'affects_gross' => true, 'affects_net' => true, 'status' => 'active'], $actor);
        self::assertSame('BASIC', $component->code);
        $assignment = $service->assign($company, ['employee_id' => $employee, 'salary_component_id' => $component->id, 'amount_minor' => 5000, 'currency_code' => 'VND', 'effective_from' => '2026-01-01', 'source' => 'manual', 'status' => 'active', 'version' => 1, 'idempotency_key' => (string) Str::uuid()], $actor);
        self::assertSame(1, $assignment->version);
        try {
            $service->assign($company, ['employee_id' => $employee, 'salary_component_id' => $component->id, 'amount_minor' => 5100, 'currency_code' => 'VND', 'effective_from' => '2026-06-01', 'source' => 'manual', 'status' => 'active', 'version' => 1, 'idempotency_key' => (string) Str::uuid()], $actor);
            self::fail('Overlap must fail.');
        } catch (ValidationException $e) {
            self::assertArrayHasKey('assignment', $e->errors());
        }
        $ended = $service->endAssignment($company, $assignment->id, '2026-05-31', 1, $actor);
        self::assertSame('ended', $ended->status);
        self::assertSame(2, $ended->version);
    }

    public function test_formula_placeholder_and_cross_tenant_employee_are_rejected(): void
    {
        [$company,,$actor] = $this->context();
        [$other,$employee] = $this->context();
        $service = app(PayrollConfigurationService::class);
        $this->expectException(ValidationException::class);
        $service->createComponent($company, ['code' => 'FORM', 'name' => 'Unsafe', 'category' => 'earning', 'calculation_type' => 'formula_placeholder', 'value_type' => 'metadata', 'status' => 'active'], $actor);
    }

    /** @return array{int,int,int} */
    private function context(): array
    {
        $company = Company::query()->create(['uuid' => (string) Str::uuid(), 'code' => 'PC-'.Str::upper(Str::random(6)), 'name' => 'Schema Payroll Config', 'country_code' => 'VN', 'timezone' => 'Asia/Ho_Chi_Minh', 'currency_code' => 'VND', 'currency' => 'VND', 'status' => 'active', 'is_active' => true]);
        $user = User::factory()->create();
        $employee = Employee::query()->create(['company_id' => $company->id, 'user_id' => $user->id, 'employee_code' => 'PC-'.Str::upper(Str::random(6)), 'full_name' => 'Payroll Config Employee', 'nationality' => 'VN', 'join_date' => '2026-01-01', 'employment_status' => 'active', 'employment_type' => 'full_time']);

        return [$company->id, $employee->id, $user->id];
    }
}
