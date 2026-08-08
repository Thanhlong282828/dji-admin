<?php

namespace Tests\Feature\Payroll;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Tests\Support\Payroll\PayrollAdjustmentDownstreamTestCase;

final class PayrollAdjustmentAccountingIntegrationTest extends PayrollAdjustmentDownstreamTestCase
{
    public function test_positive_and_negative_adjustments_create_balanced_idempotent_delta_journals(): void
    {
        foreach ([['POS', 5_100_000], ['NEG', 4_900_000]] as [$token, $corrected]) {
            $fixture = $this->payrollAdjustmentFixture('ACC-'.$token);
            $this->adjustmentAccountingMappings($fixture);
            $adjustment = $this->applyAdjustment($fixture, $corrected);
            $url = route('admin.payroll.adjustments.downstream', ['adjustment' => $adjustment, 'type' => 'accounting']);
            $this->actingAs($fixture['maker'])->post($url, ['company_id' => $fixture['company']])->assertRedirect();
            $journal = DB::table('payroll_accounting_journals')->where('adjustment_request_id', $adjustment)->first();
            $failure = DB::table('payroll_adjustment_downstream_intents')->where('adjustment_request_id', $adjustment)->where('intent_type', 'accounting_eligible')->value('failure_code');
            self::assertNotNull($journal, is_string($failure) ? $failure : 'Journal was not created.');
            self::assertSame('retroactive_adjustment', $journal->journal_source_type);
            self::assertNull($journal->payroll_run_id);
            self::assertSame($this->databaseInt($journal->total_debit_minor), $this->databaseInt($journal->total_credit_minor));
            self::assertGreaterThan(0, $this->databaseInt($journal->total_debit_minor));
            self::assertSame(0, DB::table('payroll_accounting_journal_lines')->where('journal_id', $journal->id)->where(fn (Builder $query): Builder => $query->where('debit_minor', '<', 0)->orWhere('credit_minor', '<', 0))->count());
            self::assertGreaterThan(0, DB::table('payroll_accounting_journal_sources')->where('journal_id', $journal->id)->where('downstream_source_type', 'retroactive_adjustment')->count());
            $this->actingAs($fixture['maker'])->post(route('admin.payroll.adjustments.downstream.retry', ['adjustment' => $adjustment, 'type' => 'accounting']), ['company_id' => $fixture['company']])->assertRedirect();
            self::assertSame(1, DB::table('payroll_accounting_journals')->where('adjustment_request_id', $adjustment)->count());
        }
    }
}
