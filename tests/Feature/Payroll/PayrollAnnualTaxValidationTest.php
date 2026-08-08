<?php

namespace Tests\Feature\Payroll;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Company\Domain\Models\Company;
use Modules\Employee\Domain\Models\Employee;
use Modules\Payroll\Application\Commands\CreateAnnualTaxReconciliation\CreateAnnualTaxReconciliationCommand;
use Modules\Payroll\Domain\Enums\PayrollAnnualTaxIssueCode;
use Modules\Payroll\Domain\Enums\PayrollAnnualTaxValidationStage;
use Modules\Payroll\Domain\Services\PayrollAnnualTaxReconciliationService;
use Modules\Payroll\Domain\Services\PayrollAnnualTaxValidator;
use Tests\TestCase;

final class PayrollAnnualTaxValidationTest extends TestCase
{
    use RefreshDatabase;

    public function test_missing_snapshot_and_source_issues_are_blocking_and_fingerprint_deduplicated(): void
    {
        $company = Company::query()->create(['uuid' => (string) Str::uuid(), 'code' => 'ATVALID', 'name' => 'Schema Annual Validation', 'country_code' => 'VN', 'timezone' => 'Asia/Ho_Chi_Minh', 'currency_code' => 'VND', 'currency' => 'VND', 'status' => 'active', 'is_active' => true]);
        $employee = Employee::query()->create(['company_id' => $company->id, 'employee_code' => 'AT-V', 'full_name' => 'Annual Validation Employee', 'nationality' => 'VN', 'join_date' => '2026-01-01', 'employment_status' => 'active', 'employment_type' => 'full_time']);
        $actor = User::factory()->create();
        $draft = app(PayrollAnnualTaxReconciliationService::class)->create(new CreateAnnualTaxReconciliationCommand($company->id, $employee->id, 2026, 'VN', 'VND', (string) Str::uuid(), $actor->id));
        $validator = app(PayrollAnnualTaxValidator::class);
        $first = $validator->validate($company->id, $draft->id, PayrollAnnualTaxValidationStage::Calculation);
        $count = DB::table('payroll_annual_tax_validation_issues')->where('annual_tax_reconciliation_id', $draft->id)->whereNull('resolved_at')->count();
        $second = $validator->validate($company->id, $draft->id, PayrollAnnualTaxValidationStage::Calculation);
        self::assertFalse($first->valid);
        self::assertFalse($second->valid);
        self::assertSame($count, DB::table('payroll_annual_tax_validation_issues')->where('annual_tax_reconciliation_id', $draft->id)->whereNull('resolved_at')->count());
        foreach ([PayrollAnnualTaxIssueCode::NoEligibleSources, PayrollAnnualTaxIssueCode::MissingTaxIdentifier, PayrollAnnualTaxIssueCode::MissingTaxResidency, PayrollAnnualTaxIssueCode::MissingTaxPolicySnapshot] as $code) {
            self::assertTrue(DB::table('payroll_annual_tax_validation_issues')->where('annual_tax_reconciliation_id', $draft->id)->where('issue_code', $code->value)->whereNull('resolved_at')->exists());
        }
        self::assertSame($count, DB::table('payroll_annual_tax_validation_issues')->where('annual_tax_reconciliation_id', $draft->id)->distinct()->count('fingerprint'));
    }

    public function test_checksum_dependent_evidence_and_stale_calculation_matrix_is_blocking(): void
    {
        $company = Company::query()->create(['uuid' => (string) Str::uuid(), 'code' => 'AT-MATRIX', 'name' => 'Schema Annual Matrix', 'country_code' => 'VN', 'timezone' => 'Asia/Ho_Chi_Minh', 'currency_code' => 'VND', 'currency' => 'VND', 'status' => 'active', 'is_active' => true]);
        $employee = Employee::query()->create(['company_id' => $company->id, 'employee_code' => 'AT-MATRIX-E', 'full_name' => 'Annual Matrix Employee', 'nationality' => 'VN', 'join_date' => '2026-01-01', 'employment_status' => 'active', 'employment_type' => 'full_time']);
        $actor = User::factory()->create();
        $draft = app(PayrollAnnualTaxReconciliationService::class)->create(new CreateAnnualTaxReconciliationCommand($company->id, $employee->id, 2026, 'VN', 'VND', (string) Str::uuid(), $actor->id));
        DB::table('payroll_annual_tax_reconciliations')->where('id', $draft->id)->update(['taxpayer_snapshot' => json_encode(['tax_identifier' => '0312345678', 'tax_residency' => 'resident'], JSON_THROW_ON_ERROR), 'tax_policy_snapshot' => json_encode(['residency_group' => 'resident', 'deductions' => [['type' => 'dependent']]], JSON_THROW_ON_ERROR), 'source_checksum' => str_repeat('a', 64), 'calculated_source_checksum' => str_repeat('b', 64), 'calculation_version' => 1]);
        DB::table('payroll_annual_tax_sources')->insert(['annual_tax_reconciliation_id' => $draft->id, 'company_id' => $company->id, 'employee_id' => $employee->id, 'source_type' => 'regular_payroll', 'source_reference' => 'matrix-source', 'source_period_year' => 2026, 'source_period_month' => 1, 'tax_attribution_year' => 2026, 'source_calculation_version' => 1, 'gross_income_minor' => 1, 'taxable_income_minor' => 1, 'non_taxable_income_minor' => 0, 'employee_insurance_deduction_minor' => 0, 'personal_deduction_minor' => 0, 'dependent_deduction_minor' => 0, 'other_deduction_minor' => 0, 'pit_withheld_minor' => 0, 'currency' => 'VND', 'source_checksum' => str_repeat('c', 64), 'source_sort_key' => 'matrix', 'created_at' => now(), 'updated_at' => now()]);
        $result = app(PayrollAnnualTaxValidator::class)->validate($company->id, $draft->id, PayrollAnnualTaxValidationStage::PreSubmit);
        self::assertFalse($result->valid);
        foreach ([PayrollAnnualTaxIssueCode::SourceChecksumMismatch, PayrollAnnualTaxIssueCode::MissingDependentEvidence, PayrollAnnualTaxIssueCode::CalculationStale] as $code) {
            self::assertTrue(DB::table('payroll_annual_tax_validation_issues')->where('annual_tax_reconciliation_id', $draft->id)->where('issue_code', $code->value)->whereNull('resolved_at')->exists());
        }
    }
}
