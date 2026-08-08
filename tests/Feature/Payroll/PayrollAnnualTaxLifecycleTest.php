<?php

namespace Tests\Feature\Payroll;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Modules\Payroll\Application\Commands\CreateAnnualTaxReconciliation\CreateAnnualTaxReconciliationCommand;
use Modules\Payroll\Domain\Enums\PayrollAnnualTaxReconciliationStatus;
use Modules\Payroll\Domain\Services\PayrollAnnualTaxLifecycleService;
use Modules\Payroll\Domain\Services\PayrollAnnualTaxOperationsService;
use Modules\Payroll\Domain\Services\PayrollAnnualTaxReconciliationService;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Tests\Support\Payroll\CreatesAppliedPayrollAdjustment;
use Tests\TestCase;

final class PayrollAnnualTaxLifecycleTest extends TestCase
{
    use CreatesAppliedPayrollAdjustment;
    use RefreshDatabase;

    public function test_calculate_validate_maker_checker_finalize_intents_and_supersede(): void
    {
        $fixture = $this->payrollAdjustmentFixture('ATLIFE');
        $policy = (int) DB::table('tax_policies')->insertGetId(['company_id' => $fixture['company'], 'code' => 'ANNUAL', 'name' => 'Annual policy snapshot', 'version' => 1, 'effective_from' => '2026-01-01', 'currency_code' => 'VND', 'tax_method' => 'progressive', 'residency_group' => 'resident', 'rounding_rule' => 'half_up', 'rounding_stage' => 'total', 'floor_taxable_income_at_zero' => true, 'status' => 'active', 'created_by' => $fixture['maker']->id, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('tax_policy_brackets')->insert(['tax_policy_id' => $policy, 'sequence' => 1, 'lower_bound_minor' => 0, 'upper_bound_minor' => null, 'rate_basis_points' => 1000, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('employee_tax_profiles')->insert(['company_id' => $fixture['company'], 'employee_id' => $fixture['employee'], 'tax_policy_id' => $policy, 'tax_identification_number' => '0312345678', 'tax_status' => 'taxable', 'residency_status' => 'resident', 'effective_from' => '2026-01-01', 'personal_deduction_eligible' => true, 'withholding_method' => 'policy', 'created_by' => $fixture['maker']->id, 'created_at' => now(), 'updated_at' => now()]);
        $sourceService = app(PayrollAnnualTaxReconciliationService::class);
        $draft = $sourceService->create(new CreateAnnualTaxReconciliationCommand($fixture['company'], $fixture['employee'], 2026, 'VN', 'VND', (string) Str::uuid(), $fixture['maker']->id));
        $sourceService->collect($fixture['company'], $draft->id, $fixture['maker']->id);
        $calculation = app(PayrollAnnualTaxOperationsService::class)->calculate($fixture['company'], $draft->id, $fixture['maker']->id);
        self::assertSame(2, $calculation->calculationVersion);
        self::assertSame('annual-tax-calculation-v1', DB::table('payroll_annual_tax_reconciliations')->where('id', $draft->id)->value('calculator_version'));
        self::assertTrue(app(PayrollAnnualTaxOperationsService::class)->validate($fixture['company'], $draft->id, $fixture['maker']->id)->valid);
        $lifecycle = app(PayrollAnnualTaxLifecycleService::class);
        $lifecycle->transition($fixture['company'], $draft->id, 'submit', $fixture['maker']->id);
        $reviewer = User::factory()->create();
        $approver = User::factory()->create();
        $finalizer = User::factory()->create();
        $lifecycle->transition($fixture['company'], $draft->id, 'review', $reviewer->id);
        $lifecycle->transition($fixture['company'], $draft->id, 'approve', $approver->id);
        $lifecycle->transition($fixture['company'], $draft->id, 'finalize', $finalizer->id);
        self::assertSame(PayrollAnnualTaxReconciliationStatus::Finalized->value, DB::table('payroll_annual_tax_reconciliations')->where('id', $draft->id)->value('status'));
        self::assertSame(4, DB::table('payroll_annual_tax_downstream_intents')->where('annual_tax_reconciliation_id', $draft->id)->count());
        $new = app(PayrollAnnualTaxOperationsService::class)->supersede($fixture['company'], $draft->id, $finalizer->id, 'late_source');
        self::assertSame(2, $new->newVersion);
        self::assertSame('superseded', DB::table('payroll_annual_tax_reconciliations')->where('id', $draft->id)->value('status'));
        self::assertSame(0, DB::table('payroll_annual_tax_sources')->where('annual_tax_reconciliation_id', $new->newReconciliationId)->count());
    }

    public function test_invalid_transition_and_creator_review_are_controlled(): void
    {
        $this->expectException(NotFoundHttpException::class);
        $fixture = $this->payrollAdjustmentFixture('ATINVALID');
        app(PayrollAnnualTaxLifecycleService::class)->transition($fixture['company'], 999999, 'submit', $fixture['maker']->id);
    }

    public function test_invalid_lifecycle_transition_matrix_does_not_mutate_state(): void
    {
        $fixture = $this->payrollAdjustmentFixture('AT-MATRIX');
        $draft = app(PayrollAnnualTaxReconciliationService::class)->create(new CreateAnnualTaxReconciliationCommand($fixture['company'], $fixture['employee'], 2026, 'VN', 'VND', (string) Str::uuid(), $fixture['maker']->id));
        $service = app(PayrollAnnualTaxLifecycleService::class);
        foreach ([['draft', 'submit'], ['draft', 'review'], ['draft', 'approve'], ['draft', 'finalize'], ['calculated', 'submit'], ['calculated', 'approve'], ['validated', 'approve'], ['submitted', 'approve'], ['submitted', 'finalize'], ['reviewed', 'finalize'], ['finalized', 'submit'], ['superseded', 'review']] as [$status,$action]) {
            DB::table('payroll_annual_tax_reconciliations')->where('id', $draft->id)->update(['status' => $status]);
            try {
                $service->transition($fixture['company'], $draft->id, $action, $fixture['checker']->id);
                self::fail("{$status}->{$action} must fail");
            } catch (ValidationException) {
                self::assertSame($status, DB::table('payroll_annual_tax_reconciliations')->where('id', $draft->id)->value('status'));
            }
        }
        self::assertSame(1, DB::table('payroll_annual_tax_histories')->where('annual_tax_reconciliation_id', $draft->id)->count());
    }
}
