<?php

namespace Tests\Concurrency\Finance;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Company\Domain\Models\Company;
use Modules\Finance\Application\Services\AccountingPostingPolicyService;
use Modules\Finance\Application\Services\CustomerInvoiceFxRecognitionService;
use Modules\Finance\Application\Services\FxRateService;
use PHPUnit\Framework\Attributes\Group;
use Tests\Support\MysqlProcessRaceRunner;
use Tests\TestCase;

#[Group('mysql-concurrency')]
final class ArFxCollectionProcessConcurrencyTest extends TestCase
{
    public function test_two_workers_commit_one_foreign_collection_settlement_journal_and_treasury_receipt(): void
    {
        self::assertSame('schema_admin_testing', DB::connection()->getDatabaseName());
        $token = Str::lower(Str::random(10));
        $maker = User::factory()->create(['email' => "ar-fx-collection-maker-{$token}@example.test"]);
        $checker = User::factory()->create(['email' => "ar-fx-collection-checker-{$token}@example.test"]);
        $company = Company::factory()->create(['code' => 'ARFXC-'.$token, 'functional_currency_code' => 'VND']);
        $branch = (int) DB::table('branches')->insertGetId(['company_id' => $company->id, 'code' => 'ARFXC-'.$token, 'name' => 'AR FX Collection Race', 'status' => 'active', 'timezone' => 'Asia/Ho_Chi_Minh', 'created_at' => now(), 'updated_at' => now()]);
        $customer = (int) DB::table('commerce_customers')->insertGetId(['company_id' => $company->id, 'full_name' => 'AR FX Collection Race', 'phone' => '07'.random_int(10000000, 99999999), 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
        $invoice = (int) DB::table('customer_invoices')->insertGetId(['company_id' => $company->id, 'branch_id' => $branch, 'customer_id' => $customer, 'invoice_number' => 'ARFX-COL-'.$token, 'invoice_date' => '2026-10-03', 'due_date' => '2026-11-03', 'currency' => 'USD', 'subtotal_minor' => 10_000, 'discount_minor' => 0, 'tax_minor' => 0, 'total_minor' => 10_000, 'paid_minor' => 0, 'tax_status' => 'configuration_required', 'status' => 'issued', 'created_by' => $maker->id, 'issued_by' => $checker->id, 'issued_at' => now(), 'created_at' => now(), 'updated_at' => now()]);

        try {
            $this->rate($company->id, $maker, $checker, '2026-10-03', '24690.500000000000', 'RACE-RECOGNITION-'.$token);
            app(CustomerInvoiceFxRecognitionService::class)->recognize($company->id, $invoice, $maker->id);
            $this->rate($company->id, $maker, $checker, '2026-10-04', '25000.000000000000', 'RACE-SETTLEMENT-'.$token);
            $cash = $this->account($company->id, $maker->id, '1100', 'Functional Cash', 'asset', 'debit', 'VND');
            $receivable = $this->account($company->id, $maker->id, '1150', 'Receivable', 'asset', 'debit', 'VND');
            $gain = $this->account($company->id, $maker->id, '4200', 'Realized FX Gain', 'revenue', 'credit', 'VND');
            $usdLedger = $this->account($company->id, $maker->id, '1110', 'USD Bank', 'asset', 'debit', 'USD');
            $usdAccount = (int) DB::table('treasury_financial_accounts')->insertGetId(['company_id' => $company->id, 'branch_id' => $branch, 'accounting_account_id' => $usdLedger, 'code' => 'USD-'.$token, 'name' => 'USD Bank', 'type' => 'bank', 'currency' => 'USD', 'status' => 'active', 'created_by' => $maker->id, 'created_at' => now(), 'updated_at' => now()]);
            $this->policy($company->id, $maker, $checker, 'CUSTOMER_COLLECTION_FX_GAIN', [['entry_side' => 'debit', 'line_role' => 'CASH', 'account_id' => $cash], ['entry_side' => 'credit', 'line_role' => 'RECEIVABLE', 'account_id' => $receivable], ['entry_side' => 'credit', 'line_role' => 'FX_GAIN', 'account_id' => $gain]]);
            $payload = ['company_id' => $company->id, 'actor_id' => $checker->id, 'customer_invoice_id' => $invoice, 'financial_account_id' => $usdAccount, 'reference' => 'ARFX-RACE-'.$token, 'collection_date' => '2026-10-04', 'amount_minor' => 10_000, 'method' => 'bank_transfer'];
            $race = (new MysqlProcessRaceRunner)->run('ar_fx_collection', $payload, $payload);
            self::assertSame([0, 0], $race['exit_codes'], implode("\n", $race['outputs']));
            self::assertSame(1, DB::table('customer_collections')->where(['company_id' => $company->id, 'reference' => $payload['reference']])->count());
            self::assertSame(1, DB::table('customer_collection_allocations')->where('customer_invoice_id', $invoice)->count());
            self::assertSame(1, DB::table('customer_collection_allocation_fx_settlements as settlement')->join('customer_collection_allocations as allocation', 'allocation.id', '=', 'settlement.customer_collection_allocation_id')->where('allocation.customer_invoice_id', $invoice)->count());
            self::assertSame(1, DB::table('payroll_accounting_journals')->where(['company_id' => $company->id, 'source_type' => 'customer_collection'])->count());
            self::assertSame(1, DB::table('treasury_transactions')->where(['company_id' => $company->id, 'source_type' => 'customer_collection'])->count());
            self::assertSame(10_000, (int) DB::table('customer_invoices')->where('id', $invoice)->value('paid_minor'));
        } finally {
            $collectionIds = DB::table('customer_collections')->where('company_id', $company->id)->pluck('id');
            $journalIds = DB::table('payroll_accounting_journals')->where('company_id', $company->id)->pluck('id');
            DB::table('customer_invoices')->where('id', $invoice)->update(['accounting_journal_id' => null]);
            DB::table('customer_collections')->whereIn('id', $collectionIds)->update(['accounting_journal_id' => null, 'treasury_transaction_id' => null]);
            DB::table('treasury_transactions')->where('company_id', $company->id)->delete();
            DB::table('accounting_journal_source_evidence')->whereIn('journal_id', $journalIds)->delete();
            DB::table('payroll_accounting_journal_lines')->whereIn('journal_id', $journalIds)->delete();
            DB::table('payroll_accounting_journals')->whereIn('id', $journalIds)->delete();
            DB::table('customer_collection_allocation_fx_settlements')->whereIn('customer_collection_allocation_id', DB::table('customer_collection_allocations')->whereIn('customer_collection_id', $collectionIds)->select('id'))->delete();
            DB::table('customer_collection_allocations')->whereIn('customer_collection_id', $collectionIds)->delete();
            DB::table('customer_collections')->whereIn('id', $collectionIds)->delete();
            DB::table('customer_invoice_fx_recognitions')->where('customer_invoice_id', $invoice)->delete();
            DB::table('finance_fx_rate_snapshots')->where('company_id', $company->id)->delete();
            DB::table('customer_invoices')->where('id', $invoice)->delete();
            DB::table('treasury_financial_accounts')->where('company_id', $company->id)->delete();
            DB::table('accounting_posting_policy_lines')->whereIn('accounting_posting_policy_id', DB::table('accounting_posting_policies')->where('company_id', $company->id)->select('id'))->delete();
            DB::table('accounting_posting_policies')->where('company_id', $company->id)->delete();
            DB::table('accounting_periods')->where('company_id', $company->id)->delete();
            DB::table('accounting_accounts')->where('company_id', $company->id)->delete();
            DB::table('finance_fx_rates')->where('company_id', $company->id)->delete();
            DB::table('commerce_customers')->where('id', $customer)->delete();
            DB::table('branches')->where('id', $branch)->delete();
            $company->delete();
            $maker->forceDelete();
            $checker->forceDelete();
        }
    }

    private function rate(int $company, User $maker, User $checker, string $date, string $rate, string $reference): void
    {
        $service = app(FxRateService::class);
        $id = $service->create($company, $maker, ['base_currency' => 'USD', 'quote_currency' => 'VND', 'rate_decimal' => $rate, 'rate_date' => $date, 'rate_type' => 'spot', 'source_reference' => $reference, 'effective_from' => $date, 'effective_to' => $date, 'idempotency_key' => (string) Str::uuid()]);
        $service->transition($company, $id, 'submit', $maker);
        $service->transition($company, $id, 'approve', $checker);
        $service->transition($company, $id, 'activate', $checker);
    }

    private function account(int $company, int $actor, string $code, string $name, string $type, string $normal, string $currency): int
    {
        return (int) DB::table('accounting_accounts')->insertGetId(['uuid' => (string) Str::uuid(), 'company_id' => $company, 'code' => $code, 'name' => $name, 'account_type' => $type, 'normal_balance' => $normal, 'currency' => $currency, 'allow_posting' => true, 'status' => 'active', 'effective_from' => '2026-01-01', 'created_by' => $actor, 'created_at' => now(), 'updated_at' => now()]);
    }

    /** @param list<array{entry_side:string,line_role:string,account_id:int}> $lines */
    private function policy(int $company, User $maker, User $checker, string $event, array $lines): void
    {
        $service = app(AccountingPostingPolicyService::class);
        $id = $service->create($company, $maker, ['policy_key' => $event, 'source_type' => 'customer_collection', 'business_event' => $event, 'classification' => null, 'version' => 1, 'currency' => 'VND', 'effective_from' => '2026-01-01', 'lines' => $lines]);
        $service->submit($company, $id, $maker);
        $service->approve($company, $id, $checker);
    }
}
