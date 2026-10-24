<?php

namespace Tests\Feature\FiscalYear;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Modules\Finance\Application\Services\AccountingPeriodCloseService;
use Modules\Finance\Application\Services\FiscalYearGovernanceService;
use Modules\Finance\Application\Services\SourceAccountingPostingService;
use Tests\TestCase;

final class FiscalYearGovernanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_fiscal_year_and_period_are_tenant_scoped_and_reject_overlap(): void
    {
        $f = $this->fixture();
        $service = app(FiscalYearGovernanceService::class);
        $year = $service->createFiscalYear($f['company'], $f['maker'], ['name' => 'FY 2026', 'start_date' => '2026-01-01', 'end_date' => '2026-12-31']);
        self::assertDatabaseHas('accounting_fiscal_years', ['id' => $year, 'company_id' => $f['company'], 'status' => 'draft']);
        $service->openFiscalYear($f['company'], $year, $f['maker']);
        $period = $service->createPeriod($f['company'], $year, ['year' => 2026, 'month' => 1, 'start_date' => '2026-01-01', 'end_date' => '2026-01-31']);
        self::assertDatabaseHas('accounting_periods', ['id' => $period, 'company_id' => $f['company'], 'fiscal_year_id' => $year, 'status' => 'open']);

        $this->expectException(ValidationException::class);
        $service->createFiscalYear($f['company'], $f['maker'], ['name' => 'Overlapping FY', 'start_date' => '2026-06-01', 'end_date' => '2027-05-31']);
    }

    public function test_posting_is_allowed_before_period_close_and_rejected_after_close(): void
    {
        $f = $this->fixtureWithOpenPeriod();
        $posting = app(SourceAccountingPostingService::class);
        $posting->post($f['company'], null, 'fiscal_test', 1, 'period_guard', '2026-12-15', 'VND', $this->profitLines(1_000, 0), $f['maker']->id);
        $request = app(FiscalYearGovernanceService::class)->closePeriod($f['company'], $f['period'], $f['maker'], 'Runtime close verification');
        app(AccountingPeriodCloseService::class)->decide($f['company'], $request, true, $f['checker'], null);
        self::assertSame('closed', DB::table('accounting_periods')->where('id', $f['period'])->value('status'));

        try {
            $posting->post($f['company'], null, 'fiscal_test', 2, 'period_guard', '2026-12-16', 'VND', $this->profitLines(1_000, 0), $f['maker']->id);
            self::fail('Posting into a closed accounting period must be rejected.');
        } catch (ValidationException $exception) {
            self::assertSame('ACCOUNTING_PERIOD_CLOSED', $exception->errors()['period'][0] ?? null);
        }
    }

    public function test_year_close_calculates_profit_and_carries_balanced_opening_with_lineage(): void
    {
        $f = $this->fixtureWithOpenPeriod();
        $service = app(FiscalYearGovernanceService::class);
        $posting = app(SourceAccountingPostingService::class);
        $posting->post($f['company'], null, 'fiscal_revenue', 1, 'revenue', '2026-12-15', 'VND', $this->profitLines(1_000, 0), $f['maker']->id);
        $posting->post($f['company'], null, 'fiscal_expense', 1, 'expense', '2026-12-16', 'VND', $this->profitLines(0, 700), $f['maker']->id);

        $target = $service->createFiscalYear($f['company'], $f['maker'], ['name' => 'FY 2027', 'start_date' => '2027-01-01', 'end_date' => '2027-12-31']);
        $service->openFiscalYear($f['company'], $target, $f['maker']);
        $service->createPeriod($f['company'], $target, ['year' => 2027, 'month' => 1, 'start_date' => '2027-01-01', 'end_date' => '2027-01-31']);

        $periodClose = $service->closePeriod($f['company'], $f['period'], $f['maker'], 'Year-end period close');
        app(AccountingPeriodCloseService::class)->decide($f['company'], $periodClose, true, $f['checker'], null);
        $close = $service->closeFiscalYear($f['company'], $f['year'], $f['retained'], 'fiscal-close-runtime-2026', $f['maker'], 'Annual close');
        self::assertDatabaseHas('accounting_fiscal_year_close_requests', ['id' => $close, 'revenue_minor' => 1_000, 'expense_minor' => 700, 'net_income_minor' => 300, 'status' => 'submitted']);

        $openingJournal = $service->carryForward($f['company'], $close, $target, $f['checker']);
        $request = DB::table('accounting_fiscal_year_close_requests')->where('id', $close)->firstOrFail();
        self::assertSame('carried_forward', $request->status);
        self::assertSame($openingJournal, (int) $request->opening_journal_id);
        self::assertNotNull($request->closing_journal_id);
        self::assertSame(1, DB::table('payroll_accounting_journals')->where(['source_type' => 'fiscal_year_close', 'source_id' => $close])->count());
        self::assertSame(1, DB::table('payroll_accounting_journals')->where(['source_type' => 'fiscal_year_carry_forward', 'source_id' => $close])->count());

        foreach ([(int) $request->closing_journal_id, $openingJournal] as $journalId) {
            $journal = DB::table('payroll_accounting_journals')->where('id', $journalId)->firstOrFail();
            self::assertSame((int) $journal->total_debit_minor, (int) $journal->total_credit_minor);
        }
        self::assertSame(300, (int) DB::table('accounting_opening_balance_lines')->where(['target_fiscal_year_id' => $target, 'account_id' => $f['cash']])->value('debit_minor'));
        self::assertSame(300, (int) DB::table('accounting_opening_balance_lines')->where(['target_fiscal_year_id' => $target, 'account_id' => $f['retained']])->value('credit_minor'));
        self::assertSame((int) $request->closing_journal_id, (int) DB::table('accounting_opening_balance_lines')->where('target_fiscal_year_id', $target)->value('source_journal_id'));
    }

    /** @return array{company:int,maker:User,checker:User,cash:int,retained:int} */
    private function fixture(): array
    {
        $maker = User::factory()->create();
        $checker = User::factory()->create();
        $company = (int) DB::table('companies')->insertGetId(['uuid' => (string) Str::uuid(), 'code' => 'FY-'.Str::upper(Str::random(8)), 'name' => 'Fiscal Year Runtime', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
        foreach ([$maker, $checker] as $actor) {
            DB::table('company_user_access')->insert(['company_id' => $company, 'user_id' => $actor->id, 'created_at' => now(), 'updated_at' => now()]);
        }
        $cash = $this->account($company, $maker, '1100', 'Cash', 'asset', 'debit');
        $this->account($company, $maker, '4000', 'Revenue', 'revenue', 'credit');
        $this->account($company, $maker, '5100', 'Expense', 'expense', 'debit');
        $retained = $this->account($company, $maker, '3100', 'Retained Earnings', 'equity', 'credit');

        return compact('company', 'maker', 'checker', 'cash', 'retained');
    }

    /** @return array{company:int,maker:User,checker:User,cash:int,retained:int,year:int,period:int} */
    private function fixtureWithOpenPeriod(): array
    {
        $f = $this->fixture();
        $service = app(FiscalYearGovernanceService::class);
        $year = $service->createFiscalYear($f['company'], $f['maker'], ['name' => 'FY 2026', 'start_date' => '2026-01-01', 'end_date' => '2026-12-31']);
        $service->openFiscalYear($f['company'], $year, $f['maker']);
        $period = $service->createPeriod($f['company'], $year, ['year' => 2026, 'month' => 12, 'start_date' => '2026-12-01', 'end_date' => '2026-12-31']);

        return $f + compact('year', 'period');
    }

    /** @return list<array{account_code:string,debit:int,credit:int,description:string}> */
    private function profitLines(int $revenue, int $expense): array
    {
        if ($revenue > 0) {
            return [['account_code' => '1100', 'debit' => $revenue, 'credit' => 0, 'description' => 'Cash receipt'], ['account_code' => '4000', 'debit' => 0, 'credit' => $revenue, 'description' => 'Revenue']];
        }

        return [['account_code' => '5100', 'debit' => $expense, 'credit' => 0, 'description' => 'Expense'], ['account_code' => '1100', 'debit' => 0, 'credit' => $expense, 'description' => 'Cash payment']];
    }

    private function account(int $company, User $actor, string $code, string $name, string $type, string $balance): int
    {
        return (int) DB::table('accounting_accounts')->insertGetId(['uuid' => (string) Str::uuid(), 'company_id' => $company, 'code' => $code, 'name' => $name, 'account_type' => $type, 'normal_balance' => $balance, 'currency' => 'VND', 'allow_posting' => true, 'status' => 'active', 'effective_from' => '2026-01-01', 'created_by' => $actor->id, 'created_at' => now(), 'updated_at' => now()]);
    }
}
