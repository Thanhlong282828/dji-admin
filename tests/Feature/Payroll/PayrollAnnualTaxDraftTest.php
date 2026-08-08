<?php

namespace Tests\Feature\Payroll;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Modules\Company\Domain\Models\Company;
use Modules\Employee\Domain\Models\Employee;
use Modules\Payroll\Application\Commands\CreateAnnualTaxReconciliation\CreateAnnualTaxReconciliationCommand;
use Modules\Payroll\Domain\Services\PayrollAnnualTaxReconciliationService;
use Tests\TestCase;

final class PayrollAnnualTaxDraftTest extends TestCase
{
    use RefreshDatabase;

    public function test_draft_captures_snapshots_is_idempotent_and_rejects_a_second_active_draft(): void
    {
        [$company, $employee, $actor] = $this->fixture('DRAFT');
        $service = app(PayrollAnnualTaxReconciliationService::class);
        $key = (string) Str::uuid();
        $command = new CreateAnnualTaxReconciliationCommand($company, $employee, 2026, 'VN', 'VND', $key, $actor);

        $created = $service->create($command);
        $retried = $service->create($command);

        self::assertSame($created->id, $retried->id);
        self::assertFalse($created->existing);
        self::assertTrue($retried->existing);
        self::assertSame('draft', $created->status);
        self::assertSame('AT-DRAFT', $created->taxpayerSnapshot['employee_code']);
        self::assertSame('0123456789', $created->taxpayerSnapshot['tax_identifier']);
        self::assertSame(1, DB::table('payroll_annual_tax_reconciliations')->count());
        self::assertSame(1, DB::table('payroll_annual_tax_histories')->where('action', 'created')->count());

        $this->expectException(ValidationException::class);
        $service->create(new CreateAnnualTaxReconciliationCommand($company, $employee, 2026, 'VN', 'VND', (string) Str::uuid(), $actor));
    }

    /** @return array{int, int, int} */
    private function fixture(string $token): array
    {
        $actor = User::factory()->create();
        $company = Company::query()->create(['uuid' => (string) Str::uuid(), 'code' => 'AT-'.$token, 'name' => 'Schema Annual Tax '.$token, 'country_code' => 'VN', 'timezone' => 'Asia/Ho_Chi_Minh', 'currency_code' => 'VND', 'currency' => 'VND', 'status' => 'active', 'is_active' => true]);
        $employee = Employee::query()->create(['company_id' => $company->id, 'employee_code' => 'AT-'.$token, 'full_name' => 'Annual Tax Employee', 'nationality' => 'VN', 'join_date' => '2026-01-01', 'employment_status' => 'active', 'employment_type' => 'full_time']);
        $policy = (int) DB::table('tax_policies')->insertGetId(['company_id' => $company->id, 'code' => 'AT-POLICY', 'name' => 'Annual Tax Policy', 'version' => 1, 'effective_from' => '2026-01-01', 'currency_code' => 'VND', 'tax_method' => 'progressive', 'residency_group' => 'resident', 'rounding_rule' => 'half_up', 'rounding_stage' => 'total', 'floor_taxable_income_at_zero' => true, 'status' => 'active', 'created_by' => $actor->id, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('employee_tax_profiles')->insert(['company_id' => $company->id, 'employee_id' => $employee->id, 'tax_policy_id' => $policy, 'tax_identification_number' => '0123456789', 'tax_status' => 'taxable', 'residency_status' => 'resident', 'effective_from' => '2026-01-01', 'personal_deduction_eligible' => true, 'withholding_method' => 'policy', 'created_by' => $actor->id, 'created_at' => now(), 'updated_at' => now()]);

        return [$company->id, $employee->id, $actor->id];
    }
}
