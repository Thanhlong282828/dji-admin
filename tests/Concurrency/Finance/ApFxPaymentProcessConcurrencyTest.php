<?php

namespace Tests\Concurrency\Finance;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Company\Domain\Models\Company;
use Modules\Finance\Application\Services\AccountingPostingPolicyService;
use Modules\Finance\Application\Services\FxRateService;
use Modules\Finance\Application\Services\SourceAccountingPostingService;
use PHPUnit\Framework\Attributes\Group;
use Tests\Support\MysqlProcessRaceRunner;
use Tests\TestCase;

#[Group('mysql-concurrency')]
final class ApFxPaymentProcessConcurrencyTest extends TestCase
{
    public function test_two_workers_create_one_supplier_payment_and_one_economic_effect(): void
    {
        self::assertSame('schema_admin_testing', DB::connection()->getDatabaseName());
        $token = Str::lower(Str::random(10));
        $maker = User::factory()->create(['email' => "ap-fx-payment-maker-{$token}@example.test"]);
        $checker = User::factory()->create(['email' => "ap-fx-payment-checker-{$token}@example.test"]);
        $company = Company::factory()->create(['code' => 'APFXP-'.$token, 'currency_code' => 'VND', 'functional_currency_code' => 'VND']);
        $branch = (int) DB::table('branches')->insertGetId(['company_id' => $company->id, 'code' => 'HQ-'.$token, 'name' => 'AP FX Payment Race', 'status' => 'active', 'timezone' => 'Asia/Ho_Chi_Minh', 'created_at' => now(), 'updated_at' => now()]);
        $vendor = (int) DB::table('procurement_vendors')->insertGetId(['company_id' => $company->id, 'code' => 'V-'.$token, 'name' => 'AP FX Payment Vendor', 'status' => 'active', 'created_by' => $maker->id, 'created_at' => now(), 'updated_at' => now()]);
        $request = (int) DB::table('purchase_requests')->insertGetId(['company_id' => $company->id, 'branch_id' => $branch, 'requester_id' => $maker->id, 'reference' => 'PR-'.$token, 'requested_date' => '2026-10-09', 'reason' => 'AP FX payment race', 'status' => 'approved', 'version' => 1, 'created_at' => now(), 'updated_at' => now()]);
        $order = (int) DB::table('purchase_orders')->insertGetId(['company_id' => $company->id, 'branch_id' => $branch, 'purchase_request_id' => $request, 'vendor_id' => $vendor, 'reference' => 'PO-'.$token, 'currency' => 'USD', 'order_date' => '2026-10-09', 'status' => 'received', 'subtotal_minor' => 10_000, 'created_by' => $maker->id, 'approved_by' => $checker->id, 'approved_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
        $bill = 0;

        try {
            $posting = app(SourceAccountingPostingService::class);
            $expense = $posting->accountEvidence($company->id, '5100', 'VND', $maker->id)['id'];
            $ap = $posting->accountEvidence($company->id, '2100', 'VND', $maker->id)['id'];
            $cash = $posting->accountEvidence($company->id, '1100', 'VND', $maker->id)['id'];
            $usdLedger = $this->account($company->id, $maker->id, '1101-'.$token, 'USD Bank', 'asset', 'debit', 'USD');
            $usdAccount = (int) DB::table('treasury_financial_accounts')->insertGetId(['company_id' => $company->id, 'branch_id' => $branch, 'accounting_account_id' => $usdLedger, 'code' => 'USD-'.$token, 'name' => 'USD Bank', 'type' => 'bank', 'currency' => 'USD', 'status' => 'active', 'created_by' => $maker->id, 'created_at' => now(), 'updated_at' => now()]);
            $recognitionPolicy = $this->policy($company->id, $maker, $checker, 'SERVICE_VENDOR_BILL_POSTED', 'service_vendor_bill', [['entry_side' => 'debit', 'line_role' => 'EXPENSE', 'account_id' => $expense], ['entry_side' => 'credit', 'line_role' => 'AP', 'account_id' => $ap]]);
            $paymentPolicy = $this->policy($company->id, $maker, $checker, 'VENDOR_PAYMENT', 'vendor_payment', [['entry_side' => 'debit', 'line_role' => 'AP', 'account_id' => $ap], ['entry_side' => 'credit', 'line_role' => 'CASH', 'account_id' => $cash]]);
            $bill = (int) DB::table('vendor_bills')->insertGetId(['company_id' => $company->id, 'branch_id' => $branch, 'vendor_id' => $vendor, 'purchase_order_id' => $order, 'invoice_number' => 'APFX-PAY-'.$token, 'invoice_date' => '2026-10-09', 'due_date' => '2026-11-09', 'currency' => 'USD', 'subtotal_minor' => 10_000, 'tax_minor' => 0, 'total_minor' => 10_000, 'tax_status' => 'configuration_required', 'status' => 'approved', 'accounting_posting_policy_id' => $recognitionPolicy, 'accounting_posting_policy_version' => 1, 'created_by' => $maker->id, 'approved_by' => $checker->id, 'approved_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
            $recognitionJournal = $posting->postPolicyBacked($company->id, $branch, 'vendor_bill', $bill, 'service_vendor_bill', '2026-10-09', 'VND', [['account_code' => '5100', 'debit' => 2_500_000, 'credit' => 0, 'description' => 'AP FX recognition'], ['account_code' => '2100', 'debit' => 0, 'credit' => 2_500_000, 'description' => 'AP FX recognition']], $checker->id, $recognitionPolicy, 1);
            DB::table('vendor_bills')->where('id', $bill)->update(['accounting_journal_id' => $recognitionJournal]);
            $recognition = (int) DB::table('supplier_invoice_fx_recognitions')->insertGetId(['company_id' => $company->id, 'branch_id' => $branch, 'vendor_bill_id' => $bill, 'transaction_currency' => 'USD', 'transaction_amount_minor' => 10_000, 'functional_currency' => 'VND', 'functional_amount_minor' => 2_500_000, 'fx_rate_snapshot_id' => null, 'rate_decimal' => '25000.000000000000', 'rate_date' => '2026-10-09', 'rate_type' => 'spot', 'source_reference' => 'APFX-RECOGNITION-'.$token, 'accounting_policy_id' => $recognitionPolicy, 'accounting_policy_version' => 1, 'accounting_journal_id' => $recognitionJournal, 'checksum' => hash('sha256', $token), 'created_by' => $checker->id, 'created_at' => now()]);
            $this->rate($company->id, $maker, $checker, $token);

            $payload = ['company_id' => $company->id, 'actor_id' => $checker->id, 'vendor_bill_id' => $bill, 'financial_account_id' => $usdAccount, 'payment_date' => '2026-10-10', 'amount_minor' => 10_000, 'method' => 'bank_transfer', 'reference' => 'APFX-PAY-RACE-'.$token];
            $race = (new MysqlProcessRaceRunner)->run('ap_fx_payment', $payload, $payload);

            self::assertSame([0, 0], $race['exit_codes'], implode("\n", $race['outputs']));
            self::assertSame(1, DB::table('ap_payments')->where(['company_id' => $company->id, 'reference' => $payload['reference']])->count());
            self::assertSame(1, DB::table('ap_payment_allocations')->where('vendor_bill_id', $bill)->count());
            self::assertSame(1, DB::table('supplier_payment_fx_settlements')->where('supplier_invoice_fx_recognition_id', $recognition)->count());
            self::assertSame(1, DB::table('finance_fx_rate_snapshots')->where('source_type', 'supplier_payment_allocation_settlement')->count());
            self::assertSame(1, DB::table('payroll_accounting_journals')->where(['company_id' => $company->id, 'source_type' => 'ap_payment'])->count());
            self::assertSame(1, DB::table('treasury_transactions')->where(['company_id' => $company->id, 'source_type' => 'ap_payment'])->count());
            self::assertSame('paid', DB::table('vendor_bills')->where('id', $bill)->value('status'));
            self::assertSame($paymentPolicy, (int) DB::table('supplier_payment_fx_settlements')->where('supplier_invoice_fx_recognition_id', $recognition)->value('accounting_policy_id'));
        } finally {
            $paymentIds = DB::table('ap_payments')->where('company_id', $company->id)->pluck('id');
            $allocationIds = DB::table('ap_payment_allocations')->whereIn('payment_id', $paymentIds)->pluck('id');
            $journalIds = DB::table('payroll_accounting_journals')->where('company_id', $company->id)->pluck('id');
            DB::table('supplier_payment_fx_settlements')->whereIn('ap_payment_allocation_id', $allocationIds)->delete();
            DB::table('finance_fx_rate_snapshots')->where('company_id', $company->id)->delete();
            DB::table('treasury_transactions')->where('company_id', $company->id)->delete();
            DB::table('ap_payment_allocations')->whereIn('id', $allocationIds)->delete();
            DB::table('ap_payments')->whereIn('id', $paymentIds)->delete();
            DB::table('supplier_invoice_fx_recognitions')->where('vendor_bill_id', $bill)->delete();
            DB::table('vendor_bills')->where('id', $bill)->update(['accounting_journal_id' => null, 'accounting_posting_policy_id' => null]);
            DB::table('accounting_journal_source_evidence')->whereIn('journal_id', $journalIds)->delete();
            DB::table('payroll_accounting_journal_lines')->whereIn('journal_id', $journalIds)->delete();
            DB::table('payroll_accounting_journals')->whereIn('id', $journalIds)->delete();
            DB::table('vendor_bills')->where('id', $bill)->delete();
            DB::table('treasury_financial_accounts')->where('company_id', $company->id)->delete();
            DB::table('accounting_posting_policy_lines')->whereIn('accounting_posting_policy_id', DB::table('accounting_posting_policies')->where('company_id', $company->id)->select('id'))->delete();
            DB::table('accounting_posting_policies')->where('company_id', $company->id)->delete();
            DB::table('accounting_periods')->where('company_id', $company->id)->delete();
            DB::table('accounting_accounts')->where('company_id', $company->id)->delete();
            DB::table('finance_fx_rates')->where('company_id', $company->id)->delete();
            DB::table('purchase_orders')->where('id', $order)->delete();
            DB::table('purchase_requests')->where('id', $request)->delete();
            DB::table('procurement_vendors')->where('id', $vendor)->delete();
            DB::table('branches')->where('id', $branch)->delete();
            $company->delete();
            $maker->forceDelete();
            $checker->forceDelete();
        }
    }

    private function account(int $company, int $actor, string $code, string $name, string $type, string $normal, string $currency): int
    {
        return (int) DB::table('accounting_accounts')->insertGetId(['uuid' => (string) Str::uuid(), 'company_id' => $company, 'code' => $code, 'name' => $name, 'account_type' => $type, 'normal_balance' => $normal, 'currency' => $currency, 'allow_posting' => true, 'status' => 'active', 'effective_from' => '2026-01-01', 'created_by' => $actor, 'created_at' => now(), 'updated_at' => now()]);
    }

    /** @param list<array{entry_side:string,line_role:string,account_id:int}> $lines */
    private function policy(int $company, User $maker, User $checker, string $event, string $source, array $lines): int
    {
        $service = app(AccountingPostingPolicyService::class);
        $id = $service->create($company, $maker, ['policy_key' => $event.'-'.Str::lower(Str::random(6)), 'source_type' => $source, 'business_event' => $event, 'classification' => null, 'version' => 1, 'currency' => 'VND', 'effective_from' => '2026-01-01', 'lines' => $lines]);
        $service->submit($company, $id, $maker);
        $service->approve($company, $id, $checker);

        return $id;
    }

    private function rate(int $company, User $maker, User $checker, string $token): void
    {
        $service = app(FxRateService::class);
        $id = $service->create($company, $maker, ['base_currency' => 'USD', 'quote_currency' => 'VND', 'rate_decimal' => '25000.000000000000', 'rate_date' => '2026-10-10', 'rate_type' => 'spot', 'source_reference' => 'APFX-PAY-RATE-'.$token, 'effective_from' => '2026-10-10', 'effective_to' => '2026-10-10', 'idempotency_key' => (string) Str::uuid()]);
        $service->transition($company, $id, 'submit', $maker);
        $service->transition($company, $id, 'approve', $checker);
        $service->transition($company, $id, 'activate', $checker);
    }
}
