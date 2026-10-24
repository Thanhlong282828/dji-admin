<?php

namespace Tests\Concurrency\Finance;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Finance\Application\Services\AccountingPostingPolicyService;
use Modules\Finance\Application\Services\FxRateService;
use Modules\Finance\Application\Services\FxRevaluableSource;
use Modules\Finance\Application\Services\FxRevaluationService;
use PHPUnit\Framework\Attributes\Group;
use Tests\Support\MysqlProcessRaceRunner;
use Tests\TestCase;

#[Group('mysql-concurrency')]
final class FxFoundationProcessConcurrencyTest extends TestCase
{
    public function test_rate_approval_race_persists_one_canonical_approval(): void
    {
        $f = $this->fixture('approval');
        try {
            $rate = $this->rate($f, 'approval-a', '2026-10-01', null);
            app(FxRateService::class)->transition($f['company'], $rate, 'submit', $f['maker']);
            $base = ['company_id' => $f['company'], 'rate_id' => $rate];
            $race = (new MysqlProcessRaceRunner)->run('fx_rate_approve', $base + ['actor_id' => $f['checker']->id], $base + ['actor_id' => $f['reviewer']->id]);

            self::assertSame([0, 2], $race['exit_codes'], implode("\n", $race['outputs']));
            $row = DB::table('finance_fx_rates')->where('id', $rate)->first();
            self::assertSame('approved', $row?->status);
            self::assertContains((int) $row?->approved_by, [$f['checker']->id, $f['reviewer']->id]);
            self::assertNotNull($row?->approved_at);
            self::assertSame(1, DB::table('finance_fx_rates')->where('id', $rate)->count());
        } finally {
            $this->cleanup($f);
        }
    }

    public function test_overlapping_rate_activation_race_never_persists_two_active_windows(): void
    {
        $f = $this->fixture('activation');
        try {
            $first = $this->approvedRate($f, 'activation-a', '2026-10-01', '2026-10-31');
            $second = $this->approvedRate($f, 'activation-b', '2026-10-15', '2026-11-15');
            $race = (new MysqlProcessRaceRunner)->run(
                'fx_rate_activate',
                ['company_id' => $f['company'], 'rate_id' => $first, 'actor_id' => $f['reviewer']->id],
                ['company_id' => $f['company'], 'rate_id' => $second, 'actor_id' => $f['reviewer']->id],
            );

            self::assertSame([0, 2], $race['exit_codes'], implode("\n", $race['outputs']));
            self::assertSame(1, DB::table('finance_fx_rates')->where('company_id', $f['company'])->where(['base_currency' => 'USD', 'quote_currency' => 'VND', 'rate_type' => 'closing', 'status' => 'active'])->count());
        } finally {
            $this->cleanup($f);
        }
    }

    public function test_snapshot_race_resolves_to_one_immutable_canonical_row(): void
    {
        $f = $this->fixture('snapshot');
        try {
            $rate = $this->approvedRate($f, 'snapshot-a', '2026-10-01', null);
            app(FxRateService::class)->transition($f['company'], $rate, 'activate', $f['reviewer']);
            $payload = ['company_id' => $f['company'], 'rate_id' => $rate, 'source_type' => 'fx_race_source', 'source_id' => 71_001];
            $race = (new MysqlProcessRaceRunner)->run('fx_snapshot_capture', $payload, $payload);

            self::assertSame([0, 0], $race['exit_codes'], implode("\n", $race['outputs']));
            $snapshots = DB::table('finance_fx_rate_snapshots')->where(['company_id' => $f['company'], 'source_type' => 'fx_race_source', 'source_id' => 71_001])->get();
            self::assertCount(1, $snapshots);
            self::assertSame(64, strlen((string) $snapshots->first()->checksum));
        } finally {
            $this->cleanup($f);
        }
    }

    public function test_revaluation_post_race_creates_one_accounting_effect(): void
    {
        $f = $this->fixture('post');
        try {
            $run = $this->approvedRun($f, 72_001);
            $payload = ['company_id' => $f['company'], 'run_id' => $run, 'actor_id' => $f['reviewer']->id];
            $race = (new MysqlProcessRaceRunner)->run('fx_revaluation_post', $payload, $payload);

            self::assertSame([0, 0], $race['exit_codes'], implode("\n", $race['outputs']));
            $stored = DB::table('finance_fx_revaluation_runs')->where('id', $run)->first();
            self::assertSame('posted', $stored?->status);
            self::assertNotNull($stored?->accounting_journal_id);
            self::assertSame(1, DB::table('payroll_accounting_journals')->where(['company_id' => $f['company'], 'source_type' => 'fx_revaluation', 'source_id' => $run])->whereNull('reversal_of_journal_id')->count());
            self::assertSame(2, DB::table('payroll_accounting_journal_lines')->where('journal_id', $stored?->accounting_journal_id)->count());
        } finally {
            $this->cleanup($f);
        }
    }

    public function test_revaluation_reversal_race_creates_one_mirror_journal(): void
    {
        $f = $this->fixture('reverse');
        try {
            $run = $this->approvedRun($f, 73_001);
            $original = app(FxRevaluationService::class)->post($f['company'], $run, $f['reviewer']);
            self::assertNotNull($original);
            $payload = ['company_id' => $f['company'], 'run_id' => $run, 'actor_id' => $f['reviewer']->id];
            $race = (new MysqlProcessRaceRunner)->run('fx_revaluation_reverse', $payload, $payload);

            self::assertSame([0, 0], $race['exit_codes'], implode("\n", $race['outputs']));
            $stored = DB::table('finance_fx_revaluation_runs')->where('id', $run)->first();
            self::assertSame('reversed', $stored?->status);
            self::assertNotNull($stored?->reversal_journal_id);
            self::assertSame(1, DB::table('payroll_accounting_journals')->where('reversal_of_journal_id', $original)->count());
        } finally {
            $this->cleanup($f);
        }
    }

    /** @return array<string,mixed> */
    private function fixture(string $label): array
    {
        $this->assertSafeDatabase();
        $token = Str::lower(Str::random(10));
        $maker = User::factory()->create(['email' => "fx-maker-{$token}@example.test"]);
        $checker = User::factory()->create(['email' => "fx-checker-{$token}@example.test"]);
        $reviewer = User::factory()->create(['email' => "fx-reviewer-{$token}@example.test"]);
        $company = (int) DB::table('companies')->insertGetId(['uuid' => (string) Str::uuid(), 'code' => 'FX-'.$token, 'name' => 'FX Race '.$label, 'currency_code' => 'VND', 'functional_currency_code' => 'VND', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
        $branch = (int) DB::table('branches')->insertGetId(['company_id' => $company, 'code' => 'HQ-'.$token, 'name' => 'HQ', 'status' => 'active', 'timezone' => 'Asia/Ho_Chi_Minh', 'created_at' => now(), 'updated_at' => now()]);
        foreach ([$maker, $checker, $reviewer] as $actor) {
            DB::table('company_user_access')->insert(['company_id' => $company, 'user_id' => $actor->id, 'created_at' => now(), 'updated_at' => now()]);
        }
        $year = (int) DB::table('accounting_fiscal_years')->insertGetId(['company_id' => $company, 'name' => 'FY 2026', 'start_date' => '2026-01-01', 'end_date' => '2026-12-31', 'status' => 'open', 'created_by' => $maker->id, 'version' => 1, 'created_at' => now(), 'updated_at' => now()]);
        $period = (int) DB::table('accounting_periods')->insertGetId(['company_id' => $company, 'fiscal_year_id' => $year, 'year' => 2026, 'month' => 10, 'start_date' => '2026-10-01', 'end_date' => '2026-10-31', 'status' => 'open', 'created_at' => now(), 'updated_at' => now()]);
        $asset = $this->account($company, $maker, 'FX-ASSET-'.$token, 'asset', 'debit');
        $gain = $this->account($company, $maker, 'FX-GAIN-'.$token, 'revenue', 'credit');
        $loss = $this->account($company, $maker, 'FX-LOSS-'.$token, 'expense', 'debit');
        $this->policy($company, $maker, $checker, 'FX_UNREALIZED_GAIN', [['line_role' => 'RECEIVABLE', 'entry_side' => 'debit', 'account_id' => $asset], ['line_role' => 'REVENUE', 'entry_side' => 'credit', 'account_id' => $gain]]);
        $this->policy($company, $maker, $checker, 'FX_UNREALIZED_LOSS', [['line_role' => 'EXPENSE', 'entry_side' => 'debit', 'account_id' => $loss], ['line_role' => 'RECEIVABLE', 'entry_side' => 'credit', 'account_id' => $asset]]);

        return compact('token', 'maker', 'checker', 'reviewer', 'company', 'branch', 'year', 'period');
    }

    /** @param array<string,mixed> $f */
    private function rate(array $f, string $key, string $from, ?string $to): int
    {
        return app(FxRateService::class)->create($f['company'], $f['maker'], ['base_currency' => 'USD', 'quote_currency' => 'VND', 'rate_decimal' => '25.000000000000', 'rate_date' => '2026-10-03', 'rate_type' => 'closing', 'source_reference' => strtoupper($key), 'effective_from' => $from, 'effective_to' => $to, 'idempotency_key' => (string) Str::uuid()]);
    }

    /** @param array<string,mixed> $f */
    private function approvedRate(array $f, string $key, string $from, ?string $to): int
    {
        $id = $this->rate($f, $key, $from, $to);
        $service = app(FxRateService::class);
        $service->transition($f['company'], $id, 'submit', $f['maker']);
        $service->transition($f['company'], $id, 'approve', $f['checker']);

        return $id;
    }

    /** @param array<string,mixed> $f */
    private function approvedRun(array $f, int $sourceId): int
    {
        $rate = $this->approvedRate($f, 'run-'.$sourceId, '2026-10-01', null);
        app(FxRateService::class)->transition($f['company'], $rate, 'activate', $f['reviewer']);
        $service = app(FxRevaluationService::class);
        $source = new FxRevaluableSource('fx_race_source', $sourceId, $f['company'], $f['branch'], 'USD', 100, 'VND', 2400, 'asset', $sourceId, hash('sha256', 'recognition-'.$sourceId));
        $run = $service->create($f['company'], $f['branch'], $f['period'], '2026-10-03', (string) Str::uuid(), $f['maker'], [$source]);
        $service->transition($f['company'], $run, 'submit', $f['maker']);
        $service->transition($f['company'], $run, 'approve', $f['checker']);

        return $run;
    }

    private function account(int $company, User $actor, string $code, string $type, string $normal): int
    {
        return (int) DB::table('accounting_accounts')->insertGetId(['uuid' => (string) Str::uuid(), 'company_id' => $company, 'code' => $code, 'name' => $code, 'account_type' => $type, 'normal_balance' => $normal, 'currency' => 'VND', 'allow_posting' => true, 'status' => 'active', 'effective_from' => '2026-01-01', 'created_by' => $actor->id, 'created_at' => now(), 'updated_at' => now()]);
    }

    /** @param list<array{line_role:string,entry_side:string,account_id:int}> $lines */
    private function policy(int $company, User $maker, User $checker, string $event, array $lines): int
    {
        $service = app(AccountingPostingPolicyService::class);
        $id = $service->create($company, $maker, ['policy_key' => $event.'-'.$company, 'source_type' => 'fx_revaluation', 'business_event' => $event, 'classification' => null, 'version' => 1, 'currency' => 'VND', 'effective_from' => '2026-01-01', 'effective_to' => null, 'lines' => $lines]);
        $service->submit($company, $id, $maker);
        $service->approve($company, $id, $checker);

        return $id;
    }

    /** @param array<string,mixed> $f */
    private function cleanup(array $f): void
    {
        $journals = DB::table('payroll_accounting_journals')->where('company_id', $f['company'])->pluck('id');
        DB::table('accounting_journal_source_evidence')->whereIn('journal_id', $journals)->delete();
        DB::table('payroll_accounting_journal_lines')->whereIn('journal_id', $journals)->delete();
        DB::table('finance_fx_revaluation_lines')->where('company_id', $f['company'])->delete();
        DB::table('finance_fx_revaluation_runs')->where('company_id', $f['company'])->delete();
        DB::table('payroll_accounting_journals')->whereIn('id', $journals)->whereNotNull('reversal_of_journal_id')->delete();
        DB::table('payroll_accounting_journals')->whereIn('id', $journals)->delete();
        DB::table('finance_fx_rate_snapshots')->where('company_id', $f['company'])->delete();
        DB::table('finance_fx_rates')->where('company_id', $f['company'])->delete();
        DB::table('accounting_posting_policy_lines')->whereIn('accounting_posting_policy_id', DB::table('accounting_posting_policies')->where('company_id', $f['company'])->select('id'))->delete();
        DB::table('accounting_posting_policies')->where('company_id', $f['company'])->delete();
        DB::table('accounting_periods')->where('company_id', $f['company'])->delete();
        DB::table('accounting_fiscal_years')->where('company_id', $f['company'])->delete();
        DB::table('accounting_accounts')->where('company_id', $f['company'])->delete();
        DB::table('company_user_access')->where('company_id', $f['company'])->delete();
        DB::table('branches')->where('company_id', $f['company'])->delete();
        DB::table('companies')->where('id', $f['company'])->delete();
        $f['maker']->forceDelete();
        $f['checker']->forceDelete();
        $f['reviewer']->forceDelete();
    }

    private function assertSafeDatabase(): void
    {
        self::assertSame('testing', app()->environment());
        self::assertSame('mysql', DB::connection()->getDriverName());
        self::assertSame('schema_admin_testing', DB::connection()->getDatabaseName());
    }
}
