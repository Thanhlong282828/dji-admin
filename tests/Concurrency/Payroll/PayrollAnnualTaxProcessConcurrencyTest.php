<?php

namespace Tests\Concurrency\Payroll;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Payroll\Domain\Services\PayrollAdjustmentBrowserFixtureService;
use Modules\Payroll\Domain\Services\PayrollAnnualTaxReconciliationService;
use PHPUnit\Framework\Attributes\Group;
use Tests\Support\MysqlProcessRaceRunner;
use Tests\TestCase;

#[Group('payroll-mysql-concurrency')]
final class PayrollAnnualTaxProcessConcurrencyTest extends TestCase
{
    public function test_create_calculate_and_finalize_process_races_are_consistent(): void
    {
        $baseService = app(PayrollAdjustmentBrowserFixtureService::class);
        $base = $baseService->create('testing', 'positive');
        try {
            $company = $base['company_id'];
            $this->clearAnnualTaxRows($company);
            $actor = $base['user_id'];
            $employee = $this->integer(DB::table('employees')->where('company_id', $company)->value('id'));
            DB::table('payroll_adjustment_requests')->where('company_id', $company)->update(['tax_attribution_year' => 2026]);
            $item = $this->integer(DB::table('payroll_items')->where('company_id', $company)->value('id'));
            DB::table('payroll_items')->where('id', $item)->update(['taxable_income_minor' => 5_000_000, 'personal_income_tax_minor' => 0]);
            $policy = (int) DB::table('tax_policies')->insertGetId(['company_id' => $company, 'code' => 'ANNUAL-RACE', 'name' => 'Annual race policy', 'version' => 1, 'effective_from' => '2026-01-01', 'currency_code' => 'VND', 'tax_method' => 'progressive', 'residency_group' => 'resident', 'rounding_rule' => 'half_up', 'rounding_stage' => 'total', 'floor_taxable_income_at_zero' => true, 'status' => 'active', 'created_by' => $actor, 'created_at' => now(), 'updated_at' => now()]);
            DB::table('tax_policy_brackets')->insert(['tax_policy_id' => $policy, 'sequence' => 1, 'lower_bound_minor' => 0, 'upper_bound_minor' => null, 'rate_basis_points' => 1000, 'created_at' => now(), 'updated_at' => now()]);
            DB::table('tax_policy_deductions')->insert(['tax_policy_id' => $policy, 'code' => 'PERSONAL', 'name' => 'Personal', 'deduction_type' => 'personal', 'amount_minor' => 0, 'priority' => 1, 'effective_from' => '2026-01-01', 'created_at' => now(), 'updated_at' => now()]);
            DB::table('employee_tax_profiles')->insert(['company_id' => $company, 'employee_id' => $employee, 'tax_policy_id' => $policy, 'tax_identification_number' => '0312345678', 'tax_status' => 'taxable', 'residency_status' => 'resident', 'effective_from' => '2026-01-01', 'personal_deduction_eligible' => true, 'withholding_method' => 'policy', 'created_by' => $actor, 'created_at' => now(), 'updated_at' => now()]);
            $payload = ['company_id' => $company, 'employee_id' => $employee, 'tax_year' => 2026, 'actor_id' => $actor];
            $race = (new MysqlProcessRaceRunner)->run('annual_tax_create', $payload + ['idempotency_key' => (string) Str::uuid()], $payload + ['idempotency_key' => (string) Str::uuid()]);
            self::assertSame([0, 2], $race['exit_codes'], implode("\n", $race['outputs']));
            self::assertSame(1, DB::table('payroll_annual_tax_reconciliations')->where('company_id', $company)->count());
            $id = $this->integer(DB::table('payroll_annual_tax_reconciliations')->where('company_id', $company)->value('id'));
            app(PayrollAnnualTaxReconciliationService::class)->collect($company, $id, $actor);
            $calc = (new MysqlProcessRaceRunner)->run('annual_tax_calculate', ['company_id' => $company, 'reconciliation_id' => $id, 'actor_id' => $actor], ['company_id' => $company, 'reconciliation_id' => $id, 'actor_id' => $actor]);
            self::assertSame([0, 0], $calc['exit_codes'], implode("\n", $calc['outputs']));
            self::assertSame(1, DB::table('payroll_annual_tax_histories')->where('annual_tax_reconciliation_id', $id)->where('action', 'calculated')->count());
            self::assertSame(2, $this->integer(DB::table('payroll_annual_tax_reconciliations')->where('id', $id)->value('calculation_version')));
            DB::table('payroll_annual_tax_reconciliations')->where('id', $id)->update(['status' => 'approved', 'approved_by' => $actor, 'approved_at' => now()]);
            $finalizerA = User::factory()->create()->id;
            $finalizerB = User::factory()->create()->id;
            $final = (new MysqlProcessRaceRunner)->run('annual_tax_finalize_downstream', ['company_id' => $company, 'reconciliation_id' => $id, 'actor_id' => $finalizerA], ['company_id' => $company, 'reconciliation_id' => $id, 'actor_id' => $finalizerB]);
            self::assertSame([0, 2], $final['exit_codes'], implode("\n", $final['outputs']));
            self::assertSame('finalized', DB::table('payroll_annual_tax_reconciliations')->where('id', $id)->value('status'));
            self::assertSame(4, DB::table('payroll_annual_tax_downstream_intents')->where('annual_tax_reconciliation_id', $id)->count());
            self::assertSame(1, DB::table('payroll_annual_tax_histories')->where('annual_tax_reconciliation_id', $id)->where('action', 'finalize')->count());
            $document = DB::table('payroll_annual_tax_documents')->where('annual_tax_reconciliation_id', $id)->first();
            if ($document !== null && is_string($document->storage_path ?? null)) {
                app('filesystem')->disk('local')->delete($document->storage_path);
            }
            DB::table('payroll_annual_tax_downstream_intents')->where('annual_tax_reconciliation_id', $id)->where('intent_type', 'annual_statement')->update(['status' => 'eligible', 'annual_tax_document_id' => null]);
            DB::table('payroll_annual_tax_access_logs')->where('annual_tax_reconciliation_id', $id)->delete();
            DB::table('payroll_annual_tax_documents')->where('annual_tax_reconciliation_id', $id)->delete();
            $basePayload = ['company_id' => $company, 'reconciliation_id' => $id, 'actor_id' => $actor];
            $statement = (new MysqlProcessRaceRunner)->run('annual_tax_statement_generate', $basePayload, $basePayload);
            self::assertContains($statement['exit_codes'], [[0, 0], [0, 2]], implode("\n", $statement['outputs']));
            self::assertSame(1, DB::table('payroll_annual_tax_documents')->where('annual_tax_reconciliation_id', $id)->count());
            DB::table('payroll_annual_tax_downstream_intents')->where('annual_tax_reconciliation_id', $id)->where('intent_type', 'additional_tax_recovery')->update(['status' => 'eligible', 'attempt_count' => 0]);
            $payment = (new MysqlProcessRaceRunner)->run('annual_tax_payment_intent', $basePayload, $basePayload);
            self::assertSame([0, 0], $payment['exit_codes'], implode("\n", $payment['outputs']));
            self::assertSame(1, DB::table('payroll_annual_tax_downstream_intents')->where('annual_tax_reconciliation_id', $id)->where('intent_type', 'additional_tax_recovery')->where('status', 'completed')->count());
            DB::table('payroll_annual_tax_downstream_intents')->where('annual_tax_reconciliation_id', $id)->where('intent_type', 'accounting_journal')->update(['status' => 'eligible', 'settlement_journal_id' => null]);
            DB::table('payroll_annual_tax_settlement_journal_lines')->where('company_id', $company)->delete();
            DB::table('payroll_annual_tax_settlement_journals')->where('company_id', $company)->delete();
            $accounting = (new MysqlProcessRaceRunner)->run('annual_tax_settlement_journal', $basePayload, $basePayload);
            self::assertContains($accounting['exit_codes'], [[0, 0], [0, 2]], implode("\n", $accounting['outputs']));
            self::assertSame(1, DB::table('payroll_annual_tax_settlement_journals')->where('annual_tax_reconciliation_id', $id)->count());
            $retry = (new MysqlProcessRaceRunner)->run('annual_tax_downstream_retry', $basePayload, $basePayload);
            self::assertSame([0, 0], $retry['exit_codes'], implode("\n", $retry['outputs']));
        } finally {
            DB::table('payroll_annual_tax_settlement_journal_lines')->where('company_id', $base['company_id'])->delete();
            DB::table('payroll_annual_tax_downstream_intents')->where('company_id', $base['company_id'])->update(['settlement_journal_id' => null, 'refund_obligation_id' => null]);
            DB::table('payroll_annual_tax_settlement_journals')->where('company_id', $base['company_id'])->delete();
            DB::table('payroll_annual_tax_refund_obligations')->where('company_id', $base['company_id'])->delete();
            $this->clearAnnualTaxRows($base['company_id']);
            DB::table('employee_tax_profiles')->where('company_id', $base['company_id'])->delete();
            $policies = DB::table('tax_policies')->where('company_id', $base['company_id'])->pluck('id');
            DB::table('tax_policy_deductions')->whereIn('tax_policy_id', $policies)->delete();
            DB::table('tax_policy_brackets')->whereIn('tax_policy_id', $policies)->delete();
            DB::table('tax_policies')->where('company_id', $base['company_id'])->delete();
            $baseService->cleanup('testing', 'positive');
        }
    }

    private function clearAnnualTaxRows(int $companyId): void
    {
        foreach (['payroll_annual_tax_access_logs', 'payroll_annual_tax_downstream_intents', 'payroll_annual_tax_documents', 'payroll_annual_tax_histories', 'payroll_annual_tax_validation_issues', 'payroll_annual_tax_components', 'payroll_annual_tax_sources', 'payroll_annual_tax_reconciliations'] as $table) {
            DB::table($table)->where('company_id', $companyId)->delete();
        }
    }

    private function integer(mixed $value): int
    {
        if (! is_numeric($value)) {
            self::fail('Expected numeric identifier.');
        }

        return (int) $value;
    }
}
