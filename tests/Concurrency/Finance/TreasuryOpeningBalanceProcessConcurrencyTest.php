<?php

namespace Tests\Concurrency\Finance;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Finance\Application\Services\AccountingPostingPolicyService;
use Modules\Finance\Application\Services\TreasuryCashPositionReadService;
use Modules\Finance\Application\Services\TreasuryOpeningBalanceService;
use PHPUnit\Framework\Attributes\Group;
use Tests\Support\MysqlProcessRaceRunner;
use Tests\TestCase;

#[Group('mysql-concurrency')]
final class TreasuryOpeningBalanceProcessConcurrencyTest extends TestCase
{
    private const REPETITIONS = 10;

    public function test_duplicate_opening_basis_race_is_canonical_and_controlled(): void
    {
        $this->assertSafeDatabase();
        for ($run = 1; $run <= self::REPETITIONS; $run++) {
            $fixture = $this->fixture('BASIS-'.$run);
            try {
                $base = $this->createPayload($fixture, 100_000_000, '2026-01-01', null, 'BASIS-'.$run);
                $race = (new MysqlProcessRaceRunner)->run('treasury_opening_create', $base + ['idempotency_key' => 'basis-a-'.$fixture['token']], $base + ['idempotency_key' => 'basis-b-'.$fixture['token']]);
                $this->assertControlledRace($race);
                self::assertSame(1, DB::table('treasury_opening_balances')->where(['company_id' => $fixture['company'], 'treasury_financial_account_id' => $fixture['account'], 'currency' => 'VND', 'effective_date' => '2026-01-01'])->count());
                self::assertSame('NOT_ENOUGH_EVIDENCE', app(TreasuryCashPositionReadService::class)->read($fixture['company'], '2026-01-01')['available_cash']['VND']['status']);
            } finally {
                $this->cleanup($fixture);
            }
        }
    }

    public function test_duplicate_post_race_creates_one_balanced_journal_and_one_balance(): void
    {
        $this->assertSafeDatabase();
        for ($run = 1; $run <= self::REPETITIONS; $run++) {
            $fixture = $this->fixture('POST-'.$run);
            try {
                $opening = app(TreasuryOpeningBalanceService::class);
                $id = $opening->create($fixture['company'], $fixture['maker'], $this->createPayload($fixture, 100_000_000, '2026-01-01', null, 'POST-'.$run) + ['idempotency_key' => 'post-'.$fixture['token']]);
                $opening->submit($fixture['company'], $id, $fixture['maker']);
                $opening->approve($fixture['company'], $id, $fixture['checker']);
                $payload = ['company_id' => $fixture['company'], 'opening_balance_id' => $id, 'actor_id' => $fixture['checker']->id];
                $race = (new MysqlProcessRaceRunner)->run('treasury_opening_post', $payload, $payload);
                self::assertSame([0, 0], $race['exit_codes'], implode("\n", $race['outputs']));
                $this->assertPostedExactlyOnce($fixture, $id, 100_000_000);
            } finally {
                $this->cleanup($fixture);
            }
        }
    }

    public function test_duplicate_correction_post_race_preserves_original_and_applies_delta_once(): void
    {
        $this->assertSafeDatabase();
        for ($run = 1; $run <= self::REPETITIONS; $run++) {
            $fixture = $this->fixture('CORRECTION-'.$run);
            try {
                $opening = app(TreasuryOpeningBalanceService::class);
                $root = $opening->create($fixture['company'], $fixture['maker'], $this->createPayload($fixture, 100_000_000, '2026-01-01', null, 'ROOT-'.$run) + ['idempotency_key' => 'root-'.$fixture['token']]);
                $opening->submit($fixture['company'], $root, $fixture['maker']);
                $opening->approve($fixture['company'], $root, $fixture['checker']);
                $opening->post($fixture['company'], $root, $fixture['checker']);
                $rootBefore = DB::table('treasury_opening_balances')->where('id', $root)->firstOrFail();
                $correction = $opening->create($fixture['company'], $fixture['maker'], $this->createPayload($fixture, 95_000_000, '2026-02-01', $root, 'CORRECTION-'.$run) + ['idempotency_key' => 'correction-'.$fixture['token']]);
                $opening->submit($fixture['company'], $correction, $fixture['maker']);
                $opening->approve($fixture['company'], $correction, $fixture['checker']);
                $payload = ['company_id' => $fixture['company'], 'opening_balance_id' => $correction, 'actor_id' => $fixture['checker']->id];
                $race = (new MysqlProcessRaceRunner)->run('treasury_opening_post', $payload, $payload);
                self::assertSame([0, 0], $race['exit_codes'], implode("\n", $race['outputs']));
                $rootAfter = DB::table('treasury_opening_balances')->where('id', $root)->firstOrFail();
                self::assertEquals($rootBefore, $rootAfter);
                self::assertSame(-5_000_000, (int) DB::table('treasury_opening_balances')->where('id', $correction)->value('posting_adjustment_minor'));
                $this->assertPostedExactlyOnce($fixture, $correction, 95_000_000);
                self::assertSame(2, DB::table('payroll_accounting_journals')->where(['company_id' => $fixture['company'], 'source_type' => 'treasury_opening_balance'])->count());
            } finally {
                $this->cleanup($fixture);
            }
        }
    }

    /** @param array{exit_codes:list<int>,outputs:list<string>} $race */
    private function assertControlledRace(array $race): void
    {
        self::assertContains($race['exit_codes'], [[0, 0], [0, 2]], implode("\n", $race['outputs']));
        foreach ($race['outputs'] as $output) {
            self::assertStringNotContainsString('QueryException', $output);
            self::assertStringNotContainsString('SQLSTATE', $output);
        }
    }

    /** @param array{company:int,account:int} $fixture */
    private function assertPostedExactlyOnce(array $fixture, int $openingId, int $expectedBalance): void
    {
        $row = DB::table('treasury_opening_balances')->where('id', $openingId)->firstOrFail();
        self::assertSame('posted', $row->status);
        self::assertNotNull($row->accounting_journal_id);
        $journals = DB::table('payroll_accounting_journals')->where(['source_type' => 'treasury_opening_balance', 'source_id' => $openingId])->get();
        self::assertCount(1, $journals);
        self::assertSame((int) $journals[0]->total_debit_minor, (int) $journals[0]->total_credit_minor);
        self::assertSame(2, DB::table('payroll_accounting_journal_lines')->where('journal_id', $journals[0]->id)->count());
        self::assertSame(2, DB::table('accounting_journal_source_evidence')->where('journal_id', $journals[0]->id)->count());
        $position = app(TreasuryCashPositionReadService::class)->read($fixture['company'], '2026-02-01');
        self::assertSame('SUPPORTED', $position['available_cash']['VND']['status']);
        self::assertSame($expectedBalance, $position['available_cash']['VND']['available_cash_minor']);
    }

    /** @return array{token:string,maker:User,checker:User,company:int,branch:int,account:int,policy:int} */
    private function fixture(string $label): array
    {
        $token = Str::lower(Str::random(12));
        $maker = User::factory()->create(['email' => "treasury-maker-{$token}@example.test", 'email_verified_at' => now()]);
        $checker = User::factory()->create(['email' => "treasury-checker-{$token}@example.test", 'email_verified_at' => now()]);
        $company = (int) DB::table('companies')->insertGetId(['uuid' => (string) Str::uuid(), 'code' => 'TR-'.$token, 'name' => 'Treasury Race '.$label, 'country_code' => 'VN', 'timezone' => 'Asia/Ho_Chi_Minh', 'currency_code' => 'VND', 'currency' => 'VND', 'status' => 'active', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
        $branch = (int) DB::table('branches')->insertGetId(['company_id' => $company, 'code' => 'HQ', 'name' => 'HQ', 'status' => 'active', 'timezone' => 'Asia/Ho_Chi_Minh', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('company_user_access')->insert([['company_id' => $company, 'user_id' => $maker->id, 'created_at' => now(), 'updated_at' => now()], ['company_id' => $company, 'user_id' => $checker->id, 'created_at' => now(), 'updated_at' => now()]]);
        $cash = $this->ledger($company, '1110', 'Operating Bank', 'asset', 'debit', $maker->id);
        $equity = $this->ledger($company, '3000', 'Opening Equity', 'equity', 'credit', $maker->id);
        $account = (int) DB::table('treasury_financial_accounts')->insertGetId(['company_id' => $company, 'branch_id' => $branch, 'accounting_account_id' => $cash, 'code' => 'BANK-HQ', 'name' => 'Operating Bank', 'type' => 'bank', 'currency' => 'VND', 'status' => 'active', 'created_by' => $maker->id, 'created_at' => now(), 'updated_at' => now()]);
        $policies = app(AccountingPostingPolicyService::class);
        $policy = $policies->create($company, $maker, ['policy_key' => 'TREASURY-OPENING-'.$token, 'source_type' => 'treasury_opening_balance', 'business_event' => 'TREASURY_OPENING_BALANCE', 'classification' => 'bank', 'version' => 1, 'currency' => 'VND', 'effective_from' => '2026-01-01', 'lines' => [['entry_side' => 'debit', 'line_role' => 'CASH', 'account_id' => $cash], ['entry_side' => 'credit', 'line_role' => 'OPENING_EQUITY', 'account_id' => $equity]]]);
        $policies->submit($company, $policy, $maker);
        $policies->approve($company, $policy, $checker);

        return compact('token', 'maker', 'checker', 'company', 'branch', 'account', 'policy');
    }

    private function ledger(int $company, string $code, string $name, string $type, string $normal, int $actor): int
    {
        return (int) DB::table('accounting_accounts')->insertGetId(['uuid' => (string) Str::uuid(), 'company_id' => $company, 'code' => $code, 'name' => $name, 'account_type' => $type, 'normal_balance' => $normal, 'currency' => 'VND', 'allow_posting' => true, 'status' => 'active', 'effective_from' => '2026-01-01', 'created_by' => $actor, 'created_at' => now(), 'updated_at' => now()]);
    }

    /** @param array{token:string,maker:User,company:int,account:int} $fixture
     * @return array<string,bool|int|string|null>
     */
    private function createPayload(array $fixture, int $amount, string $date, ?int $supersedes, string $reference): array
    {
        return ['company_id' => $fixture['company'], 'actor_id' => $fixture['maker']->id, 'treasury_financial_account_id' => $fixture['account'], 'supersedes_opening_balance_id' => $supersedes, 'currency' => 'VND', 'effective_date' => $date, 'opening_amount_minor' => $amount, 'reference' => $reference.'-'.$fixture['token'], 'source_note' => 'Concurrency evidence'];
    }

    /** @param array{maker:User,checker:User,company:int,branch:int,account:int,policy:int} $fixture */
    private function cleanup(array $fixture): void
    {
        $journalIds = DB::table('payroll_accounting_journals')->where(['company_id' => $fixture['company'], 'source_type' => 'treasury_opening_balance'])->pluck('id');
        DB::table('treasury_opening_balances')->where('company_id', $fixture['company'])->whereNotNull('supersedes_opening_balance_id')->delete();
        DB::table('treasury_opening_balances')->where('company_id', $fixture['company'])->delete();
        DB::table('accounting_journal_source_evidence')->whereIn('journal_id', $journalIds)->delete();
        DB::table('payroll_accounting_journal_lines')->whereIn('journal_id', $journalIds)->delete();
        DB::table('payroll_accounting_journals')->whereIn('id', $journalIds)->delete();
        DB::table('accounting_periods')->where('company_id', $fixture['company'])->delete();
        DB::table('accounting_posting_policy_lines')->where('accounting_posting_policy_id', $fixture['policy'])->delete();
        DB::table('accounting_posting_policies')->where('id', $fixture['policy'])->delete();
        DB::table('treasury_financial_accounts')->where('id', $fixture['account'])->delete();
        DB::table('accounting_accounts')->where('company_id', $fixture['company'])->delete();
        DB::table('company_user_access')->where('company_id', $fixture['company'])->delete();
        DB::table('branches')->where('id', $fixture['branch'])->delete();
        DB::table('companies')->where('id', $fixture['company'])->delete();
        $fixture['maker']->delete();
        $fixture['checker']->delete();
    }

    private function assertSafeDatabase(): void
    {
        self::assertSame('testing', app()->environment());
        self::assertSame('mysql', DB::connection()->getDriverName());
        self::assertSame('schema_admin_testing', DB::connection()->getDatabaseName());
    }
}
