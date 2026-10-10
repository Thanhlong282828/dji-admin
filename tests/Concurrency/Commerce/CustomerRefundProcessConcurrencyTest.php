<?php

namespace Tests\Concurrency\Commerce;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Finance\Application\Services\AccountingPostingPolicyService;
use PHPUnit\Framework\Attributes\Group;
use Tests\Support\MysqlProcessRaceRunner;
use Tests\TestCase;

#[Group('mysql-concurrency')]
final class CustomerRefundProcessConcurrencyTest extends TestCase
{
    public function test_competing_refund_posts_have_one_economic_winner(): void
    {
        $fixture = $this->fixture('LIMIT');
        try {
            $left = $this->approvedRefund($fixture, 'LEFT', 700);
            $right = $this->approvedRefund($fixture, 'RIGHT', 700);
            $race = (new MysqlProcessRaceRunner)->run('customer_refund_post', $this->postPayload($fixture, $left), $this->postPayload($fixture, $right));

            self::assertSame([0, 2], $race['exit_codes'], implode("\n", $race['outputs']));
            self::assertSame(1, DB::table('customer_refunds')->whereIn('id', [$left, $right])->where('status', 'posted')->count());
            self::assertSame(700, (int) DB::table('customer_refund_allocations as allocation')->join('customer_refunds as refund', 'refund.id', '=', 'allocation.customer_refund_id')->whereIn('refund.id', [$left, $right])->where('refund.status', 'posted')->sum('allocation.allocated_minor'));
            self::assertSame(1, DB::table('payroll_accounting_journals')->where(['company_id' => $fixture['company'], 'source_type' => 'customer_refund'])->count());
            self::assertSame(1, DB::table('treasury_transactions')->where(['company_id' => $fixture['company'], 'source_type' => 'customer_refund'])->count());
        } finally {
            $this->cleanup($fixture);
        }
    }

    public function test_same_idempotency_create_race_returns_one_canonical_refund(): void
    {
        $fixture = $this->fixture('IDEMPOTENCY');
        try {
            $payload = $this->createPayload($fixture, 'SAME', 400, 'same-'.$fixture['token']);
            $race = (new MysqlProcessRaceRunner)->run('customer_refund_create', $payload, $payload);

            self::assertSame([0, 0], $race['exit_codes'], implode("\n", $race['outputs']));
            self::assertSame(1, DB::table('customer_refunds')->where(['company_id' => $fixture['company'], 'idempotency_key' => 'same-'.$fixture['token']])->count());
            $refund = (int) DB::table('customer_refunds')->where('idempotency_key', 'same-'.$fixture['token'])->value('id');
            self::assertSame(1, DB::table('customer_refund_allocations')->where('customer_refund_id', $refund)->count());
            self::assertSame(0, DB::table('payroll_accounting_journals')->where(['source_type' => 'customer_refund', 'source_id' => $refund])->count());
            self::assertSame(0, DB::table('treasury_transactions')->where(['source_type' => 'customer_refund', 'source_id' => $refund])->count());
        } finally {
            $this->cleanup($fixture);
        }
    }

    /** @return array{token:string,maker:User,checker:User,company:int,branch:int,customer:int,invoice:int,note:int,bank:int,policy:int} */
    private function fixture(string $label): array
    {
        $this->assertSafeDatabase();
        $token = Str::lower(Str::random(12));
        $maker = User::factory()->create(['email' => "refund-maker-{$token}@example.test"]);
        $checker = User::factory()->create(['email' => "refund-checker-{$token}@example.test"]);
        $company = (int) DB::table('companies')->insertGetId(['uuid' => (string) Str::uuid(), 'code' => 'CRF-'.$token, 'name' => 'Refund Race '.$label, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
        $branch = (int) DB::table('branches')->insertGetId(['company_id' => $company, 'code' => 'HQ-'.$token, 'name' => 'HQ', 'status' => 'active', 'timezone' => 'Asia/Ho_Chi_Minh', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('company_user_access')->insert([['company_id' => $company, 'user_id' => $maker->id, 'created_at' => now(), 'updated_at' => now()], ['company_id' => $company, 'user_id' => $checker->id, 'created_at' => now(), 'updated_at' => now()]]);
        $customer = (int) DB::table('commerce_customers')->insertGetId(['company_id' => $company, 'full_name' => 'Refund Customer', 'phone' => '09'.substr(hash('sha256', $token), 0, 8), 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
        $invoice = (int) DB::table('customer_invoices')->insertGetId(['company_id' => $company, 'branch_id' => $branch, 'customer_id' => $customer, 'invoice_number' => 'INV-'.$token, 'invoice_date' => '2026-09-01', 'due_date' => '2026-09-30', 'currency' => 'VND', 'subtotal_minor' => 1000, 'discount_minor' => 0, 'tax_minor' => 0, 'total_minor' => 1000, 'paid_minor' => 1000, 'status' => 'paid', 'created_by' => $maker->id, 'issued_by' => $checker->id, 'issued_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
        $note = (int) DB::table('customer_credit_notes')->insertGetId(['company_id' => $company, 'branch_id' => $branch, 'customer_id' => $customer, 'customer_invoice_id' => $invoice, 'credit_note_number' => 'CN-'.$token, 'effective_date' => '2026-09-10', 'currency' => 'VND', 'reason' => 'Concurrency source', 'status' => 'posted', 'net_minor' => 1000, 'tax_minor' => 0, 'total_minor' => 1000, 'ar_reduction_minor' => 0, 'customer_credit_liability_minor' => 1000, 'idempotency_key' => 'credit-'.$token, 'created_by' => $maker->id, 'submitted_by' => $maker->id, 'submitted_at' => now(), 'approved_by' => $checker->id, 'approved_at' => now(), 'posted_by' => $checker->id, 'posted_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
        $liability = $this->ledger($company, $maker, '2399', 'Customer Credit', 'liability', 'credit');
        $cash = $this->ledger($company, $maker, '1111', 'Refund Bank', 'asset', 'debit');
        $bank = (int) DB::table('treasury_financial_accounts')->insertGetId(['company_id' => $company, 'branch_id' => $branch, 'accounting_account_id' => $cash, 'code' => 'BANK-'.$token, 'name' => 'Refund Bank', 'type' => 'bank', 'currency' => 'VND', 'status' => 'active', 'created_by' => $maker->id, 'created_at' => now(), 'updated_at' => now()]);
        $policies = app(AccountingPostingPolicyService::class);
        $policy = $policies->create($company, $maker, ['policy_key' => 'REFUND-'.$token, 'source_type' => 'customer_refund', 'business_event' => 'CUSTOMER_REFUND', 'classification' => null, 'version' => 1, 'currency' => 'VND', 'effective_from' => '2026-01-01', 'lines' => [['entry_side' => 'debit', 'line_role' => 'CUSTOMER_CREDIT_LIABILITY', 'account_id' => $liability], ['entry_side' => 'credit', 'line_role' => 'CASH', 'account_id' => $cash]]]);
        $policies->submit($company, $policy, $maker);
        $policies->approve($company, $policy, $checker);

        return compact('token', 'maker', 'checker', 'company', 'branch', 'customer', 'invoice', 'note', 'bank', 'policy');
    }

    /** @param array{token:string,maker:User,checker:User,company:int,note:int,bank:int} $fixture */
    private function approvedRefund(array $fixture, string $suffix, int $amount): int
    {
        $id = (int) DB::table('customer_refunds')->insertGetId(['company_id' => $fixture['company'], 'branch_id' => DB::table('customer_credit_notes')->where('id', $fixture['note'])->value('branch_id'), 'customer_id' => DB::table('customer_credit_notes')->where('id', $fixture['note'])->value('customer_id'), 'financial_account_id' => $fixture['bank'], 'reference' => 'REF-'.$suffix.'-'.$fixture['token'], 'refund_date' => '2026-09-20', 'currency' => 'VND', 'amount_minor' => $amount, 'status' => 'approved', 'idempotency_key' => 'refund-'.$suffix.'-'.$fixture['token'], 'created_by' => $fixture['maker']->id, 'submitted_by' => $fixture['maker']->id, 'submitted_at' => now(), 'approved_by' => $fixture['checker']->id, 'approved_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
        DB::table('customer_refund_allocations')->insert(['customer_refund_id' => $id, 'customer_credit_note_id' => $fixture['note'], 'allocated_minor' => $amount, 'created_at' => now(), 'updated_at' => now()]);

        return $id;
    }

    /** @param array{company:int,checker:User} $fixture
     * @return array<string,bool|int|string|null>
     */
    private function postPayload(array $fixture, int $refund): array
    {
        return ['company_id' => $fixture['company'], 'customer_refund_id' => $refund, 'actor_id' => $fixture['checker']->id];
    }

    /** @param array{token:string,maker:User,company:int,note:int,bank:int} $fixture
     * @return array<string,bool|int|string|null>
     */
    private function createPayload(array $fixture, string $suffix, int $amount, string $key): array
    {
        return ['company_id' => $fixture['company'], 'actor_id' => $fixture['maker']->id, 'financial_account_id' => $fixture['bank'], 'reference' => 'REF-'.$suffix.'-'.$fixture['token'], 'refund_date' => '2026-09-20', 'amount_minor' => $amount, 'customer_credit_note_id' => $fixture['note'], 'idempotency_key' => $key];
    }

    private function ledger(int $company, User $actor, string $code, string $name, string $type, string $normal): int
    {
        return (int) DB::table('accounting_accounts')->insertGetId(['uuid' => (string) Str::uuid(), 'company_id' => $company, 'code' => $code, 'name' => $name, 'account_type' => $type, 'normal_balance' => $normal, 'currency' => 'VND', 'allow_posting' => true, 'status' => 'active', 'effective_from' => '2026-01-01', 'created_by' => $actor->id, 'created_at' => now(), 'updated_at' => now()]);
    }

    /** @param array{maker:User,checker:User,company:int,branch:int,customer:int,invoice:int,policy:int} $fixture */
    private function cleanup(array $fixture): void
    {
        $journals = DB::table('payroll_accounting_journals')->where('company_id', $fixture['company'])->pluck('id');
        DB::table('customer_refund_allocations')->whereIn('customer_refund_id', DB::table('customer_refunds')->where('company_id', $fixture['company'])->select('id'))->delete();
        DB::table('customer_refunds')->where('company_id', $fixture['company'])->delete();
        DB::table('treasury_transactions')->where('company_id', $fixture['company'])->delete();
        DB::table('accounting_journal_source_evidence')->whereIn('journal_id', $journals)->delete();
        DB::table('payroll_accounting_journal_lines')->whereIn('journal_id', $journals)->delete();
        DB::table('payroll_accounting_journals')->whereIn('id', $journals)->delete();
        DB::table('customer_credit_notes')->where('company_id', $fixture['company'])->delete();
        DB::table('customer_invoices')->where('id', $fixture['invoice'])->delete();
        DB::table('accounting_posting_policy_lines')->where('accounting_posting_policy_id', $fixture['policy'])->delete();
        DB::table('accounting_posting_policies')->where('id', $fixture['policy'])->delete();
        DB::table('treasury_financial_accounts')->where('company_id', $fixture['company'])->delete();
        DB::table('accounting_periods')->where('company_id', $fixture['company'])->delete();
        DB::table('accounting_accounts')->where('company_id', $fixture['company'])->delete();
        DB::table('company_user_access')->where('company_id', $fixture['company'])->delete();
        DB::table('commerce_customers')->where('id', $fixture['customer'])->delete();
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
