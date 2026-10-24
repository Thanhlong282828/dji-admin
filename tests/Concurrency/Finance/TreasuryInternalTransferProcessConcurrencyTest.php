<?php

namespace Tests\Concurrency\Finance;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Finance\Application\Services\AccountingPostingPolicyService;
use Modules\Finance\Application\Services\TreasuryInternalTransferService;
use PHPUnit\Framework\Attributes\Group;
use Tests\Support\MysqlProcessRaceRunner;
use Tests\TestCase;

#[Group('mysql-concurrency')]
final class TreasuryInternalTransferProcessConcurrencyTest extends TestCase
{
    private const REPETITIONS = 10;

    public function test_duplicate_create_and_post_are_serialized_without_partial_evidence(): void
    {
        self::assertSame('schema_admin_testing', DB::connection()->getDatabaseName());
        for ($run = 1; $run <= self::REPETITIONS; $run++) {
            $fixture = $this->fixture($run);
            try {
                $payload = ['company_id' => $fixture['company'], 'actor_id' => $fixture['maker']->id, 'source_treasury_account_id' => $fixture['source'], 'destination_treasury_account_id' => $fixture['destination'], 'currency' => 'VND', 'amount_minor' => 30_000_000, 'occurred_at' => '2026-06-15 10:00:00', 'reference' => 'RACE-'.$run, 'reason' => 'Concurrency', 'idempotency_key' => 'transfer-'.$fixture['token']];
                $create = (new MysqlProcessRaceRunner)->run('treasury_transfer_create', $payload, $payload);
                self::assertSame([0, 0], $create['exit_codes'], implode("\n", $create['outputs']));
                $this->assertNoSqlLeakage($create['outputs']);
                $id = (int) DB::table('treasury_internal_transfers')->where(['company_id' => $fixture['company'], 'idempotency_key' => $payload['idempotency_key']])->value('id');
                self::assertSame(1, DB::table('treasury_internal_transfers')->where('id', $id)->count());
                self::assertSame(2, DB::table('treasury_internal_transfer_legs')->where('treasury_internal_transfer_id', $id)->count());

                $service = app(TreasuryInternalTransferService::class);
                $service->submit($fixture['company'], $id, $fixture['maker']);
                $service->approve($fixture['company'], $id, $fixture['checker']);
                $postPayload = ['company_id' => $fixture['company'], 'transfer_id' => $id, 'actor_id' => $fixture['checker']->id];
                $post = (new MysqlProcessRaceRunner)->run('treasury_transfer_post', $postPayload, $postPayload);
                self::assertSame([0, 0], $post['exit_codes'], implode("\n", $post['outputs']));
                $this->assertNoSqlLeakage($post['outputs']);
                self::assertSame(2, DB::table('treasury_transactions')->whereIn('source_id', DB::table('treasury_internal_transfer_legs')->where('treasury_internal_transfer_id', $id)->pluck('id'))->where('source_type', 'treasury_internal_transfer_leg')->count());
                self::assertSame(1, DB::table('payroll_accounting_journals')->where(['source_type' => 'treasury_internal_transfer', 'source_id' => $id])->count());
                $journal = DB::table('payroll_accounting_journals')->where(['source_type' => 'treasury_internal_transfer', 'source_id' => $id])->firstOrFail();
                self::assertSame((int) $journal->total_debit_minor, (int) $journal->total_credit_minor);

                $left = ['company_id' => $fixture['company'], 'transfer_id' => $id, 'actor_id' => $fixture['maker']->id, 'occurred_at' => '2026-06-20 10:00:00', 'reference' => 'REV-A-'.$run, 'reason' => 'Concurrent correction', 'idempotency_key' => 'reverse-a-'.$fixture['token']];
                $right = [...$left, 'reference' => 'REV-B-'.$run, 'idempotency_key' => 'reverse-b-'.$fixture['token']];
                $reverse = (new MysqlProcessRaceRunner)->run('treasury_transfer_reverse', $left, $right);
                self::assertSame([0, 0], $reverse['exit_codes'], implode("\n", $reverse['outputs']));
                $this->assertNoSqlLeakage($reverse['outputs']);
                self::assertSame(1, DB::table('treasury_internal_transfers')->where('reversal_of_transfer_id', $id)->count());
                $reversalId = (int) DB::table('treasury_internal_transfers')->where('reversal_of_transfer_id', $id)->value('id');
                $service->submit($fixture['company'], $reversalId, $fixture['maker']);
                $service->approve($fixture['company'], $reversalId, $fixture['checker']);
                $service->post($fixture['company'], $reversalId, $fixture['checker']);
                self::assertSame(1, DB::table('payroll_accounting_journals')->where(['source_type' => 'treasury_internal_transfer', 'source_id' => $reversalId])->count());
                self::assertSame(2, DB::table('treasury_transactions')->whereIn('source_id', DB::table('treasury_internal_transfer_legs')->where('treasury_internal_transfer_id', $reversalId)->pluck('id'))->where('source_type', 'treasury_internal_transfer_leg')->count());
            } finally {
                $this->cleanup($fixture);
            }
        }
    }

    /** @return array{token:string,maker:User,checker:User,company:int,branch:int,source:int,destination:int,policy:int} */
    private function fixture(int $run): array
    {
        $token = Str::lower(Str::random(12));
        $maker = User::factory()->create(['email' => "transfer-maker-{$token}@example.test"]);
        $checker = User::factory()->create(['email' => "transfer-checker-{$token}@example.test"]);
        $company = (int) DB::table('companies')->insertGetId(['uuid' => (string) Str::uuid(), 'code' => 'TF-'.$token, 'name' => 'Transfer Race '.$run, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
        $branch = (int) DB::table('branches')->insertGetId(['company_id' => $company, 'code' => 'HQ', 'name' => 'HQ', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
        $sourceLedger = $this->ledger($company, '1110', 'Source Bank', $maker->id);
        $destinationLedger = $this->ledger($company, '1111', 'Destination Cash', $maker->id);
        $source = $this->account($company, $branch, $sourceLedger, 'SOURCE', 'bank', $maker->id);
        $destination = $this->account($company, $branch, $destinationLedger, 'DEST', 'cash', $maker->id);
        DB::table('accounting_periods')->insert(['company_id' => $company, 'year' => 2026, 'month' => 6, 'start_date' => '2026-06-01', 'end_date' => '2026-06-30', 'status' => 'open', 'created_at' => now(), 'updated_at' => now()]);
        $policies = app(AccountingPostingPolicyService::class);
        $policy = $policies->create($company, $maker, ['policy_key' => 'TRANSFER-'.$token, 'source_type' => 'treasury_internal_transfer', 'business_event' => 'TREASURY_INTERNAL_TRANSFER', 'classification' => null, 'version' => 1, 'currency' => 'VND', 'effective_from' => '2026-01-01', 'lines' => [['entry_side' => 'debit', 'line_role' => 'DESTINATION_CASH', 'account_id' => $destinationLedger], ['entry_side' => 'credit', 'line_role' => 'SOURCE_CASH', 'account_id' => $sourceLedger]]]);
        $policies->submit($company, $policy, $maker);
        $policies->approve($company, $policy, $checker);

        return compact('token', 'maker', 'checker', 'company', 'branch', 'source', 'destination', 'policy');
    }

    private function ledger(int $company, string $code, string $name, int $actor): int
    {
        return (int) DB::table('accounting_accounts')->insertGetId(['uuid' => (string) Str::uuid(), 'company_id' => $company, 'code' => $code, 'name' => $name, 'account_type' => 'asset', 'normal_balance' => 'debit', 'currency' => 'VND', 'allow_posting' => true, 'status' => 'active', 'effective_from' => '2026-01-01', 'created_by' => $actor, 'created_at' => now(), 'updated_at' => now()]);
    }

    private function account(int $company, int $branch, int $ledger, string $code, string $type, int $actor): int
    {
        return (int) DB::table('treasury_financial_accounts')->insertGetId(['company_id' => $company, 'branch_id' => $branch, 'accounting_account_id' => $ledger, 'code' => $code, 'name' => $code, 'type' => $type, 'currency' => 'VND', 'status' => 'active', 'created_by' => $actor, 'created_at' => now(), 'updated_at' => now()]);
    }

    /** @param list<string> $outputs */
    private function assertNoSqlLeakage(array $outputs): void
    {
        foreach ($outputs as $output) {
            self::assertStringNotContainsString('SQLSTATE', $output);
            self::assertStringNotContainsString('QueryException', $output);
        }
    }

    /** @param array{maker:User,checker:User,company:int,branch:int,source:int,destination:int,policy:int} $fixture */
    private function cleanup(array $fixture): void
    {
        $journalIds = DB::table('payroll_accounting_journals')->where(['company_id' => $fixture['company'], 'source_type' => 'treasury_internal_transfer'])->pluck('id');
        DB::table('treasury_internal_transfer_legs')->whereIn('treasury_internal_transfer_id', DB::table('treasury_internal_transfers')->where('company_id', $fixture['company'])->pluck('id'))->update(['treasury_transaction_id' => null]);
        DB::table('treasury_transactions')->where('company_id', $fixture['company'])->delete();
        DB::table('treasury_internal_transfer_legs')->whereIn('treasury_internal_transfer_id', DB::table('treasury_internal_transfers')->where('company_id', $fixture['company'])->pluck('id'))->delete();
        DB::table('treasury_internal_transfers')->where('company_id', $fixture['company'])->delete();
        DB::table('accounting_journal_source_evidence')->whereIn('journal_id', $journalIds)->delete();
        DB::table('payroll_accounting_journal_lines')->whereIn('journal_id', $journalIds)->delete();
        DB::table('payroll_accounting_journals')->whereIn('id', $journalIds)->update(['reversal_of_journal_id' => null, 'reversed_by_journal_id' => null]);
        DB::table('payroll_accounting_journals')->whereIn('id', $journalIds)->delete();
        DB::table('accounting_periods')->where('company_id', $fixture['company'])->delete();
        DB::table('accounting_posting_policy_lines')->where('accounting_posting_policy_id', $fixture['policy'])->delete();
        DB::table('accounting_posting_policies')->where('id', $fixture['policy'])->delete();
        DB::table('treasury_financial_accounts')->whereIn('id', [$fixture['source'], $fixture['destination']])->delete();
        DB::table('accounting_accounts')->where('company_id', $fixture['company'])->delete();
        DB::table('branches')->where('id', $fixture['branch'])->delete();
        DB::table('companies')->where('id', $fixture['company'])->delete();
        $fixture['maker']->delete();
        $fixture['checker']->delete();
    }
}
