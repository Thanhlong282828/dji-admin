<?php

namespace Tests\Feature\AccountingPosting;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Modules\Finance\Application\Services\AccountingPostingPolicyService;
use Modules\Finance\Application\Services\SourceAccountingPostingService;
use Tests\TestCase;

final class AccountingPostingGovernanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_policy_backed_posting_persists_policy_source_period_and_balanced_lines(): void
    {
        $f = $this->fixture('open');
        $policyService = app(AccountingPostingPolicyService::class);
        $policy = $policyService->create($f['company'], $f['maker'], ['policy_key' => 'ASSET-TEST', 'source_type' => 'asset_impairment', 'business_event' => 'ASSET_IMPAIRMENT', 'classification' => 'DAMAGE', 'version' => 1, 'currency' => 'VND', 'effective_from' => '2026-01-01', 'lines' => [['entry_side' => 'debit', 'line_role' => 'IMPAIRMENT_LOSS', 'account_id' => $f['expense']], ['entry_side' => 'credit', 'line_role' => 'ASSET_VALUE', 'account_id' => $f['asset']]]]);
        $policyService->submit($f['company'], $policy, $f['maker']);
        $policyService->approve($f['company'], $policy, $f['checker']);

        $journal = app(SourceAccountingPostingService::class)->postAssetLifecycle($f['company'], $f['branch'], 'asset_impairment', 91, '2026-06-15', 'VND', [['account_code' => '5100', 'debit' => 500, 'credit' => 0, 'description' => 'Loss'], ['account_code' => '1300', 'debit' => 0, 'credit' => 500, 'description' => 'Asset']], $f['checker']->id, $policy, 1);
        self::assertDatabaseHas('payroll_accounting_journals', ['id' => $journal, 'company_id' => $f['company'], 'branch_id' => $f['branch'], 'accounting_period_id' => $f['period'], 'source_type' => 'asset_impairment', 'source_id' => 91, 'total_debit_minor' => 500, 'total_credit_minor' => 500]);
        self::assertSame(2, DB::table('payroll_accounting_journal_lines')->where('journal_id', $journal)->whereRaw("JSON_UNQUOTE(JSON_EXTRACT(mapping_snapshot, '$.policy_id')) = ?", [(string) $policy])->whereRaw("JSON_UNQUOTE(JSON_EXTRACT(mapping_snapshot, '$.policy_version')) = '1'")->count());
        self::assertSame(2, DB::table('accounting_journal_source_evidence')->where('journal_id', $journal)->count());
    }

    public function test_closed_fiscal_year_and_cross_tenant_branch_are_rejected(): void
    {
        $f = $this->fixture('closed');
        $posting = app(SourceAccountingPostingService::class);
        try {
            $posting->post($f['company'], null, 'governance_test', 1, 'test', '2026-06-15', 'VND', $this->lines(), $f['maker']->id);
            self::fail('Closed fiscal year must reject posting.');
        } catch (ValidationException $exception) {
            self::assertSame('FISCAL_YEAR_CLOSED', $exception->errors()['fiscal_year'][0] ?? null);
        }

        $other = $this->fixture('open');
        $this->expectException(ValidationException::class);
        $posting->post($f['company'], $other['branch'], 'governance_test', 2, 'test', '2026-06-15', 'VND', $this->lines(), $f['maker']->id);
    }

    /** @return array<string,mixed> */
    private function fixture(string $status): array
    {
        $maker = User::factory()->create();
        $checker = User::factory()->create();
        $company = (int) DB::table('companies')->insertGetId(['uuid' => (string) Str::uuid(), 'code' => 'POST-'.Str::upper(Str::random(7)), 'name' => 'Posting Governance', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
        $branch = (int) DB::table('branches')->insertGetId(['company_id' => $company, 'code' => 'HQ', 'name' => 'HQ', 'status' => 'active', 'timezone' => 'Asia/Ho_Chi_Minh', 'created_at' => now(), 'updated_at' => now()]);
        $year = (int) DB::table('accounting_fiscal_years')->insertGetId(['company_id' => $company, 'name' => 'FY 2026', 'start_date' => '2026-01-01', 'end_date' => '2026-12-31', 'status' => $status, 'created_by' => $maker->id, 'version' => 1, 'created_at' => now(), 'updated_at' => now()]);
        $period = (int) DB::table('accounting_periods')->insertGetId(['company_id' => $company, 'fiscal_year_id' => $year, 'year' => 2026, 'month' => 6, 'start_date' => '2026-06-01', 'end_date' => '2026-06-30', 'status' => 'open', 'created_at' => now(), 'updated_at' => now()]);
        $expense = $this->account($company, $maker, '5100', 'Impairment Loss', 'expense', 'debit');
        $asset = $this->account($company, $maker, '1300', 'Asset', 'asset', 'debit');

        return compact('maker', 'checker', 'company', 'branch', 'year', 'period', 'expense', 'asset');
    }

    /** @return list<array{account_code:string,debit:int,credit:int,description:string}> */
    private function lines(): array
    {
        return [['account_code' => '5100', 'debit' => 100, 'credit' => 0, 'description' => 'Debit'], ['account_code' => '1300', 'debit' => 0, 'credit' => 100, 'description' => 'Credit']];
    }

    private function account(int $company, User $actor, string $code, string $name, string $type, string $normal): int
    {
        return (int) DB::table('accounting_accounts')->insertGetId(['uuid' => (string) Str::uuid(), 'company_id' => $company, 'code' => $code, 'name' => $name, 'account_type' => $type, 'normal_balance' => $normal, 'currency' => 'VND', 'allow_posting' => true, 'status' => 'active', 'effective_from' => '2026-01-01', 'created_by' => $actor->id, 'created_at' => now(), 'updated_at' => now()]);
    }
}
