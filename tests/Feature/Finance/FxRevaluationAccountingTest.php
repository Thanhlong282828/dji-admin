<?php

namespace Tests\Feature\Finance;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Modules\Company\Domain\Models\Company;
use Modules\Finance\Application\Services\AccountingPostingPolicyService;
use Modules\Finance\Application\Services\FxRateService;
use Modules\Finance\Application\Services\FxRevaluableSource;
use Modules\Finance\Application\Services\FxRevaluationService;
use Tests\TestCase;

final class FxRevaluationAccountingTest extends TestCase
{
    use RefreshDatabase;

    public function test_four_way_asset_liability_sign_matrix_posts_policy_backed_balanced_journals(): void
    {
        $fixture = $this->fixture();
        $cases = [
            ['asset', 2400, 'fx_unrealized_gain'],
            ['asset', 2600, 'fx_unrealized_loss'],
            ['liability', 2400, 'fx_unrealized_loss'],
            ['liability', 2600, 'fx_unrealized_gain'],
        ];
        foreach ($cases as $index => [$nature, $carrying, $expectedPosting]) {
            $run = $this->approvedRun($fixture, $index + 1, $nature, $carrying);
            $journal = app(FxRevaluationService::class)->post($fixture['company']->id, $run, $fixture['poster']);
            self::assertNotNull($journal);
            self::assertDatabaseHas('finance_fx_revaluation_runs', ['id' => $run, 'status' => 'posted', 'accounting_journal_id' => $journal]);
            self::assertDatabaseHas('payroll_accounting_journals', ['id' => $journal, 'company_id' => $fixture['company']->id, 'branch_id' => $fixture['branch'], 'accounting_period_id' => $fixture['period'], 'posting_type' => $expectedPosting, 'total_debit_minor' => 100, 'total_credit_minor' => 100]);
            self::assertSame(2, DB::table('payroll_accounting_journal_lines')->where('journal_id', $journal)->count());
            self::assertSame(100, (int) DB::table('payroll_accounting_journal_lines')->where('journal_id', $journal)->sum('debit_minor'));
            self::assertSame(100, (int) DB::table('payroll_accounting_journal_lines')->where('journal_id', $journal)->sum('credit_minor'));
            self::assertDatabaseHas('finance_fx_revaluation_lines', ['fx_revaluation_run_id' => $run, 'accounting_policy_id' => $expectedPosting === 'fx_unrealized_gain' ? $fixture['gain_policy'] : $fixture['loss_policy'], 'policy_version' => 1]);
            self::assertSame($journal, app(FxRevaluationService::class)->post($fixture['company']->id, $run, $fixture['poster']));
            self::assertSame(1, DB::table('payroll_accounting_journals')->where(['source_type' => 'fx_revaluation', 'source_id' => $run])->whereNull('reversal_of_journal_id')->count());
        }
    }

    public function test_gain_and_loss_reversal_mirror_original_and_are_idempotent(): void
    {
        $fixture = $this->fixture();
        foreach ([['asset', 2400], ['asset', 2600]] as $index => [$nature, $carrying]) {
            $run = $this->approvedRun($fixture, $index + 20, $nature, $carrying);
            $original = app(FxRevaluationService::class)->post($fixture['company']->id, $run, $fixture['poster']);
            self::assertNotNull($original);
            $reversal = app(FxRevaluationService::class)->reverse($fixture['company']->id, $run, $fixture['poster']);
            self::assertSame($reversal, app(FxRevaluationService::class)->reverse($fixture['company']->id, $run, $fixture['poster']));
            self::assertDatabaseHas('payroll_accounting_journals', ['id' => $reversal, 'reversal_of_journal_id' => $original, 'total_debit_minor' => 100, 'total_credit_minor' => 100]);
            self::assertDatabaseHas('finance_fx_revaluation_runs', ['id' => $run, 'status' => 'reversed', 'reversal_journal_id' => $reversal]);
            $originalLines = DB::table('payroll_accounting_journal_lines')->where('journal_id', $original)->orderBy('line_number')->get();
            $reversalLines = DB::table('payroll_accounting_journal_lines')->where('journal_id', $reversal)->orderBy('line_number')->get();
            foreach ($originalLines as $lineIndex => $line) {
                self::assertSame((int) $line->debit_minor, (int) $reversalLines[$lineIndex]->credit_minor);
                self::assertSame((int) $line->credit_minor, (int) $reversalLines[$lineIndex]->debit_minor);
            }
            self::assertSame(1, DB::table('payroll_accounting_journals')->where('reversal_of_journal_id', $original)->count());
        }
    }

    public function test_zero_difference_and_missing_policy_fail_without_partial_journal(): void
    {
        $fixture = $this->fixture();
        $zero = $this->approvedRun($fixture, 40, 'asset', 2500);
        try {
            app(FxRevaluationService::class)->post($fixture['company']->id, $zero, $fixture['poster']);
            self::fail('Zero difference must not post.');
        } catch (ValidationException $exception) {
            self::assertSame('FX_REVALUATION_ZERO_DIFFERENCE_NO_POSTING_REQUIRED', $exception->errors()['accounting'][0]);
        }
        self::assertDatabaseHas('finance_fx_revaluation_runs', ['id' => $zero, 'status' => 'approved', 'accounting_journal_id' => null]);

        DB::table('accounting_posting_policies')->where('id', $fixture['gain_policy'])->update(['status' => 'retired']);
        $missing = $this->approvedRun($fixture, 41, 'asset', 2400);
        try {
            app(FxRevaluationService::class)->post($fixture['company']->id, $missing, $fixture['poster']);
            self::fail('Missing policy must fail closed.');
        } catch (ValidationException $exception) {
            self::assertSame('ACCOUNTING_POSTING_POLICY_MISSING', $exception->errors()['accounting_policy'][0]);
        }
        self::assertDatabaseHas('finance_fx_revaluation_runs', ['id' => $missing, 'status' => 'approved', 'accounting_journal_id' => null]);
        self::assertSame(0, DB::table('payroll_accounting_journals')->where(['source_type' => 'fx_revaluation', 'source_id' => $missing])->count());
    }

    public function test_closed_period_closed_fiscal_year_and_missing_loss_policy_roll_back_atomically(): void
    {
        $fixture = $this->fixture();
        $periodRun = $this->approvedRun($fixture, 50, 'asset', 2400);
        DB::table('accounting_periods')->where('id', $fixture['period'])->update(['status' => 'closed']);
        $this->assertPostingRejected($fixture, $periodRun, 'period', 'ACCOUNTING_PERIOD_CLOSED');

        DB::table('accounting_periods')->where('id', $fixture['period'])->update(['status' => 'open']);
        DB::table('accounting_fiscal_years')->where('id', $fixture['year'])->update(['status' => 'closed']);
        $fiscalRun = $this->approvedRun($fixture, 51, 'asset', 2400);
        $this->assertPostingRejected($fixture, $fiscalRun, 'fiscal_year', 'FISCAL_YEAR_CLOSED');

        DB::table('accounting_fiscal_years')->where('id', $fixture['year'])->update(['status' => 'open']);
        DB::table('accounting_posting_policies')->where('id', $fixture['loss_policy'])->update(['status' => 'retired']);
        $lossRun = $this->approvedRun($fixture, 52, 'asset', 2600);
        $this->assertPostingRejected($fixture, $lossRun, 'accounting_policy', 'ACCOUNTING_POSTING_POLICY_MISSING');
    }

    /** @return array<string,mixed> */
    private function fixture(): array
    {
        $company = Company::factory()->create(['functional_currency_code' => 'VND']);
        $maker = User::factory()->create();
        $checker = User::factory()->create();
        $poster = User::factory()->create();
        DB::table('company_user_access')->insert(collect([$maker, $checker, $poster])->map(fn (User $user): array => ['company_id' => $company->id, 'user_id' => $user->id, 'created_at' => now(), 'updated_at' => now()])->all());
        $branch = (int) DB::table('branches')->insertGetId(['company_id' => $company->id, 'code' => 'FX-HQ', 'name' => 'FX HQ', 'status' => 'active', 'timezone' => 'Asia/Ho_Chi_Minh', 'created_at' => now(), 'updated_at' => now()]);
        $year = (int) DB::table('accounting_fiscal_years')->insertGetId(['company_id' => $company->id, 'name' => 'FY 2026', 'start_date' => '2026-01-01', 'end_date' => '2026-12-31', 'status' => 'open', 'created_by' => $maker->id, 'version' => 1, 'created_at' => now(), 'updated_at' => now()]);
        $period = (int) DB::table('accounting_periods')->insertGetId(['company_id' => $company->id, 'fiscal_year_id' => $year, 'year' => 2026, 'month' => 10, 'start_date' => '2026-10-01', 'end_date' => '2026-10-31', 'status' => 'open', 'created_at' => now(), 'updated_at' => now()]);
        $asset = $this->account($company->id, $maker, 'FX-ASSET', 'asset', 'debit');
        $gain = $this->account($company->id, $maker, 'FX-GAIN', 'revenue', 'credit');
        $loss = $this->account($company->id, $maker, 'FX-LOSS', 'expense', 'debit');
        $gainPolicy = $this->policy($company->id, $maker, $checker, 'FX_UNREALIZED_GAIN', [['line_role' => 'RECEIVABLE', 'entry_side' => 'debit', 'account_id' => $asset], ['line_role' => 'REVENUE', 'entry_side' => 'credit', 'account_id' => $gain]]);
        $lossPolicy = $this->policy($company->id, $maker, $checker, 'FX_UNREALIZED_LOSS', [['line_role' => 'EXPENSE', 'entry_side' => 'debit', 'account_id' => $loss], ['line_role' => 'RECEIVABLE', 'entry_side' => 'credit', 'account_id' => $asset]]);
        $rate = app(FxRateService::class);
        $rateId = $rate->create($company->id, $maker, ['base_currency' => 'USD', 'quote_currency' => 'VND', 'rate_decimal' => '2500.000000000000', 'rate_date' => '2026-10-03', 'rate_type' => 'closing', 'source_reference' => 'TEST-CLOSING', 'effective_from' => '2026-10-01', 'effective_to' => null, 'idempotency_key' => (string) Str::uuid()]);
        $rate->transition($company->id, $rateId, 'submit', $maker);
        $rate->transition($company->id, $rateId, 'approve', $checker);
        $rate->transition($company->id, $rateId, 'activate', $checker);

        return ['company' => $company, 'maker' => $maker, 'checker' => $checker, 'poster' => $poster, 'branch' => $branch, 'year' => $year, 'period' => $period, 'gain_policy' => $gainPolicy, 'loss_policy' => $lossPolicy];
    }

    /** @param array<string,mixed> $fixture */
    private function approvedRun(array $fixture, int $sourceId, string $nature, int $carrying): int
    {
        $source = new FxRevaluableSource('test_fx_source', $sourceId, $fixture['company']->id, $fixture['branch'], 'USD', 100, 'VND', $carrying, $nature, $sourceId, hash('sha256', 'recognition-'.$sourceId));
        $service = app(FxRevaluationService::class);
        $run = $service->create($fixture['company']->id, $fixture['branch'], $fixture['period'], '2026-10-03', (string) Str::uuid(), $fixture['maker'], [$source]);
        $service->transition($fixture['company']->id, $run, 'submit', $fixture['maker']);
        $service->transition($fixture['company']->id, $run, 'approve', $fixture['checker']);

        return $run;
    }

    private function account(int $companyId, User $actor, string $code, string $type, string $normal): int
    {
        return (int) DB::table('accounting_accounts')->insertGetId(['uuid' => (string) Str::uuid(), 'company_id' => $companyId, 'code' => $code, 'name' => $code, 'account_type' => $type, 'normal_balance' => $normal, 'currency' => 'VND', 'allow_posting' => true, 'status' => 'active', 'effective_from' => '2026-01-01', 'created_by' => $actor->id, 'created_at' => now(), 'updated_at' => now()]);
    }

    /** @param array<string,mixed> $fixture */
    private function assertPostingRejected(array $fixture, int $run, string $field, string $message): void
    {
        try {
            app(FxRevaluationService::class)->post($fixture['company']->id, $run, $fixture['poster']);
            self::fail('Posting must fail closed.');
        } catch (ValidationException $exception) {
            self::assertSame($message, $exception->errors()[$field][0]);
        }
        self::assertDatabaseHas('finance_fx_revaluation_runs', ['id' => $run, 'status' => 'approved', 'accounting_journal_id' => null]);
        self::assertSame(0, DB::table('payroll_accounting_journals')->where(['source_type' => 'fx_revaluation', 'source_id' => $run])->count());
    }

    /** @param list<array{line_role:string,entry_side:string,account_id:int}> $lines */
    private function policy(int $companyId, User $maker, User $checker, string $event, array $lines): int
    {
        $service = app(AccountingPostingPolicyService::class);
        $id = $service->create($companyId, $maker, ['policy_key' => $event, 'source_type' => 'fx_revaluation', 'business_event' => $event, 'classification' => null, 'version' => 1, 'currency' => 'VND', 'effective_from' => '2026-01-01', 'effective_to' => null, 'lines' => $lines]);
        $service->submit($companyId, $id, $maker);
        $service->approve($companyId, $id, $checker);

        return $id;
    }
}
