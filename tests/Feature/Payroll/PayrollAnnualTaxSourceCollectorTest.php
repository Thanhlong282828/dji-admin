<?php

namespace Tests\Feature\Payroll;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Modules\Payroll\Application\Commands\CreateAnnualTaxReconciliation\CreateAnnualTaxReconciliationCommand;
use Modules\Payroll\Domain\Services\PayrollAnnualTaxReconciliationService;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\Support\Payroll\CreatesAppliedPayrollAdjustment;
use Tests\TestCase;

final class PayrollAnnualTaxSourceCollectorTest extends TestCase
{
    use CreatesAppliedPayrollAdjustment;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        foreach (['payroll.adjustment.view', 'payroll.adjustment.create', 'payroll.adjustment.update', 'payroll.adjustment.calculate', 'payroll.adjustment.review', 'payroll.adjustment.approve', 'payroll.adjustment.apply', 'payroll.adjustment.payment.create', 'payroll.adjustment.accounting.create', 'payroll.adjustment.statement.generate', 'payroll.adjustment.statement.download', 'payroll.adjustment.downstream.retry'] as $permission) {
            Permission::findOrCreate($permission, 'web');
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function test_collector_reads_finalized_regular_and_explicitly_attributed_applied_adjustment_sources(): void
    {
        $fixture = $this->payrollAdjustmentFixture('ATCOLLECT');
        $adjustment = $this->applyAdjustment($fixture, 5_250_000);
        DB::table('payroll_adjustment_requests')->where('id', $adjustment)->update(['tax_attribution_year' => 2026]);
        $policy = (int) DB::table('tax_policies')->insertGetId(['company_id' => $fixture['company'], 'code' => 'AT-COLLECT', 'name' => 'Annual immutable policy', 'version' => 1, 'effective_from' => '2026-01-01', 'currency_code' => 'VND', 'tax_method' => 'progressive', 'residency_group' => 'resident', 'rounding_rule' => 'half_up', 'rounding_stage' => 'total', 'floor_taxable_income_at_zero' => true, 'status' => 'active', 'created_by' => $fixture['maker']->id, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('employee_tax_profiles')->insert(['company_id' => $fixture['company'], 'employee_id' => $fixture['employee'], 'tax_policy_id' => $policy, 'tax_identification_number' => '0312345678', 'tax_status' => 'taxable', 'residency_status' => 'resident', 'effective_from' => '2026-01-01', 'personal_deduction_eligible' => true, 'withholding_method' => 'policy', 'created_by' => $fixture['maker']->id, 'created_at' => now(), 'updated_at' => now()]);
        $service = app(PayrollAnnualTaxReconciliationService::class);
        $draft = $service->create(new CreateAnnualTaxReconciliationCommand($fixture['company'], $fixture['employee'], 2026, 'VN', 'VND', (string) Str::uuid(), $fixture['maker']->id));

        $result = $service->collect($fixture['company'], $draft->id, $fixture['maker']->id);

        self::assertSame(2, $result->sourceCount);
        self::assertSame(1, DB::table('payroll_annual_tax_sources')->where('source_type', 'regular_payroll')->count());
        self::assertSame(1, DB::table('payroll_annual_tax_sources')->where('source_type', 'retroactive_adjustment')->where('tax_attribution_year', 2026)->count());
        self::assertGreaterThan(0, $result->componentCount);
        self::assertTrue($service->verify($fixture['company'], $draft->id));

        $sourceCount = DB::table('payroll_annual_tax_sources')->count();
        $componentCount = DB::table('payroll_annual_tax_components')->count();
        $checksum = $result->sourceChecksum;
        $recollected = $service->collect($fixture['company'], $draft->id, $fixture['maker']->id);
        self::assertTrue($recollected->recollected);
        self::assertSame($sourceCount, DB::table('payroll_annual_tax_sources')->count());
        self::assertSame($componentCount, DB::table('payroll_annual_tax_components')->count());
        self::assertSame($checksum, $recollected->sourceChecksum);
        self::assertSame(1, DB::table('payroll_annual_tax_histories')->where('action', 'sources_collected')->count());
        self::assertSame(1, DB::table('payroll_annual_tax_histories')->where('action', 'sources_recollected')->count());

        $storedPolicy = DB::table('payroll_annual_tax_reconciliations')->where('id', $draft->id)->value('tax_policy_snapshot');
        $storedChecksum = DB::table('payroll_annual_tax_reconciliations')->where('id', $draft->id)->value('source_checksum');
        DB::table('tax_policies')->where('id', $policy)->update(['name' => 'Mutated live policy']);
        DB::table('employee_tax_profiles')->where('employee_id', $fixture['employee'])->update(['tax_identification_number' => '9999999999']);
        self::assertSame($storedPolicy, DB::table('payroll_annual_tax_reconciliations')->where('id', $draft->id)->value('tax_policy_snapshot'));
        self::assertSame($storedChecksum, DB::table('payroll_annual_tax_reconciliations')->where('id', $draft->id)->value('source_checksum'));
        self::assertTrue($service->verify($fixture['company'], $draft->id));

        DB::table('payroll_annual_tax_components')->where('annual_tax_reconciliation_id', $draft->id)->limit(1)->increment('amount_minor');
        self::assertFalse($service->verify($fixture['company'], $draft->id));
    }

    public function test_missing_adjustment_attribution_is_blocking_and_submitted_sources_are_immutable(): void
    {
        $fixture = $this->payrollAdjustmentFixture('ATBLOCK');
        $this->applyAdjustment($fixture, 5_100_000);
        $service = app(PayrollAnnualTaxReconciliationService::class);
        $draft = $service->create(new CreateAnnualTaxReconciliationCommand($fixture['company'], $fixture['employee'], 2026, 'VN', 'VND', (string) Str::uuid(), $fixture['maker']->id));
        $result = $service->collect($fixture['company'], $draft->id, $fixture['maker']->id);

        self::assertGreaterThan(0, $result->blockingIssueCount);
        self::assertSame(1, DB::table('payroll_annual_tax_validation_issues')->where('issue_code', 'missing_adjustment_tax_attribution_year')->count());
        DB::table('payroll_annual_tax_reconciliations')->where('id', $draft->id)->update(['status' => 'submitted']);

        $this->expectException(ValidationException::class);
        $service->collect($fixture['company'], $draft->id, $fixture['maker']->id);
    }
}
