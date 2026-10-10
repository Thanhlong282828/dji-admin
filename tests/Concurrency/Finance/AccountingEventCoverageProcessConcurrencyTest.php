<?php

namespace Tests\Concurrency\Finance;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Finance\Application\Services\AccountingPostingPolicyService;
use Modules\Finance\Application\Services\SourceAccountingPostingService;
use PHPUnit\Framework\Attributes\Group;
use Tests\Support\MysqlProcessRaceRunner;
use Tests\TestCase;

#[Group('mysql-concurrency')]
final class AccountingEventCoverageProcessConcurrencyTest extends TestCase
{
    public function test_receipt_collection_and_cogs_duplicate_posts_create_one_policy_backed_journal(): void
    {
        $this->assertSafeDatabase();
        $token = Str::lower(Str::random(10));
        $maker = User::factory()->create(['email' => "event-maker-{$token}@example.test"]);
        $checker = User::factory()->create(['email' => "event-checker-{$token}@example.test"]);
        $company = (int) DB::table('companies')->insertGetId(['uuid' => (string) Str::uuid(), 'code' => 'EVENT-'.$token, 'name' => 'Accounting Event Coverage', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
        $branch = (int) DB::table('branches')->insertGetId(['company_id' => $company, 'code' => 'HQ-'.$token, 'name' => 'HQ', 'status' => 'active', 'timezone' => 'Asia/Ho_Chi_Minh', 'created_at' => now(), 'updated_at' => now()]);
        foreach ([$maker, $checker] as $actor) {
            DB::table('company_user_access')->insert(['company_id' => $company, 'user_id' => $actor->id, 'created_at' => now(), 'updated_at' => now()]);
        }

        try {
            $events = [
                ['goods_receipt', 'inventory_accrual', 'INVENTORY_RECEIPT', 'inventory', '1200', 'INVENTORY', '2150', 'GRNI'],
                ['customer_collection', 'customer_collection', 'CUSTOMER_COLLECTION', null, '1100', 'CASH', '1150', 'RECEIVABLE'],
                ['sales_fulfillment', 'erp_sales_cogs', 'SALES_COGS', null, '5000', 'EXPENSE', '1200', 'INVENTORY'],
            ];
            foreach ($events as $index => [$source, $postingType, $event, $classification, $debitCode, $debitRole, $creditCode, $creditRole]) {
                $policy = $this->policy($company, $maker, $checker, $token.'-'.$index, $source, $event, $classification, $debitCode, $debitRole, $creditCode, $creditRole);
                $payload = ['company_id' => $company, 'branch_id' => $branch, 'source_type' => $source, 'source_id' => 90_000 + $index, 'posting_type' => $postingType, 'posting_date' => '2026-09-29', 'amount_minor' => 1_000 + $index, 'actor_id' => $checker->id, 'policy_id' => $policy, 'policy_version' => 1, 'debit_account' => $debitCode, 'credit_account' => $creditCode];
                $race = (new MysqlProcessRaceRunner)->run('policy_backed_source_post', $payload, $payload);

                self::assertSame([0, 0], $race['exit_codes'], implode("\n", $race['outputs']));
                self::assertSame(1, DB::table('payroll_accounting_journals')->where(['company_id' => $company, 'source_type' => $source, 'source_id' => 90_000 + $index, 'posting_type' => $postingType])->count());
            }
        } finally {
            $journalIds = DB::table('payroll_accounting_journals')->where('company_id', $company)->pluck('id');
            DB::table('accounting_journal_source_evidence')->whereIn('journal_id', $journalIds)->delete();
            DB::table('payroll_accounting_journal_lines')->whereIn('journal_id', $journalIds)->delete();
            DB::table('payroll_accounting_journals')->whereIn('id', $journalIds)->delete();
            DB::table('accounting_posting_policy_lines')->whereIn('accounting_posting_policy_id', DB::table('accounting_posting_policies')->where('company_id', $company)->select('id'))->delete();
            DB::table('accounting_posting_policies')->where('company_id', $company)->delete();
            DB::table('accounting_periods')->where('company_id', $company)->delete();
            DB::table('accounting_accounts')->where('company_id', $company)->delete();
            DB::table('company_user_access')->where('company_id', $company)->delete();
            DB::table('branches')->where('company_id', $company)->delete();
            DB::table('companies')->where('id', $company)->delete();
            $maker->forceDelete();
            $checker->forceDelete();
        }
    }

    private function policy(int $company, User $maker, User $checker, string $key, string $source, string $event, ?string $classification, string $debitCode, string $debitRole, string $creditCode, string $creditRole): int
    {
        $posting = app(SourceAccountingPostingService::class);
        $debit = $posting->accountEvidence($company, $debitCode, 'VND', $maker->id)['id'];
        $credit = $posting->accountEvidence($company, $creditCode, 'VND', $maker->id)['id'];
        $policies = app(AccountingPostingPolicyService::class);
        $id = $policies->create($company, $maker, ['policy_key' => strtoupper($key), 'source_type' => $source, 'business_event' => $event, 'classification' => $classification, 'version' => 1, 'currency' => 'VND', 'effective_from' => '2026-01-01', 'lines' => [['entry_side' => 'debit', 'line_role' => $debitRole, 'account_id' => $debit], ['entry_side' => 'credit', 'line_role' => $creditRole, 'account_id' => $credit]]]);
        $policies->submit($company, $id, $maker);
        $policies->approve($company, $id, $checker);

        return $id;
    }

    private function assertSafeDatabase(): void
    {
        self::assertSame('testing', app()->environment());
        self::assertSame('mysql', DB::connection()->getDriverName());
        self::assertSame('schema_admin_testing', DB::connection()->getDatabaseName());
    }
}
