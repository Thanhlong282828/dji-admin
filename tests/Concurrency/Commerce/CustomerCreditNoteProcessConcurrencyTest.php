<?php

namespace Tests\Concurrency\Commerce;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Commerce\Application\Services\CustomerCreditNoteService;
use Modules\Finance\Application\Services\AccountingPostingPolicyService;
use Modules\Finance\Application\Services\SourceAccountingPostingService;
use PHPUnit\Framework\Attributes\Group;
use Tests\Support\MysqlProcessRaceRunner;
use Tests\TestCase;

#[Group('mysql-concurrency')]
final class CustomerCreditNoteProcessConcurrencyTest extends TestCase
{
    private const REPETITIONS = 10;

    public function test_remaining_creditable_race_has_one_economic_winner_in_ten_repetitions(): void
    {
        $this->assertSafeDatabase();
        for ($run = 1; $run <= self::REPETITIONS; $run++) {
            $fixture = $this->fixture('LIMIT-'.$run);
            try {
                $this->postCredit($fixture, 80, 80_000, 'PRIOR-'.$run);
                $left = $this->approvedCredit($fixture, 20, 20_000, 'LEFT-'.$run);
                $right = $this->approvedCredit($fixture, 20, 20_000, 'RIGHT-'.$run);
                $race = (new MysqlProcessRaceRunner)->run('customer_credit_post', $this->postPayload($fixture, $left), $this->postPayload($fixture, $right));
                self::assertSame([0, 2], $race['exit_codes'], implode("\n", $race['outputs']));
                $this->assertNoRawSqlLeakage($race['outputs']);
                self::assertSame(100_000, (int) DB::table('customer_credit_notes')->where(['customer_invoice_id' => $fixture['invoice'], 'status' => 'posted'])->sum('total_minor'));
                self::assertSame(100_000, (int) DB::table('customer_credit_note_lines as line')->join('customer_credit_notes as note', 'note.id', '=', 'line.customer_credit_note_id')->where(['line.customer_invoice_line_id' => $fixture['line'], 'note.status' => 'posted'])->sum('line.total_minor'));
                self::assertSame(1, DB::table('customer_credit_notes')->whereIn('id', [$left, $right])->where('status', 'posted')->count());
                self::assertSame(2, DB::table('payroll_accounting_journals')->where(['company_id' => $fixture['company'], 'source_type' => 'customer_credit_note'])->count());
            } finally {
                $this->cleanup($fixture);
            }
        }
    }

    public function test_same_idempotency_create_race_returns_one_canonical_document_and_line(): void
    {
        $fixture = $this->fixture('IDEMPOTENCY');
        try {
            $payload = $this->createPayload($fixture, 20, 20_000, 'SAME', 'same-'.$fixture['token']);
            $race = (new MysqlProcessRaceRunner)->run('customer_credit_create', $payload, $payload);
            self::assertSame([0, 0], $race['exit_codes'], implode("\n", $race['outputs']));
            $this->assertNoRawSqlLeakage($race['outputs']);
            self::assertSame(1, DB::table('customer_credit_notes')->where(['company_id' => $fixture['company'], 'idempotency_key' => 'same-'.$fixture['token']])->count());
            $note = $this->persistedInt(DB::table('customer_credit_notes')->where('idempotency_key', 'same-'.$fixture['token'])->value('id'));
            self::assertSame(1, DB::table('customer_credit_note_lines')->where('customer_credit_note_id', $note)->count());
            self::assertSame(0, DB::table('payroll_accounting_journals')->where(['source_type' => 'customer_credit_note', 'source_id' => $note])->count());
        } finally {
            $this->cleanup($fixture);
        }
    }

    public function test_same_document_post_race_creates_one_journal_and_one_effect(): void
    {
        $fixture = $this->fixture('POST');
        try {
            $note = $this->approvedCredit($fixture, 20, 20_000, 'POST');
            $race = (new MysqlProcessRaceRunner)->run('customer_credit_post', $this->postPayload($fixture, $note), $this->postPayload($fixture, $note));
            self::assertSame([0, 0], $race['exit_codes'], implode("\n", $race['outputs']));
            $this->assertNoRawSqlLeakage($race['outputs']);
            self::assertSame(1, DB::table('payroll_accounting_journals')->where(['source_type' => 'customer_credit_note', 'source_id' => $note])->count());
            self::assertSame(20_000, $this->persistedInt(DB::table('customer_credit_notes')->where('id', $note)->value('ar_reduction_minor')));
            self::assertSame(0, $this->persistedInt(DB::table('customer_credit_notes')->where('id', $note)->value('customer_credit_liability_minor')));
        } finally {
            $this->cleanup($fixture);
        }
    }

    public function test_same_vat_credit_note_post_race_creates_one_reversal_evidence_and_one_journal(): void
    {
        $fixture = $this->fixture('VAT-POST', 0, true);
        try {
            $note = $this->approvedCredit($fixture, 10, 10_000, 'VAT-POST', 1_000);
            $race = (new MysqlProcessRaceRunner)->run('customer_credit_post', $this->postPayload($fixture, $note), $this->postPayload($fixture, $note));
            self::assertSame([0, 0], $race['exit_codes'], implode("\n", $race['outputs']));
            $this->assertNoRawSqlLeakage($race['outputs']);
            self::assertSame(1, DB::table('payroll_accounting_journals')->where(['source_type' => 'customer_credit_note', 'source_id' => $note])->count());
            self::assertSame(1, DB::table('tax_transaction_evidence')->where(['source_type' => 'customer_credit_note', 'source_id' => $note])->count());
            self::assertSame(1_000, $this->persistedInt(DB::table('tax_transaction_evidence')->where(['source_type' => 'customer_credit_note', 'source_id' => $note])->value('tax_amount_minor')));
        } finally {
            $this->cleanup($fixture);
        }
    }

    public function test_paid_invoice_post_race_splits_ar_and_customer_credit_once_without_treasury_mutation(): void
    {
        $fixture = $this->fixture('PAID', 90_000);
        try {
            $note = $this->approvedCredit($fixture, 20, 20_000, 'PAID');
            $invoiceBefore = DB::table('customer_invoices')->where('id', $fixture['invoice'])->firstOrFail();
            $lineBefore = DB::table('customer_invoice_lines')->where('id', $fixture['line'])->firstOrFail();
            $collectionBefore = DB::table('customer_collections')->where('id', $fixture['collection'])->firstOrFail();
            $allocationBefore = DB::table('customer_collection_allocations')->where('customer_collection_id', $fixture['collection'])->firstOrFail();
            $journalBefore = DB::table('payroll_accounting_journals')->where('id', $fixture['invoice_journal'])->firstOrFail();
            $treasuryBefore = DB::table('treasury_transactions')->count();
            $race = (new MysqlProcessRaceRunner)->run('customer_credit_post', $this->postPayload($fixture, $note), $this->postPayload($fixture, $note));
            self::assertSame([0, 0], $race['exit_codes'], implode("\n", $race['outputs']));
            $this->assertNoRawSqlLeakage($race['outputs']);
            $stored = DB::table('customer_credit_notes')->where('id', $note)->firstOrFail();
            self::assertSame(10_000, $this->persistedInt($stored->ar_reduction_minor ?? null));
            self::assertSame(10_000, $this->persistedInt($stored->customer_credit_liability_minor ?? null));
            self::assertSame(1, DB::table('payroll_accounting_journals')->where(['source_type' => 'customer_credit_note', 'source_id' => $note])->count());
            self::assertSame($treasuryBefore, DB::table('treasury_transactions')->count());
            self::assertEquals($invoiceBefore, DB::table('customer_invoices')->where('id', $fixture['invoice'])->firstOrFail());
            self::assertEquals($lineBefore, DB::table('customer_invoice_lines')->where('id', $fixture['line'])->firstOrFail());
            self::assertEquals($collectionBefore, DB::table('customer_collections')->where('id', $fixture['collection'])->firstOrFail());
            self::assertEquals($allocationBefore, DB::table('customer_collection_allocations')->where('customer_collection_id', $fixture['collection'])->firstOrFail());
            self::assertEquals($journalBefore, DB::table('payroll_accounting_journals')->where('id', $fixture['invoice_journal'])->firstOrFail());
        } finally {
            $this->cleanup($fixture);
        }
    }

    /** @return array{token:string,maker:User,checker:User,company:int,branch:int,customer:int,invoice:int,line:int,policy:int,invoice_journal:int,collection:?int} */
    private function fixture(string $label, int $collected = 0, bool $withVat = false): array
    {
        $token = Str::lower(Str::random(12));
        $maker = User::factory()->create(['email' => "credit-maker-{$token}@example.test"]);
        $checker = User::factory()->create(['email' => "credit-checker-{$token}@example.test"]);
        $company = (int) DB::table('companies')->insertGetId(['uuid' => (string) Str::uuid(), 'code' => 'CCN-'.$token, 'name' => 'Credit Race '.$label, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
        $branch = (int) DB::table('branches')->insertGetId(['company_id' => $company, 'code' => 'HQ-'.$token, 'name' => 'HQ', 'status' => 'active', 'timezone' => 'Asia/Ho_Chi_Minh', 'created_at' => now(), 'updated_at' => now()]);
        $customer = (int) DB::table('commerce_customers')->insertGetId(['company_id' => $company, 'full_name' => 'Credit Customer', 'phone' => '09'.substr(hash('sha256', $token), 0, 8), 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('company_user_access')->insert([['company_id' => $company, 'user_id' => $maker->id, 'created_at' => now(), 'updated_at' => now()], ['company_id' => $company, 'user_id' => $checker->id, 'created_at' => now(), 'updated_at' => now()]]);
        $revenue = $this->ledger($company, $maker, '4099', 'Credit Revenue', 'revenue', 'credit');
        $tax = $this->ledger($company, $maker, '2299', 'Credit Tax', 'liability', 'credit');
        $receivable = $this->ledger($company, $maker, '1199', 'Credit Receivable', 'asset', 'debit');
        $liability = $this->ledger($company, $maker, '2399', 'Customer Credit', 'liability', 'credit');
        $policies = app(AccountingPostingPolicyService::class);
        $policy = $policies->create($company, $maker, ['policy_key' => 'CCN-'.$token, 'source_type' => 'customer_credit_note', 'business_event' => 'CUSTOMER_INVOICE_CREDIT_NOTE', 'classification' => null, 'version' => 1, 'currency' => 'VND', 'effective_from' => '2026-01-01', 'lines' => [['entry_side' => 'debit', 'line_role' => 'REVENUE', 'account_id' => $revenue], ['entry_side' => 'debit', 'line_role' => 'TAX_PAYABLE', 'account_id' => $tax], ['entry_side' => 'credit', 'line_role' => 'RECEIVABLE', 'account_id' => $receivable], ['entry_side' => 'credit', 'line_role' => 'CUSTOMER_CREDIT_LIABILITY', 'account_id' => $liability]]]);
        $policies->submit($company, $policy, $maker);
        $policies->approve($company, $policy, $checker);
        $invoiceTax = $withVat ? 10_000 : 0;
        $invoice = (int) DB::table('customer_invoices')->insertGetId(['company_id' => $company, 'branch_id' => $branch, 'customer_id' => $customer, 'invoice_number' => 'INV-'.$token, 'invoice_date' => '2026-09-01', 'due_date' => '2026-09-30', 'currency' => 'VND', 'subtotal_minor' => 100_000, 'discount_minor' => 0, 'tax_minor' => $invoiceTax, 'total_minor' => 100_000 + $invoiceTax, 'paid_minor' => $collected, 'status' => $collected > 0 ? 'partially_paid' : 'issued', 'created_by' => $maker->id, 'issued_by' => $checker->id, 'issued_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
        $line = (int) DB::table('customer_invoice_lines')->insertGetId(['customer_invoice_id' => $invoice, 'description' => 'Race invoice line', 'quantity' => 100, 'unit_price_minor' => 1000, 'discount_minor' => 0, 'tax_minor' => $invoiceTax, 'line_total_minor' => 100_000 + $invoiceTax, 'cogs_minor' => 0, 'created_at' => now(), 'updated_at' => now()]);
        $invoiceLines = [['account_code' => '1199', 'debit' => 100_000 + $invoiceTax, 'credit' => 0, 'description' => 'Invoice AR'], ['account_code' => '4099', 'debit' => 0, 'credit' => 100_000, 'description' => 'Invoice revenue']];
        if ($withVat) {
            $invoiceLines[] = ['account_code' => '2299', 'debit' => 0, 'credit' => $invoiceTax, 'description' => 'Invoice VAT'];
        }
        $invoiceJournal = app(SourceAccountingPostingService::class)->post($company, $branch, 'customer_invoice', $invoice, 'customer_invoice', '2026-09-01', 'VND', $invoiceLines, $checker->id);
        DB::table('customer_invoices')->where('id', $invoice)->update(['accounting_journal_id' => $invoiceJournal]);
        if ($withVat) {
            $category = (int) DB::table('tax_categories')->insertGetId(['company_id' => $company, 'code' => 'VAT_OUTPUT', 'name' => 'VAT Output', 'direction' => 'output', 'country_code' => 'VN', 'status' => 'active', 'created_by' => $maker->id, 'created_at' => now(), 'updated_at' => now()]);
            $vatPolicy = (int) DB::table('vat_policies')->insertGetId(['company_id' => $company, 'tax_category_id' => $category, 'policy_key' => 'VAT-'.$token, 'version' => 1, 'country_code' => 'VN', 'currency' => 'VND', 'rate_basis_points' => 1_000, 'effective_from' => '2026-01-01', 'tax_account_id' => $tax, 'accounting_posting_policy_id' => $policy, 'status' => 'approved', 'created_by' => $maker->id, 'approved_by' => $checker->id, 'approved_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
            DB::table('tax_transaction_evidence')->insert(['company_id' => $company, 'branch_id' => $branch, 'source_type' => 'customer_invoice', 'source_id' => $invoice, 'tax_category_id' => $category, 'tax_policy_id' => $vatPolicy, 'policy_version' => 1, 'rate_basis_points' => 1_000, 'taxable_amount_minor' => 100_000, 'tax_amount_minor' => $invoiceTax, 'currency' => 'VND', 'posting_date' => '2026-09-01', 'accounting_journal_id' => $invoiceJournal, 'mapping_snapshot' => json_encode(['tax_account_id' => $tax, 'tax_account_code' => '2299', 'accounting_policy_id' => $policy, 'accounting_policy_version' => 1], JSON_THROW_ON_ERROR), 'created_at' => now(), 'updated_at' => now()]);
        }
        $collection = null;
        if ($collected > 0) {
            $collection = (int) DB::table('customer_collections')->insertGetId(['company_id' => $company, 'branch_id' => $branch, 'customer_id' => $customer, 'reference' => 'COL-'.$token, 'collection_date' => '2026-09-10', 'amount_minor' => $collected, 'allocated_minor' => $collected, 'currency' => 'VND', 'method' => 'bank_transfer', 'status' => 'recorded', 'created_by' => $checker->id, 'created_at' => now(), 'updated_at' => now()]);
            DB::table('customer_collection_allocations')->insert(['customer_collection_id' => $collection, 'customer_invoice_id' => $invoice, 'allocated_minor' => $collected, 'created_at' => now(), 'updated_at' => now()]);
        }

        return ['token' => $token, 'maker' => $maker, 'checker' => $checker, 'company' => $company, 'branch' => $branch, 'customer' => $customer, 'invoice' => $invoice, 'line' => $line, 'policy' => $policy, 'invoice_journal' => $invoiceJournal, 'collection' => $collection];
    }

    private function ledger(int $company, User $creator, string $code, string $name, string $type, string $normal): int
    {
        return (int) DB::table('accounting_accounts')->insertGetId(['uuid' => (string) Str::uuid(), 'company_id' => $company, 'code' => $code, 'name' => $name, 'account_type' => $type, 'normal_balance' => $normal, 'currency' => 'VND', 'allow_posting' => true, 'status' => 'active', 'effective_from' => '2020-01-01', 'created_by' => $creator->id, 'created_at' => now(), 'updated_at' => now()]);
    }

    /** @param array{company:int,maker:User,checker:User,invoice:int,line:int,token:string} $fixture */
    private function approvedCredit(array $fixture, int $quantity, int $net, string $suffix, int $tax = 0): int
    {
        $service = app(CustomerCreditNoteService::class);
        $id = $service->create($fixture['company'], $fixture['maker'], [
            'customer_invoice_id' => $fixture['invoice'],
            'credit_note_number' => 'CN-'.$suffix.'-'.$fixture['token'],
            'effective_date' => '2026-09-20',
            'reason' => 'Concurrency verification',
            'idempotency_key' => 'key-'.$suffix.'-'.$fixture['token'],
            'lines' => [[
                'customer_invoice_line_id' => $fixture['line'],
                'quantity' => $quantity,
                'net_minor' => $net,
                'tax_minor' => $tax,
                'total_minor' => $net + $tax,
                'description' => 'Concurrent customer credit',
            ]],
        ]);
        $service->submit($fixture['company'], $id, $fixture['maker']);
        $service->approve($fixture['company'], $id, $fixture['checker']);

        return $id;
    }

    /** @param array{company:int,maker:User,checker:User,invoice:int,line:int,token:string} $fixture */
    private function postCredit(array $fixture, int $quantity, int $total, string $suffix): int
    {
        $id = $this->approvedCredit($fixture, $quantity, $total, $suffix);
        app(CustomerCreditNoteService::class)->post($fixture['company'], $id, $fixture['checker']);

        return $id;
    }

    /** @param array{company:int,maker:User,invoice:int,line:int,token:string} $fixture
     * @return array<string,bool|int|string|null>
     */
    private function createPayload(array $fixture, int $quantity, int $total, string $suffix, string $key): array
    {
        return ['company_id' => $fixture['company'], 'actor_id' => $fixture['maker']->id, 'customer_invoice_id' => $fixture['invoice'], 'customer_invoice_line_id' => $fixture['line'], 'credit_note_number' => 'CN-'.$suffix.'-'.$fixture['token'], 'effective_date' => '2026-09-20', 'quantity' => $quantity, 'total_minor' => $total, 'idempotency_key' => $key];
    }

    /** @param array{company:int,checker:User} $fixture
     * @return array<string,bool|int|string|null>
     */
    private function postPayload(array $fixture, int $note): array
    {
        return ['company_id' => $fixture['company'], 'credit_note_id' => $note, 'actor_id' => $fixture['checker']->id];
    }

    /** @param list<string> $outputs */
    private function assertNoRawSqlLeakage(array $outputs): void
    {
        foreach ($outputs as $output) {
            self::assertStringNotContainsString('SQLSTATE', $output);
            self::assertStringNotContainsString('QueryException', $output);
            self::assertStringNotContainsString('Duplicate entry', $output);
        }
    }

    /** @param array{company:int,branch:int,customer:int,invoice:int,policy:int,maker:User,checker:User} $fixture */
    private function cleanup(array $fixture): void
    {
        $journals = DB::table('payroll_accounting_journals')->where('company_id', $fixture['company'])->pluck('id');
        DB::table('tax_transaction_evidence')->where('company_id', $fixture['company'])->delete();
        DB::table('customer_collection_allocations')->whereIn('customer_invoice_id', DB::table('customer_invoices')->where('company_id', $fixture['company'])->select('id'))->delete();
        DB::table('customer_collections')->where('company_id', $fixture['company'])->delete();
        DB::table('customer_credit_note_lines')->whereIn('customer_credit_note_id', DB::table('customer_credit_notes')->where('company_id', $fixture['company'])->select('id'))->delete();
        DB::table('customer_credit_notes')->where('company_id', $fixture['company'])->delete();
        DB::table('customer_invoice_lines')->whereIn('customer_invoice_id', DB::table('customer_invoices')->where('company_id', $fixture['company'])->select('id'))->delete();
        DB::table('customer_invoices')->where('company_id', $fixture['company'])->delete();
        DB::table('accounting_journal_source_evidence')->whereIn('journal_id', $journals)->delete();
        DB::table('payroll_accounting_journal_lines')->whereIn('journal_id', $journals)->delete();
        DB::table('payroll_accounting_journals')->whereIn('id', $journals)->delete();
        DB::table('accounting_periods')->where('company_id', $fixture['company'])->delete();
        DB::table('accounting_posting_policy_lines')->where('accounting_posting_policy_id', $fixture['policy'])->delete();
        DB::table('vat_policies')->where('company_id', $fixture['company'])->delete();
        DB::table('tax_categories')->where('company_id', $fixture['company'])->delete();
        DB::table('accounting_posting_policies')->where('id', $fixture['policy'])->delete();
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

    private function persistedInt(mixed $value): int
    {
        return is_int($value) ? $value : (is_string($value) && ctype_digit($value) ? (int) $value : throw new \RuntimeException('Invalid persisted integer.'));
    }
}
