<?php

namespace Tests\Concurrency\Tax;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Finance\Application\Services\AccountingPostingPolicyService;
use PHPUnit\Framework\Attributes\Group;
use Tests\Support\MysqlProcessRaceRunner;
use Tests\TestCase;

#[Group('mysql-concurrency')]
final class TaxVatPostingProcessConcurrencyTest extends TestCase
{
    public function test_two_workers_posting_same_invoice_vat_create_one_journal_and_one_tax_evidence(): void
    {
        $this->assertSafeDatabase();
        $token = Str::lower(Str::random(10));
        $maker = User::factory()->create(['email' => "vat-maker-{$token}@example.test"]);
        $checker = User::factory()->create(['email' => "vat-checker-{$token}@example.test"]);
        $company = (int) DB::table('companies')->insertGetId(['uuid' => (string) Str::uuid(), 'code' => 'VAT-'.$token, 'name' => 'VAT concurrency', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
        $branch = (int) DB::table('branches')->insertGetId(['company_id' => $company, 'code' => 'HQ-'.$token, 'name' => 'HQ', 'status' => 'active', 'timezone' => 'Asia/Ho_Chi_Minh', 'created_at' => now(), 'updated_at' => now()]);
        foreach ([$maker, $checker] as $actor) {
            DB::table('company_user_access')->insert(['company_id' => $company, 'user_id' => $actor->id, 'created_at' => now(), 'updated_at' => now()]);
        }

        try {
            $receivable = $this->account($company, $maker->id, '1150', 'Accounts Receivable', 'asset', 'debit');
            $revenue = $this->account($company, $maker->id, '4000', 'Sales Revenue', 'revenue', 'credit');
            $taxAccount = $this->account($company, $maker->id, '2180', 'VAT Payable', 'liability', 'credit');
            $policies = app(AccountingPostingPolicyService::class);
            $accountingPolicy = $policies->create($company, $maker, [
                'policy_key' => 'VAT-OUTPUT-'.$token,
                'source_type' => 'customer_invoice',
                'business_event' => 'CUSTOMER_INVOICE',
                'classification' => 'VAT_OUTPUT',
                'version' => 1,
                'currency' => 'VND',
                'effective_from' => '2026-01-01',
                'lines' => [
                    ['entry_side' => 'debit', 'line_role' => 'RECEIVABLE', 'account_id' => $receivable],
                    ['entry_side' => 'credit', 'line_role' => 'REVENUE', 'account_id' => $revenue],
                    ['entry_side' => 'credit', 'line_role' => 'TAX_PAYABLE', 'account_id' => $taxAccount],
                ],
            ]);
            $policies->submit($company, $accountingPolicy, $maker);
            $policies->approve($company, $accountingPolicy, $checker);
            $category = (int) DB::table('tax_categories')->insertGetId(['company_id' => $company, 'code' => 'VAT_OUTPUT', 'name' => 'Output VAT', 'direction' => 'output', 'country_code' => 'VN', 'status' => 'active', 'created_by' => $maker->id, 'created_at' => now(), 'updated_at' => now()]);
            $taxPolicy = (int) DB::table('vat_policies')->insertGetId(['company_id' => $company, 'tax_category_id' => $category, 'policy_key' => 'VAT-OUTPUT-'.$token, 'version' => 1, 'country_code' => 'VN', 'currency' => 'VND', 'rate_basis_points' => 1000, 'effective_from' => '2026-01-01', 'effective_to' => null, 'tax_account_id' => $taxAccount, 'accounting_posting_policy_id' => $accountingPolicy, 'status' => 'approved', 'created_by' => $maker->id, 'submitted_by' => $maker->id, 'submitted_at' => now(), 'approved_by' => $checker->id, 'approved_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
            $payload = ['company_id' => $company, 'branch_id' => $branch, 'source_type' => 'customer_invoice', 'source_id' => 91_001, 'posting_type' => 'customer_invoice', 'posting_date' => '2026-09-30', 'taxable_amount_minor' => 10_000_000, 'tax_amount_minor' => 1_000_000, 'actor_id' => $checker->id, 'accounting_policy_id' => $accountingPolicy, 'accounting_policy_version' => 1, 'receivable_account' => '1150', 'revenue_account' => '4000', 'tax_account' => '2180', 'tax_account_id' => $taxAccount, 'tax_category_id' => $category, 'tax_policy_id' => $taxPolicy, 'tax_policy_version' => 1, 'rate_basis_points' => 1000];

            $race = (new MysqlProcessRaceRunner)->run('tax_policy_backed_source_post', $payload, $payload);

            self::assertSame([0, 0], $race['exit_codes'], implode("\n", $race['outputs']));
            self::assertSame(1, DB::table('payroll_accounting_journals')->where(['company_id' => $company, 'source_type' => 'customer_invoice', 'source_id' => 91_001, 'posting_type' => 'customer_invoice'])->count());
            self::assertSame(1, DB::table('tax_transaction_evidence')->where(['company_id' => $company, 'source_type' => 'customer_invoice', 'source_id' => 91_001])->count());
        } finally {
            $journalIds = DB::table('payroll_accounting_journals')->where('company_id', $company)->pluck('id');
            DB::table('tax_transaction_evidence')->where('company_id', $company)->delete();
            DB::table('accounting_journal_source_evidence')->whereIn('journal_id', $journalIds)->delete();
            DB::table('payroll_accounting_journal_lines')->whereIn('journal_id', $journalIds)->delete();
            DB::table('payroll_accounting_journals')->whereIn('id', $journalIds)->delete();
            DB::table('vat_policies')->where('company_id', $company)->delete();
            DB::table('tax_categories')->where('company_id', $company)->delete();
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

    private function account(int $company, int $actor, string $code, string $name, string $type, string $normal): int
    {
        return (int) DB::table('accounting_accounts')->insertGetId(['uuid' => (string) Str::uuid(), 'company_id' => $company, 'parent_id' => null, 'code' => $code, 'name' => $name, 'account_type' => $type, 'normal_balance' => $normal, 'currency' => 'VND', 'allow_posting' => true, 'status' => 'active', 'effective_from' => '2000-01-01', 'effective_to' => null, 'created_by' => $actor, 'created_at' => now(), 'updated_at' => now()]);
    }

    private function assertSafeDatabase(): void
    {
        self::assertSame('testing', app()->environment());
        self::assertSame('mysql', DB::connection()->getDriverName());
        self::assertSame('schema_admin_testing', DB::connection()->getDatabaseName());
    }
}
