<?php

namespace Tests\Concurrency\Finance;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Finance\Application\Services\AccountingPostingPolicyService;
use Modules\Finance\Application\Services\AssetLifecycleService;
use PHPUnit\Framework\Attributes\Group;
use Tests\Support\MysqlProcessRaceRunner;
use Tests\TestCase;

#[Group('mysql-concurrency')]
final class FixedAssetLifecycleProcessConcurrencyTest extends TestCase
{
    public function test_competing_approvals_have_one_winner(): void
    {
        $f = $this->fixture('APPROVE');
        try {
            $disposal = app(AssetLifecycleService::class)->createDisposal($f['company'], $f['maker'], $this->payload($f));
            $base = ['company_id' => $f['company'], 'disposal_id' => $disposal];
            $race = (new MysqlProcessRaceRunner)->run('asset_disposal_approve', $base + ['actor_id' => $f['checker']->id], $base + ['actor_id' => $f['reviewer']->id]);

            self::assertSame([0, 2], $race['exit_codes'], implode("\n", $race['outputs']));
            self::assertSame('approved', DB::table('finance_asset_disposals')->where('id', $disposal)->value('status'));
            self::assertContains((int) DB::table('finance_asset_disposals')->where('id', $disposal)->value('approved_by'), [$f['checker']->id, $f['reviewer']->id]);
        } finally {
            $this->cleanup($f);
        }
    }

    public function test_competing_posts_create_one_journal_and_one_retirement(): void
    {
        $f = $this->fixture('POST');
        try {
            $service = app(AssetLifecycleService::class);
            $disposal = $service->createDisposal($f['company'], $f['maker'], $this->payload($f));
            $service->approveDisposal($f['company'], $disposal, $f['checker']);
            $payload = ['company_id' => $f['company'], 'disposal_id' => $disposal, 'actor_id' => $f['reviewer']->id];
            $race = (new MysqlProcessRaceRunner)->run('asset_disposal_post', $payload, $payload);

            self::assertSame([0, 0], $race['exit_codes'], implode("\n", $race['outputs']));
            self::assertSame(1, DB::table('payroll_accounting_journals')->where(['company_id' => $f['company'], 'source_type' => 'asset_disposal', 'source_id' => $disposal])->count());
            self::assertSame('disposed', DB::table('finance_assets')->where('id', $f['asset'])->value('status'));
            self::assertNotNull(DB::table('finance_asset_assignments')->where('asset_id', $f['asset'])->value('effective_to'));
        } finally {
            $this->cleanup($f);
        }
    }

    public function test_same_idempotency_key_creates_one_canonical_disposal(): void
    {
        $f = $this->fixture('IDEMPOTENCY');
        try {
            $payload = $this->payload($f) + ['company_id' => $f['company'], 'actor_id' => $f['maker']->id];
            $race = (new MysqlProcessRaceRunner)->run('asset_disposal_create', $payload, $payload);

            self::assertSame([0, 0], $race['exit_codes'], implode("\n", $race['outputs']));
            self::assertSame(1, DB::table('finance_asset_disposals')->where(['company_id' => $f['company'], 'idempotency_key' => $payload['idempotency_key']])->count());
        } finally {
            $this->cleanup($f);
        }
    }

    /** @return array<string,mixed> */
    private function fixture(string $label): array
    {
        self::assertSame('testing', app()->environment());
        self::assertSame('schema_admin_testing', DB::connection()->getDatabaseName());
        $token = Str::lower(Str::random(10));
        $maker = User::factory()->create(['email' => "asset-maker-{$token}@example.test"]);
        $checker = User::factory()->create(['email' => "asset-checker-{$token}@example.test"]);
        $reviewer = User::factory()->create(['email' => "asset-reviewer-{$token}@example.test"]);
        $company = (int) DB::table('companies')->insertGetId(['uuid' => (string) Str::uuid(), 'code' => 'FA-'.$token, 'name' => 'Asset Race '.$label, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
        $branch = (int) DB::table('branches')->insertGetId(['company_id' => $company, 'code' => 'HQ', 'name' => 'HQ', 'status' => 'active', 'timezone' => 'Asia/Ho_Chi_Minh', 'created_at' => now(), 'updated_at' => now()]);
        foreach ([$maker, $checker, $reviewer] as $actor) {
            DB::table('company_user_access')->insert(['company_id' => $company, 'user_id' => $actor->id, 'created_at' => now(), 'updated_at' => now()]);
        }
        DB::table('accounting_periods')->insert(['company_id' => $company, 'year' => 2026, 'month' => 9, 'start_date' => '2026-09-01', 'end_date' => '2026-09-30', 'status' => 'open', 'created_at' => now(), 'updated_at' => now()]);
        $category = (int) DB::table('finance_asset_categories')->insertGetId(['company_id' => $company, 'code' => 'EQ', 'name' => 'Equipment', 'created_at' => now(), 'updated_at' => now()]);
        $asset = (int) DB::table('finance_assets')->insertGetId(['company_id' => $company, 'asset_category_id' => $category, 'branch_id' => $branch, 'asset_code' => 'ASSET-'.$token, 'name' => 'Concurrent asset', 'acquisition_cost_minor' => 100_000, 'residual_value_minor' => 0, 'currency' => 'VND', 'acquisition_date' => '2026-01-01', 'in_service_date' => '2026-01-01', 'useful_life_months' => 60, 'depreciation_method' => 'straight_line', 'status' => 'active', 'created_by' => $maker->id, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('finance_asset_assignments')->insert(['company_id' => $company, 'asset_id' => $asset, 'branch_id' => $branch, 'effective_from' => '2026-01-01', 'reason' => 'Initial', 'created_by' => $maker->id, 'created_at' => now(), 'updated_at' => now()]);
        $accumulated = $this->account($company, $maker, '1390', 'Accumulated', 'contra_asset', 'credit');
        $cash = $this->account($company, $maker, '1110', 'Cash', 'asset', 'debit');
        $loss = $this->account($company, $maker, '5320', 'Loss', 'expense', 'debit');
        $fixed = $this->account($company, $maker, '1300', 'Fixed Asset', 'asset', 'debit');
        $gain = $this->account($company, $maker, '7110', 'Gain', 'revenue', 'credit');
        $policies = app(AccountingPostingPolicyService::class);
        $policy = $policies->create($company, $maker, ['policy_key' => 'DISPOSAL-LOSS-'.$token, 'source_type' => 'asset_disposal', 'business_event' => 'ASSET_DISPOSAL', 'classification' => 'LOSS', 'version' => 1, 'currency' => 'VND', 'effective_from' => '2026-01-01', 'lines' => [['entry_side' => 'debit', 'line_role' => 'ACCUMULATED_DEPRECIATION', 'account_id' => $accumulated], ['entry_side' => 'debit', 'line_role' => 'CASH_PROCEEDS', 'account_id' => $cash], ['entry_side' => 'debit', 'line_role' => 'DISPOSAL_LOSS', 'account_id' => $loss], ['entry_side' => 'credit', 'line_role' => 'FIXED_ASSET', 'account_id' => $fixed], ['entry_side' => 'credit', 'line_role' => 'DISPOSAL_GAIN', 'account_id' => $gain]]]);
        $policies->submit($company, $policy, $maker);
        $policies->approve($company, $policy, $checker);

        return compact('token', 'maker', 'checker', 'reviewer', 'company', 'branch', 'category', 'asset', 'policy');
    }

    /** @param array<string,mixed> $fixture @return array<string,int|string> */
    private function payload(array $fixture): array
    {
        return ['asset_id' => $fixture['asset'], 'reference' => 'DISP-'.$fixture['token'], 'disposal_date' => '2026-09-20', 'reason' => 'Concurrent disposal', 'proceeds_minor' => 70_000, 'idempotency_key' => 'disposal-'.$fixture['token']];
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
        DB::table('payroll_accounting_journal_lines')->whereIn('journal_id', $journals)->delete();
        DB::table('finance_asset_disposals')->where('company_id', $f['company'])->delete();
        DB::table('payroll_accounting_journals')->whereIn('id', $journals)->delete();
        DB::table('finance_asset_assignments')->where('asset_id', $f['asset'])->delete();
        DB::table('finance_assets')->where('id', $f['asset'])->delete();
        DB::table('finance_asset_categories')->where('id', $f['category'])->delete();
        DB::table('accounting_posting_policy_lines')->where('accounting_posting_policy_id', $f['policy'])->delete();
        DB::table('accounting_posting_policies')->where('id', $f['policy'])->delete();
        DB::table('accounting_periods')->where('company_id', $f['company'])->delete();
        DB::table('accounting_accounts')->where('company_id', $f['company'])->delete();
        DB::table('company_user_access')->where('company_id', $f['company'])->delete();
        DB::table('branches')->where('id', $f['branch'])->delete();
        DB::table('companies')->where('id', $f['company'])->delete();
        $f['maker']->delete();
        $f['checker']->delete();
        $f['reviewer']->delete();
    }
}
