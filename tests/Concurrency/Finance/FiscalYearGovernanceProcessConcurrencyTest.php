<?php

namespace Tests\Concurrency\Finance;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Finance\Application\Services\AccountingPeriodCloseService;
use Modules\Finance\Application\Services\FiscalYearGovernanceService;
use Modules\Finance\Application\Services\SourceAccountingPostingService;
use PHPUnit\Framework\Attributes\Group;
use Tests\Support\MysqlProcessRaceRunner;
use Tests\TestCase;

#[Group('mysql-concurrency')]
final class FiscalYearGovernanceProcessConcurrencyTest extends TestCase
{
    public function test_competing_period_close_requests_have_one_winner(): void
    {
        $f = $this->fixture();
        try {
            $payload = ['company_id' => $f['company'], 'period_id' => $f['period'], 'actor_id' => $f['maker']->id];
            $race = (new MysqlProcessRaceRunner)->run('fiscal_period_close', $payload, $payload);
            self::assertSame([0, 2], $race['exit_codes'], implode("\n", $race['outputs']));
            self::assertSame(1, DB::table('accounting_period_close_requests')->where(['company_id' => $f['company'], 'accounting_period_id' => $f['period'], 'status' => 'pending'])->count());
        } finally {
            $this->cleanup($f);
        }
    }

    public function test_competing_carry_forward_creates_one_close_and_one_opening_journal(): void
    {
        $f = $this->fixture();
        try {
            $posting = app(SourceAccountingPostingService::class);
            $posting->post($f['company'], null, 'race_revenue', 1, 'revenue', '2026-12-15', 'VND', [['account_code' => '1100', 'debit' => 1_000, 'credit' => 0, 'description' => 'Cash'], ['account_code' => '4000', 'debit' => 0, 'credit' => 1_000, 'description' => 'Revenue']], $f['maker']->id);
            $service = app(FiscalYearGovernanceService::class);
            $periodClose = $service->closePeriod($f['company'], $f['period'], $f['maker'], 'Year close');
            app(AccountingPeriodCloseService::class)->decide($f['company'], $periodClose, true, $f['checker'], null);
            $target = $service->createFiscalYear($f['company'], $f['maker'], ['name' => 'FY 2027', 'start_date' => '2027-01-01', 'end_date' => '2027-12-31']);
            $service->openFiscalYear($f['company'], $target, $f['maker']);
            $service->createPeriod($f['company'], $target, ['year' => 2027, 'month' => 1, 'start_date' => '2027-01-01', 'end_date' => '2027-01-31']);
            $close = $service->closeFiscalYear($f['company'], $f['year'], $f['retained'], 'race-close-'.$f['token'], $f['maker'], null);
            $payload = ['company_id' => $f['company'], 'close_request_id' => $close, 'target_fiscal_year_id' => $target, 'actor_id' => $f['checker']->id];
            $race = (new MysqlProcessRaceRunner)->run('fiscal_carry_forward', $payload, $payload);
            self::assertSame([0, 0], $race['exit_codes'], implode("\n", $race['outputs']));
            self::assertSame(1, DB::table('payroll_accounting_journals')->where(['company_id' => $f['company'], 'source_type' => 'fiscal_year_close', 'source_id' => $close])->count());
            self::assertSame(1, DB::table('payroll_accounting_journals')->where(['company_id' => $f['company'], 'source_type' => 'fiscal_year_carry_forward', 'source_id' => $close])->count());
        } finally {
            $this->cleanup($f);
        }
    }

    /** @return array<string,mixed> */
    private function fixture(): array
    {
        self::assertSame('testing', app()->environment());
        self::assertSame('schema_admin_testing', DB::connection()->getDatabaseName());
        $token = Str::lower(Str::random(10));
        $maker = User::factory()->create(['email' => "fy-maker-{$token}@example.test"]);
        $checker = User::factory()->create(['email' => "fy-checker-{$token}@example.test"]);
        $company = (int) DB::table('companies')->insertGetId(['uuid' => (string) Str::uuid(), 'code' => 'FY-'.$token, 'name' => 'Fiscal Race', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
        foreach ([$maker, $checker] as $actor) {
            DB::table('company_user_access')->insert(['company_id' => $company, 'user_id' => $actor->id, 'created_at' => now(), 'updated_at' => now()]);
        }
        $this->account($company, $maker, '1100', 'Cash', 'asset', 'debit');
        $this->account($company, $maker, '4000', 'Revenue', 'revenue', 'credit');
        $retained = $this->account($company, $maker, '3100', 'Retained Earnings', 'equity', 'credit');
        $service = app(FiscalYearGovernanceService::class);
        $year = $service->createFiscalYear($company, $maker, ['name' => 'FY 2026', 'start_date' => '2026-01-01', 'end_date' => '2026-12-31']);
        $service->openFiscalYear($company, $year, $maker);
        $period = $service->createPeriod($company, $year, ['year' => 2026, 'month' => 12, 'start_date' => '2026-12-01', 'end_date' => '2026-12-31']);

        return compact('token', 'maker', 'checker', 'company', 'retained', 'year', 'period');
    }

    private function account(int $company, User $actor, string $code, string $name, string $type, string $balance): int
    {
        return (int) DB::table('accounting_accounts')->insertGetId(['uuid' => (string) Str::uuid(), 'company_id' => $company, 'code' => $code, 'name' => $name, 'account_type' => $type, 'normal_balance' => $balance, 'currency' => 'VND', 'allow_posting' => true, 'status' => 'active', 'effective_from' => '2026-01-01', 'created_by' => $actor->id, 'created_at' => now(), 'updated_at' => now()]);
    }

    /** @param array<string,mixed> $f */
    private function cleanup(array $f): void
    {
        $journals = DB::table('payroll_accounting_journals')->where('company_id', $f['company'])->pluck('id');
        DB::table('accounting_journal_source_evidence')->whereIn('journal_id', $journals)->delete();
        DB::table('accounting_opening_balance_lines')->where('company_id', $f['company'])->delete();
        DB::table('accounting_fiscal_year_close_requests')->where('company_id', $f['company'])->delete();
        DB::table('payroll_accounting_journal_lines')->whereIn('journal_id', $journals)->delete();
        DB::table('payroll_accounting_journals')->whereIn('id', $journals)->delete();
        DB::table('accounting_period_close_checks')->whereIn('close_request_id', DB::table('accounting_period_close_requests')->where('company_id', $f['company'])->pluck('id'))->delete();
        DB::table('accounting_period_close_requests')->where('company_id', $f['company'])->delete();
        DB::table('accounting_periods')->where('company_id', $f['company'])->delete();
        DB::table('accounting_fiscal_years')->where('company_id', $f['company'])->delete();
        DB::table('accounting_accounts')->where('company_id', $f['company'])->delete();
        DB::table('company_user_access')->where('company_id', $f['company'])->delete();
        DB::table('companies')->where('id', $f['company'])->delete();
        $f['maker']->delete();
        $f['checker']->delete();
    }
}
